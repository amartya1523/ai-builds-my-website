<?php

namespace App\Services\Build;

use App\Models\Site;
use App\Models\Source;
use App\Services\Ai\AiUnavailableException;
use App\Services\Facts\AiFactExtractor;
use App\Services\Facts\FactResolver;
use App\Services\Facts\ParserFacts;
use App\Services\Ingest\FileReader;
use App\Services\Ingest\InjectionGuard;
use App\Services\Ingest\LinkReader;
use App\Services\Ingest\WebSearch;

/**
 * read sources -> remove hidden instructions -> search the web -> extract facts (parser + AI, grounded)
 * -> resolve conflicts into questions -> write the site (only when nothing blocking is left).
 */
class BuildPipeline
{
    public function __construct(
        private FileReader $files,
        private LinkReader $links,
        private WebSearch $search,
        private InjectionGuard $guard,
        private ParserFacts $parser,
        private AiFactExtractor $extractor,
        private FactResolver $resolver,
        private SiteWriter $writer,
    ) {
    }

    public function run(Site $site): void
    {
        foreach ($site->sources()->where('status', 'pending')->get() as $source) {
            $this->ingest($source);
        }

        if (config('aiwb.search_enabled')) {
            $this->searchWeb($site);
        }

        $this->extractFacts($site);
        $this->resolver->resolve($site);
        $this->continue($site);
    }

    /** Called after every answer: write the site once nothing blocking is left. */
    public function continue(Site $site): void
    {
        $site->refresh();
        if ($site->openBlockingQuestions()->exists()) {
            $site->update(['status' => $site->status === 'published' ? 'published' : 'needs_input']);

            return;
        }
        if ($site->sections()->doesntExist()) {
            $this->writer->build($site);
        } elseif ($site->status === 'needs_input') {
            $site->update(['status' => 'ready']);
        }
    }

    /** Re-read new sources (owner notes / files added later) and refresh facts without touching edited sections. */
    public function refreshFacts(Site $site): void
    {
        foreach ($site->sources()->where('status', 'pending')->get() as $source) {
            $this->ingest($source);
        }
        $this->extractFacts($site, onlyNew: true);
        $this->resolver->resolve($site);
        $this->continue($site);
    }

    public function ingest(Source $source): void
    {
        if ($source->kind === 'file') {
            $r = $this->files->read($source->path, (string) $source->mime, $source->label);
            $source->fill(['status' => $r['status'], 'status_note' => $r['note'], 'content' => $r['text']]);
        } elseif (in_array($source->kind, ['website', 'social', 'google_business'], true)) {
            $r = $this->links->read($source->url, $source->kind);
            $source->fill(['status' => $r['status'], 'status_note' => $r['note'], 'content' => $r['text'], 'meta' => $r['meta']]);
        } else {
            $source->status = 'ok';
        }

        // Hidden page text gets scanned too (that is where instructions for AI tools usually hide).
        $hidden = (string) ($source->meta['hidden_text'] ?? '');
        $text = trim(($hidden !== '' ? $hidden."\n" : '').(string) $source->content);

        if ($text !== '') {
            $scan = $this->guard->scan($text);
            $source->content = mb_substr($scan['clean'], 0, config('aiwb.max_source_chars') * 2);
            $source->injection_flagged = $scan['flagged'];
            $source->injection_score = $scan['score'];
            if ($scan['flagged']) {
                $meta = $source->meta ?? [];
                $meta['removed_instructions'] = $scan['removed'];
                $source->meta = $meta;
                $source->status_note = trim(($source->status_note ? $source->status_note.' ' : '').
                    'We found text that tries to give instructions to the AI and ignored it ('.count($scan['removed']).' line(s)).');
            }
        }

        $source->save();
    }

    private function searchWeb(Site $site): void
    {
        if ($site->sources()->whereIn('kind', ['web_search'])->exists()) {
            return;
        }

        // 1) Real places with this name in this city: if more than one, the owner must pick.
        $places = $this->search->places($site->business_name, $site->city);
        if ($places['candidates'] && ! $site->questions()->where('kind', 'choose_identity')->exists()) {
            $count = count($places['candidates']);
            $site->questions()->create([
                'kind' => 'choose_identity',
                'field' => 'identity',
                'blocking' => true,
                'prompt' => $count > 1
                    ? "We found {$count} places called “{$site->business_name}” on the map. Which one is yours?"
                    : "We found a place called “{$site->business_name}” on the map. Is this your business?",
                'options' => array_slice($places['candidates'], 0, 6),
            ]);
        }

        // 2) General web mentions: useful hints, never trusted until the owner confirms.
        $query = '"'.$site->business_name.'" '.$site->city;
        $web = $this->search->web($query);
        $content = collect($web['results'])->map(fn ($r) => "{$r['title']}\n{$r['url']}\n{$r['snippet']}")->implode("\n\n");

        $source = Source::create([
            'site_id' => $site->id,
            'kind' => 'web_search',
            'label' => 'Web search: '.$query,
            'url' => 'https://duckduckgo.com/?q='.urlencode($query),
            'status' => $web['status'] === 'ok' && $content !== '' ? 'ok' : ($web['status'] === 'ok' ? 'skipped' : $web['status']),
            'status_note' => $web['note'] ?? 'Search results are hints only. Nothing from here is used unless you confirm it.',
            'content' => $content ?: null,
            'meta' => ['results' => $web['results']],
        ]);

        if ($content !== '') {
            $scan = $this->guard->scan($content);
            $source->update(['content' => $scan['clean'], 'injection_flagged' => $scan['flagged']]);
        }
    }

    private function extractFacts(Site $site, bool $onlyNew = false): void
    {
        $sources = $site->sources()->whereIn('status', ['ok', 'blocked'])->get();
        if ($onlyNew) {
            $sources = $sources->filter(fn ($s) => $s->facts()->doesntExist() && $s->kind !== 'directory');
        }

        foreach ($sources as $source) {
            if ($source->kind === 'directory') {
                continue;
            }
            foreach ($this->parser->extract($source) as $f) {
                $site->facts()->create($f + ['source_id' => $source->id, 'origin' => 'parser']);
            }
        }

        try {
            $result = $this->extractor->extract($site, $sources->where('status', 'ok')->where('kind', '!=', 'directory')->values());
            foreach ($sources->where('status', 'ok') as $source) {
                if (in_array($source->kind, ['file', 'website', 'owner_note'], true) && AiFactExtractor::looksLikePriceList((string) $source->content)) {
                    $prices = $this->extractor->extractPrices($site, $source);
                    $result['facts'] = array_merge(
                        array_values(array_filter($result['facts'], fn ($f) => ! ($f['field'] === 'price_item' && $f['source_id'] === $source->id))),
                        $prices['facts']
                    );
                    $result['dropped'] = array_merge($result['dropped'], $prices['dropped']);
                }
            }
            foreach ($result['facts'] as $f) {
                $exists = $site->facts()->where('field', $f['field'])->where('source_id', $f['source_id'])->get()
                    ->contains(fn ($e) => \App\Services\Facts\Normalize::key($f['field'], $e->value) === \App\Services\Facts\Normalize::key($f['field'], $f['value']));
                if (! $exists) {
                    $site->facts()->create($f + ['origin' => 'ai']);
                }
            }
            $site->update(['ai_status' => 'ok', 'ai_error' => count($result['dropped']) ? count($result['dropped']).' AI fact(s) dropped: not found in the cited source.' : null]);
        } catch (AiUnavailableException $e) {
            $site->update(['ai_status' => 'fallback', 'ai_error' => 'AI unavailable while reading sources: '.$e->getMessage().' Only directly readable details (phones, emails, links, structured data) were used. You can retry.']);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Site;
use App\Services\Build\SiteWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function update(Request $request, Site $site, Section $section): RedirectResponse
    {
        abort_unless($section->site_id === $site->id, 404);
        $input = $request->input('content', []);
        $content = $section->content;

        foreach ($content as $key => $value) {
            if (is_array($value) && array_key_exists($key, $input)) {
                $content[$key] = $this->parseList($key, (string) $input[$key], $value);
            } elseif (! is_array($value) && array_key_exists($key, $input)) {
                $content[$key] = trim(strip_tags((string) $input[$key]));
            }
        }

        $section->update(['content' => $content, 'written_by' => 'owner', 'removed_claims' => null]);

        return back()->with('status', $section->label().' saved.')->withFragment('section-'.$section->id);
    }

    public function regenerate(Request $request, Site $site, Section $section, SiteWriter $writer): RedirectResponse
    {
        abort_unless($section->site_id === $site->id, 404);
        @set_time_limit(120);
        $writer->regenerate($section, $request->input('instruction'));
        $fresh = $section->fresh();

        return back()->with(
            $site->fresh()->ai_status === 'fallback' ? 'error' : 'status',
            $site->fresh()->ai_status === 'fallback'
                ? 'The AI is not responding right now, so this section uses plain template text. Try again in a minute.'
                : $fresh->label().' regenerated.'
        )->withFragment('section-'.$section->id);
    }

    public function toggle(Site $site, Section $section): RedirectResponse
    {
        abort_unless($section->site_id === $site->id, 404);
        $section->update(['visible' => ! $section->visible]);

        return back()->withFragment('section-'.$section->id);
    }

    public function move(Request $request, Site $site, Section $section): RedirectResponse
    {
        abort_unless($section->site_id === $site->id, 404);
        $sections = $site->sections()->get()->values();
        $i = $sections->search(fn ($s) => $s->id === $section->id);
        $j = $request->input('dir') === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $sections->count()) {
            [$a, $b] = [$sections[$i], $sections[$j]];
            [$pa, $pb] = [$a->position, $b->position];
            $a->update(['position' => $pb === $pa ? $pa + ($j > $i ? 1 : -1) : $pb]);
            $b->update(['position' => $pa]);
        }

        return back()->withFragment('section-'.$section->id);
    }

    /** Lists are edited as one item per line; "name | description" or "name | price". */
    private function parseList(string $key, string $text, array $old): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), fn ($l) => $l !== ''));
        $first = $old[0] ?? null;

        if (is_array($first) || in_array($key, ['items', 'links'], true)) {
            $fields = is_array($first) ? array_keys($first) : ($key === 'links' ? ['label', 'url'] : ['name', 'description']);

            return array_map(function ($line) use ($fields) {
                $parts = array_map('trim', explode('|', $line, count($fields)));
                $row = [];
                foreach ($fields as $i => $f) {
                    $row[$f] = strip_tags($parts[$i] ?? '');
                }

                return $row;
            }, $lines);
        }

        return array_map('strip_tags', $lines);
    }
}

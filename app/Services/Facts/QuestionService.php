<?php

namespace App\Services\Facts;

use App\Models\Question;
use App\Models\Site;
use App\Models\Source;

class QuestionService
{
    public function __construct(private FactResolver $resolver)
    {
    }

    /**
     * @param  array{option?:string|int|null, text?:?string, skip?:bool}  $input
     */
    public function answer(Question $q, array $input): void
    {
        $site = $q->site;
        $text = trim((string) ($input['text'] ?? ''));
        $option = $input['option'] ?? null;

        if (! empty($input['skip'])) {
            $q->update(['status' => 'skipped', 'answer' => null]);
            $this->resolver->resolve($site);

            return;
        }

        match ($q->kind) {
            'choose_identity' => $this->chooseIdentity($site, $q, $option),
            'resolve_conflict' => $this->resolveConflict($site, $q, $option, $text),
            'confirm' => $this->confirm($site, $q, $option),
            'missing' => $this->fillMissing($site, $q, $text),
            default => null,
        };

        $q->update(['status' => 'answered', 'answer' => $text !== '' ? $text : (string) $option]);
        $this->resolver->resolve($site->fresh());
    }

    private function chooseIdentity(Site $site, Question $q, $option): void
    {
        $candidates = $q->options ?? [];
        if ($option === 'none' || ! isset($candidates[(int) $option])) {
            $site->update(['identity' => ['none' => true]]);
            // Listings that are not this business must not feed the site.
            $site->sources()->where('kind', 'directory')->each(fn ($s) => $s->facts()->update(['status' => 'rejected']));

            return;
        }

        $c = $candidates[(int) $option];
        $site->update(['identity' => $c]);

        $source = Source::create([
            'site_id' => $site->id,
            'kind' => 'directory',
            'label' => 'OpenStreetMap listing you confirmed',
            'url' => $c['osm_url'] ?? null,
            'status' => 'ok',
            'content' => collect($c)->map(fn ($v, $k) => is_scalar($v) ? "$k: $v" : null)->filter()->implode("\n"),
        ]);

        $add = fn ($field, $value, $ev) => $value ? $site->facts()->create([
            'source_id' => $source->id, 'field' => $field, 'value' => $field === 'phone' ? Normalize::phone($value) : $value,
            'evidence' => $ev, 'origin' => 'parser',
        ]) : null;

        $add('address', $c['address'] ?? null, 'Map listing address');
        $add('phone', $c['phone'] ?? null, 'Map listing phone');
        $add('website', $c['website'] ?? null, 'Map listing website');
        $add('hours', $c['opening_hours'] ?? null, 'Map listing hours');
    }

    private function resolveConflict(Site $site, Question $q, $option, string $text): void
    {
        $options = $q->options ?? [];
        $value = $text !== '' ? $text : ($options[(int) $option]['value'] ?? null);
        if (! $value) {
            return;
        }
        $site->facts()->create([
            'field' => $q->field, 'value' => $q->field === 'phone' ? Normalize::phone($value) : $value,
            'evidence' => 'Chosen by the owner', 'origin' => 'owner', 'status' => 'accepted',
        ]);
    }

    private function confirm(Site $site, Question $q, $option): void
    {
        $factId = $q->options[0]['fact_id'] ?? null;
        $fact = $factId ? $site->facts()->find($factId) : null;
        if (! $fact) {
            return;
        }
        $option === 'yes'
            ? $fact->update(['origin' => 'owner_confirmed', 'status' => 'accepted'])
            : $fact->update(['status' => 'rejected']);
    }

    private function fillMissing(Site $site, Question $q, string $text): void
    {
        if ($text === '') {
            return;
        }
        $note = Source::create([
            'site_id' => $site->id, 'kind' => 'owner_note', 'label' => 'Your answer', 'status' => 'ok', 'content' => $text,
        ]);

        if ($q->field === 'contact') {
            $rest = $text;
            if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
                foreach ($m[0] as $email) {
                    $site->facts()->create(['source_id' => $note->id, 'field' => 'email', 'value' => strtolower($email), 'evidence' => $text, 'origin' => 'owner', 'status' => 'accepted']);
                    $rest = str_replace($email, '', $rest);
                }
            }
            if (preg_match_all('/\+?[\d][\d\s\-]{8,}\d/', $rest, $m)) {
                foreach ($m[0] as $phone) {
                    $site->facts()->create(['source_id' => $note->id, 'field' => 'phone', 'value' => Normalize::phone($phone), 'evidence' => $text, 'origin' => 'owner', 'status' => 'accepted']);
                    $rest = str_replace($phone, '', $rest);
                }
            }
            $rest = trim($rest, " ,;\n\t");
            if (mb_strlen($rest) > 8) {
                $site->facts()->create(['source_id' => $note->id, 'field' => 'address', 'value' => $rest, 'evidence' => $text, 'origin' => 'owner', 'status' => 'accepted']);
            }

            return;
        }

        $site->facts()->create(['source_id' => $note->id, 'field' => $q->field, 'value' => $text, 'evidence' => $text, 'origin' => 'owner', 'status' => 'accepted']);
    }
}

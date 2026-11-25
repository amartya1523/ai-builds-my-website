<?php

namespace App\Services\Facts;

use App\Models\Fact;
use App\Models\Question;
use App\Models\Site;
use Illuminate\Support\Collection;

/**
 * Decides which facts go on the website, and turns every doubt into a question for the owner.
 *
 * Trust order: owner answers > owner's own files/links > confirmed map listing > web search (never used unconfirmed).
 */
class FactResolver
{
    private const OWNER_ORIGINS = ['owner', 'owner_confirmed'];

    public function resolve(Site $site): void
    {
        // Rebuild the open, machine-made questions; answered ones (and their effects) stay.
        $site->questions()->where('status', 'open')->whereIn('kind', ['resolve_conflict', 'confirm', 'missing'])->delete();

        $facts = $site->facts()->with('source')->where('status', '!=', 'rejected')->get();
        $site->facts()->whereIn('status', ['accepted', 'duplicate'])->whereNotIn('origin', self::OWNER_ORIGINS)->update(['status' => 'candidate']);
        $facts->each(function (Fact $f) {
            if (! in_array($f->origin, self::OWNER_ORIGINS, true) && in_array($f->status, ['accepted', 'duplicate'], true)) {
                $f->status = 'candidate';
            }
        });

        $confirmQuestions = 0;

        foreach ($facts->groupBy('field') as $field => $group) {
            $trusted = $group->filter(fn (Fact $f) => $this->trusted($site, $f));
            $untrusted = $group->reject(fn (Fact $f) => $this->trusted($site, $f));

            $acceptedKeys = in_array($field, Fact::SINGLE_VALUED, true)
                ? $this->resolveSingle($site, $field, $trusted)
                : $this->acceptAll($trusted);

            foreach ($untrusted as $fact) {
                $key = Normalize::key($field, $fact->value);
                if (in_array($key, $acceptedKeys, true)) {
                    $fact->update(['status' => 'duplicate']); // the web agrees with the owner
                    continue;
                }
                if ($confirmQuestions >= 8) {
                    continue;
                }
                $confirmQuestions++;
                $isConflict = in_array($field, Fact::SINGLE_VALUED, true) && $acceptedKeys !== [];
                $site->questions()->create([
                    'kind' => 'confirm',
                    'field' => $field,
                    'blocking' => false,
                    'prompt' => $isConflict
                        ? "The web shows a different {$this->label($field)} (“{$fact->value}”) from your own sources. Should the site use it instead?"
                        : "We found this {$this->label($field)} on the web, not in your own files: “{$fact->value}”. Is it correct?",
                    'options' => [['fact_id' => $fact->id, 'source' => $fact->source?->label, 'url' => $fact->source?->url, 'evidence' => $fact->evidence]],
                ]);
            }
        }

        $this->askForMissing($site);
    }

    public function trusted(Site $site, Fact $fact): bool
    {
        if (in_array($fact->origin, self::OWNER_ORIGINS, true)) {
            return true;
        }
        $source = $fact->source;
        if (! $source) {
            return false;
        }
        if ($source->kind === 'directory') {
            return ! empty($site->identity) && empty($site->identity['none']);
        }

        return $source->isOwnerProvided();
    }

    /** @return array<int,string> accepted normalised keys */
    private function resolveSingle(Site $site, string $field, Collection $trusted): array
    {
        if ($trusted->isEmpty()) {
            return [];
        }

        $owner = $trusted->filter(fn ($f) => in_array($f->origin, self::OWNER_ORIGINS, true))->sortByDesc('id')->first();
        if ($owner) {
            $key = Normalize::key($field, $owner->value);
            $owner->update(['status' => 'accepted']);
            foreach ($trusted as $f) {
                if ($f->id !== $owner->id) {
                    $f->update(['status' => Normalize::key($field, $f->value) === $key ? 'duplicate' : 'rejected']);
                }
            }

            return [$key];
        }

        $byKey = $trusted->groupBy(fn ($f) => Normalize::key($field, $f->value));

        if ($byKey->count() === 1) {
            $group = $byKey->first()->sortBy(fn ($f) => $this->rank($f))->values();
            $group->first()->update(['status' => 'accepted']);
            $group->slice(1)->each->update(['status' => 'duplicate']);

            return [$byKey->keys()->first()];
        }

        // Two of the owner's own sources disagree: never pick one ourselves.
        $options = $byKey->map(function ($group) {
            $first = $group->sortBy(fn ($f) => $this->rank($f))->first();

            return [
                'value' => $first->value,
                'fact_ids' => $group->pluck('id')->all(),
                'sources' => $group->map(fn ($f) => $f->source?->label ?? 'you')->unique()->values()->all(),
            ];
        })->values()->all();

        $site->questions()->create([
            'kind' => 'resolve_conflict',
            'field' => $field,
            'blocking' => true,
            'prompt' => "Your sources list different {$this->label($field, true)}. Which one should customers see?",
            'options' => $options,
        ]);

        return [];
    }

    private function acceptAll(Collection $trusted): array
    {
        $keys = [];
        foreach ($trusted->sortBy(fn ($f) => $this->rank($f)) as $f) {
            $key = Normalize::key($f->field, $f->value);
            if (in_array($key, $keys, true)) {
                $f->update(['status' => 'duplicate']);

                continue;
            }
            $keys[] = $key;
            $f->update(['status' => 'accepted']);
        }

        return $keys;
    }

    private function askForMissing(Site $site): void
    {
        $accepted = $site->facts()->where('status', 'accepted')->pluck('field')->unique();
        $openConflicts = $site->questions()->where('status', 'open')->where('kind', 'resolve_conflict')->pluck('field');
        $answered = $site->questions()->whereIn('status', ['answered', 'skipped'])->where('kind', 'missing')->pluck('field');

        $hasContact = $accepted->intersect(['phone', 'email', 'address'])->isNotEmpty()
            || $openConflicts->intersect(['phone', 'email', 'address'])->isNotEmpty();
        if (! $hasContact && ! $answered->contains('contact')) {
            $site->questions()->create([
                'kind' => 'missing', 'field' => 'contact', 'blocking' => true,
                'prompt' => 'We could not find a phone number, email or address in your sources. How should customers reach you?',
            ]);
        }

        if ($accepted->intersect(['about', 'tagline', 'service', 'category', 'specialty'])->isEmpty() && ! $answered->contains('about')) {
            $site->questions()->create([
                'kind' => 'missing', 'field' => 'about', 'blocking' => false,
                'prompt' => 'In one or two sentences, what does your business do? (We will not make this up.)',
            ]);
        }
    }

    /** Lower is better: owner answers, then files, then the website, then social, then listings. */
    private function rank(Fact $f): int
    {
        if (in_array($f->origin, self::OWNER_ORIGINS, true)) {
            return 0;
        }

        return match ($f->source?->kind) {
            'owner_note' => 1, 'file' => 2, 'website' => 3, 'google_business' => 4, 'social' => 5, 'directory' => 6, default => 9,
        };
    }

    private function label(string $field, bool $plural = false): string
    {
        $labels = ['phone' => ['phone number', 'phone numbers'], 'email' => ['email', 'emails'], 'address' => ['address', 'addresses'], 'hours' => ['opening hours', 'opening hours'], 'price_item' => ['price', 'prices']];

        return $labels[$field][$plural ? 1 : 0] ?? str_replace('_', ' ', $field).($plural ? 's' : '');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Site extends Model
{
    protected $fillable = [
        'slug', 'business_name', 'city', 'status', 'theme', 'accent_color',
        'identity', 'ai_status', 'ai_error', 'published_html', 'published_at',
    ];

    protected $casts = [
        'identity' => 'array',
        'published_at' => 'datetime',
    ];

    public static function uniqueSlug(string $name, ?string $city): string
    {
        $base = Str::slug(trim($name.' '.($city ?? ''))) ?: 'site';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    public function openBlockingQuestions()
    {
        return $this->questions()->where('status', 'open')->where('blocking', true);
    }

    /** Accepted facts grouped by field: ['phone' => ['+91…'], 'service' => [...]]. */
    public function acceptedFacts(): array
    {
        return $this->facts()->where('status', 'accepted')->orderBy('id')->get()
            ->groupBy('field')
            ->map(fn ($g) => $g->pluck('value')->unique()->values()->all())
            ->all();
    }

    public function photos()
    {
        return $this->sources()->where('kind', 'file')->where('mime', 'like', 'image/%')->get();
    }

    public function publicUrl(): string
    {
        return route('public.site', $this->slug);
    }
}

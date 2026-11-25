<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Source extends Model
{
    protected $fillable = [
        'site_id', 'kind', 'label', 'url', 'path', 'mime', 'status', 'status_note',
        'content', 'injection_score', 'injection_flagged', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'injection_flagged' => 'boolean',
        'injection_score' => 'float',
    ];

    /** Sources the owner gave us directly are trusted more than what we found on the web. */
    public function isOwnerProvided(): bool
    {
        return in_array($this->kind, ['file', 'website', 'social', 'google_business', 'owner_note'], true);
    }

    public function facts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Fact::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

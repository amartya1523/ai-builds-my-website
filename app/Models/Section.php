<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Section extends Model
{
    public const ORDER = ['hero', 'about', 'services', 'pricing', 'gallery', 'hours', 'contact', 'social'];

    public const LABELS = [
        'hero' => 'Hero', 'about' => 'About', 'services' => 'Services', 'pricing' => 'Prices',
        'gallery' => 'Gallery', 'hours' => 'Opening hours', 'contact' => 'Contact', 'social' => 'Social links',
    ];

    protected $fillable = ['site_id', 'type', 'position', 'visible', 'content', 'written_by', 'removed_claims'];

    protected $casts = [
        'content' => 'array',
        'removed_claims' => 'array',
        'visible' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? ucfirst($this->type);
    }
}

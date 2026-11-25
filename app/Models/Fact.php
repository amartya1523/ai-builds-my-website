<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fact extends Model
{
    /** Fields where a business normally has one value, so two different values are a conflict. */
    public const SINGLE_VALUED = ['phone', 'email', 'address', 'hours', 'founded'];

    protected $fillable = ['site_id', 'source_id', 'field', 'value', 'evidence', 'status', 'origin'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}

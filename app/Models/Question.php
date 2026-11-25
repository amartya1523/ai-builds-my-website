<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = ['site_id', 'kind', 'field', 'prompt', 'options', 'blocking', 'answer', 'status'];

    protected $casts = [
        'options' => 'array',
        'blocking' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

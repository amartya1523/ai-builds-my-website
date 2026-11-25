<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCall extends Model
{
    protected $fillable = ['site_id', 'purpose', 'provider', 'model', 'ok', 'latency_ms', 'error'];

    protected $casts = ['ok' => 'boolean'];
}

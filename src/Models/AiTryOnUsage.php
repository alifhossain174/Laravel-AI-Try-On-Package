<?php

namespace Vendor\LaravelAiTryon\Models;

use Illuminate\Database\Eloquent\Model;

class AiTryOnUsage extends Model
{
    protected $table = 'ai_tryon_usages';

    protected $fillable = [
        'user_id',
        'ip_address',
        'provider',
        'model',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];
}

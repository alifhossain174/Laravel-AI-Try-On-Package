<?php

namespace FahimHossain\LaravelAiTryon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiTryOnGeneration extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ai_tryon_generations';

    protected $fillable = [
        'uuid',
        'user_id',
        'ip_address',
        'product_id',
        'product_type',
        'original_user_image_path',
        'product_image_path',
        'generated_image_path',
        'status',
        'provider',
        'model',
        'error_message',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $generation): void {
            if (! $generation->uuid) {
                $generation->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}

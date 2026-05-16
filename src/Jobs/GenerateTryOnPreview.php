<?php

namespace FahimHossain\LaravelAiTryon\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use FahimHossain\LaravelAiTryon\Models\AiTryOnGeneration;
use FahimHossain\LaravelAiTryon\Services\TryOnService;

class GenerateTryOnPreview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public int $generationId)
    {
        $connection = config('ai-tryon.queue.connection');
        $queue = config('ai-tryon.queue.queue');

        if ($connection) {
            $this->onConnection((string) $connection);
        }

        if ($queue) {
            $this->onQueue((string) $queue);
        }
    }

    public function handle(TryOnService $tryOnService): void
    {
        $tryOnService->processGeneration($this->generationId);
    }

    public function failed(\Throwable $exception): void
    {
        AiTryOnGeneration::query()
            ->whereKey($this->generationId)
            ->update([
                'status' => AiTryOnGeneration::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);
    }
}

<?php

namespace Vendor\LaravelAiTryon\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Vendor\LaravelAiTryon\Http\Requests\TryOnRequest;
use Vendor\LaravelAiTryon\Jobs\GenerateTryOnPreview;
use Vendor\LaravelAiTryon\Models\AiTryOnGeneration;
use Vendor\LaravelAiTryon\Services\TryOnService;

class TryOnController extends Controller
{
    public function store(TryOnRequest $request, TryOnService $tryOnService): JsonResponse
    {
        $limit = $tryOnService->checkLimits($request->user(), $request->ip());

        if (! $limit['allowed']) {
            return response()->json([
                'message' => $limit['message'],
                'code' => 'limit_exceeded',
                'limit' => $limit['limit'],
                'premium_url' => $limit['premium_url'],
                'billing_enabled' => $limit['billing_enabled'],
            ], $limit['billing_enabled'] ? 402 : 429);
        }

        $productImage = $request->file('product_image_file') ?: (string) $request->input('product_image');
        $generation = $tryOnService->createGeneration(
            $request->file('user_image'),
            $productImage,
            $request->input('product_id'),
            $request->input('product_type', 'other'),
            $request->user(),
            $request->ip()
        );

        if (config('ai-tryon.queue.enabled', true)) {
            GenerateTryOnPreview::dispatch($generation->id);

            return response()->json([
                'message' => 'Your try-on preview is being generated.',
                'generation' => $tryOnService->generationPayload($generation->fresh()),
                'status_url' => route('ai-tryon.generations.show', $generation),
            ], 202);
        }

        $result = $tryOnService->processGeneration($generation->id);
        $generation->refresh();

        return response()->json([
            'message' => $result->success ? 'Your try-on preview is ready.' : 'The try-on preview could not be generated.',
            'generation' => $tryOnService->generationPayload($generation),
            'result' => $result->toArray(),
        ], $result->success ? 201 : 422);
    }

    public function show(Request $request, AiTryOnGeneration $generation, TryOnService $tryOnService): JsonResponse
    {
        if (! $this->canView($request, $generation)) {
            abort(404);
        }

        return response()->json([
            'generation' => $tryOnService->generationPayload($generation),
        ]);
    }

    private function canView(Request $request, AiTryOnGeneration $generation): bool
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId) {
            return (string) $generation->user_id === (string) $userId;
        }

        return $generation->user_id === null && $generation->ip_address === $request->ip();
    }
}

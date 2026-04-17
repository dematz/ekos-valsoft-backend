<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Item;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AiPredictionService
{
    private string $model;

    public function __construct()
    {
        $this->model = config('openai.model', 'llama-3.3-70b-versatile');
    }

    public function predictRestock(Item $item): array
    {
        $history = $this->buildItemHistory($item);
        $prompt  = $this->buildPrompt($item, $history);

        if (empty(config('openai.api_key'))) {
            return $this->mockResponse();
        }

        $rawText = null;

        try {
            $response = OpenAI::chat()->create([
                'model'    => $this->model,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Eres un sistema de análisis de inventario. Responde siempre con JSON puro, sin markdown ni texto adicional.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            $rawText = $response->choices[0]->message->content;

            $parsed = json_decode(
                trim($rawText, " \n\r\t`"),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );

            return $this->normalizeResponse($parsed);

        } catch (\JsonException) {
            Log::warning('Groq returned non-JSON', ['raw' => $rawText]);

            return ['error' => 'Could not parse AI response'];

        } catch (\Throwable $e) {
            Log::error('Groq API error', ['message' => $e->getMessage()]);

            return ['error' => 'AI service unavailable'];
        }
    }

    private function buildItemHistory(Item $item): array
    {
        return AuditLog::where('model_type', 'Item')
            ->where('model_id', $item->id)
            ->where('action', 'update')
            ->whereRaw("JSON_EXTRACT(changes, '$.quantity') IS NOT NULL")
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($log) => [
                'date'         => $log->created_at->toDateString(),
                'quantity_old' => $log->changes['quantity']['old'] ?? null,
                'quantity_new' => $log->changes['quantity']['new'] ?? null,
            ])
            ->filter(fn ($e) => $e['quantity_old'] !== null && $e['quantity_new'] !== null)
            ->values()
            ->toArray();
    }

    private function buildPrompt(Item $item, array $history): string
    {
        $historyString = empty($history)
            ? 'Sin movimientos registrados.'
            : collect($history)
                ->map(fn ($e) => "{$e['date']}: {$e['quantity_old']} → {$e['quantity_new']}")
                ->implode(', ');

        return 'Basado en este historial de inventario: '
            . $historyString
            . ', el stock actual es ' . $item->quantity
            . ' y el umbral mínimo es ' . $item->min_stock_threshold
            . '. ¿En cuántos días se agotará?'
            . ' Responde en JSON con las llaves "prediction_days" (int),'
            . ' "confidence" (float) y "recommendation" (string).';
    }

    private function normalizeResponse(array $parsed): array
    {
        return [
            'prediction_days' => isset($parsed['prediction_days']) ? (int) $parsed['prediction_days'] : null,
            'confidence'      => isset($parsed['confidence'])      ? (float) $parsed['confidence']      : null,
            'recommendation'  => $parsed['recommendation']         ?? null,
        ];
    }

    private function mockResponse(): array
    {
        return [
            'prediction_days' => null,
            'confidence'      => null,
            'recommendation'  => 'Configure OPENAI_API_KEY para obtener predicciones reales.',
        ];
    }
}

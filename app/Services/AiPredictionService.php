<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Item;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiPredictionService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash');
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    public function predictRestock(Item $item): array
    {
        $history = $this->buildItemHistory($item);
        $prompt  = $this->buildPrompt($item, $history);

        return $this->callGeminiApi($prompt);
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
            ->filter(fn ($entry) => $entry['quantity_old'] !== null && $entry['quantity_new'] !== null)
            ->values()
            ->toArray();
    }

    private function buildPrompt(Item $item, array $history): string
    {
        $historyText = empty($history)
            ? 'No hay historial de cambios de stock registrado.'
            : collect($history)
                ->map(fn ($h) => "  - {$h['date']}: {$h['quantity_old']} → {$h['quantity_new']}")
                ->implode("\n");

        return <<<PROMPT
        Eres un analista experto en gestión de inventario. Analiza los siguientes datos y proporciona una predicción de reabastecimiento.

        DATOS DEL ARTÍCULO:
        - Nombre: {$item->name}
        - SKU: {$item->sku}
        - Stock actual: {$item->quantity} unidades
        - Umbral mínimo de stock: {$item->min_stock_threshold} unidades
        - Estado actual: {$item->status}
        - Precio unitario: \${$item->price}
        - Categoría: {$item->category?->name}

        HISTORIAL DE CAMBIOS DE STOCK (últimas 30 entradas):
        {$historyText}

        Responde ÚNICAMENTE con un objeto JSON con esta estructura exacta, sin markdown ni texto adicional:
        {
            "restock_needed": true/false,
            "suggested_quantity": número entero,
            "urgency": "low|medium|high",
            "estimated_days_until_stockout": número entero o null,
            "confidence": "low|medium|high",
            "reasoning": "explicación breve en español"
        }
        PROMPT;
    }

    private function callGeminiApi(string $prompt): array
    {
        if (empty($this->apiKey)) {
            return $this->mockResponse();
        }

        try {
            $response = Http::timeout(15)
                ->withQueryParameters(['key' => $this->apiKey])
                ->post($this->apiUrl, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 512,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['error' => 'AI service unavailable', 'status' => $response->status()];
            }

            $text = $response->json('candidates.0.content.parts.0.text', '');

            return json_decode(trim($text), true)
                ?? ['error' => 'Could not parse AI response', 'raw' => $text];

        } catch (\Throwable $e) {
            Log::error('Gemini API exception', ['message' => $e->getMessage()]);

            return ['error' => 'AI service exception', 'message' => $e->getMessage()];
        }
    }

    private function mockResponse(): array
    {
        return [
            'restock_needed'               => true,
            'suggested_quantity'           => 50,
            'urgency'                      => 'medium',
            'estimated_days_until_stockout' => 14,
            'confidence'                   => 'low',
            'reasoning'                    => 'Respuesta simulada. Configure GEMINI_API_KEY para obtener predicciones reales.',
        ];
    }
}

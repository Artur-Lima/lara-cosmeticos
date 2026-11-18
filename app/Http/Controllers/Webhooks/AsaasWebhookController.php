<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAsaasEvent;
use App\Models\AsaasWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe eventos do Asaas. Responde rápido (o Asaas penaliza filas lentas) e
 * deixa o processamento para a fila. Reentregas do mesmo evento são ignoradas.
 */
class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('services.asaas.webhook_token');
        $received = (string) $request->header('asaas-access-token', '');

        // Sem token configurado o endpoint fica fechado (falha segura).
        if ($expected === '' || ! hash_equals($expected, $received)) {
            Log::warning('Webhook Asaas recusado: token inválido', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $eventId = (string) $request->input('id');
        $event = (string) $request->input('event');

        if ($eventId === '' || $event === '') {
            return response()->json(['message' => 'Payload inválido'], 422);
        }

        try {
            $record = AsaasWebhookEvent::create([
                'event_id' => $eventId,
                'event' => $event,
                'payment_id' => $request->input('payment.id'),
                'payload' => $request->all(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        ProcessAsaasEvent::dispatch($record->id);

        return response()->json(['received' => true]);
    }
}

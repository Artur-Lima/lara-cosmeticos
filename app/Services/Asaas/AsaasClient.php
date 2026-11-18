<?php

namespace App\Services\Asaas;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente mínimo da API v3 do Asaas (só o que a loja usa).
 * Autenticação pelo header access_token (não usa Bearer) e User-Agent obrigatório.
 */
class AsaasClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $userAgent,
    ) {}

    public static function fromConfig(): self
    {
        $env = config('services.asaas.env', 'sandbox');

        return new self(
            apiKey: (string) config('services.asaas.key'),
            baseUrl: $env === 'production' ? 'https://api.asaas.com/v3' : 'https://api-sandbox.asaas.com/v3',
            userAgent: 'LaraLimaCosmeticos/1.0 (Laravel; '.$env.')',
        );
    }

    /** Reaproveita o cliente do usuário no Asaas ou cria um novo (e guarda o id). */
    public function ensureCustomer(User $user): string
    {
        if ($user->asaas_customer_id) {
            return $user->asaas_customer_id;
        }

        $data = $this->post('/customers', [
            'name' => $user->name,
            'cpfCnpj' => $user->cpf,
            'email' => $user->email,
            'mobilePhone' => $user->phone,
            'externalReference' => 'user-'.$user->id,
            // A loja avisa pelo WhatsApp; evita e-mail/SMS duplicado do Asaas.
            'notificationDisabled' => true,
        ]);

        $user->asaas_customer_id = $data['id'];
        $user->save();

        return $data['id'];
    }

    /** @return array<string, mixed> */
    public function createPayment(array $payload): array
    {
        return $this->post('/payments', $payload);
    }

    /** @return array<string, mixed> */
    public function getPayment(string $paymentId): array
    {
        return $this->get("/payments/{$paymentId}");
    }

    /** @return array{encodedImage: string, payload: string, expirationDate: ?string} */
    public function pixQrCode(string $paymentId): array
    {
        return $this->get("/payments/{$paymentId}/pixQrCode");
    }

    private function http(): PendingRequest
    {
        if ($this->apiKey === '') {
            throw new AsaasException('ASAAS_API_KEY não configurada.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['access_token' => $this->apiKey, 'User-Agent' => $this->userAgent])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function post(string $path, array $body): array
    {
        return $this->handle(fn () => $this->http()->post($path, $body), $path);
    }

    private function get(string $path): array
    {
        // GET com body vazio: o Asaas responde 403 se houver body.
        return $this->handle(fn () => $this->http()->get($path), $path);
    }

    private function handle(callable $send, string $path): array
    {
        try {
            /** @var Response $response */
            $response = $send();
        } catch (ConnectionException $e) {
            Log::warning('Asaas indisponível', ['path' => $path, 'error' => $e->getMessage()]);

            throw new AsaasException('Não foi possível falar com o Asaas.', previous: $e);
        }

        if ($response->failed()) {
            $errors = collect($response->json('errors', []))->pluck('description')->filter()->implode(' ');
            Log::warning('Asaas recusou requisição', ['path' => $path, 'status' => $response->status(), 'errors' => $errors]);

            throw new AsaasException($errors ?: "Asaas respondeu HTTP {$response->status()}.");
        }

        return $response->json() ?? [];
    }
}

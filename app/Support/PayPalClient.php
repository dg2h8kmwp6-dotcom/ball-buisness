<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A thin client for the PayPal REST API.
 *
 * PayPal's API is small enough for Laravel's HTTP client — token, create
 * order, capture order, verify webhook — so there is no SDK dependency.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/
 */
class PayPalClient
{
    protected const LIVE_URL = 'https://api-m.paypal.com';

    protected const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';

    protected const TOKEN_CACHE_KEY = 'paypal.access_token';

    public function isConfigured(): bool
    {
        return filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.client_secret'));
    }

    public function isLive(): bool
    {
        return config('services.paypal.mode') === 'live';
    }

    public function baseUrl(): string
    {
        return $this->isLive() ? self::LIVE_URL : self::SANDBOX_URL;
    }

    /**
     * Create an order.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, string $requestId): array
    {
        return $this->request($requestId)
            ->post($this->baseUrl().'/v2/checkout/orders', $payload)
            ->throw()
            ->json();
    }

    /**
     * Capture the funds for an approved order.
     *
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId, string $requestId): array
    {
        // An empty body is required; PayPal rejects a bare POST.
        return $this->request($requestId)
            ->post($this->baseUrl()."/v2/checkout/orders/{$orderId}/capture", new \stdClass)
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array
    {
        return $this->request()
            ->get($this->baseUrl()."/v2/checkout/orders/{$orderId}")
            ->throw()
            ->json();
    }

    /**
     * Ask PayPal whether a webhook really came from them.
     *
     * @param  array<string, string>  $headers  the PAYPAL-* transmission headers
     * @param  array<string, mixed>  $event  the decoded webhook body
     */
    public function verifyWebhookSignature(array $headers, array $event): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (blank($webhookId)) {
            return false;
        }

        $required = [
            'auth_algo' => $headers['paypal-auth-algo'] ?? null,
            'cert_url' => $headers['paypal-cert-url'] ?? null,
            'transmission_id' => $headers['paypal-transmission-id'] ?? null,
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? null,
            'transmission_time' => $headers['paypal-transmission-time'] ?? null,
        ];

        foreach ($required as $value) {
            if (blank($value)) {
                return false;
            }
        }

        $response = $this->request()->post(
            $this->baseUrl().'/v1/notifications/verify-webhook-signature',
            [
                ...$required,
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ],
        );

        if ($response->failed()) {
            return false;
        }

        return $response->json('verification_status') === 'SUCCESS';
    }

    /**
     * An OAuth2 access token, cached until shortly before it expires.
     */
    public function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.paypal.client_id'),
                (string) config('services.paypal.client_secret'),
            )
            ->timeout(15)
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $this->throwUnlessSuccessful($response, 'Could not obtain a PayPal access token.');

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('PayPal returned no access token.');
        }

        // Expire our copy a minute early so we never present a stale token.
        $lifetime = max((int) $response->json('expires_in', 3600) - 60, 60);

        Cache::put(self::TOKEN_CACHE_KEY, $token, $lifetime);

        return $token;
    }

    public function forgetAccessToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    protected function request(?string $requestId = null): PendingRequest
    {
        $request = Http::withToken($this->accessToken())
            ->asJson()
            ->acceptJson()
            ->timeout(30);

        if ($requestId !== null) {
            // Makes retries safe: PayPal returns the original result rather
            // than creating or capturing a second time.
            $request = $request->withHeaders(['PayPal-Request-Id' => $requestId]);
        }

        return $request;
    }

    protected function throwUnlessSuccessful(Response $response, string $message): void
    {
        if ($response->failed()) {
            throw new RuntimeException($message.' HTTP '.$response->status());
        }
    }
}

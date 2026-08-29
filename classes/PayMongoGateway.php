<?php
declare(strict_types=1);

final class PayMongoGateway
{
    private const CHECKOUT_ENDPOINT = 'https://api.paymongo.com/v1/checkout_sessions';

    public function __construct(private string $secretKey)
    {
        if (!str_starts_with($secretKey, 'sk_test_') && !str_starts_with($secretKey, 'sk_live_')) {
            throw new RuntimeException('PayMongo is not configured. Set PAYMONGO_SECRET_KEY on the server.');
        }
    }

    public function createGcashCheckout(array $booking, int $amountCentavos, string $baseUrl): array
    {
        if ($baseUrl === '' || !str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('APP_BASE_URL must be a public HTTPS address for PayMongo checkout.');
        }
        $reference = 'RES-' . $booking['reservation_id'] . '-' . bin2hex(random_bytes(4));
        $payload = ['data' => ['attributes' => [
            'cancel_url' => $baseUrl . '/index.php?payment=cancelled',
            'description' => 'Laguerta Picklebook court reservation #' . $booking['reservation_id'],
            'line_items' => [[
                'amount' => $amountCentavos, 'currency' => 'PHP',
                'description' => $booking['court_name'] . ' on ' . $booking['reservation_date'],
                'name' => 'Pickleball court reservation', 'quantity' => 1,
            ]],
            'payment_method_types' => ['gcash'],
            'reference_number' => $reference,
            'send_email_receipt' => false, 'show_description' => true, 'show_line_items' => true,
            'success_url' => $baseUrl . '/payment-success.php?reservation_id=' . $booking['reservation_id'],
            'metadata' => ['reservation_id' => (string) $booking['reservation_id']],
        ]]];

        $curl = curl_init(self::CHECKOUT_ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
                'Content-Type: application/json',
                'Idempotency-Key: reservation-' . $booking['reservation_id'],
            ],
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        if ($response === false || $curlError !== '') {
            throw new RuntimeException('PayMongo could not be reached. Try again shortly.');
        }
        $decoded = json_decode($response, true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded)) {
            error_log('PayMongo checkout error: ' . $response);
            throw new RuntimeException('PayMongo could not create the GCash checkout.');
        }
        $session = $decoded['data'] ?? [];
        $checkoutUrl = $session['attributes']['checkout_url'] ?? '';
        if (!is_string($checkoutUrl) || $checkoutUrl === '') {
            throw new RuntimeException('PayMongo returned an invalid checkout session.');
        }
        return ['id' => (string) ($session['id'] ?? ''), 'reference_number' => $reference, 'checkout_url' => $checkoutUrl];
    }

    public static function verifyWebhook(string $rawBody, string $signatureHeader, string $secret, string $mode): bool
    {
        if ($rawBody === '' || $signatureHeader === '' || $secret === '') return false;
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }
        $timestamp = $parts['t'] ?? '';
        $provided = $parts[$mode === 'live' ? 'li' : 'te'] ?? '';
        if ($timestamp === '' || $provided === '' || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) return false;
        return hash_equals(hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret), $provided);
    }
}

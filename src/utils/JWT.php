<?php

namespace UtiCensor\Utils;

class JWT
{
    private static $config;

    public static function init(): void
    {
        if (self::$config === null) {
            $appConfig = require __DIR__ . '/../../config/app.php';
            self::$config = $appConfig['jwt'];
        }
    }

    public static function encode(array $payload): string
    {
        self::init();
        
        $header = [
            'typ' => 'JWT',
            'alg' => self::$config['algorithm']
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + self::$config['expire'];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            self::$config['secret'],
            true
        );
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    public static function decode(string $token): ?array
    {
        self::init();
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            error_log("JWT: Invalid token format - expected 3 parts, got " . count($parts));
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $header = json_decode(self::base64UrlDecode($headerEncoded), true);
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$header || !$payload) {
            error_log("JWT: Failed to decode header or payload");
            return null;
        }

        // Verify signature
        $expectedSignature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            self::$config['secret'],
            true
        );
        $expectedSignatureEncoded = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
            error_log("JWT: Signature verification failed");
            error_log("JWT: Expected: " . $expectedSignatureEncoded);
            error_log("JWT: Got: " . $signatureEncoded);
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            error_log("JWT: Token expired");
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}


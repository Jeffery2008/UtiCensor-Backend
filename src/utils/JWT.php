<?php

namespace UtiCensor\Utils;

use UtiCensor\Utils\Logger;

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
            Logger::warning("JWT令牌格式无效", 'jwt', [
                'expected_parts' => 3,
                'actual_parts' => count($parts)
            ]);
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $header = json_decode(self::base64UrlDecode($headerEncoded), true);
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$header || !$payload) {
            Logger::warning("JWT令牌解码失败", 'jwt', [
                'header_valid' => $header !== null,
                'payload_valid' => $payload !== null
            ]);
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
            Logger::warning("JWT令牌签名验证失败", 'jwt', [
                'expected_signature' => substr($expectedSignatureEncoded, 0, 10) . '...',
                'actual_signature' => substr($signatureEncoded, 0, 10) . '...'
            ]);
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            Logger::warning("JWT令牌已过期", 'jwt', [
                'expiration_time' => date('Y-m-d H:i:s', $payload['exp']),
                'current_time' => date('Y-m-d H:i:s')
            ]);
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


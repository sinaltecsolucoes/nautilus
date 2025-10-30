<?php

/**
 * CLASSE DE SERVIÇO: JwtService
 * Local: app/Services/JwtService.php
 * Descrição: Lógica para geração e validação de JSON Web Tokens (JWT).
 * Requer uma biblioteca JWT (usaremos uma simulação).
 */

// AVISO: Em um projeto real, você usaria uma biblioteca como 'firebase/php-jwt'.
// Como estamos em MVC puro, vamos simular o mínimo necessário para fins didáticos.

class JwtService
{

    // Chave secreta definida em config.php
    const SECRET = SECRET_KEY;

    /**
     * Gera um token JWT simples (simulação).
     * @param array $payload Dados a serem incluídos (user_id, cargo).
     * @return string O token JWT gerado.
     */
    public static function generateToken(array $payload): string
    {
        $header = base66url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));

        $issuedAt = time();
        $expirationTime = $issuedAt + (TOKEN_EXPIRY_HOURS * 3600); // Expira em X horas

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $expirationTime;
        $payloadEncoded = base66url_encode(json_encode($payload));

        $signature = base66url_encode(hash_hmac('sha256', "$header.$payloadEncoded", self::SECRET, true));

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Valida um token JWT (simulação).
     * @param string $token O token recebido.
     * @return object|false O payload decodificado ou false se inválido/expirado.
     */
    public static function validateToken(string $token)
    {
        list($headerEncoded, $payloadEncoded, $signatureReceived) = explode('.', $token, 3);

        // 1. Verifica a assinatura
        $expectedSignature = base66url_encode(hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::SECRET, true));
        if (!hash_equals($expectedSignature, $signatureReceived)) {
            return false; // Assinatura inválida
        }

        // 2. Decodifica o payload e verifica a expiração
        $payload = json_decode(base66url_decode($payloadEncoded));

        if (isset($payload->exp) && $payload->exp < time()) {
            return false; // Token expirado
        }

        return $payload;
    }
}

// Funções auxiliares (necessárias para o JWT manual)
if (!function_exists('base66url_encode')) {
    function base66url_encode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
if (!function_exists('base66url_decode')) {
    function base66url_decode($data)
    {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($base64);
    }
}

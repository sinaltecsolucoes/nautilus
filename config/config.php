<?php

/**
 * CONFIGURAÇÃO GLOBAL
 * Usa variáveis de ambiente (.env) para segurança e flexibilidade.
 */

// Só declara se ainda não existir
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            [$name, $value] = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

//Carrega .env
loadEnv(__DIR__ . '/env');

//Retorna array de configuração
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'name' => getenv('DB_NAME') ?: 'db_nautilus',
    ],
    'app' => [
        'name' => getenv('APP_NAME') ?: 'Sistema de Gestão de Pescados',
        'base_url' => getenv('BASE_URL') ?: 'http://localhost/nautilus',
    ],
    'security' => [
        'secret_key' => getenv('SECRET_KEY') ?: 'chave_forte',
        'token_expiry_hours' => getenv('TOKEN_EXPIRY_HOURS') ?: '24',
    ],
    'api' => [
        'viacep' => 'https://viacep.com.br/ws/',
        'cnpja' => 'https://cnpja.com/api/v1/companies/',
    ],
];

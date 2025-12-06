<?php

namespace App\Services;

/**
 * CLASSE DE SERVIÇO: HttpService
 * Local: app/Services/HttpService.php
 * Descrição: Centraliza a comunicação com APIs externas (GET).
 */

class HttpService
{

    /**
     * Realiza uma requisição GET para uma URL específica.
     * @param string $url A URL completa da API.
     * @return array|false Os dados decodificados (array) ou false em caso de falha.
     */
    public static function get(string $url): array|false
    {
        // Inicializa a sessão cURL
        /** @var \CurlHandle $ch */
        $ch = curl_init($url);

        // Configurações básicas da requisição
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,  // Retorna a resposta como string
            CURLOPT_TIMEOUT => 10,           // Tempo limite de 10 segundos
            CURLOPT_SSL_VERIFYPEER => false, // Ignora a verificação SSL (útil para testes locais)
        ]);
        try {
            // Executa a requisição
            $response  = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Verifica erros
            if ($response === false) {
                error_log("Erro cURL: " . curl_error($ch));
                return false;
            }

            // Verifica se o status HTTP é de sucesso (200)
            if ($http_code !== 200) {
                error_log("Erro HTTP: código {$http_code} para {$url}");
                // Log de erro HTTP
                return false;
            }

            // Decodifica a resposta JSON
            $data = json_decode($response, true);

            // Verifica se a decodificação foi bem-sucedida e se a API não retornou erro (ViaCEP)
            if (json_last_error() !== JSON_ERROR_NONE || (isset($data['erro']) && $data['erro'])) {
                error_log("Erro JSON ou API retornou erro para {$url}");
                // Log de erro JSON ou API
                return false;
            }

            return $data;
        } finally {
            // Fechamento único e garantido
            curl_close($ch);
        }
    }
}
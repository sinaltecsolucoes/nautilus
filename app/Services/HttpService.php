<?php

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
    public static function get($url)
    {
        // Inicializa a sessão cURL
        $ch = curl_init();

        // Configurações básicas da requisição
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna a resposta como string
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);          // Tempo limite de 10 segundos
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignora a verificação SSL (útil para testes locais)

        // Executa a requisição
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Verifica erros
        if (curl_errno($ch)) {
            // Log de erro de cURL
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        // Verifica se o status HTTP é de sucesso (200)
        if ($http_code !== 200) {
            // Log de erro HTTP
            return false;
        }

        // Decodifica a resposta JSON
        $data = json_decode($response, true);

        // Verifica se a decodificação foi bem-sucedida e se a API não retornou erro (ViaCEP)
        if (json_last_error() !== JSON_ERROR_NONE || (isset($data['erro']) && $data['erro'])) {
            // Log de erro JSON ou API
            return false;
        }

        return $data;
    }
}

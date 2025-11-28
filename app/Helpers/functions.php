<?php

/**
 * ARQUIVO DE FUNÇÕES GLOBAIS (HELPERS)
 * Funções utilitárias acessíveis em todo o sistema.
 */

if (!function_exists('str_upper')) {
    /**
     * Converte string para MAIÚSCULO tratando acentos (UTF-8).
     * Ex: "josé da silva" -> "JOSÉ DA SILVA"
     */
    function str_upper($string)
    {
        if (empty($string) || !is_string($string)) {
            return $string;
        }
        return mb_strtoupper(trim($string), 'UTF-8');
    }
}

if (!function_exists('str_lower')) {
    /**
     * Converte string para minúsculo tratando acentos.
     * Ideal para emails.
     */
    function str_lower($string)
    {
        if (empty($string) || !is_string($string)) {
            return $string;
        }
        return mb_strtolower(trim($string), 'UTF-8');
    }
}

if (!function_exists('sanitize_input_array')) {
    /**
     * Processa um array de dados (ex: $_POST) e padroniza os textos.
     * - Converte textos normais para MAIÚSCULO.
     * - Mantém senhas, tokens e chaves inalterados.
     * - Converte emails para minúsculo.
     * * @param array $data O array de dados (geralmente $_POST).
     * @param array $extraExempt Campos extras que não devem ser tocados.
     * @return array Dados higienizados.
     */
    function sanitize_input_array(array $data, array $extraExempt = [])
    {
        // Campos que NUNCA devem ser alterados (Case Sensitive ou Sistema)
        $globalExempt = [
            'senha',
            'senha_hash',
            'password',
            'password_confirm',
            'token',
            'csrf_token',
            'hash',
            'url',
            'link',
            'id',
            'funcionario_id',
            'usuario_id' // IDs geralmente não mexemos
        ];

        // Campos que devem ser sempre MINÚSCULOS
        $forceLower = ['email', 'login', 'site', 'usuario'];

        $exemptKeys = array_merge($globalExempt, $extraExempt);

        foreach ($data as $key => $value) {
            // Se for array (ex: permissoes[]), chama recursivo ou ignora
            if (is_array($value)) continue;

            // Se estiver na lista de isenção, não toca
            if (in_array($key, $exemptKeys)) {
                continue;
            }

            // Se for email/login, força minúsculo
            if (in_array($key, $forceLower)) {
                $data[$key] = str_lower($value);
                continue;
            }

            // Regra Geral: Converte para MAIÚSCULO
            if (is_string($value)) {
                $data[$key] = str_upper($value);
            }
        }

        return $data;
    }
}

if (!function_exists('format_money_db')) {
    /**
     * Converte valor monetário BR (R$ 1.200,50) para DB (1200.50).
     */
    function format_money_db($valor)
    {
        if (empty($valor)) return 0.00;
        $valor = str_replace(['R$', ' ', '.'], '', $valor); // Remove R$, espaço e ponto de milhar
        return str_replace(',', '.', $valor); // Troca vírgula decimal por ponto
    }
}

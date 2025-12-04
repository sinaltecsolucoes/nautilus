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

if (!function_exists('format_documento')) {
    /**
     * Aplica máscara de CPF ou CNPJ.
     * @param string $doc Documento puro (apenas números ou sujo).
     * @return string Documento formatado.
     */
    function format_documento($doc)
    {
        // Remove tudo que não for dígito
        $doc = preg_replace("/\D/", '', $doc);

        $len = strlen($doc);

        if ($len === 11) {
            // CPF: 000.000.000-00
            return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $doc);
        }

        if ($len === 14) {
            // CNPJ: 00.000.000/0000-00
            return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $doc);
        }

        // Se não for nem 11 nem 14, retorna o original (evita sumir com dados errados)
        return $doc;
    }
}

if (!function_exists('format_date_db')) {
    /**
     * Converte data BR (dd/mm/yyyy) para DB (yyyy-mm-dd).
     * Se já estiver em formato ISO ou vazio, retorna como está.
     */
    function format_date_db($date)
    {
        if (empty($date)) return null;

        // Se tiver barra, assume formato BR
        if (strpos($date, '/') !== false) {
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                // Inverte para YYYY-MM-DD
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        return $date; // Já deve estar em YYYY-MM-DD
    }
}

<?php

/**
 * CLASSE DE SERVIÇO: EntidadeService
 * Local: app/Services/EntidadeService.php
 * Descrição: Lógica para busca de dados externos (CEP, CNPJ).
 */

// Define ROOT_PATH como fallback (necessário para incluir HttpService)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}
require_once 'HttpService.php';
require_once ROOT_PATH . '/config/config.php'; // Para BASE_URL e URLs das APIs

class EntidadeService
{

    /**
     * Busca informações de endereço a partir do ViaCEP.
     * @param string $cep O CEP a ser consultado (apenas números).
     * @return array|false Dados do endereço ou false em caso de erro.
     */
    public static function buscarEnderecoPorCep($cep)
    {
        $url = VIACEP_URL . $cep . '/json/';
        $data = HttpService::get($url);

        if ($data) {
            return [
                'logradouro'  => $data['logradouro'] ?? '',
                'bairro'      => $data['bairro'] ?? '',
                'cidade'      => $data['localidade'] ?? '',
                'uf'          => $data['uf'] ?? ''
            ];
        }
        return false;
    }

    /**
     * Busca informações da Pessoa Jurídica em APIs externas (CNPJá com fallback para BrasilAPI).
     * @param string $cnpj O CNPJ a ser consultado (apenas números).
     * @return array|false Dados da PJ (Razão Social, Endereço, etc.) ou false em caso de erro.
     */
    public static function buscarDadosPJPorCnpj($cnpj)
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpjLimpo) !== 14) {
            return false;
        }

        // ----------------------------------------------------
        // 1. TENTATIVA: CNPJá API (Principal)
        // ----------------------------------------------------
        $urlCnpja = "https://open.cnpja.com/office/{$cnpjLimpo}";
        $dataCnpja = HttpService::get($urlCnpja);

        if ($dataCnpja && !isset($dataCnpja['status']) && isset($dataCnpja['taxId'])) {

            $registrations = $dataCnpja['registrations'][0] ?? [];
            $address = $dataCnpja['address'] ?? [];

            return [
                'razao_social'       => strtoupper($dataCnpja['company']['name'] ?? ''),
                'nome_fantasia'      => strtoupper($dataCnpja['alias'] ?? ''),
                'inscricao_estadual' => strtoupper($registrations['number'] ?? ''),
                'tipo_pessoa'        => 'Juridica',

                // Dados de Endereço (Corrigido para garantir que o número seja mapeado)
                'cep'                => preg_replace('/\D/', '', $address['zip'] ?? ''),
                'logradouro'         => strtoupper($address['street'] ?? ''),
                'numero'             => strtoupper($address['number'] ?? ''), 
                'bairro'             => strtoupper($address['district'] ?? ''),
                'cidade'             => strtoupper($address['city'] ?? ''),
                'uf'                 => strtoupper($address['state'] ?? '')
            ];
        }

        // ----------------------------------------------------
        // 2. FALLBACK: BrasilAPI
        // ----------------------------------------------------
        $urlBrasilApi = "https://brasilapi.com.br/api/cnpj/v1/{$cnpjLimpo}";
        $dataBrasilApi = HttpService::get($urlBrasilApi);

        if ($dataBrasilApi && !isset($dataBrasilApi['type'])) {
            // Sucesso na BrasilAPI.

            return [
                'razao_social'       => strtoupper($dataBrasilApi['razao_social'] ?? ''),
                'nome_fantasia'      => strtoupper($dataBrasilApi['nome_fantasia'] ?? ''),
                'inscricao_estadual' => null, // BrasilAPI não fornece IE
                'tipo_pessoa'        => 'Juridica',

                // Dados de Endereço (A BrasilAPI NÃO fornece o número do imóvel)
                'cep'                => preg_replace('/\D/', '', $dataBrasilApi['cep'] ?? ''),
                'logradouro'         => strtoupper($dataBrasilApi['logradouro'] ?? ''),
                'numero'             => null, // Deixamos NULO para esta API e focamos no preenchimento manual
                'bairro'             => strtoupper($dataBrasilApi['bairro'] ?? ''),
                'cidade'             => strtoupper($dataBrasilApi['municipio'] ?? ''),
                'uf'                 => strtoupper($dataBrasilApi['uf'] ?? '')
            ];
        }

        // Se ambas falharem
        return false;
    }
}

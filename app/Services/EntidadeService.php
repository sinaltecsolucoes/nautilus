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
     * Busca informações da Pessoa Jurídica a partir de uma API CNPJ.
     * NOTA: A CNPJA (CNPJA_URL) geralmente requer uma chave de API para funcionar.
     * @param string $cnpj O CNPJ a ser consultado (apenas números).
     * @return array|false Dados da PJ (Razão Social, Nome Fantasia) ou false em caso de erro.
     */
    public static function buscarDadosPJPorCnpj($cnpj)
    {
        // ESTE É UM EXEMPLO GENÉRICO. A API CNPJA requer autenticação.
        // Adaptaremos para uma chamada real se for necessário, mas por agora, apenas o esqueleto.

        // Exemplo: $url = CNPJA_URL . $cnpj . '?token=SUA_CHAVE';

        // Para fins de demonstração, simulamos um resultado
        if (strlen($cnpj) === 14) {
            return [
                'razao_social' => 'Laboratório Teste Ltda',
                'nome_fantasia' => 'Lagosta Premium',
                'inscricao_estadual' => '123.456.789.000',
                'tipo_pessoa' => 'Juridica'
            ];
        }
        return false;
    }
}

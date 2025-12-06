<?php

namespace App\Services;

use App\Services\HttpService;

/**
 * CLASSE DE SERVIÇO: EntidadeService
 * Local: app/Services/EntidadeService.php
 * Descrição: Lógica para busca de dados externos (CEP, CNPJ).
 */

class EntidadeService
{
    /**
     * Busca informações de endereço a partir do ViaCEP.
     * @param string $cep O CEP a ser consultado (apenas números).
     * @return array|false Dados do endereço ou false em caso de erro.
     */
    public static function buscarEnderecoPorCep(string $cep): array|false
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

    public static function buscarDadosPJPorCnpj(string $cnpj): array|false
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpjLimpo) !== 14) {
            return false;
        }

        $options = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept: application/json\r\n"
            ],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false
            ]
        ];
        $context = stream_context_create($options);

        // 1. TENTATIVA: CNPJ.ws
        $urlCnpjWs  = CNPJW_URL . $cnpjLimpo;
        $response   = @file_get_contents($urlCnpjWs, false, $context);
        $dataCnpjWs = $response ? json_decode($response, true) : null;

        if ($dataCnpjWs && isset($dataCnpjWs['cnpj_raiz'])) {
            $end       = $dataCnpjWs['estabelecimento'] ?? [];
            $ufEmpresa = $end['estado']['sigla'] ?? '';

            // Captura inscriçaõ Estadual
            $ie      = null;
            $debugIE = "IE Vazia";

            // 1. Tenta pegar da raiz (Padrão de algumas documentações)
            $listaIEs = $dataCnpjWs['inscricoes_estaduais']
                ?? $dataCnpjWs['estabelecimento']['inscricoes_estaduais']
                ?? [];

            // Agora varre a lista encontrada
            if (is_array($listaIEs) && !empty($listaIEs)) {
                foreach ($listaIEs as $item) {
                    $num = $item['inscricao_estadual'] ?? null;

                    if ($num) {
                        $limpo = preg_replace('/\D/', '', $num);

                        // Pega o primeiro que aparecer como garantia
                        if ($ie === null) {
                            $ie      = $limpo;
                            $debugIE = "IE Capturada (Primeira)";
                        }

                        // Se for ATIVA, essa é a campeã. Substitui e encerra.
                        if (!empty($item['ativo'])) {
                            $ie      = $limpo;
                            $debugIE = "IE Capturada (Ativa)";
                            break;
                        }
                    }
                }
            }

            return [
                'razao_social'       => strtoupper($dataCnpjWs['razao_social'] ?? ''),
                'nome_fantasia'      => strtoupper($dataCnpjWs['estabelecimento']['nome_fantasia'] ?? $dataCnpjWs['razao_social']),
                'inscricao_estadual' => $ie, // Valor final
                'tipo_pessoa'        => 'Juridica',
                'cep'                => preg_replace('/\D/', '', $end['cep'] ?? ''),
                'logradouro'         => strtoupper(($end['tipo_logradouro'] ?? '') . ' ' . ($end['logradouro'] ?? '')),
                'numero'             => strtoupper($end['numero'] ?? 'S/N'),
                'complemento'        => strtoupper($end['complemento'] ?? ''),
                'bairro'             => strtoupper($end['bairro'] ?? ''),
                'cidade'             => strtoupper($end['cidade']['nome'] ?? ''),
                'uf'                 => strtoupper($ufEmpresa),
                // DEBUG VISUAL: Mostra se achou a IE no texto da fonte
                'api_origem'         => "CNPJ.ws ($debugIE)"
            ];
        }

        // 2. FALLBACK: BrasilAPI
        $urlBrasilApi   = BRASILAPI_CNPJ_URL . $cnpjLimpo;
        $responseBrasil = @file_get_contents($urlBrasilApi, false, $context);
        $dataBrasilApi  = $responseBrasil ? json_decode($responseBrasil, true) : null;

        if ($dataBrasilApi && !isset($dataBrasilApi['type'])) {
            return [
                'razao_social'       => strtoupper($dataBrasilApi['razao_social'] ?? ''),
                'nome_fantasia'      => strtoupper($dataBrasilApi['nome_fantasia'] ?? ''),
                'inscricao_estadual' => null,
                'tipo_pessoa'        => 'Juridica',
                'cep'                => preg_replace('/\D/', '', $dataBrasilApi['cep'] ?? ''),
                'logradouro'         => strtoupper($dataBrasilApi['logradouro'] ?? ''),
                'numero'             => '',
                'complemento'        => strtoupper($dataBrasilApi['complemento'] ?? ''),
                'bairro'             => strtoupper($dataBrasilApi['bairro'] ?? ''),
                'cidade'             => strtoupper($dataBrasilApi['municipio'] ?? ''),
                'uf'                 => strtoupper($dataBrasilApi['uf'] ?? ''),
                'api_origem'         => 'BrasilAPI (Sem IE)'
            ];
        }

        return false;
    }
}

<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\PedidoModel;
use App\Models\EntidadeModel;
use App\Models\FuncionarioModel;
use App\Services\PermissaoService;
use Exception;

/**
 * Class PedidoController
 * Responsável pelo fluxo de Vendas e Previsões.
 */
class PedidoController extends BaseController
{
    // Propriedades Tipadas (PHP 7.4+)
    private PedidoModel $pedidoModel;
    private EntidadeModel $entidadeModel;
    private FuncionarioModel $funcionarioModel;

    public function __construct()
    {
        // Garante sessão iniciada (se não estiver no index global)
        if (session_status() == PHP_SESSION_NONE) session_start();

        // Injeção das dependências
        $this->pedidoModel = new PedidoModel();
        $this->entidadeModel = new EntidadeModel();
        $this->funcionarioModel = new FuncionarioModel();
    }

    /**
     * GET /pedidos
     * Exibe a view principal.
     */
    public function index(): void
    {
        $data = [
            'title' => 'Gestão de Pedidos',
            'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)),
            'pageScript' => 'pedidos',
            // Dados auxiliares para selects iniciais
            'vendedores' => $this->funcionarioModel->getFuncionariosByCargos(['Vendedor', 'Auxiliar de Vendas'])
        ];

        // Usa o helper do BaseController para renderizar
        $this->view('pedidos/index', $data);
    }

    /**
     * POST /pedidos/listar
     * Retorna JSON para o DataTables.
     */
    public function listar(): void
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        if (!PermissaoService::checarPermissao($cargo, 'Previsoes', 'Ler')) {
            $this->jsonResponse(['error' => 'Acesso negado'], 403);
        }

        try {
            // Filtro por vendedor (Regra de Negócio)
            $vendedorId = ($cargo === 'Vendedor') ? ($_SESSION['user_id'] ?? 0) : 0;

            // Pega dados do DataTables via $_POST
            $params = $_POST;

            $data = $this->pedidoModel->findAllForDataTable($params, $vendedorId);

            $this->jsonResponse($data);
        } catch (Exception $e) {
            error_log("Erro em Pedido::listar: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Erro interno ao processar lista.'], 500);
        }
    }

    /**
     * POST /pedidos/get
     * Busca um pedido específico para edição.
     */
    public function get(): void
    {
        try {
            // Método do BaseController: lê JSON ou POST
            $id = $this->getInput('id', FILTER_VALIDATE_INT);

            if (!$id) {
                $this->jsonResponse(['success' => false, 'message' => 'ID inválido.']);
            }

            $pedido = $this->pedidoModel->find($id);

            if ($pedido) {
                $this->jsonResponse(['success' => true, 'data' => $pedido]);
            } else {
                $this->jsonResponse(['success' => false, 'message' => 'Pedido não encontrado.']);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Erro no servidor.'], 500);
        }
    }

    /**
     * POST /pedidos/salvar
     * Cria ou Atualiza um pedido.
     */
    public function salvar(): void
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // 1. Verifica se é Edição (tem ID) ou Criação (não tem ID)
        $id = $this->getInput('id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';

        // Verifica permissão básica (melhorar para CREATE vs UPDATE dependendo do ID)
        if (!PermissaoService::checarPermissao($cargo, 'Previsoes', $acao)) {
            $this->jsonResponse(['success' => false, 'message' => 'Sem permissão para ' . $acao . "."], 403);
        }

        try {
            // Coleta dados usando Input Inteligente
            // Nota: Mapeie todos os campos aqui conforme seu formulário
            $data = [
                'os_numero'                 => $this->getInput('ped_os_numero', FILTER_SANITIZE_SPECIAL_CHARS),
                'cliente_entidade_id'       => $this->getInput('ped_cliente_id', FILTER_VALIDATE_INT),
                'vendedor_funcionario_id'   => $this->getInput('ped_vendedor_id', FILTER_VALIDATE_INT),
                'data_saida'                => format_date_db($this->getInput('ped_data_saida')),

                // Trata quantidade e valores monetários (remove pontos de milhar, troca virgula por ponto)
                'quantidade'                => format_money_db($this->getInput('ped_quantidade')),
                'percentual_bonus'          => format_money_db($this->getInput('ped_percentual_bonus')),
                'valor_unitario'            => format_money_db($this->getInput('ped_valor_unitario')),

                // Selects e Textos Simples (Padronizados para Maiúsculo)
                'forma_pagamento' => str_upper($this->getInput('ped_forma_pagamento')),
                'condicao'        => str_upper($this->getInput('ped_condicao')),
                'salinidade'      => str_upper($this->getInput('ped_salinidade')),
                'divisao'         => str_upper($this->getInput('ped_divisao')),
                'status'          => str_upper($this->getInput('ped_status')),
                'status_dia'      => str_upper($this->getInput('ped_status_dia'))

            ];

            // Validação Simples
            if (empty($data['os_numero']) || empty($data['cliente_entidade_id'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Dados obrigatórios faltando.']);
            }

            // Decisão: Insert ou Update
            if ($id) {
                // --- MODO EDIÇÃO ---
                $this->pedidoModel->update($id, $data, $_SESSION['user_id'] ?? 0);
                $msg = 'Pedido atualizado com sucesso!';
            } else {
                // --- MODO CRIAÇÃO ---
                $id = $this->pedidoModel->create($data, $_SESSION['user_id'] ?? 0);
                $msg = 'Pedido criado com sucesso!';
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Pedido salvo com sucesso!',
                'id' => $id
            ]);
        } catch (Exception $e) {
            error_log("Erro Salvar Pedido: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deleta um pedido.
     * Rota: pedidos/deletar
     */
    public function deletePedido(): void
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Previsoes', 'Deletar')) {
            $this->jsonResponse(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        $id = $this->getInput('pedido_id', FILTER_VALIDATE_INT);

        if ($this->pedidoModel->delete($id, $_SESSION['user_id'] ?? 0)) {
            $this->jsonResponse(['success' => true, 'message' => 'Pedido excluído.']);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Erro ao excluir.']);
        }
    }

    /**
     * Busca a próxima OS disponível.
     * Rota: pedidos/next-os
     */
    public function getNextOSNumber(): void
    {
        try {
            $lastOS = $this->pedidoModel->getLastOSNumber();

            // Lógica: Se existe última OS numérica, soma 1. Se não, inicia em 1000.
            $nextOS = $lastOS ? ((int)$lastOS + 1) : 1000;

            $this->jsonResponse(['success' => true, 'os_number' => $nextOS]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Erro ao gerar OS.']);
        }
    }

    /**
     * Proxy para buscar clientes (Select2).
     * Rota: pedidos/getClientesOptions
     */
    public function getClientesOptions(): void
    {
        $term = $this->getInput('term') ?? '';
        $results = $this->entidadeModel->getClienteOptions($term);
        $this->jsonResponse(['results' => $results]);
    }
}

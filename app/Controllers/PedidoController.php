<?php

/**
 * CLASSE CONTROLLER: PedidoController
 * Local: app/Controllers/PedidoController.php
 * Descrição: Gerencia o módulo de Vendas (PREVISOES_VENDAS).
 */

require_once ROOT_PATH . '/app/Models/PedidoModel.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php';
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class PedidoController
{
    private $pedidoModel;
    private $entidadePedModel;
    private $funcionarioModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->pedidoModel = new PedidoModel();

        // Inicializamos Models de apoio para Selects/Options
        $this->entidadePedModel = new EntidadeModel();
        $this->funcionarioModel = new FuncionarioModel();
    }

    /**
     * Exibe a tela de listagem e o modal de Pedidos.
     * Rota: /pedidos
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0;

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        $data = [
            'title' => 'Gestão de Pedidos/Previsões',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            // Futuramente: Opções para Salinidade, Condição, etc.
        ];

        // Dados para o formulário de Pedido
        $data = [
            'title' => 'Gestão de Pedidos/Previsões',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            'pageScript' => 'pedidos',

            // Opções para Selects:
            'vendedores' => $this->funcionarioModel->getFuncionariosByCargos(['Vendedor', 'Auxiliar de Vendas']),
            // Clientes serão carregados via Select2 (AJAX) para performance!
            // Os options de forma de pagamento e condição vêm da ENUM no Model.
        ];

        // RENDERIZAÇÃO
        ob_start();
        require_once ROOT_PATH . '/app/Views/pedidos/index.php'; // View a ser criada
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    /**
     * Rota AJAX para retornar a lista de pedidos (DataTables).
     */
    public function listarPedidos()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Previsoes', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        $vendedorId = 0;
        // Se o usuário for Vendedor, aplica o filtro (Regra de Negócio)
        if ($cargo === 'Vendedor' || $cargo === 'Auxiliar de Vendas') {
            $vendedorId = $_SESSION['user_id'] ?? 0;
        }

        try {
            $output = $this->pedidoModel->findAllForDataTable($_POST, $vendedorId);
            echo json_encode($output);
        } catch (\Exception $e) {
            error_log("Erro em listarPedidos: " . $e->getMessage());
            echo json_encode(["error" => "Erro ao processar dados."]);
        }
    }

    /**
     * Salva ou Atualiza um pedido (POST).
     * Rota: /pedidos/salvar
     */
    public function salvarPedido()
    {
        $id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0; // Usuário logado que faz a ação

        if (!PermissaoService::checarPermissao($cargo, 'Previsoes', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para esta ação.']);
            exit;
        }

        // 1. Coleta e Sanitiza/Valida dados
        $data = [
            // Usando os nomes da View (ped_*) e mapeando para o nome do Model (tabela)
            'os_numero'             => filter_input(INPUT_POST, 'ped_os_numero', FILTER_SANITIZE_SPECIAL_CHARS),
            'cliente_entidade_id'   => filter_input(INPUT_POST, 'ped_cliente_id', FILTER_VALIDATE_INT),
            'vendedor_funcionario_id' => filter_input(INPUT_POST, 'ped_vendedor_id', FILTER_VALIDATE_INT),
            'data_saida'            => filter_input(INPUT_POST, 'ped_data_saida', FILTER_SANITIZE_SPECIAL_CHARS),
            'quantidade'            => filter_input(INPUT_POST, 'ped_quantidade', FILTER_VALIDATE_FLOAT),
            'percentual_bonus'      => filter_input(INPUT_POST, 'ped_percentual_bonus', FILTER_VALIDATE_FLOAT),
            'valor_unitario'        => filter_input(INPUT_POST, 'ped_valor_unitario', FILTER_VALIDATE_FLOAT),
            'forma_pagamento'       => filter_input(INPUT_POST, 'ped_forma_pagamento', FILTER_SANITIZE_SPECIAL_CHARS),
            'condicao'              => filter_input(INPUT_POST, 'ped_condicao', FILTER_SANITIZE_SPECIAL_CHARS),
            'salinidade'            => filter_input(INPUT_POST, 'ped_salinidade', FILTER_SANITIZE_SPECIAL_CHARS),
            'divisao'               => filter_input(INPUT_POST, 'ped_divisao', FILTER_SANITIZE_SPECIAL_CHARS),
            'status'                => filter_input(INPUT_POST, 'ped_status', FILTER_SANITIZE_SPECIAL_CHARS),
            'status_dia'            => filter_input(INPUT_POST, 'ped_status_dia', FILTER_SANITIZE_SPECIAL_CHARS),
        ];

        // 2. Validação básica de obrigatórios
        if (empty($data['os_numero']) || !$data['cliente_entidade_id'] || !$data['vendedor_funcionario_id']) {
            echo json_encode(['success' => false, 'message' => 'Campos obrigatórios (OS, Cliente, Vendedor) não preenchidos.']);
            exit;
        }

        try {
            if ($id) {
                // UPDATE (Implementaremos depois)
                $message = 'Pedido atualizado com sucesso!';
                $newId = $id;
            } else {
                // CREATE

                // 1. VERIFICAÇÃO DE DUPLICIDADE ANTES DE INSERIR
                // Verifica se já existe essa OS no banco
                $pedidoExistente = $this->pedidoModel->getPedidoPorOS($data['os_numero']);

                if ($pedidoExistente) {
                    // Retorna erro amigável em vez de estourar erro de SQL
                    echo json_encode(['success' => false, 'message' => "A OS {$data['os_numero']} já existe! Por favor, clique em 'Novo' novamente para gerar uma nova numeração ou verifique a listagem."]);
                    exit;
                }

                $newId = $this->pedidoModel->create($data, $userId);
                $message = 'Pedido cadastrado com sucesso! (OS: ' . $data['os_numero'] . ')';
            }
            echo json_encode(['success' => true, 'message' => $message, 'pedido_id' => $newId ?? $id]);
        } catch (\PDOException $e) {
            // Tratamento para caso a verificação falhe e o banco pegue a duplicidade (segunda camada de segurança)
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo json_encode(['success' => false, 'message' => "Erro: A OS informada já foi cadastrada por outro usuário."]);
            } else {
                $message = "Erro no banco de dados. " . $e->getMessage();
                echo json_encode(['success' => false, 'message' => $message]);
            }
        }
    }

    /**
     * Rota AJAX para retornar opções de Clientes ativos para Select2.
     * Rota: /pedidos/clientes-options
     */
    public function getClientesOptions()
    {
        $term = $_POST['term'] ?? '';
        $options = $this->entidadePedModel->getClienteOptions($term);
        echo json_encode(['results' => $options]);
    }

    /**
     * Busca um pedido por ID para edição (POST).
     * Rota: /pedidos/get
     */
    public function getPedido()
    {
        $id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        if (!PermissaoService::checarPermissao($cargo, 'Previsoes', 'Ler')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }

        $dados = $this->pedidoModel->find($id);

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
        }
    }

    /**
     * Deleta um pedido (POST).
     * Rota: /pedidos/deletar
     */
    public function deletePedido()
    {
        $id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0;

        if (!PermissaoService::checarPermissao($cargo, 'Previsoes', 'Deletar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para Deletar.']);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID de pedido inválido.']);
            exit;
        }

        try {
            if ($this->pedidoModel->delete($id, $userId)) {
                echo json_encode(['success' => true, 'message' => 'Pedido excluído com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Falha ao excluir. Pedido não encontrado.']);
            }
        } catch (\Exception $e) {
            $message = strpos($e->getMessage(), 'Impossível excluir') !== false
                ? $e->getMessage()
                : "Erro no banco de dados. " . $e->getMessage();
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }


    /**
     * Retorna o próximo número sequencial para a OS.
     * Rota: /pedidos/next-os
     */
    public function getNextOSNumber()
    {
        try {
            // Define o valor inicial caso não haja registros.
            $nextNumber = 1001;

            // 1. Busca o último número de OS no Model
            $lastOS = $this->pedidoModel->getLastOSNumber();

            // 2. Se houver um último número, incrementa
            if ($lastOS) {
                // Se a OS for numérica (ex: "1000"), converte para int e soma 1.
                // Se for alfanumérica, pode exigir lógica mais complexa, mas vamos assumir numérica/simples.
                $nextNumber = (int) $lastOS + 1;
            }

            // Retorna o resultado como string (pois é VARCHAR no banco)
            echo json_encode(['success' => true, 'os_number' => (string)$nextNumber]);
        } catch (\Exception $e) {
            error_log("Erro ao buscar próxima OS: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro ao gerar número de OS.']);
        }
    }

    // =================================================================
    // AUXILIARES
    // =================================================================

    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

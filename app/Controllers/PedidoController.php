<?php

/**
 * CLASSE CONTROLLER: PedidoController
 * Local: app/Controllers/PedidoController.php
 * Descrição: Gerencia o módulo de Vendas (PREVISOES_VENDAS).
 */

require_once ROOT_PATH . '/app/Models/PedidoModel.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php'; // Para buscar Clientes
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class PedidoController
{
    private $pedidoModel;
    private $entidadeModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->pedidoModel = new PedidoModel();
        $this->entidadeModel = new EntidadeModel();
    }

    /**
     * Exibe a lista e o formulário de Pedidos.
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // Exemplo de VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Vendas', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado: Você não tem permissão para visualizar o Módulo de Vendas.");
        }

        $data = [
            'title' => 'Gestão de Pedidos/Previsões',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            // Futuramente: Opções para Salinidade, Condição, etc.
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
        if (!PermissaoService::checarPermissao($cargo, 'Vendas', 'Ler')) {
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
     * Rota AJAX para salvar um novo pedido (POST).
     */
    public function salvarPedido()
    {
        $id = filter_input(INPUT_POST, 'ped_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        if (!PermissaoService::checarPermissao($cargo, 'Vendas', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para salvar pedidos.']);
            exit;
        }

        try {
            // Adiciona o ID do vendedor (usuário logado) para o Model
            $_POST['ped_vendedor_id'] = $_SESSION['user_id'] ?? 0;

            if ($id) {
                // $this->pedidoModel->update($id, $_POST); // Método update a ser criado
                $message = 'Pedido atualizado com sucesso!';
            } else {
                $this->pedidoModel->create($_POST);
                $message = 'Pedido registrado com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $message = "Erro: Número de Ordem de Serviço (OS) já existe.";
            } else {
                $message = "Erro no banco de dados: " . $e->getMessage();
            }
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }

    // Futuramente: getClientesOptions, getNextOSNumber, etc.

    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

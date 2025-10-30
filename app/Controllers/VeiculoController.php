<?php

/**
 * CLASSE CONTROLLER: VeiculoController
 * Local: app/Controllers/VeiculoController.php
 * Descrição: Gerencia o módulo de cadastro e gestão da frota (tabela VEICULOS).
 */

require_once ROOT_PATH . '/app/Models/VeiculoModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php'; // Para buscar proprietários (Entidades)

class VeiculoController
{
    private $veiculoModel;
    private $entidadeModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->veiculoModel = new VeiculoModel();
        $this->entidadeModel = new EntidadeModel();
    }

    /**
     * Exibe a lista e o formulário de Cadastro de Veículos.
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Veiculos', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado: Você não tem permissão para visualizar o Cadastro de Veículos.");
        }

        // Futuramente, passaremos opções de proprietários (Entidades) para o formulário.
        $data = [
            'title' => 'Cadastro de Veículos',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            // Passaremos opções de proprietários (Entidades) aqui futuramente
        ];

        // RENDERIZAÇÃO
        ob_start();
        require_once ROOT_PATH . '/app/Views/veiculos/index.php'; // View a ser criada
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    /**
     * Rota AJAX para retornar a lista de veículos (DataTables).
     */
    public function listarVeiculos()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Veiculos', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        try {
            // NOTE: O método findAllForDataTable precisa ser adicionado ao VeiculoModel.
            $output = $this->veiculoModel->findAllForDataTable($_POST);
            echo json_encode($output);
        } catch (\Exception $e) {
            error_log("Erro em listarVeiculos: " . $e->getMessage());
            echo json_encode(["error" => "Erro ao processar dados."]);
        }
    }

    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Rota AJAX para salvar ou atualizar um veículo.
     */
    public function salvarVeiculo()
    {
        $id = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // 1. VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Veiculos', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para esta ação.']);
            exit;
        }

        try {
            // NOTE: Aqui você faria a validação dos dados antes de chamar o Model

            if ($id) {
                $this->veiculoModel->update($id, $_POST);
                $message = 'Veículo atualizado com sucesso!';
            } else {
                $this->veiculoModel->create($_POST);
                $message = 'Veículo cadastrado com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\PDOException $e) {
            // Tratamento de erro de chave única (Placa ou Chassi duplicado)
            if ($e->getCode() === '23000') {
                $message = "Erro: Placa ou Chassi já cadastrado.";
            } else {
                $message = "Erro no banco de dados: " . $e->getMessage();
            }
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }

    /**
     * Rota AJAX para buscar um veículo para edição.
     */
    public function getVeiculo()
    {
        $id = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $dados = $this->veiculoModel->find($id);

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Veículo não encontrado.']);
        }
    }

    /**
     * Rota AJAX para excluir um veículo.
     */
    public function deleteVeiculo()
    {
        $id = filter_input(INPUT_POST, 'veiculo_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        if (!PermissaoService::checarPermissao($cargo, 'Veiculos', 'Deletar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para excluir.']);
            exit;
        }

        try {
            if ($this->veiculoModel->delete($id)) {
                echo json_encode(['success' => true, 'message' => 'Veículo excluído com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veículo não encontrado.']);
            }
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro: Veículo em uso em manutenções ou entregas.']);
        }
    }
}

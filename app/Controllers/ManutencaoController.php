<?php

/**
 * CLASSE CONTROLLER: ManutencaoController
 * Local: app/Controllers/ManutencaoController.php
 * Descrição: Gerencia o módulo de manutenção.
 */

require_once ROOT_PATH . '/app/Models/ManutencaoModel.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php';
require_once ROOT_PATH . '/app/Models/VeiculoModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class ManutencaoController
{
    private $model;
    private $entidadeModel;
    private $veiculoModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->model = new ManutencaoModel();
        $this->entidadeModel = new EntidadeModel();
        $this->veiculoModel = new VeiculoModel();
    }

    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Manutencao', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado.");
        }

        $data = [
            'title' => 'Gestão de Manutenção',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateToken(),
            'pageScript' => 'manutencao'
        ];

        ob_start();
        require_once ROOT_PATH . '/app/Views/manutencao/index.php';
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    public function listar()
    {
        if (ob_get_length()) ob_clean();

        $params = [
            'draw'   => $_POST['draw'] ?? 1,
            'start'  => $_POST['start'] ?? 0,
            'length' => $_POST['length'] ?? 10,
            'search' => $_POST['search']['value'] ?? '',
            'order'  => $_POST['order'] ?? []
        ];

        echo json_encode($this->model->findAllForDataTable($params));
        exit;
    }

    /**
     * Salva cabeçalho e itens (recebe JSON).
     */
    /*public function salvar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        // Pega o JSON Raw do corpo da requisição
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data || !isset($data['header']) || !isset($data['itens'])) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos ou incompletos.']);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? 0;

        try {
            // Chama o model para salvar a transação completa
            $this->model->createFull($data['header'], $data['itens'], $userId);

            echo json_encode(['success' => true, 'message' => 'Manutenção registrada com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Erro no servidor: " . $e->getMessage()]);
        }
    } */

    public function salvar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data || !isset($data['header']) || !isset($data['itens'])) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        // Pega o ID que pode vir dentro do header ou na raiz do JSON, dependendo de como o JS manda.
        // Vamos garantir que o JS mande no header ou tratar aqui.
        // No JS atualizado abaixo, mandaremos no header.
        $id = isset($data['header']['id']) ? (int)$data['header']['id'] : 0;

        try {
            if ($id > 0) {
                // EDIÇÃO
                $this->model->updateFull($id, $data['header'], $data['itens'], $userId);
                $msg = "Manutenção atualizada com sucesso!";
            } else {
                // CRIAÇÃO
                $this->model->createFull($data['header'], $data['itens'], $userId);
                $msg = "Manutenção registrada com sucesso!";
            }

            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Erro: " . $e->getMessage()]);
        }
    }

    public function get()
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $dados = $this->model->findFull($id);

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        }
    }

    public function deletar()
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $userId = $_SESSION['user_id'] ?? 0;

        if ($this->model->delete($id, $userId)) {
            echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir']);
        }
    }

    // --- ROTAS AUXILIARES SELECT2 ---

    public function getVeiculosOptions()
    {
        try {
            $term = $_POST['term'] ?? '';
            $options = $this->veiculoModel->getVeiculosOptions($term);
            echo json_encode(['results' => $options]);
        } catch (\Exception $e) {
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
        }
    }

    public function getFornecedoresOptions()
    {
        try {
            $term = $_POST['term'] ?? '';
            $options = $this->entidadeModel->getFornecedoresOptions($term);
            echo json_encode(['results' => $options]);
        } catch (\Exception $e) {
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
        }
    }

    private function generateToken()
    {
        return bin2hex(random_bytes(32));
    }
}

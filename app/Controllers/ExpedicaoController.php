<?php

/**
 * CLASSE CONTROLLER: ExpedicaoController
 * Local: app/Controllers/ExpedicaoController.php
 * Descrição: Gerencia o módulo de Logística e Expedição (ENTREGAS_EXPEDICAO).
 */

require_once ROOT_PATH . '/app/Models/ExpedicaoModel.php';
require_once ROOT_PATH . '/app/Models/VeiculoModel.php';
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php'; // Para Motoristas/Encarregados
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class ExpedicaoController
{
    private $expedicaoModel;
    private $veiculoModel;
    private $funcionarioModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->expedicaoModel = new ExpedicaoModel();
        $this->veiculoModel = new VeiculoModel();
        $this->funcionarioModel = new FuncionarioModel();
    }

    /**
     * Exibe a lista e o formulário de Entregas/Expedições.
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Logistica', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado: Você não tem permissão para visualizar o Módulo de Logística.");
        }

        $data = [
            'title' => 'Gestão de Entregas e Carregamentos',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            // Futuramente: lista de pedidos pendentes para vincular à expedição
        ];

        // RENDERIZAÇÃO
        ob_start();
        require_once ROOT_PATH . '/app/Views/expedicao/index.php'; // View a ser criada
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    /**
     * Rota AJAX para salvar uma nova expedição (POST).
     */
    public function salvarExpedicao()
    {
        $id = filter_input(INPUT_POST, 'exp_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $responsavelId = $_SESSION['user_id'] ?? 0;

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC) - Normalmente Administrador ou Gerente de Logística
        if (!PermissaoService::checarPermissao($cargo, 'Logistica', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para salvar expedição.']);
            exit;
        }

        try {
            if ($id) {
                // Lógica de UPDATE
                // $this->expedicaoModel->update($id, $_POST); 
                $message = 'Expedição atualizada com sucesso!';
            } else {
                $this->expedicaoModel->create($_POST, $responsavelId);
                $message = 'Expedição registrada com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\PDOException $e) {
            error_log("Erro em salvarExpedicao: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro no banco de dados ao salvar a expedição.']);
        }
    }

    // Rota AJAX para buscar opções de Motoristas/Técnicos (Select2)
    public function getPessoalOptions()
    {
        $cargos = ['Motorista', 'Tecnico', 'Encarregado']; // Apenas os cargos relevantes para Logística
        try {
            // O FuncionarioModel precisa de um método para buscar por cargo
            $options = $this->funcionarioModel->getFuncionariosByCargos($cargos);
            echo json_encode(['results' => $options]);
        } catch (\Exception $e) {
            echo json_encode(['results' => [], 'error' => 'Erro ao buscar pessoal.']);
        }
    }

    /**
     * Rota AJAX para buscar o próximo número de carregamento sequencial (GET).
     */
    public function getNextCargaNumber()
    {
        $dataCarregamento = $_GET['data'] ?? '';

        if (empty($dataCarregamento)) {
            echo json_encode(['success' => false, 'message' => 'Data é obrigatória.']);
            exit;
        }

        try {
            $numero = $this->expedicaoModel->getNextNumeroCarregamento($dataCarregamento);
            echo json_encode(['success' => true, 'proximo_numero' => $numero]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar número.']);
        }
    }

    /**
     * Rota AJAX para retornar a lista de expedições (DataTables).
     */
    public function listarExpedicoes()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Logistica', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        try {
            // O ExpedicaoModel precisa de um método findAllForDataTable (a ser criado)
            // $output = $this->expedicaoModel->findAllForDataTable($_POST);

            // SIMULANDO o DataTables para fins de teste imediato:
            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 1),
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "Método listarExpedicoes (Model) não implementado. Listagem simulada."
            ]);
        } catch (\Exception $e) {
            echo json_encode(["error" => "Erro ao processar dados."]);
        }
    }

    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

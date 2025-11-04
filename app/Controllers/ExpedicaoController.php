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
            'title' => 'Organização de Entregas / Expedição',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            'pageScript' => 'expedicao',

            // CORRIGIDO: Agora o método existe no Model
            'pedidos_pendentes' => $this->expedicaoModel->getPedidosPendentes(), // <--- CORREÇÃO

            // Options para Selects (Veículos e Motoristas)
            'motoristas' => $this->funcionarioModel->getFuncionariosByCargos(['Motorista']),
            'veiculos' => $this->veiculoModel->getVeiculosOptions(),
        ];

        // RENDERIZAÇÃO
        ob_start();
        require_once ROOT_PATH . '/app/Views/expedicao/index.php'; // View a ser criada
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
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
            // NOVO: Chama o método real para listar os dados
            $output = $this->expedicaoModel->findAllForDataTable($_POST);
            echo json_encode($output);
        } catch (\Exception $e) {
            error_log("Erro em listarExpedicoes: " . $e->getMessage());
            echo json_encode(["error" => "Erro ao processar dados."]);
        }
    }

    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Rota AJAX para buscar opções de pedidos pendentes para Select2.
     */
    public function getPedidosPendentesOptions()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Logistica', 'Ler')) {
            http_response_code(403);
            echo json_encode(["results" => [], "error" => "Acesso negado."]);
            exit;
        }

        try {
            $pedidos = $this->expedicaoModel->getPedidosPendentes();

            $results = array_map(function ($p) {
                return [
                    'id' => $p['id'],
                    'text' => "OS: {$p['os_numero']} ({$p['quantidade_com_bonus']} PL) p/ {$p['data_saida']}"
                ];
            }, $pedidos);

            echo json_encode(['results' => $results]);
        } catch (\Exception $e) {
            echo json_encode(['results' => [], 'error' => 'Erro ao buscar pedidos.']);
        }
    }

    /**
     * Salva ou Atualiza uma Expedição.
     * Rota: /expedicao/salvar
     */
    public function salvarExpedicao()
    {
        $id = filter_input(INPUT_POST, 'exp_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0;

        // 1. Coleta e Sanitiza/Valida dados (MOVIDO PARA CÁ)
        $data = [
            'exp_previsao_id'              => filter_input(INPUT_POST, 'exp_previsao_id', FILTER_VALIDATE_INT),
            'exp_numero_carregamento'      => filter_input(INPUT_POST, 'exp_numero_carregamento', FILTER_VALIDATE_INT),
            'exp_data_carregamento'        => filter_input(INPUT_POST, 'exp_data_carregamento', FILTER_SANITIZE_SPECIAL_CHARS),
            'exp_veiculo_id'               => filter_input(INPUT_POST, 'exp_veiculo_id', FILTER_VALIDATE_INT),
            'exp_motorista_principal_id'   => filter_input(INPUT_POST, 'exp_motorista_principal_id', FILTER_VALIDATE_INT),
            'exp_motorista_reserva_id'     => filter_input(INPUT_POST, 'exp_motorista_reserva_id', FILTER_VALIDATE_INT),
            'exp_encarregado_id'           => filter_input(INPUT_POST, 'exp_encarregado_id', FILTER_VALIDATE_INT),
            'exp_horario_expedicao'        => filter_input(INPUT_POST, 'exp_horario_expedicao', FILTER_SANITIZE_SPECIAL_CHARS),
            'exp_quantidade_caixas'        => filter_input(INPUT_POST, 'exp_quantidade_caixas', FILTER_VALIDATE_INT),
            'exp_observacao'               => filter_input(INPUT_POST, 'exp_observacao', FILTER_SANITIZE_SPECIAL_CHARS),
            'exp_status_logistica'         => filter_input(INPUT_POST, 'exp_status_logistica', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Carregamento pendente',
        ];

        // 2. Validação básica de obrigatórios
        if (!$data['exp_numero_carregamento'] || !$data['exp_data_carregamento'] || !$data['exp_veiculo_id'] || !$data['exp_motorista_principal_id'] || !$data['exp_encarregado_id']) {
            echo json_encode(['success' => false, 'message' => 'Campos obrigatórios (Nº Carga, Data, Veículo, Motorista Principal e Encarregado) não preenchidos.']);
            exit;
        }

        try {
            if ($id) {
                // UPDATE
                $this->expedicaoModel->update($id, $data, $userId);
                $message = 'Expedição atualizada com sucesso!';
            } else {
                // CREATE
                $newId = $this->expedicaoModel->create($data, $userId);
                $message = 'Expedição registrada com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\PDOException $e) {
            $message = "Erro no banco de dados. " . $e->getMessage();
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }

    /**
     * Busca os dados de uma expedição para edição (POST).
     * Rota: /expedicao/get
     */
    public function getExpedicao()
    {
        $id = filter_input(INPUT_POST, 'exp_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        if (!PermissaoService::checarPermissao($cargo, 'Entregas', 'Ler')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }

        $dados = $this->expedicaoModel->find($id);

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Expedição não encontrada.']);
        }
    }

    /**
     * Deleta uma expedição (POST).
     * Rota: /expedicao/deletar
     */
    public function deleteExpedicao()
    {
        $id = filter_input(INPUT_POST, 'exp_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0;

        if (!PermissaoService::checarPermissao($cargo, 'Entregas', 'Deletar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para deletar.']);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID de expedição inválido.']);
            exit;
        }

        try {
            if ($this->expedicaoModel->delete($id, $userId)) {
                echo json_encode(['success' => true, 'message' => 'Expedição deletada com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Falha ao deletar. Expedição não encontrada.']);
            }
        } catch (\PDOException $e) {
            $message = "Erro no banco de dados. ";
            if ($e->getCode() === '23000') {
                $message = "Impossível deletar: Esta expedição tem Custos ou Registros de Frota associados (Exclusão Restrita).";
            } else {
                $message .= $e->getMessage();
            }
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }
}

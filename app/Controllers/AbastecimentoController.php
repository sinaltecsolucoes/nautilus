<?php

/**
 * CLASSE CONTROLLER: AbastecimentoController
 * Local: app/Controllers/AbastecimentoController.php
 */

require_once ROOT_PATH . '/app/Models/AbastecimentoModel.php';
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php'; // Para listar motoristas
require_once ROOT_PATH . '/app/Services/PermissaoService.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php';
require_once ROOT_PATH . '/app/Models/VeiculoModel.php';

class AbastecimentoController
{
    private $model;
    private $funcionarioModel;
    private $entidadeModel;
    private $veiculoModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) session_start();
        $this->model = new AbastecimentoModel();
        $this->funcionarioModel = new FuncionarioModel();
        $this->entidadeModel = new EntidadeModel();
        $this->veiculoModel = new VeiculoModel();
    }

    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Logistica', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado.");
        }

        // Busca Motoristas para o Select do Modal
        $motoristas = $this->funcionarioModel->getMotoristasOptions();

        $data = [
            'title' => 'Gestão de Abastecimento',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'pageScript' => 'abastecimentos',
            'motoristas' => $motoristas
        ];

        $_SESSION['csrf_token'] = $data['csrf_token']; // Salva token

        ob_start();
        require_once ROOT_PATH . '/app/Views/abastecimentos/index.php';
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    public function listar()
    {
        // AJAX DataTables
        $draw = $_POST['draw'] ?? 1;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $search = $_POST['search']['value'] ?? '';

        $resultado = $this->model->getForDataTable([
            'start' => $start,
            'length' => $length,
            'search' => $search
        ]);

        echo json_encode([
            'draw' => intval($draw),
            'recordsTotal' => $resultado['total'],
            'recordsFiltered' => $resultado['totalFiltered'],
            'data' => $resultado['data']
        ]);
    }

    public function salvar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        // Função auxiliar para converter "1.200,50" -> 1200.50
        $toDouble = function ($val) {
            if (empty($val)) return 0.00;
            return (float) str_replace(',', '.', str_replace('.', '', $val));
        };

        $dados = [
            'funcionario_id' => $_POST['funcionario_id'],
            'entidade_id'    => $_POST['entidade_id'], // Posto ID
            'veiculo_id'     => $_POST['veiculo_id'],  // Veículo ID
            'data_abastecimento' => $_POST['data'] . ' ' . $_POST['hora'],
            'numero_cupom' => $_POST['numero_cupom'],
            'descricao_combustivel' => $_POST['descricao_combustivel'],
            'quilometro_abast' => $_POST['quilometro_abast'],

            // Tratamento de valores monetários
            'valor_unitario' => $toDouble($_POST['valor_unitario']),
            'total_litros' => str_replace(',', '.', $_POST['total_litros']),
            'valor_total' => $toDouble($_POST['valor_total']),
        ];

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        try {
            if ($id) {
                $this->model->update($id, $dados);
                $msg = "Abastecimento atualizado!";
            } else {
                $this->model->create($dados);
                $msg = "Abastecimento cadastrado!";
            }
            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Erro: " . $e->getMessage()]);
        }
    }

    public function get()
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $dados = $this->model->find($id);
        if ($dados) {
            // Formata data e hora para o form
            $dt = new DateTime($dados['data_abastecimento']);
            $dados['data'] = $dt->format('Y-m-d');
            $dados['hora'] = $dt->format('H:i');
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Não encontrado']);
        }
    }

    public function deletar()
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($this->model->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Excluído com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir']);
        }
    }

    public function getPostosOptions()
    {
        $term = $_GET['term'] ?? '';
        // Supondo que você crie esse método no EntidadeModel
        // Ele deve buscar onde tipo = 'Fornecedor' e (nome LIKE term OR cnpj LIKE term)
        $resultados = $this->entidadeModel->getFornecedoresOptions($term);
        echo json_encode(['results' => $resultados]);
    }

    public function getVeiculosOptions()
    {
        $term = $_GET['term'] ?? '';
        // Método no VeiculoModel
        $resultados = $this->veiculoModel->getVeiculosOptions($term);
        echo json_encode(['results' => $resultados]);
    }
}

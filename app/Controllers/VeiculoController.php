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

    public function listarVeiculos()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Veiculos', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        // NOVO: Coleta e passa todos os parâmetros DataTables (draw, start, length, search)
        $params = [
            'draw' => filter_input(INPUT_POST, 'draw', FILTER_VALIDATE_INT) ?? 1,
            'start' => filter_input(INPUT_POST, 'start', FILTER_VALIDATE_INT) ?? 0,
            'length' => filter_input(INPUT_POST, 'length', FILTER_VALIDATE_INT) ?? 10,
            'search' => filter_input(INPUT_POST, 'search', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY),
        ];

        try {
            $output = $this->veiculoModel->findAllForDataTable($params); // Chama o Model corrigido
            echo json_encode($output);
        } catch (\Exception $e) {
            error_log("Erro em listarVeiculos: " . $e->getMessage());
            // Se houver erro, retorna JSON com a estrutura do DataTables, mas com erro
            http_response_code(500);
            echo json_encode(["draw" => $params['draw'], "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Erro ao processar dados."]);
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
        $userId = $_SESSION['user_id'] ?? 0; // ID do usuário logado para Auditoria

        // 1. VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Veiculos', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para esta ação.']);
            exit;
        }

        // 2. Coleta e Sanitiza TODOS os dados do formulário
        $data = [
            'veiculo_placa'             => filter_input(INPUT_POST, 'veiculo_placa', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_marca'             => filter_input(INPUT_POST, 'veiculo_marca', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_modelo'            => filter_input(INPUT_POST, 'veiculo_modelo', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_tipo_frota'        => filter_input(INPUT_POST, 'veiculo_tipo_frota', FILTER_SANITIZE_SPECIAL_CHARS),
            'proprietario_entidade_id'  => filter_input(INPUT_POST, 'proprietario_entidade_id', FILTER_VALIDATE_INT),
            'veiculo_ano'               => filter_input(INPUT_POST, 'veiculo_ano', FILTER_VALIDATE_INT),
            'veiculo_tipo_combustivel'  => filter_input(INPUT_POST, 'veiculo_tipo_combustivel', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_autonomia'         => filter_input(INPUT_POST, 'veiculo_autonomia', FILTER_VALIDATE_FLOAT),
            'veiculo_renavam'           => filter_input(INPUT_POST, 'veiculo_renavam', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_crv'               => filter_input(INPUT_POST, 'veiculo_crv', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_situacao'          => filter_input(INPUT_POST, 'veiculo_situacao', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_chassi'            => filter_input(INPUT_POST, 'veiculo_chassi', FILTER_SANITIZE_SPECIAL_CHARS),
            'veiculo_licenciamento'     => filter_input(INPUT_POST, 'veiculo_licenciamento', FILTER_VALIDATE_FLOAT),
            'veiculo_ipva'              => filter_input(INPUT_POST, 'veiculo_ipva', FILTER_VALIDATE_FLOAT),
            'veiculo_depreciacao'       => filter_input(INPUT_POST, 'veiculo_depreciacao', FILTER_VALIDATE_FLOAT),
        ];

        // 3. Validação Regra de Negócio (Proprietário Próprio)
        /*
        if ($data['veiculo_tipo_frota'] === 'Propria' && (!$data['proprietario_entidade_id'] || $data['proprietario_entidade_id'] !== ID_EMPRESA_PROPRIA)) {
            // Regra: Para frota Própria, deve ser vinculada à Entidade da empresa (ID_EMPRESA_PROPRIA).
            // A constante ID_EMPRESA_PROPRIA deve ser definida no config.php.
            // echo json_encode(['success' => false, 'message' => 'Para frota Própria, vincule à Entidade da empresa (ID: ID_EMPRESA_PROPRIA).']);
            // exit;
        }
        */

        try {
            if ($id) {
                // UPDATE com userId para Auditoria
                $this->veiculoModel->update($id, $data, $userId);
                $message = 'Veículo atualizado com sucesso!';
            } else {
                // CREATE com userId para Auditoria
                $this->veiculoModel->create($data, $userId);
                $message = 'Veículo cadastrado com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\PDOException $e) {
            // Tratamento de erro de chave única (Placa ou Chassi duplicado)
            if ($e->getCode() === '23000') {
                $message = "Erro: Placa, Chassi, Renavam ou CRV já cadastrado.";
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
        $userId = $_SESSION['user_id'] ?? 0; // ID do usuário logado para Auditoria

        if (!PermissaoService::checarPermissao($cargo, 'Veiculos', 'Deletar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para excluir.']);
            exit;
        }

        try {
            // CORRIGIDO: Passando o $userId para o Model
            if ($this->veiculoModel->delete($id, $userId)) {
                echo json_encode(['success' => true, 'message' => 'Veículo excluído com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veículo não encontrado.']);
            }
        } catch (\PDOException $e) {
            // Tratamento de erro de chave estrangeira (em uso)
            echo json_encode(['success' => false, 'message' => 'Erro: Veículo em uso em manutenções ou entregas.']);
        }
    }

    /**
     * Rota AJAX para retornar opções de Entidades (Proprietários) para Select2.
     */
    public function getProprietariosOptions()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        // A permissão para 'Ler' Entidades deve bastar.
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            http_response_code(403);
            echo json_encode(["results" => [], "error" => "Acesso negado."]);
            exit;
        }

        $term = $_GET['term'] ?? '';

        try {
            // Assume que EntidadeModel possui o método auxiliar getEntidadesOptions
            $options = $this->entidadeModel->getEntidadesOptions($term);

            echo json_encode(['results' => $options]);
        } catch (\Exception $e) {
            error_log("Erro em getProprietariosOptions: " . $e->getMessage());
            echo json_encode(['results' => [], 'error' => 'Erro ao buscar proprietários.']);
        }
    }
}

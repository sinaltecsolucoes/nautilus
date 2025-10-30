<?php

/**
 * CLASSE CONTROLLER: ManutencaoController
 * Local: app/Controllers/ManutencaoController.php
 * Descrição: Gerencia o módulo de controle de manutenção de veículos.
 */

require_once ROOT_PATH . '/app/Models/ManutencaoModel.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php'; // Para buscar Fornecedores
require_once ROOT_PATH . '/app/Models/VeiculoModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class ManutencaoController
{
    private $manutencaoModel;
    private $entidadeModel; // Usado para buscar fornecedores no select
    private $veiculoModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->manutencaoModel = new ManutencaoModel();
        // Inicializa o Model de Entidades para usar os métodos de busca de fornecedores
        $this->entidadeModel = new EntidadeModel();
        $this->veiculoModel = new VeiculoModel();
    }

    /**
     * Exibe a lista e o formulário de Manutenções.
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // Exemplo de VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Manutencoes', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado: Você não tem permissão para visualizar o Controle de Manutenção.");
        }

        $data = [
            'title' => 'Controle de Manutenção',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            // Futuramente: 'veiculosOptions', 'fornecedoresOptions'
        ];

        // RENDERIZAÇÃO
        ob_start();
        require_once ROOT_PATH . '/app/Views/manutencao/index.php'; // View a ser criada
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    /**
     * Rota AJAX para retornar a lista de manutenções (DataTables).
     */
    public function listarManutencoes()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Manutencoes', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        try {
            $output = $this->manutencaoModel->findAllForDataTable($_POST);
            echo json_encode($output);
        } catch (\Exception $e) {
            error_log("Erro em listarManutencoes: " . $e->getMessage());
            echo json_encode(["error" => "Erro ao processar dados."]);
        }
    }

    /**
     * Rota AJAX para salvar ou atualizar um registro de manutenção (POST).
     */
    public function salvarManutencao()
    {
        $id = filter_input(INPUT_POST, 'man_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Manutencoes', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para salvar manutenção.']);
            exit;
        }

        try {
            // NOTE: A validação dos campos (se estão preenchidos) deve ser feita aqui!

            if ($id) {
                // Lógica de UPDATE (Método 'update' precisa ser adicionado ao Model)
                // $this->manutencaoModel->update($id, $_POST);
                $message = 'Manutenção atualizada com sucesso!';
            } else {
                // CREATE (Método 'create' já foi adicionado ao Model)
                $this->manutencaoModel->create($_POST);
                $message = 'Manutenção registrada com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\PDOException $e) {
            error_log("Erro em salvarManutencao: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro no banco de dados ao salvar o registro.']);
        }
    }

    /**
     * Rota AJAX para retornar opções de Veículos (para Select2).
     */
    public function getVeiculosOptions()
    {
        try {
            $options = $this->veiculoModel->getVeiculosOptions();
            echo json_encode(['results' => $options]); // Formato Select2
        } catch (\Exception $e) {
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Rota AJAX para retornar opções de Fornecedores (para Select2).
     */
    public function getFornecedoresOptions()
    {
        try {
            // Assume que EntidadeModel possui um método específico para Fornecedores
            $options = $this->entidadeModel->getFornecedorOptions();
            // O EntidadeModel retorna a chave 'nome_display'. Precisamos adaptar para 'text' e 'id'
            $results = array_map(function ($item) {
                return ['id' => $item['ent_codigo'], 'text' => $item['nome_display']];
            }, $options);

            echo json_encode(['results' => $results]);
        } catch (\Exception $e) {
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
        }
    }
    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

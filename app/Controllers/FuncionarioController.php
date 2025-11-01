<?php

/**
 * CLASSE CONTROLLER: FuncionarioController
 * Local: app/Controllers/FuncionarioController.php
 * Descrição: Gerencia o CRUD de Funcionários do sistema (Usuários).
 */

require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class FuncionarioController
{
    private $funcionarioModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->funcionarioModel = new FuncionarioModel();
    }

    // =================================================================
    // FUNÇÕES DE VIEW (GET)
    // =================================================================

    /**
     * Exibe a lista e o formulário de Funcionários.
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado: Você não tem permissão para visualizar o Cadastro de Funcionários.");
        }

        $data = [
            'title' => 'Gestão de Funcionários',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            'cargos' => $this->funcionarioModel->getCargosOptions(), // Lista de cargos que o formulário precisará
            'pageScript' => 'funcionarios', // Novo script JS
        ];

        // RENDERIZAÇÃO
        ob_start();
        require_once ROOT_PATH . '/app/Views/funcionarios/index.php';
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    // =================================================================
    // FUNÇÕES DE AÇÃO (AJAX/POST)
    // =================================================================

    /**
     * Rota AJAX para o DataTables (Server-Side).
     */
    public function listarFuncionarios()
    {
        // ... (Implementação do DataTables - Mantida)
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        $draw = filter_input(INPUT_POST, 'draw', FILTER_VALIDATE_INT) ?? 1;
        $start = filter_input(INPUT_POST, 'start', FILTER_VALIDATE_INT) ?? 0;
        $length = filter_input(INPUT_POST, 'length', FILTER_VALIDATE_INT) ?? 10;
        $searchValue = filter_input(INPUT_POST, 'search', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY)['value'] ?? '';

        try {
            $resultado = $this->funcionarioModel->getForDataTable([
                'start' => $start,
                'length' => $length,
                'search' => $searchValue
            ]);

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $resultado['total'],
                'recordsFiltered' => $resultado['totalFiltered'],
                'data' => $resultado['data']
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Erro em listarFuncionarios: " . $e->getMessage());
            echo json_encode(['error' => 'Erro interno do servidor ao buscar dados.']);
        }
    }


    /**
     * Salva ou Atualiza um funcionário (POST).
     * O Controller agora faz o Hashing e passa o ID de Auditoria.
     */
    public function salvarFuncionario()
    {
        $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0; // ID DO USUÁRIO LOGADO para Auditoria

        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para esta ação.']);
            exit;
        }

        // 1. Coleta e Sanitiza os dados
        $data = [
            'nome_completo' => filter_input(INPUT_POST, 'funcionario_nome', FILTER_SANITIZE_SPECIAL_CHARS),
            'email'         => filter_input(INPUT_POST, 'funcionario_email', FILTER_SANITIZE_EMAIL),
            'tipo_cargo'    => filter_input(INPUT_POST, 'funcionario_cargo', FILTER_SANITIZE_SPECIAL_CHARS),
            'situacao'      => filter_input(INPUT_POST, 'funcionario_situacao', FILTER_SANITIZE_SPECIAL_CHARS),
            'senha_pura'    => filter_input(INPUT_POST, 'funcionario_senha', FILTER_DEFAULT),
        ];

        // 2. Validação básica e Hashing de Senha (TRANSFERIDO PARA O CONTROLLER)
        if (!$id && empty($data['senha_pura'])) {
            echo json_encode(['success' => false, 'message' => 'A senha é obrigatória para um novo cadastro.']);
            exit;
        }

        // Se houver senha, criptografa
        if (!empty($data['senha_pura'])) {
            $data['senha_hash'] = password_hash($data['senha_pura'], PASSWORD_BCRYPT);
        }
        unset($data['senha_pura']); // Remove a senha pura antes de passar para o Model

        // 3. Chamada ao Model
        try {
            if ($id) {
                // UPDATE
                $this->funcionarioModel->updateFuncionario($id, $data, $userId);
                $message = 'Funcionário atualizado com sucesso!';
                $newId = $id;
            } else {
                // CREATE
                $newId = $this->funcionarioModel->createFuncionario($data, $userId); // Passa o userId
                $message = 'Funcionário cadastrado com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message, 'func_id' => $newId]);
        } catch (\PDOException $e) {
            // Código 23000 = Violacão de integridade (Ex: Email duplicado)
            if ($e->getCode() === '23000') {
                $message = "Erro: O email **{$data['email']}** já está cadastrado. Emails devem ser únicos.";
            } else {
                error_log("Erro no DB ao salvar funcionário: " . $e->getMessage());
                $message = "Erro interno do banco de dados ao salvar o funcionário.";
            }
            echo json_encode(['success' => false, 'message' => $message]);
        } catch (\Exception $e) {
            error_log("Erro geral ao salvar funcionário: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado: ' . $e->getMessage()]);
        }
    }


    /**
     * Busca dados de um funcionário para pré-preenchimento (GET).
     */
    public function getFuncionario()
    {
        $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $dados = $this->funcionarioModel->find($id);

        if ($dados) {
            // Não devolve dados sensíveis (o Model já exclui a senha)
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
        }
    }


    /**
     * Inativa (Soft Delete) um funcionário (POST).
     * Rota de `usuarios/deletar`
     */
    public function deleteFuncionario()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0; // ID DO USUÁRIO LOGADO para Auditoria
        $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);

        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Deletar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para Inativar.']);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido para inativação.']);
            exit;
        }

        // Regra de Ouro: Impedir que o próprio Admin se delete/inative
        if ($id == $userId) {
            echo json_encode(['success' => false, 'message' => 'Você não pode inativar sua própria conta.']);
            exit;
        }

        try {
            if ($this->funcionarioModel->inactivate($id, $userId)) { // Passa o userId
                echo json_encode(['success' => true, 'message' => 'Funcionário inativado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Falha ao inativar. Funcionário não encontrado ou já inativo.']);
            }
        } catch (\Exception $e) {
            // Captura a exceção de Restrição de Chave Estrangeira do Model
            if (strpos($e->getMessage(), 'Restrição de Chave Estrangeira') !== false) {
                $message = "Impossível inativar: Este funcionário possui registros associados (Entregas, Previsões, Auditoria).";
            } else {
                error_log("Erro ao inativar funcionário: " . $e->getMessage());
                $message = "Erro interno ao processar a inativação.";
            }
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }


    // =================================================================
    // AUXILIARES
    // =================================================================

    private function generateCsrfToken()
    {
        // Implementa geração de token e salvar em $_SESSION
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

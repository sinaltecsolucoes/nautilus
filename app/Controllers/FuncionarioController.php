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

    /**
     * Exibe a lista e o formulário de Funcionários.
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // Exemplo de VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado: Você não tem permissão para visualizar o Cadastro de Funcionários.");
        }

        $data = [
            'title' => 'Gestão de Funcionários',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            // Lista de cargos que o formulário precisará
            'cargos' => ['Administrador', 'Gerente', 'Encarregado', 'Tecnico', 'Motorista', 'Vendedor', 'Auxiliar de Vendas', 'Auxiliar de Expedicao']
        ];

        // RENDERIZAÇÃO
        ob_start();
        require_once ROOT_PATH . '/app/Views/funcionarios/index.php'; // View a ser criada
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    /**
     * Rota AJAX para retornar a lista de funcionários (DataTables).
     */
    public function listarFuncionarios()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        try {
            $output = $this->funcionarioModel->findAllForDataTable($_POST);
            echo json_encode($output);
        } catch (\Exception $e) {
            error_log("Erro em listarFuncionarios: " . $e->getMessage());
            echo json_encode(["error" => "Erro ao processar dados."]);
        }
    }

    /**
     * Busca os dados de um funcionário para edição (POST).
     */
    public function getFuncionario()
    {
        $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // 1. VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para visualização.']);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        // 2. Busca no Model (o Model precisa ter um método findById)
        $dados = $this->funcionarioModel->findById($id);

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
        }
    }

    /**
     * Salva ou atualiza um registro de funcionário (POST).
     */
    public function salvarFuncionario()
    {
        $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // 1. VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para esta ação.']);
            exit;
        }

        // 2. Validação de Senha (se fornecida)
        $senha = $_POST['funcionario_senha'] ?? '';
        $confirmarSenha = $_POST['funcionario_confirmar_senha'] ?? '';

        if (!empty($senha) && $senha !== $confirmarSenha) {
            echo json_encode(['success' => false, 'message' => 'Erro: A senha e a confirmação não conferem.']);
            exit;
        }

        // Regra: Se for CREATE, a senha é obrigatória
        if (!$id && empty($senha)) {
            echo json_encode(['success' => false, 'message' => 'Erro: A senha é obrigatória para novos cadastros.']);
            exit;
        }

        try {
            if ($id) {
                // UPDATE: O Model precisa de um método 'update'
                $this->funcionarioModel->update($id, $_POST, $senha);
                $message = 'Funcionário atualizado com sucesso!';
            } else {
                // CREATE: O método 'createFuncionario' já existe (precisamos usá-lo ou adaptá-lo)
                $this->funcionarioModel->createFuncionario($_POST); // Assumindo que este método faz o hash da senha
                $message = 'Funcionário cadastrado com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (\PDOException $e) {
            // Tratamento de erro de email duplicado (código 23000)
            if ($e->getCode() === '23000') {
                $message = "Erro: Este email (login) já está em uso.";
            } else {
                $message = "Erro no banco de dados. " . $e->getMessage();
            }
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }

    /**
     * Rota AJAX para excluir um funcionário (POST).
     */
    public function deleteFuncionario()
    {
        $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $loggedInUserId = $_SESSION['user_id'] ?? 0;

        // 1. VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Deletar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para exclusão.']);
            exit;
        }

        if (!$id || $id === $loggedInUserId) {
            echo json_encode(['success' => false, 'message' => 'Erro: Não é possível excluir o próprio usuário ou ID inválido.']);
            exit;
        }

        try {
            // Implementar a inativação ou exclusão no Model
            if ($this->funcionarioModel->inactivate($id)) { // Vamos usar Inativação para segurança
                echo json_encode(['success' => true, 'message' => 'Funcionário inativado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado ou já inativo.']);
            }
        } catch (\PDOException $e) {
            error_log("Erro ao inativar funcionário: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
        }
    }

    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\FuncionarioModel;
use App\Services\PermissaoService;

class FuncionarioController extends BaseController
{
    private FuncionarioModel $funcionarioModel;

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
            exit("Acesso Negado: Você não tem permissão para visualizar o Cadastro de Funcionários.");
        }

        $data = [
            'title'      => 'Gestão de Funcionários',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
            'cargos'     => $this->funcionarioModel->getCargosOptions(), // Lista de cargos que o formulário precisará
            'pageScript' => 'funcionarios', // Novo script JS
        ];

        // RENDERIZAÇÃO
        $this->view('funcionarios/index', $data);
    }

    // =================================================================
    // FUNÇÕES DE AÇÃO (AJAX/POST)
    // =================================================================

    /**
     * Rota AJAX para o DataTables (Server-Side).
     */
    public function listarFuncionarios()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            http_response_code(403);
            $this->jsonResponse(['error' => 'Acesso negado.']);
            return;
        }

        $draw   = $this->getInput('draw', FILTER_VALIDATE_INT) ?? 1;
        $start  = $this->getInput('start', FILTER_VALIDATE_INT) ?? 0;
        $length = $this->getInput('length', FILTER_VALIDATE_INT) ?? 10;
        $search = $_POST['search']['value'] ?? '';

        try {
            $resultado = $this->funcionarioModel->getForDataTable([
                'start'  => $start,
                'length' => $length,
                'search' => $search
            ]);

            $this->jsonResponse([
                'draw'            => $draw,
                'recordsTotal'    => $resultado['total'],
                'recordsFiltered' => $resultado['totalFiltered'],
                'data'            => $resultado['data']
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Erro em listarFuncionarios: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Erro interno do servidor ao buscar dados.']);
        }
    }

    public function salvarFuncionario()
    {
        $id     = $this->getInput('funcionario_id', FILTER_VALIDATE_INT);
        $acao   = $id ? 'Alterar' : 'Criar';
        $cargo  = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0;

        // 1. Permissão
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', $acao)) {
            http_response_code(403);
            $this->jsonResponse(['success' => false, 'message' => 'Acesso negado.']);
            return;
        }

        $postLimpo = sanitize_input_array($_POST);

        // 2. Coleta Dados Básicos
        $temAcesso = isset($_POST['tem_acesso']); // Checkbox marcado?

        $data = [
            'nome_completo' => $postLimpo['funcionario_nome'],
            'nome_comum'    => $postLimpo['funcionario_apelido'],
            'tipo_cargo'    => $postLimpo['funcionario_cargo'],
            'situacao'      => $postLimpo['funcionario_situacao'],

            // Inicializa Login como NULL por padrão
            'email'         => null,
            'senha_hash'    => null,
        ];

        // Pequena lógica inteligente: Se não digitou apelido, usa o primeiro nome
        if (empty($data['nome_comum'])) {
            $partes = explode(' ', $data['nome_completo']);
            $data['nome_comum'] = $partes[0];
        }

        // 3. Processa Login (Se Checkbox marcado)
        if ($temAcesso) {
            $emailInput = $postLimpo['funcionario_email'];

            if (empty($emailInput)) {
                $this->jsonResponse(['success' => false, 'message' => 'Email é obrigatório para usuários com acesso.']);
                return;
            }
            $data['email'] = $emailInput;

            // Senha
            $senhaPura = $_POST['funcionario_senha'] ?? '';

            // Se for NOVO e tem acesso, senha é obrigatória
            if (!$id && empty($senhaPura)) {
                $this->jsonResponse(['success' => false, 'message' => 'Senha é obrigatória para novos usuários.']);
                return;
            }

            // Se digitou senha (Novo ou Edição), criptografa e atualiza
            if (!empty($senhaPura)) {
                $data['senha_hash'] = password_hash($senhaPura, PASSWORD_BCRYPT);
            }
            // OBS: Se for Edição e senhaPura estiver vazia, 'senha_hash' continua NULL no array $data. 
            // O Model deve ser inteligente para ignorar 'senha_hash' se for NULL durante o UPDATE, 
            // a menos que estejamos removendo o acesso (mas aqui estamos dentro do if($temAcesso), então ok).
        }

        // 4. Salvar no Banco
        try {
            if ($id) {
                // UPDATE: O Model cuida de manter a senha antiga se $data['senha_hash'] for null/vazio E $temAcesso for true
                // Se $temAcesso for false, o Model vai receber email=null e senha=null e vai zerar o acesso.
                $this->funcionarioModel->updateFuncionario($id, $data, $userId);
                $message = 'Funcionário atualizado com sucesso!';
                $newId = $id;
            } else {
                // CREATE
                $newId = $this->funcionarioModel->createFuncionario($data, $userId);
                $message = 'Funcionário cadastrado com sucesso!';
            }

            $this->jsonResponse(['success' => true, 'message' => $message, 'func_id' => $newId]);
        } catch (\PDOException $e) {
            $message = $e->getCode() === '23000'
                ? "Erro: O email informado já está em uso."
                : "Erro interno ao salvar dados.";
            error_log("DB Error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro inesperado: ' . $e->getMessage()]);
        }
    }

    /**
     * Busca dados de um funcionário para pré-preenchimento (GET).
     */
    public function getFuncionario()
    {
        $id     = $this->getInput('funcionario_id', FILTER_VALIDATE_INT);
        $cargo  = $_SESSION['user_cargo'] ?? 'Visitante';

        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Ler')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Acesso negado.'],
                403
            );
        }

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID inválido.']);
        }

        $dados = $this->funcionarioModel->find($id);

        if ($dados) {
            // Não devolve dados sensíveis (o Model já exclui a senha)
            $this->jsonResponse(['success' => true, 'data' => $dados]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Funcionário não encontrado.']);
        }
    }

    /**
     * Inativa (Soft Delete) um funcionário (POST).
     * Rota de `usuarios/deletar`
     */
    public function deleteFuncionario()
    {
        $cargo  = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0; // ID DO USUÁRIO LOGADO para Auditoria
        $id     = $this->getInput('funcionario_id', FILTER_VALIDATE_INT);

        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Deletar')) {
            http_response_code(403);
            $this->jsonResponse(['success' => false, 'message' => 'Acesso negado para Inativar.']);
            return;
        }

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID inválido para inativação.']);
            return;
        }

        // Impedir que o próprio Admin se delete/inative
        if ($id == $userId) {
            $this->jsonResponse(['success' => false, 'message' => 'Você não pode inativar sua própria conta.']);
            return;
        }

        try {
            if ($this->funcionarioModel->inactivate($id, $userId)) { // Passa o userId
                $this->jsonResponse(['success' => true, 'message' => 'Funcionário inativado com sucesso!']);
            } else {
                $this->jsonResponse(['success' => false, 'message' => 'Falha ao inativar. Funcionário não encontrado ou já inativo.']);
            }
        } catch (\Exception $e) {
            // Captura a exceção de Restrição de Chave Estrangeira do Model
            if (strpos($e->getMessage(), 'Restrição de Chave Estrangeira') !== false) {
                $message = "Impossível inativar: Este funcionário possui registros associados (Entregas, Previsões, Auditoria).";
            } else {
                error_log("Erro ao inativar funcionário: " . $e->getMessage());
                $message = "Erro interno ao processar a inativação.";
            }
            $this->jsonResponse(['success' => false, 'message' => $message]);
        }
    }
}

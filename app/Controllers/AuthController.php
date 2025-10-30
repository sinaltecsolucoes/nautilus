<?php

/**
 * CLASSE CONTROLLER: AuthController
 * Local: app/Controllers/AuthController.php
 * Descrição: Gerencia as rotas e a lógica de autenticação (Login, Logout)
 * e interage com o FuncionarioModel.
 */

// Inclui o Model de Funcionário para acessar a lógica do DB
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';

class AuthController
{
    private $funcionarioModel;

    public function __construct()
    {
        // Inicializa o Model
        $this->funcionarioModel = new FuncionarioModel();
    }

    /**
     * Exibe a página de Login (View).
     */
    public function showLogin()
    {
        // Inclui a View de login para mostrar o formulário
        include __DIR__ . '/../Views/login.php';
    }

    /**
     * Processa a submissão do formulário de Login.
     */
    public function processLogin()
    {
        // Verifica se os dados foram enviados via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // Se não for POST, redireciona ou mostra erro
            $this->showLogin(['error' => 'Método de requisição inválido.']);
            return;
        }

        // 1. Coleta e Limpeza dos Dados
        $email = trim($_POST['email'] ?? '');
        $senha_pura = $_POST['senha'] ?? '';
        $error = '';

        if (empty($email) || empty($senha_pura)) {
            $error = 'Por favor, preencha o email e a senha.';
        }

        if (!empty($error)) {
            $this->showLogin(['error' => $error]);
            return;
        }

        // 2. Busca o usuário no Model
        $funcionario = $this->funcionarioModel->getFuncionarioByEmail($email);

        // 3. Verifica a Senha (Segurança!)
        if ($funcionario && password_verify($senha_pura, $funcionario['senha_hash'])) {

            // Autenticação BEM-SUCEDIDA!

            // 4. Inicia a Sessão e Armazena Dados
            session_start();
            $_SESSION['user_id'] = $funcionario['id'];
            $_SESSION['user_nome'] = $funcionario['nome_completo'];
            $_SESSION['user_cargo'] = $funcionario['tipo_cargo'];
            $_SESSION['logged_in'] = true;

            // 5. Redireciona para o Dashboard principal
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        } else {
            // Autenticação FALHOU!
            $error = 'Email ou senha incorretos.';
            $this->showLogin(['error' => $error]);
        }
    }

    /**
     * Realiza o Logout do usuário.
     */
    public function logout()
    {
        session_start();
        session_unset();    // Remove todas as variáveis de sessão
        session_destroy();  // Destrói a sessão

        // Redireciona para a página de login
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}

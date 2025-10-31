<?php

/**
 * CLASSE CONTROLLER: AuthController
 * Local: app/Controllers/AuthController.php
 * Descrição: Gerencia as rotas e a lógica de autenticação (Login, Logout)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
// Inclui o Model de Funcionário (necessário para a lógica de login)
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';

class AuthController
{
    private $funcionarioModel;

    public function __construct()
    {
        $this->funcionarioModel = new FuncionarioModel();
    }

    public function handleLogin()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processLogin();
        } else {
            $this->showLogin();
        }
    }

    /**
     * Exibe a página de Login (View Standalone - chamada via GET pelo Roteador).
     */
    public function showLogin()
    {
        // Garante a sessão para poder ler a mensagem de erro de $_SESSION['erro_login']
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $viewPath = ROOT_PATH . '/app/Views/login.php';

        if (file_exists($viewPath)) {
            // A View de Login é uma página HTML completa e lida com a exibição de erros
            require_once $viewPath;
        } else {
            http_response_code(500);
            echo "Erro: View de Login não encontrada.";
        }
    }

    /**
     * Processa a submissão do formulário de Login (chamada via POST pelo Roteador).
     */
    public function processLogin()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['erro_login']);

        // 1. Captura os dados: 'login' (email) e 'senha' (nomes dos campos do login.php)
        $email = trim($_POST['login'] ?? '');
        $senha_pura = $_POST['senha'] ?? '';
        $error = '';

        if (empty($email) || empty($senha_pura)) {
            $error = 'Por favor, preencha o login e a senha.';
        }

        if (!empty($error)) {
            $_SESSION['erro_login'] = $error;
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        // 2. Busca e Verifica a Senha (usando a lógica do seu projeto)
        $funcionario = $this->funcionarioModel->getFuncionarioByEmail($email);

        if ($funcionario && password_verify($senha_pura, $funcionario['senha_hash'])) {
            // SUCESSO
            $_SESSION['user_id'] = $funcionario['id'];
            $_SESSION['user_nome'] = $funcionario['nome_completo'];
            $_SESSION['user_cargo'] = $funcionario['tipo_cargo'];
            $_SESSION['logged_in'] = true;

            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        } else {
            // FALHA
            $_SESSION['erro_login'] = 'Login (Email) ou senha incorretos.';
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }

    /**
     * Realiza o Logout do usuário.
     */
    public function logout()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}

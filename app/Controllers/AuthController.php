<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\FuncionarioModel;

/**
 * Controller de Autenticação
 *
 */
class AuthController extends BaseController
{
    protected string $modulo = 'Auth';

    public function __construct(
        private readonly FuncionarioModel $funcionarioModel
    ) {
        if (session_status() === PHP_SESSION_NONE) {

            //Configura sessão com parâmetros seguros
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    /**
     * Decide se mostra login ou processa POST 
     * @return void
     */
    public function handleLogin()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processLogin();
        } else {
            $this->showLogin();
        }
    }
    /**
     * Exibe a página de login
     * @return void
     */
    public function showLogin()
    {
        $viewPath = ROOT_PATH . '/app/Views/login.php';

        if (file_exists($viewPath)) {

            $config = require ROOT_PATH . '/config/config.php'; // carrega config
            // A View de Login é uma página HTML completa e lida com a exibição de erros
            require_once $viewPath;
        } else {
            http_response_code(500);
            echo "Erro: View de Login não encontrada.";
            exit;
        }
    }

    /**
     * Processa submissão do formulário de Login
     * @return void
     */
    public function processLogin()
    {
        unset($_SESSION['erro_login']);

        // 1. Captura os dados: 'login' (email) e 'senha' (nomes dos campos do login.php)
        $email = trim($_POST['login'] ?? '');
        $senha_pura = $_POST['senha'] ?? '';

        // Validação básica
        if (empty($email) || empty($senha_pura)) {
            $_SESSION['erro_login'] = 'Por favor, preencha o login e a senha.';
            $this->redirectToLogin();
        }

        // Validação do formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['erro_login'] = 'Formato de email inválido';
            $this->redirectToLogin();
        }

        // 2. Busca e Verifica a Senha (usando a lógica do seu projeto)
        $funcionario = $this->funcionarioModel->getFuncionarioByEmail($email);

        if ($funcionario && password_verify($senha_pura, $funcionario['senha_hash'])) {
            // SUCESSO
            $this->fazerLogin($funcionario);
        } else {
            // FALHA
            $_SESSION['erro_login'] = 'Login (Email) ou senha incorretos.';
            $this->redirectToLogin();
            exit;
        }
    }

    /**
     * Realiza o Logout do usuário
     * @return void
     */
    public function logout()
    {
        // Limpa sessão
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        $this->redirectToLogin();
    }

    // =============================================
    // MÉTODOS AUXILIARES
    // =============================================

    private function fazerLogin(array $funcionario): void
    {
        $_SESSION['user_id']    = $funcionario['id'];
        $_SESSION['user_nome']  = $funcionario['nome_completo'];
        $_SESSION['user_cargo'] = $funcionario['tipo_cargo'];
        $_SESSION['logged_in']  = true;

        //Regerera ID da sessão
        session_regenerate_id(true);

        header('Location: ' . $this->getBaseUrl() . '/dashboard');
        exit;
    }

    private function redirectToLogin(): void
    {
        header('Location: ' . $this->getBaseUrl() . '/login');
        exit;
    }

    private function getBaseUrl(): string
    {
        $config = require ROOT_PATH . '/config/config.php';
        return $config['app']['base_url'];
    }

    public function refreshCsrf()
    {
        $token = $this->generateCsrfToken();
        $this->jsonResponse(['csrf_token' => $token]);
    }
}

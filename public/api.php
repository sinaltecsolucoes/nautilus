<?php

/**
 * ROTEADOR DA API REST (PONTO DE ENTRADA MOBILE)
 * Local: public/api.php
 * Descrição: Processa todas as requisições vindas do Aplicativo Android.
 */

// Define a raiz do projeto e inicia a sessão (Embora a API seja stateless, a sessão é útil para CSRF em alguns casos)
define('ROOT_PATH', dirname(__DIR__));
session_start();
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/Controllers/ApiController.php'; // Nosso novo Controller de API

// 1. Configuração do Header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Em produção, este valor deve ser o domínio do seu App
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Se for OPTIONS (requisição de pré-voo do navegador/app), encerra
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 2. Roteamento Simples da API
$route = $_GET['route'] ?? 'auth/login'; // Padrão: ir para o login
$controller = new ApiController();

// Mapeamento das rotas da API para [método do controller, requer autenticação?]
$routes = [
    // Rota de Autenticação (Pública)
    'auth/login'    => ['login', false],

    // Rotas Protegidas (Entrega/Lançamentos)
    'entrega/minhas-entregas' => ['getMinhasEntregas', true], // Lista as entregas do Motorista/Técnico
    'entrega/observacao'      => ['registrarObservacao', true], // Motorista/Técnico
    'entrega/abastecimento'   => ['registrarAbastecimento', true], // Motorista
    'entrega/confirmacao'     => ['confirmarCarregamento', true], // Encarregado
];

if (isset($routes[$route])) {
    list($methodName, $requiresAuth) = $routes[$route];

    // 3. Verificação de Token (Para rotas protegidas)
    if ($requiresAuth) {
        $authResult = $controller->checkAuthToken();
        if (!$authResult['success']) {
            http_response_code(401); // Não autorizado
            echo json_encode(['success' => false, 'message' => $authResult['message']]);
            exit;
        }
        // Se autenticado, o ID do usuário está em $authResult['user_id']
        // Podemos adicionar o ID ao POST para o controller
        $_POST['user_id_api'] = $authResult['user_id'];
    }

    // 4. Execução do Controller
    if (method_exists($controller, $methodName)) {
        $controller->$methodName();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Método não implementado no Controller.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Rota de API não encontrada.']);
}

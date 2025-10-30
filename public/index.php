<?php

/**
 * ROTEADOR CENTRAL (FRONT CONTROLLER)
 * Local: public/index.php
 * Descrição: Ponto de entrada para todas as requisições do NAUTILUS ERP.
 */

// Define a raiz do projeto (nautilus/) para ser usada nas inclusões
define('ROOT_PATH', dirname(__DIR__));

// 1. Configurações Globais
session_start(); // Inicia a sessão para controle de login/usuário
// Usa ROOT_PATH
require_once ROOT_PATH . '/config/config.php';

// 2. Inclusão dos Controllers 
// Usa ROOT_PATH
require_once ROOT_PATH . '/app/Controllers/AuthController.php';
require_once ROOT_PATH . '/app/Controllers/DashboardController.php';
require_once ROOT_PATH . '/app/Controllers/PermissaoController.php';
require_once ROOT_PATH . '/app/Controllers/EntidadeController.php';
require_once ROOT_PATH . '/app/Controllers/ManutencaoController.php';
require_once ROOT_PATH . '/app/Controllers/VeiculoController.php';
require_once ROOT_PATH . '/app/Controllers/FuncionarioController.php';

// 3. Definição do Roteamento
// Captura a rota da URL (agora vindo do .htaccess)
$route = $_GET['route'] ?? 'home';

// 1. Limpa barras e remove extensões .php (se existirem)
$route = trim($route, '/');
$route = str_replace('.php', '', $route);

// 2. Trata o prefixo 'public/' que o Apache pode adicionar
if (strpos($route, 'public/') === 0) {
    $route = substr($route, 7); // Remove "public/" (7 caracteres)
}

// 3. O NOME FINAL DA ROTA É A STRING COMPLETA (ex: 'entidades/api-busca')
if (empty($route)) {
    $route = 'home';
}

// Mapeamento das rotas para [Controller, método]
$routes = [
    // Rotas de Autenticação (Acesso Público)
    'login'     => ['AuthController', 'processLogin'],
    'logout'    => ['AuthController', 'logout'],

    // Rotas da Interface (Acesso Público ou Restrito)
    'home'      => ['DashboardController', 'showDashboard'],
    'dashboard' => ['DashboardController', 'showDashboard'],

    // Rotas de Permissões (Acesso Restrito ao Administrador)
    'permissoes'        => ['PermissaoController', 'index'],
    'permissoes-update' => ['PermissaoController', 'update'],

    // Rotas de Entidades (View)
    'clientes'          => ['EntidadeController', 'index'],
    'fornecedores'      => ['EntidadeController', 'index'],
    'transportadoras'   => ['EntidadeController', 'index'],

    // Rotas de Manutenção 
    'manutencao'            => ['ManutencaoController', 'index'],
    'manutencao/listar'     => ['ManutencaoController', 'listarManutencoes'],

    // Rotas de Manutenção (AÇÕES)
    'manutencao/salvar'         => ['ManutencaoController', 'salvarManutencao'],
    'manutencao/veiculos-options' => ['ManutencaoController', 'getVeiculosOptions'],
    'manutencao/fornecedores-options' => ['ManutencaoController', 'getFornecedoresOptions'],

    // Rotas de Veículos
    'veiculos'          => ['VeiculoController', 'index'],
    'veiculos/listar'   => ['VeiculoController', 'listarVeiculos'],
    'veiculos/salvar'   => ['VeiculoController', 'salvarVeiculo'],
    'veiculos/get'      => ['VeiculoController', 'getVeiculo'],
    'veiculos/deletar'  => ['VeiculoController', 'deleteVeiculo'],

    // Rotas de Integração (AJAX)
    'entidades/api-busca' => ['EntidadeController', 'buscarApiDados'],
    'entidades/clientes-options' => ['EntidadeController', 'getClientesOptions'],

    // Rotas de Ações CRUD para o Módulo de Entidades
    'entidades/listar' => ['EntidadeController', 'listarEntidades'],    // Para DataTables
    'entidades/get'    => ['EntidadeController', 'getEntidade'],       // Para Edição
    'entidades/salvar' => ['EntidadeController', 'salvarEntidade'],    // Criação/Atualização

    // Rotas de Funcionários (Usuários)
    'usuarios'          => ['FuncionarioController', 'index'],
    'usuarios/listar'   => ['FuncionarioController', 'listarFuncionarios'],
    'usuarios/get'      => ['FuncionarioController', 'getFuncionario'],
    'usuarios/salvar'   => ['FuncionarioController', 'salvarFuncionario'],
    'usuarios/deletar'  => ['FuncionarioController', 'deleteFuncionario'],

    // Rotas de Vendas/Pedidos
    'pedidos'           => ['PedidoController', 'index'],
    'pedidos/listar'    => ['PedidoController', 'listarPedidos'],
    'pedidos/salvar'    => ['PedidoController', 'salvarPedido'],

    // Rotas de Logística/Expedição 
    'logistica'             => ['ExpedicaoController', 'index'],
    'expedicao/listar'      => ['ExpedicaoController', 'listarExpedicoes'],
    'expedicao/salvar'      => ['ExpedicaoController', 'salvarExpedicao'],
    'expedicao/pessoal-options' => ['ExpedicaoController', 'getPessoalOptions'],
    'expedicao/next-carga-number' => ['ExpedicaoController', 'getNextCargaNumber'], 
];

// 4. Lógica de Acesso (Verificação de Login)
$is_public_route = in_array($route, ['login', 'logout', 'forgot_password', 'register']);
$is_logged_in = $_SESSION['logged_in'] ?? false;

if (!$is_logged_in && !$is_public_route) {
    // Se não estiver logado E a rota não for pública, força o login
    $controller = new AuthController();
    $controller->showLogin();
    exit;
}

// Se a rota for 'login' e o usuário já estiver logado, redireciona para o dashboard
if ($is_logged_in && $route === 'login') {
    // Redireciona para a URL Amigável
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

// 5. Execução do Controller
if (isset($routes[$route])) {
    list($controllerName, $methodName) = $routes[$route];

    // Verifica se o Controller existe (AGORA DEVE FUNCIONAR DEVIDO AO ROOT_PATH)
    if (class_exists($controllerName)) {
        $controller = new $controllerName();

        // Verifica se o método existe no Controller
        if (method_exists($controller, $methodName)) {
            // Execução do método
            if (($route === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') || $route === 'permissoes-update') {
                $controller->$methodName();
            } else {
                $controller->$methodName();
            }
        } else {
            http_response_code(404);
            echo "Erro 404: Método '{$methodName}' não encontrado no Controller '{$controllerName}'.";
        }
    } else {
        http_response_code(404);
        echo "Erro 404: Controller '{$controllerName}' não encontrado.";
    }
} else {
    // Rota não mapeada (404)
    http_response_code(404);
    echo "Erro 404: Rota não encontrada. Tente " . BASE_URL . "/login"; // URL Amigável no erro!
}

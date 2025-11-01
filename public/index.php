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
require_once ROOT_PATH . '/config/config.php';

// 2. Inclusão dos Controllers
require_once ROOT_PATH . '/app/Controllers/AuthController.php';
require_once ROOT_PATH . '/app/Controllers/DashboardController.php';
require_once ROOT_PATH . '/app/Controllers/PermissaoController.php';
require_once ROOT_PATH . '/app/Controllers/EntidadeController.php';
require_once ROOT_PATH . '/app/Controllers/ManutencaoController.php';
require_once ROOT_PATH . '/app/Controllers/VeiculoController.php';
require_once ROOT_PATH . '/app/Controllers/FuncionarioController.php';

// 3. Definição do Roteamento
$route = $_GET['route'] ?? 'home';

// Limpa barras e remove extensões .php
$route = trim($route, '/');
$route = str_replace('.php', '', $route);

// Trata o prefixo 'public/' que o Apache pode adicionar
if (strpos($route, 'public/') === 0) {
    $route = substr($route, 7);
}

if (empty($route)) {
    $route = 'home';
}

// Mapeamento das rotas para [Controller, método]
$routes = [
    // Rotas de Autenticação
    'login'     => ['AuthController', 'handleLogin'],   // GET: exibe tela | POST: processa login
    'logout'    => ['AuthController', 'logout'],

    // Rotas da Interface
    'home'      => ['DashboardController', 'showDashboard'],
    'dashboard' => ['DashboardController', 'showDashboard'],

    // Rotas de Permissões
    'permissoes'        => ['PermissaoController', 'index'],
    'permissoes-update' => ['PermissaoController', 'update'],

    // Rotas de Entidades (View)
    'clientes'          => ['EntidadeController', 'index'],
    'fornecedores'      => ['EntidadeController', 'index'],
    'transportadoras'   => ['EntidadeController', 'index'],

    // Rotas de Manutenção
    'manutencao'                    => ['ManutencaoController', 'index'],
    'manutencao/listar'             => ['ManutencaoController', 'listarManutencoes'],
    'manutencao/salvar'             => ['ManutencaoController', 'salvarManutencao'],
    'manutencao/veiculos-options'   => ['ManutencaoController', 'getVeiculosOptions'],
    'manutencao/fornecedores-options' => ['ManutencaoController', 'getFornecedoresOptions'],

    // Rotas de Veículos
    'veiculos'          => ['VeiculoController', 'index'],
    'veiculos/listar'   => ['VeiculoController', 'listarVeiculos'],
    'veiculos/salvar'   => ['VeiculoController', 'salvarVeiculo'],
    'veiculos/get'      => ['VeiculoController', 'getVeiculo'],
    'veiculos/deletar'  => ['VeiculoController', 'deleteVeiculo'],

    // Rotas de Integração (AJAX)
    'entidades/api-busca'           => ['EntidadeController', 'buscarApiDados'],
    'entidades/clientes-options'    => ['EntidadeController', 'getClientesOptions'],

    // Rotas de Ações CRUD para Entidades
    'entidades/listar' => ['EntidadeController', 'listarEntidades'],
    'entidades/get'    => ['EntidadeController', 'getEntidade'],
    'entidades/salvar' => ['EntidadeController', 'salvarEntidade'],
    'entidades/deletar' => ['EntidadeController', 'deleteEntidade'],

    // Rotas de Ações CRUD para Endereços Adicionais
    'entidades/enderecos/listar' => ['EntidadeController', 'listarEnderecosAdicionais'],
    'entidades/enderecos/salvar' => ['EntidadeController', 'salvarEnderecoAdicional'],
    'entidades/enderecos/get'    => ['EntidadeController', 'getEnderecoAdicional'],  
    'entidades/enderecos/deletar' => ['EntidadeController', 'deleteEnderecoAdicional'], 

    // Rotas de Funcionários
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
    'logistica'                 => ['ExpedicaoController', 'index'],
    'expedicao/listar'          => ['ExpedicaoController', 'listarExpedicoes'],
    'expedicao/salvar'          => ['ExpedicaoController', 'salvarExpedicao'],
    'expedicao/pessoal-options' => ['ExpedicaoController', 'getPessoalOptions'],
    'expedicao/next-carga-number' => ['ExpedicaoController', 'getNextCargaNumber'],

    // Rotas de Relatórios
    'relatorios'                => ['RelatorioController', 'index'],
    'relatorios/manutencao-pdf' => ['RelatorioController', 'gerarManutencaoPdf'],
];

// 4. Rotas públicas (não exigem login)
$public_routes = ['login', 'logout', 'home'];
$is_public_route = in_array($route, $public_routes);
$is_logged_in = !empty($_SESSION['logged_in']);

// CORREÇÃO: Evitar redirecionamento infinito

// Se NÃO logado e rota protegida → vai para login
if (!$is_logged_in && !$is_public_route) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// Se JÁ logado e tenta acessar login ou home → vai para dashboard
if ($is_logged_in && in_array($route, ['login', 'home'])) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

// 5. Execução do Controller
if (isset($routes[$route])) {
    [$controllerName, $methodName] = $routes[$route];

    if (class_exists($controllerName)) {
        $controller = new $controllerName();

        if (method_exists($controller, $methodName)) {
            // handleLogin decide GET ou POST
            $controller->$methodName();
        } else {
            http_response_code(404);
            echo "Erro 404: Método '{$methodName}' não encontrado em '{$controllerName}'.";
        }
    } else {
        http_response_code(404);
        echo "Erro 404: Controller '{$controllerName}' não encontrado.";
    }
} else {
    http_response_code(404);
    echo "Erro 404: Rota não encontrada. Acesse: " . BASE_URL . "/login";
}

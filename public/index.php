<?php

/**
 * ROTEADOR CENTRAL (FRONT CONTROLLER)
 * Local: public/index.php
 * Descrição: Ponto de entrada para todas as requisições do NAUTILUS ERP.
 */

declare(strict_types=1);

// Define a raiz do projeto para ser usada nas inclusões
define('ROOT_PATH', dirname(__DIR__));

// Carrega configuração
$config = require ROOT_PATH . '/config/config.php';


//Parâmetros de sessão segura
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'],
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

// 1. Configurações Globais
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Inicia a sessão para controle de login/usuário
}

// Define 'BASE_URL' como constante global para ser usada nas views
define('BASE_URL', $config['app']['base_url']);

// Carrega o Autoloader Manual  
require_once ROOT_PATH . '/app/Core/Autoload.php';

// Autoload
require_once ROOT_PATH . '/app/Core/Autoload.php';

// Carrega funções globais (Helpers)
if (file_exists(ROOT_PATH . '/app/Helpers/functions.php')) {
    require_once ROOT_PATH . '/app/Helpers/functions.php';
}

// ===============================================================
// Roteamento
// ===============================================================

// 2. Definição do Roteamento
$route = $_GET['route'] ?? 'home';

// Limpa barras e remove extensões .php
$route = strtolower(trim(str_replace('.php', '', $route), '/'));

// Trata o prefixo 'public/' que o servidor pode adicionar
if (strpos($route, 'public/') === 0) {
    $route = substr($route, 7);
}

if (empty($route)) {
    $route = 'home';
}

// Mapeamento das rotas para [NomeDaClasse, NomeDoMetodo]
$routes = [
    // Autenticação
    'login'             => ['AuthController', 'handleLogin', true],
    'logout'            => ['AuthController', 'logout', true],
    'auth/refresh-csrf' => ['AuthController', 'refreshCsrf', true],

    // Interface Geral
    'home'      => ['DashboardController', 'showDashboard', true],
    'dashboard' => ['DashboardController', 'showDashboard'],

    // Permissões
    'permissoes'        => ['PermissaoController', 'index'],
    'permissoes/salvar' => ['PermissaoController', 'salvar'],

    // Entidades
    'clientes'          => ['EntidadeController', 'index'],
    'fornecedores'      => ['EntidadeController', 'index'],
    'transportadoras'   => ['EntidadeController', 'index'],

    // Entidades (API/AJAX)
    'entidades/listar'           => ['EntidadeController', 'listarEntidades'],
    'entidades/salvar'           => ['EntidadeController', 'salvarEntidade'],
    'entidades/get'              => ['EntidadeController', 'getEntidade'],
    'entidades/deletar'          => ['EntidadeController', 'deleteEntidade'],
    'entidades/api-busca'        => ['EntidadeController', 'buscarApiDados'],
    'entidades/clientes-options' => ['EntidadeController', 'getClientesOptions'],
    'entidades/proximo-codigo'   => ['EntidadeController', 'getProximoCodigo'],

    // Endereços
    'entidades/enderecos/listar'  => ['EntidadeController', 'listarEnderecosAdicionais'],
    'entidades/enderecos/salvar'  => ['EntidadeController', 'salvarEnderecoAdicional'],
    'entidades/enderecos/get'     => ['EntidadeController', 'getEnderecoAdicional'],
    'entidades/enderecos/deletar' => ['EntidadeController', 'deleteEnderecoAdicional'],

    // Manutenção
    'manutencao'                        => ['ManutencaoController', 'index'],
    'manutencao/listar'                 => ['ManutencaoController', 'listar'],
    'manutencao/salvar'                 => ['ManutencaoController', 'salvar'],
    'manutencao/get'                    => ['ManutencaoController', 'get'],
    'manutencao/deletar'                => ['ManutencaoController', 'deletar'],
    'manutencao/getVeiculosOptions'     => ['ManutencaoController', 'getVeiculosOptions'],
    'manutencao/getFornecedoresOptions' => ['ManutencaoController', 'getFornecedoresOptions'],

    // Veículos
    'veiculos'                           => ['VeiculoController', 'index'],
    'veiculos/listar'                    => ['VeiculoController', 'listarVeiculos'],
    'veiculos/salvar'                    => ['VeiculoController', 'salvarVeiculo'],
    'veiculos/get'                       => ['VeiculoController', 'getVeiculo'],
    'veiculos/deletar'                   => ['VeiculoController', 'deleteVeiculo'],
    'veiculos/getProprietariosOptions'   => ['VeiculoController', 'getProprietariosOptions'],

    // Funcionários
    'usuarios'          => ['FuncionarioController', 'index'],
    'usuarios/listar'   => ['FuncionarioController', 'listarFuncionarios'],
    'usuarios/get'      => ['FuncionarioController', 'getFuncionario'],
    'usuarios/salvar'   => ['FuncionarioController', 'salvarFuncionario'],
    'usuarios/deletar'  => ['FuncionarioController', 'deleteFuncionario'],

    // Pedidos
    'pedidos'                    => ['PedidoController', 'index'],
    'pedidos/listar'             => ['PedidoController', 'listar'],
    'pedidos/salvar'             => ['PedidoController', 'salvar'],
    'pedidos/get'                => ['PedidoController', 'get'],
    'pedidos/deletar'            => ['PedidoController', 'deletePedido'],
    'pedidos/getClientesOptions' => ['PedidoController', 'getClientesOptions'],
    'pedidos/next-os'            => ['PedidoController', 'getNextOSNumber'],

    // Expedição
    'expedicao'                        => ['ExpedicaoController', 'index'],
    'expedicao/listar'                 => ['ExpedicaoController', 'listar'],
    'expedicao/salvar'                 => ['ExpedicaoController', 'salvar'],
    'expedicao/get'                    => ['ExpedicaoController', 'get'],
    'expedicao/deletar'                => ['ExpedicaoController', 'deletar'],
    'expedicao/getVeiculosOptions'     => ['ExpedicaoController', 'getVeiculosOptions'],
    'expedicao/getPessoalOptions'      => ['ExpedicaoController', 'getPessoalOptions'],
    'expedicao/getMotoristasOptions'   => ['ExpedicaoController', 'getMotoristasOptions'],
    'expedicao/getClientesOptions'     => ['ExpedicaoController', 'getClientesOptions'],
    'expedicao/reordenar'              => ['ExpedicaoController', 'reordenar'],
    'expedicao/romaneio'               => ['ExpedicaoController', 'gerarRomaneio'],

    // Abastecimentos
    'abastecimentos'                    => ['AbastecimentoController', 'index'],
    'abastecimentos/listar'             => ['AbastecimentoController', 'listar'],
    'abastecimentos/salvar'             => ['AbastecimentoController', 'salvar'],
    'abastecimentos/get'                => ['AbastecimentoController', 'get'],
    'abastecimentos/deletar'            => ['AbastecimentoController', 'deletar'],
    'abastecimentos/getPostosOptions'   => ['AbastecimentoController', 'getPostosOptions'],
    'abastecimentos/getVeiculosOptions' => ['AbastecimentoController', 'getVeiculosOptions'],

    // Relatórios
    'relatorios'                => ['RelatorioController', 'index'],
    'relatorios/manutencao-pdf' => ['RelatorioController', 'gerarManutencaoPdf'],
];

// ======================================================
// Middleware de Autenticação
// ======================================================

$is_logged_in = !empty($_SESSION['logged_in']);
$routeConfig = $routes[$route] ?? null;

if ($routeConfig) {
    [$controllerName, $methodName, $isPublic] = array_pad($routeConfig, 3, false);

    // Se rota protegida e usuário não logado → vai para login
    if (!$isPublic && !$is_logged_in) {
        header('Location: ' . $config['app']['base_url'] . '/login');
        exit;
    }

    // Se logado e tenta acessar login/home → vai para dashboard
    if ($is_logged_in && in_array($route, ['login', 'home'])) {
        header('Location: ' . $config['app']['base_url'] . '/dashboard');
        exit;
    }

    // ======================================================
    // Execução do Controller
    // ======================================================

    $controllerClass = "App\\Controllers\\" . $controllerName;

    if (class_exists($controllerClass)) {
        if ($controllerName == 'AuthController') {
            $controller = new $controllerClass(new \App\Models\FuncionarioModel());
        } else {
            $controller = new $controllerClass();
        }
        if (method_exists($controller, $methodName)) {
            $controller->$methodName();
        } else {
            http_response_code(404);
            echo "Erro 404: Método '{$methodName}' não encontrado em '{$controllerName}'.";
        }
    } else {
        http_response_code(404);
        echo "Erro 404: Controller '{$controllerClass}' não encontrado ou não carregado pelo Autoload.";
    }
} else {
    http_response_code(404);
    echo "Erro 404: Rota '{$route}' não encontrada.";
}

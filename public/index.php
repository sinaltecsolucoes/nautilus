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

// Carrega o Autoloader Manual
require_once ROOT_PATH . '/app/Core/Autoload.php';

// Carrega configurações
//require_once ROOT_PATH . '/config/config.php';

// Carrega funções globais (Helpers)
if (file_exists(ROOT_PATH . '/app/Helpers/functions.php')) {
    require_once ROOT_PATH . '/app/Helpers/functions.php';
}

// 2. Definição do Roteamento
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

// Mapeamento das rotas para [NomeDaClasse, NomeDoMetodo]
$routes = [
    // Autenticação
    'login'     => ['AuthController', 'handleLogin'],
    'logout'    => ['AuthController', 'logout'],

    // Interface Geral
    'home'      => ['DashboardController', 'showDashboard'],
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
    'entidades/getEntidade'      => ['EntidadeController', 'getEntidade'],
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

// 3. Rotas públicas (não exigem login)
$public_routes = ['login', 'logout', 'home'];
$is_public_route = in_array($route, $public_routes);
$is_logged_in = !empty($_SESSION['logged_in']);

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

// 4. Execução do Controller
if (isset($routes[$route])) {
    [$controllerName, $methodName] = $routes[$route];

    // Namespace completo
    $controllerClass = "App\\Controllers\\" . $controllerName;

    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();

        if (method_exists($controller, $methodName)) {
            // handleLogin decide GET ou POST
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
    echo "Erro 404: Rota não encontrada. Acesse: " . BASE_URL . "/login";
}

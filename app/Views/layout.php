    <?php
    // Carrega configuração como array
    $config = require ROOT_PATH . '/config/config.php';

    // Dados usuário logado
    $usuarioNome = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
    $usuarioCargo = htmlspecialchars($_SESSION['user_cargo'] ?? 'Visitante', ENT_QUOTES, 'UTF-8');

    // Título da pagina
    $pageTitle = htmlspecialchars($title ?? $config['app']['name'], ENT_QUOTES, 'UTF-8');

    // URLs de assets
    $TEMA_BASE_URL = $config['app']['base_url'] . '/assets/theme/sb-admin-themewagon/dist';
    $THEME_CSS     = $TEMA_BASE_URL . '/css';
    $THEME_JS      = $TEMA_BASE_URL . '/js';
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?> | NAUTILUS ERP</title>

        <link href="<?php echo $TEMA_BASE_URL; ?>/css/styles.css" rel="stylesheet" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
        <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <link href="<?php $config['app']['base_url']; ?>/assets/css/style.css" rel="stylesheet" />
    </head>

    <body class="sb-nav-fixed"

        data-base-url="<?php $config['app']['base_url']; ?>"
        data-csrf-token="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
        data-page-module="<?php echo $data['pageScript'] ?? 'unknown'; ?>"
        data-debug="<?php getenv('APP_ENV') === 'dev' ? 'true' : 'false'; ?>">

        <!-- Navbar -->
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <a class="navbar-brand ps-3" href="<?php $config['app']['base_url']; ?>/dashboard">NAUTILUS ERP</a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="navbar-nav ms-auto me-3">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user fa-fw"></i> <?php echo $usuarioNome; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php $config['app']['base_url']; ?>/logout">Sair</a></li>
                    </ul>
                </li>
            </ul>
        </nav>

        <!-- Sidebar + Conteúdo -->
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Geral</div>
                            <a class="nav-link" href="<?php $config['app']['base_url']; ?>/dashboard">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Dashboard
                            </a>

                            <div class="sb-sidenav-menu-heading">Módulos</div>
                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseCadastros">
                                <div class="sb-nav-link-icon"><i class="fas fa-columns"></i></div>
                                Cadastros Base
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>

                            <div class="collapse" id="collapseCadastros">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="<?php $config['app']['base_url']; ?>/clientes">Clientes</a>
                                    <a class="nav-link" href="<?php $config['app']['base_url']; ?>/fornecedores">Fornecedores</a>
                                    <a class="nav-link" href="<?php $config['app']['base_url']; ?>/transportadoras">Transportadoras</a>
                                    <a class="nav-link" href="<?php $config['app']['base_url']; ?>/veiculos">Veículos</a>
                                </nav>
                            </div>

                            <a class="nav-link" href="<?php $config['app']['base_url']; ?>/pedidos">
                                <div class="sb-nav-link-icon"><i class="fas fa-funnel-dollar"></i></div>
                                Vendas / Pedidos
                            </a>

                            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLogistica">
                                <div class="sb-nav-link-icon"><i class="fas fa-columns"></i></div>
                                Logistica
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>

                            <div class="collapse" id="collapseLogistica">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="<?php $config['app']['base_url']; ?>/expedicao">Expedição</a>
                                    <a class="nav-link" href="<?php $config['app']['base_url']; ?>/abastecimentos">Abastecimentos</a>
                                    <a class="nav-link" href="<?php $config['app']['base_url']; ?>/manutencao">Manutenção</a>
                                </nav>
                            </div>

                            <div class="sb-sidenav-menu-heading">Admin</div>
                            <a class="nav-link" href="<?php $config['app']['base_url']; ?>/usuarios">
                                <div class="sb-nav-link-icon"><i class="fas fa-users-cog"></i></div>
                                Usuários
                            </a>
                            <a class="nav-link" href="<?php $config['app']['base_url']; ?>/permissoes">
                                <div class="sb-nav-link-icon"><i class="fas fa-user-shield"></i></div>
                                Permissões
                            </a>
                            <a class="nav-link" href="<?php $config['app']['base_url']; ?>/relatorios">
                                <div class="sb-nav-link-icon"><i class="fas fa-file-pdf"></i></div>
                                Relatórios
                            </a>
                        </div>
                    </div>
                    <div class="sb-sidenav-footer">
                        <div class="small">Logado como:</div>
                        <?php echo $usuarioCargo; ?>
                    </div>
                </nav>
            </div>

            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4"><?php echo $pageTitle; ?></h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="<?php $config['app']['base_url']; ?>/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active"><?php echo $pageTitle; ?></li>
                        </ol>
                        <?php echo $content; ?>
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; <?php $config['app']['name']; ?> <?php echo date('Y'); ?></div>
                            <div><a href="#">Privacidade</a> &middot; <a href="#">Termos</a></div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>

        <!-- ==================== SCRIPTS (ordem CRÍTICA) ==================== -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- DataTables Core  -->
        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

        <!-- DataTables + Bootstrap 5 Integration -->
        <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

        <!-- Bootstrap -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Outras libs -->
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
        <script src="<?php $config['app']['base_url']; ?>/assets/js/config.js"></script>
        <script src="<?php $config['app']['base_url']; ?>/assets/js/app.js"></script>
        <script src="<?php $config['app']['base_url']; ?>/assets/js/datatables-pt-BR.js"></script>
        <script src="<?php echo $THEME_JS; ?>/scripts.js"></script>

        <!-- Script específico da página (entidades.js, funcionarios.js, etc.) -->
        <?php if (isset($data['pageScript'])) : ?>
            <script src="<?php $config['app']['base_url']; ?>/assets/js/<?php echo $data['pageScript']; ?>.js"></script>
        <?php endif; ?>
        <!-- ================================================================ -->

    </body>

    </html>
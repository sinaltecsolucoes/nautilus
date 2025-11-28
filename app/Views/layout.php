<?php
require_once __DIR__ . '/../../config/config.php';
$usuarioNome = $_SESSION['user_nome'] ?? 'Usuário';
$usuarioCargo = $_SESSION['user_cargo'] ?? 'Visitante';
$pageTitle = htmlspecialchars($title ?? APP_NAME);

$TEMA_BASE_URL = BASE_URL . '/assets/theme/sb-admin-themewagon/dist';
$THEME_CSS = $TEMA_BASE_URL . '/css';
$THEME_JS  = $TEMA_BASE_URL . '/js';
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
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet" />
</head>

<body class="sb-nav-fixed">

    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand ps-3" href="<?php echo BASE_URL; ?>/dashboard">NAUTILUS ERP</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!">
            <i class="fas fa-bars"></i>
        </button>

        <ul class="navbar-nav ms-auto me-3">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user fa-fw"></i> <?php echo $usuarioNome; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout">Sair</a></li>
                </ul>
            </li>
        </ul>
    </nav>

    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading">Geral</div>
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/dashboard">
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
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/clientes">Clientes</a>
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/fornecedores">Fornecedores</a>
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/transportadoras">Transportadoras</a>
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/veiculos">Veículos</a>
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/manutencao">Manutenção</a>
                            </nav>
                        </div>

                        <a class="nav-link" href="<?php echo BASE_URL; ?>/pedidos">
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
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/logistica">Expedição</a>
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/abastecimentos">Abastecimentos</a>
                            </nav>
                        </div>

                        <div class="sb-sidenav-menu-heading">Admin</div>
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/usuarios">
                            <div class="sb-nav-link-icon"><i class="fas fa-users-cog"></i></div>
                            Usuários
                        </a>
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/permissoes">
                            <div class="sb-nav-link-icon"><i class="fas fa-user-shield"></i></div>
                            Permissões
                        </a>
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/relatorios">
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
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active"><?php echo $pageTitle; ?></li>
                    </ol>
                    <?php echo $content; ?>
                </div>
            </main>
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; <?php echo APP_NAME; ?> <?php echo date('Y'); ?></div>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo $THEME_JS; ?>/scripts.js"></script>

    <!-- Script específico da página (entidades.js, funcionarios.js, etc.) -->
    <?php if (isset($data['pageScript'])) : ?>
        <script src="<?php echo BASE_URL; ?>/assets/js/<?php echo $data['pageScript']; ?>.js"></script>
    <?php endif; ?>
    <!-- ================================================================ -->

    <script src="<?= BASE_URL ?>/assets/js/datatables-pt-BR.js"></script>

</body>

</html>
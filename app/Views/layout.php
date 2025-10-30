<?php

/**
 * ARQUIVO DE LAYOUT CENTRAL
 * Local: app/Views/layout.php
 * Descrição: O Template HTML principal para todas as páginas logadas do sistema.
 * * @param string $content O conteúdo específico da View a ser injetada.
 * @param string $title O título da página.
 */
// Assegura que as constantes de configuração estejam carregadas
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? APP_NAME); ?> | NAUTILUS ERP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH57PUX8zP1Rz3p44pT77x5+6L3FjWfB2i4F1QzC/tT5iJ8X5F5j1Z2h7n8J5g/OQ5wM8B8B5g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">

</head>

<body>
    <div id="wrapper">
        <header>
            <nav id="main-nav" class="navbar navbar-expand navbar-dark bg-primary shadow">
                <div class="container-fluid">
                    <a class="navbar-brand me-4" href="<?php echo BASE_URL; ?>/dashboard">NAUTILUS</a>

                    <div class="collapse navbar-collapse">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/dashboard">Dashboard</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Cadastros
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/clientes">Clientes</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/fornecedores">Fornecedores</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/transportadoras">Transportadoras</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/veiculos">Cadastro de Veículos</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/manutencao">Controle de Manutenção</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/usuarios">Usuários (CRUD)</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/permissoes">Permissões (ACL)</a></li>
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/pedidos">Vendas/Pedidos</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/logistica">Logística/Expedição</a></li>
                        </ul>

                    </div>

                    <div class="d-flex">
                        <span class="navbar-text me-3">
                            Bem-vindo(a), <?php echo $_SESSION['user_nome'] ?? 'Usuário'; ?>
                        </span>
                        <a href="<?php echo BASE_URL; ?>/logout" class="btn btn-outline-light">Sair</a>
                    </div>
                </div>
            </nav>
        </header>

        <main id="content-area" class="container-fluid py-4">
            <h2 class="mb-4"><?php echo htmlspecialchars($title ?? 'NAUTILUS ERP'); ?></h2>

            <?php echo $content; ?>
        </main>

        <footer class="footer bg-light py-3 mt-auto">
            <div class="container-fluid">
                <p class="text-center text-muted m-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos os direitos reservados.</p>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>

    <?php if (isset($title)): ?>
        <?php if (strpos($title, 'Permissões') !== false): ?>
            <script src="<?php echo BASE_URL; ?>/assets/js/permissoes.js"></script>
        <?php elseif (strpos($title, 'Manutenção') !== false): ?>
            <script src="<?php echo BASE_URL; ?>/assets/js/manutencao.js"></script>
        <?php elseif (strpos($title, 'Funcionários') !== false): ?>
            <script src="<?php echo BASE_URL; ?>/assets/js/funcionarios.js"></script>
        <?php endif; ?>
    <?php endif; ?>
</body>

</html>
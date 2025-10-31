<?php
// nautilus/app/Views/auth_layout.php

require_once __DIR__ . '/../../config/config.php';
$pageTitle = htmlspecialchars($title ?? APP_NAME);

// Definindo os caminhos dos assets do tema (MESMA CORREÇÃO FEITA ANTERIORMENTE)
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
    <title><?php echo $pageTitle; ?> | Login NAUTILUS ERP</title>

    <link href="<?php echo $TEMA_BASE_URL; ?>/css/styles.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
</head>

<body class="bg-primary">
    <div id="layoutAuthentication">
        <div id="layoutAuthentication_content">
            <main>
                <div class="container">
                    <?php echo $content; ?>
                </div>
            </main>
        </div>

        <div id="layoutAuthentication_footer">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $THEME_JS; ?>/scripts.js"></script>
</body>

</html>
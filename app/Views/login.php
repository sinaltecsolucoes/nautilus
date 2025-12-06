<?php
// nautilus/app/Views/login.php
// View da tela de login para NAUTULUS ERP
// Esta é uma View completa (Standalone).

// Nota: A variável BASE_URL deve ser definida em config.php e incluída no roteador (index.php)

$mensagem_erro_login = '';
// Verifica se há mensagem de erro na sessão
if (isset($_SESSION['erro_login'])) {
    $mensagemErro = htmlspecialchars($_SESSION['erro_login'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['erro_login']); // Limpa o erro após exibição
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>NAUTULUS ERP - Login</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS customizado -->
    <link href="<?php echo $config['app']['base_url']; ?>/assets/css/login.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">

                <div id="login-box" class="card shadow-sw p-4">

                    <div class="text-center mb-4">

                        <img src="<?php echo BASE_URL; ?>/assets/img/nautilus_logo_placeholder.png"
                            alt="Logo NAUTULUS ERP"
                            id="login-logo"
                            class="img-fluid"
                            style="max-height: 80px;">

                        <form id="login-form"
                            class="form"
                            action="<?php echo BASE_URL; ?>/login"
                            method="post" novalidate>

                            <?php if (!empty($mensagemErro)): ?>
                                <div class="alert alert-danger text-center" role="alert">
                                    <?php echo $mensagemErro; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="login-usuario" class="form-label">Login (Email):</label>
                                <input type="email"
                                    name="login"
                                    id="login-usuario"
                                    class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="senha" class="form-label">Senha:</label>
                                <input type="password"
                                    name="senha"
                                    id="senha"
                                    class="form-control" required>

                                <div class="form-check d-flex align-items=center gap-2 mt-2">
                                    <input class="form-check-input" type="checkbox" id="exibir-senha-login">
                                    <label class="form-check-label mb-0" for="exibir-senha-login">Exibir Senha</label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit"
                                    name="conectar"
                                    class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i>Entrar
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const senhaInput = document.getElementById('senha');
                const toggleSenha = document.getElementById('exibir-senha-login');
                if (senhaInput && toggleSenha) {
                    toggleSenha.addEventListener('change', () => {
                        senhaInput.type = toggleSenha.checked ? 'text' : 'password';
                    });
                }
            });
        </script>
</body>

</html>
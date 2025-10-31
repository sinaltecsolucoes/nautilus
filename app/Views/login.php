<?php
// nautilus/app/Views/login.php
// View da tela de login adaptada do SGI ERP para NAUTULUS ERP
// Esta é uma View completa (Standalone).

// Nota: A variável BASE_URL deve ser definida em config.php e incluída no roteador (index.php)

$mensagem_erro_login = '';
// Verifica se há mensagem de erro na sessão
if (isset($_SESSION['erro_login'])) {
    $mensagem_erro_login = htmlspecialchars($_SESSION['erro_login']);
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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="<?php echo BASE_URL; ?>/assets/css/login.css" rel="stylesheet">

</head>

<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">

                <div id="login-box">

                    <img src="<?php echo BASE_URL; ?>/assets/img/nautilus_logo_placeholder.png" alt="Logo NAUTULUS ERP" id="login-logo">

                    <form id="login-form" class="form" action="<?php echo BASE_URL; ?>/login" method="post">

                        <?php if (!empty($mensagem_erro_login)): ?>
                            <div class="alert alert-danger text-center" role="alert">
                                <?php echo $mensagem_erro_login; ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-group text-start mb-3">
                            <label for="login-usuario">Login (Email):</label>
                            <input type="text" name="login" id="login-usuario" class="form-control" required>
                        </div>
                        <div class="form-group text-start mb-3">
                            <label for="senha">Senha:</label>
                            <input type="password" name="senha" id="senha" class="form-control" required>

                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="exibir-senha-login">
                                <label class="form-check-label" for="exibir-senha-login">Exibir Senha</label>
                            </div>
                        </div>
                        <div class="form-group text-center">
                            <input type="submit" name="conectar" class="btn btn-info" value="Entrar">
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const elSenha = document.getElementById('senha');
            const elCheckbox = document.getElementById('exibir-senha-login');
            if (elSenha && elCheckbox) {
                elCheckbox.addEventListener('change', function() {
                    elSenha.type = this.checked ? 'text' : 'password';
                });
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
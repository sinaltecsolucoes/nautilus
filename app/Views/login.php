<?php
// Inclui o arquivo de configuração para usar a constante BASE_URL
require_once __DIR__ . '/../../config/config.php';

$error_message = $data['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>

<body>
    <div class="login-container">
        <h2>Acesso - <?php echo APP_NAME; ?></h2>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/login" method="POST">

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">Entrar no Sistema</button>

            <p style="margin-top: 15px;">
                <a href="<?php echo BASE_URL; ?>/forgot_password" style="color: #1a4d2e; text-decoration: none;">Esqueceu sua senha?</a>
            </p>
        </form>
    </div>
</body>

</html>
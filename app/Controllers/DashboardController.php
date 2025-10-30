<?php

/**
 * CLASSE CONTROLLER: DashboardController
 * Local: app/Controllers/DashboardController.php
 * Descrição: Gerencia a página inicial (Dashboard) do sistema.
 */

// Inclui o FuncionarioModel para qualquer dado específico do usuário que seja necessário
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';

class DashboardController
{

    // Método principal para exibir o dashboard
    public function showDashboard()
    {
        // Garante que a sessão está iniciada (já é feito no Roteador, mas bom para garantir)
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Verifica se o usuário está logado (Embora o Roteador já faça isso, é uma camada de segurança)
        if (!($_SESSION['logged_in'] ?? false)) {
            // Se não estiver logado, redireciona para o login (embora o Roteador deva pegar isso antes)
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $user_nome = $_SESSION['user_nome'] ?? 'Visitante';
        $user_cargo = $_SESSION['user_cargo'] ?? 'Indefinido';

        // Dados que serão passados para a View
        $data = [
            'title' => 'Dashboard Principal',
            'welcome_message' => "Bem-vindo(a), {$user_nome} ({$user_cargo})!"
            // Futuramente: dados estatísticos, resumo de pedidos, status da frota.
        ];

        // 1. Inicia o buffer de saída. Tudo que for 'echo' a partir de agora será capturado.
        ob_start();

        // 2. Carrega a View específica (o conteúdo HTML puro)
        require_once ROOT_PATH . '/app/Views/dashboard.php';

        // 3. Obtém o conteúdo da View e limpa o buffer
        $content = ob_get_clean();

        // 4. Carrega o Layout Central, passando o conteúdo da View e o título
        // Esta é a magia da injeção de View no Layout Central!
        require_once ROOT_PATH . '/app/Views/layout.php';
    }
}

// Nota: Precisamos adicionar este Controller ao roteador index.php (próxima seção)
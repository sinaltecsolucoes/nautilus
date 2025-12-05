<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * CLASSE CONTROLLER: DashboardController
 * Local: app/Controllers/DashboardController.php
 * Descrição: Gerencia a página inicial (Dashboard) do sistema.
 */

/**
 * Exibe o dashboard principal
 */
class DashboardController extends BaseController
{
    // Método principal para exibir o dashboard
    public function showDashboard()
    {
        // Garante que a sessão está iniciada 
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Carrega config
        $config = require ROOT_PATH . '/config/config.php';

        // Verifica se o usuário está logado 
        if (!($_SESSION['logged_in'] ?? false)) {
            // Se não estiver logado, redireciona para o login
            header('Location: ' . $config['app']['base_url'] . '/login');
            exit;
        }

        $user_nome = $_SESSION['user_nome'] ?? 'Visitante';
        $user_cargo = $_SESSION['user_cargo'] ?? 'Indefinido';

        // Dados que serão passados para a View
        $data = [
            'title'           => 'Painel Principal',
            'welcome_message' => "Bem-vindo(a), {$user_nome} ({$user_cargo})!"
        ];

        // Renderiza a view dentro do layout
        $this->view('dashboard', $data);
    }
}

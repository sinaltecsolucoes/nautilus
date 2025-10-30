<?php

/**
 * VIEW: Dashboard Principal
 * Local: app/Views/dashboard.php
 * Descrição: Exibe a área principal do sistema com informações resumidas.
 * Esta View é injetada no layout.php.
 * * Variáveis disponíveis: $data['welcome_message']
 */
// Nota: Não colocamos aqui o <html>, <head>, <body>, etc., pois isso está no layout.php
?>

<div class="dashboard-content">

    <div class="welcome-banner">
        <h2><?php echo htmlspecialchars($data['welcome_message']); ?></h2>
        <p>Esta é a visão geral do **NAUTILUS ERP**. Utilize a navegação para acessar os módulos de Logística, Vendas e Cadastros.</p>
    </div>

    <div class="widgets-grid">
        <h3>📊 Resumo de Hoje</h3>
        <div class="widget">
            <h4>Pedidos para Entrega</h4>
            <p class="metric">0</p>
            <small>Pedidos confirmados para carregamento.</small>
        </div>

        <div class="widget">
            <h4>Frota em Trânsito</h4>
            <p class="metric">0/<?php // echo $data['total_frota']; 
                                ?></p>
            <small>Veículos alocados e em trânsito.</small>
        </div>

        <div class="widget">
            <h4>Manutenções Pendentes</h4>
            <p class="metric">0</p>
            <small>Serviços corretivos ou preventivos.</small>
        </div>
    </div>

    <div style="margin-top: 30px; padding: 15px; background-color: #fffbe6; border: 1px solid #ffe082; border-radius: 4px;">
        <p><strong>Atenção:</strong> Os módulos de Entidades, Vendas e Logística serão ativados nos próximos passos!</p>
    </div>
</div>
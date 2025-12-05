<?php
// Garante que $data existe
$data = $data ?? [];

// Mensagem de Boas-Vindas
$welcome = htmlspecialchars(
    $data['welcome_message'] ?? 'Bem-vindo(a), Admin Master (Administrador)!',
    ENT_QUOTES,
    'UTF-8'
);

// Dados dinâmicos (KPIs)
$pedidoEntrega  = $data['pedidos_entrega'] ?? 0;
$frotaTransito  = $data['frota_transito'] ?? 0;
$manutPendentes = $data['manutencoes'] ?? 0;
?>

<div class="row">
    <div class="col-xl-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-tachometer-alt me-1"></i>
                <?php echo $welcome; ?>
            </div>
            <div class="card-body">
                <p>
                    Esta é a visão geral do <strong>NAUTILUS ERP</strong>.
                    Utilize a navegação para acessar os módulos.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">

    <!-- Pedidos -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                Pedidos para Entrega <span class="float-end"><?php echo $pedidoEntrega; ?></span>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="<?php $config['app']['base_url']; ?>/pedidos">Ver Detalhes</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>

    <!-- Frota -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                Frota em Trânsito <span class="float-end"><?php echo $frotaTransito; ?></span>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="<?= $config['app']['base_url']; ?>/expedicao">Ver Frota</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>

    <!-- Manutenções -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                Manutenções Pendentes <span class="float-end"><?php echo $manutPendentes; ?></span>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="<?= $config['app']['base_url']; ?>/manutencao">Ver Pendências</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <strong>Atenção:</strong> Os módulos de Entidades, Vendas e Logística serão ativados nos próximos passos!
</div>
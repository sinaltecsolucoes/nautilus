<?php
$data = $data ?? [];
$welcome = $data['welcome_message'] ?? 'Bem-vindo(a), Admin Master (Administrador)!';
?>
<div class="row">
    <div class="col-xl-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-tachometer-alt me-1"></i>
                <?php echo htmlspecialchars($welcome); ?>
            </div>
            <div class="card-body">
                <p>Esta é a visão geral do <strong>NAUTILUS ERP</strong>. Utilize a navegação para acessar os módulos.</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">Pedidos para Entrega <span class="float-end">0</span></div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="#">Ver Detalhes</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">Frota em Trânsito <span class="float-end">0</span></div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="#">Ver Frota</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">Manutenções Pendentes <span class="float-end">0</span></div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="#">Ver Pendências</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <strong>Atenção:</strong> Os módulos de Entidades, Vendas e Logística serão ativados nos próximos passos!
</div>
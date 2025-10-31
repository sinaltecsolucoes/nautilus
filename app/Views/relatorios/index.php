<?php

/**
 * VIEW: Módulo de Relatórios
 * Local: app/Views/relatorios/index.php
 * Descrição: Centraliza as opções de geração de relatórios.
 * Variáveis: $data['title'], $data['csrf_token']
 */
$csrf_token = $data['csrf_token'] ?? '';
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($data['title']); ?></h1>

<div id="relatorio-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"
    style="display: none;">
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Relatórios Disponíveis</h6>
    </div>
    <div class="card-body">

        <div id="status-relatorio" class="alert d-none" role="alert"></div>

        <div class="d-flex justify-content-between align-items-center border-bottom py-3">
            <div>
                <h5 class="mb-1">Histórico de Manutenção de Frota</h5>
                <p class="text-muted mb-0">Lista detalhada de todos os serviços e peças registrados, com custos e fornecedores.</p>
            </div>
            <div>
                <button class="btn btn-success" id="btn-gerar-manutencao">
                    <i class="fas fa-file-pdf me-1"></i> Gerar PDF
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center py-3">
            <div>
                <h5 class="mb-1">Log de Auditoria Completo</h5>
                <p class="text-muted mb-0">Registro de todas as ações CREATE/UPDATE/DELETE no sistema.</p>
            </div>
            <div>
                <button class="btn btn-outline-secondary" disabled>
                    <i class="fas fa-search me-1"></i> Visualizar (Em Breve)
                </button>
            </div>
        </div>

    </div>
</div>
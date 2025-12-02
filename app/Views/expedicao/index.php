<h1 class="h3 mb-4 text-gray-800">
    Programação de Expedição - <span id="data-titulo"><?= date('d/m/Y') ?></span>
</h1>

<div id="expedicao-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($data['csrf_token']); ?>">
</div>

<!-- TOTAIS DO DIA (FIXO NO TOPO) -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total PL (milhões)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-pl">0,000</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-water fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Custo Total</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-custo">R$ 0,00</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center bg-primary text-white">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-list-ol me-2"></i> Programação de Entrega (Arraste para ordenar)</h6>
        <div>
            <button class="btn btn-light me-2" id="btn-mudar-data"><i class="fas fa-calendar-alt"></i> Mudar Data</button>
            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modal-linha">
                <i class="fas fa-plus-circle"></i> Nova Entrega
            </button>
            <button class="btn btn-danger" id="btn-imprimir"><i class="fas fa-file-pdf"></i> Romaneio</button>
        </div>
    </div>
    <div class="card-body p-3 bg-light">
        <div id="cards-container" class="row g-3">
        </div>

        <div id="empty-state" class="text-center text-muted py-5" style="display:none;">
            <i class="fas fa-truck-loading fa-4x mb-3 text-gray-300"></i>
            <h5>Nenhuma entrega programada para hoje</h5>
            <p>Clique em "Nova Entrega" para começar o dia.</p>
        </div>
    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="modal-linha" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalLabel">Programar Entrega</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-linha">
                    <input type="hidden" id="linha_id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Veículo *</label>
                            <select id="veiculo" class="form-select select2" style="width:100%" required></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Técnico *</label>
                            <select id="tecnico" class="form-select select2" style="width:100%" required></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Motorista</label>
                            <select id="motorista" class="form-select select2" style="width:100%"></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Horário</label>
                            <input type="time" id="horario" class="form-control">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold">OS</label>
                            <input type="text" id="os" class="form-control">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Cliente *</label>
                            <select id="cliente" class="form-select select2" style="width:100%" required></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Qtd PL (Milhões)</label>
                            <input type="number" step="0.001" id="qtd_pl" class="form-control" placeholder="0.000" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Volumes / Caixas</label>
                            <input type="text" id="caixas_usadas" class="form-control" placeholder="Ex: 06 Cxs Transfish">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Observação</label>
                            <textarea id="observacao" class="form-control" rows="1" placeholder="Detalhes da entrega..."></textarea>
                        </div>

                        <div class="col-12">
                            <hr class="my-2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Frete / Abast. (R$)</label>
                            <input type="text" class="form-control money" id="valor_abastecimento" placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Diária Motorista (R$)</label>
                            <input type="text" class="form-control money" id="valor_diaria_motorista" placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Diária Técnico (R$)</label>
                            <input type="text" class="form-control money" id="valor_diaria_tecnico" placeholder="0,00">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary px-4" id="btn-salvar-linha">
                    <i class="fas fa-save me-1"></i> Salvar Edição
                </button>
            </div>
        </div>
    </div>
</div>
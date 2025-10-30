<?php

/**
 * VIEW: Gestão de Entregas e Carregamentos (Logística)
 * Local: app/Views/expedicao/index.php
 * Descrição: Formulário de organização de carregamentos e listagem de expedições.
 * Variáveis: $data['title'], $data['csrf_token']
 */
$csrf_token = $data['csrf_token'] ?? '';
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($data['title']); ?></h1>

<div id="expedicao-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"
    style="display: none;">
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Carregamentos Organizados</h6>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-expedicao" id="btn-adicionar-expedicao">
            <i class="fas fa-truck-loading me-1"></i> Criar Nova Expedição
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-expedicoes" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nº Carga</th>
                        <th>Data</th>
                        <th>Veículo</th>
                        <th>Motorista Principal</th>
                        <th>Encarregado</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-expedicao" tabindex="-1" role="dialog" aria-labelledby="modal-expedicao-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-expedicao-label">Organizar Carregamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-expedicao" class="row g-3">
                    <input type="hidden" id="exp-id" name="exp_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div id="mensagem-expedicao" class="col-12 mb-3"></div>

                    <div class="col-md-3">
                        <label for="exp-data-carregamento" class="form-label">Data Carregamento</label>
                        <input type="date" class="form-control" id="exp-data-carregamento" name="exp_data_carregamento" required>
                    </div>
                    <div class="col-md-3">
                        <label for="exp-numero-carregamento" class="form-label">Nº Carga</label>
                        <input type="text" class="form-control bg-light" id="exp-numero-carregamento" name="exp_numero_carregamento" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="exp-previsao-id" class="form-label">Vincular Pedido/Previsão (Opcional)</label>
                        <select class="form-select" id="exp-previsao-id" name="exp_previsao_id">
                            <option value="">Nenhum pedido vinculado</option>
                        </select>
                    </div>

                    <hr class="mt-4">

                    <h6 class="mb-3">Alocação de Recursos</h6>

                    <div class="col-md-4">
                        <label for="exp-veiculo-id" class="form-label">Veículo Principal</label>
                        <select class="form-select" id="exp-veiculo-id" name="exp_veiculo_id" required>
                            <option value="">Selecione o Veículo...</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="exp-motorista-principal-id" class="form-label">Motorista Principal</label>
                        <select class="form-select" id="exp-motorista-principal-id" name="exp_motorista_principal_id" required>
                            <option value="">Selecione o Motorista...</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="exp-motorista-reserva-id" class="form-label">Motorista Reserva (Opcional)</label>
                        <select class="form-select" id="exp-motorista-reserva-id" name="exp_motorista_reserva_id">
                            <option value="">Nenhum</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="exp-encarregado-id" class="form-label">Encarregado da Separação</label>
                        <select class="form-select" id="exp-encarregado-id" name="exp_encarregado_id" required>
                            <option value="">Selecione o Encarregado...</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="exp-horario-expedicao" class="form-label">Horário Previsto</label>
                        <input type="time" class="form-control" id="exp-horario-expedicao" name="exp_horario_expedicao">
                    </div>
                    <div class="col-md-4">
                        <label for="exp-quantidade-caixas" class="form-label">Quant. Caixas Previstas</label>
                        <input type="number" min="0" class="form-control" id="exp-quantidade-caixas" name="exp_quantidade_caixas">
                    </div>

                    <div class="col-12">
                        <label for="exp-observacao" class="form-label">Observações Logísticas</label>
                        <textarea class="form-control" id="exp-observacao" name="exp_observacao" rows="2"></textarea>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-expedicao" class="btn btn-primary" id="btn-salvar-expedicao">
                    <i class="fas fa-save me-2"></i> Criar Expedição
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
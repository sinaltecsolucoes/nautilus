<?php

/**
 * VIEW: Cadastro de Abastecimentos
 * Local: app/Views/abastecimentos/index.php
 */
$csrf_token = $data['csrf_token'] ?? '';
?>

<h1 class="h3 mb-2 text-gray-800">Abastecimentos</h1>

<div id="abastecimento-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"
    style="display: none;">
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Histórico de Abastecimentos</h6>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-abastecimento">
            <i class="fas fa-plus"></i> Novo Lançamento
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-abastecimentos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Motorista</th>
                        <th>Posto (CNPJ)</th>
                        <th>Combustível</th>
                        <th>Cupom</th>
                        <th>Veículo</th>
                        <th>Quilômetro</th>
                        <th>Litros</th>
                        <th>Valor Total</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-abastecimento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Lançar Abastecimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-abastecimento">
                    <input type="hidden" name="id" id="abast_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Motorista</label>
                            <select name="funcionario_id" id="funcionario_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($data['motoristas'] as $motorista): ?>
                                    <option value="<?= $motorista['id'] ?>">
                                        <?= $motorista['nome_comum'] ?? $motorista['nome_completo'] ?? $motorista['nome'] ?? 'Sem Nome' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" id="data" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Hora</label>
                            <input type="time" name="hora" id="hora" class="form-control" required value="<?= date('H:i') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Posto de Combustível</label>
                            <select name="entidade_id" id="entidade_id" class="form-select" required style="width: 100%;">
                                <option></option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Veículo (Placa)</label>
                            <select name="veiculo_id" id="veiculo_id" class="form-select" required style="width: 100%;">
                                <option></option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">KM Atual</label>
                            <input type="number" name="quilometro_abast" id="quilometro_abast" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Combustível</label>
                            <select name="descricao_combustivel" id="descricao_combustivel" class="form-select">
                                <option value="DIESEL S10">DIESEL S10</option>
                                <option value="GASOLINA COMUM">GASOLINA COMUM</option>
                                <option value="GASOLINA ADITIVADA">GASOLINA ADITIVADA</option>
                                <option value="ARLA">ARLA</option>
                                <option value="ETANOL">ETANOL</option>
                                <option value="OUTROS">OUTROS</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nº Cupom Fiscal</label>
                            <input type="text" name="numero_cupom" id="numero_cupom" class="form-control">
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Preço Unit. (R$)</label>
                            <input type="text" name="valor_unitario" id="valor_unitario" class="form-control money" placeholder="0,00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Litros</label>
                            <input type="text" name="total_litros" id="total_litros" class="form-control" placeholder="0,000">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-success">Total (R$)</label>
                            <input type="text" name="valor_total" id="valor_total" class="form-control fw-bold money" readonly>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" id="btn-salvar-abastecimento" class="btn btn-primary">Salvar Lançamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
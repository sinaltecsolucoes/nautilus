<h1 class="h3 mb-2 text-gray-800">Abastecimentos</h1>

<div id="abastecimento-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($data['csrf_token']); ?>"
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
            <table class="table table-bordered" id="tabela-abastecimentos" width="100%" cellspacing="0">
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
                                    <option value="<?= $motorista['id'] ?>"><?= $motorista['nome_completo'] ?></option>
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">CNPJ Posto</label>
                            <input type="text" name="cnpj_posto" id="cnpj_posto" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Placa</label>
                            <input type="text" name="placa_veiculo" id="placa_veiculo" class="form-control text-uppercase" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">KM</label>
                            <input type="number" name="quilometro_abast" id="quilometro_abast" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Combustível</label>
                            <input type="text" name="descricao_combustivel" id="descricao_combustivel" class="form-control" value="DIESEL S10">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cupom</label>
                            <input type="text" name="numero_cupom" id="numero_cupom" class="form-control">
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Preço Unit. (R$)</label>
                            <input type="text" name="valor_unitario" id="valor_unitario" class="form-control money" oninput="calcTotal()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Litros</label>
                            <input type="text" name="total_litros" id="total_litros" class="form-control" oninput="calcTotal()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-success">Total (R$)</label>
                            <input type="text" name="valor_total" id="valor_total" class="form-control fw-bold" readonly>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function calcTotal() {
        let unit = $('#valor_unitario').val().replace('.', '').replace(',', '.').replace('R$ ', '');
        let lit = $('#total_litros').val().replace(',', '.');
        if (unit && lit) {
            let tot = parseFloat(unit) * parseFloat(lit);
            $('#valor_total').val(tot.toLocaleString('pt-BR', {
                minimumFractionDigits: 2
            }));
        }
    }
</script>
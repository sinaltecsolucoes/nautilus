<?php

/**
 * VIEW: Controle de Manutenção de Veículos
 * Local: app/Views/manutencao/index.php
 * Descrição: Formulário de registro de manutenção e listagem de histórico.
 * Variáveis: $data['title'], $data['csrf_token']
 */
$csrf_token = $data['csrf_token'] ?? '';
?>

<div id="manutencao-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"
    style="display: none;">
</div>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($data['title']); ?></h1>

<div class="row">

    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Registrar Novo Serviço/Peça</h6>
            </div>
            <div class="card-body">
                <form id="form-manutencao" class="row g-3">
                    <input type="hidden" id="man-id" name="man_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div id="mensagem-manutencao" class="col-12 mb-3"></div>

                    <div class="col-md-4">
                        <label for="man-veiculo-id" class="form-label">Veículo (Placa)</label>
                        <select class="form-select" id="man-veiculo-id" name="man_veiculo_id" required>
                            <option value="">Selecione o Veículo...</option>
                            <option value="1">ABC-1234 (Caminhão 1)</option>
                            <option value="2">XYZ-5678 (Carro 2)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="man-fornecedor-id" class="form-label">Fornecedor do Serviço/Peça</label>
                        <select class="form-select" id="man-fornecedor-id" name="man_fornecedor_id" required>
                            <option value="">Selecione o Fornecedor...</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="man-data-servico" class="form-label">Data do Serviço</label>
                        <input type="date" class="form-control" id="man-data-servico" name="man_data_servico" required>
                    </div>

                    <div class="col-md-4">
                        <label for="man-tipo-manutencao" class="form-label">Tipo de Manutenção</label>
                        <select class="form-select" id="man-tipo-manutencao" name="man_tipo_manutencao" required>
                            <option value="Preventiva">Preventiva</option>
                            <option value="Corretiva">Corretiva</option>
                            <option value="Implementacao">Implementação</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="man-servico-peca" class="form-label">Serviço/Peça (Descrição)</label>
                        <input type="text" class="form-control" id="man-servico-peca" name="man_servico_peca" required>
                    </div>

                    <div class="col-md-4">
                        <label for="man-valor" class="form-label">Valor (R$)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="man-valor" name="man_valor" required>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-success me-2" id="btn-salvar-manutencao">
                            <i class="fas fa-save me-1"></i> Salvar Registro
                        </button>
                        <button type="button" class="btn btn-secondary" id="btn-cancelar-manutencao">
                            <i class="fas fa-undo me-1"></i> Limpar Formulário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Histórico de Manutenções</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="tabela-manutencoes" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Veículo (Placa)</th>
                                <th>Tipo</th>
                                <th>Serviço/Peça</th>
                                <th>Fornecedor</th>
                                <th>Valor (R$)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php

/**
 * VIEW: Cadastro de Veículos
 * Local: app/Views/veiculos/index.php
 * Descrição: Formulário de registro e listagem da frota.
 * Variáveis: $data['title'], $data['csrf_token']
 */
$csrf_token = $data['csrf_token'] ?? '';
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($data['title']); ?></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Gerenciar Frota</h6>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modal-veiculo" id="btn-add-veiculo">
            <i class="fas fa-plus me-1"></i> Adicionar Veículo
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-veiculos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Placa</th>
                        <th>Marca/Modelo</th>
                        <th>Ano</th>
                        <th>Tipo Frota</th>
                        <th>Situação</th>
                        <th>Proprietário</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-veiculo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-veiculo-label">Adicionar Novo Veículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-veiculo" class="row g-3">
                    <input type="hidden" id="veiculo-id" name="veiculo_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div id="mensagem-veiculo" class="col-12"></div>

                    <div class="col-md-4">
                        <label for="veiculo-placa" class="form-label">Placa</label>
                        <input type="text" class="form-control" id="veiculo-placa" name="veiculo_placa" required maxlength="8">
                    </div>
                    <div class="col-md-4">
                        <label for="veiculo-marca" class="form-label">Marca</label>
                        <input type="text" class="form-control" id="veiculo-marca" name="veiculo_marca" required>
                    </div>
                    <div class="col-md-4">
                        <label for="veiculo-modelo" class="form-label">Modelo</label>
                        <input type="text" class="form-control" id="veiculo-modelo" name="veiculo_modelo" required>
                    </div>

                    <div class="col-md-4">
                        <label for="veiculo-tipo-frota" class="form-label">Tipo de Frota</label>
                        <select class="form-select" id="veiculo-tipo-frota" name="veiculo_tipo_frota" required>
                            <option value="Propria">Própria</option>
                            <option value="Terceiros">Terceiros</option>
                            <option value="Locada">Locada</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label for="veiculo-proprietario" class="form-label">Proprietário (Entidade)</label>
                        <select class="form-select" id="veiculo-proprietario" name="proprietario_entidade_id" required>
                            <option value="">Selecione ou Busque a Entidade</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="veiculo-ano" class="form-label">Ano</label>
                        <input type="number" class="form-control" id="veiculo-ano" name="veiculo_ano" required min="1900">
                    </div>
                    <div class="col-md-4">
                        <label for="veiculo-combustivel" class="form-label">Combustível</label>
                        <select class="form-select" id="veiculo-combustivel" name="veiculo_tipo_combustivel" required>
                            <option value="Diesel">Diesel</option>
                            <option value="Gasolina">Gasolina</option>
                            <option value="Etanol">Etanol</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="veiculo-autonomia" class="form-label">Autonomia (km/L)</label>
                        <input type="number" step="0.1" min="0" class="form-control" id="veiculo-autonomia" name="veiculo_autonomia">
                    </div>

                    <div class="col-12 mt-4">
                        <h6>Documentação e Custos</h6>
                        <hr class="mt-1">
                    </div>

                    <div class="col-md-6">
                        <label for="veiculo-renavam" class="form-label">RENAVAM</label>
                        <input type="text" class="form-control" id="veiculo-renavam" name="veiculo_renavam">
                    </div>
                    <div class="col-md-6">
                        <label for="veiculo-crv" class="form-label">CRV</label>
                        <input type="text" class="form-control" id="veiculo-crv" name="veiculo_crv">
                    </div>

                    <div class="col-md-4">
                        <label for="veiculo-situacao" class="form-label">Situação</label>
                        <select class="form-select" id="veiculo-situacao" name="veiculo_situacao" required>
                            <option value="Ativo">Ativo</option>
                            <option value="Inativo">Inativo</option>
                            <option value="Manutencao">Manutenção</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-veiculo" class="btn btn-primary" id="btn-salvar-veiculo">
                    <i class="fas fa-save me-1"></i> Salvar Veículo
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/veiculos.js"></script>
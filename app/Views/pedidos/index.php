<?php

/**
 * VIEW: Gestão de Pedidos/Previsões de Vendas
 * Local: app/Views/pedidos/index.php
 * Descrição: CRUD de PREVISOES_VENDAS, com todos os detalhes logísticos.
 * Variáveis: $data['title'], $data['csrf_token']
 */
$csrf_token = $data['csrf_token'] ?? '';
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($data['title']); ?></h1>

<div id="pedido-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"
    style="display: none;">
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Previsões de Pedidos Cadastradas</h6>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-pedido" id="btn-adicionar-pedido">
            <i class="fas fa-plus me-1"></i> Adicionar Novo Pedido
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-pedidos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>OS Nº</th>
                        <th>Data Saída</th>
                        <th>Cliente</th>
                        <th>Quant. Bônus</th>
                        <th>Valor Total</th>
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

<div class="modal fade" id="modal-pedido" tabindex="-1" role="dialog" aria-labelledby="modal-pedido-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-pedido-label">Adicionar Novo Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-pedido" class="row g-3">
                    <input type="hidden" id="pedido-id" name="ped_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" id="pedido-vendedor-id" name="ped_vendedor_id" value="<?php echo $_SESSION['user_id'] ?? 0; ?>">

                    <div id="mensagem-pedido" class="col-12 mb-3"></div>

                    <div class="col-md-3">
                        <label for="pedido-os-numero" class="form-label">Nº OS (Ordem de Serviço)</label>
                        <input type="text" class="form-control" id="pedido-os-numero" name="ped_os_numero" placeholder="Ex: 10882 ou LAB-A" required>
                    </div>
                    <div class="col-md-5">
                        <label for="pedido-cliente" class="form-label">Cliente</label>
                        <select class="form-select" id="pedido-cliente" name="ped_cliente_id" required>
                            <option value="">Buscar Cliente...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="pedido-data-saida" class="form-label">Data de Saída Prevista</label>
                        <input type="date" class="form-control" id="pedido-data-saida" name="ped_data_saida" required>
                    </div>

                    <hr class="mt-4">

                    <div class="col-md-3">
                        <label for="pedido-quantidade" class="form-label">Quantidade</label>
                        <input type="number" step="1" min="1" class="form-control" id="pedido-quantidade" name="ped_quantidade" required>
                    </div>
                    <div class="col-md-3">
                        <label for="pedido-bonus-perc" class="form-label">Bônus (%)</label>
                        <input type="number" step="0.1" min="0" max="100" class="form-control" id="pedido-bonus-perc" name="ped_percentual_bonus" value="0.0">
                    </div>
                    <div class="col-md-3">
                        <label for="pedido-valor-unitario" class="form-label">Valor Unitário</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="pedido-valor-unitario" name="ped_valor_unitario" required>
                    </div>
                    <div class="col-md-3">
                        <label for="pedido-valor-total" class="form-label">Valor Total (Calculado)</label>
                        <input type="text" class="form-control bg-light" id="pedido-valor-total" readonly>
                    </div>

                    <hr class="mt-4">

                    <div class="col-md-3">
                        <label for="pedido-salinidade" class="form-label">Salinidade</label>
                        <select class="form-select" id="pedido-salinidade" name="ped_salinidade" required>
                            <option value="43">43</option>
                            <option value="2">2</option>
                            <option value="Canal">Canal</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="pedido-divisao" class="form-label">Divisão</label>
                        <select class="form-select" id="pedido-divisao" name="ped_divisao" required>
                            <option value="02 viveiros">02 Viveiros</option>
                            <option value="04 divisoes">04 Divisões</option>
                            <option value="embaladas">Embaladas</option>
                            <option value="raceway">Raceway</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="pedido-forma-pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="pedido-forma-pagamento" name="ped_forma_pagamento" required>
                            <option value="A vista">À Vista</option>
                            <option value="Boleto">Boleto</option>
                            <option value="Prazo">Prazo</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="pedido-condicao" class="form-label">Condição</label>
                        <select class="form-select" id="pedido-condicao" name="ped_condicao" required>
                            <option value="Antecipacao">Antecipação</option>
                            <option value="Troca servico">Troca Serviço</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="pedido-status" class="form-label">Status (Vendas)</label>
                        <select class="form-select" id="pedido-status" name="ped_status" required>
                            <option value="Confirmado">Confirmado</option>
                            <option value="Cancelado">Cancelado</option>
                            <option value="Adiado">Adiado</option>
                            <option value="Suspenso">Suspenso</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="pedido-status-dia" class="form-label">Status Dia (Logística)</label>
                        <select class="form-select" id="pedido-status-dia" name="ped_status_dia" required>
                            <option value="Reservado no tanque">Reservado no Tanque</option>
                            <option value="entrega de hoje">Entrega de Hoje</option>
                            <option value="entrega de amanha">Entrega de Amanhã</option>
                            <option value="entregue">Entregue</option>
                        </select>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-pedido" class="btn btn-primary" id="btn-salvar-pedido">
                    <i class="fas fa-save me-2"></i> Salvar Pedido
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($data['title']); ?></h1>

<div id="manutencao-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($data['csrf_token']); ?>">
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Histórico de Serviços</h6>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modal-manutencao">
            <i class="fas fa-plus me-1"></i> Nova Manutenção
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-manutencao" width="100%">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Veículo</th>
                        <th>Fornecedor</th>
                        <th>Nº OS</th>
                        <th>Qtd. Serviços</th>
                        <th>Total (R$)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-manutencao" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalLabel">Registrar Manutenção</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-primary fw-bold mb-3"><i class="fas fa-info-circle me-1"></i> Dados Gerais</h6>
                        <form id="form-header" class="row g-3">
                            <input type="hidden" id="man_id" value="">
                            <div class="col-md-4">
                                <label class="form-label">Veículo (*)</label>
                                <select id="veiculo_id" class="form-select" style="width:100%"></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fornecedor (*)</label>
                                <select id="fornecedor_id" class="form-select" style="width:100%"></select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data (*)</label>
                                <input type="date" id="data_manutencao" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Nº OS / NF</label>
                                <input type="text" id="numero_os" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">KM Atual</label>
                                <input type="number" id="km_atual" class="form-control" placeholder="0">
                            </div>
                        </form>
                    </div>
                </div>



                <div class="card mb-3 border-start border-4 border-success">
                    <div class="card-body">
                        <h6 class="card-title text-success fw-bold"><i class="fas fa-tools me-1"></i> Adicionar Serviço/Peça</h6>


                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="small fw-bold">Tipo</label>
                                <select id="item_tipo" class="form-select form-select-sm">
                                    <option value="Preventiva">Preventiva</option>
                                    <option value="Corretiva">Corretiva</option>
                                    <option value="Preditiva">Preditiva</option>
                                    <option value="Implementacao">Implementação</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="small fw-bold">Descrição</label>
                                <input type="text" id="item_descricao" class="form-control form-control-sm" placeholder="Ex: Filtro de Óleo">
                            </div>

                            <div class="col-md-2">
                                <label class="small fw-bold">Peças (R$)</label>
                                <input type="text" id="item_valor_pecas" class="form-control form-control-sm money" placeholder="0,00">
                            </div>

                            <div class="col-md-2">
                                <label class="small fw-bold">Mão Obra (R$)</label>
                                <input type="text" id="item_valor_mo" class="form-control form-control-sm money" placeholder="0,00">
                            </div>

                            <div class="col-md-1">
                                <label class="small fw-bold" title="Diluir em meses">Rateio</label>
                                <input type="number" id="item_rateio" class="form-control form-control-sm" value="1" min="1" max="60">
                            </div>

                            <div class="col-md-2 d-flex gap-1">
                                <button type="button" id="btn-cancelar-item" class="btn btn-secondary btn-sm w-50" style="display: none;" title="Cancelar">
                                    Cancelar
                                </button>
                                <button type="button" id="btn-add-item" class="btn btn-success btn-sm w-100" title="Adicionar à lista">
                                    Adicionar
                                </button>
                            </div>
                        </div>


                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-striped bg-white align-middle" id="tabela-itens-temp">
                        <thead class="table-dark">
                            <tr>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th class="text-center">Rateio</th>
                                <th class="text-end">Peças</th>
                                <th class="text-end">Mão Obra</th>
                                <th class="text-end">Total Item</th>
                                <th class="text-center" style="width: 100px;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold">TOTAL BRUTO:</td>
                                <td class="text-end fw-bold text-primary" id="lbl-total-bruto">R$ 0,00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row justify-content-end mt-2 mb-3 border-top pt-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Desconto Geral (R$)</label>
                        <input type="text" id="valor_desconto" class="form-control money text-danger fw-bold text-end" placeholder="0,00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">TOTAL LÍQUIDO (R$)</label>
                        <input type="text" id="valor_liquido" class="form-control form-control-lg bg-dark text-warning fw-bold text-end" readonly value="R$ 0,00">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-salvar-tudo" class="btn btn-primary btn-lg">
                        <i class="fas fa-check-circle me-2"></i> Finalizar Lançamento
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-detalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i> Detalhes da Manutenção</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Veículo:</strong> <span id="det-veiculo">...</span><br>
                            <strong>Fornecedor:</strong> <span id="det-fornecedor">...</span>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Data:</strong> <span id="det-data">...</span><br>
                            <strong>Nº OS:</strong> <span id="det-os">...</span><br>
                            <strong>KM:</strong> <span id="det-km">...</span>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mt-4">Serviços Executados</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th class="text-end">Peças</th>
                                <th class="text-end">Mão de Obra</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="det-tbody"></tbody>
                        <tfoot class="table-group-divider">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">TOTAL GERAL:</td>
                                <td class="text-end fw-bold bg-warning text-dark" id="det-total">R$ 0,00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
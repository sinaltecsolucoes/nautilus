<?php

/**
 * VIEW: Lista de Entidades (Clientes, Fornecedores, Transportadoras)
 * Local: app/Views/entidades/lista_entidades.php
 * Descrição: Listagem e Modal de CRUD para entidades.
 * Variáveis: $data['pageType'], $data['csrf_token']
 */

$pageType = $data['pageType'];
$singular = ucfirst($pageType);

if (strtolower($pageType) === 'fornecedor') {
    $titulo = 'Fornecedores';
} else {
    // Regra geral (Clientes, Transportadoras)
    $titulo = $singular . 's';
}
?>

<script>
    // Usada para filtrar a DataTables e controlar a UI
    const ENTIDADE_TYPE = '<?php echo $pageType; ?>';
</script>

<h1 class="h3 mb-2 text-gray-800"><?php echo $titulo; ?></h1>

<div id="entidade-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($data['csrf_token']); ?>"
    style="display: none;">
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Registros de <?php echo $titulo; ?></h6>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-entidade">
            <i class="fas fa-plus"></i> Adicionar <?php echo $singular; ?>
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="tabela-entidades" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th class="text-center align-middle">Situação</th>
                        <th class="text-center align-middle">Tipo</th>
                        <th class="text-center align-middle">Cód. Interno</th>
                        <th class="text-center align-middle">Razão Social</th>
                        <th class="text-center align-middle">Nome Fantasia</th>
                        <th class="text-center align-middle">CPF/CNPJ</th>
                        <th class="text-center align-middle">Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-entidade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-entidade-label">Adicionar <?php echo $singular; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>


            <div class="modal-body">

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="dados-tab" data-bs-toggle="tab" href="#dados" role="tab" aria-controls="dados" aria-selected="true">Dados Principais</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" id="endereco-adicional-tab" data-bs-toggle="tab" href="#endereco-adicional-panel" role="tab" aria-controls="endereco-adicional-panel" aria-selected="false">Endereços Adicionais</a>
                    </li>
                </ul>

                <div class="tab-content" id="myTabContent">

                    <div class="tab-pane fade show active" id="dados" role="tabpanel" aria-labelledby="dados-tab">
                        <form id="form-entidade" class="mt-3">
                            <input type="hidden" id="entidade-id" name="ent_codigo">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">
                            <input type="hidden" name="tipo" id="ent_tipo" value="<?php echo ucfirst($pageType); ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tipo_pessoa" class="form-label">Tipo de Pessoa (*)</label>
                                    <select class="form-select" id="tipo_pessoa" name="tipo_pessoa" required>
                                        <option value="Juridica">Jurídica</option>
                                        <option value="Fisica">Física</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="situacao" class="form-label">Situação (*)</label>
                                    <select class="form-select" id="situacao" name="situacao" required>
                                        <option value="Ativo">Ativo</option>
                                        <option value="Inativo">Inativo</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="cnpj_cpf" class="form-label">CNPJ/CPF (*)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cnpj_cpf" name="cnpj_cpf" required>
                                        <button class="btn btn-outline-secondary" type="button" id="btn-buscar-cnpj" title="Buscar dados (Receita)">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <small id="api-debug-info" class="text-muted" style="font-size: 0.8em;"></small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="inscricao_estadual_rg" class="form-label">Inscrição Estadual/RG</label>
                                    <input type="text" class="form-control" id="inscricao_estadual_rg" name="inscricao_estadual_rg">
                                </div>

                                <div class="col-12 mb-3">
                                    <label for="razao_social" class="form-label">Razão Social / Nome Completo (*)</label>
                                    <input type="text" class="form-control" id="razao_social" name="razao_social" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="nome_fantasia" class="form-label">Nome Fantasia / Apelido</label>
                                    <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="codigo_interno" class="form-label">Código Interno</label>
                                    <input type="text" class="form-control" id="codigo_interno" name="codigo_interno">
                                </div>
                            </div>

                            <hr>
                            <h5 class="modal-title">Endereço Principal (Visualização)</h5>

                            <div class="row mt-3">
                                <div class="col-md-4 mb-3">
                                    <label for="end_cep" class="form-label">CEP</label>
                                    <input type="text" class="form-control bg-light" id="end_cep" name="end_cep" maxlength="9" readonly>
                                </div>

                                <div class="col-md-8 mb-3">
                                    <label for="end_logradouro" class="form-label">Logradouro</label>
                                    <input type="text" class="form-control bg-light" id="end_logradouro" name="end_logradouro" readonly>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="end_numero" class="form-label">Número</label>
                                    <input type="text" class="form-control bg-light" id="end_numero" name="end_numero" readonly>
                                </div>

                                <div class="col-md-8 mb-3">
                                    <label for="end_complemento" class="form-label">Complemento</label>
                                    <input type="text" class="form-control bg-light" id="end_complemento" name="end_complemento" readonly>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="end_bairro" class="form-label">Bairro</label>
                                    <input type="text" class="form-control bg-light" id="end_bairro" name="end_bairro" readonly>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="end_cidade" class="form-label">Cidade</label>
                                    <input type="text" class="form-control bg-light" id="end_cidade" name="end_cidade" readonly>
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label for="end_uf" class="form-label">UF</label>
                                    <input type="text" class="form-control bg-light" id="end_uf" name="end_uf" maxlength="2" readonly>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="endereco-adicional-panel" role="tabpanel" aria-labelledby="endereco-adicional-tab">
                        <div class="row mt-3">
                            <form id="form-endereco-adicional" class="row">
                                <input type="hidden" name="entidade_id" id="end_adic_entidade_id">
                                <input type="hidden" name="end_id" id="end_adic_id">

                                <div class="col-md-6 mb-3">
                                    <label for="end_adic_tipo" class="form-label">Tipo de Endereço (*)</label>
                                    <select class="form-select" id="end_adic_tipo" name="tipo_endereco" required>
                                        <option value="Principal">Principal</option>
                                        <option value="Comercial">Comercial</option>
                                        <option value="Entrega">Entrega</option>
                                        <option value="Correspondencia">Correspondência</option>
                                        <option value="Filial">Filial</option>
                                        <option value="Cobranca">Cobrança</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="end_adic_cep" class="form-label">CEP (*)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="end_adic_cep" name="cep" maxlength="9" required>
                                        <button class="btn btn-outline-secondary" type="button" id="btn-buscar-cep-adic">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-8 mb-3">
                                    <label for="end_adic_logradouro" class="form-label">Logradouro (*)</label>
                                    <input type="text" class="form-control" id="end_adic_logradouro" name="logradouro" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="end_adic_numero" class="form-label">Número (*)</label>
                                    <input type="text" class="form-control" id="end_adic_numero" name="numero" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="end_adic_complemento" class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="end_adic_complemento" name="complemento">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="end_adic_bairro" class="form-label">Bairro (*)</label>
                                    <input type="text" class="form-control" id="end_adic_bairro" name="bairro" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="end_adic_cidade" class="form-label">Cidade (*)</label>
                                    <input type="text" class="form-control" id="end_adic_cidade" name="cidade" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="end_adic_uf" class="form-label">UF (*)</label>
                                    <input type="text" class="form-control" id="end_adic_uf" name="uf" maxlength="2" required>
                                </div>

                                <div class="col-md-4 mb-3 text-end">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <button type="button" id="btn-cancelar-endereco" class="btn btn-secondary me-2" style="display: none;">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>

                                    <button type="submit" id="btn-salvar-endereco" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Adicionar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <hr>
                        <h5>Endereços Registrados</h5>
                        <div class="table-responsive">
                            <table class="table table-striped" id="tabela-enderecos-adicionais" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Logradouro, Número</th>
                                        <th>Bairro, Cidade/UF</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


            <div class="modal-footer">
                <button type="submit" form="form-entidade" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</div>
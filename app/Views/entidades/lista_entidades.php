<?php

/**
 * VIEW: Lista de Entidades (Clientes, Fornecedores, Transportadoras)
 * Local: app/Views/entidades/lista_entidades.php
 * Descrição: Listagem e Modal de CRUD para entidades.
 * Variáveis: $data['pageType'], $data['csrf_token']
 */

$pageType = $data['pageType'];
$titulo = ucfirst($pageType) . 's';
$singular = ucfirst($pageType);
?>

<h1 class="h3 mb-2 text-gray-800"><?php echo $titulo; ?></h1>

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
                        <th>Situação</th>
                        <th>Tipo</th>
                        <th>Razão Social</th>
                        <th>CPF/CNPJ</th>
                        <th>Ações</th>
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
                <form id="form-entidade">
                    <input type="hidden" id="entidade-id" name="ent_codigo">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($data['csrf_token']); ?>">

                    <p>Conteúdo do formulário de Entidades virá aqui (Dados Principais e Endereços Adicionais).</p>

                    <div class="form-group">
                        <label for="cnpj">CNPJ/CPF</label>
                        <input type="text" class="form-control" id="cnpj" name="cnpj_cpf">
                        <button type="button" id="btn-buscar-cnpj">Buscar CNPJ</button>
                    </div>

                    <div class="form-group">
                        <label for="cep">CEP</label>
                        <input type="text" class="form-control" id="cep" name="end_cep">
                        <button type="button" id="btn-buscar-cep">Buscar CEP</button>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="form-entidade" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</div>
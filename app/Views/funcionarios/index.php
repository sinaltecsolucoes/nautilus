<?php

/**
 * VIEW: Gestão de Funcionários (Usuários)
 * Local: app/Views/funcionarios/index.php
 * Descrição: CRUD e listagem de todos os funcionários e seus acessos.
 * Variáveis: $data['title'], $data['csrf_token'], $data['cargos']
 */
$csrf_token = $data['csrf_token'] ?? '';
$cargos = $data['cargos'] ?? [];
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($data['title']); ?></h1>

<div id="funcionario-data"
    data-base-url="<?php echo BASE_URL; ?>"
    data-csrf-token="<?php echo htmlspecialchars($csrf_token); ?>"
    data-logged-in-user-id="<?php echo $_SESSION['user_id'] ?? 0; ?>" style="display: none;">
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Funcionários Cadastrados</h6>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-funcionario" id="btn-adicionar-funcionario">
            <i class="fas fa-user-plus me-1"></i> Adicionar Funcionário
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tabela-funcionarios" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nome Completo</th>
                        <th>Apelido (Nome Comum)</th>
                        <th>Email (Login)</th>
                        <th>Cargo</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-funcionario" tabindex="-1" role="dialog" aria-labelledby="modal-funcionario-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-funcionario-label">Adicionar Funcionário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">


                <form id="form-funcionario" class="row g-3">
                    <input type="hidden" id="funcionario-id" name="funcionario_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <h6 class="text-secondary border-bottom pb-2"><i class="fas fa-user"></i> Dados Pessoais</h6>

                    <div class="col-md-12 mb-3">
                        <label for="funcionario-nome" class="form-label">Nome Completo (*)</label>
                        <input type="text" class="form-control" id="funcionario-nome" name="funcionario_nome" required>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label for="funcionario-apelido" class="form-label">Nome Comum (Apelido)</label>
                        <input type="text" class="form-control" id="funcionario-apelido" name="funcionario_apelido" placeholder="Como ele é conhecido">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="funcionario-cargo" class="form-label">Cargo (*)</label>
                        <select class="form-select" id="funcionario-cargo" name="funcionario_cargo" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($cargos as $cargo): ?>
                                <option value="<?php echo htmlspecialchars($cargo); ?>"><?php echo htmlspecialchars($cargo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="funcionario-situacao" class="form-label">Situação</label>
                        <select class="form-select" id="funcionario-situacao" name="funcionario_situacao" required>
                            <option value="Ativo">Ativo</option>
                            <option value="Inativo">Inativo</option>
                        </select>
                    </div>

                    <h6 class="text-secondary border-bottom pb-2 mt-4">
                        <i class="fas fa-lock"></i> Credenciais de Acesso
                    </h6>

                    <div class="col-12 mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="tem_acesso" name="tem_acesso">
                            <label class="form-check-label fw-bold" for="tem_acesso">Conceder acesso ao sistema?</label>
                        </div>
                    </div>

                    <div id="area-login" class="row g-3" style="display: none;">
                        <div class="col-md-12">
                            <label for="funcionario-email" class="form-label">Email (Login) (*)</label>
                            <input type="email" class="form-control" id="funcionario-email" name="funcionario_email">
                        </div>

                        <div class="col-md-6">
                            <label for="funcionario-senha" class="form-label">Senha (*)</label>
                            <input type="password" class="form-control" id="funcionario-senha" name="funcionario_senha">
                        </div>
                        <div class="col-md-6">
                            <label for="funcionario-confirmar-senha" class="form-label">Confirmar Senha</label>
                            <input type="password" class="form-control" id="funcionario-confirmar-senha" name="funcionario_confirmar_senha">
                        </div>
                        <div class="col-12">
                            <small class="text-muted" id="senha-help"></small>
                        </div>
                    </div>
                </form>



            </div>
            <div class="modal-footer">
                <button type="submit" form="form-funcionario" class="btn btn-primary" id="btn-salvar-funcionario">
                    <i class="fas fa-save me-2"></i> Salvar Funcionário
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
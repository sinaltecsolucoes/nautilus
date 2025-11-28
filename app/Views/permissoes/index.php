<h1 class="h3 mb-4 text-gray-800">Gestão de Permissões (ACL)</h1>

<div id="permissoes-save-url" data-url="<?php echo BASE_URL; ?>/permissoes/salvar"></div>

<div class="card shadow mb-4">
    <div class="card-header py-3 bg-primary text-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-user-shield"></i> Matriz de Acesso</h6>
        <button type="button" id="btn-salvar-permissoes" class="btn btn-light text-primary fw-bold">
            <i class="fas fa-save"></i> Gravar Alterações
        </button>
    </div>
    <div class="card-body">

        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle"></i> O cargo <strong>Administrador</strong> possui acesso total irrestrito e não é listado abaixo.
        </div>

        <?php
        // CORREÇÃO 1: Verifica se existem MÓDULOS definidos, e não se existe algo no banco
        if (!empty($data['modulos'])):
        ?>

            <?php
            // CORREÇÃO 2: Garante que a lista de cargos exista
            $cargosExibidos = array_filter($data['cargos'] ?? [], fn($c) => $c !== 'Administrador');

            // Fallback caso não venha cargos (evita tela branca)
            if (empty($cargosExibidos)) {
                echo '<div class="alert alert-warning">Nenhum cargo encontrado para configurar.</div>';
            }
            ?>

            <div class="table-responsive">
                <form id="form-matriz-permissoes">
                    <table class="table table-bordered table-hover text-center align-middle" width="100%" cellspacing="0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start" style="width: 25%;">Módulo / Ação</th>
                                <?php foreach ($cargosExibidos as $cargo): ?>
                                    <th><?php echo $cargo; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Loop pelos Módulos (Vem do Controller->index->$modulos)
                            foreach ($data['modulos'] as $moduloNome => $acoesPossiveis):
                            ?>
                                <tr class="table-secondary text-start">
                                    <td colspan="<?php echo count($cargosExibidos) + 1; ?>" class="fw-bold text-uppercase py-2">
                                        <i class="fas fa-cube me-2"></i> <?php echo $moduloNome; ?>
                                    </td>
                                </tr>

                                <?php foreach ($acoesPossiveis as $acao): ?>
                                    <tr>
                                        <td class="text-start ps-4">
                                            <?php
                                            $icon = match ($acao) {
                                                'Criar' => 'fa-plus text-success',
                                                'Ler' => 'fa-eye text-primary',
                                                'Alterar' => 'fa-edit text-warning',
                                                'Deletar' => 'fa-trash text-danger',
                                                default => 'fa-circle'
                                            };
                                            ?>
                                            <i class="fas <?php echo $icon; ?> me-2"></i> <?php echo $acao; ?>
                                        </td>

                                        <?php foreach ($cargosExibidos as $cargo):
                                            // CORREÇÃO 3: Usa a variável correta 'permissoesAtuais' vinda do controller
                                            // Se não existir registro no banco, assume FALSE (desmarcado)
                                            $permitido = $data['permissoesAtuais'][$cargo][$moduloNome][$acao] ?? false;
                                        ?>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input permission-checkbox"
                                                        type="checkbox"
                                                        style="cursor: pointer; transform: scale(1.3);"
                                                        data-cargo="<?php echo $cargo; ?>"
                                                        data-modulo="<?php echo $moduloNome; ?>"
                                                        data-acao="<?php echo $acao; ?>"
                                                        <?php echo $permitido ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>

        <?php else: ?>
            <div class="alert alert-danger">Erro: Lista de módulos não definida no Controller.</div>
        <?php endif; ?>

    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/permissoes.js"></script>
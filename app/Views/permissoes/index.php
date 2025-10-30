<?php

/**
 * VIEW: Gestão de Permissões (ACL/RBAC)
 * Local: app/Views/permissoes/index.php
 * Descrição: Matriz interativa para o Administrador ativar/inativar permissões.
 * * Variáveis disponíveis:
 * $data['permissoes'] - Matriz de permissões [modulo][cargo][acao]
 * $data['cargos'] - Lista de cargos (para as colunas)
 * $data['acoes'] - Lista de ações (para a exibição na tabela)
 */
$permissoes = $data['permissoes'] ?? [];
$cargos = $data['cargos'] ?? [];
$acoes_ordem = $data['acoes'] ?? ['Criar', 'Ler', 'Alterar', 'Deletar'];

// Remove o Administrador da lista de cargos exibidos, pois ele tem acesso TRUE por padrão
$cargos_exibicao = array_filter($cargos, fn($cargo) => $cargo !== 'Administrador');

// Definição da URL para a chamada AJAX de atualização
$update_url = BASE_URL . '/permissoes-update';
?>

<div class="permissoes-container">
    <div id="permissoes-update-url" data-url="<?php echo $update_url; ?>" style="display: none;"></div>
    <div id="status-message" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 5px;"></div>

    <p class="description-text">Utilize a matriz abaixo para conceder ou revogar permissões aos diferentes cargos do sistema. Lembre-se que o cargo **Administrador** possui acesso total por padrão e não pode ser alterado.</p>

    <table class="permissoes-table">
        <thead>
            <tr>
                <th rowspan="2">Módulo / Ação</th>
                <?php foreach ($cargos_exibicao as $cargo): ?>
                    <th colspan="<?php echo count($acoes_ordem); ?>" class="cargo-header"><?php echo htmlspecialchars($cargo); ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($cargos_exibicao as $cargo): ?>
                    <?php foreach ($acoes_ordem as $acao): ?>
                        <th class="action-header"><?php echo substr($acao, 0, 1); ?></th> <?php endforeach; ?>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permissoes as $modulo => $regras_modulo): ?>
                <tr class="module-row">
                    <td class="module-name-cell"><?php echo htmlspecialchars($modulo); ?></td>

                    <?php foreach ($cargos_exibicao as $cargo): ?>
                        <?php foreach ($acoes_ordem as $acao):
                            // Verifica se a regra existe para este Cargo/Módulo/Ação
                            $regra = $regras_modulo[$cargo][$acao] ?? null;
                            $regra_id = $regra['id'] ?? null;
                            $permitido = $regra['permitido'] ?? false;
                        ?>
                            <td class="switch-cell">
                                <?php if ($regra_id): ?>
                                    <label class="switch">
                                        <input type="checkbox"
                                            class="permission-switch"
                                            data-id="<?php echo $regra_id; ?>"
                                            data-modulo="<?php echo htmlspecialchars($modulo); ?>"
                                            data-cargo="<?php echo htmlspecialchars($cargo); ?>"
                                            data-acao="<?php echo htmlspecialchars($acao); ?>"
                                            <?php echo $permitido ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                <?php else: ?>
                                    <span title="Regra não definida. Execute o script de setup.">--</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
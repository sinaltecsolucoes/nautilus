<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <i class="fas fa-user-shield me-1"></i> Auditoria do Sistema
    </div>
    <div class="card-body">
        <table id="auditTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Tabela</th>
                    <th>Registro</th>
                    <th>IP</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['logs'] as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['id']); ?></td>
                        <td><?= htmlspecialchars($log['usuario_nome'] ?? 'Sistema'); ?></td>
                        <td><?= htmlspecialchars($log['acao']); ?></td>
                        <td><?= htmlspecialchars($log['tabela_afetada']); ?></td>
                        <td><?= htmlspecialchars($log['registro_id']); ?></td>
                        <td><?= htmlspecialchars($log['ip_endereco']); ?></td>
                        <td><?= htmlspecialchars($log['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
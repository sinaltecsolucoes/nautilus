<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AuditModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAllLogs(int $limit = 100): array
    {
        $sql = "SELECT a.id, a.usuario_id, u.nome_completo AS usuario_nome,
                       a.tabela_afetada, a.registro_id, a.acao,
                       a.dados_antigos, a.dados_novos, a.ip_endereco, a.created_at
                FROM auditoria a
                LEFT JOIN funcionarios u ON u.id = a.usuario_id
                ORDER BY a.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
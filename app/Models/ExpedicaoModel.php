<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use Exception;
use PDOException;

// Mantemos o helper de funções se ele for global (procedural)
if (file_exists(ROOT_PATH . '/app/Helpers/functions.php')) {
    require_once ROOT_PATH . '/app/Helpers/functions.php';
}

class ExpedicaoModel
{
    private PDO $pdo;
    private AuditLoggerService $logger;
    private string $table = 'expedicao_programacao';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    public function listar(array $params): array
    {
        $data = $params['data'] ?? date('Y-m-d');
        $draw = (int)($params['draw'] ?? 1);
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 100);
        $search = $params['search']['value'] ?? '';

        $sqlBase = "FROM {$this->table} e
                    LEFT JOIN veiculos v ON e.veiculo = v.id
                    LEFT JOIN funcionarios t ON e.tecnico = t.id
                    LEFT JOIN funcionarios m ON e.motorista = m.id
                    LEFT JOIN entidades c ON e.cliente = c.id
                    WHERE e.data_expedicao = :data";

        $bind = [':data' => $data];

        if ($search) {
            $sqlBase .= " AND (c.nome_fantasia LIKE :s OR v.placa LIKE :s OR e.os LIKE :s)";
            $bind[':s'] = "%{$search}%";
        }

        $stmtT = $this->pdo->prepare("SELECT COUNT(e.id) $sqlBase");
        $stmtT->execute($bind);
        $totalRecords = $stmtT->fetchColumn();

        $sql = "SELECT 
                    e.*,
                    v.placa AS veiculo_nome,
                    v.codigo_veiculo,
                    t.nome_comum AS tecnico_nome,
                    m.nome_comum AS motorista_nome,
                    c.nome_fantasia AS cliente_nome
                $sqlBase
                ORDER BY ISNULL(e.ordem), e.ordem ASC, e.horario ASC, e.id ASC
                LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            "draw" => $draw,
            "recordsTotal" => (int)$totalRecords,
            "recordsFiltered" => (int)$totalRecords,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public function create(array $linha, int $userId)
    {
        $cliente = !empty($linha['cliente']) && is_numeric($linha['cliente']) ? (int)$linha['cliente'] : null;
        if (!$cliente) throw new Exception('Cliente é obrigatório!');

        $obs = !empty($linha['observacao']) ? mb_strtoupper($linha['observacao'], 'UTF-8') : null;
        $caixas = !empty($linha['caixas_usadas']) ? mb_strtoupper($linha['caixas_usadas'], 'UTF-8') : null;

        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO {$this->table} 
                (data_expedicao, ordem, veiculo, tecnico, os, cliente, qtd_pl, horario, observacao, 
                 motorista, caixas_usadas, valor_abastecimento, valor_diaria_motorista, valor_diaria_tecnico, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $linha['data_expedicao'],
                $linha['ordem'] ?? null,
                $linha['veiculo'] ?? null,
                $linha['tecnico'] ?? null,
                $linha['os'] ?? null,
                $cliente,
                $linha['qtd_pl'] ?? null,
                $linha['horario'] ?? null,
                $obs,
                $linha['motorista'] ?? null,
                $caixas,
                $linha['valor_abastecimento'] ?? 0,
                $linha['valor_diaria_motorista'] ?? 0,
                $linha['valor_diaria_tecnico'] ?? 0,
                $userId
            ]);

            $this->pdo->commit();
            return "Entrega adicionada com sucesso!";
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $linha)
    {
        $cliente = !empty($linha['cliente']) && is_numeric($linha['cliente']) ? (int)$linha['cliente'] : null;
        if (!$cliente) throw new Exception('Cliente é obrigatório!');

        $obs = !empty($linha['observacao']) ? mb_strtoupper(trim($linha['observacao']), 'UTF-8') : null;
        $caixas = !empty($linha['caixas_usadas']) ? mb_strtoupper(trim($linha['caixas_usadas']), 'UTF-8') : null;

        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE {$this->table} SET 
                    data_expedicao=?, ordem=?, veiculo=?, tecnico=?, os=?, cliente=?, 
                    qtd_pl=?, horario=?, observacao=?, motorista=?, caixas_usadas=?, 
                    valor_abastecimento=?, valor_diaria_motorista=?, valor_diaria_tecnico=?, 
                    atualizado_em=NOW() 
                    WHERE id=?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $linha['data_expedicao'],
                $linha['ordem'] ?? null,
                $linha['veiculo'] ?? null,
                $linha['tecnico'] ?? null,
                $linha['os'] ?? null,
                $cliente,
                $linha['qtd_pl'] ?? null,
                $linha['horario'] ?? null,
                $obs,
                $linha['motorista'] ?? null,
                $caixas,
                $linha['valor_abastecimento'] ?? 0,
                $linha['valor_diaria_motorista'] ?? 0,
                $linha['valor_diaria_tecnico'] ?? 0,
                $id
            ]);

            $this->pdo->commit();
            return "Entrega atualizada com sucesso!";
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deletar(array $data, int $userId): string
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) return "ID inválido";

        $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$id]);
        return "Linha excluída!";
    }

    public function atualizarOrdem(int $id, int $novaOrdem): bool
    {
        try {
            $sql = "UPDATE {$this->table} SET ordem = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$novaOrdem, $id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function get(int $id)
    {
        $sql = "SELECT e.*, v.placa AS veiculo_nome, t.nome_comum AS tecnico_nome,
                       m.nome_comum AS motorista_nome, c.nome_fantasia AS cliente_nome
                FROM {$this->table} e
                LEFT JOIN veiculos v ON e.veiculo = v.id
                LEFT JOIN funcionarios t ON e.tecnico = t.id
                LEFT JOIN funcionarios m ON e.motorista = m.id
                LEFT JOIN entidades c ON e.cliente = c.id
                WHERE e.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDadosRelatorio(string $data): array
    {
        $sql = "SELECT 
                    e.*,
                    DATE_FORMAT(e.horario, '%H:%i') as hora_formatada,
                    CONCAT(IF(v.codigo_veiculo IS NOT NULL AND v.codigo_veiculo != '', CONCAT(v.codigo_veiculo, ' - '), ''), v.placa) AS veiculo_nome,
                    m.nome_comum AS motorista_nome,
                    t.nome_comum AS tecnico_nome,
                    c.nome_fantasia AS cliente_nome,
                    CONCAT(COALESCE(end.cidade, ''), '/', COALESCE(end.uf, '')) as cidade_uf
                FROM {$this->table} e
                LEFT JOIN veiculos v ON e.veiculo = v.id
                LEFT JOIN funcionarios t ON e.tecnico = t.id
                LEFT JOIN funcionarios m ON e.motorista = m.id
                LEFT JOIN entidades c ON e.cliente = c.id
                LEFT JOIN enderecos end ON (c.id = end.entidade_id AND end.tipo_endereco = 'Principal')
                WHERE e.data_expedicao = :data
                ORDER BY e.horario ASC, e.ordem ASC, e.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':data' => $data]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

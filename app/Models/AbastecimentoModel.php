<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use PDOException;

class AbastecimentoModel
{
    private PDO $pdo;
    private AuditLoggerService $logger;
    private string $table = 'abastecimentos_v2';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Cria um novo abastecimento.
     * @param array $data Dados do formulário.
     * @return int|false ID criado ou false.
     */
    public function create(array $data)
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO {$this->table} 
                (funcionario_id, entidade_id, veiculo_id, data_abastecimento, numero_cupom, 
                 descricao_combustivel, valor_unitario, total_litros, valor_total, quilometro_abast)
                VALUES (:func_id, :posto_id, :veiculo_id, :data, :cupom, :combustivel, :vl_unit, :litros, :total, :km)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':func_id'     => $data['funcionario_id'],
                ':posto_id'    => $data['entidade_id'],
                ':veiculo_id'  => $data['veiculo_id'],
                ':data'        => $data['data_abastecimento'],
                ':cupom'       => $data['numero_cupom'],
                ':combustivel' => $data['descricao_combustivel'],
                ':vl_unit'     => $data['valor_unitario'],
                ':litros'      => $data['total_litros'],
                ':total'       => $data['valor_total'],
                ':km'          => $data['quilometro_abast']
            ]);

            $id = (int)$this->pdo->lastInsertId();

            // Log simplificado
            $this->logger->log('CREATE', $this->table, $id, null, ['cupom' => $data['numero_cupom']]);

            $this->pdo->commit();
            return $id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Atualiza um abastecimento.
     */
    public function update(int $id, array $data): bool
    {
        $this->pdo->beginTransaction();
        try {
            $antigo = $this->find($id);

            $sql = "UPDATE {$this->table} SET 
                funcionario_id = :func_id, entidade_id = :posto_id, veiculo_id = :veiculo_id, 
                data_abastecimento = :data, numero_cupom = :cupom, descricao_combustivel = :combustivel, 
                valor_unitario = :vl_unit, total_litros = :litros, valor_total = :total, 
                quilometro_abast = :km
                WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':func_id'     => $data['funcionario_id'],
                ':posto_id'    => $data['entidade_id'],
                ':veiculo_id'  => $data['veiculo_id'],
                ':data'        => $data['data_abastecimento'],
                ':cupom'       => $data['numero_cupom'],
                ':combustivel' => $data['descricao_combustivel'],
                ':vl_unit'     => $data['valor_unitario'],
                ':litros'      => $data['total_litros'],
                ':total'       => $data['valor_total'],
                ':km'          => $data['quilometro_abast'],
                ':id'          => $id
            ]);

            $this->logger->log('UPDATE', $this->table, $id, $antigo, $data);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function find(int $id)
    {
        $sql = "SELECT 
                    a.*,
                    e.nome_fantasia as nome_posto,
                    v.placa as placa_veiculo
                FROM {$this->table} a
                LEFT JOIN entidades e ON a.entidade_id = e.id
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                WHERE a.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $antigo = $this->find($id);
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $success = $stmt->execute([':id' => $id]);

            if ($success) {
                $this->logger->log('DELETE', $this->table, $id, $antigo, null);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getForDataTable(array $params): array
    {
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';

        $columnsMap = [
            0 => 'a.data_abastecimento',
            1 => 'f.nome_comum',
            2 => 'e.nome_fantasia',
            3 => 'a.descricao_combustivel',
            4 => 'a.numero_cupom',
            5 => 'v.placa',
            6 => 'a.quilometro_abast',
            7 => 'a.total_litros',
            8 => 'a.valor_total',
            9 => 'a.id'
        ];

        $where = "WHERE 1=1 ";
        $bindParams = [];

        if (!empty($search)) {
            $where .= "AND (v.placa LIKE :s OR f.nome_comum LIKE :s OR e.nome_fantasia LIKE :s OR a.numero_cupom LIKE :s) ";
            $bindParams[':s'] = "%{$search}%";
        }

        $orderBy = "ORDER BY a.data_abastecimento DESC";
        if (!empty($params['order'])) {
            $colIndex = (int)$params['order'][0]['column'];
            $colDir = strtoupper($params['order'][0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
            if (isset($columnsMap[$colIndex])) {
                $orderBy = "ORDER BY {$columnsMap[$colIndex]} {$colDir}";
            }
        }

        $sqlBase = "FROM {$this->table} a 
                    LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                    LEFT JOIN entidades e ON a.entidade_id = e.id
                    LEFT JOIN veiculos v ON a.veiculo_id = v.id";

        $totalRecords = $this->pdo->query("SELECT COUNT(a.id) FROM {$this->table} a")->fetchColumn();

        $stmtF = $this->pdo->prepare("SELECT COUNT(a.id) {$sqlBase} {$where}");
        $stmtF->execute($bindParams);
        $totalFiltered = $stmtF->fetchColumn();

        $sqlData = "SELECT 
                        a.*, 
                        f.nome_comum as motorista_apelido,
                        e.nome_fantasia as nome_posto, e.cnpj_cpf as cnpj_posto,
                        v.placa as placa_veiculo
                    {$sqlBase} {$where} {$orderBy} LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        foreach ($bindParams as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total' => (int)$totalRecords,
            'totalFiltered' => (int)$totalFiltered,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
}

<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use PDOException;

class VeiculoModel
{
    protected PDO $pdo;
    protected AuditLoggerService $logger;
    protected string $table = 'veiculos';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $searchValue = $params['search']['value'] ?? '';

        $columnsMap = [
            0 => 'v.codigo_veiculo',
            1 => 'v.placa',
            2 => 'v.modelo',
            3 => 'v.ano',
            4 => 'v.tipo_frota',
            5 => 'v.situacao',
            6 => 'proprietario_nome',
            7 => 'v.id'
        ];

        $where = "WHERE 1=1 ";
        $bindParams = [];

        if (!empty($searchValue)) {
            $where .= "AND (v.codigo_veiculo LIKE :s OR v.placa LIKE :s OR v.modelo LIKE :s OR v.marca LIKE :s OR e.nome_fantasia LIKE :s) ";
            $bindParams[':s'] = "%{$searchValue}%";
        }

        $orderBy = "ORDER BY v.placa ASC";
        if (!empty($params['order'])) {
            $colIndex = (int)$params['order'][0]['column'];
            $colDir = strtoupper($params['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
            if (isset($columnsMap[$colIndex])) {
                $orderBy = "ORDER BY {$columnsMap[$colIndex]} {$colDir}";
            }
        }

        $sqlBase = "FROM {$this->table} v LEFT JOIN entidades e ON v.proprietario_entidade_id = e.id";

        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(v.id) {$sqlBase} {$where}");
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT v.*, COALESCE(e.nome_fantasia, e.razao_social, '---') AS proprietario_nome
                    {$sqlBase} {$where} {$orderBy} LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        foreach ($bindParams as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            "draw" => (int)$draw,
            "recordsTotal" => (int)$totalRecords,
            "recordsFiltered" => (int)$totalFiltered,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public function create(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO {$this->table} (
                codigo_veiculo, placa, modelo, marca, ano, tipo_frota, chassi, 
                especie_tipo, tipo_combustivel, autonomia, renavam, crv, 
                valor_licenciamento, valor_ipva, valor_depreciacao, 
                proprietario_entidade_id, situacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['veiculo_codigo'] ?? null,
                $data['veiculo_placa'],
                $data['veiculo_modelo'],
                $data['veiculo_marca'],
                $data['veiculo_ano'],
                $data['veiculo_tipo_frota'],
                $data['veiculo_chassi'],
                $data['veiculo_especie_tipo'] ?? null,
                $data['veiculo_tipo_combustivel'] ?? 'Diesel',
                $data['veiculo_autonomia'] ?? 0.0,
                $data['veiculo_renavam'] ?? null,
                $data['veiculo_crv'] ?? null,
                $data['veiculo_licenciamento'] ?? 0.0,
                $data['veiculo_ipva'] ?? 0.0,
                $data['veiculo_depreciacao'] ?? 0.0,
                !empty($data['proprietario_entidade_id']) ? $data['proprietario_entidade_id'] : null,
                $data['veiculo_situacao'] ?? 'Ativo'
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $this->logger->log('CREATE', $this->table, $id, null, ['placa' => $data['veiculo_placa']], $userId);

            $this->pdo->commit();
            return $id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Adicionar métodos update, find e delete seguindo o mesmo padrão...
}

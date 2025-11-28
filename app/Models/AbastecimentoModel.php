<?php

/**
 * CLASSE MODELO: AbastecimentoModel
 * Local: app/Models/AbastecimentoModel.php
 */

require_once __DIR__ . '/Database.php';
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';

class AbastecimentoModel
{
    private $pdo;
    private $table = 'abastecimentos';
    private $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    // Salvar (Criar)
    public function create($data)
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO {$this->table} 
                (funcionario_id, data_abastecimento, cnpj_posto, nome_posto, numero_cupom, descricao_combustivel, placa_veiculo, valor_unitario, total_litros, valor_total, quilometro_abast)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['funcionario_id'],
                $data['data_abastecimento'],
                $data['cnpj_posto'],
                $data['nome_posto'] ?? null,
                $data['numero_cupom'],
                $data['descricao_combustivel'],
                $data['placa_veiculo'],
                $data['valor_unitario'],
                $data['total_litros'],
                $data['valor_total'],
                $data['quilometro_abast']
            ]);

            $id = $this->pdo->lastInsertId();

            // Auditoria
            $this->logger->log('CREATE', $this->table, $id, null, $data);

            $this->pdo->commit();
            return $id;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // Atualizar
    public function update($id, $data)
    {
        $this->pdo->beginTransaction();
        try {
            $antigo = $this->find($id);

            $sql = "UPDATE {$this->table} SET 
                funcionario_id = ?, data_abastecimento = ?, cnpj_posto = ?, nome_posto = ?, 
                numero_cupom = ?, descricao_combustivel = ?, placa_veiculo = ?, 
                valor_unitario = ?, total_litros = ?, valor_total = ?, quilometro_abast = ?
                WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['funcionario_id'],
                $data['data_abastecimento'],
                $data['cnpj_posto'],
                $data['nome_posto'] ?? null,
                $data['numero_cupom'],
                $data['descricao_combustivel'],
                $data['placa_veiculo'],
                $data['valor_unitario'],
                $data['total_litros'],
                $data['valor_total'],
                $data['quilometro_abast'],
                $id
            ]);

            $this->logger->log('UPDATE', $this->table, $id, $antigo, $data);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Buscar por ID
    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Deletar
    public function delete($id)
    {
        $this->pdo->beginTransaction();
        try {
            $antigo = $this->find($id);
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                $this->logger->log('DELETE', $this->table, $id, $antigo, null);
            }

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // DataTables (Server-Side)
    public function getForDataTable(array $params): array
    {
        $start = $params['start'];
        $length = $params['length'];
        $searchValue = $params['search'];

        $where = "WHERE 1=1 ";
        $bindParams = [];

        // Pesquisa
        if (!empty($searchValue)) {
            $where .= "AND (a.placa_veiculo LIKE :search OR f.nome_completo LIKE :search OR a.cnpj_posto LIKE :search OR a.descricao_combustivel LIKE :search) ";
            $bindParams[':search'] = "%" . $searchValue . "%";
        }

        // Total
        $sqlTotal = "SELECT COUNT(a.id) FROM {$this->table} a JOIN funcionarios f ON a.funcionario_id = f.id";
        $stmtTotal = $this->pdo->query($sqlTotal);
        $totalRecords = $stmtTotal->fetchColumn();

        // Total Filtrado
        $sqlFiltered = "SELECT COUNT(a.id) FROM {$this->table} a JOIN funcionarios f ON a.funcionario_id = f.id " . $where;
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        foreach ($bindParams as $k => $v) $stmtFiltered->bindValue($k, $v);
        $stmtFiltered->execute();
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Dados
        $sqlData = "SELECT a.*, f.nome_completo as motorista 
                    FROM {$this->table} a 
                    JOIN funcionarios f ON a.funcionario_id = f.id
                    {$where} 
                    ORDER BY a.data_abastecimento 
                    LIMIT :start, :length";

        $stmtData = $this->pdo->prepare($sqlData);
        foreach ($bindParams as $k => $v) $stmtData->bindValue($k, $v);
        $stmtData->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmtData->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmtData->execute();

        return [
            'total' => $totalRecords,
            'totalFiltered' => $totalFiltered,
            'data' => $stmtData->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
}

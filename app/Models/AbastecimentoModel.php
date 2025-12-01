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
    private $table = 'abastecimentos_v2';
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
            // Agora salvamos entidade_id (posto) e veiculo_id em vez de texto solto
            $sql = "INSERT INTO {$this->table} 
                (funcionario_id, entidade_id, veiculo_id, data_abastecimento, numero_cupom, 
                 descricao_combustivel, valor_unitario, total_litros, valor_total, quilometro_abast)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['funcionario_id'],
                $data['entidade_id'], // ID do Posto (Entidade)
                $data['veiculo_id'],  // ID do Veículo
                $data['data_abastecimento'],
                $data['numero_cupom'],
                $data['descricao_combustivel'],
                $data['valor_unitario'],
                $data['total_litros'],
                $data['valor_total'],
                $data['quilometro_abast']
            ]);

            $id = $this->pdo->lastInsertId();
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
                funcionario_id = ?, entidade_id = ?, veiculo_id = ?, data_abastecimento = ?, 
                numero_cupom = ?, descricao_combustivel = ?, valor_unitario = ?, 
                total_litros = ?, valor_total = ?, quilometro_abast = ?
                WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['funcionario_id'],
                $data['entidade_id'],
                $data['veiculo_id'],
                $data['data_abastecimento'],
                $data['numero_cupom'],
                $data['descricao_combustivel'],
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

    public function find($id)
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
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? ''; 

        // Mapeamento correto para ordenação
        $columnsMap = [
            0 => 'a.data_abastecimento',
            1 => 'f.nome_comum',     // Motorista (Apelido)
            2 => 'e.nome_fantasia',  // Posto
            3 => 'a.descricao_combustivel',
            4 => 'a.numero_cupom',
            5 => 'v.placa',          // Veículo
            6 => 'a.quilometro_abast',
            7 => 'a.total_litros',
            8 => 'a.valor_total',
            9 => 'a.id'
        ];

        $where = "WHERE 1=1 ";
        $bindParams = [];

        if (!empty($searchValue)) {
            $where .= "AND (v.placa LIKE :s OR f.nome_comum LIKE :s OR e.nome_fantasia LIKE :s OR a.numero_cupom LIKE :s) ";
            $bindParams[':s'] = "%" . $searchValue . "%";
        }

        // Ordenação Dinâmica
        $orderBy = "ORDER BY a.data_abastecimento DESC";
        if (isset($params['order']) && !empty($params['order'])) {
            $colIndex = intval($params['order'][0]['column']);
            $colDir = strtoupper($params['order'][0]['dir']);
            if (isset($columnsMap[$colIndex])) {
                $orderBy = "ORDER BY " . $columnsMap[$colIndex] . " " . ($colDir === 'ASC' ? 'ASC' : 'DESC');
            }
        }

        // SQL Base com JOINs para pegar nomes
        $sqlBase = "FROM {$this->table} a 
                    LEFT JOIN funcionarios f ON a.funcionario_id = f.id
                    LEFT JOIN entidades e ON a.entidade_id = e.id
                    LEFT JOIN veiculos v ON a.veiculo_id = v.id";

        // Totais
        $totalRecords = $this->pdo->query("SELECT COUNT(a.id) FROM {$this->table} a")->fetchColumn();

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(a.id) {$sqlBase} {$where}");
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Dados
        $sqlData = "SELECT 
                        a.*, 
                        f.nome_comum as motorista_apelido,
                        e.nome_fantasia as nome_posto, e.cnpj_cpf as cnpj_posto,
                        v.placa as placa_veiculo
                    {$sqlBase}
                    {$where} 
                    {$orderBy} 
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

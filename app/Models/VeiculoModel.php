<?php

/**
 * CLASSE MODELO: VeiculoModel
 * Local: app/Models/VeiculoModel.php
 * Descrição: Gerencia as operações de CRUD e consultas na tabela VEICULOS.
 */

require_once __DIR__ . '/Database.php';
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';

class VeiculoModel
{
    private $pdo;
    private $table = 'veiculos';
    private $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Busca veículos ativos para o Select2 com filtro de pesquisa.
     * @param string $term Termo digitado pelo usuário (Placa ou Modelo).
     * @return array Lista formatada para o Select2.
     */
    /* public function getVeiculosOptions(string $term = ''): array
    {
        $sql = "SELECT 
                    id, 
                    CONCAT(
                        IF(codigo_veiculo IS NOT NULL AND codigo_veiculo != '', CONCAT(codigo_veiculo, ' - '), ''), 
                        placa, ' - ', modelo
                    ) AS text
                FROM {$this->table} 
                WHERE situacao = 'Ativo'";

        $params = [];

        // Se o usuário digitou algo, filtramos por Placa OU Modelo
        if (!empty($term)) {
            // $sql .= " AND (placa LIKE :term OR modelo LIKE :term)";
            $sql .= " AND (placa LIKE :term1 OR modelo LIKE :term2 OR codigo_veiculo LIKE :term3)";
            $params[':term1'] = '%' . $term . '%';
            $params[':term2'] = '%' . $term . '%';
            $params[':term3'] = '%' . $term . '%';

            
        }

        // Ordena e limita a 30 resultados para não travar o navegador
        $sql .= " ORDER BY codigo_veiculo ASC, placa ASC LIMIT 30";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // O fetchAll(PDO::FETCH_ASSOC) já retorna o array no formato correto 
        // se o SELECT tiver os campos 'id' e 'text'
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } */

    /**
     * Busca veículos para Select2 (VERSÃO DEBUG).
     */
    public function getVeiculosOptions(string $term = ''): array
    {
        $sql = "SELECT 
                    id, 
                    CONCAT(
                        IF(codigo_veiculo IS NOT NULL AND codigo_veiculo != '', CONCAT(codigo_veiculo, ' - '), ''), 
                        placa, ' - ', modelo
                    ) AS text
                FROM {$this->table} 
                WHERE situacao = 'Ativo'";

        $params = [];

        if (!empty($term)) {
            $termLike = '%' . $term . '%';
            
            // CORREÇÃO MANTIDA: Parâmetros únicos para evitar conflito no PDO
            $sql .= " AND (placa LIKE :term1 OR modelo LIKE :term2 OR codigo_veiculo LIKE :term3)";
            
            $params[':term1'] = $termLike;
            $params[':term2'] = $termLike;
            $params[':term3'] = $termLike;
        }

        $sql .= " ORDER BY codigo_veiculo ASC, placa ASC LIMIT 30";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Busca os dados formatados para o DataTables (CORRIGIDO: BIND DE PARÂMETROS).
     * @param array $params Parâmetros DataTables (start, length, search).
     * @return array Array formatado para o DataTables.
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        // Mapeamento para ordenação
        $columnsMap = [
            /*0 => 'v.placa',
            1 => 'v.modelo',
            2 => 'v.ano',
            3 => 'v.tipo_frota',
            4 => 'v.situacao',
            5 => 'proprietario_nome',
            6 => 'v.id'*/

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
            // $where .= "AND (v.placa LIKE :s OR v.modelo LIKE :s OR v.marca LIKE :s OR e.nome_fantasia LIKE :s) ";
            $where .= "AND (v.codigo_veiculo LIKE :s OR v.placa LIKE :s OR v.modelo LIKE :s OR v.marca LIKE :s OR e.nome_fantasia LIKE :s) ";
            $bindParams[':s'] = '%' . $searchValue . '%';
        }

        // Ordenação
        $orderBy = "ORDER BY v.placa ASC";
        if (isset($params['order']) && !empty($params['order'])) {
            $colIndex = intval($params['order'][0]['column']);
            $colDir = strtoupper($params['order'][0]['dir']);
            if (isset($columnsMap[$colIndex])) {
                $orderBy = "ORDER BY " . $columnsMap[$colIndex] . " " . ($colDir === 'DESC' ? 'DESC' : 'ASC');
            }
        }

        // SQL Base
        $sqlBase = "FROM {$this->table} v 
                    LEFT JOIN entidades e ON v.proprietario_entidade_id = e.id";

        // Totais
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();

        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(v.id) {$sqlBase} {$where}");
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Dados 
        $sqlData = "SELECT 
                        v.id, v.codigo_veiculo, v.placa, v.marca, v.modelo, v.ano, v.tipo_frota, v.situacao,
                        COALESCE(e.nome_fantasia, e.razao_social, '---') AS proprietario_nome
                    {$sqlBase} 
                    {$where} 
                    {$orderBy} 
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        foreach ($bindParams as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            "draw" => intval($draw),
            "recordsTotal" => intval($totalRecords),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Insere um novo registro de veículo no DB.
     */
    public function create(array $data, int $userId)
    {
        $this->pdo->beginTransaction();

        $sql = "INSERT INTO {$this->table} (
                    codigo_veiculo, placa, modelo, marca, ano, tipo_frota, chassi, 
                    especie_tipo, tipo_combustivel, autonomia, renavam, crv, 
                    valor_licenciamento, valor_ipva, valor_depreciacao, 
                    proprietario_entidade_id, situacao
                ) VALUES (
                    :codigo, :placa, :modelo, :marca, :ano, :tipo_frota, :chassi, 
                    :especie_tipo, :tipo_combustivel, :autonomia, :renavam, :crv, 
                    :valor_licenciamento, :valor_ipva, :valor_depreciacao, 
                    :proprietario_entidade_id, :situacao
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':codigo' => !empty($data['veiculo_codigo']) ? $data['veiculo_codigo'] : null,
                ':placa'  => $data['veiculo_placa'],
                ':modelo' => $data['veiculo_modelo'],
                ':marca'  => $data['veiculo_marca'], // Não esqueça deste campo que adicionamos
                ':ano'    => $data['veiculo_ano'],
                ':tipo_frota' => $data['veiculo_tipo_frota'],

                ':chassi' => $data['veiculo_chassi'],

                ':especie_tipo'     => $data['veiculo_especie_tipo'] ?? null,
                ':tipo_combustivel' => $data['veiculo_tipo_combustivel'] ?? 'Diesel',
                ':autonomia'        => $data['veiculo_autonomia'] ?? 0.0,

                ':renavam' => !empty($data['veiculo_renavam']) ? $data['veiculo_renavam'] : null,
                ':crv'     => !empty($data['veiculo_crv']) ? $data['veiculo_crv'] : null,

                ':valor_licenciamento' => $data['veiculo_licenciamento'] ?? 0.0,
                ':valor_ipva'          => $data['veiculo_ipva'] ?? 0.0,
                ':valor_depreciacao'   => $data['veiculo_depreciacao'] ?? 0.0,

                ':proprietario_entidade_id' => !empty($data['proprietario_entidade_id']) ? $data['proprietario_entidade_id'] : null,
                ':situacao' => $data['veiculo_situacao'] ?? 'Ativo'
            ]);

            $veiculoId = $this->pdo->lastInsertId();

            $this->logger->log(
                'CREATE',
                $this->table,
                $veiculoId,
                null,
                ['placa' => $data['veiculo_placa'], 'modelo' => $data['veiculo_modelo']],
                $userId
            );

            $this->pdo->commit();
            return $veiculoId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Busca um veículo pelo ID, com dados do proprietário.
     * @param int $id ID do veículo.
     * @return array|false
     */
    public function find(int $id)
    {
        $sql = "SELECT 
                    v.*,
                    COALESCE(e.nome_fantasia, e.razao_social) AS proprietario_nome
                FROM {$this->table} v
                LEFT JOIN ENTIDADES e ON v.proprietario_entidade_id = e.id
                WHERE v.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza um veículo existente.
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id);

        $sql = "UPDATE {$this->table} SET
                    codigo_veiculo = :codigo, placa = :placa, modelo = :modelo, marca = :marca,
                    ano = :ano, tipo_frota = :tipo_frota, chassi = :chassi, 
                    tipo_combustivel = :tipo_combustivel, autonomia = :autonomia, 
                    renavam = :renavam, crv = :crv,
                    valor_licenciamento = :valor_licenciamento,
                    valor_ipva = :valor_ipva, 
                    proprietario_entidade_id = :proprietario_entidade_id, 
                    situacao = :situacao, data_atualizacao = NOW()
                WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                ':codigo' => !empty($data['veiculo_codigo']) ? $data['veiculo_codigo'] : null,
                ':placa'  => $data['veiculo_placa'],
                ':modelo' => $data['veiculo_modelo'],
                ':marca'  => $data['veiculo_marca'],
                ':ano'    => $data['veiculo_ano'],
                ':tipo_frota' => $data['veiculo_tipo_frota'],
                ':chassi' => $data['veiculo_chassi'],
                ':tipo_combustivel' => $data['veiculo_tipo_combustivel'] ?? 'Diesel',
                ':autonomia' => $data['veiculo_autonomia'] ?? 0.0,

                ':renavam' => !empty($data['veiculo_renavam']) ? $data['veiculo_renavam'] : null,
                ':crv'     => !empty($data['veiculo_crv']) ? $data['veiculo_crv'] : null,

                ':valor_licenciamento' => $data['veiculo_licenciamento'] ?? 0.0,
                ':valor_ipva'          => $data['veiculo_ipva'] ?? 0.0,

                ':proprietario_entidade_id' => !empty($data['proprietario_entidade_id']) ? $data['proprietario_entidade_id'] : null,
                ':situacao' => $data['veiculo_situacao'] ?? 'Ativo',
                ':id' => $id
            ]);

            if ($success) {
                $this->logger->log('UPDATE', $this->table, $id, $dadosAntigos, $data, $userId);
            }

            $this->pdo->commit();
            return $success;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Exclui um veículo.
     * @param int $id ID do veículo.
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool
     */
    public function delete(int $id, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id);

        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $success = $stmt->execute([':id' => $id]);

            // LOG DE AUDITORIA
            if ($success) {
                $this->logger->log('DELETE', $this->table, $id, $dadosAntigos, null, $userId);
            }

            $this->pdo->commit();
            return $success;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

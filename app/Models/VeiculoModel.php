<?php

/**
 * CLASSE MODELO: VeiculoModel
 * Local: app/Models/VeiculoModel.php
 * Descrição: Gerencia as operações de CRUD e consultas na tabela VEICULOS.
 */

require_once __DIR__ . '/Database.php';

class VeiculoModel
{
    private $pdo;
    private $table = 'VEICULOS';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Busca todos os veículos ativos para serem usados em dropdowns (Select2).
     * @return array Lista de veículos (id, placa, modelo).
     */
    public function getVeiculosOptions(): array
    {
        $sql = "SELECT 
                    id, 
                    placa, 
                    modelo, 
                    CONCAT(placa, ' - ', modelo) AS text
                FROM {$this->table} 
                WHERE situacao = 'Ativo' 
                ORDER BY placa ASC";

        $stmt = $this->pdo->query($sql);

        // Retorna no formato [id: x, text: 'Placa - Modelo'] que o JavaScript Select2 espera
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => $row['id'],
                'text' => $row['text']
            ];
        }
        return $results;
    }

    /**
     * Busca os dados formatados para o DataTables.
     * @param array $params Parâmetros DataTables (start, length, search).
     * @return array Array formatado para o DataTables.
     */
    public function findAllForDataTable(array $params): array
    {
        // SIMPLIFICAÇÃO: A query final precisará de JOIN com ENTIDADES para mostrar o proprietário.
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        $sqlBase = "FROM VEICULOS v 
                    LEFT JOIN ENTIDADES e ON v.proprietario_entidade_id = e.id";

        $conditions = [];
        $queryParams = [];

        if (!empty($searchValue)) {
            $conditions[] = "(v.placa LIKE :search OR v.modelo LIKE :search OR e.razao_social LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

        $sqlCount = "SELECT COUNT(v.id) $sqlBase";
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM VEICULOS")->fetchColumn();

        $stmtFiltered = $this->pdo->prepare($sqlCount . $whereClause);
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT 
                        v.id, v.placa, v.modelo, v.ano, v.tipo_frota, v.situacao,
                        COALESCE(e.nome_fantasia, e.razao_social) AS proprietario_nome
                    $sqlBase $whereClause 
                    ORDER BY v.placa ASC 
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int) $length, PDO::PARAM_INT);
        foreach ($queryParams as $key => &$value) {
            $stmt->bindParam($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => (int) $draw,
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Insere um novo registro de veículo no DB.
     * @param array $data Dados do veículo.
     * @return int|false ID do novo veículo ou false em caso de erro.
     */
    public function create(array $data)
    {
        $sql = "INSERT INTO {$this->table} (
                    placa, modelo, ano, tipo_frota, chassi, especie_tipo, tipo_combustivel, 
                    autonomia, renavam, crv, valor_licenciamento, valor_ipva, 
                    valor_depreciacao, proprietario_entidade_id, situacao
                ) VALUES (
                    :placa, :modelo, :ano, :tipo_frota, :chassi, :especie_tipo, :tipo_combustivel, 
                    :autonomia, :renavam, :crv, :valor_licenciamento, :valor_ipva, 
                    :valor_depreciacao, :proprietario_entidade_id, :situacao
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':placa' => $data['veiculo_placa'],
                ':modelo' => $data['veiculo_modelo'],
                ':ano' => $data['veiculo_ano'],
                ':tipo_frota' => $data['veiculo_tipo_frota'],
                ':chassi' => $data['veiculo_chassi'],

                // Trata campos opcionais com null ou valor padrão
                ':especie_tipo' => $data['veiculo_especie_tipo'] ?? null,
                ':tipo_combustivel' => $data['veiculo_tipo_combustivel'] ?? 'Diesel',
                ':autonomia' => $data['veiculo_autonomia'] ?? 0.0,
                ':renavam' => $data['veiculo_renavam'] ?? null,
                ':crv' => $data['veiculo_crv'] ?? null,
                ':valor_licenciamento' => $data['veiculo_licenciamento'] ?? 0.0,
                ':valor_ipva' => $data['veiculo_ipva'] ?? 0.0,
                ':valor_depreciacao' => $data['veiculo_depreciacao'] ?? 0.0,

                ':proprietario_entidade_id' => $data['veiculo_proprietario_id'],
                ':situacao' => $data['veiculo_situacao'] ?? 'Ativo' 
            ]);
            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
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
     * @param int $id ID do veículo a ser atualizado.
     * @param array $data Dados do veículo.
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    placa = :placa, modelo = :modelo, ano = :ano, tipo_frota = :tipo_frota, 
                    chassi = :chassi, tipo_combustivel = :tipo_combustivel, autonomia = :autonomia, 
                    valor_ipva = :valor_ipva, proprietario_entidade_id = :proprietario_entidade_id, 
                    situacao = :situacao, data_atualizacao = NOW()
                WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':placa' => $data['veiculo_placa'],
                ':modelo' => $data['veiculo_modelo'],
                ':ano' => $data['veiculo_ano'],
                ':tipo_frota' => $data['veiculo_tipo_frota'],
                ':chassi' => $data['veiculo_chassi'],
                ':tipo_combustivel' => $data['veiculo_tipo_combustivel'] ?? 'Diesel',
                ':autonomia' => $data['veiculo_autonomia'] ?? 0.0,
                ':valor_ipva' => $data['veiculo_ipva'] ?? 0.0,
                ':proprietario_entidade_id' => $data['veiculo_proprietario_id'],
                ':situacao' => $data['veiculo_situacao'] ?? 'Ativo',
                ':id' => $id
            ]);
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Exclui um veículo.
     * @param int $id ID do veículo.
     * @return bool
     */
    public function delete(int $id): bool
    {
        // Implementar a auditoria aqui futuramente
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}

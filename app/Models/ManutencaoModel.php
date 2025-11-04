<?php

/**
 * CLASSE MODELO: ManutencaoModel
 * Local: app/Models/ManutencaoModel.php
 * Descrição: Gerencia as operações de CRUD na tabela MANUTENCOES e consultas relacionadas a veículos/custos.
 */

require_once __DIR__ . '/Database.php';

class ManutencaoModel
{
    private $pdo;
    private $table = 'MANUTENCOES';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Insere um novo registro de manutenção no DB.
     * @param array $data Dados da manutenção.
     * @return int|false ID da nova manutenção ou false.
     */
    public function create(array $data)
    {
        $sql = "INSERT INTO {$this->table} (
                    veiculo_id, fornecedor_id, data_servico, 
                    servico_peca, tipo_manutencao, valor, data_criacao
                ) VALUES (
                    :veiculo_id, :fornecedor_id, :data_servico, 
                    :servico_peca, :tipo_manutencao, :valor, NOW()
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':veiculo_id'       => $data['man_veiculo_id'],
                ':fornecedor_id'    => $data['man_fornecedor_id'],
                ':data_servico'     => $data['man_data_servico'],
                ':servico_peca'     => $data['man_servico_peca'],
                ':tipo_manutencao'  => $data['man_tipo_manutencao'],
                ':valor'            => $data['man_valor'],
            ]);
            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Em um sistema com auditoria, registraríamos o erro aqui.
            throw $e;
        }
    }

    /**
     * Busca todos os registros de manutenção para o DataTables (CORRIGIDO: BIND DE PARÂMETROS).
     * @param array $params Parâmetros DataTables.
     * @return array Dados formatados.
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        $sqlBase = "FROM MANUTENCOES m 
                    JOIN VEICULOS v ON m.veiculo_id = v.id
                    JOIN ENTIDADES f ON m.fornecedor_id = f.id";

        $bindParams = [];
        $where = "WHERE 1=1 ";

        // 1. CONDIÇÃO DA PESQUISA GLOBAL (SEARCH)
        if (!empty($searchValue)) {
            // Usando placeholders nomeados para evitar HY093 (como fizemos em Entidades)
            $where .= "AND (v.placa LIKE :search1 OR m.servico_peca LIKE :search2 OR f.razao_social LIKE :search3) ";
            $bindParams[':search1'] = '%' . $searchValue . '%';
            $bindParams[':search2'] = '%' . $searchValue . '%';
            $bindParams[':search3'] = '%' . $searchValue . '%';
        }

        // 2. Contagem Total
        $sqlTotal = "SELECT COUNT(m.id) $sqlBase";
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM MANUTENCOES")->fetchColumn();

        // 3. Contagem Filtrada
        $sqlFiltered = "SELECT COUNT(m.id) $sqlBase $where";
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // 4. Dados
        $sqlData = "SELECT 
                        m.id, m.data_servico, m.servico_peca, m.valor, m.tipo_manutencao, 
                        v.placa, f.nome_fantasia AS fornecedor_nome
                    $sqlBase $where 
                    ORDER BY m.data_servico DESC 
                    LIMIT :start, :length";

        $stmtData = $this->pdo->prepare($sqlData);
        $stmtData->bindValue(':start', (int) $start, PDO::PARAM_INT);
        $stmtData->bindValue(':length', (int) $length, PDO::PARAM_INT);
        foreach ($bindParams as $key => &$value) {
            $stmtData->bindValue($key, $value);
        }
        $stmtData->execute();
        $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => (int) $draw,
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Busca todos os dados de manutenção para a geração do PDF.
     * @return array Dados completos.
     */
    public function findAllRelatorioData(): array
    {
        $sql = "SELECT 
                    m.data_servico, m.servico_peca, m.valor, 
                    v.placa, 
                    f.nome_fantasia AS fornecedor_nome
                FROM MANUTENCOES m 
                JOIN VEICULOS v ON m.veiculo_id = v.id
                JOIN ENTIDADES f ON m.fornecedor_id = f.id
                ORDER BY m.data_servico DESC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

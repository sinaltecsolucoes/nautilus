<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use PDOException;
use Exception;

class ManutencaoModel
{
    protected PDO $pdo;
    protected AuditLoggerService $logger;
    protected string $headerTable = 'manutencao_header';
    protected string $itensTable = 'manutencao_itens';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Salva uma manutenção completa (Cabeçalho + Itens) em uma única transação.
     */
    public function createFull(array $header, array $itens, int $userId)
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Inserir Cabeçalho
            $sqlHeader = "INSERT INTO manutencao_header 
                (veiculo_id, fornecedor_id, data_manutencao, km_atual, numero_os, valor_bruto, valor_desconto, valor_liquido)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sqlHeader);
            $stmt->execute([
                $header['veiculo_id'],
                $header['fornecedor_id'],
                $header['data_manutencao'],
                $header['km_atual'],
                $header['numero_os'],
                $header['valor_bruto'],
                $header['valor_desconto'],
                $header['valor_liquido']
            ]);

            $headerId = $this->pdo->lastInsertId();

            // 2. Inserir Itens
            $sqlItem = "INSERT INTO manutencao_itens 
                (header_id, tipo, descricao, valor_pecas, valor_mao_obra, valor_total_item, meses_rateio)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtItem = $this->pdo->prepare($sqlItem);

            foreach ($itens as $item) {
                $stmtItem->execute([
                    $headerId,
                    $item['tipo'],
                    $item['descricao'],
                    $item['valor_pecas'],
                    $item['valor_mao_obra'],
                    $item['valor_total_item'],
                    $item['meses_rateio']
                ]);
            }

            // Log de Auditoria
            $this->logger->log('CREATE', 'manutencao_header', $headerId, null, $header, $userId);

            $this->pdo->commit();
            return $headerId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Busca dados para o DataTables (Server-Side).
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        // Trata se search vier como array ou string
        $search = is_array($params['search']) ? ($params['search']['value'] ?? '') : ($params['search'] ?? '');

        // Mapeamento de colunas para ordenação
        $columnsMap = [
            0 => 'mh.data_manutencao',
            1 => 'v.placa',
            2 => 'e.nome_fantasia',
            3 => 'mh.numero_os',
            4 => 'mh.valor_liquido',
            5 => 'mh.id'
        ];

        $where = "WHERE 1=1 ";
        $bindParams = [];

        if (!empty($search)) {
            $searchTerm = "%$search%";

            $where .= "AND (
                v.placa LIKE :s1 OR 
                e.nome_fantasia LIKE :s2 OR 
                mh.numero_os LIKE :s3 OR
                -- BUSCA PROFUNDA: Verifica se existe algum item com essa descrição vinculado a este cabeçalho
                EXISTS (SELECT 1 FROM manutencao_itens mi_busca WHERE mi_busca.header_id = mh.id AND mi_busca.descricao LIKE :s4)
            ) ";

            $bindParams[':s1'] = $searchTerm;
            $bindParams[':s2'] = $searchTerm;
            $bindParams[':s3'] = $searchTerm;
            $bindParams[':s4'] = $searchTerm; // Novo parâmetro para a subquery
        }

        // Ordenação Padrão: Data Crescente
        $orderBy = "ORDER BY mh.data_manutencao ASC";
        if (isset($params['order']) && !empty($params['order'])) {
            $colIndex = intval($params['order'][0]['column']);
            $colDir = strtoupper($params['order'][0]['dir']);
            if (isset($columnsMap[$colIndex])) {
                $orderBy = "ORDER BY " . $columnsMap[$colIndex] . " " . ($colDir === 'ASC' ? 'ASC' : 'DESC');
            }
        }

        $sqlBase = "FROM manutencao_header mh
                    LEFT JOIN veiculos v ON mh.veiculo_id = v.id
                    LEFT JOIN entidades e ON mh.fornecedor_id = e.id";

        // Totais
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM manutencao_header")->fetchColumn();

        $stmtFilt = $this->pdo->prepare("SELECT COUNT(mh.id) {$sqlBase} {$where}");
        $stmtFilt->execute($bindParams);
        $totalFiltered = $stmtFilt->fetchColumn();

        // Dados finais com concatenação do nome do veículo e contagem de serviços
        $sqlData = "SELECT 
                        mh.*, 
                        CONCAT(
                            IF(v.codigo_veiculo IS NOT NULL AND v.codigo_veiculo != '', CONCAT(v.codigo_veiculo, ' - '), ''), 
                            v.placa
                        ) AS veiculo_nome,
                        e.nome_fantasia AS fornecedor_nome,
                        (SELECT COUNT(*) FROM manutencao_itens mi WHERE mi.header_id = mh.id) as qtd_servicos
                    {$sqlBase} {$where} {$orderBy} LIMIT :start, :length";

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
     * Busca todos os dados de uma manutenção (Header + Itens) para visualização/edição.
     */
    public function findFull($id)
    {
        // 1. Header
        $sqlHeader = "SELECT mh.*, 
                             v.placa as veiculo_placa, v.codigo_veiculo,
                             e.nome_fantasia as fornecedor_nome
                      FROM manutencao_header mh
                      LEFT JOIN veiculos v ON mh.veiculo_id = v.id
                      LEFT JOIN entidades e ON mh.fornecedor_id = e.id
                      WHERE mh.id = ?";
        $stmt = $this->pdo->prepare($sqlHeader);
        $stmt->execute([$id]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$header) return null;

        // Formata nome do veículo para o Select2
        $header['veiculo_nome_completo'] = ($header['codigo_veiculo'] ? $header['codigo_veiculo'] . ' - ' : '') . $header['veiculo_placa'];

        // 2. Itens
        $sqlItens = "SELECT * FROM manutencao_itens WHERE header_id = ?";
        $stmtItem = $this->pdo->prepare($sqlItens);
        $stmtItem->execute([$id]);
        $itens = $stmtItem->fetchAll(PDO::FETCH_ASSOC);

        return ['header' => $header, 'itens' => $itens];
    }

    /**
     * Exclui uma manutenção (o banco deleta os itens em cascata).
     */
    public function delete(int $id, int $userId)
    {
        $this->pdo->beginTransaction();
        try {
            // Busca dados antigos para log
            $sqlOld = "SELECT * FROM manutencao_header WHERE id = ?";
            $stmtOld = $this->pdo->prepare($sqlOld);
            $stmtOld->execute([$id]);
            $antigo = $stmtOld->fetch(PDO::FETCH_ASSOC);

            // Deleta
            $stmt = $this->pdo->prepare("DELETE FROM manutencao_header WHERE id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                $this->logger->log('DELETE', 'manutencao_header', $id, $antigo, null, $userId);
            }

            $this->pdo->commit();
            return $success;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Atualiza uma manutenção completa (Header + Substituição de Itens).
     */
    public function updateFull(int $id, array $header, array $itens, int $userId)
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Atualiza o Cabeçalho
            $sqlHeader = "UPDATE manutencao_header SET 
                veiculo_id=?, fornecedor_id=?, data_manutencao=?, km_atual=?, numero_os=?, 
                valor_bruto=?, valor_desconto=?, valor_liquido=?, atualizado_em=NOW()
                WHERE id=?";

            $stmt = $this->pdo->prepare($sqlHeader);
            $stmt->execute([
                $header['veiculo_id'],
                $header['fornecedor_id'],
                $header['data_manutencao'],
                $header['km_atual'],
                $header['numero_os'],
                $header['valor_bruto'],
                $header['valor_desconto'],
                $header['valor_liquido'],
                $id
            ]);

            // 2. Limpa os itens antigos (Estratégia de Substituição)
            $stmtDel = $this->pdo->prepare("DELETE FROM manutencao_itens WHERE header_id = ?");
            $stmtDel->execute([$id]);

            // 3. Reinsere os itens atualizados
            $sqlItem = "INSERT INTO manutencao_itens 
                (header_id, tipo, descricao, valor_pecas, valor_mao_obra, valor_total_item, meses_rateio)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtItem = $this->pdo->prepare($sqlItem);

            foreach ($itens as $item) {
                $stmtItem->execute([
                    $id, // Usa o ID do cabeçalho existente
                    $item['tipo'],
                    $item['descricao'],
                    $item['valor_pecas'],
                    $item['valor_mao_obra'],
                    $item['valor_total_item'],
                    $item['meses_rateio']
                ]);
            }

            // Log
            $this->logger->log('UPDATE', 'manutencao_header', $id, null, $header, $userId);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

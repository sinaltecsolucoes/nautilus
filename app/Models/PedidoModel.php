<?php

/**
 * CLASSE MODELO: PedidoModel
 * Local: app/Models/PedidoModel.php
 * Descrição: Gerencia as operações de CRUD na tabela PREVISOES_VENDAS (Pedidos).
 */

require_once __DIR__ . '/Database.php';

class PedidoModel
{
    private $pdo;
    private $table = 'PREVISOES_VENDAS';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Insere um novo pedido (previsão) no DB.
     * @param array $data Dados do pedido (contém todos os campos da tabela).
     * @return int|false ID do novo pedido.
     */
    public function create(array $data)
    {
        // Cálculo do Bônus (Lógica de Negócio)
        $quantidade = (float)($data['ped_quantidade'] ?? 0);
        $bonus_perc = (float)($data['ped_percentual_bonus'] ?? 0);
        $quantidade_com_bonus = $quantidade * (1 + ($bonus_perc / 100));

        // Cálculo do Valor Total (Baseado na Quantidade original, não no bônus)
        $valor_unitario = (float)($data['ped_valor_unitario'] ?? 0);
        $valor_total = $quantidade * $valor_unitario;

        $sql = "INSERT INTO {$this->table} (
                    os_numero, cliente_entidade_id, vendedor_funcionario_id, data_saida,
                    quantidade, percentual_bonus, quantidade_com_bonus, valor_unitario, valor_total,
                    forma_pagamento, condicao, salinidade, divisao, status, status_dia
                ) VALUES (
                    :os_numero, :cliente_id, :vendedor_id, :data_saida,
                    :quantidade, :bonus_perc, :qtd_com_bonus, :valor_unitario, :valor_total,
                    :forma_pagamento, :condicao, :salinidade, :divisao, :status, :status_dia
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':os_numero'        => $data['ped_os_numero'],
                ':cliente_id'       => $data['ped_cliente_id'],
                ':vendedor_id'      => $data['ped_vendedor_id'], // Deve vir da sessão ou do formulário
                ':data_saida'       => $data['ped_data_saida'],
                ':quantidade'       => $quantidade,
                ':bonus_perc'       => $bonus_perc,
                ':qtd_com_bonus'    => $quantidade_com_bonus,
                ':valor_unitario'   => $valor_unitario,
                ':valor_total'      => $valor_total,
                ':forma_pagamento'  => $data['ped_forma_pagamento'] ?? 'Prazo',
                ':condicao'         => $data['ped_condicao'] ?? 'Antecipacao',
                ':salinidade'       => $data['ped_salinidade'] ?? null,
                ':divisao'          => $data['ped_divisao'] ?? '02 viveiros',
                ':status'           => $data['ped_status'] ?? 'Confirmado',
                ':status_dia'       => $data['ped_status_dia'] ?? 'Reservado no tanque',
            ]);
            // Adicionar Log de Auditoria aqui
            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Busca todos os pedidos para o DataTables (Foco em listagem de previsão).
     * @param array $params Parâmetros DataTables.
     * @return array Dados formatados.
     */
    public function findAllForDataTable(array $params, int $vendedorId = 0): array
    {
        // Esta é uma simplificação. A query real precisa de JOINS.
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        $sqlBase = "FROM {$this->table} pv 
                    JOIN ENTIDADES c ON pv.cliente_entidade_id = c.id
                    JOIN FUNCIONARIOS v ON pv.vendedor_funcionario_id = v.id";

        $conditions = [];
        $queryParams = [];

        // Se for um Vendedor, mostra apenas os pedidos dele (ACL simples)
        if ($vendedorId > 0) {
            $conditions[] = "pv.vendedor_funcionario_id = :vendedor_id";
            $queryParams[':vendedor_id'] = $vendedorId;
        }

        if (!empty($searchValue)) {
            $conditions[] = "(pv.os_numero LIKE :search OR c.razao_social LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

        // Contagem Total e Filtrada... (simplificado)
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();
        $totalFiltered = $totalRecords;

        $sqlData = "SELECT 
                        pv.id, pv.os_numero, pv.data_saida, pv.quantidade_com_bonus, pv.valor_total,
                        pv.status, pv.status_dia, c.nome_fantasia AS cliente_nome, v.nome_completo AS vendedor_nome
                    $sqlBase $whereClause 
                    ORDER BY pv.data_saida DESC 
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

    // Futuramente: find, update, delete, getNextOSNumber...
}

<?php

/**
 * CLASSE MODELO: PedidoModel
 * Local: app/Models/PedidoModel.php
 * Descrição: Gerencia as operações de CRUD na tabela PREVISOES_VENDAS (Pedidos).
 */

require_once __DIR__ . '/Database.php';
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';

class PedidoModel
{
    private $pdo;
    private $table = 'PREVISOES_VENDAS';
    private $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Calcula a quantidade final com bônus e o valor total do pedido.
     * @param float $quantidade Quantidade base (larvas).
     * @param float $percentualBonus Percentual de bônus (0.00 a 100.00).
     * @param float $valorUnitario Valor unitário do produto.
     * @return array [quantidade_com_bonus, valor_total]
     */
    private function calcularMetricas(float $quantidade, float $percentualBonus, float $valorUnitario): array
    {
        // Quantidade com Bônus = Quantidade * (1 + (Percentual Bônus / 100))
        $fatorBonus = 1 + ($percentualBonus / 100);
        $quantidadeComBonus = round($quantidade * $fatorBonus);

        // Valor Total = Quantidade com Bônus * Valor Unitário
        $valorTotal = $quantidadeComBonus * $valorUnitario;

        return [
            'quantidade_com_bonus' => (int)$quantidadeComBonus,
            'valor_total' => round($valorTotal, 2)
        ];
    }

    /**
     * Cria um novo Pedido/Previsão de Venda.
     * @param array $data Dados do pedido (sem 'quantidade_com_bonus' e 'valor_total').
     * @param int $userId ID do funcionário logado (para auditoria).
     * @return int|false O ID do novo pedido ou false em caso de erro.
     */
    public function create(array $data, int $userId)
    {
        $this->pdo->beginTransaction();

        try {
            // 1. CALCULA MÉTRICAS
            $metricas = $this->calcularMetricas(
                $data['quantidade'],
                $data['percentual_bonus'],
                $data['valor_unitario']
            );

            // 2. INSERE O PEDIDO
            $sql = "INSERT INTO {$this->table} 
                (os_numero, cliente_entidade_id, vendedor_funcionario_id, data_saida,
                 quantidade, percentual_bonus, quantidade_com_bonus, valor_unitario, valor_total,
                 forma_pagamento, condicao, salinidade, divisao, status, status_dia)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['os_numero'],
                $data['cliente_entidade_id'],
                $data['vendedor_funcionario_id'],
                $data['data_saida'],
                $data['quantidade'],
                $data['percentual_bonus'],
                $metricas['quantidade_com_bonus'], // RESULTADO DO CÁLCULO
                $data['valor_unitario'],
                $metricas['valor_total'],         // RESULTADO DO CÁLCULO
                $data['forma_pagamento'],
                $data['condicao'],
                $data['salinidade'] ?? null,
                $data['divisao'] ?? null,
                $data['status'] ?? 'Confirmado',
                $data['status_dia'] ?? null,
            ]);

            $pedidoId = $this->pdo->lastInsertId();

            // LOG DE AUDITORIA: CREATE
            $this->logger->log(
                'CREATE',
                $this->table,
                $pedidoId,
                null,
                $data,
                $userId
            );

            $this->pdo->commit();
            return $pedidoId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
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

    /**
     * Busca um pedido por ID (para edição).
     * @param int $id O ID do pedido.
     * @return array|false Os dados do pedido ou false se não encontrado.
     */
    public function find(int $id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Deleta um pedido.
     * @param int $id O ID do pedido a ser deletado.
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool Sucesso ou falha na transação.
     */
    public function delete(int $id, int $userId)
    {
        $this->pdo->beginTransaction();

        try {
            // 1. Auditoria
            $dadosAntigos = $this->find($id);

            // 2. DELETA O PEDIDO
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);

            // 3. Log de DELETE
            if ($stmt->rowCount() > 0) {
                $this->logger->log('DELETE', $this->table, $id, $dadosAntigos, null, $userId);
            }

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            // Lançar exceção se houver restrições de chave estrangeira (se o pedido já estiver em expedição)
            if ($e->getCode() === '23000') {
                throw new \Exception("Impossível excluir: Este pedido já está em uma entrega associada.");
            }
            throw $e;
        }
    }

    /**
     * Busca o número da última OS salva para determinar a próxima sequência.
     * @return string|false O número da última OS (ex: '1000') ou false.
     */
    public function getLastOSNumber()
    {
        // 1. Busca o valor máximo (último) da coluna os_numero.
        // A OS é VARCHAR(50) no banco, então precisamos garantir que o MAX() funcione numericamente se os números forem sequenciais.
        $sql = "SELECT MAX(os_numero) FROM {$this->table}";

        $stmt = $this->pdo->query($sql);
        $lastOS = $stmt->fetchColumn();

        // Se o resultado for nulo ou vazio, retorna false para o Controller usar o número inicial (ex: 1001)
        return $lastOS ?? false;
    }
}

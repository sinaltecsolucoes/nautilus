<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use PDOException;

/**
 * Class PedidoModel
 * Gerencia a tabela de Previsões de Vendas.
 * @package App\Models
 * @version 1.1.0
 */
class PedidoModel
{
    // =========================================================================
    // 1. PROPRIEDADES E CONSTANTES
    // =========================================================================

    protected PDO $pdo;
    protected AuditLoggerService $logger;
    protected string $table = 'previsoes_vendas';
    protected array $fillable = [
        'os_numero',
        'cliente_entidade_id',
        'vendedor_funcionario_id',
        'data_saida',
        'quantidade',
        'valor_unitario',
        'valor_total'
    ];

    // =========================================================================
    // 2. CONSTRUTOR E INICIALIZAÇÃO
    // =========================================================================

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->logger = new AuditLoggerService();
    }

    // =========================================================================
    // 3. RELACIONAMENTOS (Simulados em SQL)
    // =========================================================================

    /**
     * Define o JOIN padrão para trazer dados do Cliente e Vendedor.
     * @return string Trecho SQL para Joins.
     */
    protected function getDefaultJoins(): string
    {
        return " JOIN ENTIDADES c ON pv.cliente_entidade_id = c.id
                 JOIN FUNCIONARIOS v ON pv.vendedor_funcionario_id = v.id ";
    }

    // =========================================================================
    // 4. MÉTODOS DE ESCOPO/QUERY (CRUD)
    // =========================================================================

    /**
     * Busca um pedido por ID com todos os relacionamentos necessários.
     * @param int $id
     */
    public function find(int $id)
    {
        try {
            $sql = "SELECT 
                    pv.*, 
                    c.nome_fantasia AS cliente_nome,
                    c.razao_social AS cliente_razao
                FROM {$this->table} pv
                LEFT JOIN ENTIDADES c ON pv.cliente_entidade_id = c.id
                WHERE pv.id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar Pedido {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria um novo registro no banco.
     * @param array $data Dados sanitizados.
     * @param int $userId ID do usuário logado.
     * @return int ID do registro criado.
     * @throws PDOException
     */
    public function create(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();

        try {
            // Lógica de Negócio: Cálculo de Métricas antes de salvar
            $metricas = $this->calcularMetricas(
                (float)$data['quantidade'],
                (float)($data['percentual_bonus'] ?? 0),
                (float)$data['valor_unitario']
            );

            $sql = "INSERT INTO {$this->table} 
                    (os_numero, 
                    cliente_entidade_id,
                    vendedor_funcionario_id, 
                    data_saida,
                    quantidade,
                    percentual_bonus,
                    quantidade_com_bonus, 
                    valor_unitario, 
                    valor_total,
                    forma_pagamento,
                    condicao,
                    salinidade, 
                    divisao, 
                    status, 
                    status_dia)
                VALUES (:os, 
                    :cliente_id,
                    :vendedor_id, 
                    :data_saida,
                    :quantidade,
                    :percentual_bonus,
                    :quantidade_com_bonus, 
                    :valor_unitario, 
                    :valor_total,
                    :forma_pagamento,
                    :condicao,
                    :salinidade, 
                    :divisao, 
                    :status, 
                    :status_dia)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':os'                   => $data['os_numero'],
                ':cliente_id'           => $data['cliente_entidade_id'],
                ':vendedor_id'          => $data['vendedor_funcionario_id'],
                ':data_saidao'          => $data['data_saida'],
                ':quantidade'           => $data['quantidade'],
                ':percentual_bonus'     => $data['percentual_bonus'],
                ':quantidade_com_bonus' => $metricas['quantidade_com_bonus'],
                ':valor_unitario'       => $data['valor_unitario'],
                ':valor_total'          => $metricas['valor_total'],
                ':forma_pagamento'      => $data['forma_pagamento'],
                ':condicao'             => $data['condicao'],
                ':salinidade'           => $data['salinidade'] ?? null,
                ':divisao'              => $data['divisao'] ?? null,
                ':status'               => $data['status'] ?? 'Confirmado',
                ':status_dia'           => $data['status_dia'] ?? null,
            ]);

            $id = (int)$this->pdo->lastInsertId();

            // Log de Auditoria
            $this->logger->log(
                'CREATE',
                $this->table,
                $id,
                null,
                $data,
                $userId
            );

            $this->pdo->commit();
            return $id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Erro ao criar entidade: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atualiza um pedido existente.
     * @param int $id ID do pedido.
     * @param array $data Dados do formulário.
     * @param int $userId ID do usuário logado.
     * @return bool
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();

        // 1. Busca dados antigos para o Log de Auditoria
        $dadosAntigos = $this->find($id);

        if (!$dadosAntigos) {
            throw new \Exception("Pedido não encontrado.");
        }

        try {

            // 2. Recalcula Métricas (Importante se mudou quantidade ou valor)
            $metricas = $this->calcularMetricas(
                (float)$data['quantidade'],
                (float)($data['percentual_bonus'] ?? 0),
                (float)$data['valor_unitario']
            );

            // 3. Atualiza no Banco
            $sql = "UPDATE {$this->table} SET 
                os_numero = :os, cliente_entidade_id = :cliente,
                vendedor_funcionario_id = :vendedor, data_saida = :data,
                quantidade = :qtd, percentual_bonus = :perc,
                quantidade_com_bonus = :qtd_bonus, valor_unitario = :unit,
                valor_total = :total, forma_pagamento = :pgto,
                condicao = :cond,salinidade = :sal, divisao = :div, status = :status,
                status_dia = :status_dia, data_atualizacao = NOW()
                WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':os'        => $data['os_numero'],
                ':cliente'   => $data['cliente_entidade_id'],
                ':vendedor'  => $data['vendedor_funcionario_id'],
                ':data'      => $data['data_saida'],
                ':qtd'       => $data['quantidade'],
                ':perc'      => $data['percentual_bonus'],
                ':qtd_bonus' => $metricas['quantidade_com_bonus'],
                ':unit'      => $data['valor_unitario'],
                ':total'     => $metricas['valor_total'],
                ':pgto'      => $data['forma_pagamento'],
                ':cond'      => $data['condicao'],
                ':sal'       => $data['salinidade'] ?? null,
                ':div'       => $data['divisao'] ?? null,
                ':status'    => $data['status'] ?? 'Confirmado',
                ':status_dia' => $data['status_dia'] ?? null,
                ':id'        => $id
            ]);

            // 4. Log de Auditoria (UPDATE)
            $this->logger->log(
                'UPDATE',
                $this->table,
                $id,
                $dadosAntigos,
                $data,
                $userId
            );

            $this->pdo->commit();

            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro ao atualizar entidade {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Método específico para DataTables (Server-side).
     */
    public function findAllForDataTable(array $params, int $vendedorId = 0): array
    {
        $draw = $params['draw'] ?? 1;
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';

        $conditions = [];
        $bindings = [];

        // Filtros (Scopes)
        if ($vendedorId > 0) {
            $conditions[] = "pv.vendedor_funcionario_id = :vendedor_id";
            $bindings[':vendedor_id'] = $vendedorId;
        }

        if (!empty($search)) {
            $conditions[] = "(pv.os_numero LIKE :search OR c.razao_social LIKE :search OR c.nome_fantasia LIKE :search)";
            $bindings[':search'] = "%{$search}%";
        }

        $where = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
        $joins = $this->getDefaultJoins(); // Reutiliza o join padrão

        // Queries
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();

        $sqlCount = "SELECT COUNT(pv.id) FROM {$this->table} pv {$joins} {$where}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($bindings);
        $totalFiltered = $stmtCount->fetchColumn();

        $sqlData = "SELECT 
                        pv.id, pv.os_numero, pv.data_saida, pv.quantidade,
                        pv.quantidade_com_bonus, pv.valor_unitario, pv.valor_total,
                        pv.status, pv.status_dia, 
                        c.nome_fantasia AS cliente_nome, 
                        v.nome_completo AS vendedor_nome
                    FROM {$this->table} pv 
                    {$joins} {$where} 
                    ORDER BY pv.data_saida DESC 
                    LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        foreach ($bindings as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        return [
            "draw" => (int)$draw,
            "recordsTotal" => (int)$totalRecords,
            "recordsFiltered" => (int)$totalFiltered,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    // =========================================================================
    // 5. MÉTODOS CUSTOMIZADOS (Regras de Negócio)
    // =========================================================================

    /**
     * Calcula regras de negócio para valores e bônus.
     */
    private function calcularMetricas(float $qtd, float $bonusPerc, float $valorUnit): array
    {
        $fatorBonus = 1 + ($bonusPerc / 100);
        $qtdBonus = round($qtd * $fatorBonus);

        return [
            'quantidade_com_bonus' => (int)$qtdBonus,
            'valor_total' => round($qtdBonus * $valorUnit, 2)
        ];
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
        } catch (PDOException $e) {
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

    /**
     * Busca dados do pedido pelo número da OS.
     * Usado para:
     * 1. Integração com Expedição (preenchimento automático).
     * 2. Validação de duplicidade no cadastro de Pedidos.
     * * @param string $osNumero
     * @return array|false
     */
    public function getPedidoPorOS($osNumero)
    {
        // Faz o JOIN para já trazer o nome do cliente (necessário para o Select2 da expedição)
        $sql = "SELECT 
                    pv.*,
                    e.nome_fantasia as cliente_nome,
                    e.id as cliente_id
                FROM {$this->table} pv
                INNER JOIN ENTIDADES e ON pv.cliente_entidade_id = e.id
                WHERE pv.os_numero = :os
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':os' => $osNumero]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

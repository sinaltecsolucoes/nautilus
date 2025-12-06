<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use PDOException;
use Exception;

class FuncionarioModel
{
    protected PDO $pdo;
    protected AuditLoggerService $logger;
    protected string $table = 'funcionarios';

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->logger = new AuditLoggerService();
    }

    public function getFuncionarioByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createFuncionario(array $data, int $userId = 0): int
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO {$this->table}
                (nome_completo, nome_comum, email, senha_hash, tipo_cargo, situacao)
                VALUES (:nome, :apelido, :email, :senha, :cargo, :sit)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome'    => $data['nome_completo'],
                ':apelido' => $data['nome_comum'],
                ':email'   => $data['email'],
                ':senha'   => $data['senha_hash'] ?? null,
                ':cargo'   => $data['tipo_cargo'],
                ':sit'     => $data['situacao'] ?? 'Ativo',
            ]);

            $id = (int)$this->pdo->lastInsertId();
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
            throw $e;
        }
    }

    public function getForDataTable(array $params): array
    {
        $start = (int)$params['start'];
        $length = (int)$params['length'];
        //$search = $params['search'] ?? '';
        // Trata se search vier como array ou string
        $search = is_array($params['search']) ? ($params['search']['value'] ?? '') : ($params['search'] ?? '');


        $where = "WHERE 1=1 ";
        $bindParams = [];

        if (!empty($search)) {
            $searchTerm = "%$search%";

            $where .= "AND (
            nome_completo LIKE :s1 OR 
            nome_comum LIKE :s2 OR 
            email LIKE :s3 OR 
            tipo_cargo LIKE :s4) ";

            $bindParams[':s1'] = $searchTerm;
            $bindParams[':s2'] = $searchTerm;
            $bindParams[':s3'] = $searchTerm;
            $bindParams[':s4'] = $searchTerm;
        }

        $totalRecords = (int)$this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();

        $stmtF = $this->pdo->prepare("SELECT COUNT(id) FROM {$this->table} {$where}");
        $stmtF->execute($bindParams);
        $totalFiltered = $stmtF->fetchColumn();

        $sql = "SELECT id, nome_completo, nome_comum, email, tipo_cargo, situacao 
                FROM {$this->table} {$where} ORDER BY nome_completo ASC LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sql);
        foreach ($bindParams as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total'         => $totalRecords,
            'totalFiltered' => $totalFiltered,
            'data'          => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Busca um funcionário pelo ID (sem a senha hash).
     * @param int $id O ID do funcionário.
     * @return array|false Os dados do funcionário ou false.
     */
    public function find(int $id)
    {
        $sql = "SELECT id, nome_completo, nome_comum, email, tipo_cargo, situacao, data_criacao, data_atualizacao
                FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atualiza um funcionário existente.
     */
    public function updateFuncionario(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id); // Obtém dados antigos para auditoria

        // Campos permitidos para atualização
        $fields = ['nome_completo', 'nome_comum', 'email', 'tipo_cargo', 'situacao', 'senha_hash'];
        $setClauses = [];
        $bindValues = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {

                if ($field === 'senha_hash' && empty($data[$field])) {
                    continue;
                }

                $setClauses[] = "{$field} = :{$field}";
                $bindValues[":{$field}"] = $data[$field];
            }
        }

        if (empty($setClauses)) {
            $this->pdo->rollBack();
            return false; // Nada para atualizar
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . ", data_atualizacao = NOW() WHERE id = :id";
        $bindValues[':id'] = $id;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindValues);

            // LOG DE AUDITORIA (UPDATE)
            $this->logger->log(
                'UPDATE',
                $this->table,
                $id,
                $dadosAntigos, // Dados anteriores
                $data,         // Dados atualizados
                $userId
            );

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Inativa um funcionário (Ação 'Soft-Delete').
     * @param int $id ID do funcionário.
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool Sucesso ou falha.
     */
    public function inactivate(int $id, int $userId): bool
    {
        $this->pdo->beginTransaction(); // Inicia a transação
        $dadosAntigos = $this->find($id); // OBTÉM DADOS ANTIGOS PARA AUDITORIA

        try {
            // Verifica se o funcionário já não está inativo
            if (!$dadosAntigos || $dadosAntigos['situacao'] === 'Inativo') {
                $this->pdo->rollBack();
                return false;
            }

            $sql = "UPDATE {$this->table} SET situacao = 'Inativo', data_atualizacao = NOW() WHERE id = :id AND situacao = 'Ativo'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            $success = $stmt->rowCount() > 0;

            if ($success) {
                // LOG DE AUDITORIA (INACTIVATE/DELETE)
                $this->logger->log(
                    'DELETE', // Usamos DELETE para inativação no log
                    $this->table,
                    $id,
                    $dadosAntigos,
                    ['situacao' => 'Inativo'],
                    $userId
                );
            }

            $this->pdo->commit(); // Confirma a transação
            return $success;
        } catch (PDOException $e) {
            $this->pdo->rollBack(); // Reverte a transação em caso de erro
            // O código '23000' indica restrição de chave estrangeira (FOREIGN KEY)
            if ($e->getCode() === '23000') {
                // Lançamos uma exceção customizada para o Controller
                throw new Exception("Restrição de Chave Estrangeira: Este funcionário está vinculado a Entregas, Previsões ou Auditoria.");
            }
            throw $e;
        }
    }
    /**
     * Busca funcionários por cargos específicos para uso em Select2 (Ex: Motoristas, Técnicos).
     * @param array $cargos Lista de cargos a buscar.
     * @return array Opções no formato Select2 (id, text).
     */
    public function getFuncionariosByCargos(array $cargos): array
    {
        // Gera a string de placeholders (?, ?, ?)
        $placeholders = implode(',', array_fill(0, count($cargos), '?'));

        $sql = "SELECT 
                    id, 
                    nome_completo, 
                    tipo_cargo, 
                    CONCAT(nome_completo, ' (', tipo_cargo, ')') AS text
                FROM {$this->table} 
                WHERE tipo_cargo IN ({$placeholders}) AND situacao = 'Ativo'
                ORDER BY nome_completo ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($cargos);

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
     * Retorna lista de cargos disponíveis na ENUM.
     * @return array Lista de cargos.
     */
    public function getCargosOptions(): array
    {
        // Adicionado um método auxiliar para buscar as opções de cargo
        return [
            'Administrador',
            'Gerente',
            'Encarregado',
            'Tecnico',
            'Motorista',
            'Vendedor',
            'Auxiliar de Vendas',
            'Auxiliar de Expedicao'
        ];
    }

    /**
     * Busca motoristas ativos para o select de abastecimento.
     */
    public function getMotoristasOptions(string $term = '')
    {
        // Busca apenas quem tem cargo de Motorista e está Ativo
        $sql = "SELECT id, nome_completo, nome_comum AS text
                FROM {$this->table} 
                WHERE tipo_cargo = 'Motorista' 
                AND situacao = 'Ativo'";

        $params = [];

        if (!empty($term)) {
            $sql .= " AND (nome_comum LIKE :t1 OR nome_completo LIKE :t2)";
            $params[':t1'] = "%$term%";
            $params[':t2'] = "%$term%";
        }

        $sql .= " ORDER BY nome_comum ASC LIMIT 30";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca funcionários operacionais (Técnico, Encarregado) ativos para Select2.
     * @param string $term Termo de busca (Nome ou Apelido).
     * @return array Lista formatada para Select2.
     */
    public function getFuncionariosOperacionais(string $term = ''): array
    {
        // Define quais cargos devem aparecer na lista de expedição
        // Baseado no seu SQL, os cargos relevantes são:
        $cargosPermitidos = "'Tecnico', 'Auxiliar de Expedicao'";

        $sql = "SELECT id, nome_completo, nome_comum, tipo_cargo 
                FROM {$this->table} 
                WHERE situacao = 'Ativo' 
                AND tipo_cargo IN ($cargosPermitidos)";

        $params = [];

        if (!empty($term)) {
            $sql .= " AND (nome_completo LIKE :t1 OR nome_comum LIKE :t2)";
            $params[':t1'] = "%$term%";
            $params[':t2'] = "%$term%";
        }

        $sql .= " ORDER BY nome_comum ASC LIMIT 30";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Usa o Nome Comum (Apelido) se tiver, senão usa o Completo
            $nomeExibicao = $row['nome_comum'] ?: $row['nome_completo'];

            // Retorna no formato: "João (Motorista)"
            $results[] = [
                'id' => $row['id'],
                'text' => $nomeExibicao . ' (' . $row['tipo_cargo'] . ')'
            ];
        }

        return $results;
    }
}

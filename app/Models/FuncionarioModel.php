<?php

/**
 * CLASSE MODELO: FuncionarioModel
 * Local: app/Models/FuncionarioModel.php
 * Descrição: Gerencia as operações de CRUD e autenticação na tabela FUNCIONARIOS.
 */

// Inclui a classe de conexão PDO
require_once __DIR__ . '/Database.php';

// Inclui o serviço de log para auditoria
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';

class FuncionarioModel
{
    private $pdo;
    private $table = 'FUNCIONARIOS';
    private $logger;

    public function __construct()
    {
        // Obtém a única instância da conexão PDO
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Insere um novo funcionário no banco de dados.
     * @param array $data Dados do funcionário (JÁ inclui o senha_hash).
     * @param int   $userId ID do usuário que está executando a ação (auditoria).
     * @return int|false O ID do novo funcionário ou false em caso de erro.
     */
    public function createFuncionario(array $data, int $userId = 0)
    {
        $this->pdo->beginTransaction();

        try {
            $sql = "INSERT INTO {$this->table}
                (nome_completo, email, senha_hash, tipo_cargo, situacao)
                VALUES (:nome_completo, :email, :senha_hash, :tipo_cargo, :situacao)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome_completo' => $data['nome_completo'],
                ':email'         => $data['email'],
                ':senha_hash'    => $data['senha_hash'] ?? null,
                ':tipo_cargo'    => $data['tipo_cargo'],
                ':situacao'      => $data['situacao'] ?? 'Ativo',
            ]);

            $funcionarioId = $this->pdo->lastInsertId();

            // LOG DE AUDITORIA (CREATE)
            $this->logger->log(
                'CREATE',
                $this->table,
                $funcionarioId,
                null, // dados antigos
                [
                    'nome_completo' => $data['nome_completo'],
                    'email'         => $data['email'],
                    'tipo_cargo'    => $data['tipo_cargo']
                ],
                $userId
            );

            $this->pdo->commit();
            return (int)$funcionarioId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Busca um funcionário pelo email para fins de login.
     * @param string $email O email do usuário.
     * @return array|false Dados do funcionário ou false se não encontrado.
     */
    public function getFuncionarioByEmail($email)
    {
        $sql = "SELECT id, nome_completo, email, senha_hash, tipo_cargo, situacao 
                FROM {$this->table} 
                WHERE email = :email";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':email' => $email]);

        // Retorna a linha como um array associativo
        return $stmt->fetch();
    }

    /**
     * Busca dados dos funcionários no formato DataTables (Server-Side).
     * @param array $params Parâmetros DataTables (start, length, search).
     * @return array Dados paginados e totais.
     */
    public function getForDataTable(array $params): array
    {
        // Implementação conforme o arquivo original
        $start = $params['start'];
        $length = $params['length'];
        $searchValue = $params['search'];

        $bindParams = [];
        $where = "WHERE 1=1 ";

        // 1. CONDIÇÃO DA PESQUISA GLOBAL (SEARCH)
        if (!empty($searchValue)) {
            $where .= "AND (nome_completo LIKE :search1 OR email LIKE :search2 OR tipo_cargo LIKE :search3) ";
            $bindParams[':search1'] = "%" . $searchValue . "%";
            $bindParams[':search2'] = "%" . $searchValue . "%";
            $bindParams[':search3'] = "%" . $searchValue . "%";
        }

        // 2. Contagem Total de Registros
        $sqlTotal = "SELECT COUNT(id) FROM {$this->table}";
        $stmtTotal = $this->pdo->query($sqlTotal);
        $totalRecords = $stmtTotal->fetchColumn();


        // 3. Contagem de Registros Filtrados
        $sqlFiltered = "SELECT COUNT(id) FROM {$this->table} " . $where;
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();


        // 4. Query Principal com Limite (Dados)
        $sqlData = "SELECT id, nome_completo, email, tipo_cargo, situacao FROM {$this->table} 
                    {$where} 
                    ORDER BY nome_completo ASC 
                    LIMIT :start, :length";

        $stmtData = $this->pdo->prepare($sqlData);

        // Bindar os parâmetros nomeados
        foreach ($bindParams as $key => $value) {
            $stmtData->bindValue($key, $value);
        }
        $stmtData->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmtData->bindValue(':length', (int)$length, PDO::PARAM_INT);

        $stmtData->execute();
        $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $totalRecords,
            'totalFiltered' => $totalFiltered,
            'data' => $data
        ];
    }

    /**
     * Busca um funcionário pelo ID (sem a senha hash).
     * @param int $id O ID do funcionário.
     * @return array|false Os dados do funcionário ou false.
     */
    public function find(int $id)
    {
        $sql = "SELECT id, nome_completo, email, tipo_cargo, situacao, data_criacao, data_atualizacao
                FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza um funcionário existente.
     * @param int $id ID do funcionário.
     * @param array $data Dados para atualização (pode incluir 'senha_hash').
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool Sucesso ou falha.
     */
    public function updateFuncionario(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id); // OBTÉM DADOS ANTIGOS PARA AUDITORIA

        // Constrói a query e os parâmetros dinamicamente (para incluir ou não a senha)
        $fields = ['nome_completo', 'email', 'tipo_cargo', 'situacao', 'senha_hash'];
        $setClauses = [];
        $bindValues = [];

        foreach ($fields as $field) {
            // Verifica se o campo existe nos dados passados
            if (isset($data[$field])) {
                $setClauses[] = "{$field} = :{$field}";
                $bindValues[":{$field}"] = $data[$field];
            }
        }

        if (empty($setClauses)) {
            $this->pdo->rollBack();
            return false; // Nada para atualizar
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE id = :id";
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

            $this->pdo->commit(); // Confirma a transação
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack(); // Reverte a transação em caso de erro
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
        } catch (\PDOException $e) {
            $this->pdo->rollBack(); // Reverte a transação em caso de erro
            // O código '23000' indica restrição de chave estrangeira (FOREIGN KEY)
            if ($e->getCode() === '23000') {
                // Lançamos uma exceção customizada para o Controller
                throw new \Exception("Restrição de Chave Estrangeira: Este funcionário está vinculado a Entregas, Previsões ou Auditoria.");
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
}

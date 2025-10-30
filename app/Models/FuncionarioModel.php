<?php

/**
 * CLASSE MODELO: FuncionarioModel
 * Local: app/Models/FuncionarioModel.php
 * Descrição: Gerencia as operações de CRUD e autenticação na tabela FUNCIONARIOS.
 */

// Inclui a classe de conexão PDO
require_once 'Database.php';

class FuncionarioModel
{
    private $pdo;
    private $table = 'FUNCIONARIOS';

    public function __construct()
    {
        // Obtém a única instância da conexão PDO
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Insere um novo funcionário no banco de dados.
     * @param array $data Dados do funcionário (incluindo senha_pura).
     * @return bool|string O ID do novo funcionário ou false em caso de erro.
     */
    public function createFuncionario($data)
    {
        // 1. SEGURANÇA: Cria o HASH seguro da senha
        // password_hash() é a forma segura e recomendada no PHP
        $senha_hash = password_hash($data['senha_pura'], PASSWORD_BCRYPT);

        $sql = "INSERT INTO {$this->table} (nome_completo, email, senha_hash, tipo_cargo, situacao) 
                VALUES (:nome_completo, :email, :senha_hash, :tipo_cargo, :situacao)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome_completo' => $data['nome_completo'],
                ':email'         => $data['email'],
                ':senha_hash'    => $senha_hash, // Salva o HASH, não a senha pura
                ':tipo_cargo'    => $data['tipo_cargo'],
                ':situacao'      => $data['situacao'] ?? 'Ativo'
            ]);
            // Retorna o ID do último registro inserido
            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Em caso de erro (ex: email duplicado), retorna false
            // Em um sistema real, você registraria este erro em um arquivo de log
            return false;
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
     * Busca todos os funcionários para o DataTables (exceto o próprio usuário logado para segurança).
     * @param array $params Parâmetros DataTables.
     * @return array Dados formatados.
     */
    public function findAllForDataTable(array $params): array
    {
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        $loggedInUserId = $_SESSION['user_id'] ?? 0; // Exclui o próprio usuário

        $sqlBase = "FROM {$this->table} WHERE id != :user_id";
        $conditions = [];
        $queryParams = [':user_id' => $loggedInUserId];

        if (!empty($searchValue)) {
            $conditions[] = "(nome_completo LIKE :search OR email LIKE :search)";
            $queryParams[':search'] = '%' . $searchValue . '%';
        }

        $whereClause = $sqlBase . (!empty($conditions) ? " AND " . implode(" AND ", $conditions) : "");

        // Contagem Total
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();

        // Contagem Filtrada
        $stmtFiltered = $this->pdo->prepare("SELECT COUNT(id) $whereClause");
        $stmtFiltered->execute($queryParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Dados
        $sqlData = "SELECT id, nome_completo, email, tipo_cargo, situacao 
                    $whereClause 
                    ORDER BY nome_completo ASC 
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
     * Busca um funcionário pelo ID.
     * @param int $id ID do funcionário.
     * @return array|false
     */
    public function findById(int $id)
    {
        $sql = "SELECT id, nome_completo, email, tipo_cargo, situacao FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza os dados de um funcionário.
     * @param int $id ID do funcionário.
     * @param array $data Dados do formulário (incluindo cargo, email, etc.).
     * @param string $senha_pura Nova senha (se não vazia).
     * @return bool
     */
    public function update(int $id, array $data, string $senha_pura = ''): bool
    {

        $fields = [
            'nome_completo' => $data['funcionario_nome'],
            'email' => $data['funcionario_email'],
            'tipo_cargo' => $data['funcionario_cargo'],
            'situacao' => $data['funcionario_situacao'],
        ];

        $sqlParts = [];
        $params = [':id' => $id];

        foreach ($fields as $key => $value) {
            $sqlParts[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        // Se uma nova senha foi fornecida, faz o hash e adiciona à query
        if (!empty($senha_pura)) {
            $senha_hash = password_hash($senha_pura, PASSWORD_BCRYPT);
            $sqlParts[] = "senha_hash = :senha_hash";
            $params[':senha_hash'] = $senha_hash;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sqlParts) . " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Inativa um funcionário (mudando o status).
     * @param int $id ID do funcionário.
     * @return bool
     */
    public function inactivate(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET situacao = 'Inativo', data_atualizacao = NOW() WHERE id = :id AND situacao = 'Ativo'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        // Retorna true se alguma linha foi afetada (o usuário foi inativado)
        return $stmt->rowCount() > 0;
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
}

<?php

/**
 * CLASSE MODELO: EntidadeModel
 * Local: app/Models/EntidadeModel.php
 * Descrição: Gerencia as operações de CRUD nas tabelas ENTIDADES e ENDERECOS.
 */

require_once __DIR__ . '/Database.php';
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';

class EntidadeModel
{
    private $pdo;
    private $table = 'ENTIDADES';
    private $tableEnd = 'ENDERECOS';
    private $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Busca dados para o DataTables (Server-Side).
     */
    public function findAllForDataTable(array $params): array
    {
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';
        $tipo = $params['tipo_entidade'] ?? 'cliente'; // Filtro extra

        $where = "WHERE 1=1 ";
        $bindParams = [];

        // Filtro por Tipo (Cliente, Fornecedor, Transportadora)
        // A lógica do sistema usa 'Cliente', 'Fornecedor', 'Transportadora' no banco
        if (!empty($tipo)) {
            $tipoDb = ucfirst($tipo); // cliente -> Cliente
            // Se for fornecedor, pode ser 'Fornecedor' ou 'Cliente e Fornecedor'
            if ($tipoDb === 'Fornecedor') {
                $where .= "AND (tipo = 'Fornecedor' OR tipo = 'Cliente e Fornecedor') ";
            } elseif ($tipoDb === 'Cliente') {
                $where .= "AND (tipo = 'Cliente' OR tipo = 'Cliente e Fornecedor') ";
            } else {
                $where .= "AND tipo = :tipo ";
                $bindParams[':tipo'] = $tipoDb;
            }
        }

        if (!empty($searchValue)) {
            $where .= "AND (razao_social LIKE :s1 OR nome_fantasia LIKE :s1 OR cnpj_cpf LIKE :s2 OR codigo_interno LIKE :s3) ";
            $bindParams[':s1'] = "%" . $searchValue . "%";
            $bindParams[':s2'] = "%" . $searchValue . "%";
            $bindParams[':s3'] = "%" . $searchValue . "%";
        }

        // Totais
        $sqlTotal = "SELECT COUNT(id) FROM {$this->table}";
        $totalRecords = $this->pdo->query($sqlTotal)->fetchColumn();

        $sqlFiltered = "SELECT COUNT(id) FROM {$this->table} " . $where;
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        // Dados (Faz join com endereço principal para exibir na lista)
        $sqlData = "SELECT 
                        ent.id, ent.situacao, ent.tipo, ent.razao_social, ent.nome_fantasia, ent.cnpj_cpf, ent.codigo_interno,
                        end.logradouro, end.numero, end.cidade, end.uf
                    FROM {$this->table} ent
                    LEFT JOIN {$this->tableEnd} end ON ent.id = end.entidade_id AND end.tipo_endereco = 'Principal'
                    {$where} 
                    ORDER BY ent.razao_social ASC 
                    LIMIT :start, :length";

        $stmtData = $this->pdo->prepare($sqlData);
        foreach ($bindParams as $k => $v) $stmtData->bindValue($k, $v);
        $stmtData->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmtData->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmtData->execute();

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalFiltered,
            "data" => $stmtData->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Cria uma nova entidade e seu endereço principal.
     */
    public function create(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Inserir Entidade
            $sql = "INSERT INTO {$this->table} (tipo, tipo_pessoa, razao_social, nome_fantasia, codigo_interno, cnpj_cpf, inscricao_estadual_rg, situacao)
                    VALUES (:tipo, :tipo_pessoa, :razao, :nome_fan, :codigo, :doc, :ie, :sit)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':tipo'        => $data['tipo'],
                ':tipo_pessoa' => $data['tipo_pessoa'],
                ':razao'       => $data['razao_social'],
                ':nome_fan'    => $data['nome_fantasia'] ?? null,
                ':codigo'      => $data['codigo_interno'] ?? null,
                ':doc'         => $data['cnpj_cpf'],
                ':ie'          => $data['inscricao_estadual_rg'] ?? null,
                ':sit'         => $data['situacao'] ?? 'Ativo'
            ]);

            $id = $this->pdo->lastInsertId();

            // 2. Inserir Endereço Principal
            if (!empty($data['endereco_principal'])) {
                $end = $data['endereco_principal'];
                $sqlEnd = "INSERT INTO {$this->tableEnd} (entidade_id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf)
                           VALUES (?, 'Principal', ?, ?, ?, ?, ?, ?, ?)";
                $stmtEnd = $this->pdo->prepare($sqlEnd);
                $stmtEnd->execute([
                    $id,
                    $end['cep'],
                    $end['logradouro'],
                    $end['numero'],
                    $end['complemento'] ?? null,
                    $end['bairro'],
                    $end['cidade'],
                    $end['uf']
                ]);
            }

            // Log
            $this->logger->log('CREATE', $this->table, $id, null, $data, $userId);

            $this->pdo->commit();
            return (int)$id;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza entidade e endereço principal.
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id);

        try {
            // 1. Update Entidade
            $sql = "UPDATE {$this->table} SET 
                    tipo = :tipo, tipo_pessoa = :tipo_pessoa, razao_social = :razao, 
                    nome_fantasia = :nome_fan, codigo_interno = :codigo, cnpj_cpf = :doc, 
                    inscricao_estadual_rg = :ie, situacao = :sit
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':tipo'        => $data['tipo'],
                ':tipo_pessoa' => $data['tipo_pessoa'],
                ':razao'       => $data['razao_social'],
                ':nome_fan'    => $data['nome_fantasia'] ?? null,
                ':codigo'      => $data['codigo_interno'] ?? null,
                ':doc'         => $data['cnpj_cpf'],
                ':ie'          => $data['inscricao_estadual_rg'] ?? null,
                ':sit'         => $data['situacao'],
                ':id'          => $id
            ]);

            // 2. Update ou Insert Endereço Principal
            // (Se não existir, cria. Se existir, atualiza)
            if (!empty($data['endereco_principal'])) {
                $end = $data['endereco_principal'];

                // Verifica se já existe endereço principal
                $check = $this->pdo->prepare("SELECT id FROM {$this->tableEnd} WHERE entidade_id = ? AND tipo_endereco = 'Principal'");
                $check->execute([$id]);
                $endId = $check->fetchColumn();

                if ($endId) {
                    $sqlEnd = "UPDATE {$this->tableEnd} SET cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, uf=? WHERE id=?";
                    $this->pdo->prepare($sqlEnd)->execute([
                        $end['cep'],
                        $end['logradouro'],
                        $end['numero'],
                        $end['complemento'] ?? null,
                        $end['bairro'],
                        $end['cidade'],
                        $end['uf'],
                        $endId
                    ]);
                } else {
                    $sqlEnd = "INSERT INTO {$this->tableEnd} (entidade_id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf) VALUES (?, 'Principal', ?, ?, ?, ?, ?, ?, ?)";
                    $this->pdo->prepare($sqlEnd)->execute([
                        $id,
                        $end['cep'],
                        $end['logradouro'],
                        $end['numero'],
                        $end['complemento'] ?? null,
                        $end['bairro'],
                        $end['cidade'],
                        $end['uf']
                    ]);
                }
            }

            $this->logger->log('UPDATE', $this->table, $id, $dadosAntigos, $data, $userId);
            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Deleta uma entidade (e seus endereços via ON DELETE CASCADE).
     * @param int $id O ID da entidade a ser deletada.
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool Sucesso ou falha na transação.
     */
    public function delete(int $id, int $userId)
    {
        $this->pdo->beginTransaction();

        try {
            // PASSO DE AUDITORIA 1: Busca o registro ANTES da exclusão
            $dadosAntigos = $this->find($id);

            // 1. DELETA A ENTIDADE
            $sqlEntidade = "DELETE FROM {$this->table} WHERE id = ?";
            $stmtEntidade = $this->pdo->prepare($sqlEntidade);
            $stmtEntidade->execute([$id]);

            // PASSO DE AUDITORIA 2: Log de DELETE
            if ($stmtEntidade->rowCount() > 0) {
                $this->logger->log(
                    'DELETE',
                    $this->table,
                    $id,
                    $dadosAntigos, // O que foi deletado
                    null // Dados Novos: null
                );
            }

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function find(int $id)
    {
        // Busca Entidade + Endereço Principal
        $sql = "SELECT 
                    ent.*,
                    end.cep as end_cep, end.logradouro as end_logradouro, end.numero as end_numero,
                    end.complemento as end_complemento, end.bairro as end_bairro, 
                    end.cidade as end_cidade, end.uf as end_uf
                FROM {$this->table} ent
                LEFT JOIN {$this->tableEnd} end ON ent.id = end.entidade_id AND end.tipo_endereco = 'Principal'
                WHERE ent.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os endereços de uma entidade (exceto o Principal, que está no FIND).
     * @param int $entidadeId O ID da entidade.
     * @return array Lista de endereços.
     */
    public function getEnderecosAdicionais(int $entidadeId): array
    {
        $sql = "SELECT id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf 
                FROM {$this->tableEnd} 
                WHERE entidade_id = :id AND tipo_endereco != 'Principal' 
                ORDER BY tipo_endereco";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $entidadeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um novo endereço para uma entidade existente (Usado para Endereços Adicionais).
     * @param int $entidadeId O ID da entidade.
     * @param array $endereco Dados do endereço.
     * @return int|false O ID do novo endereço ou false.
     */
    public function createEnderecoAdicional(int $entidadeId, array $endereco)
    {
        try {
            $sqlEndereco = "INSERT INTO {$this->tableEnd} 
                (entidade_id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtEndereco = $this->pdo->prepare($sqlEndereco);
            $stmtEndereco->execute([
                $entidadeId,
                $endereco['tipo_endereco'], // NOVO CAMPO REQUERIDO
                $endereco['cep'],
                $endereco['logradouro'],
                $endereco['numero'],
                $endereco['complemento'] ?? null,
                $endereco['bairro'],
                $endereco['cidade'],
                $endereco['uf']
            ]);

            // Não logamos Endereços na AUDITORIA, apenas a ENTIDADE.

            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Logar o erro
            return false;
        }
    }

    /**
     * Busca um endereço adicional pelo ID.
     * @param int $id O ID do registro de ENDERECOS.
     * @return array|false Dados do endereço.
     */
    public function findEnderecoAdicional(int $id)
    {
        $sql = "SELECT id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf 
                FROM {$this->tableEnd} 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza um endereço adicional existente.
     * @param int $id O ID do registro de ENDERECOS.
     * @param array $endereco Dados do endereço a serem atualizados.
     * @return bool Sucesso ou falha.
     */
    public function updateEnderecoAdicional(int $id, array $endereco)
    {
        $sql = "UPDATE {$this->tableEnd} SET 
            tipo_endereco = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?
            WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $endereco['tipo_endereco'],
            $endereco['cep'],
            $endereco['logradouro'],
            $endereco['numero'],
            $endereco['complemento'] ?? null,
            $endereco['bairro'],
            $endereco['cidade'],
            $endereco['uf'],
            $id
        ]);
    }

    /**
     * Deleta um endereço adicional.
     * @param int $id O ID do registro de ENDERECOS.
     * @return bool Sucesso ou falha.
     */
    public function deleteEnderecoAdicional(int $id)
    {
        $sql = "DELETE FROM {$this->tableEnd} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Busca Entidades ativas para Select2 (Proprietários).
     * @param string $term Termo de busca (CNPJ/Nome).
     * @return array Lista de entidades [id, text].
     */
    public function getEntidadesOptions(string $term = ''): array
    {
        $sql = "SELECT 
                    id, 
                    razao_social, 
                    nome_fantasia
                FROM {$this->table} 
                WHERE situacao = 'Ativo'";

        $params = [];

        if (!empty($term)) {
            $sql .= " AND (razao_social LIKE :term OR nome_fantasia LIKE :term OR cnpj_cpf LIKE :term)";
            $params[':term'] = '%' . $term . '%';
        }

        $sql .= " ORDER BY razao_social ASC LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['nome_fantasia'] ?: $row['razao_social'];
            $results[] = [
                'id' => $row['id'],
                'text' => $name
            ];
        }
        return $results;
    }
}

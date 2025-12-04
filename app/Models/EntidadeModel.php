<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use PDOException;

/**
 * Class EntidadeModel
 * Gerencia Clientes, Fornecedores e Transportadoras.
 * @package App\Models
 */
class EntidadeModel
{
    protected PDO $pdo;
    protected AuditLoggerService $logger;
    protected string $table = 'ENTIDADES';
    protected string $tableEnd = 'ENDERECOS';

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
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $searchValue = $params['search']['value'] ?? '';
        $tipo = $params['tipo_entidade'] ?? 'cliente';

        $columnsMap = [
            0 => 'ent.situacao',
            1 => 'ent.tipo',
            2 => 'ent.codigo_interno',
            3 => 'ent.razao_social',
            4 => 'ent.nome_fantasia',
            5 => 'ent.cnpj_cpf',
            6 => 'ent.id'
        ];

        $where = "WHERE 1=1 ";
        $bindParams = [];

        if (!empty($tipo)) {
            $tipoDb = ucfirst($tipo);
            if ($tipoDb === 'Fornecedor' || $tipoDb === 'Cliente') {
                $where .= "AND (tipo = :tipoExact OR tipo = 'Cliente e Fornecedor') ";
                $bindParams[':tipoExact'] = $tipoDb;
            } else {
                $where .= "AND tipo = :tipo ";
                $bindParams[':tipo'] = $tipoDb;
            }
        }

        if (!empty($searchValue)) {
            $where .= "AND (razao_social LIKE :s1 OR nome_fantasia LIKE :s2 OR cnpj_cpf LIKE :s3 OR codigo_interno LIKE :s4) ";
            $term = "%{$searchValue}%";
            $bindParams[':s1'] = $term;
            $bindParams[':s2'] = $term;
            $bindParams[':s3'] = $term;
            $bindParams[':s4'] = $term;
        }

        $orderBy = "ORDER BY ent.razao_social ASC";
        if (!empty($params['order'])) {
            $colIndex = (int)$params['order'][0]['column'];
            $colDir = strtoupper($params['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
            if (isset($columnsMap[$colIndex])) {
                $orderBy = "ORDER BY {$columnsMap[$colIndex]} {$colDir}";
            }
        }

        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();

        $sqlFiltered = "SELECT COUNT(ent.id) FROM {$this->table} ent {$where}";
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();

        $sqlData = "SELECT 
                        ent.id, ent.situacao, ent.tipo, ent.razao_social, ent.nome_fantasia, ent.cnpj_cpf, ent.codigo_interno,
                        end.logradouro, end.numero, end.cidade, end.uf
                    FROM {$this->table} ent
                    LEFT JOIN {$this->tableEnd} end ON ent.id = end.entidade_id AND end.tipo_endereco = 'Principal'
                    {$where} {$orderBy} LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sqlData);
        foreach ($bindParams as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatação
        foreach ($data as &$row) {
            if (function_exists('format_documento')) {
                $row['cnpj_cpf'] = format_documento($row['cnpj_cpf']);
            }
        }

        return [
            "draw" => (int)($params['draw'] ?? 1),
            "recordsTotal" => (int)$totalRecords,
            "recordsFiltered" => (int)$totalFiltered,
            "data" => $data
        ];
    }

    public function create(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO {$this->table} 
                    (tipo, tipo_pessoa, razao_social, nome_fantasia, codigo_interno, cnpj_cpf, inscricao_estadual_rg, situacao)
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

            $id = (int)$this->pdo->lastInsertId();

            if (!empty($data['endereco_principal'])) {
                $this->saveAddress($id, $data['endereco_principal']);
            }

            $this->logger->log('CREATE', $this->table, $id, null, $data, $userId);
            $this->pdo->commit();
            return $id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id);

        try {
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

            if (!empty($data['endereco_principal'])) {
                $this->saveAddress($id, $data['endereco_principal']);
            }

            $this->logger->log('UPDATE', $this->table, $id, $dadosAntigos, $data, $userId);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Helper privado para salvar/atualizar endereço principal (DRY).
     */
    private function saveAddress(int $entidadeId, array $end): void
    {
        $check = $this->pdo->prepare("SELECT id FROM {$this->tableEnd} WHERE entidade_id = ? AND tipo_endereco = 'Principal'");
        $check->execute([$entidadeId]);
        $endId = $check->fetchColumn();

        $params = [
            $end['cep'],
            $end['logradouro'],
            $end['numero'],
            $end['complemento'] ?? null,
            $end['bairro'],
            $end['cidade'],
            $end['uf']
        ];

        if ($endId) {
            $sql = "UPDATE {$this->tableEnd} SET cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, uf=? WHERE id=?";
            $params[] = $endId;
        } else {
            $sql = "INSERT INTO {$this->tableEnd} (entidade_id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf) 
                    VALUES (?, 'Principal', ?, ?, ?, ?, ?, ?, ?)";
            array_unshift($params, $entidadeId);
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id, int $userId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $dadosAntigos = $this->find($id);
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                $this->logger->log('DELETE', $this->table, $id, $dadosAntigos, null, $userId);
            }

            $this->pdo->commit();
            return $success;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function find(int $id)
    {
        $sql = "SELECT ent.*,
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
                WHERE entidade_id = :id 
                ORDER BY FIELD (tipo_endereco, 'Principal') DESC, tipo_endereco ASC";

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
                VALUES (:entidadeId, :tipoEndereco, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf)";

            $stmtEndereco = $this->pdo->prepare($sqlEndereco);
            $stmtEndereco->execute([
                $entidadeId,
                $endereco['tipo_endereco'],
                $endereco['cep'],
                $endereco['logradouro'],
                $endereco['numero'],
                $endereco['complemento'] ?? null,
                $endereco['bairro'],
                $endereco['cidade'],
                $endereco['uf']
            ]);

            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
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
                tipo_endereco = :tipoEndereco, 
                cep = :cep, 
                logradouro = :logradouro, 
                numero = :numero, 
                complemento = :complemento, 
                bairro = :bairro, 
                cidade = :cidade, 
                uf = :uf
            WHERE id = :id";

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

    /**
     * Busca APENAS Clientes ativos para Select2 .
     * @param string $term Termo de busca (CNPJ/Nome).
     * @return array Lista formatada.
     */
    public function getClienteOptions(string $term = ''): array
    {
        // Query base: Filtra Ativos E (Tipo Cliente)
        $sql = "SELECT 
                    id, razao_social, nome_fantasia, cnpj_cpf
                FROM {$this->table} 
                WHERE situacao = 'Ativo'
                AND tipo = 'Cliente'";

        $params = [];

        if (!empty($term)) {
            // Remove caracteres não numéricos caso o usuário cole um CNPJ formatado
            $termLike = '%' . $term . '%';

            // Início do grupo OR
            $whereClause = "(razao_social LIKE :p1 OR nome_fantasia LIKE :p2";
            $params[':p1'] = $termLike;
            $params[':p2'] = $termLike;

            // Só adiciona busca por CNPJ se tiver números na pesquisa
            $termClean = preg_replace('/\D/', '', $term);
            if (!empty($termClean)) {
                $whereClause .= " OR cnpj_cpf LIKE :p3";
                $params[':p3'] = '%' . $termClean . '%';
            }

            $whereClause .= ")"; // Fecha o grupo OR
            $sql .= " AND " . $whereClause;
        }

        // Ordena por Nome Fantasia (geralmente como chamamos os postos)
        $sql .= " ORDER BY nome_fantasia ASC LIMIT 30";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['nome_fantasia'] ?: $row['razao_social'];
                $doc = $row['cnpj_cpf'];

                // Formatação visual do CNPJ
                $displayText = $name;
                if ($doc) {
                    if (strlen($doc) === 14) {
                        $docMask = preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $doc);
                        $displayText .= " ({$docMask})";
                    } else {
                        $displayText .= " ({$doc})";
                    }
                }
                $results[] = ['id' => $row['id'], 'text' => $displayText];
            }
            return $results;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Busca APENAS Fornecedores ativos para Select2
     * @param string $term Termo de busca (CNPJ/Nome)
     * @return array Lista formatada
     */
    public function getFornecedoresOptions(string $term = ''): array
    {
        $sql = "SELECT 
                    id, razao_social, nome_fantasia, cnpj_cpf
                FROM {$this->table} 
                WHERE situacao = 'Ativo'
                AND tipo = 'Fornecedor'";

        $params = [];

        if (!empty($term)) {
            // Remove caracteres não numéricos caso o usuário cole um CNPJ formatado
            $termLike = '%' . $term . '%';

            // Início do grupo OR
            $whereClause = "(razao_social LIKE :p1 OR nome_fantasia LIKE :p2";
            $params[':p1'] = $termLike;
            $params[':p2'] = $termLike;

            // Só adiciona busca por CNPJ se tiver números na pesquisa
            $termClean = preg_replace('/\D/', '', $term);
            if (!empty($termClean)) {
                $whereClause .= " OR cnpj_cpf LIKE :p3";
                $params[':p3'] = '%' . $termClean . '%';
            }

            $whereClause .= ")"; // Fecha o grupo OR
            $sql .= " AND " . $whereClause;
        }

        $sql .= " ORDER BY nome_fantasia ASC LIMIT 30";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['nome_fantasia'] ?: $row['razao_social'];
                $doc = $row['cnpj_cpf'];

                // Formatação visual do CNPJ
                $displayText = $name;
                if ($doc) {
                    if (strlen($doc) === 14) {
                        $docMask = preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $doc);
                        $displayText .= " ({$docMask})";
                    } else {
                        $displayText .= " ({$doc})";
                    }
                }
                $results[] = ['id' => $row['id'], 'text' => $displayText];
            }
            return $results;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Busca APENAS Fornecedores ativos para Select2 (Transportadoras).
     * @param string $term Termo de busca (CNPJ/Nome).
     * @return array Lista formatada.
     */
    public function getTransportadorasOptions(string $term = ''): array
    {
        // Query base: Filtra Ativos E Tipo Transportadoras
        $sql = "SELECT 
                    id, 
                    razao_social, 
                    nome_fantasia,
                    cnpj_cpf
                FROM {$this->table} 
                WHERE situacao = 'Ativo'
                AND tipo = 'Transportadora'"; //Filtro Específico

        $params = [];

        if (!empty($term)) {
            // Remove caracteres não numéricos caso o usuário cole um CNPJ formatado
            $termClean = preg_replace('/\D/', '', $term);

            $sql .= " AND (razao_social LIKE :term OR nome_fantasia LIKE :term OR cnpj_cpf LIKE :term OR cnpj_cpf LIKE :termClean)";
            $params[':term'] = '%' . $term . '%';
            $params[':termClean'] = '%' . $termClean . '%';
        }

        // Ordena por Nome Fantasia (geralmente como chamamos os postos)
        $sql .= " ORDER BY nome_fantasia ASC LIMIT 30";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['nome_fantasia'] ?: $row['razao_social'];

            // Formatação do CNPJ para exibição visual 
            $doc = $row['cnpj_cpf'];

            // Formatação visual"
            $displayText = $name;
            if ($doc) {
                // Aplica máscara visual rápida se for CNPJ (14 dígitos)
                if (strlen($doc) === 14) {
                    $docMask = preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $doc);
                    $displayText .= " ({$docMask})";
                } else {
                    $displayText .= " ({$doc})";
                }
            }

            $results[] = [
                'id' => $row['id'],
                'text' => $displayText
            ];
        }
        return $results;
    }

    public function findByDocumentOrCode($valor)
    {
        $sql = "SELECT id, razao_social, nome_fantasia FROM ENTIDADES 
            WHERE cnpj_cpf = :valor OR codigo_interno = :valor LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':valor' => preg_replace('/\D/', '', $valor)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNextCodigoInterno(): int
    {
        // Como agora é INT, não precisa mais de CAST
        $sql = "SELECT MAX(codigo_interno) as max_code FROM {$this->table}";
        $stmt = $this->pdo->query($sql);
        $max = $stmt->fetchColumn();

        return $max ? (int)$max + 1 : 1;
    }
}

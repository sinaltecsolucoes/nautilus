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
    protected string $table = 'entidades';
    protected string $tableEnd = 'enderecos'; 

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Busca dados para o DataTables (Server-Side).
     */
    public function findAllForDataTable(array $params): array
    {
        $start       = (int)($params['start'] ?? 0);
        $length      = (int)($params['length'] ?? 10);
        $searchValue = $params['search']['value'] ?? '';
        $tipo        = $params['tipo_entidade'] ?? 'cliente';

        $columnsMap = [
            0 => 'ent.situacao',
            1 => 'ent.tipo',
            2 => 'ent.codigo_interno',
            3 => 'ent.razao_social',
            4 => 'ent.nome_fantasia',
            5 => 'ent.cnpj_cpf',
            6 => 'ent.id'
        ];

        $where      = "WHERE 1=1 ";
        $bindParams = [];

        if (!empty($tipo)) {
            $tipoDb = ucfirst($tipo);
            if (in_array($tipoDb, ['Fornecedor', 'Cliente'])) {
                $where .= "AND tipo = :tipoExact ";
                $bindParams[':tipoExact'] = $tipoDb;
            } else {
                $where .= "AND tipo = :tipo ";
                $bindParams[':tipo'] = $tipoDb;
            }
        }

        if (!empty($searchValue)) {
            $where .= "AND (razao_social LIKE :s1 
                            OR nome_fantasia LIKE :s2 
                            OR cnpj_cpf LIKE :s3 
                            OR codigo_interno LIKE :s4) ";
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

        $totalRecords = (int)$this->pdo->query("SELECT COUNT(id) FROM {$this->table}")->fetchColumn();

        $sqlFiltered  = "SELECT COUNT(ent.id) FROM {$this->table} ent {$where}";
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($bindParams);
        $totalFiltered = (int)$stmtFiltered->fetchColumn();

        $sqlData = "SELECT 
                        ent.id, ent.situacao, ent.tipo, ent.razao_social, 
                        ent.nome_fantasia, ent.cnpj_cpf, ent.codigo_interno, 
                        end.logradouro, end.numero, end.cidade, end.uf
                    FROM {$this->table} ent
                    LEFT JOIN {$this->tableEnd} end 
                        ON ent.id = end.entidade_id 
                        AND end.tipo_endereco = 'Principal'
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
            "draw"            => (int)($params['draw'] ?? 1),
            "recordsTotal"    => $totalRecords,
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        ];
    }

    /**
     * Cria uma nova entidade.
     */
    public function create(array $data, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO {$this->table} 
                    (tipo, tipo_pessoa, razao_social, nome_fantasia, 
                    codigo_interno, cnpj_cpf, inscricao_estadual_rg, situacao)
                    VALUES (:tipo, :tipo_pessoa, :razao, :nome_fan, :codigo, :doc, :ie, :sit)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':tipo'        => $data['tipo'],
                ':tipo_pessoa' => $data['tipo_pessoa'],
                ':razao'       => $data['razao_social'],
                ':nome_fan'    => $data['nome_fantasia'] ?? null,
                ':codigo'      => $data['codigo_interno'] ?? null,
                ':doc'         => preg_replace('/\D/', '', $data['cnpj_cpf'] ?? ''), // sanitiza CNPJ/CPF
                ':ie'          => $data['inscricao_estadual_rg'] ?? null,
                ':sit'         => $data['situacao'] ?? 'Ativo'
            ]);

            $id = (int)$this->pdo->lastInsertId();

            if (!empty($data['endereco_principal'])) {
                $this->saveAddress($id, $data['endereco_principal']);
            }

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
     * Atualiza uma entidade existente.
     */
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
                ':tipo'        => $data['tipo'] ?? $dadosAntigos['tipo'],
                ':tipo_pessoa' => $data['tipo_pessoa'] ?? $dadosAntigos['tipo_pessoa'],
                ':razao'       => $data['razao_social'] ?? $dadosAntigos['razao_social'],
                ':nome_fan'    => $data['nome_fantasia'] ?? $dadosAntigos['nome_fantasia'],
                ':codigo'      => $data['codigo_interno'] ?? $dadosAntigos['codigo_interno'],
                ':doc'         => preg_replace('/\D/', '', $data['cnpj_cpf'] ?? $dadosAntigos['cnpj_cpf']),
                ':ie'          => $data['inscricao_estadual_rg'] ?? $dadosAntigos['inscricao_estadual_rg'],
                ':sit'         => $data['situacao'] ?? $dadosAntigos['situacao'],
                ':id'          => $id
            ]);

            if (!empty($data['endereco_principal'])) {
                $this->saveAddress($id, $data['endereco_principal']);
            }

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
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("Erro ao atualizar entidade {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function delete(int $id, int $userId): bool
    {
        $this->pdo->beginTransaction();

        try {
            $dadosAntigos = $this->find($id);

            if (!$dadosAntigos) {
                // Se não encontrou, não há o que deletar
                $this->pdo->rollBack();
                return false;
            }

            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $success = $stmt->execute([':id' => $id]);

            if ($success) {
                $this->logger->log(
                    'DELETE',
                    $this->table,
                    $id,
                    $dadosAntigos,
                    null,
                    $userId
                );
            }

            $this->pdo->commit();
            return $success;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("Erro ao deletar entidade {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function find(int $id)
    {
        try {
            $sql = "SELECT ent.*,
                       end.cep as end_cep, end.logradouro as end_logradouro, end.numero as end_numero,
                       end.complemento as end_complemento, end.bairro as end_bairro, 
                       end.cidade as end_cidade, end.uf as end_uf
                FROM {$this->table} ent
                LEFT JOIN {$this->tableEnd} end ON ent.id = end.entidade_id AND end.tipo_endereco = 'Principal'
                WHERE ent.id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar entidade {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper privado para salvar/atualizar endereço principal (DRY).
     */
    private function saveAddress(int $entidadeId, array $end): void
    {
        try {
            $check = $this->pdo->prepare("SELECT id FROM {$this->tableEnd} WHERE entidade_id = :id AND tipo_endereco = 'Principal'");
            $check->execute([':id' => $entidadeId]);
            $endId = $check->fetchColumn();

            $params = [
                ':cep'        => $end['cep'] ?? null,
                ':logradouro' => $end['logradouro'] ?? null,
                ':numero'     => $end['numero'] ?? null,
                ':complemento' => $end['complemento'] ?? null,
                ':bairro'     => $end['bairro'] ?? null,
                ':cidade'     => $end['cidade'] ?? null,
                ':uf'         => $end['uf'] ?? null,
            ];

            if ($endId) {
                $sql = "UPDATE {$this->tableEnd} 
                    SET cep=:cep, logradouro=:logradouro, numero=:numero, complemento=:complemento, 
                        bairro=:bairro, cidade=:cidade, uf=:uf 
                    WHERE id=:id";
                $params[':id'] = $endId;
            } else {
                $sql = "INSERT INTO {$this->tableEnd} 
                    (entidade_id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf) 
                    VALUES (:entidadeId, 'Principal', :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf)";
                $params[':entidadeId'] = $entidadeId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\Throwable $e) {
            error_log("Erro ao salvar endereço principal da entidade {$entidadeId}: " . $e->getMessage());
            throw $e;
        }
    }


    /**
     * Busca todos os endereços adicionais de uma entidade.
     */
    public function getEnderecosAdicionais(int $entidadeId): array
    {
        try {
            $sql = "SELECT id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf 
                FROM {$this->tableEnd} 
                WHERE entidade_id = :id AND tipo_endereco <> 'Principal'
                ORDER BY tipo_endereco ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $entidadeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log("Erro ao buscar endereços adicionais da entidade {$entidadeId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cria um novo endereço adicional.
     */
    public function createEnderecoAdicional(int $entidadeId, array $endereco): ?int
    {
        try {
            $sql = "INSERT INTO {$this->tableEnd} 
                (entidade_id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf)
                VALUES (:entidadeId, :tipoEndereco, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':entidadeId'   => $entidadeId,
                ':tipoEndereco' => $endereco['tipo_endereco'] ?? 'Adicional',
                ':cep'          => $endereco['cep'] ?? null,
                ':logradouro'   => $endereco['logradouro'] ?? null,
                ':numero'       => $endereco['numero'] ?? null,
                ':complemento'  => $endereco['complemento'] ?? null,
                ':bairro'       => $endereco['bairro'] ?? null,
                ':cidade'       => $endereco['cidade'] ?? null,
                ':uf'           => $endereco['uf'] ?? null,
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log("Erro ao criar endereço adicional da entidade {$entidadeId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca um endereço adicional pelo ID.
     */
    public function findEnderecoAdicional(int $id): ?array
    {
        try {
            $sql = "SELECT id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf 
                FROM {$this->tableEnd} 
                WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar endereço adicional {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Atualiza um endereço adicional existente.
     */
    public function updateEnderecoAdicional(int $id, array $endereco): bool
    {
        try {
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
                ':tipoEndereco' => $endereco['tipo_endereco'] ?? null,
                ':cep'          => $endereco['cep'] ?? null,
                ':logradouro'   => $endereco['logradouro'] ?? null,
                ':numero'       => $endereco['numero'] ?? null,
                ':complemento'  => $endereco['complemento'] ?? null,
                ':bairro'       => $endereco['bairro'] ?? null,
                ':cidade'       => $endereco['cidade'] ?? null,
                ':uf'           => $endereco['uf'] ?? null,
                ':id'           => $id
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao atualizar endereço adicional {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deleta um endereço adicional.
     */
    public function deleteEnderecoAdicional(int $id): bool
    {
        try {
            $sql = "DELETE FROM {$this->tableEnd} WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':id' => $id]);
        } catch (\Throwable $e) {
            error_log("Erro ao deletar endereço adicional {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca Entidades ativas para Select2 (Proprietários).
     */
    public function getEntidadesOptions(string $term = ''): array
    {
        try {
            $sql = "SELECT id, razao_social, nome_fantasia
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
                    'id'   => $row['id'],
                    'text' => $name
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar entidades para Select2: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca APENAS Clientes ativos para Select2.
     */
    public function getClienteOptions(string $term = ''): array
    {
        try {
            $sql = "SELECT id, razao_social, nome_fantasia, cnpj_cpf
                FROM {$this->table} 
                WHERE situacao = 'Ativo'
                  AND tipo = 'Cliente'";

            $params = [];

            if (!empty($term)) {
                $termLike  = '%' . $term . '%';
                $termClean = preg_replace('/\D/', '', $term);

                $whereClause = "(razao_social LIKE :p1 OR nome_fantasia LIKE :p2";
                $params[':p1'] = $termLike;
                $params[':p2'] = $termLike;

                if (!empty($termClean)) {
                    $whereClause .= " OR cnpj_cpf LIKE :p3";
                    $params[':p3'] = '%' . $termClean . '%';
                }

                $whereClause .= ")";
                $sql .= " AND " . $whereClause;
            }

            $sql .= " ORDER BY nome_fantasia ASC LIMIT 30";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['nome_fantasia'] ?: $row['razao_social'];
                $doc  = $row['cnpj_cpf'];

                // Usa helper para formatação de documento
                if (function_exists('format_documento') && $doc) {
                    $doc = format_documento($doc);
                }

                $displayText = $doc ? "{$name} ({$doc})" : $name;

                $results[] = [
                    'id'   => $row['id'],
                    'text' => $displayText
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar clientes para Select2: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca APENAS Fornecedores ativos para Select2.
     */
    public function getFornecedoresOptions(string $term = ''): array
    {
        try {
            $sql = "SELECT id, razao_social, nome_fantasia, cnpj_cpf
                FROM {$this->table} 
                WHERE situacao = 'Ativo'
                  AND tipo = 'Fornecedor'";

            $params = [];

            if (!empty($term)) {
                $termLike  = '%' . $term . '%';
                $termClean = preg_replace('/\D/', '', $term);

                $whereClause = "(razao_social LIKE :p1 OR nome_fantasia LIKE :p2";
                $params[':p1'] = $termLike;
                $params[':p2'] = $termLike;

                if (!empty($termClean)) {
                    $whereClause .= " OR cnpj_cpf LIKE :p3";
                    $params[':p3'] = '%' . $termClean . '%';
                }

                $whereClause .= ")";
                $sql .= " AND " . $whereClause;
            }

            $sql .= " ORDER BY nome_fantasia ASC LIMIT 30";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['nome_fantasia'] ?: $row['razao_social'];
                $doc  = $row['cnpj_cpf'];

                if (function_exists('format_documento') && $doc) {
                    $doc = format_documento($doc);
                }

                $displayText = $doc ? "{$name} ({$doc})" : $name;
                $results[]   = ['id' => $row['id'], 'text' => $displayText];
            }

            return $results;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar fornecedores para Select2: " . $e->getMessage());
            return [];
        }
    }


    /**
     * Busca APENAS Transportadoras ativas para Select2.
     */
    public function getTransportadorasOptions(string $term = ''): array
    {
        try {
            $sql = "SELECT id, razao_social, nome_fantasia, cnpj_cpf
                FROM {$this->table} 
                WHERE situacao = 'Ativo'
                  AND tipo = 'Transportadora'";

            $params = [];

            if (!empty($term)) {
                $termLike  = '%' . $term . '%';
                $termClean = preg_replace('/\D/', '', $term);

                $sql .= " AND (razao_social LIKE :term OR nome_fantasia LIKE :term OR cnpj_cpf LIKE :term OR cnpj_cpf LIKE :termClean)";
                $params[':term']      = $termLike;
                $params[':termClean'] = '%' . $termClean . '%';
            }

            $sql .= " ORDER BY nome_fantasia ASC LIMIT 30";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['nome_fantasia'] ?: $row['razao_social'];
                $doc  = $row['cnpj_cpf'];

                if (function_exists('format_documento') && $doc) {
                    $doc = format_documento($doc);
                }

                $displayText = $doc ? "{$name} ({$doc})" : $name;
                $results[]   = ['id' => $row['id'], 'text' => $displayText];
            }

            return $results;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar transportadoras para Select2: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca entidade por documento ou código interno.
     */
    public function findByDocumentOrCode(string $valor): ?array
    {
        try {
            $sql = "SELECT id, razao_social, nome_fantasia 
                FROM {$this->table} 
                WHERE cnpj_cpf = :valor OR codigo_interno = :valor 
                LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':valor' => preg_replace('/\D/', '', $valor)]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Throwable $e) {
            error_log("Erro ao buscar entidade por documento/código: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retorna o próximo código interno sequencial.
     */
    public function getNextCodigoInterno(): int
    {
        try {
            $sql  = "SELECT MAX(codigo_interno) as max_code FROM {$this->table}";
            $stmt = $this->pdo->query($sql);
            $max  = $stmt->fetchColumn();

            return $max ? (int)$max + 1 : 1;
        } catch (\Throwable $e) {
            error_log("Erro ao calcular próximo código interno: " . $e->getMessage());
            return 1;
        }
    }
}

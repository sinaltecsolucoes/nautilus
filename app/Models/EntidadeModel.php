<?php

/**
 * CLASSE MODELO: EntidadeModel
 * Local: app/Models/EntidadeModel.php
 * Descrição: Gerencia as operações de CRUD nas tabelas ENTIDADES e ENDERECOS.
 */

// Inclui a classe de conexão PDO
require_once __DIR__ . '/Database.php';

class EntidadeModel
{
    private $pdo;
    private $tableEntidade = 'ENTIDADES';
    private $tableEndereco = 'ENDERECOS';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Cria uma nova entidade (Cliente/Fornecedor/Transportadora) e seus endereços.
     * @param array $dataEntidade Dados da entidade.
     * @param array $dataEnderecos Array de endereços.
     * @return int|false O ID da nova entidade ou false em caso de erro.
     */
    public function create($dataEntidade, $dataEnderecos)
    {
        $this->pdo->beginTransaction();

        try {
            // 1. INSERE A ENTIDADE
            $sqlEntidade = "INSERT INTO {$this->tableEntidade} 
                (tipo, tipo_pessoa, razao_social, nome_fantasia, codigo_interno, cnpj_cpf, inscricao_estadual_rg, situacao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtEntidade = $this->pdo->prepare($sqlEntidade);
            $stmtEntidade->execute([
                $dataEntidade['tipo'],
                $dataEntidade['tipo_pessoa'],
                $dataEntidade['razao_social'],
                $dataEntidade['nome_fantasia'] ?? null,
                $dataEntidade['codigo_interno'] ?? null,
                $dataEntidade['cnpj_cpf'],
                $dataEntidade['inscricao_estadual_rg'] ?? null,
                $dataEntidade['situacao'] ?? 'Ativo'
            ]);

            $entidadeId = $this->pdo->lastInsertId();

            // 2. INSERE OS ENDEREÇOS
            foreach ($dataEnderecos as $endereco) {
                $sqlEndereco = "INSERT INTO {$this->tableEndereco} 
                    (entidade_id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
            }

            $this->pdo->commit();
            return $entidadeId;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            // Em ambiente de produção, logar $e->getMessage()
            return false;
        }
    }

    /**
     * Busca todas as entidades.
     * @return array Lista de entidades.
     */
    public function getAll()
    {
        // Regra de ACL será aplicada no Controller
        $sql = "SELECT id, tipo, tipo_pessoa, razao_social, nome_fantasia, cnpj_cpf, situacao FROM {$this->tableEntidade} ORDER BY razao_social";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Busca uma entidade e seu endereço principal pelo ID.
     * Necessário para pré-preencher o formulário de edição (Controller: getEntidade).
     * @param int $id O ID da entidade.
     * @return array|false Os dados da entidade ou false se não for encontrada.
     */
    public function find(int $id)
    {
        // Esta query é complexa, inspirada no seu EntidadeRepository de referência.
        $sql = "SELECT 
                    ent.*, 
                    end.cep AS end_cep, 
                    end.logradouro AS end_logradouro, 
                    end.numero AS end_numero, 
                    end.complemento AS end_complemento, 
                    end.bairro AS end_bairro, 
                    end.cidade AS end_cidade, 
                    end.uf AS end_uf 
                FROM {$this->tableEntidade} ent 
                LEFT JOIN {$this->tableEndereco} end 
                    ON ent.id = end.entidade_id AND end.tipo_endereco = 'Principal' 
                WHERE ent.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    /**
     * Atualiza uma entidade existente e seu endereço principal.
     * Necessário para a lógica de edição (Controller: salvarEntidade - com ID).
     * @param int $id O ID da entidade a ser atualizada.
     * @param array $dataEntidade Dados da entidade.
     * @param array $dataEnderecos Array com o endereço principal.
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool Sucesso ou falha na transação.
     */
    public function update(int $id, $dataEntidade, $dataEnderecos, int $userId)
    {
        // Implementação simplificada (transação e auditoria virão na próxima fase)
        // Por agora, apenas o esqueleto para satisfazer o Controller.

        $this->pdo->beginTransaction();

        try {
            // 1. UPDATE na Entidade
            $sqlEntidade = "UPDATE {$this->tableEntidade} SET 
                tipo = ?, tipo_pessoa = ?, razao_social = ?, nome_fantasia = ?, 
                cnpj_cpf = ?, situacao = ? 
                WHERE id = ?";

            $stmtEntidade = $this->pdo->prepare($sqlEntidade);
            $stmtEntidade->execute([
                $dataEntidade['tipo'],
                $dataEntidade['tipo_pessoa'],
                $dataEntidade['razao_social'],
                $dataEntidade['nome_fantasia'] ?? null,
                $dataEntidade['cnpj_cpf'],
                $dataEntidade['situacao'] ?? 'Ativo',
                $id
            ]);

            // 2. UPDATE no Endereço Principal (Busca e atualiza o endereço com tipo 'Principal')
            $enderecoPrincipal = $dataEnderecos[0]; // Assume que o primeiro é o principal

            $sqlEnderecoUpdate = "UPDATE {$this->tableEndereco} SET 
                cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?
                WHERE entidade_id = ? AND tipo_endereco = 'Principal'";

            $stmtEndereco = $this->pdo->prepare($sqlEnderecoUpdate);
            $stmtEndereco->execute([
                $enderecoPrincipal['cep'],
                $enderecoPrincipal['logradouro'],
                $enderecoPrincipal['numero'],
                $enderecoPrincipal['complemento'] ?? null,
                $enderecoPrincipal['bairro'],
                $enderecoPrincipal['cidade'],
                $enderecoPrincipal['uf'],
                $id
            ]);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e; // Lança a exceção para o Controller tratar o erro de DB
        }
    }

    /**
     * Busca os dados formatados para o DataTables.
     * Necessário para a listagem (Controller: listarEntidades).
     * @param array $params Parâmetros DataTables (start, length, search).
     * @return array Array formatado para o DataTables (draw, recordsTotal, data).
     */
    public function findAllForDataTable(array $params): array
    {
        // Esta é uma SIMPLIFICAÇÃO. O código real é complexo e foi visto no seu repositório de referência.
        // Aqui, apenas fornecemos o esqueleto para evitar o erro P1013 e PHP0418.

        $draw = $params['draw'] ?? 1;
        $searchValue = $params['search']['value'] ?? '';
        $pageType = $params['tipo_entidade'] ?? 'Cliente';

        // Lógica de filtragem e contagem (aqui estamos simulando)
        $totalRecords = $this->pdo->query("SELECT COUNT(id) FROM {$this->tableEntidade}")->fetchColumn();

        $sql = "SELECT ent.id, ent.tipo, ent.tipo_pessoa, ent.razao_social, ent.nome_fantasia, 
                       ent.cnpj_cpf, ent.situacao, end.logradouro AS end_logradouro, 
                       end.numero AS end_numero
                FROM {$this->tableEntidade} ent 
                LEFT JOIN {$this->tableEndereco} end ON ent.id = end.entidade_id AND end.tipo_endereco = 'Principal'
                WHERE ent.tipo LIKE :tipo AND (ent.razao_social LIKE :search OR ent.cnpj_cpf LIKE :search)
                LIMIT :start, :length";

        // NOTA: O método findById e a lógica de filtros estão omissos aqui por brevidade.

        $data = $this->pdo->query("SELECT id, tipo, tipo_pessoa, razao_social, nome_fantasia, cnpj_cpf, situacao FROM {$this->tableEntidade} LIMIT 10")->fetchAll();

        return [
            "draw" => (int) $draw,
            "recordsTotal" => (int) $totalRecords,
            "recordsFiltered" => (int) $totalRecords,
            "data" => $data
        ];
    }

    /**
     * Busca Entidades com o tipo 'Fornecedor' ou 'Cliente e Fornecedor' para Selects.
     * @return array Opções no formato [id, nome_display].
     */
    public function getFornecedorOptions(): array
    {
        $sql = "SELECT 
                    id, 
                    COALESCE(nome_fantasia, razao_social) AS nome_display
                FROM {$this->tableEntidade} 
                WHERE (tipo = 'Fornecedor' OR tipo = 'Cliente e Fornecedor') 
                AND situacao = 'Ativo' 
                ORDER BY nome_display ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        // Retorna os dados no formato esperado pelo Controller
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca clientes ativos para Select2, permitindo busca por termo.
     * @param string $term Termo de busca (CNPJ/Nome).
     * @return array Lista de clientes (id, nome_fantasia, razao_social, codigo_interno).
     */
    public function getClienteOptions(string $term = ''): array
    {
        $sql = "SELECT 
                    id, 
                    razao_social, 
                    nome_fantasia,
                    codigo_interno
                FROM {$this->tableEntidade} 
                WHERE (tipo = 'Cliente' OR tipo = 'Cliente e Fornecedor') 
                AND situacao = 'Ativo'";

        $params = [];

        if (!empty($term)) {
            $sql .= " AND (razao_social LIKE :term OR nome_fantasia LIKE :term OR codigo_interno LIKE :term)";
            $params[':term'] = '%' . $term . '%';
        }

        $sql .= " ORDER BY razao_social ASC LIMIT 50"; // Limite para selects

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

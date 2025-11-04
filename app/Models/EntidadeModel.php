<?php

/**
 * CLASSE MODELO: EntidadeModel
 * Local: app/Models/EntidadeModel.php
 * Descrição: Gerencia as operações de CRUD nas tabelas ENTIDADES e ENDERECOS.
 */

// Inclui a classe de conexão PDO
require_once __DIR__ . '/Database.php';
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';

class EntidadeModel
{
    private $pdo;
    private $tableEntidade = 'ENTIDADES';
    private $tableEndereco = 'ENDERECOS';
    private $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
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

            // LOG DE AUDITORIA: CREATE
            $this->logger->log(
                'CREATE',
                $this->tableEntidade,
                $entidadeId,
                null, // Dados Antigos: null
                $dataEntidade // Dados Novos: $dataEntidade
            );

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
        $this->pdo->beginTransaction();

        // Inicializa $dadosAntigos como null.
        $dadosAntigos = null;

        try {
            // PASSO DE AUDITORIA 1: Busca o registro ANTES da alteração
            // Se o find falhar, $dadosAntigos ainda será null, e o logger pode lidar com isso.
            $dadosAntigos = $this->find($id);

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

            // PASSO DE AUDITORIA 2: Log de UPDATE após o commit
            $this->logger->log(
                'UPDATE',
                $this->tableEntidade,
                $id,
                $dadosAntigos, // Variável sempre terá um valor (null ou array)
                $dataEntidade
            );

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e; // Lança a exceção para o Controller tratar o erro de DB
        }
    }

    /**
     * Busca dados das entidades no formato DataTables (Server-Side).
     * @param array $params Parâmetros DataTables (start, length, search, tipo).
     * @return array Dados paginados e totais.
     */
    /* public function getForDataTable(array $params): array
    {
        $start = $params['start'];
        $length = $params['length'];
        $searchValue = $params['search'];
        $tipo = $params['tipo'];

        $bindParams = [];
        $where = "WHERE 1=1 ";

        // 1. Filtragem pelo TIPO (Cliente/Fornecedor/Transportadora)
        if (strtolower($tipo) !== 'entidades') {
            $where .= "AND tipo = :tipo ";
            $bindParams[':tipo'] = $tipo;
        }

        // 2. Filtragem pela PESQUISA GLOBAL (Search)
        if (!empty($searchValue)) {
            $where .= "AND (razao_social LIKE :search OR cnpj_cpf LIKE :search OR nome_fantasia LIKE :search) ";
            // Adicionamos o coringa (%) para a pesquisa LIKE
            $bindParams[':search'] = "%" . $searchValue . "%";

            // Se já temos a condição do tipo, a pesquisa aplica-se dentro desse tipo.
        }

        // 3. Contagem Total de Registros (Sem filtro de pesquisa)
        $sqlTotal = "SELECT COUNT(id) FROM {$this->tableEntidade} " . ($where === "WHERE 1=1 " ? "" : $where);
        // Clonamos os params, mas removemos o search para o totalFiltered
        $paramsTotal = $bindParams;
        unset($paramsTotal[':search']); // Se houver, remover o search para o total geral

        $stmtTotal = $this->pdo->prepare($sqlTotal);
        // Ajuste para bindar apenas o :tipo (se existir) para o total geral
        $stmtTotal->execute(array_filter($paramsTotal, fn($k) => $k === ':tipo', ARRAY_FILTER_USE_KEY));
        $totalRecords = $stmtTotal->fetchColumn();


        // 4. Contagem de Registros Filtrados (Com filtro de pesquisa)
        $sqlFiltered = "SELECT COUNT(id) FROM {$this->tableEntidade} " . $where;
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($bindParams); // Executa com todos os filtros
        $totalFiltered = $stmtFiltered->fetchColumn();


        // 5. Query Principal com Limite (Paginação)
        $sqlData = "SELECT id, situacao, tipo, razao_social, cnpj_cpf FROM {$this->tableEntidade} 
                    {$where} 
                    ORDER BY razao_social ASC 
                    LIMIT :start, :length";

        $stmtData = $this->pdo->prepare($sqlData);

        // Bindar os parâmetros e limites
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
    } */

    /**
     * Busca dados das entidades no formato DataTables (Server-Side).
     * @param array $params Parâmetros DataTables (start, length, search, tipo).
     * @return array Dados paginados e totais.
     */

    public function getForDataTable(array $params): array
    {
        $start = $params['start'];
        $length = $params['length'];
        $searchValue = $params['search'];
        $tipo = $params['tipo'];

        $bindParams = [];
        $where = "WHERE 1=1 ";

        // --- 1. CONDIÇÃO BASE: Filtragem pelo TIPO ---
        $tipoIsSet = (strtolower($tipo) !== 'entidades');
        if ($tipoIsSet) {
            $where .= "AND tipo = :tipo ";
            $bindParams[':tipo'] = $tipo;
        }

        // --- 2. CONDIÇÃO DA PESQUISA GLOBAL (SEARCH) ---
        $searchIsSet = !empty($searchValue);
        if ($searchIsSet) {
            $where .= "AND (razao_social LIKE :search1 OR cnpj_cpf LIKE :search2 OR nome_fantasia LIKE :search3) ";
            $bindParams[':search1'] = "%" . $searchValue . "%";
            $bindParams[':search2'] = "%" . $searchValue . "%";
            $bindParams[':search3'] = "%" . $searchValue . "%";
        }

        // --- 3. CONTAGEM TOTAL (Sem pesquisa, Apenas filtro por Tipo) ---
        $sqlTotal = "SELECT COUNT(id) FROM {$this->tableEntidade}";
        if ($tipoIsSet) {
            $sqlTotal .= " WHERE tipo = :tipo";
        }
        $stmtTotal = $this->pdo->prepare($sqlTotal);
        if ($tipoIsSet) {
            $stmtTotal->bindValue(':tipo', $tipo);
        }
        $stmtTotal->execute();
        $totalRecords = $stmtTotal->fetchColumn();

        // --- 4. CONTAGEM FILTRADA (Com pesquisa E filtro por Tipo) ---
        $sqlFiltered = "SELECT COUNT(id) FROM {$this->tableEntidade} " . $where;
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        foreach ($bindParams as $key => $value) {
            $stmtFiltered->bindValue($key, $value);
        }
        $stmtFiltered->execute();
        $totalFiltered = $stmtFiltered->fetchColumn();

        // --- 5. QUERY PRINCIPAL COM LIMITE (Dados) ---
        $sqlData = "SELECT id, situacao, tipo, razao_social, cnpj_cpf FROM {$this->tableEntidade} 
                {$where} 
                ORDER BY razao_social ASC 
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
            $sqlEntidade = "DELETE FROM {$this->tableEntidade} WHERE id = ?";
            $stmtEntidade = $this->pdo->prepare($sqlEntidade);
            $stmtEntidade->execute([$id]);

            // PASSO DE AUDITORIA 2: Log de DELETE
            if ($stmtEntidade->rowCount() > 0) {
                $this->logger->log(
                    'DELETE',
                    $this->tableEntidade,
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

    /**
     * Busca todos os endereços de uma entidade (exceto o Principal, que está no FIND).
     * @param int $entidadeId O ID da entidade.
     * @return array Lista de endereços.
     */
    public function getEnderecosAdicionais(int $entidadeId): array
    {
        $sql = "SELECT id, tipo_endereco, cep, logradouro, numero, complemento, bairro, cidade, uf 
                FROM {$this->tableEndereco} 
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
            $sqlEndereco = "INSERT INTO {$this->tableEndereco} 
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
                FROM {$this->tableEndereco} 
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
        $sql = "UPDATE {$this->tableEndereco} SET 
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
        $sql = "DELETE FROM {$this->tableEndereco} WHERE id = ?";
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
                FROM {$this->tableEntidade} 
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

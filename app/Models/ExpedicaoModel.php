<?php

/**
 * CLASSE MODELO: ExpedicaoModel
 * Local: app/Models/ExpedicaoModel.php
 * Descrição: Gerencia as operações de CRUD na tabela ENTREGAS_EXPEDICAO e suas relações (Veículos, Pessoal).
 */

require_once __DIR__ . '/Database.php';
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';

class ExpedicaoModel
{
    private $pdo;
    private $table = 'ENTREGAS_EXPEDICAO';
    private $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Busca o próximo número sequencial de carregamento disponível para o dia.
     * @param string $dataCarregamento Data (YYYY-MM-DD) do carregamento.
     * @return int Próximo número sequencial.
     */
    public function getNextNumeroCarregamento(string $dataCarregamento): int
    {
        // Encontra o máximo número de carregamento para a data específica
        $sql = "SELECT MAX(numero_carregamento) FROM {$this->table} WHERE data_carregamento = :data";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':data' => $dataCarregamento]);

        $maxNum = $stmt->fetchColumn();
        return (int)$maxNum + 1;
    }

    /**
     * Insere um novo registro de expedição (carregamento).
     * @param array $data Dados da expedição.
     * @param int $responsavelId ID do funcionário logístico responsável.
     * @return int|false ID da nova expedição.
     */
    public function create(array $data, int $responsavelId)
    {
        $sql = "INSERT INTO {$this->table} (
                    previsao_id, numero_carregamento, data_carregamento, veiculo_id, 
                    motorista_principal_id, motorista_reserva_id, encarregado_id, 
                    horario_expedicao, quantidade_caixas, observacao
                ) VALUES (
                    :previsao_id, :numero_carregamento, :data_carregamento, :veiculo_id, 
                    :motorista_principal_id, :motorista_reserva_id, :encarregado_id, 
                    :horario_expedicao, :quantidade_caixas, :observacao
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':previsao_id'              => $data['exp_previsao_id'] ?? null,
                ':numero_carregamento'      => $data['exp_numero_carregamento'],
                ':data_carregamento'        => $data['exp_data_carregamento'],
                ':veiculo_id'               => $data['exp_veiculo_id'],
                ':motorista_principal_id'   => $data['exp_motorista_principal_id'],
                ':motorista_reserva_id'     => $data['exp_motorista_reserva_id'] ?? null,
                ':encarregado_id'           => $data['exp_encarregado_id'],
                ':horario_expedicao'        => $data['exp_horario_expedicao'] ?? null,
                ':quantidade_caixas'        => $data['exp_quantidade_caixas'] ?? 0,
                ':observacao'               => $data['exp_observacao'] ?? null,
            ]);
            // Adicionar Log de Auditoria aqui
            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Motorista: Registra observação ou incidente na tabela REGISTROS_FROTA.
     * @param int $expedicaoId ID da expedição.
     * @param int $motoristaId ID do motorista.
     * @param string $tipo Tipo de lançamento ('Observacao', 'Incidente').
     * @param string $observacao Texto do registro.
     * @return bool
     */
    public function registrarLogFrota(int $expedicaoId, int $motoristaId, string $tipo, string $observacao): bool
    {
        $sql = "INSERT INTO REGISTROS_FROTA (expedicao_id, motorista_id, tipo_lancamento, observacao) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$expedicaoId, $motoristaId, $tipo, $observacao]);
    }

    /**
     * Motorista: Registra abastecimento na tabela REGISTROS_FROTA.
     * @param int $expedicaoId ID da expedição.
     * @param int $motoristaId ID do motorista.
     * @param float $valor Custo total.
     * @param float $litros Quantidade de litros.
     * @return bool
     */
    public function registrarAbastecimento(int $expedicaoId, int $motoristaId, float $valor, float $litros): bool
    {
        $sql = "INSERT INTO REGISTROS_FROTA (expedicao_id, motorista_id, tipo_lancamento, valor, litros) 
                VALUES (?, ?, 'Abastecimento', ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$expedicaoId, $motoristaId, $valor, $litros]);
    }

    /**
     * Técnico: Registra observação, checklist ou mídia na tabela REGISTROS_ENTREGA.
     * @param array $data Dados do formulário (inclui observacao, parametros, etc.).
     * @param array $files Dados do arquivo de mídia ($_FILES).
     * @param int $tecnicoId ID do técnico.
     * @return bool
     */
    public function registrarLogEntrega(array $data, array $files, int $tecnicoId): bool
    {
        // NOTA: Esta lógica requer uma transação para salvar múltiplos itens de checklist.
        // A SIMPLIFICAÇÃO: Apenas registra uma observação geral.

        $expedicaoId = $data['expedicao_id'];
        $observacao = $data['observacao'] ?? '';

        // Simulação do upload de arquivo (necessário para o Flutter)
        $caminhoMidia = null;
        // if (!empty($files['midia']['tmp_name'])) { ... lógica de upload ... }

        $sql = "INSERT INTO REGISTROS_ENTREGA (expedicao_id, tecnico_id, observacao, url_midia) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$expedicaoId, $tecnicoId, $observacao, $caminhoMidia]);
    }

    /**
     * API: Lista as entregas alocadas para um funcionário (Motorista ou Técnico).
     * @param int $userId ID do funcionário.
     * @param string $cargo Cargo do funcionário ('Motorista' ou 'Tecnico').
     * @return array Lista de entregas.
     */
    public function findEntregasByUserId(int $userId, string $cargo): array
    {
        // Base da query
        $sql = "SELECT 
                    e.id, 
                    e.data_carregamento, 
                    e.status_logistica,
                    v.placa,
                    c.nome_fantasia AS cliente_nome 
                FROM ENTREGAS_EXPEDICAO e
                JOIN VEICULOS v ON e.veiculo_id = v.id
                -- Assume que o cliente da primeira previsão é o cliente principal
                LEFT JOIN PREVISOES_VENDAS pv ON e.previsao_id = pv.id
                LEFT JOIN ENTIDADES c ON pv.cliente_entidade_id = c.id ";

        // Condição de filtro (onde o usuário está alocado)
        if ($cargo === 'Motorista') {
            $sql .= " WHERE e.motorista_principal_id = :user_id OR e.motorista_reserva_id = :user_id";
        } elseif ($cargo === 'Tecnico') {
            // NOTA: A tabela de alocação de Técnicos ainda não foi criada. Por ora, vamos simular que ele está alocado.
            // Para o futuro, será necessário uma tabela de N:N (ENTREGA_TECNICOS).
            // Por enquanto, vamos retornar uma lista de exemplo.
            return [
                ['id' => 101, 'cliente' => 'Fazenda Peixe Bom', 'status' => 'Em Carregamento'],
                ['id' => 102, 'cliente' => 'Fazenda Sol', 'status' => 'Em Trânsito']
            ];
        } else {
            return [];
        }

        $sql .= " ORDER BY e.data_carregamento ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a lista de Pedidos/Previsões que estão abertos (status: Confirmado)
     * para serem alocados em uma entrega.
     * @return array Lista simplificada de pedidos.
     */
    public function getPedidosPendentes(): array
    {
        // Esta query busca o básico. O Controller fará o mapeamento Select2/Options.
        $sql = "SELECT 
                    id, 
                    os_numero, 
                    data_saida,
                    quantidade_com_bonus
                FROM PREVISOES_VENDAS 
                WHERE status = 'Confirmado' 
                AND data_saida >= CURDATE()
                ORDER BY data_saida ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados das expedições no formato DataTables (Server-Side).
     * @param array $params Parâmetros DataTables (start, length, search).
     * @return array Dados paginados e totais.
     */
    public function findAllForDataTable(array $params): array
    {
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $searchValue = $params['search']['value'] ?? '';

        $sqlBase = "FROM {$this->table} exp
                    JOIN VEICULOS v ON exp.veiculo_id = v.id
                    JOIN FUNCIONARIOS mp ON exp.motorista_principal_id = mp.id
                    LEFT JOIN FUNCIONARIOS enc ON exp.encarregado_id = enc.id";

        $bindParams = [];
        $where = "WHERE 1=1 ";

        // 1. CONDIÇÃO DA PESQUISA GLOBAL (SEARCH)
        if (!empty($searchValue)) {
            // Permite buscar por Nº Carga, Placa ou Nome do Motorista Principal
            $where .= "AND (exp.numero_carregamento LIKE :search1 OR v.placa LIKE :search2 OR mp.nome_completo LIKE :search3) ";
            $bindParams[':search1'] = "%" . $searchValue . "%";
            $bindParams[':search2'] = "%" . $searchValue . "%";
            $bindParams[':search3'] = "%" . $searchValue . "%";
        }

        // 2. Contagem Total de Registros
        $sqlTotal = "SELECT COUNT(exp.id) " . $sqlBase;
        $stmtTotal = $this->pdo->query($sqlTotal);
        $totalRecords = $stmtTotal->fetchColumn();


        // 3. Contagem de Registros Filtrados
        $sqlFiltered = "SELECT COUNT(exp.id) " . $sqlBase . " " . $where;
        $stmtFiltered = $this->pdo->prepare($sqlFiltered);
        $stmtFiltered->execute($bindParams);
        $totalFiltered = $stmtFiltered->fetchColumn();


        // 4. Query Principal com Limite (Dados)
        $sqlData = "SELECT 
                        exp.id, 
                        exp.numero_carregamento, 
                        exp.data_carregamento, 
                        v.placa AS veiculo_placa,
                        mp.nome_completo AS motorista_principal_nome, 
                        enc.nome_completo AS encarregado_nome,
                        exp.status_logistica
                    {$sqlBase} 
                    {$where} 
                    ORDER BY exp.data_carregamento DESC, exp.numero_carregamento DESC 
                    LIMIT :start, :length";

        $stmtData = $this->pdo->prepare($sqlData);

        // Bindar os parâmetros nomeados (:search1, :search2, etc.)
        foreach ($bindParams as $key => $value) {
            $stmtData->bindValue($key, $value);
        }
        $stmtData->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmtData->bindValue(':length', (int)$length, PDO::PARAM_INT);

        $stmtData->execute();
        $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        return [
            "draw" => intval($params['draw'] ?? 1),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        ];
    }

    /**
     * Busca uma expedição pelo ID.
     * @param int $id O ID da expedição.
     * @return array|false Os dados da expedição ou false se não encontrada.
     */
    public function find(int $id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza um registro de expedição existente.
     * @param int $id ID da expedição.
     * @param array $data Dados para atualização.
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool Sucesso ou falha.
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id);

        try {
            // A Tabela ENTREGAS_EXPEDICAO usa: previsao_id, numero_carregamento, etc.
            $sql = "UPDATE {$this->table} SET 
                previsao_id = ?, numero_carregamento = ?, data_carregamento = ?, veiculo_id = ?, 
                motorista_principal_id = ?, motorista_reserva_id = ?, encarregado_id = ?, 
                horario_expedicao = ?, quantidade_caixas_prevista = ?, observacao = ?, status_logistica = ?
                WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $data['exp_previsao_id'] ?? null,
                $data['exp_numero_carregamento'],
                $data['exp_data_carregamento'],
                $data['exp_veiculo_id'],
                $data['exp_motorista_principal_id'],
                $data['exp_motorista_reserva_id'] ?? null,
                $data['exp_encarregado_id'] ?? null, // Incluído encarregado
                $data['exp_horario_expedicao'] ?? null,
                $data['exp_quantidade_caixas'] ?? null,
                $data['exp_observacao'] ?? null,
                $data['exp_status_logistica'] ?? 'Carregamento pendente', // Permite atualizar status
                $id
            ]);

            // LOG DE AUDITORIA: UPDATE
            if ($success) {
                $this->logger->log('UPDATE', $this->table, $id, $dadosAntigos, $data, $userId);
            }

            $this->pdo->commit();
            return $success;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cancela/Deleta uma expedição.
     * NOTA: É mais seguro apenas cancelar (mudar o status) se já houve atividade (auditoria/custos).
     * Para este projeto, faremos DELETE, mas lançamos exceção se houver custos ou registros.
     * @param int $id ID da expedição.
     * @param int $userId ID do usuário logado (para auditoria).
     * @return bool Sucesso ou falha.
     */
    public function delete(int $id, int $userId): bool
    {
        $this->pdo->beginTransaction();
        $dadosAntigos = $this->find($id);

        try {
            // 1. VERIFICAÇÃO DE DEPENDÊNCIA
            // Se houver registros em CUSTOS_ENTREGA, REGISTROS_FROTA ou ITENS_CARREGAMENTO_CONFIRMACAO
            // o DELETE CASCADE não é suficiente para o log, ou a exclusão deve ser impedida.
            // Aqui, confiamos que as Foreign Keys com ON DELETE RESTRICT (se houver) ou SET NULL
            // lidarão com isso, mas vamos verificar se há custos registrados (que exigem RESTRICT).

            // Simplesmente tentamos o DELETE, e o tratamento de FK fica no Controller (Exceção 23000)
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$id]);

            // 2. Log de Auditoria
            if ($success) {
                $this->logger->log('DELETE', $this->table, $id, $dadosAntigos, null, $userId);
            }

            $this->pdo->commit();
            return $success;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e; // Lança para o Controller
        }
    }
}

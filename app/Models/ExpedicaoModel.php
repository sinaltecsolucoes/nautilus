<?php

/**
 * CLASSE MODELO: ExpedicaoModel
 * Local: app/Models/ExpedicaoModel.php
 * Descrição: Gerencia as operações de CRUD na tabela ENTREGAS_EXPEDICAO e suas relações (Veículos, Pessoal).
 */

require_once __DIR__ . '/Database.php';

class ExpedicaoModel
{
    private $pdo;
    private $table = 'ENTREGAS_EXPEDICAO';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
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
}

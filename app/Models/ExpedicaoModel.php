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

    // Futuramente: findAllForDataTable, findById, update, delete
}

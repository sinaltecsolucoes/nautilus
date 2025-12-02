<?php

/**
 * CLASSE MODELO: ExpedicaooModel
 * Local: app/Models/ExpedicaoModel.php
 * Descrição: Gerencia as operações de CRUD para Expedicao.
 */

require_once __DIR__ . '/Database.php';
require_once ROOT_PATH . '/app/Services/AuditLoggerService.php';
require_once ROOT_PATH . '/app/Helpers/functions.php';

class ExpedicaoModel
{
    private $pdo;
    private $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    public function listar($params)
    {
        $data = $params['data'] ?? date('Y-m-d');
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 100;
        $search = $params['search']['value'] ?? '';

        // Alias para as tabelas: 
        // e = expedição, v = veiculo, t = tecnico, m = motorista, c = cliente
        $sqlBase = "FROM expedicao_programacao e
                    LEFT JOIN veiculos v ON e.veiculo = v.id
                    LEFT JOIN funcionarios t ON e.tecnico = t.id
                    LEFT JOIN funcionarios m ON e.motorista = m.id
                    LEFT JOIN entidades c ON e.cliente = c.id
                    WHERE e.data_expedicao = :data";

        $bind = [':data' => $data];

        if ($search) {
            // Pesquisa pelos NOMES e não pelos IDs
            $sqlBase .= " AND (c.nome_fantasia LIKE :s OR v.placa LIKE :s OR e.os LIKE :s)";
            $bind[':s'] = "%$search%";
        }

        // Contagem Total
        $total = $this->pdo->prepare("SELECT COUNT(e.id) $sqlBase");
        $total->execute($bind);
        $totalRecords = $total->fetchColumn();

        // Busca com JOINs para trazer os nomes
        $sql = "SELECT 
                    e.*,
                    v.placa AS veiculo_nome,
                    v.codigo_veiculo, -- Para montar 'V1 - PLACA' se quiser
                    t.nome_comum AS tecnico_nome,
                    m.nome_comum AS motorista_nome,
                    c.nome_fantasia AS cliente_nome
                $sqlBase
                ORDER BY e.horario ASC, e.ordem ASC, e.id ASC 
                LIMIT :start, :length";

        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            "draw" => (int)$draw,
            "recordsTotal" => (int)$totalRecords,
            "recordsFiltered" => (int)$totalRecords,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public function create(array $linha, int $userId)
    {
        // Prepara valores nulos e numéricos para evitar erros
        $cliente   = !empty($linha['cliente']) && is_numeric($linha['cliente']) ? (int)$linha['cliente'] : null;

        if (!$cliente) {
            throw new Exception('Cliente é obrigatório!');
        }

        $ordem     = !empty($linha['ordem']) ? $linha['ordem'] : null;
        $os        = !empty($linha['os']) ? $linha['os'] : null;
        $horario   = !empty($linha['horario']) ? $linha['horario'] : null;
        //$obs       = !empty($linha['observacao']) ? $linha['observacao'] : null;
        $linha['observacao']    = !empty($linha['observacao'])    ? str_upper($linha['observacao'])    : null;
        $motorista = !empty($linha['motorista']) ? $linha['motorista'] : null;
        //$caixas    = !empty($linha['caixas_usadas']) ? $linha['caixas_usadas'] : null;
        $linha['caixas_usadas'] = !empty($linha['caixas_usadas']) ? str_upper($linha['caixas_usadas']) : null;

        $valAbast = $linha['valor_abastecimento'] ?? 0;
        $valMot   = $linha['valor_diaria_motorista'] ?? 0;
        $valTec   = $linha['valor_diaria_tecnico'] ?? 0;

        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO expedicao_programacao 
                (data_expedicao, ordem, veiculo, tecnico, os, cliente, qtd_pl, horario, observacao, 
                 motorista, caixas_usadas, valor_abastecimento, valor_diaria_motorista, valor_diaria_tecnico, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $linha['data_expedicao'],
                $ordem,
                $linha['veiculo'],
                $linha['tecnico'],
                $os,
                $linha['cliente'], // Lembre-se: ID do cliente
                $linha['qtd_pl'],
                $horario,
                $linha['observacao'],
                //$obs,
                $motorista,
                $linha['caixas_usadas'],
                //$caixas,
                $valAbast,
                $valMot,
                $valTec,
                $userId
            ]);

            $this->pdo->commit();
            return "Entrega adicionada com sucesso!";
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza uma programação existente.
     * @param int $id ID da linha a ser atualizada.
     * @param array $linha Dados do formulário.
     * @return string Mensagem de sucesso.
     */
    public function update(int $id, array $linha)
    {
        // Prepara valores nulos e numéricos
        $cliente = !empty($linha['cliente']) && is_numeric($linha['cliente']) ? (int)$linha['cliente'] : null;

        if (!$cliente) {
            throw new Exception('Cliente é obrigatório!');
        }

        $ordem     = !empty($linha['ordem']) ? $linha['ordem'] : null;
        $os        = !empty($linha['os']) ? $linha['os'] : null;
        $horario   = !empty($linha['horario']) ? $linha['horario'] : null;
        //  $obs       = !empty($linha['observacao']) ? $linha['observacao'] : null;
        $linha['observacao']    = !empty($linha['observacao'])    ? str_upper(trim($linha['observacao']))    : null;
        $motorista = !empty($linha['motorista']) ? $linha['motorista'] : null;
        //$caixas    = !empty($linha['caixas_usadas']) ? $linha['caixas_usadas'] : null;
        $linha['caixas_usadas'] = !empty($linha['caixas_usadas']) ? str_upper(trim($linha['caixas_usadas'])) : null;

        $valAbast = $linha['valor_abastecimento'] ?? 0;
        $valMot   = $linha['valor_diaria_motorista'] ?? 0;
        $valTec   = $linha['valor_diaria_tecnico'] ?? 0;

        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE expedicao_programacao SET 
                    data_expedicao=?, ordem=?, veiculo=?, tecnico=?, os=?, cliente=?, 
                    qtd_pl=?, horario=?, observacao=?, motorista=?, caixas_usadas=?, 
                    valor_abastecimento=?, valor_diaria_motorista=?, valor_diaria_tecnico=?, 
                    atualizado_em=NOW() 
                    WHERE id=?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $linha['data_expedicao'],
                $ordem,
                $linha['veiculo'],
                $linha['tecnico'],
                $os,
                $linha['cliente'],
                $linha['qtd_pl'],
                $horario,
                //$obs,
                $linha['observacao'],
                $motorista,
                $linha['caixas_usadas'],
                //$caixas,
                $valAbast,
                $valMot,
                $valTec,
                $id
            ]);

            $this->pdo->commit();
            return "Entrega atualizada com sucesso!";
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deletar($data, $userId)
    {
        $id = $data['id'] ?? 0;
        $this->pdo->prepare("DELETE FROM expedicao_programacao WHERE id = ?")->execute([$id]);
        return "Linha excluída!";
    }

    /**
     * Atualiza apenas o campo 'ordem' de uma linha da programação
     */
    public function atualizarOrdem(int $id, int $novaOrdem): bool
    {
        try {
            $sql = "UPDATE expedicao_programacao SET ordem = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$novaOrdem, $id]);
        } catch (PDOException $e) {
            // Opcional: logar o erro
            error_log("Erro ao atualizar ordem da expedição ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca uma linha específica pelo ID (Com nomes para o Select2)
     */
    public function get($id)
    {
        $sql = "SELECT 
                    e.*,
                    v.placa AS veiculo_nome,
                    t.nome_comum AS tecnico_nome,
                    m.nome_comum AS motorista_nome,
                    c.nome_fantasia AS cliente_nome
                FROM expedicao_programacao e
                LEFT JOIN veiculos v ON e.veiculo = v.id
                LEFT JOIN funcionarios t ON e.tecnico = t.id
                LEFT JOIN funcionarios m ON e.motorista = m.id
                LEFT JOIN entidades c ON e.cliente = c.id
                WHERE e.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os dados de uma data específica para o Relatório de Romaneio.
     * Traz todos os nomes (joins) para não precisar processar no Controller.
     */
    /* public function getDadosRelatorio($data)
    {
        $sql = "SELECT 
                    e.*,
                    -- Formata Horário
                    DATE_FORMAT(e.horario, '%H:%i') as hora_formatada,
                    
                    -- Veículo
                    CONCAT(IF(v.codigo_veiculo IS NOT NULL, CONCAT(v.codigo_veiculo, ' - '), ''), v.placa) AS veiculo_nome,
                    
                    -- Pessoas
                    m.nome_comum AS motorista_nome,
                    t.nome_comum AS tecnico_nome,
                    
                    -- Cliente
                    c.nome_fantasia AS cliente_nome

                FROM expedicao_programacao e
                LEFT JOIN veiculos v ON e.veiculo = v.id
                LEFT JOIN funcionarios t ON e.tecnico = t.id
                LEFT JOIN funcionarios m ON e.motorista = m.id
                LEFT JOIN entidades c ON e.cliente = c.id
                
                WHERE e.data_expedicao = :data
                ORDER BY e.horario ASC, e.ordem ASC, e.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':data' => $data]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } */

    /**
     * Busca dados para o Romaneio (Layout Ficha).
     */
    /* public function getDadosRelatorio($data)
    {
        $sql = "SELECT 
                    e.*,
                    
                    -- Formata Horário (Ex: 03:00)
                    DATE_FORMAT(e.horario, '%H:%i') as hora_formatada,
                    
                    -- Veículo (Código - Placa - Modelo)
                    CONCAT(
                        IF(v.codigo_veiculo IS NOT NULL, CONCAT(v.codigo_veiculo, ' - '), ''), 
                        v.placa, ' - ', v.modelo
                    ) AS veiculo_completo,
                    
                    -- Pessoas
                    m.nome_comum AS motorista_nome,
                    t.nome_comum AS tecnico_nome,
                    
                    -- Cliente e Endereço
                    c.nome_fantasia AS cliente_nome,
                    CONCAT(end.cidade, '/', end.uf) as cidade_uf

                FROM expedicao_programacao e
                LEFT JOIN veiculos v ON e.veiculo = v.id
                LEFT JOIN funcionarios t ON e.tecnico = t.id
                LEFT JOIN funcionarios m ON e.motorista = m.id
                LEFT JOIN entidades c ON e.cliente = c.id
                -- Join para pegar a cidade do endereço principal do cliente
                LEFT JOIN enderecos end ON (c.id = end.entidade_id AND end.tipo_endereco = 'Principal')
                
                WHERE e.data_expedicao = :data
                ORDER BY e.horario ASC, e.ordem ASC, e.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':data' => $data]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } */

    /**
     * Busca dados para o Romaneio (Layout Ficha).
     */
    public function getDadosRelatorio($data)
    {
        $sql = "SELECT 
                    e.*,
                    
                    -- Formata Horário
                    DATE_FORMAT(e.horario, '%H:%i') as hora_formatada,
                    
                    -- OBRIGATÓRIO: Garante que 'veiculo_nome' exista
                    CONCAT(
                        IF(v.codigo_veiculo IS NOT NULL AND v.codigo_veiculo != '', CONCAT(v.codigo_veiculo, ' - '), ''), 
                        v.placa
                    ) AS veiculo_nome,
                    
                    -- Pessoas
                    m.nome_comum AS motorista_nome,
                    t.nome_comum AS tecnico_nome,
                    
                    -- Cliente e Endereço
                    c.nome_fantasia AS cliente_nome,
                    CONCAT(COALESCE(end.cidade, ''), '/', COALESCE(end.uf, '')) as cidade_uf

                FROM expedicao_programacao e
                LEFT JOIN veiculos v ON e.veiculo = v.id
                LEFT JOIN funcionarios t ON e.tecnico = t.id
                LEFT JOIN funcionarios m ON e.motorista = m.id
                LEFT JOIN entidades c ON e.cliente = c.id
                
                -- Busca endereço principal do cliente para o relatório
                LEFT JOIN enderecos end ON (c.id = end.entidade_id AND end.tipo_endereco = 'Principal')
                
                WHERE e.data_expedicao = :data
                ORDER BY e.horario ASC, e.ordem ASC, e.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':data' => $data]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

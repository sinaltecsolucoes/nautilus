<?php

/**
 * CLASSE MODELO: PermissaoModel
 * Local: app/Models/PermissaoModel.php
 * Descrição: Gerencia as operações de leitura na tabela PERMISSOES_MODULOS,
 * que armazena as regras de acesso por Cargo, Módulo e Ação.
 */

// Inclui a classe de conexão PDO
require_once 'Database.php';


class PermissaoModel
{
    private $pdo;
    private $table = 'PERMISSOES_MODULOS';

    public function __construct()
    {
        // Obtém a única instância da conexão PDO
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Busca a regra de permissão específica no banco de dados.
     * @param string $cargo O tipo de cargo do funcionário (Ex: 'Vendedor').
     * @param string $modulo O módulo sendo acessado (Ex: 'Entidades').
     * @param string $acao A ação desejada (Ex: 'Alterar').
     * @return array|false O registro da permissão ou false se a regra não for encontrada.
     */
    public function getRegraPermissao($cargo, $modulo, $acao)
    {
        $sql = "SELECT permitido 
                FROM {$this->table} 
                WHERE tipo_cargo = :cargo 
                  AND modulo = :modulo 
                  AND acao = :acao";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':cargo'  => $cargo,
                ':modulo' => $modulo,
                ':acao'   => $acao
            ]);

            // Retorna o resultado da consulta (apenas o campo 'permitido')
            return $stmt->fetch();
        } catch (\PDOException $e) {
            // Em caso de erro de DB, loga e assume que a permissão é negada por segurança
            // Log::error("Erro ao buscar permissão: " . $e->getMessage()); // Simulação de log
            return false;
        }
    }

    /**
     * Busca todas as regras de permissão do sistema, agrupadas por Módulo.
     * @return array Array aninhado de permissões [cargo][modulo][acao]
     */
    /*  public function getAllPermissoes()
    {
        // Seleciona todos os dados da tabela, ordenados para facilitar a visualização (View)
        $sql = "SELECT id, tipo_cargo, modulo, acao, permitido 
                FROM {$this->table} 
                ORDER BY modulo, tipo_cargo, acao";

        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll();

        // Estrutura os resultados para facilitar o uso na View
        $permissoes = [];
        foreach ($results as $row) {
            $permissoes[$row['modulo']][$row['tipo_cargo']][$row['acao']] = [
                'id' => $row['id'],
                'permitido' => (bool)$row['permitido']
            ];
        }
        return $permissoes;
    }*/

    /**
     * Atualiza o status 'permitido' de uma regra específica.
     * @param int $id O ID da regra na tabela PERMISSOES_MODULOS.
     * @param bool $status O novo status (1 para TRUE/Permitido, 0 para FALSE/Negado).
     * @return bool Sucesso ou falha na atualização.
     */
    /*  public function updatePermissaoStatus($id, $status)
    {
        $sql = "UPDATE {$this->table} SET permitido = :status WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':status' => (int)$status,
                ':id'     => $id
            ]);
        } catch (\PDOException $e) {
            // Em caso de erro, loga e retorna false
            // Log::error("Erro ao atualizar permissão ID {$id}: " . $e->getMessage()); 
            return false;
        }
    }*/

    public function getAllPermissoesMatriz()
    {
        $sql = "SELECT tipo_cargo, modulo, acao, permitido FROM permissoes_modulos";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matriz = [];
        foreach ($rows as $r) {
            // Cria um array: $matriz['Motorista']['Entidades']['Ler'] = true
            if ($r['permitido'] == 1) {
                $matriz[$r['tipo_cargo']][$r['modulo']][$r['acao']] = true;
            }
        }
        return $matriz;
    }

    // Salva ou Atualiza uma permissão (Upsert)
    public function updatePermissao($cargo, $modulo, $acao, $status)
    {
        // Tenta inserir, se já existir (chave única), atualiza
        $sql = "INSERT INTO permissoes_modulos (tipo_cargo, modulo, acao, permitido) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE permitido = VALUES(permitido)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$cargo, $modulo, $acao, $status]);
    }
}

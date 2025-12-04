<?php

namespace App\Models;

use App\Core\Database;
use App\Services\AuditLoggerService;
use PDO;
use PDOException;

class PermissaoModel
{
    protected PDO $pdo;
    protected AuditLoggerService $logger;
    protected string $table = 'permissoes_modulos';

    public function __construct()
    {
        // Obtém a única instância da conexão PDO
        $this->pdo = Database::getInstance()->getConnection();
        $this->logger = new AuditLoggerService();
    }

    /**
     * Busca a regra de permissão específica no banco de dados.
     * @param string $cargo O tipo de cargo do funcionário (Ex: 'Vendedor').
     * @param string $modulo O módulo sendo acessado (Ex: 'Entidades').
     * @param string $acao A ação desejada (Ex: 'Alterar').
     * @return array|false O registro da permissão ou false se a regra não for encontrada.
     */
    public function getRegraPermissao(string $cargo, string $modulo, string $acao)
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
        } catch (PDOException $e) {
            // Em caso de erro de Banco de Dados, loga e assume que a permissão é negada por segurança
            // Log::error("Erro ao buscar permissão: " . $e->getMessage()); // Simulação de log
            return false;
        }
    }

    public function getAllPermissoesMatriz()
    {
        $sql = "SELECT tipo_cargo, modulo, acao, permitido FROM permissoes_modulos";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matriz = [];
        foreach ($rows as $r) {
            // Cria um array: $matriz['Motorista']['Entidades']['Ler'] = true
            if ((int) $r['permitido'] == 1) {
                $matriz[$r['tipo_cargo']][$r['modulo']][$r['acao']] = true;
            }
        }
        return $matriz;
    }

    /**
     * Atualiza ou Insere uma permissão (Upsert).
     * @param string $cargo
     * @param string $modulo
     * @param string $acao
     * @param int $status 1 para permitido, 0 para negado
     * @return bool
     */
    public function updatePermissao(string $cargo, string $modulo, string $acao, int $status)
    {
        // Tenta inserir, se já existir (chave única), atualiza
        $sql = "INSERT INTO {$this->table} (tipo_cargo, modulo, acao, permitido) 
                VALUES (:cargo, :modulo, :acao, :status) 
                ON DUPLICATE KEY UPDATE permitido = VALUES(permitido)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':cargo'  => $cargo,
                ':modulo' => $modulo,
                ':acao'   => $acao,
                'status'  => $status
            ]);
        } catch (PDOException $e) {
            return false;
        }
    } 
}

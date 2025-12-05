<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;

/**
 * Service AuditLoggerService
 * Responsável por registrar logs de auditoria no sistema.
 * @package App\Services
 */
class AuditLoggerService
{

    private PDO $db;
    private const TABLE = 'auditoria';

    public function __construct()
    {
        // Inicialisza a conexão PDO
        $this->db = Database::getConnection();
    }

    /**
     * Registra uma ação de auditoria no sistema.
     * @param string $acao Tipo de ação ('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT').
     * @param string $tabela Nome da tabela afetada (Ex: ENTIDADES).
     * @param int $registroId ID da linha afetada.
     * @param array|null $dadosAntigos Array associativo dos dados ANTES (para UPDATE/DELETE).
     * @param array|null $dadosNovos Array associativo dos dados DEPOIS (para CREATE/UPDATE).
     * @param int|null $usuarioId  ID do usuário logado (se null, pega da sessão).
     * @return bool Sucesso ou falha na escrita do log.
     */
    public function log(
        string $acao,
        string $tabela,
        int $registroId,
        ?array $dadosAntigos = null,
        ?array $dadosNovos = null,
        ?int $usuarioId = null
    ): bool {
        // 1. Obtém o ID do usuário (priorizando o valor passado ou a sessão)
        if ($usuarioId === null) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            $usuarioId = $_SESSION['user_id'] ?? 1; // 1: ID de fallback para o Admin/Sistema
        }

        // 2. Obtém o IP do usuário
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI'; // 'CLI' se for rodado da linha de comando

        // 3. Converte os arrays de dados para JSON (necessário para o tipo JSON no MySQL)
        $jsonAntigo = $dadosAntigos ? json_encode($dadosAntigos, JSON_UNESCAPED_UNICODE) : null;
        $jsonNovo = $dadosNovos ? json_encode($dadosNovos, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO" . self::TABLE . " (
                    usuario_id, 
                    tabela_afetada, 
                    registro_id, 
                    acao, 
                    dados_antigos, 
                    dados_novos, 
                    ip_endereco,
                    created_at
                ) VALUES (
                    :user_id, 
                    :tabela, 
                    :registro_id, 
                    :acao, 
                    :antigo, 
                    :novo, 
                    :ip,
                    NOW()
                )";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':user_id'     => $usuarioId,
                ':tabela'      => $tabela,
                ':registro_id' => $registroId,
                ':acao'        => strtoupper($acao),
                ':antigo'      => $jsonAntigo,
                ':novo'        => $jsonNovo,
                ':ip'          => $ip
            ]);
        } catch (PDOException $e) {
            // Em caso de falha na auditoria (ex: tabela AUDITORIA travada), 
            // registramos o erro no log do servidor, mas não impedimos a operação principal.
            error_log("ERRO FATAL DE AUDITORIA: " . $e->getMessage());
            return false;
        }
    }
}
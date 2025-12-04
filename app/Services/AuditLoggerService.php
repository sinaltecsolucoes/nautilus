<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;

/**
 * Class AuditLoggerService
 * Registra logs de atividades críticas no sistema.
 * @package App\Services
 */
class AuditLoggerService
{

    private PDO $pdo;
    private string $table = 'auditoria';

    public function __construct()
    {
        // Inicializa a conexão PDO
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Registra uma ação de auditoria no sistema.
     * @param string $acao Tipo de ação ('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT').
     * @param string $tabela Nome da tabela afetada (Ex: ENTIDADES).
     * @param int $registroId ID da linha afetada.
     * @param array|null $dadosAntigos Array associativo dos dados ANTES (para UPDATE/DELETE).
     * @param array|null $dadosNovos Array associativo dos dados DEPOIS (para CREATE/UPDATE).
     * @param int|null $usuarioId Opcional: ID do usuário logado (padrão é buscar na sessão).
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
        $jsonAntigo = !empty($dadosAntigos) ? json_encode($dadosAntigos) : null;
        $jsonNovo = !empty($dadosNovos) ? json_encode($dadosNovos) : null;

        $sql = "INSERT INTO {$this->table} (
                    usuario_id, tabela_afetada, registro_id, acao, 
                    dados_antigos, dados_novos, ip_endereco
                ) VALUES (
                    :user_id, :tabela, :registro_id, :acao, 
                    :antigo, :novo, :ip
                )";

        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                ':user_id' => $usuarioId,
                ':tabela' => $tabela,
                ':registro_id' => $registroId,
                ':acao' => $acao,
                ':antigo' => $jsonAntigo,
                ':novo' => $jsonNovo,
                ':ip' => $ip
            ]);

            return $success;
        } catch (PDOException $e) {
            // Em caso de falha na auditoria (ex: tabela AUDITORIA travada), 
            // registramos o erro no log do servidor, mas não impedimos a operação principal.
            error_log("ERRO FATAL DE AUDITORIA: " . $e->getMessage());
            return false;
        }
    }
}

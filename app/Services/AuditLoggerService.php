<?php

/**
 * CLASSE DE SERVIÇO: AuditLoggerService
 * Local: app/Services/AuditLoggerService.php
 * Descrição: Centraliza a escrita de logs de auditoria no banco de dados.
 */

require_once ROOT_PATH . '/app/Models/Database.php';

class AuditLoggerService
{

    private $pdo;
    private $table = 'AUDITORIA';

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
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $usuarioId ?? $_SESSION['user_id'] ?? 1; // 1: ID de fallback para o Admin/Sistema

        // 2. Obtém o IP do usuário
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI'; // 'CLI' se for rodado da linha de comando

        // 3. Converte os arrays de dados para JSON (necessário para o tipo JSON no MySQL)
        $jsonAntigo = $dadosAntigos ? json_encode($dadosAntigos) : null;
        $jsonNovo = $dadosNovos ? json_encode($dadosNovos) : null;

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
                ':user_id' => $userId,
                ':tabela' => $tabela,
                ':registro_id' => $registroId,
                ':acao' => $acao,
                ':antigo' => $jsonAntigo,
                ':novo' => $jsonNovo,
                ':ip' => $ip
            ]);

            return $success;
        } catch (\PDOException $e) {
            // Em caso de falha na auditoria (ex: tabela AUDITORIA travada), 
            // registramos o erro no log do servidor, mas não impedimos a operação principal.
            error_log("ERRO FATAL DE AUDITORIA: " . $e->getMessage());
            return false;
        }
    }
}

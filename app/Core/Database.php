<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Class Database
 * Singleton para conexão segura com MySQL via PDO.
 * @package App\Core
 */
class Database
{
    // Variável para armazenar a conexao PDO
    private static ?PDO $connection = null;

    /**
     * Retorna a conexão PDO única
     * @return PDO
     */
    public static function getConnection(): PDO
    {

        if (self::$connection === null) {
            $config = require ROOT_PATH . '/config/config.php';

            $dsn = sprintf(
                "mysql:host=%s;dbname=%;charset=utf8mb4",
                $config['db']['host'],
                $config['db']['name'],
            );

            // Define as opções de conexão do PDO
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lançar exceções em caso de erro
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retornar resultados como arrays associativos
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Essencial para segurança (Prepared Statements nativos)
            ];

            try {
                // Cria a conexão PDO
                self::$connection = new PDO(
                    $dsn,
                    $config['db']['user'],
                    $config['db']['pass'],
                    $options
                );
            } catch (PDOException $e) {
                // Em caso de erro, exibe uma mensagem segura e registra o erro
                // Em produção, não exibirá a mensagem completa do erro!
                error_log("Erro de conexão do Banco de Dados: " . $e->getMessage());
                die("Erro de Crítico com o Banco de Dados.");
            }
        }

        return self::$connection;
    }
}

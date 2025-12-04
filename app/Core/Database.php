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
    // Variável para armazenar a única instância da conexão PDO
    private static ?Database $instance = null;

    // Variável para armazenar a conexão PDO
    private PDO $conn;

    /**
     * Construtor privado (padrão Singleton)
     * Impede a criação de múltiplas instâncias da conexão.
     */
    private function __construct()
    {
        $configPath = __DIR__ . '/../../config/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }

        // Define as opções de conexão do PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lançar exceções em caso de erro
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retornar resultados como arrays associativos
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Essencial para segurança (Prepared Statements nativos)
        ];

        // String de conexão
        $dsn = "mysql:host=" . (defined('DB_HOST') ? DB_HOST : 'localhost') .
            ";dbname=" . (defined('DB_NAME') ? DB_NAME : 'test') .
            ";charset=utf8mb4";

        try {
            // Cria a conexão PDO
            $this->conn = new PDO(
                $dsn,
                defined('   DB_USER') ? DB_USER : 'root',
                defined('DB_PASS') ? DB_PASS : '',
                $options
            );
        } catch (PDOException $e) {
            // Em caso de erro, exibe uma mensagem segura e registra o erro (idealmente em logs)
            // Em produção, não exibirá a mensagem completa do erro!
            error_log("Erro de conexão do Banco de Dados: " . $e->getMessage());
            die("Erro de Crítico com o Banco de Dados.");
        }
    }

    /**
     * Método público estático para obter a única instância da classe de conexão.
     * @return Database A instância da conexão.
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Obtém o objeto de conexão PDO.
     * @return PDO O objeto PDO conectado.
     */
    public function getConnection()
    {
        return $this->conn;
    }
}

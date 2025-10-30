<?php

/**
 * CLASSE MODELO: Database
 * Local: app/Models/Database.php
 * Descrição: Classe Singleton para conexão segura com o MySQL usando PDO.
 * Base para todos os outros Models.
 */

// Inclui as configurações de segurança e credenciais
require_once __DIR__ . '/../../config/config.php';

class Database
{
    // Variável para armazenar a única instância da conexão PDO
    private static $instance = null;
    // Variável para armazenar a conexão PDO
    private $conn;

    /**
     * Construtor privado (padrão Singleton)
     * Impede a criação de múltiplas instâncias da conexão.
     */
    private function __construct()
    {
        // Define as opções de conexão do PDO
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lançar exceções em caso de erro
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retornar resultados como arrays associativos
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Essencial para segurança (Prepared Statements nativos)
        ];

        // String de conexão
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        try {
            // Cria a conexão PDO
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            // Em caso de erro, exibe uma mensagem segura e registra o erro (idealmente em logs)
            // Em produção, não exibirá a mensagem completa do erro!
            die("Erro de Conexão com o Banco de Dados: " . $e->getMessage());
        }
    }

    /**
     * Método público estático para obter a única instância da classe de conexão.
     * @return Database A instância da conexão.
     */
    public static function getInstance()
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

// Exemplo de como usar (Para testes iniciais):
// $db = Database::getInstance();
// $conn = $db->getConnection();
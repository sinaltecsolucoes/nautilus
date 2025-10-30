<?php
/**
 * ARQUIVO DE CONFIGURAÇÃO DE CREDENCIAIS
 * Local: nautilus/config/config.php
 * Descrição: Define as constantes para conexão segura com o MySQL.
 */

// Informações do Banco de Dados
define('DB_HOST', 'localhost');          // O endereço do servidor de banco de dados 
define('DB_USER', 'root');               // O usuário do banco de dados
define('DB_PASS', '');                   // A senha do banco de dados
define('DB_NAME', 'db_nautilus'); // O nome do banco de dados

// Configurações do Sistema
define('APP_NAME', 'Sistema de Gestão de Pescados');
define('BASE_URL', 'http://localhost/nautilus'); // URL base para roteamento (Ajuste conforme sua instalação)

// Configurações de Segurança e API (Serão usadas futuramente)
define('SECRET_KEY', 'SuaChaveSecretaParaJWT'); // Chave única para assinar tokens JWT
define('TOKEN_EXPIRY_HOURS', 24);             // Tempo de vida do token em horas

// APIs Externas (URLs para ViaCEP e CNPJA)
define('VIACEP_URL', 'https://viacep.com.br/ws/');
define('CNPJA_URL', 'https://cnpja.com/api/v1/companies/');

// Observação de Segurança:
// Em um ambiente de produção real, este arquivo não deve ser incluído no controle de versão (Git)
// e a senha (DB_PASS) NUNCA deve ser vazia.
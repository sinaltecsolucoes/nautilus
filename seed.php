<?php

/**
 * SCRIPT DE INSERÇÃO DE DADOS INICIAIS (SEEDER)
 * Local: nautilus/seed.php
 * Descrição: Insere dados de teste nas tabelas básicas (Funcionários, Entidades, Veículos)
 * para permitir o teste dos módulos CRUD e de Manutenção.
 *
 * NOTA: Este script é temporário e deve ser excluído após o uso!
 */

// 1. Definição do ROOT_PATH e Inclusões
define('ROOT_PATH', __DIR__); // A raiz do projeto é onde este arquivo está.
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/Models/Database.php';

// Inclui os Models necessários para a inserção
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php';
require_once ROOT_PATH . '/app/Models/VeiculoModel.php';

// Funções Auxiliares
function generateHash($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

// 2. Conexão com o Banco de Dados
try {
    $pdo = Database::getInstance()->getConnection();
    echo "✅ Conexão com o Banco de Dados estabelecida.<br>";
} catch (Exception $e) {
    die("🛑 ERRO CRÍTICO DE CONEXÃO: " . $e->getMessage());
}

// 3. Inicialização dos Models
$funcionarioModel = new FuncionarioModel();
$entidadeModel = new EntidadeModel();
$veiculoModel = new VeiculoModel();

// 4. INSERÇÃO DE DADOS DE TESTE
$pdo->beginTransaction();

try {
    echo "<h3>1. Funcionários (Administrador/Motorista)</h3>";

    // --- A. Usuário Administrador (ID 1) ---
    $adminData = [
        'nome_completo' => 'Admin Master',
        'email' => 'admin@nautilus.com',
        'senha_pura' => '123456',
        'tipo_cargo' => 'Administrador',
    ];
    // O FuncionarioModel::create já insere com hash, mas para garantir:
    $hashAdmin = generateHash($adminData['senha_pura']);
    $stmt = $pdo->prepare("INSERT INTO FUNCIONARIOS (nome_completo, email, senha_hash, tipo_cargo) 
                           VALUES (?, ?, ?, ?)");
    $stmt->execute([$adminData['nome_completo'], $adminData['email'], $hashAdmin, $adminData['tipo_cargo']]);
    $adminId = $pdo->lastInsertId();
    echo "   - Administrador Inserido (ID: {$adminId}). Senha: 123456<br>";

    // --- B. Usuário Motorista (ID 2) ---
    $motoristaData = [
        'nome_completo' => 'João Motorista Silva',
        'email' => 'joao@nautilus.com',
        'senha_pura' => '123456',
        'tipo_cargo' => 'Motorista',
    ];
    $hashMotorista = generateHash($motoristaData['senha_pura']);
    $stmt->execute([$motoristaData['nome_completo'], $motoristaData['email'], $hashMotorista, $motoristaData['tipo_cargo']]);
    $motoristaId = $pdo->lastInsertId();
    echo "   - Motorista Inserido (ID: {$motoristaId})<br>";

    // -------------------------------------------------------------------

    echo "<h3>2. Entidades (Proprietário e Fornecedor)</h3>";

    // --- C. Entidade Proprietária (ID 1) ---
    $proprietarioData = [
        'tipo' => 'Fornecedor',
        'tipo_pessoa' => 'JURIDICA',
        'razao_social' => 'NAUTILUS LOGISTICA LTDA',
        'cnpj_cpf' => '00000000000191'
    ];
    $stmtEntidade = $pdo->prepare("INSERT INTO ENTIDADES (tipo, tipo_pessoa, razao_social, cnpj_cpf) VALUES (?, ?, ?, ?)");
    $stmtEntidade->execute([$proprietarioData['tipo'], $proprietarioData['tipo_pessoa'], $proprietarioData['razao_social'], $proprietarioData['cnpj_cpf']]);
    $proprietarioId = $pdo->lastInsertId();
    echo "   - Proprietário Inserido (ID: {$proprietarioId})<br>";

    // --- D. Entidade Fornecedor de Manutenção (ID 2) ---
    $fornecedorData = [
        'tipo' => 'Fornecedor',
        'tipo_pessoa' => 'JURIDICA',
        'razao_social' => 'Mecânica Rápida S.A.',
        'cnpj_cpf' => '11111111111111'
    ];
    $stmtEntidade->execute([$fornecedorData['tipo'], $fornecedorData['tipo_pessoa'], $fornecedorData['razao_social'], $fornecedorData['cnpj_cpf']]);
    $fornecedorId = $pdo->lastInsertId();
    echo "   - Fornecedor de Manutenção Inserido (ID: {$fornecedorId})<br>";

    // -------------------------------------------------------------------

    echo "<h3>3. Veículos</h3>";

    // --- E. Veículo Principal (ID 1) ---
    $veiculoData = [
        'veiculo_placa' => 'ABC1234', // CORRIGIDO!
        'veiculo_modelo' => 'Caminhão Refrigerado F400',
        'veiculo_ano' => 2023, // Adicionando o ano para evitar outro Warning
        'veiculo_tipo_frota' => 'Propria',
        'veiculo_chassi' => 'X000000000000001',
        'veiculo_proprietario_id' => $proprietarioId,
        'veiculo_tipo_combustivel' => 'Diesel', // Adicionando combustivel
    ];
    $veiculoModel->create($veiculoData);
    echo "   - Caminhão ABC1234 Inserido.<br>";

    // --- F. Veículo Reserva (ID 2) ---
    $veiculoData2 = [
        'veiculo_placa' => 'XYZ5678', // CORRIGIDO!
        'veiculo_modelo' => 'Van de Apoio R100',
        'veiculo_ano' => 2022, // Adicionando o ano
        'veiculo_tipo_frota' => 'Propria',
        'veiculo_chassi' => 'Y000000000000002',
        'veiculo_proprietario_id' => $proprietarioId,
        'veiculo_tipo_combustivel' => 'Etanol',
    ];
    $veiculoModel->create($veiculoData2);
    echo "   - Van XYZ5678 Inserida.<br>";

    // -------------------------------------------------------------------

    $pdo->commit();
    echo "<hr><h2>✅ SEEDING CONCLUÍDO COM SUCESSO!</h2>";
    echo "As tabelas foram populadas. Você pode agora logar como 'admin@nautilus.com' (senha: 123456) e testar os módulos.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<hr>🛑 ERRO AO INSERIR DADOS: " . $e->getMessage() . "<br>";
    echo "A transação foi revertida. Verifique se as tabelas estão vazias ou se há chaves duplicadas.";
}

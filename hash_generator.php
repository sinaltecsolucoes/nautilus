<?php
/**
 * UTILITÁRIO: Gerador de Hash de Senha
 * Local: sistema_camarao/hash_generator.php
 * Descrição: Script temporário para gerar o hash seguro de uma senha,
 * que será inserido manualmente na tabela FUNCIONARIOS.
 */

// A senha Pura que você deseja usar para o primeiro Administrador
$senha_pura = '123456'; // *** ALtere para a senha que você realmente deseja usar ***

// Usa a função segura do PHP para criar o hash (algoritmo BCRYPT)
$senha_hash = password_hash($senha_pura, PASSWORD_BCRYPT);

echo "<h2>NAUTILUS ERP - Gerador de Hash de Senha</h2>";
echo "<p>Senha Pura: <strong>{$senha_pura}</strong></p>";
echo "<p>COPIE ESTE HASH (é o valor que você deve inserir no campo 'senha_hash' no DB):</p>";
echo "<pre style='background-color: #f4f4f4; padding: 10px; border: 1px solid #ccc; font-size: 1.1em;'>";
echo htmlspecialchars($senha_hash); // Garante que o hash seja exibido corretamente
echo "</pre>";

// Exemplo de teste para verificar se funciona:
if (password_verify($senha_pura, $senha_hash)) {
    echo "<p style='color: green;'><strong>Teste de Verificação: OK. O hash é válido.</strong></p>";
} else {
    echo "<p style='color: red;'><strong>Teste de Verificação: FALHOU.</strong></p>";
}
?>
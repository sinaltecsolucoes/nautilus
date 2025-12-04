<?php

/**
 * Autoload PSR-4 Manual
 * * Este script diz ao PHP como localizar classes baseadas nos Namespaces.
 * Exemplo: A classe "App\Controllers\PedidoController" será procurada em "app/Controllers/PedidoController.php"
 */

spl_autoload_register(function ($class) {

    // 1. Prefixo do namespace base do nosso projeto
    $prefix = 'App\\';

    // 2. Diretório base onde estão os arquivos desse namespace
    // __DIR__ retorna o diretório atual (app/Core), então subimos um nível para chegar em 'app/'
    // Ajuste conforme onde você salvar este arquivo. Se estiver em app/Core:
    $base_dir = __DIR__ . '/../';

    // 3. Verifica se a classe usa o nosso prefixo (App\)
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Se a classe não começar com "App\", não é problema nosso. O autoloader ignora.
        return;
    }

    // 4. Pega o nome relativo da classe (sem o prefixo App\)
    $relative_class = substr($class, $len);

    // 5. Mapeia o namespace para o caminho do arquivo:
    // - Substitui o prefixo do namespace pelo diretório base
    // - Substitui separadores de namespace (\) por separadores de diretório (/)
    // - Adiciona .php no final
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // 6. Se o arquivo existir, exige-o
    if (file_exists($file)) {
        require $file;
    } else {
        // Opcional: Log de erro se não encontrar (útil para debug)
        // error_log("Autoload erro: Arquivo não encontrado para classe $class em: $file");
    }
});

<?php

/**
 * Autoload PSR-4 Manual
 * Local: app/Core/Autoload.php
 * Suporta múltiplos namespaces e portabilidade.
 * Este script diz ao PHP como localizar classes baseadas nos Namespaces.
 * Exemplo: A classe "App\Controllers\PedidoController" será procurada em "app/Controllers/PedidoController.php"
 */

spl_autoload_register(function (string  $class) {

    // 1. Mapeamento de namespaces → diretórios base
    $prefixes = [
        'App\\' => __DIR__ . '/../', //classes principais do projeto
        'Lib\\' => __DIR__ . '/../../lib/', //libs próprias
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);

        //2. Verifica se a classe usa o nosso prefixo (App\), senao pula
        if (strncmp($prefix, $class, $len) !== 0) {
            // Se a classe não começar com "App\", não é problema nosso. O autoloader ignora.
            continue;
        }

        // 3. Pega o nome relativo da classe (sem o prefixo App\)
        $relativeClass = substr($class, $len);

        // 4. Mapeia o namespace para o caminho do arquivo:
        // - Substitui o prefixo do namespace pelo diretório base
        // - Substitui separadores de namespace (\) por separadores de diretório (/)
        // - Adiciona .php no final
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        // 5. Se o arquivo existir, exige-o
        if (file_exists($file)) {
            require $file;
            return;
        } else {
            // Em DEV: loga erro para debug
            if (getenv('APP_ENV') === 'dev') {
                error_log("Autoload erro: Arquivo não encontrado para classe $class em: $file");
            }
            return;
        }
    }
});

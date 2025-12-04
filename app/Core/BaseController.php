<?php

namespace App\Core;

/**
 * Class BaseController
 * Abstração para funcionalidades comuns a todos os controllers.
 */
abstract class BaseController
{
    /**
     * Helper "Smart Input"
     * Detecta se a requisição é JSON ou POST normal e retorna o valor desejado.
     * @param string $key Chave do dado.
     * @param int $filter Filtro PHP (default: FILTER_DEFAULT).
     * @return mixed
     */
    protected function getInput(string $key, int $filter = FILTER_DEFAULT)
    {
        // 1. Tenta pegar do JSON (Payload moderno)
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json) && isset($json[$key])) {
            return filter_var($json[$key], $filter);
        }

        // 2. Se falhar, tenta pegar do POST tradicional (Form Data)
        return filter_input(INPUT_POST, $key, $filter);
    }

    /**
     * Retorna uma resposta JSON padronizada e encerra a execução.
     * * @param mixed $data Dados a serem retornados.
     * @param int $statusCode Código HTTP (default: 200).
     */
    protected function jsonResponse($data, int $statusCode = 200): void
    {
        // Limpa buffers anteriores para garantir JSON limpo
        if (ob_get_length()) ob_clean();

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);

        echo json_encode($data);
        exit;
    }

    /**
     * Renderiza uma View.
     * * @param string $viewPath Caminho da view (ex: 'pedidos/index').
     * @param array $data Dados para a view.
     */
    protected function view(string $viewPath, array $data = []): void
    {
        // Extrai o array para variáveis locais ($title, $content, etc)
        extract($data);

        // Output Buffering para capturar o conteúdo da view
        ob_start();
        require ROOT_PATH . "/app/Views/{$viewPath}.php";
        $content = ob_get_clean();

        // Renderiza o layout principal
        require ROOT_PATH . '/app/Views/layout.php';
    }
}

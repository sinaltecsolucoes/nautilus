<?php

namespace App\Core;

/**
 * Class BaseController
 * Abstração para funcionalidades comuns a todos os controllers.
 */
abstract class BaseController
{
    /**
     * Cache do payload JSON para evitar múltiplas leituras de php://input 
     */
    private ?array $jsonPayload = null;

    /**
     * Helper "Smart Input"
     * Detecta se a requisição é JSON, POST ou GET e retorna o valor desejado.
     * @param string $key Chave do dado.
     * @param int $filter Filtro PHP (default: FILTER_DEFAULT).
     * @return mixed
     */
    protected function getInput(string $key, int $filter = FILTER_DEFAULT)
    {
        // 1. Cache do JSON
        if ($this->jsonPayload === null) {
            $raw = file_get_contents('php://input');
            $this->jsonPayload = $raw ? json_decode($raw, true) ?? [] : [];
        }

        // 2. JSON
        if (isset($this->jsonPayload[$key])) {
            return filter_var($this->jsonPayload[$key], $filter);
        }

        // 3. POST
        $postValue = filter_input(INPUT_POST, $key, $filter);
        if ($postValue !== null) {
            return $postValue;
        }

        // 4. GET
        return filter_input(INPUT_GET, $key, $filter);
    }

    /**
     * Retorna uma resposta JSON padronizada e encerra a execução.
     * @param mixed $data Dados a serem retornados.
     * @param int $statusCode Código HTTP (default: 200).
     */
    protected function jsonResponse($data, int $statusCode = 200): void
    {
        // Limpa buffers anteriores para garantir JSON limpo
        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        exit;
    }

    /**
     * Renderiza uma View com layout
     * @param string $viewPath Caminho da view (ex: 'pedidos/index').
     * @param array $data Dados para a view.
     * @param string $layout Layout principal (default: 'layout').
     */
    protected function view(string $viewPath, array $data = [], string $layout = 'layout'): void
    {
        // Extrai o array para variáveis locais ($title, $content, etc)
        extract($data);

        $viewFile = ROOT_PATH . "/app/Views/{$viewPath}.php";
        $layoutFile = ROOT_PATH . "/app/Views/{$layout}.php";

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo "Erro: View '{$viewFile}' não encontrada.";
            exit;
        }

        if (!file_exists($layoutFile)) {
            http_response_code(500);
            echo "Erro: Layout '{$layoutFile}' não encontrado.";
            exit;
        }

        // Output Buffering para capturar o conteúdo da view
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Renderiza o layout principal
        require $layoutFile;
    }
}

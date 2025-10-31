<?php

/**
 * CLASSE CONTROLLER: RelatorioController
 * Local: app/Controllers/RelatorioController.php
 * Descrição: Gerencia a visualização e geração de relatórios (PDF).
 */

require_once ROOT_PATH . '/app/Services/RelatorioService.php';
require_once ROOT_PATH . '/app/Models/ManutencaoModel.php'; // Model de exemplo para o relatório
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class RelatorioController
{
    private $relatorioService;
    private $manutencaoModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->relatorioService = new RelatorioService();
        $this->manutencaoModel = new ManutencaoModel();
    }

    /**
     * Exibe a página principal de Relatórios.
     */
    public function index()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Relatorios', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado.");
        }

        $data = [
            'title' => 'Módulo de Relatórios',
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(),
        ];

        ob_start();
        require_once ROOT_PATH . '/app/Views/relatorios/index.php'; // View a ser criada
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    /**
     * Rota AJAX para gerar o Relatório de Histórico de Manutenção em PDF.
     */
    public function gerarManutencaoPdf()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        // Permissão para "Criar" relatórios (gerar o PDF)
        if (!PermissaoService::checarPermissao($cargo, 'Relatorios', 'Criar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para gerar PDF.']);
            exit;
        }

        try {
            // 1. Obter os dados (simplesmente todos os registros por agora)
            $historico = $this->manutencaoModel->findAllRelatorioData(); // Método a ser criado no Model!

            // 2. Montar o HTML para o PDF
            $html = $this->renderHtmlManutencao($historico);

            // 3. Gerar e Salvar o PDF
            $pdfUrl = $this->relatorioService->gerarESalvarPDF($html, 'historico_manutencao');

            echo json_encode(['success' => true, 'pdf_url' => $pdfUrl]);
        } catch (\Exception $e) {
            error_log("Erro ao gerar PDF de Manutenção: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Falha ao gerar o relatório.']);
        }
    }

    /**
     * Gera o HTML base para o relatório de manutenção.
     */
    private function renderHtmlManutencao(array $historico): string
    {
        // Esta View será simples. Em um sistema real, seria um arquivo .php dedicado para o layout do PDF.
        $html = "<!DOCTYPE html><html><head><title>Relatório Manutenção</title>";
        $html .= "<style>body { font-family: Arial, sans-serif; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #333; padding: 8px; text-align: left; }</style>";
        $html .= "</head><body>";
        $html .= "<h1>Relatório de Histórico de Manutenção</h1>";
        $html .= "<p>Gerado por: " . ($_SESSION['user_nome'] ?? 'Sistema') . " em: " . date('d/m/Y H:i:s') . "</p>";
        $html .= "<table><thead><tr><th>Data</th><th>Veículo</th><th>Serviço</th><th>Valor</th></tr></thead><tbody>";

        foreach ($historico as $item) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($item['data_servico']) . "</td>";
            $html .= "<td>" . htmlspecialchars($item['placa']) . "</td>";
            $html .= "<td>" . htmlspecialchars($item['servico_peca']) . "</td>";
            $html .= "<td>R$ " . number_format($item['valor'], 2, ',', '.') . "</td>";
            $html .= "</tr>";
        }

        $html .= "</tbody></table></body></html>";
        return $html;
    }

    private function generateCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

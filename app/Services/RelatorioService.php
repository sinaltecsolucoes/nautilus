<?php

/**
 * CLASSE DE SERVIÇO: RelatorioService
 * Local: app/Services/RelatorioService.php
 * Descrição: Gerencia a conversão de HTML para PDF usando a biblioteca DomPDF.
 */

// NOTA: Em um projeto real, você incluiria o autoload do DomPDF aqui.
// require_once ROOT_PATH . '/vendor/autoload.php';
// use Dompdf\Dompdf;
// use Dompdf\Options;

require_once ROOT_PATH . '/config/config.php'; // Para BASE_URL, APP_NAME

class RelatorioService
{

    private $pdfOptions;

    public function __construct()
    {
        // Simulação da configuração (Em produção, usaria a classe Options do Dompdf)
        // $this->pdfOptions = new Options();
        // $this->pdfOptions->set('defaultFont', 'Helvetica');
    }

    /**
     * Gera e salva um relatório PDF no diretório de arquivamento.
     * @param string $htmlContent O HTML que será convertido em PDF.
     * @param string $nomeBase O nome base do arquivo (Ex: 'historico_manutencao').
     * @return string O caminho público (URL) do arquivo PDF salvo.
     */
    public function gerarESalvarPDF(string $htmlContent, string $nomeBase): string
    {

        // Simulação do Dompdf:
        // $dompdf = new Dompdf($this->pdfOptions);
        // $dompdf->loadHtml($htmlContent);
        // $dompdf->setPaper('A4', 'portrait');
        // $dompdf->render();
        // $pdfOutput = $dompdf->output();

        // 1. Definição do Caminho de Arquivamento
        $dataAtual = date('Ymd_His');
        $usuarioNome = strtolower(str_replace(' ', '_', $_SESSION['user_nome'] ?? 'sistema'));

        // A pasta de arquivamento deve ser acessível via web, mas controlada (Ex: dentro de public/arquivos/)
        $caminhoDiretorio = ROOT_PATH . '/public/arquivos/relatorios/';
        $nomeArquivo = "REL_{$nomeBase}_{$dataAtual}_{$usuarioNome}.pdf";
        $caminhoCompleto = $caminhoDiretorio . $nomeArquivo;

        // 2. Garantir que a pasta exista
        if (!is_dir($caminhoDiretorio)) {
            mkdir($caminhoDiretorio, 0777, true);
        }

        // 3. Simulação da Escrita do Arquivo PDF
        // Em um ambiente real, seria file_put_contents($caminhoCompleto, $pdfOutput);
        file_put_contents($caminhoCompleto, $this->simularConteudoPDF($htmlContent));

        // 4. Retorna o caminho público
        return BASE_URL . "/public/arquivos/relatorios/{$nomeArquivo}";
    }

    /**
     * Simula a saída de um arquivo PDF com um HTML básico (Apenas para teste).
     */
    private function simularConteudoPDF(string $html): string
    {
        return "Conteúdo Simulado de PDF Gerado a partir do HTML:\n\n" . strip_tags($html);
    }
}

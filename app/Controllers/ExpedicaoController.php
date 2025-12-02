<?php

/**
 * CLASSE CONTROLLER: ExpedicaoController
 * Local: app/Controllers/ExpedicaoController.php
 * Descrição: Gerencia o módulo de expedicao.
 */
require_once ROOT_PATH . '/app/Models/ExpedicaoModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php';
require_once ROOT_PATH . '/app/Models/VeiculoModel.php';
require_once ROOT_PATH . '/app/Models/EntidadeModel.php';
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';


class ExpedicaoController
{
    private $model;
    private $entidadeExpModel;
    private $veiculoExpModel;
    private $funcionarioExpModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->model = new ExpedicaoModel();
        $this->entidadeExpModel = new EntidadeModel();
        $this->veiculoExpModel = new VeiculoModel();
        $this->funcionarioExpModel = new FuncionarioModel();
    }

    public function index()
    {
        PermissaoService::checarPermissao($_SESSION['user_cargo'] ?? 'Visitante', 'Expedicao', 'Ler')
            ?: die("Acesso Negado.");

        $data = [
            'title' => 'Programação de Expedição',
            'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)),
            'pageScript' => 'expedicao'
        ];

        ob_start();
        require ROOT_PATH . '/app/Views/expedicao/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/Views/layout.php';
    }

    public function listar()
    {
        if (ob_get_length()) ob_clean();
        echo json_encode($this->model->listar($_POST));
        exit;
    }

    public function salvar()
    {
        // 1. Verifica se é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        // 2. Recebe o JSON enviado pelo JavaScript

        $linha = $_POST['linha'] ?? json_decode(file_get_contents('php://input'), true)['linha'] ?? null;

        // 3. Validação 
        if (!$linha) {
            echo json_encode(['success' => false, 'message' => 'Sem dados.']);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? 0;

        // Pega o ID que vem dentro do objeto header (igual na manutenção)
        $id = !empty($linha['id']) ? (int)$linha['id'] : 0;

        try {
            if ($id > 0) {
                // EDIÇÃO: Chama update
                $msg = $this->model->update($id, $linha);
            } else {
                // CRIAÇÃO: Chama create
                $msg = $this->model->create($linha, $userId);
            }

            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            // Captura erros do Model (ex: banco fora do ar)
            echo json_encode(['success' => false, 'message' => "Erro: " . $e->getMessage()]);
        }
    }

    public function deletar()
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $userId = $_SESSION['user_id'] ?? 0;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            return;
        }

        // Chama o método deletar do Model (passando array como estava no seu padrão anterior ou direto id)
        // Seu model espera um array $data['id'], vamos adaptar:
        $msg = $this->model->deletar(['id' => $id], $userId);
        echo json_encode(['success' => true, 'message' => $msg]);
    }

    private function processJson($action)
    {
        if (ob_get_length()) ob_clean();
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $userId = $_SESSION['user_id'] ?? 0;

        try {
            $result = $this->model->$action($data, $userId);
            echo json_encode(['success' => true, 'message' => $result]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function get()
    {
        if (ob_get_length()) ob_clean();

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            return;
        }

        $dados = $this->model->get($id);

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        }

        exit;
    }

    // --- ROTAS AUXILIARES SELECT2 ---
    public function getVeiculosOptions()
    {
        try {
            $term = $_POST['term'] ?? '';
            $options = $this->veiculoExpModel->getVeiculosOptions($term);
            echo json_encode(['results' => $options]);
        } catch (\Exception $e) {
            echo json_encode(['results' => [], 'error' => $e->getMessage()]);
        }
    }

    public function getPessoalOptions()
    {
        $term = $_POST['term'] ?? '';
        $options = $this->funcionarioExpModel->getFuncionariosOperacionais($term);
        echo json_encode(['results' => $options]);
    }

    public function getMotoristasOptions()
    {
        $term = $_POST['term'] ?? '';
        $options = $this->funcionarioExpModel->getMotoristasOptions($term);
        echo json_encode(['results' => $options]);
    }

    public function getClientesOptions()
    {
        $term = $_POST['term'] ?? '';
        $options = $this->entidadeExpModel->getClienteOptions($term);
        echo json_encode(['results' => $options]);
    }

    public function reordenar()
    {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $ordem = $input['ordem'] ?? [];
        $csrf  = $input['csrf_token'] ?? '';

        if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }

        if (empty($ordem)) {
            echo json_encode(['success' => false, 'message' => 'Nenhuma ordem recebida']);
            exit;
        }

        try {
            foreach ($ordem as $item) {
                $id = (int)($item['id'] ?? 0);
                $novaOrdem = (int)($item['ordem'] ?? 0);

                if ($id > 0 && $novaOrdem > 0) {
                    $this->model->atualizarOrdem($id, $novaOrdem);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso!']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno']);
        }
        exit;
    }

    /* public function gerarRomaneio()
    {
        $dompdfPath = ROOT_PATH . '/assets/libs/dompdf/autoload.inc.php';

        if (file_exists($dompdfPath)) {
            require_once $dompdfPath;
        } else {
            die("Erro: Biblioteca DomPDF não encontrada.");
        }

        $dataFiltro = $_GET['data'] ?? date('Y-m-d');
        $itens = $this->model->getDadosRelatorio($dataFiltro);

        $totalPL = 0;
        foreach ($itens as $i) {
            $totalPL += (float)$i['qtd_pl'];
        }

        $html = '
        <html>
        <head>
            <style>
                body { font-family: Helvetica, sans-serif; font-size: 11px; }
                h1 { text-align: center; margin-bottom: 5px; font-size: 16px; text-transform: uppercase; }
                .sub-header { text-align: center; margin-bottom: 20px; font-size: 12px; color: #555; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background-color: #e9ecef; font-weight: bold; border: 1px solid #999; padding: 6px; text-align: left; }
                td { border: 1px solid #999; padding: 6px; vertical-align: top; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .fw-bold { font-weight: bold; }
            </style>
        </head>
        <body>
            <h1>Romaneio de Expedição Diária</h1>
            <div class="sub-header">Data: ' . date('d/m/Y', strtotime($dataFiltro)) . '</div>

            <table>
                <thead>
                    <tr>
                        <th width="50" class="text-center">Hora</th>
                        <th width="60">OS</th>
                        <th>Cliente / Destino</th>
                        <th>Volumes / Caixas</th>
                        <th width="80" class="text-right">Qtd PL</th>
                        <th>Veículo</th>
                        <th>Equipe (Mot/Téc)</th>
                        <th>Obs</th>
                    </tr>
                </thead>
                <tbody>';

        if (empty($itens)) {
            $html .= '<tr><td colspan="8" class="text-center" style="padding: 20px;">Nenhuma entrega programada.</td></tr>';
        } else {
            foreach ($itens as $row) {
                // Formatação PL
                $v = (float)$row['qtd_pl'];
                $plFormatado = ($v < 1)
                    ? number_format($v * 1000, 0, '', '.') . ' mil'
                    : number_format($v, 3, ',', '.') . ' M';

                // Formatação Equipe (Agora usando os campos da tabela única)
                $equipe = $row['motorista_nome'] . '<br><small>' . $row['tecnico_nome'] . '</small>';

                $html .= '
                    <tr>
                        <td class="text-center">' . ($row['hora_formatada'] ?: '-') . '</td>
                        <td>' . $row['os'] . '</td>
                        <td><strong>' . $row['cliente_nome'] . '</strong></td>
                        <td>' . $row['caixas_usadas'] . '</td>
                        <td class="text-right fw-bold">' . $plFormatado . '</td>
                        <td>' . $row['veiculo_nome'] . '</td>
                        <td>' . $equipe . '</td>
                        <td>' . ($row['observacao'] ?: '-') . '</td>
                    </tr>';
            }

            // Totais
            $html .= '
                <tr style="background-color: #d1ecf1;">
                    <td colspan="4" class="text-right fw-bold">TOTAL GERAL:</td>
                    <td class="text-right fw-bold">' . number_format($totalPL, 3, ',', '.') . ' M</td>
                    <td colspan="3"></td>
                </tr>';
        }

        $html .= '</tbody></table></body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream("Romaneio_$dataFiltro.pdf", ["Attachment" => false]);
        exit;
    } */
    public function gerarRomaneio()
    {
        // 1. Carrega DomPDF
        $dompdfPath = ROOT_PATH . '/assets/libs/dompdf/autoload.inc.php';
        if (!file_exists($dompdfPath)) die("Erro: DomPDF não encontrado.");
        require_once $dompdfPath;

        // 2. Busca Dados
        $dataFiltro = $_GET['data'] ?? date('Y-m-d');
        $itens = $this->model->getDadosRelatorio($dataFiltro);
        $dataFormatada = date('d/m/Y', strtotime($dataFiltro));

        // 3. Monta o HTML
        // CSS focado em replicar o layout da imagem (Bordas pretas, fontes compactas)
        $html = '
        <html>
        <head>
            <style>
                @page { margin: 20px; }
                body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #000; }
                
                .header-geral { 
                    text-align: center; font-weight: bold; font-size: 14px; 
                    background-color: #ddd; border: 2px solid #000; padding: 5px; margin-bottom: 2px;
                }
                .header-data {
                    text-align: center; font-weight: bold; font-size: 12px;
                    border: 2px solid #000; border-top: none; padding: 3px; margin-bottom: 10px;
                    background-color: #f9f9f9;
                }

                /* Estilo do Card (Cada Entrega) */
                .card-entrega {
                    width: 100%; border: 2px solid #000; margin-bottom: 10px; page-break-inside: avoid;
                }
                
                .tbl-layout { width: 100%; border-collapse: collapse; }
                .tbl-layout td { vertical-align: middle; border: 1px solid #000; padding: 2px 4px; }
                
                /* Coluna da Logo */
                .col-logo { width: 80px; text-align: center; vertical-align: middle; background-color: #fff; }
                .img-logo { max-width: 70px; max-height: 60px; }
                
                /* Labels e Valores */
                .lbl { font-weight: bold; width: 85px; background-color: #f0f0f0; }
                .val { font-weight: bold; }
                
                /* Texto Grande (OS, Cliente) */
                .txt-lg { font-size: 11px; }
                .txt-xl { font-size: 12px; font-weight: 900; }
                
                /* Caixa de Divisões */
                .box-divisoes { height: 100%; vertical-align: top; }
            </style>
        </head>
        <body>
            <div class="header-geral">EXPEDIÇÃO DE VENDAS</div>
            <div class="header-data">DATA: ' . $dataFormatada . '</div>';

        if (empty($itens)) {
            $html .= '<div style="text-align:center; padding:20px; border:1px solid #000;">Nenhuma expedição programada para esta data.</div>';
        } else {
            foreach ($itens as $row) {
                // Formatações
                $plFormatado = ((float)$row['qtd_pl'] < 1)
                    ? number_format((float)$row['qtd_pl'] * 1000, 0, '', '.') . ' mil'
                    : number_format((float)$row['qtd_pl'], 3, ',', '.') . ' M';

                // Caminho da Logo (Ajuste conforme seu projeto)
                // Se a imagem não aparecer, use base64 ou caminho absoluto do sistema (C:/xampp...)
                $veiculoNome = strtoupper($row['veiculo_nome'] ?? 'N/D');
                $motoristaNome = strtoupper($row['motorista_nome'] ?? 'N/D');
                $tecnicoNome = strtoupper($row['tecnico_nome'] ?? 'N/D');
                $cidadeUf = strtoupper($row['cidade_uf'] ?? '-');
                $os = $row['os'] ?? '-';

                $logoPath = 'assets/img/logo.png'; // Coloque sua logo aqui
                $imgTag = file_exists($logoPath) ? '<img src="' . $logoPath . '" class="img-logo">' : 'LOGO';

                // HTML DO CARD (Replica exata da estrutura da imagem)
                $html .= '
                <div class="card-entrega">
                    <table class="tbl-layout">
                        <tr>
                            <td class="col-logo" rowspan="6">' . $imgTag . '</td>
                            
                            <td class="lbl">EXP.:</td>
                            <td class="val">' . $dataFormatada . '</td>
                            
                            <td class="lbl">O.S.:</td>
                            <td class="val txt-lg">' . $os . '</td>
                        </tr>
                        
                        <tr>
                            <td class="lbl">EMBARQUE:</td>
                            <td class="val txt-lg">' . ($row['hora_formatada'] ?: '-') . '</td>
                            
                            <td class="lbl">CLIENTE:</td>
                            <td class="val txt-lg">' . strtoupper($row['cliente_nome']) . '</td>
                        </tr>

                        <tr>
                            <td class="lbl">LAB / CICLO:</td>
                            <td class="val">-</td> <td class="lbl">ENDEREÇO:</td>
                            <td class="val">' . $cidadeUf . '</td>
                        </tr>

                        <tr>
                            <td class="lbl">TANQUE:</td>
                            <td class="val">-</td>
                            
                            <td class="lbl">CONTATO:</td>
                            <td class="val">-</td>
                        </tr>

                        <tr>
                            <td class="lbl">VEÍCULO:</td>
                            <td class="val" style="font-size:9px;">' . $veiculoNome . '</td>
                            
                            <td class="lbl">QTD (PLs):</td>
                            <td class="val txt-xl">' . $plFormatado . '</td>
                        </tr>

                        <tr>
                            <td class="lbl">MOTORISTA:</td>
                            <td class="val">' . $motoristaNome . '</td>
                            
                            <td class="lbl">SALINIDADE:</td>
                            <td class="val">-</td>
                        </tr>

                        <tr>
                            <td class="lbl" style="border-top: 2px solid #000;">TÉCNICO:</td>
                            <td class="val" style="border-top: 2px solid #000;" colspan="2">' . strtoupper($row['tecnico_nome'] ?? '-') . '</td>
                            
                            <td class="lbl" style="border-top: 2px solid #000;">VOLUMES:</td>
                            <td class="val box-divisoes" style="border-top: 2px solid #000;">
                                <div style="font-size: 11px;">' . nl2br($row['caixas_usadas'] ?? '-') . '</div>
                                ' . ($row['observacao'] ? '<div style="margin-top:2px; font-size:9px; color:#555; font-style:italic;">Obs: ' . $row['observacao'] . '</div>' : '') . '
                            </td>
                        </tr>
                    </table>
                </div>';
            }
        }

        $html .= '</body></html>';

        // 4. Gera PDF
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait'); // Retrato, pois os cards são empilhados
        $dompdf->render();

        // Nome do arquivo
        $dompdf->stream("Romaneio_Expedicao_$dataFiltro.pdf", ["Attachment" => false]);
        exit;
    }
}

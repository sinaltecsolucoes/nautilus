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

    public function gerarRomaneio()
    {
        // 1. Carrega DomPDF
        $dompdfPath = ROOT_PATH . '/assets/libs/dompdf/autoload.inc.php';
        if (!file_exists($dompdfPath)) {
            // Tenta caminho alternativo comum
            $dompdfPath = ROOT_PATH . '/assets/libs/dompdf/vendor/autoload.php';
            if (!file_exists($dompdfPath)) die("Erro: DomPDF não encontrado.");
        }
        require_once $dompdfPath;

        // 2. Busca Dados
        $dataFiltro = $_GET['data'] ?? date('Y-m-d');
        $itens = $this->model->getDadosRelatorio($dataFiltro);
        $dataFormatada = date('d/m/Y', strtotime($dataFiltro));

        // Configuração de Logo
        // 1. Aponta para o caminho físico no servidor (C:\xampp\htdocs\nautilus\assets\img\logo.png)
        $logoPath = ROOT_PATH . '/assets/img/logo.png';
        $imgTag = '<b>LOGO</b>'; // Fallback padrão

        if (file_exists($logoPath)) {
            // 2. Converte a imagem para dados brutos (Base64)
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

            // 3. Cria a tag IMG com o código embutido
            $imgTag = '<img src="' . $base64 . '" style="max-width:90%; max-height:60px;">';
        } else {
            // Debug: se não achar, avisa onde tentou procurar (útil para você ver o erro)
            // $imgTag = 'Img não encontrada em: ' . $logoPath; 
        }

        // 3. Monta HTML
        $html = '
        <html>
        <head>
            <style>
                @page { margin: 20px; }
                body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; }
                
                /* Título Principal */
                .main-title { 
                    text-align: center; font-weight: bold; font-size: 14px; 
                    background-color: #ddd; border: 2px solid #000; padding: 5px; margin-bottom: 5px;
                }

                /* Estrutura do Card (Tabela) */
                .card-table {
                    width: 100%; 
                    border-collapse: collapse; 
                    border: 2px solid #000; 
                    margin-bottom: 5px;
                    page-break-inside: avoid; /* Evita quebrar o card no meio da página */
                    table-layout: fixed;
                }
                
                .card-table td {
                    border: 1px solid #000;
                    padding: 3px 5px;
                    vertical-align: middle;
                    overflow: hidden; /* Previne que texto grande estoure o layout fixo */
                }

                /* Estilo do Label dentro da célula */
                .lbl { font-weight: 900; color: #000; margin-right: 5px; }

                /* Estilos Específicos das Células */
                .col-label { background-color: #f2f2f2; font-weight: bold;}
                .col-value { font-weight: bold; }
                .text-center { text-align: center; }

                /* ESTILO PARA O ESPAÇO COLORIDO */
                .separator {
                    width: 100%;
                    height: 10px;           /* Altura da faixa colorida */
                    background-color: #ccc; /* Cor da faixa (Preto, Cinza #ccc, etc) */
                    margin-bottom: 5px;    /* Espaço em branco depois da faixa antes da próxima tabela */
                }
                
                /* Logo */
                .cell-logo { text-align: center; background-color: #fff; }

                /* Texto Grande para Destaques */
                .txt-lg { font-size: 11px; }
                .txt-xl { font-size: 12px; font-weight: 900; }

            </style>
        </head>
        <body>
            <div class="main-title">EXPEDIÇÃO DE VENDAS - ' . $dataFormatada . '</div>
            <div class="separator"></div>';

        if (empty($itens)) {
            $html .= '<div style="text-align:center; padding:30px; border:1px solid #000;">Nenhuma programação encontrada para esta data.</div>';
        } else {
            foreach ($itens as $row) {
                // Tratamento de Dados
                $veiculo = strtoupper($row['veiculo_nome'] ?? '-');
                $motorista = strtoupper($row['motorista_nome'] ?? '-');
                $tecnico = strtoupper($row['tecnico_nome'] ?? '-');
                $cliente = strtoupper($row['cliente_nome'] ?? '-');
                $endereco = strtoupper($row['cidade_uf'] ?? '-');
                $os = $row['os'] ?? '-';
                $horario = $row['hora_formatada'] ?? '-';

                // Formatação Qtd PL
                $qtdPl = (float)$row['qtd_pl'];
                $plFormatado = ($qtdPl < 1000)
                    ? number_format($qtdPl * 1000, 0, '', '.') . ' mil'
                    : number_format($qtdPl * 1000, 0, ',', '.') . ' milhões';

                // Caixas / Divisões
                $caixas = $row['caixas_usadas'] ?? '-';

                // --- CONSTRUÇÃO DA TABELA 5 COLUNAS x 10 LINHAS ---
                $html .= '
                <table class="card-table" style="border-collapse: collapse; width: 100%;">


                    <!-- LINHA 1 -->
                                     
                    <tr>
                        <td class="cell-logo" rowspan="2" width="12%">' . $imgTag . '</td>
                        
                        <td  width="20%"><span class="lbl">EXP.:</span> ' . $dataFormatada . '</td>
                        <td class="col-label" width="15%">O.S.:</td>
                        <td class="col-value txt-lg" width="53%">' . $os . '</td>
                    </tr>
                
                    <!-- LINHA 2 -->
                    <tr>
                        <td><span class="lbl">EMBARQUE:</span> ' . $horario . '</td>
                        
                        <td class="col-label">CLIENTE:</td>                        
                        <td class="col-value txt-lg">' . $cliente . '</td>
                    </tr>

                    <!-- LINHA 3 -->
                    <tr>
                        <td class="col-label">LAB.:</td>                        
                        <td class="col-value text-center">-</td>
                        
                        <td class="col-label">ENDEREÇO:</td>                        
                        <td class="col-value">' . $endereco . '</td>
                    </tr>

                    <!-- LINHA 4 -->
                    <tr>
                        <td class="col-label">CICLO:</td>                        
                        <td class="col-value text-center">-</td>
                        
                        <td class="col-label">CONTATO:</td>                        
                        <td class="col-value">-</td>
                    </tr>

                    <!-- LINHA 5 -->
                    <tr>
                        <td class="col-label" rowspan="2">TANQUE:</td>                        
                        <td class="col-value text-center" rowspan="2">-</td>
                        
                        <td class="col-label">QUANT. PL:</td>                        
                        <td class="col-value txt-xl">' . $plFormatado . '</td>
                    </tr>

                    <!-- LINHA 6 -->
                    <tr>
                        <td><span class="lbl">SALINIDADE:</span> -</td>
                          <td rowspan="4"></td>       
                    </tr>

                    <!-- LINHA 7 -->
                    <tr>
                        <td class="col-label">VEÍCULO:</td>                        
                        <td class="col-value">' . $veiculo . '</td>
                        
                        <td class="col-label text-center align-middle">DIVISÕES:</td>
                                                                        
                    </tr>

                    <!-- LINHA 8 -->
                    <tr>
                        <td class="col-label">MOTORISTA:</td>                        
                        <td class="col-value">' . $motorista . '</td>
                        <td class="val box-divisoes" rowspan="2" style="text-align: center; vertical-align: middle;">
                             <div style="font-size: 9px; font-weight: bold;">' . nl2br($caixas) . '</div>
                        </td>
                    </tr>

                    <!-- LINHA 9 -->
                    <tr>
                        <td class="col-label" style="vertical-align: middle;">TÉCNICO:</td>                        
                        <td class="col-value" style="vertical-align: middle;">' . $tecnico . '</td>                        
                    </tr>
                </table>
                <div class="separator"></div>';
            }
        }






























        $html .= '</body></html>';

        // 4. Gera PDF
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait'); // Retrato acomoda melhor esse layout vertical
        $dompdf->render();
        $dompdf->stream("Romaneio_$dataFiltro.pdf", ["Attachment" => false]);
        exit;
    } 
}

<?php
// app/Controllers/PermissaoController.php

require_once ROOT_PATH . '/app/Models/PermissaoModel.php';
require_once ROOT_PATH . '/app/Models/FuncionarioModel.php'; // Para pegar os cargos
require_once ROOT_PATH . '/app/Services/PermissaoService.php';

class PermissaoController
{
    private $permissaoModel;
    private $funcionarioModel;

    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) session_start();
        $this->permissaoModel = new PermissaoModel();
        $this->funcionarioModel = new FuncionarioModel();
    }

    public function index()
    {
        $cargoLogado = $_SESSION['user_cargo'] ?? 'Visitante';

        // Apenas Admin ou quem tiver permissão explicita pode acessar
        if (!PermissaoService::checarPermissao($cargoLogado, 'Permissoes', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado.");
        }

        // 1. Definição dos Módulos e Ações do Sistema (Catálogo)
        $modulos = [
            'Dashboard'      => ['Ler'], // Dashboard geralmente é só Ler
            'Entidades'      => ['Criar', 'Ler', 'Alterar', 'Deletar'],
            'Funcionarios'   => ['Criar', 'Ler', 'Alterar', 'Deletar'],
            'Abastecimentos' => ['Criar', 'Ler', 'Alterar', 'Deletar'], // Nosso novo módulo
            'Permissoes'     => ['Ler', 'Alterar'], // Permissão para mexer nas permissões
            'Relatorios'     => ['Ler']
        ];

        // 2. Busca os Cargos Disponíveis no sistema (via Enum ou Model)
        $cargos = $this->funcionarioModel->getCargosOptions();
        // Remove 'Administrador' pois ele tem acesso total via código
        $cargos = array_filter($cargos, fn($c) => $c !== 'Administrador');

        // 3. Busca todas as permissões salvas no banco
        // Formato esperado do retorno: ['Gerente' => ['Entidades' => ['Ler' => true]]]
        $permissoesAtuais = $this->permissaoModel->getAllPermissoesMatriz();

        $data = [
            'title' => 'Matriz de Permissões',
            'modulos' => $modulos,
            'cargos' => $cargos,
            'permissoesAtuais' => $permissoesAtuais,
            'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)),
            'pageScript' => 'permissoes'
        ];

        $_SESSION['csrf_token'] = $data['csrf_token'];

        ob_start();
        require_once ROOT_PATH . '/app/Views/permissoes/index.php';
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    // Método AJAX para salvar uma única permissão (Switch)
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        $cargo = $_POST['cargo'] ?? '';
        $modulo = $_POST['modulo'] ?? '';
        $acao = $_POST['acao'] ?? '';
        $status = $_POST['status'] === 'true' ? 1 : 0;

        if ($this->permissaoModel->updatePermissao($cargo, $modulo, $acao, $status)) {
            echo json_encode(['success' => true, 'message' => 'Permissão atualizada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar']);
        }
    }
    
    /**
     * Salva a matriz completa de permissões (Lote).
     */
    public function salvar()
    {
        // Verifica se é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        // Lê o JSON enviado pelo JavaScript
        $input = json_decode(file_get_contents('php://input'), true);
        $listaPermissoes = $input['permissoes'] ?? [];

        if (empty($listaPermissoes)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum dado recebido.']);
            exit;
        }

        $erros = 0;

        // Inicia uma transação para garantir integridade (opcional mas recomendado)
        // Como o PermissaoModel não expõe beginTransaction direto aqui, faremos o loop simples.

        foreach ($listaPermissoes as $p) {
            $cargo = $p['cargo'];
            $modulo = $p['modulo'];
            $acao = $p['acao'];
            $status = (int)$p['status'];

            // Chama o Model para atualizar (Upsert)
            if (!$this->permissaoModel->updatePermissao($cargo, $modulo, $acao, $status)) {
                $erros++;
            }
        }

        if ($erros === 0) {
            echo json_encode(['success' => true, 'message' => 'Todas as permissões foram atualizadas!']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Salvo com avisos. Alguns registros podem ter falhado.']);
        }
    }
}

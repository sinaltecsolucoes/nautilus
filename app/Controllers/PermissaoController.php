<?php

/**
 * CLASSE CONTROLLER: PermissaoController
 * Local: app/Controllers/PermissaoController.php
 * Descrição: Gerencia a interface e a lógica de atualização das permissões (ACL/RBAC).
 */

// Inclui Models e Services
require_once ROOT_PATH . '/app/Models/PermissaoModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php'; // Para verificação de permissão
require_once ROOT_PATH . '/app/Controllers/AuthController.php'; // Para a segurança


class PermissaoController
{
    private $permissaoModel;

    public function __construct()
    {
        $this->permissaoModel = new PermissaoModel();

        // 1. SEGURANÇA: Inicia a sessão para verificar o cargo
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Exibe a matriz de gestão de permissões.
     */
    public function index()
    {
        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // APENAS Administradores podem Ler este módulo (Gestão de Permissões é sensível)
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Alterar')) {
            // Usamos 'Funcionarios'/'Alterar' como regra inicial para gestão de usuários,
            // mas futuramente podemos ter um módulo 'Permissoes'

            // Se negado, redireciona ou exibe erro
            http_response_code(403); // Acesso Proibido
            die("Acesso Negado: Você não tem permissão para gerenciar as permissões do sistema.");
        }

        // Obtém todos os dados estruturados do Model
        $permissoes = $this->permissaoModel->getAllPermissoes();

        $data = [
            'title' => 'Gestão de Permissões (ACL)',
            'permissoes' => $permissoes,
            // Extrai a lista única de Cargos e Ações para o cabeçalho da tabela na View
            'cargos' => array_keys(current($permissoes)['Entidades'] ?? []),
            'acoes'  => ['Criar', 'Ler', 'Alterar', 'Deletar'] // Ordem fixada
        ];

        // Processo de injeção da View no Layout
        ob_start();
        require_once __DIR__ . '/../Views/permissoes/index.php'; // A View será criada a seguir
        $content = ob_get_clean();
        require_once __DIR__ . '/../Views/layout.php';
    }

    /**
     * Processa a requisição AJAX para atualizar o status de uma permissão.
     */
    public function update()
    {
        // Aplica o mesmo check de segurança
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Funcionarios', 'Alterar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso Negado.']);
            exit;
        }

        // Espera-se dados via POST/JSON: { id: 1, status: true/false }
        $id = $_POST['id'] ?? null;
        $status = $_POST['status'] ?? null;

        if (is_null($id) || is_null($status)) {
            http_response_code(400); // Requisição inválida
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
            exit;
        }

        // Converte o status (true/false) para 1/0
        $new_status = $status === 'true' || $status === '1';

        // Atualiza o banco de dados
        $result = $this->permissaoModel->updatePermissaoStatus($id, $new_status);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Permissão atualizada com sucesso.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Falha ao atualizar a permissão no DB.']);
        }
    }
}

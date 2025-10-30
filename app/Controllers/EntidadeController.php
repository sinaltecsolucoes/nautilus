<?php

/**
 * CLASSE CONTROLLER: EntidadeController
 * Local: app/Controllers/EntidadeController.php
 * Descrição: Gerencia as rotas CRUD e as chamadas de API para Entidades.
 */

// Inclui Models e Services
require_once ROOT_PATH . '/app/Models/EntidadeModel.php';
require_once ROOT_PATH . '/app/Services/PermissaoService.php';
require_once ROOT_PATH . '/app/Services/EntidadeService.php'; // Nosso serviço CNPJ/CEP

class EntidadeController
{
    private $entidadeModel;

    public function __construct()
    {
        // Garante que a sessão esteja ativa para ACL
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->entidadeModel = new EntidadeModel();
    }

    // =================================================================
    // FUNÇÕES DE VIEW (GET)
    // =================================================================

    /**
     * Exibe a lista de Entidades (Clientes, Fornecedores, ou Transportadoras).
     */
    public function index()
    {
        $pageType = $this->getPageTypeFromRoute();
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // Exemplo de VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            http_response_code(403);
            die("Acesso Negado: Você não tem permissão para visualizar este módulo.");
        }

        $data = [
            'title' => ucfirst($pageType) . 's', // Título dinâmico
            'pageType' => $pageType,
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken() // Gera token
        ];

        // RENDERIZAÇÃO
        ob_start();
        // A View agora será dinâmica (entidades/index.php ou lista_entidades.php)
        require_once ROOT_PATH . '/app/Views/entidades/lista_entidades.php';
        $content = ob_get_clean();
        require_once ROOT_PATH . '/app/Views/layout.php';
    }

    // =================================================================
    // FUNÇÕES DE AÇÃO (AJAX/POST)
    // =================================================================

    /**
     * Rota para buscar dados de CNPJ/CEP via AJAX.
     * O JS chamará esta rota, que por sua vez chama o Service.
     */
    public function buscarApiDados()
    {
        // A segurança (CSRF) deve ser adicionada aqui para POST!
        // Implementação básica:
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $termo = trim($_POST['termo'] ?? '');
        $tipo = $_POST['tipo'] ?? ''; // 'cnpj' ou 'cep'

        if (empty($termo)) {
            echo json_encode(['success' => false, 'message' => 'Termo de busca vazio.']);
            exit;
        }

        if ($tipo === 'cnpj') {
            $dados = EntidadeService::buscarDadosPJPorCnpj($termo);
        } elseif ($tipo === 'cep') {
            $dados = EntidadeService::buscarEnderecoPorCep($termo);
        } else {
            $dados = false;
        }

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Dados não encontrados ou API inacessível.']);
        }
    }

    // Futuramente: listarEntidades(), salvarEntidade(), etc., seguirão aqui.

    // =================================================================
    // AUXILIARES
    // =================================================================

    private function getPageTypeFromRoute(): string
    {
        $route = $_GET['route'] ?? 'clientes'; // Assume padrão
        if (strpos($route, 'fornecedores') !== false) return 'fornecedor';
        if (strpos($route, 'transportadoras') !== false) return 'transportadora';
        return 'cliente'; // Padrão
    }

    private function generateCsrfToken()
    {
        // Implementar geração de token e salvar em $_SESSION
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Retorna a lista de entidades para o DataTables (AJAX POST).
     */
    public function listarEntidades()
    {
        // 1. VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            http_response_code(403);
            echo json_encode(["error" => "Acesso negado."]);
            exit;
        }

        // 2. Coleta parâmetros e chama o Model
        try {
            $output = $this->entidadeModel->findAllForDataTable($_POST); // Usa o método do Model criado no seu repositório de referência
            echo json_encode($output);
        } catch (\Exception $e) {
            error_log("Erro em listarEntidades: " . $e->getMessage());
            echo json_encode(["error" => "Erro ao processar dados."]);
        }
    }

    /**
     * Salva ou Atualiza uma entidade (POST).
     */
    public function salvarEntidade()
    {
        $id = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
        $acao = $id ? 'Alterar' : 'Criar';
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0;

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', $acao)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado para esta ação.']);
            exit;
        }

        // Simulação de dados: O Model espera os dados de Entidade e Endereço Principal separados.
        $dataEntidade = $_POST;
        // Endereço Principal (sempre é o tipo 'Principal' na primeira aba)
        $dataEnderecos = [array_merge($_POST, ['tipo_endereco' => 'Principal'])];

        try {
            if ($id) {
                // CORREÇÃO: Passando AGORA os 4 argumentos: $id, $dataEntidade, $dataEnderecos, $userId
                $this->entidadeModel->update($id, $dataEntidade, $dataEnderecos, $userId);
                $message = 'Entidade atualizada com sucesso!';
                $newId = $id;
            } else {
                // CREATE: Cria a entidade e seu endereço principal
                $newId = $this->entidadeModel->create($dataEntidade, $dataEnderecos);
                $message = 'Entidade cadastrada com sucesso!';
            }

            echo json_encode(['success' => true, 'message' => $message, 'ent_codigo' => $newId]);
        } catch (\PDOException $e) {
            // Tratamento de erro de CNPJ/CPF duplicado (código 23000)
            if ($e->getCode() === '23000') {
                $message = "Erro: CNPJ/CPF já existe no cadastro.";
            } else {
                $message = "Erro no banco de dados. " . $e->getMessage();
            }
            echo json_encode(['success' => false, 'message' => $message]);
        }
    }

    /**
     * Busca dados de uma entidade para pré-preenchimento do formulário de edição (POST).
     */
    public function getEntidade()
    {
        $id = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $dados = $this->entidadeModel->find($id); // Usa o método find do Model

        if ($dados) {
            echo json_encode(['success' => true, 'data' => $dados]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Entidade não encontrada.']);
        }
    }

    /**
     * Rota AJAX para retornar opções de Clientes ativos para Select2.
     * Retorna no formato esperado pelo Select2: { results: [...] }
     */
    public function getClientesOptions()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        // A permissão para 'Ler' Entidades já deve bastar para esta busca
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            http_response_code(403);
            echo json_encode(["results" => [], "error" => "Acesso negado."]);
            exit;
        }

        // O Select2 envia o termo de busca via GET (ou POST, dependendo da configuração)
        $term = $_GET['term'] ?? '';

        try {
            // O método getClienteOptions precisa ser adicionado ao EntidadeModel!
            $options = $this->entidadeModel->getClienteOptions($term);

            // O Select2 espera os dados dentro de uma chave 'results'.
            // Vamos formatar para [ { id: ID_ENTIDADE, text: 'NOME FANTASIA (Cód: X)' } ]

            $results = array_map(function ($item) {
                // Adapta o nome para o formato Select2
                $nomeDisplay = $item['nome_fantasia'] ?? $item['razao_social'];
                return [
                    'id' => $item['id'],
                    'text' => $nomeDisplay . ' (Cód: ' . ($item['codigo_interno'] ?? 'N/A') . ')'
                ];
            }, $options);

            echo json_encode(['results' => $results]);
        } catch (\Exception $e) {
            error_log("Erro em getClientesOptions: " . $e->getMessage());
            echo json_encode(['results' => [], 'error' => 'Erro ao buscar clientes.']);
        }
    }
}

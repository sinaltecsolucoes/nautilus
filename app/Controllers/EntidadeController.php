<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\EntidadeModel;
use App\Services\PermissaoService;
use App\Services\EntidadeService;


class EntidadeController extends BaseController
{
    private EntidadeModel $entidadeModel; 

    public function __construct(?EntidadeModel $entidadeModel = null)
    {
        // Injeção de dependência
        $this->entidadeModel = $entidadeModel ?? new EntidadeModel();
    } 

    // =================================================================
    // FUNÇÕES DE VIEW (GET)
    // =================================================================

    /**
     * Exibe a lista de Entidades (Clientes, Fornecedores, ou Transportadoras).
     */
    public function index()
    {
        $pageType = $this->getPageTypeFromRoute(); // 'Cliente', 'Fornecedor', 'Transportadora'
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            $this->jsonResponse(
                ['success' => 'false', 'message' => 'Acesso Negado: Você não tem permissão para visualizar este módulo.'],
                403
            );
        }

        $data = [
            'title'      => ucfirst($pageType) . 's', // Título dinâmico
            'pageType'   => $pageType,
            'csrf_token' => $_SESSION['csrf_token'] ?? $this->generateCsrfToken(), // Gera token
            'pageScript' => 'entidades',
        ];

        // RENDERIZAÇÃO
        $this->view('entidades/lista_entidades', $data);
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

        // Validação CSRF
        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Token CSRF inválido.'],
                403
            );
        }

        $termo = trim($_POST['termo'] ?? '');
        $tipo = $_POST['tipo'] ?? ''; // 'cnpj' ou 'cep'

        if (empty($termo)) {
            $this->jsonResponse(['success' => false, 'message' => 'Termo de busca vazio.']);
        }

        switch ($tipo) {
            case 'cnpj':
                $dados = EntidadeService::buscarDadosPJPorCnpj($termo);
                break;
            case 'cep':
                $dados = EntidadeService::buscarEnderecoPorCep($termo);
                break;
            default:
                $this->jsonResponse(
                    ['success' => false, 'message' => 'Tipo de busca inválido.'],
                    400
                );
        }

        echo json_encode(
            $dados
                ? ['success' => true, 'data' => $dados]
                : ['success' => false, 'message' => 'Dados não encontrados ou API inacessível.']
        );
    }

    /**
     * Retorna a lista de entidades para o DataTables.
     */
    public function listarEntidades()
    {
        // 1. VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            $this->jsonResponse(
                ["error" => "Acesso negado."],
                403
            );
        }

        // Validação CSRF
        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['error' => 'Token CSRF inválido.'],
                403
            );
        }

        // 2. Coleta de Parâmetros do DataTables (POST)
        $draw         = filter_input(INPUT_POST, 'draw', FILTER_VALIDATE_INT) ?? 1;
        $start        = filter_input(INPUT_POST, 'start', FILTER_VALIDATE_INT) ?? 0;
        $length       = filter_input(INPUT_POST, 'length', FILTER_VALIDATE_INT) ?? 10;
        $searchValue  = $_POST['search']['value'] ?? '';
        $tipoEntidade = filter_input(INPUT_POST, 'tipo_entidade', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'cliente';

        // Captura a ordenação enviada pelo DataTables
        $order = $_POST['order'] ?? [];

        try {
            $resultado = $this->entidadeModel->findAllForDataTable([
                'start'         => $start,
                'length'        => $length,
                'search'        => ['value' => $searchValue],
                'tipo_entidade' => $tipoEntidade,
                'order'         => $order, // Passando ordenação para o Model
                'draw'          => $draw
            ]);

            // Usa as chaves exatas retornadas pelo modelo
            $this->jsonResponse([
                'draw'            => $draw,
                'recordsTotal'    => $resultado['recordsTotal'],
                'recordsFiltered' => $resultado['recordsFiltered'],
                'data'            => $resultado['data']
            ]);
        } catch (\Exception $e) {
            error_log("Erro no listarEntidades: " . $e->getMessage());
            $this->jsonResponse(
                ['success' => false, 'error' => 'Erro interno ao buscar dados.'],
                500
            );
        }
    }

    // =================================================================
    // AUXILIARES
    // =================================================================

    private function getPageTypeFromRoute(): string
    {
        $route = $_GET['route'] ?? 'clientes'; // Assume padrão
        return match (true) {
            str_contains($route, 'fornecedores') => 'fornecedor',
            str_contains($route, 'transportadoras') => 'transportadora',
            default => 'cliente',
        };
    }


    /**
     * Salva ou Atualiza uma entidade (POST).
     */
    public function salvarEntidade(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Método não permitido.'],
                405
            );
        }

        // Validação CSRF
        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['error' => 'Token CSRF inválido.'],
                403
            );
        }

        // 1. HIGIENIZAÇÃO GERAL (Aplica str_upper em tudo que é texto)
        $POST   = sanitize_input_array($_POST);
        $id     = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
        $userId = $_SESSION['user_id'] ?? 0;
        $cargo  = $_SESSION['user_cargo'] ?? 'Visitante';
        $acao   = $id ? 'Alterar' : 'Criar';

        // Verificação de permissão
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', $acao)) {
            $this->jsonResponse(
                ['success' => false, 'message' => "Acesso negado para {$acao}."],
                403
            );
        }

        // Monta array com dados da entidade (campos esperados pelo Model)
        $dadosEntidade = [
            'tipo'                  => ucfirst(filter_input(INPUT_POST, 'tipo') ?? 'cliente'),
            'tipo_pessoa'           => $_POST['tipo_pessoa'],
            'razao_social'          => $POST['razao_social'],
            'nome_fantasia'         => $POST['nome_fantasia'] ?? null,
            'codigo_interno'        => $_POST['codigo_interno'] ?? null,
            'cnpj_cpf'              => preg_replace('/\D/', '', filter_input(INPUT_POST, 'cnpj_cpf') ?? ''),
            'inscricao_estadual_rg' => $_POST['inscricao_estadual_rg'] ?? null,
            'situacao'              => $_POST['situacao'] ?? 'Ativo',
            // Endereço principal (o Model espera dentro de 'endereco_principal')
            'endereco_principal'    => [
                'cep'         => preg_replace('/\D/', '', $_POST['end_cep'] ?? ''),
                'logradouro'  => $_POST['end_logradouro'] ?? '',
                'numero'      => $_POST['end_numero'] ?? '',
                'complemento' => $_POST['end_complemento'] ?? null,
                'bairro'      => $_POST['end_bairro'] ?? '',
                'cidade'      => $_POST['end_cidade'] ?? '',
                'uf'          => $_POST['end_uf'] ?? ''
            ]
        ];

        try {
            if ($id) {
                // UPDATE
                $this->entidadeModel->update($id, $dadosEntidade, $userId);
                $message = 'Entidade atualizada com sucesso!';
                $newId = $id;
            } else {
                // CREATE
                $newId = $this->entidadeModel->create($dadosEntidade, $userId);
                $message = 'Entidade cadastrada com sucesso!';
            }

            $this->jsonResponse(['success' => true, 'message' => $message, 'ent_codigo' => $newId]);
        } catch (\PDOException $e) {
            error_log("Erro salvarEntidade: " . $e->getMessage());

            if ($e->getCode() === '23000') {

                // Busca a entidade existente para pegar o ID
                $campo = str_contains($e->getMessage(), 'cnpj_cpf_unique')
                    ? 'cnpj_cpf'
                    : 'codigo_interno';

                $valor = $_POST[$campo] ?? $_POST['cnpj_cpf'] ?? '';

                $entidadeExistente = $this->entidadeModel->findByDocumentOrCode($valor);

                $this->jsonResponse([
                    'success'     => false,
                    'error_type'  => 'duplicado',
                    'entidade_id' => $entidadeExistente['id'] ?? null,
                    'message'     => "Já existe um cadastro com esse " .
                        ($campo === 'cnpj_cpf' ? 'CNPJ/CPF' : 'Código Interno') . "."
                ]);
            }

            $this->jsonResponse([
                'success'    => false,
                'error_type' => 'erro_servidor',
                'message'    => 'Erro interno no servidor. Tente novamente.'
            ]);
        }
    }

    /**
     * Busca dados de uma entidade para pré-preenchimento do formulário de edição (POST).
     */
    public function getEntidade()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Método não permitido.'],
                405
            );
        }

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Token CSRF inválido.'],
                403
            );
        }

        $id    = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Acesso negado.'],
                403
            );
        }

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID inválido.']);
        }

        $dados = $this->entidadeModel->find($id); // Usa o método find do Model

        $this->jsonResponse(
            $dados
                ? ['success' => true, 'data' => $dados]
                : ['success' => false, 'message' => 'Entidade não encontrada.']
        );
    }

    /**
     * Deleta uma entidade (POST).
     */
    public function deleteEntidade()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Método não permitido.'],
                405
            );
        }

        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Token CSRF inválido.'],
                403
            );
        }

        // 1. Verificação de Ação (DELETE)
        $cargo  = $_SESSION['user_cargo'] ?? 'Visitante';
        $userId = $_SESSION['user_id'] ?? 0;
        $id     = filter_input(INPUT_POST, 'ent_codigo', FILTER_VALIDATE_INT);

        // VERIFICAÇÃO DE PERMISSÃO (ACL/RBAC)
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Deletar')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Acesso negado para Deletar.'],
                403
            );
        }

        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => 'ID inválido para exclusão.']);
        }

        try {
            // Chama o método delete do Model
            if ($this->entidadeModel->delete($id, $userId)) {
                $this->jsonResponse(['success' => true, 'message' => 'Entidade excluída com sucesso!']);
            } else {
                // Caso não tenha dado erro, mas não excluiu (registro não encontrado, etc.)
                $this->jsonResponse(['success' => false, 'message' => 'Falha ao excluir. Entidade não encontrada ou erro interno.']);
            }
        } catch (\PDOException $e) {
            error_log("Erro deleteEntidade: " . $e->getMessage());
            $message = $e->getCode() === '23000'
                ? "Erro: Não é possível excluir esta entidade. Ela está vinculada a outros registros."
                : "Erro interno no banco de dados.";
            $this->jsonResponse(['success' => false, 'message' => $message]);
        }
    }

    /**
     * Rota AJAX para buscar um endereço adicional por ID (para edição).
     */
    public function getEnderecoAdicional()
    {
        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Token CSRF inválido.'],
                403
            );
        }

        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $id = filter_input(INPUT_POST, 'end_id', FILTER_VALIDATE_INT);

        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Acesso negado.'],
                403
            );
        }

        $dados = $this->entidadeModel->findEnderecoAdicional($id);

        $this->jsonResponse(
            $dados
                ? ['success' => true, 'data' => $dados]
                : ['success' => false, 'message' => 'Endereço não encontrado.']
        );
    }

    /**
     * Rota AJAX para listar endereços adicionais (Usado pelo DataTables/Listagem).
     */
    public function listarEnderecosAdicionais()
    {
        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['error' => 'Token CSRF inválido.'],
                403
            );
        }

        // A ACL deve checar a permissão 'Ler' em 'Entidades'
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            $this->jsonResponse(
                ["error" => "Acesso negado para listar endereços."],
                403
            );
        }

        $entidadeId = filter_input(INPUT_POST, 'entidade_id', FILTER_VALIDATE_INT);
        if (!$entidadeId) {
            $this->jsonResponse(["data" => []]); // Retorna vazio se não tiver ID
        }

        try {
            $data = $this->entidadeModel->getEnderecosAdicionais($entidadeId);
            // Formato DataTables simplificado, já que não usamos Server-Side aqui
            $this->jsonResponse(["data" => $data]);
        } catch (\Exception $e) {
            error_log("Erro listarEnderecosAdicionais: " . $e->getMessage());
            $this->jsonResponse(["error" => "Erro ao processar dados."]);
        }
    }

    /**
     * Rota AJAX para salvar um novo endereço adicional (POST).
     */
    public function salvarEnderecoAdicional()
    {
        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Token CSRF inválido.'],
                403
            );
        }

        $cargo      = $_SESSION['user_cargo'] ?? 'Visitante';
        $entidadeId = filter_input(INPUT_POST, 'entidade_id', FILTER_VALIDATE_INT);
        $endId      = filter_input(INPUT_POST, 'end_id', FILTER_VALIDATE_INT); // ID do endereço
        $acao       = $endId ? 'Alterar' : 'Criar';

        // A ACL deve checar a permissão 'acao' em 'Entidades' 
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', $acao)) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Acesso negado para salvar endereço adicional.'],
                403
            );
        }

        if (!$entidadeId) {
            $this->jsonResponse(['success' => false, 'message' => 'Entidade ID inválida. Salve a entidade principal primeiro.']);
        }

        // Coleta e validação de dados
        $dataEndereco = [
            'tipo_endereco' => $_POST['tipo_endereco'] ?? '',
            'cep'           => $_POST['cep'] ?? '',
            'logradouro'    => $_POST['logradouro'] ?? '',
            'numero'        => $_POST['numero'] ?? '',
            'complemento'   => $_POST['complemento'] ?? '',
            'bairro'        => $_POST['bairro'] ?? '',
            'cidade'        => $_POST['cidade'] ?? '',
            'uf'            => $_POST['uf'] ?? '',
            'end_id'        => $endId,
        ];

        try {
            if ($endId) {
                // UPDATE
                $this->entidadeModel->updateEnderecoAdicional($endId, $dataEndereco);
                $message = 'Endereço adicional atualizado com sucesso!';
                $newId = $endId;
            } else {
                // CREATE
                $newId = $this->entidadeModel->createEnderecoAdicional($entidadeId, $dataEndereco);
                $message = 'Endereço adicional salvo com sucesso!';
            }
            $this->jsonResponse(['success' => true, 'message' => $message, 'end_id' => $newId]);
        } catch (\PDOException $e) {
            error_log("Erro salvarEnderecoAdicional: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno no banco de dados.']);
        }
    }

    /**
     * Rota AJAX para deletar um endereço adicional.
     */
    public function deleteEnderecoAdicional()
    {
        if (!$this->isValidCsrf($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(
                ['success' => false, 'message' => 'Token CSRF inválido.'],
                403
            );
        }

        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        $endId = filter_input(INPUT_POST, 'end_id', FILTER_VALIDATE_INT);

        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Alterar')) {
            echo json_encode(
                ['success' => false, 'message' => 'Acesso negado.'],
                403
            );
        }

        if (!$endId) {
            $this->jsonResponse(['success' => false, 'message' => 'ID de endereço inválido.']);
        }

        try {
            if ($this->entidadeModel->deleteEnderecoAdicional($endId)) {
                $this->jsonResponse(
                    ['success' => true, 'message' => 'Endereço adicional excluído com sucesso!'],
                    200
                );
            } else {
                $this->jsonResponse(
                    ['success' => false, 'message' => 'Falha ao excluir. Endereço não encontrado.'],
                    500
                );
            }
        } catch (\Exception $e) {
            error_log("Erro no deleteEnderecoAdicional: " . $e->getMessage());
            $this->jsonResponse(
                ['success' => false, 'message' => 'Erro interno ao deletar endereço adicional.'],
                500
            );
        }
    }

    /**
     * Rota AJAX: Retorna o próximo código interno formatado (Ex: 0045).
     */
    public function getProximoCodigo()
    {
        $cargo = $_SESSION['user_cargo'] ?? 'Visitante';
        // Permissão de Leitura já basta para gerar o código
        if (!PermissaoService::checarPermissao($cargo, 'Entidades', 'Ler')) {
            $this->jsonResponse(
                ['success' => false, 'code' => ''],
                403
            );
        }

        try {
            $nextId = $this->entidadeModel->getNextCodigoInterno();

            // Formata para 4 dígitos (ex: 1 -> "0001", 50 -> "0050")
            // Se for mais dígitos, mudar o 4 para 6
            $formattedCode = str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $this->jsonResponse(
                ['success' => true, 'code' => $formattedCode],
                200
            );
        } catch (\Exception $e) {
            error_log("Erro no getProximoCodigo: " . $e->getMessage());
            $this->jsonResponse(
                ['success' => false, 'code' => ''],
                500
            );
        }
    }
}

<?php

/**
 * CLASSE CONTROLLER: ApiController
 * Local: app/Controllers/ApiController.php
 * Descrição: Gerencia as rotas da API Rest para o App Mobile (Flutter).
 */

require_once ROOT_PATH . '/app/Models/FuncionarioModel.php';
require_once ROOT_PATH . '/app/Models/ExpedicaoModel.php'; // Para lançamentos em campo
require_once ROOT_PATH . '/app/Services/JwtService.php'; // Nosso novo serviço JWT

class ApiController
{
    private $funcionarioModel;
    private $expedicaoModel;

    public function __construct()
    {
        $this->funcionarioModel = new FuncionarioModel();
        $this->expedicaoModel = new ExpedicaoModel();
    }

    // =================================================================
    // FUNÇÃO DE SEGURANÇA: Autenticação via JWT
    // =================================================================

    /**
     * Processa o login mobile e gera um JWT.
     * Rota: auth/login
     */
    public function login()
    {
        $email = $_POST['email'] ?? '';
        $senha_pura = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha_pura)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios.']);
            return;
        }

        $funcionario = $this->funcionarioModel->getFuncionarioByEmail($email);

        if ($funcionario && password_verify($senha_pura, $funcionario['senha_hash'])) {
            // Sucesso: Gera o Token JWT
            $token = JwtService::generateToken(['user_id' => $funcionario['id'], 'cargo' => $funcionario['tipo_cargo']]);

            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $funcionario['id'],
                    'cargo' => $funcionario['tipo_cargo'],
                    'nome' => $funcionario['nome_completo']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenciais inválidas.']);
        }
    }

    /**
     * Verifica o token JWT na requisição. Usado pelo roteador.
     */
    public function checkAuthToken(): array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ''; // Pega o cabeçalho 'Authorization'

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = JwtService::validateToken($token);

            if ($payload) {
                return ['success' => true, 'user_id' => $payload->user_id, 'cargo' => $payload->cargo];
            }
        }
        return ['success' => false, 'message' => 'Token JWT inválido ou ausente.'];
    }

    // =================================================================
    // FUNÇÕES DE CAMPO (PROTEGIDAS)
    // =================================================================

    /**
     * Lista as entregas alocadas para o Motorista/Técnico logado.
     * Rota: entrega/minhas-entregas (Requer JWT)
     */
    public function getMinhasEntregas()
    {
        $userId = $_POST['user_id_api'];
        $cargo = $_POST['cargo_api'];    // Cargo obtido do token (Motorista, Tecnico)

        try {
            // O ExpedicaoModel precisa de um método findEntregasByUserId
            $entregas = $this->expedicaoModel->findEntregasByUserId($userId, $cargo);

            echo json_encode(['success' => true, 'data' => $entregas]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar entregas: ' . $e->getMessage()]);
        }
    }

    /**
     * Motorista: Registra observações de ocorrência ou observações gerais.
     * Rota: entrega/observacao (Requer JWT)
     */
    public function registrarObservacao()
    {
        $motoristaId = $_POST['user_id_api'];
        $expedicaoId = filter_input(INPUT_POST, 'expedicao_id', FILTER_VALIDATE_INT);
        $observacao = $_POST['observacao'] ?? '';
        $tipo = $_POST['tipo'] ?? 'Observacao'; // Observacao, Incidente

        if (!$expedicaoId || empty($observacao)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
            return;
        }

        try {
            // O ExpedicaoModel precisa de um método para salvar o log de Frota
            $this->expedicaoModel->registrarLogFrota($expedicaoId, $motoristaId, $tipo, $observacao);
            echo json_encode(['success' => true, 'message' => 'Observação registrada com sucesso.']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Falha no registro: ' . $e->getMessage()]);
        }
    }

    /**
     * Motorista: Registra abastecimento.
     * Rota: entrega/abastecimento (Requer JWT)
     */
    public function registrarAbastecimento()
    {
        $motoristaId = $_POST['user_id_api'];
        $expedicaoId = filter_input(INPUT_POST, 'expedicao_id', FILTER_VALIDATE_INT);
        $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
        $litros = filter_input(INPUT_POST, 'litros', FILTER_VALIDATE_FLOAT);

        if (!$expedicaoId || $valor === false || $litros === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados de abastecimento incompletos ou inválidos.']);
            return;
        }

        try {
            // O ExpedicaoModel precisa de um método para salvar o Abastecimento
            $this->expedicaoModel->registrarAbastecimento($expedicaoId, $motoristaId, $valor, $litros);
            echo json_encode(['success' => true, 'message' => 'Abastecimento registrado.']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Falha no registro: ' . $e->getMessage()]);
        }
    }

    /**
     * Técnico: Lançamento de Checklist, Observações e Mídias (Fotos/Vídeos).
     * Rota: entrega/lancamento-tecnico (Nova Rota)
     */
    public function registrarLancamentoTecnico()
    {
        // Esta rota é um POST complexo que recebe dados e o arquivo de mídia ($_FILES)
        $tecnicoId = $_POST['user_id_api'];
        $expedicaoId = filter_input(INPUT_POST, 'expedicao_id', FILTER_VALIDATE_INT);

        if (!$expedicaoId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID da expedição é obrigatório.']);
            return;
        }

        // Lógica de upload de arquivo: mover o $_FILES['midia'] para um diretório seguro (Ex: public/uploads/entregas/)
        $caminhoMidia = null; // Supondo que a lógica de upload está implementada

        try {
            $this->expedicaoModel->registrarLogEntrega($_POST, $_FILES, $tecnicoId); // Model precisa de um método para isso
            echo json_encode(['success' => true, 'message' => 'Lançamento e mídias registrados com sucesso.']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Falha no registro: ' . $e->getMessage()]);
        }
    }
}

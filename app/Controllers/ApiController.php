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
     * Lista as entregas alocadas para o Motorista ou Técnico logado.
     */
    public function getMinhasEntregas()
    {
        $userId = $_POST['user_id_api']; // Obtido do token via roteador

        // Lógica de ACL de API: Um Técnico/Motorista só vê as entregas alocadas a ele.
        // O ExpedicaoModel precisa de um método findEntregasByUserId (a ser criado).

        try {
            // $entregas = $this->expedicaoModel->findEntregasByUserId($userId);

            // SIMULAÇÃO:
            echo json_encode([
                'success' => true,
                'data' => [
                    ['id' => 101, 'cliente' => 'Fazenda Peixe Bom', 'status' => 'Em Carregamento'],
                    ['id' => 102, 'cliente' => 'Fazenda Sol', 'status' => 'Em Trânsito']
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar entregas.']);
        }
    }

    // ... Outros métodos de lançamento (registrarObservacao, registrarAbastecimento, etc.) virão aqui.
}

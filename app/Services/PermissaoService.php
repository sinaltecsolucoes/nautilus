<?php

namespace App\Services;

use App\Models\PermissaoModel;

/**
 * Class PermissaoService
 * Verifica se um usuário tem acesso a determinados recursos.
 * @package App\Services
 */
class PermissaoService
{
    /**
     * Verifica se um cargo específico tem permissão para uma determinada ação em um módulo.
     * @param string $cargo O tipo de cargo do usuário logado.
     * @param string $modulo O módulo que o usuário deseja acessar (Ex: 'Entidades').
     * @param string $acao A ação desejada ('Criar', 'Ler', 'Alterar', 'Deletar').
     * @return bool Retorna TRUE se permitido, FALSE se negado.
     */
    public static function checarPermissao($cargo, $modulo, $acao)
    {
        // 1. O Administrador tem sempre TODAS as permissões, independentemente da tabela!
        if ($cargo === 'Administrador' || $cargo === 'Admin Master') {
            return true;
        }

        // 2. Consulta ao Model 
        // Cria o Model (usado dentro do método estático)
        $permissaoModel = new PermissaoModel();

        // 3. Busca a regra de permissão no Banco de Dados
        $regra = $permissaoModel->getRegraPermissao($cargo, $modulo, $acao);

        // 4. Analisa o resultado
        if ($regra && isset($regra['permitido'])) {
            // Regra encontrada. Retorna TRUE se o campo 'permitido' for 1 (ou true)
            return (bool)$regra['permitido'];
        }

        // 5. Se a regra NÃO FOR ENCONTRADA no Banco de Dados , negamos por padrão (política de segurança zero-trust).
        return false;
    }
}

<?php

/**
 * CLASSE DE SERVIÇO: PermissaoService
 * Local: app/Services/PermissaoService.php
 * Descrição: Classe estática que encapsula a lógica de verificação de permissão.
 * Usa o PermissaoModel para consultar as regras de acesso.
 */

// Inclui o Model de Permissões para acesso ao DB
require_once __DIR__ . '/../Models/PermissaoModel.php';

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
        if ($cargo === 'Administrador') {
            return true;
        }

        // 2. Cria o Model (usado dentro do método estático)
        $permissaoModel = new PermissaoModel();

        // 3. Busca a regra de permissão no DB
        $regra = $permissaoModel->getRegraPermissao($cargo, $modulo, $acao);

        // 4. Analisa o resultado
        if ($regra) {
            // Regra encontrada. Retorna TRUE se o campo 'permitido' for 1 (ou true)
            return (bool)$regra['permitido'];
        }

        // 5. Se a regra NÃO FOR ENCONTRADA no DB, negamos por padrão (política de segurança zero-trust).
        return false;
    }
}

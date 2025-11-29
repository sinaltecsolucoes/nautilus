/**
 * ARQUIVO GLOBAL DE JAVASCRIPT (CORE)
 * Local: assets/js/app.js
 * Descrição: Contém configurações globais, máscaras e helpers do SweetAlert2.
 */

// 1. Configuração Padrão do Toast (Notificação flutuante)
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// 2. Função Global para Mensagem de Sucesso (Toast)
function msgSucesso(mensagem) {
    Toast.fire({
        icon: 'success',
        title: mensagem
    });
}

// 3. Função Global para Mensagem de Erro (Modal)
function msgErro(mensagem) {
    Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: mensagem,
        confirmButtonColor: '#3085d6'
    });
}

// 4. Função Global para Confirmação de Exclusão
// Retorna a "Promise" do SweetAlert para ser tratada no arquivo específico
function confirmarExclusao(titulo = 'Tem certeza?', texto = 'Você não poderá reverter isso!') {
    return Swal.fire({
        title: titulo,
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar'
    });
}

// 5. CONFIGURAÇÃO GLOBAL DE ERROS DO DATATABLES
if ($.fn.dataTable) {
    // Desativa o alerta nativo (aquele pop-up do navegador)
    $.fn.dataTable.ext.errMode = 'none';

    // Escuta eventos de erro em qualquer tabela do sistema
    $(document).on('error.dt', function (e, settings, techNote, message) {
        console.error('Erro DataTables:', message);
        
        // Usa nossa função global do SweetAlert
        msgErro('Falha ao carregar os dados da tabela.<br><small>Verifique o console para mais detalhes.</small>');
    });
}
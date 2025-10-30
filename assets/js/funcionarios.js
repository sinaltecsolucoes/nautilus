/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Funcionários
 * Local: assets/js/funcionarios.js
 * Descrição: Gerencia o CRUD de funcionários (usuários) via AJAX e DataTables.
 */

document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('funcionario-data');
    if (!rootData) return;

    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;
    // Pega o ID do usuário logado para desabilitar a exclusão do próprio registro
    const LOGGED_USER_ID = rootData.dataset.loggedInUserId || 0;

    const $modal = $('#modal-funcionario');
    const $form = $('#form-funcionario');
    const $idInput = $('#funcionario-id');
    const $senhaInput = $('#funcionario-senha');
    const $confirmarSenhaInput = $('#funcionario-confirmar-senha');

    // 1. Inicialização do DataTables
    const table = $('#tabela-funcionarios').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": BASE_URL + "/usuarios/listar",
            "type": "POST",
            "data": { csrf_token: CSRF_TOKEN }
        },
        "columns": [
            { "data": "nome_completo" },
            { "data": "email" },
            { "data": "tipo_cargo" },
            {
                "data": "situacao",
                "render": function (data) {
                    return data === 'Ativo' ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>';
                }
            },
            {
                "data": "id",
                "orderable": false,
                "render": function (data, type, row) {
                    // Impede que o próprio usuário logado edite/exclua o próprio registro (ID 1 do Administrador)
                    let buttons = '';
                    if (data != LOGGED_USER_ID) {
                        buttons = `
                            <button class="btn btn-warning btn-sm btn-editar me-1" data-id="${data}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn btn-danger btn-sm btn-excluir" data-id="${data}" data-nome="${row.nome_completo}" title="Inativar"><i class="fa fa-ban"></i></button>
                        `;
                    } else {
                        buttons = '<span class="badge bg-secondary">Seu Perfil</span>';
                    }
                    return `<div class="btn-group">${buttons}</div>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // --- FUNÇÕES AUXILIARES ---

    function showFeedback(message, type = 'success') {
        const msgArea = document.getElementById('mensagem-funcionario');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        msgArea.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
        setTimeout(() => msgArea.innerHTML = '', 5000);
    }

    function resetModal() {
        $form[0].reset();
        $idInput.val('');
        $('#modal-funcionario-label').text('Adicionar Funcionário');
        $senhaInput.prop('required', true); // Senha é obrigatória em CREATE
        $modal.find('.alert').remove();
    }

    // --- EVENTOS E AÇÕES CRUD ---

    // Abrir Modal para Adicionar
    $('#btn-adicionar-funcionario').on('click', function () {
        resetModal();
        $modal.modal('show');
    });

    // Abrir Modal para Edição (GET)
    $('#tabela-funcionarios').on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        resetModal();

        $.ajax({
            url: BASE_URL + '/usuarios/get',
            type: 'POST',
            data: { funcionario_id: id, csrf_token: CSRF_TOKEN },
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                const data = response.data;
                $idInput.val(data.id);
                $('#funcionario-nome').val(data.nome_completo);
                $('#funcionario-email').val(data.email);
                $('#funcionario-cargo').val(data.tipo_cargo);
                $('#funcionario-situacao').val(data.situacao);

                $('#modal-funcionario-label').text(`Editar: ${data.nome_completo}`);
                $senhaInput.prop('required', false); // Não é obrigatória em UPDATE
                $modal.modal('show');
            } else {
                showFeedback(response.message, 'danger');
            }
        }).fail(function () {
            showFeedback('Erro de comunicação ao buscar dados.', 'danger');
        });
    });

    // Submissão do Formulário (Salvar/Atualizar)
    $form.on('submit', function (e) {
        e.preventDefault();

        // 1. Validação de Senha no cliente
        const senha = $senhaInput.val();
        const confirmarSenha = $confirmarSenhaInput.val();

        // Se a senha foi digitada, deve ter no mínimo 6 caracteres e conferir
        if (senha && (senha.length < 6 || senha !== confirmarSenha)) {
            showFeedback('Erro: A senha deve ter no mínimo 6 caracteres e conferir.', 'danger');
            return;
        }

        // 2. Requisição AJAX
        const url = BASE_URL + '/usuarios/salvar';
        const formData = new FormData(this);

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                $modal.modal('hide');
                table.ajax.reload(null, false);
                showFeedback(response.message, 'success');
            } else {
                showFeedback(response.message, 'danger');
            }
        }).fail(function () {
            showFeedback('Erro de Comunicação com o servidor.', 'danger');
        });
    });

    // Ação de Inativar/Excluir (DELETE)
    $('#tabela-funcionarios').on('click', '.btn-excluir', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');

        // Confirmação (pode usar um SweetAlert ou o modal nativo)
        if (confirm(`Deseja realmente INATIVAR o funcionário "${nome}"?`)) {
            $.ajax({
                url: BASE_URL + '/usuarios/deletar', // Rota 'deleteFuncionario'
                type: 'POST',
                data: { funcionario_id: id, csrf_token: CSRF_TOKEN },
                dataType: 'json',
            }).done(function (response) {
                if (response.success) {
                    table.ajax.reload(null, false);
                    showFeedback(response.message, 'success');
                } else {
                    showFeedback(response.message, 'danger');
                }
            }).fail(function () {
                showFeedback('Erro de comunicação para inativação.', 'danger');
            });
        }
    });

    // Garante que o Modal resete ao fechar
    $modal.on('hidden.bs.modal', function () {
        resetModal();
    });
});
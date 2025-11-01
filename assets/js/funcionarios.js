/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Funcionários
 * Local: assets/js/funcionarios.js
 * Descrição: Gerencia o CRUD de funcionários (usuários) via AJAX e DataTables com SweetAlert2.
 */

document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('funcionario-data');
    if (!rootData) return;

    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;
    const LOGGED_USER_ID = parseInt(rootData.dataset.loggedInUserId) || 0;

    const $modal = $('#modal-funcionario');
    const $form = $('#form-funcionario');
    const $idInput = $('#funcionario-id');
    const $senhaInput = $('#funcionario-senha');
    const $confirmarSenhaInput = $('#funcionario-confirmar-senha');
    const $btnSalvar = $('#btn-salvar-funcionario');

    // ===============================================================
    // FUNÇÕES AUXILIARES (NOVO: SweetAlert2)
    // ===============================================================

    /**
     * Exibe o feedback ao usuário usando SweetAlert2.
     * @param {string} message Mensagem a ser exibida.
     * @param {string} type 'success', 'error', 'warning', 'info'.
     */
    function showSwalFeedback(message, type) {
        Swal.fire({
            icon: type === 'danger' ? 'error' : type,
            title: type === 'danger' ? 'Erro!' : 'Sucesso!',
            html: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000
        });
    }

    // Função de feedback original (substituída acima, mas mantida no JS do usuário)
    // Se a função showFeedback estiver em outro arquivo (layout.php), podemos removê-la daqui.
    // Vamos assumir que ela precisa ser adaptada:
    window.showFeedback = function (message, type) {
        showSwalFeedback(message, type);
    }

    // ===============================================================
    // 1. Inicialização do DataTables
    // ===============================================================
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
                    // Renderiza a Situação com Badge (Bootstrap)
                    const badgeClass = data === 'Ativo' ? 'bg-success' : 'bg-danger';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            {
                "data": "id",
                "orderable": false,
                "render": function (data, type, row) {
                    const isSelf = parseInt(data) === LOGGED_USER_ID;
                    const isDisabled = isSelf ? 'disabled' : '';
                    const title = isSelf ? 'Você não pode inativar sua própria conta' : '';

                    let actions = `<button class="btn btn-warning btn-sm btn-editar me-2" data-id="${data}" title="Editar Funcionário">
                                        <i class="fas fa-edit"></i>
                                    </button>`;

                    actions += `<button class="btn btn-danger btn-sm btn-inativar" data-id="${data}" data-nome="${row.nome_completo}" ${isDisabled} title="${title}">
                                    <i class="fas fa-user-slash"></i>
                                </button>`;
                    return actions;
                }
            }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        }
    });


    // ===============================================================
    // 2. Eventos do Formulário (CREATE / UPDATE)
    // ===============================================================

    // Abrir Modal (Adicionar)
    $('#btn-adicionar-funcionario').on('click', function () {
        $idInput.val('');
        $form[0].reset();
        $('#modal-funcionario-label').text('Adicionar Novo Funcionário');
        $senhaInput.prop('required', true).closest('.col-md-6').show();
        $confirmarSenhaInput.prop('required', true).closest('.col-md-6').show();
        $btnSalvar.html('<i class="fas fa-save me-2"></i> Salvar Funcionário');
    });

    // Abrir Modal (Editar)
    $('#tabela-funcionarios').on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $idInput.val(id);
        $('#modal-funcionario-label').text('Editar Funcionário ID: ' + id);

        // Esconde e torna a senha opcional ao editar
        $senhaInput.prop('required', false).val('').closest('.col-md-6').show();
        $confirmarSenhaInput.prop('required', false).val('').closest('.col-md-6').show();
        $btnSalvar.html('<i class="fas fa-edit me-2"></i> Atualizar Funcionário');

        // Busca dados para pré-preenchimento
        $.ajax({
            url: BASE_URL + '/usuarios/get',
            type: 'POST',
            data: { funcionario_id: id, csrf_token: CSRF_TOKEN },
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                const data = response.data;
                $('#funcionario-nome').val(data.nome_completo);
                $('#funcionario-email').val(data.email);
                $('#funcionario-cargo').val(data.tipo_cargo);
                $('#funcionario-situacao').val(data.situacao);
                $modal.modal('show');
            } else {
                showSwalFeedback(response.message, 'danger');
            }
        }).fail(function () {
            showSwalFeedback('Erro ao buscar dados de edição.', 'danger');
        });
    });

    // Submissão do Formulário (Salvar/Atualizar)
    $form.on('submit', function (e) {
        e.preventDefault();

        // VALIDAÇÃO DE SENHA NOVO:
        if ($senhaInput.val() !== $confirmarSenhaInput.val()) {
            showSwalFeedback('Erro: As senhas digitadas não coincidem.', 'warning');
            return;
        }

        // Se for um novo cadastro, a senha deve ser obrigatória
        const isNew = !$idInput.val();
        if (isNew && $senhaInput.val().length < 6) {
            showSwalFeedback('Erro: A senha deve ter no mínimo 6 caracteres.', 'warning');
            return;
        }

        // Bloqueia o botão para evitar cliques duplicados
        $btnSalvar.prop('disabled', true);

        $.ajax({
            url: BASE_URL + '/usuarios/salvar',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
        }).done(function (response) {
            $btnSalvar.prop('disabled', false);
            if (response.success) {
                table.ajax.reload(null, false);
                $modal.modal('hide');
                showSwalFeedback(response.message, 'success');
            } else {
                showSwalFeedback(response.message, 'danger');
            }
        }).fail(function () {
            $btnSalvar.prop('disabled', false);
            showSwalFeedback('Erro de Comunicação com o servidor.', 'danger');
        });
    });


    // ===============================================================
    // 3. Ação de Inativar (SOFT DELETE) - COM SWEETALERT2
    // ===============================================================
    $('#tabela-funcionarios').on('click', '.btn-inativar', function () {
        const $this = $(this);
        if ($this.is(':disabled')) return; // Impede ação se estiver desabilitado

        const id = $this.data('id');
        const nome = $this.data('nome');

        Swal.fire({
            title: 'Tem certeza?',
            html: `Você irá **INATIVAR** o funcionário **${nome}**. Ele perderá o acesso ao sistema.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, Inativar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Se confirmado, envia a requisição AJAX de exclusão (inativação)
                $.ajax({
                    url: BASE_URL + '/usuarios/deletar',
                    type: 'POST',
                    data: { funcionario_id: id, csrf_token: CSRF_TOKEN },
                    dataType: 'json',
                }).done(function (response) {
                    if (response.success) {
                        table.ajax.reload(null, false);
                        showSwalFeedback(response.message, 'success');
                    } else {
                        // NOVO: Usa a mensagem do Controller para erro de Foreign Key
                        showSwalFeedback(response.message, 'danger');
                    }
                }).fail(function () {
                    showSwalFeedback('Erro de comunicação para inativação.', 'danger');
                });
            }
        });
    });

    // Garante que o ID e Senha sejam limpos ao fechar o modal
    $modal.on('hidden.bs.modal', function () {
        $idInput.val('');
        $form[0].reset();
        $senhaInput.prop('required', false).val('');
        $confirmarSenhaInput.prop('required', false).val('');
    });
});
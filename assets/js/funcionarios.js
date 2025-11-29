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

    // Lógica do Checkbox de Acesso
    $('#tem_acesso').on('change', function () {
        const isChecked = $(this).is(':checked');
        const isEdit = $('#funcionario-id').val() !== '';

        if (isChecked) {
            $('#area-login').slideDown();
            $('#funcionario-email').prop('required', true);

            // Senha obrigatória apenas se for NOVO usuário com acesso
            if (!isEdit) {
                $('#funcionario-senha').prop('required', true);
                $('#funcionario-confirmar-senha').prop('required', true);
                $('#senha-help').text('Senha obrigatória para novos usuários com acesso.');
            } else {
                // Na edição, senha é opcional (só se quiser trocar)
                $('#funcionario-senha').prop('required', false);
                $('#funcionario-confirmar-senha').prop('required', false);
                $('#senha-help').text('Preencha apenas se quiser alterar a senha atual.');
            }
        } else {
            $('#area-login').slideUp();
            $('#funcionario-email').prop('required', false).val('');
            $('#funcionario-senha').prop('required', false).val('');
            $('#funcionario-confirmar-senha').prop('required', false).val('');
        }
    });

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
            { "data": "nome_comum" },
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
        "language": window.DataTablesPtBr
    });


    // ===============================================================
    // 2. Eventos do Formulário (CREATE / UPDATE)
    // ===============================================================

    // Abrir Modal (Adicionar)
    $('#btn-adicionar-funcionario').on('click', function () {
        $idInput.val('');
        $form[0].reset();
        $('#modal-funcionario-label').text('Adicionar Novo Funcionário');
        /* $senhaInput.prop('required', true).closest('.col-md-6').show();
         $confirmarSenhaInput.prop('required', true).closest('.col-md-6').show();*/

        // Apenas garantimos que o checkbox de acesso comece desmarcado.
        $('#tem_acesso').prop('checked', false).trigger('change');
        $btnSalvar.html('<i class="fas fa-save me-2"></i> Salvar Funcionário');
        //$('#tem_acesso').prop('checked', false).trigger('change'); // Reseta para fechado
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
                $('#funcionario-apelido').val(data.nome_comum);
                $('#funcionario-email').val(data.email);
                $('#funcionario-cargo').val(data.tipo_cargo);
                $('#funcionario-situacao').val(data.situacao);

                if (data.email) {
                    $('#tem_acesso').prop('checked', true).trigger('change');
                    $('#funcionario-email').val(data.email);
                } else {
                    $('#tem_acesso').prop('checked', false).trigger('change');
                }
                $modal.modal('show');
            } else {
                msgErro(msg);
            }
        }).fail(function () {
            msgErro(msg);
        });
    });

    // Submissão do Formulário (Salvar/Atualizar)
    $form.on('submit', function (e) {
        e.preventDefault();

        const senhaVal = $senhaInput.val();
        const confSenhaVal = $confirmarSenhaInput.val();
        const temAcesso = $('#tem_acesso').is(':checked');

        // VALIDAÇÃO DE SENHA (Apenas se o checkbox de acesso estiver marcado)
        if (temAcesso) {
            if (senhaVal !== confSenhaVal) {
                msgErro(msg);
                return;
            }

            // Se for novo cadastro E marcou acesso, senha é obrigatória e deve ter min 6 chars
            const isNew = !$idInput.val();
            if (isNew && senhaVal.length < 6) {
                msgErro(msg);
                return;
            }

            // Se for edição e o usuário digitou algo na senha, valida tamanho
            if (!isNew && senhaVal.length > 0 && senhaVal.length < 6) {
                msgErro(msg);
                return;
            }
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
                msgSucesso(msg);
            } else {
                msgErro(msg);
            }
        }).fail(function () {
            $btnSalvar.prop('disabled', false);
            msgErro(msg);
        });
    });

    // ===============================================================
    // 3. Ação de Inativar (SOFT DELETE) - COM SWEETALERT2
    // ===============================================================
    $('#tabela-funcionarios').on('click', '.btn-inativar', function () {
        const $this = $(this);
        if ($this.is(':disabled')) return;

        const id = $this.data('id');
        const nome = $this.data('nome');

        confirmarExclusao('Tem certeza?', `Você irá INATIVAR o funcionário **${nome}**.`)
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: BASE_URL + '/usuarios/deletar',
                        type: 'POST',
                        data: { funcionario_id: id, csrf_token: CSRF_TOKEN },
                        dataType: 'json',
                    }).done(function (response) {
                        if (response.success) {
                            table.ajax.reload(null, false);
                            msgSucesso(response.message);
                        } else {
                            msgErro(response.message);
                        }
                    }).fail(function () {
                        msgErro('Erro de comunicação para inativação.');
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
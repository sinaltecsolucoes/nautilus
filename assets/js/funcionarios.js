/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Funcionários
 * Local: assets/js/funcionarios.js
 * Descrição: Gerencia o CRUD de funcionários (usuários) via AJAX e DataTables com SweetAlert2.
 * Dependências: jQuery, DataTables, SweetAlert2 (app.js), config.js (APP_CONFIG).
 * 
 * Padrão: Constants > Helpers > Core functions > Init
 */

// =============================================================================
// 1. CONSTANTES GLOBAIS (imutáveis, maiúsculas)
// =============================================================================
const SELECTORS = {
    ROOT: '#funcionario-data',
    MODAL: '#modal-funcionario',
    FORM: '#form-funcionario',
    ID: '#funcionario-id',
    TITULO_MODAL: '#modal-funcionario-label',
    TABELA: '#tabela-funcionarios',
    TEM_ACESSO: '#tem_acesso',
    AREA_LOGIN: '#area-login',
    EMAIL: '#funcionario-email',
    SENHA: '#funcionario-senha',
    CONFIRMAR_SENHA: '#funcionario-confirmar-senha',
    SENHA_HELP: '#senha-help',
    NOME: '#funcionario-nome',
    APELIDO: '#funcionario-apelido',
    CARGO: '#funcionario-cargo',
    SITUACAO: '#funcionario-situacao',
    BTN_SALVAR: '#btn-salvar-funcionario',
    BTN_ADD: '#btn-adicionar-funcionario'
};

// =============================================================================
// 2. VARIÁVEIS GLOBAIS (mutáveis, let para reatribuição se necessário)
// =============================================================================

let table; // Instância do DataTable 

// =============================================================================
// 3. EVENT LISTENERS E INICIALIZAÇÃO (executado no DOMContentLoaded)
// =============================================================================

document.addEventListener('app:config-ready', () => {
    if (!window.APP_CONFIG) {
        console.error('APP_CONFIG não carregado. Inclua config.js antes.')
        return;
    }

    const { BASE_URL, CSRF_TOKEN } = window.APP_CONFIG;

    // ==================================================================
    // 3.1 DEFINIÇÃO DAS ROTAS
    // ==================================================================

    const ROUTES = {
        LISTAR: `${BASE_URL}/usuarios/listar`,
        GET: `${BASE_URL}/usuarios/get`,
        SALVAR: `${BASE_URL}/usuarios/salvar`,
        DELETAR: `${BASE_URL}/usuarios/deletar`
    };

    // =============================================================================
    // 3.2 FUNÇÕES AUXILIARES (pequenas, reutilizáveis e puras quando possível)
    // =============================================================================
    function isEditMode() {
        return $(SELECTORS.ID).val() !== '';
    }

    function resetModal(isCreate = true) {
        $(SELECTORS.FORM)[0].reset();
        $(SELECTORS.ID).val('');
        $(SELECTORS.TEM_ACESSO).prop('checked', false).trigger('change');
        $(SELECTORS.TITULO_MODAL).text(isCreate ? 'Adicionar Novo Funcionário' : 'Editar Funcionário');
        $(SELECTORS.BTN_SALVAR).html(isCreate
            ? '<i class="fas fa-save me-2"></i> Salvar Funcionário'
            : '<i class="fas fa-edit me-2"></i> Atualizar Funcionário'
        );
    }

    function toggleAcessoArea() {
        const isChecked = $(SELECTORS.TEM_ACESSO).is(':checked');
        const edit = isEditMode();

        if (isChecked) {
            $(SELECTORS.AREA_LOGIN).slideDown();
            $(SELECTORS.EMAIL).prop('required', true);

            if (!edit) {
                $(SELECTORS.SENHA).prop('required', true);
                $(SELECTORS.CONFIRMAR_SENHA).prop('required', true);
                $(SELECTORS.SENHA_HELP).text('Senha obrigatória para novos usuários com acesso.');
            } else {
                $(SELECTORS.SENHA).prop('required', false);
                $(SELECTORS.CONFIRMAR_SENHA).prop('required', false);
                $(SELECTORS.SENHA_HELP).text('Preencha apenas se quiser alterar a senha atual.');
            }
        } else {
            $(SELECTORS.AREA_LOGIN).slideUp();
            $(SELECTORS.EMAIL).prop('required', false).val('');
            $(SELECTORS.SENHA).prop('required', false).val('');
            $(SELECTORS.CONFIRMAR_SENHA).prop('required', false).val('');
            $(SELECTORS.SENHA_HELP).text('');
        }
    }

    function validateSenha() {
        const temAcesso = $(SELECTORS.TEM_ACESSO).is(':checked');
        if (!temAcesso) return { ok: true };

        const senha = $(SELECTORS.SENHA).val();
        const confirmar = $(SELECTORS.CONFIRMAR_SENHA).val();
        const isNew = !isEditMode();

        if (senha !== confirmar) {
            return { ok: false, message: 'As senhas não coincidem.' };
        }
        if (isNew && senha.length < 6) {
            return { ok: false, message: 'A senha deve ter pelo menos 6 caracteres.' };
        }
        if (!isNew && senha.length > 0 && senha.length < 6) {
            return { ok: false, message: 'A nova senha deve ter pelo menos 6 caracteres.' };
        }
        return { ok: true };
    }

    // =============================================================================
    // 3.3 FUNÇÕES PRINCIPAIS (lógica core, async para HTTP)
    // =============================================================================

    function initDataTable() {
        table = $(SELECTORS.TABELA).DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: ROUTES.LISTAR,
                type: 'POST',
                data: { csrf_token: CSRF_TOKEN }
            },
            columns: [
                { data: 'nome_completo' },
                { data: 'nome_comum' },
                { data: 'email' },
                { data: 'tipo_cargo' },
                {
                    data: 'situacao',
                    render: function (data) {
                        const badgeClass = data === 'Ativo' ? 'bg-success' : 'bg-danger';
                        return `<span class="badge ${badgeClass}">${data}</span>`;
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    render: function (data, type, row) {
                        const isSelf = parseInt(data) === (window.APP_CONFIG?.LOGGED_USER_ID || 0);
                        const disabled = isSelf ? 'disabled' : '';
                        const title = isSelf ? 'Você não pode inativar sua própria conta' : '';
                        return `
                            <button class="btn btn-warning btn-sm btn-editar me-2" data-id="${data}" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm btn-inativar" data-id="${data}" data-nome="${row.nome_completo}" ${disabled} title="${title}">
                                <i class="fas fa-user-slash"></i>
                            </button>
                        `;
                    }
                }
            ],
            language: window.DataTablesPtBr
        });
    }

    async function loadFuncionario(id) {
        try {
            const response = await apiPost(ROUTES.GET, { funcionario_id: id, csrf_token: CSRF_TOKEN });
            if (response.success && response.data) {
                const d = response.data;
                $(SELECTORS.ID).val(d.id);
                $(SELECTORS.NOME).val(d.nome_completo);
                $(SELECTORS.APELIDO).val(d.nome_comum);
                $(SELECTORS.EMAIL).val(d.email);
                $(SELECTORS.CARGO).val(d.tipo_cargo);
                $(SELECTORS.SITUACAO).val(d.situacao);

                if (d.email) {
                    $(SELECTORS.TEM_ACESSO).prop('checked', true);
                } else {
                    $(SELECTORS.TEM_ACESSO).prop('checked', false);
                }
                toggleAcessoArea();

                $(SELECTORS.TITULO_MODAL).text(`Editar Funcionário ID: ${id}`);
                $(SELECTORS.BTN_SALVAR).html('<i class="fas fa-edit me-2"></i> Atualizar Funcionário');
                $(SELECTORS.MODAL).modal('show');
            } else {
                msgErro(response.message || 'Erro ao carregar dados.');
            }
        } catch (error) {
            console.error(error);
            msgErro('Erro de comunicação com o servidor.');
        }
    }

    async function submitForm(e) {
        e.preventDefault();

        const senhaValidation = validateSenha();
        if (!senhaValidation.ok) {
            msgErro(senhaValidation.message);
            return;
        }

        const $btn = $(SELECTORS.BTN_SALVAR);
        const original = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        try {
            // Envia como application/x-www-form-urlencoded (serialize), mantendo seu backend
            const formData = new FormData($(SELECTORS.FORM)[0]);
            formData.append('csrf_token', CSRF_TOKEN);

            const response = await apiPost(ROUTES.SALVAR, formData, true);

            if (response.success) {
                $(SELECTORS.MODAL).modal('hide');
                table.ajax.reload(null, false);
                msgSucesso(response.message);
            } else {
                msgErro(response.message || 'Falha ao salvar.');
            }
        } catch (error) {
            console.error(error);
            msgErro('Erro de comunicação ao salvar.');
        } finally {
            $btn.prop('disabled', false).html(original);
        }
    }

    async function inativarFuncionario(id, nome) {
        const result = await confirmarExclusao('Tem certeza?', `Você irá INATIVAR o funcionário <b>${nome}</b>.`);
        if (!result.isConfirmed) return;

        try {
            const response = await apiPost(ROUTES.DELETAR, { funcionario_id: id, csrf_token: CSRF_TOKEN });
            if (response.success) {
                table.ajax.reload(null, false);
                msgSucesso(response.message);
            } else {
                msgErro(response.message || 'Falha ao inativar.');
            }
        } catch (error) {
            console.error(error);
            msgErro('Erro de comunicação para inativação.');
        }
    }

    // =============================================================================
    // 3.4 INICIALIZAÇÃO FINAL
    // =============================================================================

    // Garantir existência do root na página
    if (!document.querySelector(SELECTORS.ROOT)) {
        return;
    }

    // Eventos
    $(SELECTORS.TEM_ACESSO).on('change', toggleAcessoArea);

    $(SELECTORS.BTN_ADD).on('click', function () {
        resetModal(true);
        $(SELECTORS.MODAL).modal('show');
    });

    $(SELECTORS.FORM).on('submit', submitForm);

    $(SELECTORS.TABELA).on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        resetModal(false); // prepara modal para edição
        loadFuncionario(id);
    });

    $(SELECTORS.TABELA).on('click', '.btn-inativar', function () {
        const $btn = $(this);
        if ($btn.is(':disabled')) return;
        inativarFuncionario($btn.data('id'), $btn.data('nome'));
    });

    $(SELECTORS.MODAL).on('hidden.bs.modal', function () {
        resetModal(true);
    });

    // Inicializa tabela
    if ($(SELECTORS.TABELA).length > 0) {
        initDataTable();
    }
});
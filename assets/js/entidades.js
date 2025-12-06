/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Entidades
 * Local: assets/js/entidades.js
 * Descrição: Gerencia o CRUD de entidades (Clientes, Fornecedores, Transportadoras) via AJAX e DataTables.
 * Dependências: jQuery, DataTables, SweetAlert2 (app.js), config.js (APP_CONFIG).
 * 
 * Padrão: Constants > Helpers > Core functions > Init
 */

// =============================================================================
// 1. CONSTANTES GLOBAIS (imutáveis, maiúsculas)
// =============================================================================
const SELECTORS = {
    ROOT: '#entidade-data',
    MODAL: '#modal-entidade',
    FORM: '#form-entidade',
    FORM_END: '#form-endereco-adicional',
    ID: '#entidade-id',
    TITULO_MODAL: '#modal-entidade-label',
    TABELA: '#tabela-entidades',
    TABELA_END: '#tabela-enderecos-adicionais',
    TIPO_PESSOA: '#tipo_pessoa',
    CPF_CNPJ: '#cnpj_cpf',
    LABEL_CPF_CNPJ: 'label[for="cnpj_cpf"]',
    BTN_BUSCAR_CNPJ: '#btn-buscar-cnpj',
    DEBUG_API: '#api-debug-info',
    END_CEP: '#end_cep',
    END_LOGRADOURO: '#end_logradouro',
    END_NUMERO: '#end_numero',
    END_COMPLEMENTO: '#end_complemento',
    END_BAIRRO: '#end_bairro',
    END_CIDADE: '#end_cidade',
    END_UF: '#end_uf',
    END_ADIC_ID: '#end_adic_id',
    END_ADIC_ENTIDADE_ID: '#end_adic_entidade_id',
    BTN_SALVAR_END: '#btn-salvar-endereco',
    BTN_CANCELAR_END: '#btn-cancelar-endereco',
    TAB_ENDERECO: '#endereco-adicional-tab'
};

// =============================================================================
// 2. VARIÁVEIS GLOBAIS (mutáveis)
// =============================================================================

let tableEntidades, tableEnderecos;

// =============================================================================
// 3. EVENT LISTENERS E INICIALIZAÇÃO
// =============================================================================

document.addEventListener('app:config-ready', () => {
    if (!window.APP_CONFIG) {
        console.error('APP_CONFIG não carregado. Inclua config.js antes.');
        return;
    } 

    const { BASE_URL } = window.APP_CONFIG;
    const CSRF_TOKEN = document.querySelector('[data-csrf-token]').dataset.csrfToken;

    // ==================================================================
    // 3.1 DEFINIÇÃO DAS ROTAS
    // ==================================================================

    const ROUTES = {
        LISTAR: `${BASE_URL}/entidades/listar`,
        GET: `${BASE_URL}/entidades/get`,
        SALVAR: `${BASE_URL}/entidades/salvar`,
        DELETAR: `${BASE_URL}/entidades/deletar`,
        API_BUSCA: `${BASE_URL}/entidades/api-busca`,
        NEXT_CODE: `${BASE_URL}/entidades/proximo-codigo`,
        END_LISTAR: `${BASE_URL}/entidades/enderecos/listar`,
        END_SALVAR: `${BASE_URL}/entidades/enderecos/salvar`,
        END_GET: `${BASE_URL}/entidades/enderecos/get`,
        END_DELETAR: `${BASE_URL}/entidades/enderecos/deletar`
    };

    // =============================================================================
    // 3.2 FUNÇÕES AUXILIARES
    // =============================================================================
    function updatePessoaFields() {
        const tipo = $(SELECTORS.TIPO_PESSOA).val();
        const $input = $(SELECTORS.CPF_CNPJ);
        const $label = $(SELECTORS.LABEL_CPF_CNPJ);
        const $btnBuscar = $(SELECTORS.BTN_BUSCAR_CNPJ);

        $input.unmask();

        if (tipo === 'Juridica') {
            $label.text('CNPJ (*)');
            $input.attr('placeholder', '00.000.000/0000-00').mask('00.000.000/0000-00');
            $btnBuscar.show();
        } else {
            $label.text('CPF (*)');
            $input.attr('placeholder', '000.000.000-00').mask('000.000.000-00');
            $btnBuscar.hide();
        }
    }

    function preencherModalEntidade(d) {
        $('#codigo_interno').val(d.codigo_interno ? d.codigo_interno.toString().padStart(4, '0') : '');
        $(SELECTORS.ID).val(d.id || '');
        $(SELECTORS.TIPO_PESSOA).val(d.tipo_pessoa).trigger('change');
        $('#situacao').val(d.situacao);
        $(SELECTORS.CPF_CNPJ).val(d.cnpj_cpf).trigger('input');
        $('#inscricao_estadual_rg').val(d.inscricao_estadual_rg || '');
        $('#razao_social').val(d.razao_social || '');
        $('#nome_fantasia').val(d.nome_fantasia || '');

        // Endereço principal
        $(SELECTORS.END_CEP).val(d.end_cep || '').trigger('input');
        $(SELECTORS.END_LOGRADOURO).val(d.end_logradouro || '');
        $(SELECTORS.END_NUMERO).val(d.end_numero || '');
        $(SELECTORS.END_COMPLEMENTO).val(d.end_complemento || '');
        $(SELECTORS.END_BAIRRO).val(d.end_bairro || '');
        $(SELECTORS.END_CIDADE).val(d.end_cidade || '');
        $(SELECTORS.END_UF).val(d.end_uf || '');

        $(SELECTORS.TITULO_MODAL).text('Editar: ' + (d.razao_social || 'Entidade'));
        $(SELECTORS.TAB_ENDERECO).removeClass('disabled');
        $(SELECTORS.END_ADIC_ENTIDADE_ID).val(d.id);

        if (tableEnderecos) tableEnderecos.ajax.reload();
    }

    function resetModalEntidade(isCreate = true) {
        $(SELECTORS.FORM)[0].reset();
        $(SELECTORS.ID).val('');
        $(SELECTORS.TITULO_MODAL).text(isCreate ? 'Adicionar Novo' : 'Editar Entidade');
        $(SELECTORS.TAB_ENDERECO).addClass('disabled');
        $('#situacao').val('Ativo');
        $(SELECTORS.TIPO_PESSOA).val('Fisica').trigger('change');
        updatePessoaFields();
    }

    // =============================================================================
    // 3.3 FUNÇÕES PRINCIPAIS
    // =============================================================================
    function initDataTableEntidades() {
        tableEntidades = $(SELECTORS.TABELA).DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: ROUTES.LISTAR,
                type: 'POST',
                data: d => {
                    d.csrf_token = CSRF_TOKEN;
                    d.tipo_entidade = $('#ent_tipo').val();
                }
            },
            responsive: true,
            order: [[2, "asc"]],
            columns: [
                { data: "situacao", className: "text-center", render: d => d === 'Ativo' ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' },
                { data: "tipo", className: "text-center" },
                { data: "codigo_interno", className: "text-center", render: d => d ? d.toString().padStart(4, '0') : '' },
                { data: "razao_social" },
                { data: "nome_fantasia" },
                { data: "cnpj_cpf", className: "text-center" },
                {
                    data: "id", orderable: false, className: "text-center",
                    render: id => `
                        <button class="btn btn-warning btn-sm btn-editar-entidade me-1" data-id="${id}">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-danger btn-sm btn-inativar-entidade" data-id="${id}">
                            <i class="fas fa-ban"></i>
                        </button>`
                }
            ],
            language: window.DataTablesPtBr
        });
    }

    function initDataTableEnderecos() {
        tableEnderecos = $(SELECTORS.TABELA_END).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: ROUTES.END_LISTAR,
                type: 'POST',
                data: d => {
                    d.entidade_id = $(SELECTORS.END_ADIC_ENTIDADE_ID).val();
                    d.csrf_token = CSRF_TOKEN;
                }
            },
            paging: false, lengthChange: false, searching: false, info: false, ordering: false,
            columns: [
                { data: "tipo_endereco" },
                { data: null, render: d => `${d.logradouro}, ${d.numero} ${d.complemento ? '- ' + d.complemento : ''}` },
                { data: null, render: d => `${d.bairro}, ${d.cidade}/${d.uf}` },
                {
                    data: "id",
                    className: "text-center",
                    render: function (id, type, row) {
                        // Botão Editar (sempre aparece)
                        let btnEdit = `
                            <button class="btn btn-sm btn-info btn-editar-endereco" data-id="${id}" title="Editar">
                                <i class="fas fa-pencil-alt"></i>
                            </button>`;

                        // Botão Excluir (só aparece se NÃO for Principal)
                        let btnDelete = '';
                        if (row.tipo_endereco !== 'Principal') {
                            btnDelete = `
                                <button class="btn btn-sm btn-danger btn-deletar-endereco ms-1" data-id="${id}" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>`;
                        }

                        return btnEdit + btnDelete;
                    }
                }
            ],
            language: window.DataTablesPtBr
        });
    }

    async function loadEntidade(id) {
        try {
            const response = await apiPost(ROUTES.GET, { ent_codigo: id, csrf_token: CSRF_TOKEN });
            if (response.success && response.data) {
                preencherModalEntidade(response.data);
                $(SELECTORS.MODAL).modal('show');
            } else {
                msgErro(response.message || 'Erro ao carregar dados.');
            }
        } catch (error) {
            console.error(error);
            msgErro('Erro de comunicação com o servidor.');
        }
    }

    async function submitEntidade(e) {
        e.preventDefault();
        const $btn = $(SELECTORS.FORM).find('button[type="submit"]');
        const original = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        try {
            const formData = new FormData($(SELECTORS.FORM)[0]);
            formData.append('csrf_token', CSRF_TOKEN);

            const response = await apiPost(ROUTES.SALVAR, formData, true);

            if (response.success) {
                $(SELECTORS.MODAL).modal('hide');
                tableEntidades.ajax.reload(null, false);
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

    async function submitEnderecoAdicional(e) {
        e.preventDefault();
        try {
            const response = await apiPost(ROUTES.END_SALVAR, $(SELECTORS.FORM_END).serialize());
            if (response.success) {
                const idEntidade = $(SELECTORS.END_ADIC_ENTIDADE_ID).val();
                $(SELECTORS.FORM_END)[0].reset();
                $(SELECTORS.END_ADIC_ENTIDADE_ID).val(idEntidade);
                $(SELECTORS.END_ADIC_ID).val('');
                $(SELECTORS.BTN_SALVAR_END)
                    .removeClass('btn-warning')
                    .addClass('btn-success')
                    .html('<i class="fas fa-plus"></i> Adicionar');
                $(SELECTORS.BTN_CANCELAR_END).hide();
                tableEnderecos.ajax.reload(null, false);
                Toast.fire({ icon: 'success', title: response.message });
            } else {
                msgErro(response.message);
            }
        } catch (error) {
            console.error(error);
            msgErro('Erro ao salvar endereço.');
        }
    }

    async function deleteEndereco(id) {
        const result = await confirmarExclusao('Remover endereço?', 'Essa ação não pode ser desfeita.');
        if (!result.isConfirmed) return;

        try {
            const response = await apiPost(ROUTES.END_DELETAR, { end_id: id, csrf_token: CSRF_TOKEN });
            if (response.success) {
                Toast.fire({ icon: 'success', title: 'Endereço removido.' });
                tableEnderecos.ajax.reload(null, false);
            } else {
                msgErro(response.message);
            }
        } catch (error) {
            console.error(error);
            msgErro('Erro ao excluir endereço.');
        }
    }

    // =============================================================================
    // 3.4 INICIALIZAÇÃO FINAL
    // =============================================================================
    if (!document.querySelector(SELECTORS.ROOT)) return;

    // Máscaras
    $(SELECTORS.END_CEP + ', #end_adic_cep').mask('00000-000');
    updatePessoaFields();
    $(SELECTORS.TIPO_PESSOA).on('change', updatePessoaFields);

    // Eventos
    $(SELECTORS.FORM).on('submit', submitEntidade);
    $(SELECTORS.FORM_END).on('submit', submitEnderecoAdicional);

    $(SELECTORS.TABELA).on('click', '.btn-editar-entidade', function () {
        resetModalEntidade(false);
        loadEntidade($(this).data('id'));
    });

    $(SELECTORS.TABELA_END).on('click', '.btn-editar-endereco', function () {
        loadEndereco($(this).data('id')); // você pode criar função loadEndereco similar ao loadEntidade
    });

    $(SELECTORS.TABELA_END).on('click', '.btn-deletar-endereco', function () {
        deleteEndereco($(this).data('id'));
    });

    $(SELECTORS.MODAL).on('show.bs.modal', function (event) {
        const $trigger = $(event.relatedTarget);
        if ($trigger.length > 0 && !$trigger.hasClass('btn-editar-entidade')) {
            resetModalEntidade(true);
            $('#codigo_interno').val('...').prop('readonly', true);
            $.getJSON(ROUTES.NEXT_CODE, function (response) {
                $('#codigo_interno').val(response.success ? response.code : '');
                $('#codigo_interno').prop('readonly', false);
            });
        }
    });

    // Inicializa tabelas
    if ($(SELECTORS.TABELA).length > 0) initDataTableEntidades();
    if ($(SELECTORS.TABELA_END).length > 0) initDataTableEnderecos();
});

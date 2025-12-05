/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Pedidos
 * Local: assets/js/pedidos.js
 * Descrição: Gerencia o CRUD de pedidos via DataTables, Select2 e AJAX.
 * Autor: [SINAL]
 * Versão: 1.0.0
 * Dependências: jQuery, DataTables, Select2, jQuery Mask, SweetAlert2 (via app.js).
 * 
 * Este arquivo segue boas práticas:
 * - Estrutura modular: Constants > Funções Auxiliares > Funções Principais > Inicialização.
 * - Modernidade: Uso de async/await com fetch para HTTP (sem jQuery.ajax).
 * - Legibilidade: Comentários JSDoc, linhas curtas, template literals.
 * - Manutenção: Funções reutilizáveis, validação centralizada, error handling com try/catch.
 * - Segurança: CSRF em todos os POSTs.
 * 
 * Funções globais (de app.js): msgSucesso, msgErro, confirmarExclusao, apiPost (wrapper de fetch).
 */

// =============================================================================
// 1. CONSTANTES GLOBAIS (imutáveis, maiúsculas)
// =============================================================================
const SELECTORS = {
    MODAL: '#modal-pedido',
    TITULO_MODAL: '#modal-pedido-label',
    FORM: '#form-pedido',
    ID: '#pedido-id',
    OS_NUMERO: '#pedido-os-numero',
    QUANTIDADE: '#pedido-quantidade',
    BONUS_PERC: '#pedido-bonus-perc',
    VALOR_UNITARIO: '#pedido-valor-unitario',
    VALOR_TOTAL: '#pedido-valor-total',
    CLIENTE: '#pedido-cliente',
    DATA_PEDIDO: '#pedido-data-saida',
    SALINIDADE: '#pedido-salinidade',
    DIVISAO: '#pedido-divisao',
    FORMA_PAGAMENTO: '#pedido-forma-pagamento',
    CONDICAO: '#pedido-condicao',
    STATUS: '#pedido-status',
    STATUS_DIA: '#pedido-status-dia',
    BTN_SALVAR: '#btn-salvar-pedido',
    BTN_DELETAR: '.btn-deletar',
    TABELA: '#tabela-pedidos',

};

// =============================================================================
// 2. VARIÁVEIS GLOBAIS (mutáveis, let para reatribuição se necessário)
// =============================================================================

let table; // Instância do DataTable (inicializada no init)

// =============================================================================
// 3. EVENT LISTENERS E INICIALIZAÇÃO (executado no DOMContentLoaded)
// =============================================================================
document.addEventListener('app:config-ready', () => {
    if (!window.APP_CONFIG) {
        console.error('APP_CONFIG não carregado. Inclua config.js antes.');
        return;
    }

    const { BASE_URL, CSRF_TOKEN } = window.APP_CONFIG;

    // ------------------------------------------------------------------
    // 3.1 DEFINIÇÃO DAS ROTAS
    // ------------------------------------------------------------------
    const ROUTES = {
        LISTAR: `${BASE_URL}/pedidos/listar`,
        GET: `${BASE_URL}/pedidos/get`,
        SALVAR: `${BASE_URL}/pedidos/salvar`,
        DELETAR: `${BASE_URL}/pedidos/deletar`,
        NEXT_OS: `${BASE_URL}/pedidos/next-os`,
        CLIENTES: `${BASE_URL}/pedidos/getClientesOptions`
    };

    // =============================================================================
    // 3.2 FUNÇÕES AUXILIARES (pequenas, reutilizáveis e puras quando possível)
    // =============================================================================

    /**
     * Converte valor formatado BR ("1.234,56") para número float (1234.56).
     * @param {string} val - Valor formatado.
     * @returns {number} Valor numérico ou 0 se inválido.
     */
    function parseMoney(val) {
        if (!val) return 0;
        return parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;
    }

    /**
     * Formata número float (1234.56) para string BR ("1.234,56").
     * @param {number} val - Valor numérico.
     * @returns {string} Valor formatado com 2 casas decimais.
     */
    function formatMoney(val) {
        return parseFloat(val).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Calcula o valor total do pedido baseado em quantidade, bônus e unitário.
     * Atualiza o campo de total no DOM.
     */
    function calcularValorTotal() {
        const qtd = parseMoney($(SELECTORS.QUANTIDADE).val());
        const bonusPerc = parseFloat($(SELECTORS.BONUS_PERC).val()) || 0;
        const unitario = parseMoney($(SELECTORS.VALOR_UNITARIO).val());

        const valorBruto = qtd * unitario;
        // const desconto = (valorBruto * bonusPerc) / 100;
        // const valorTotal = valorBruto - desconto;

        // $(SELECTORS.VALOR_TOTAL).val(formatMoney(valorTotal));
        $(SELECTORS.VALOR_TOTAL).val(formatMoney(valorBruto));
    }

    /**
     * Inicializa máscaras nos inputs do formulário.
     * Dependência: jQuery Mask Plugin.
     */
    function initMasks() {
        if ($.fn.mask) {
            $('.money').mask('#.##0,00', { reverse: true });
            $('.mascara-numero').mask('0#');
            $('.mascara-float').mask('#0.0', { reverse: true });
        }
    }

    /**
     * Inicializa Select2 para o campo de clientes.
     * Carrega opções via AJAX.
     */
    function initSelect2() {
        $(SELECTORS.CLIENTE).select2({
            dropdownParent: $(SELECTORS.MODAL),
            theme: 'bootstrap-5',
            placeholder: 'Buscar Cliente (Nome ou CNPJ)',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: ROUTES.CLIENTES,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: (params) => ({ term: params.term || '', csrf_token: CSRF_TOKEN }),
                processResults: (data) => ({ results: data.results })
            }
        });
    }

    /**
     * Limpa o modal e reseta estados para novo pedido.
     */
    function resetModal() {
        $(SELECTORS.FORM)[0].reset();
        $(SELECTORS.ID).val('');
        $(SELECTORS.CLIENTE).val(null).trigger('change');

        // Define o texto padrão para Novos Pedidos
        $(SELECTORS.TITULO_MODAL).text('Adicionar Novo Pedido');
        $(SELECTORS.BTN_SALVAR).html('<i class="fas fa-save"></i> Salvar Pedido');
    }

    // =============================================================================
    // 3.3 FUNÇÕES PRINCIPAIS (lógica core, async para HTTP)
    // =============================================================================

    /**
     * Inicializa o DataTable para listagem de pedidos.
     * Configuração server-side com AJAX.
     */
    function initDataTable() {
        try {
            table = $(SELECTORS.TABELA).DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: ROUTES.LISTAR,
                    type: 'POST',
                    data: { csrf_token: CSRF_TOKEN }
                },
                order: [[0, 'asc']],
                columns: [
                    { data: 'os_numero', className: 'fw-bold' },
                    { data: 'data_saida', render: (data) => data ? new Date(data).toLocaleDateString('pt-BR') : '-' },
                    { data: 'cliente_nome', render: (data) => data || '-' },
                    { data: 'quantidade', className: 'text-end', render: (data) => formatMoney(data) },
                    { data: 'valor_unitario', className: 'text-end', render: (data) => data ? 'R$ ' + formatMoney(data) : '-' },
                    { data: 'valor_total', className: 'text-end fw-bold', render: (data) => data ? 'R$ ' + formatMoney(data) : '-' },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-center',
                        render: (data, type, row) => `
                             <button class="btn btn-sm btn-primary btn-editar" data-id="${row.id}"><i class="fas fa-edit"></i></button>
                             <button class="btn btn-sm btn-danger btn-deletar" data-id="${row.id}"><i class="fas fa-trash"></i></button>
                             `
                    }
                ],
                language: window.DataTablesPtBr //Português-BR
            });
        } catch (e) {
            console.error('Falha ao inicializar DataTable:', e);
            msgErro('Erro ao carregar tabela. Verifique colunas.');
        }
    }

    /**
     * Carrega dados de um pedido específico via AJAX e preenche o modal.
     * @param {number} id - ID do pedido a editar.
     */
    async function editarPedido(id) {
        try {
            const response = await apiPost(ROUTES.GET, { id, csrf_token: CSRF_TOKEN });
            console.log(response);

            if (response.success) {
                const dados = response.data;
                $(SELECTORS.ID).val(dados.id);
                $(SELECTORS.OS_NUMERO).val(dados.os_numero);
                $(SELECTORS.QUANTIDADE).val(dados.quantidade);
                $(SELECTORS.DATA_PEDIDO).val(dados.data_saida);
                $(SELECTORS.BONUS_PERC).val(formatMoney(dados.percentual_bonus));
                $(SELECTORS.VALOR_UNITARIO).val(formatMoney(dados.valor_unitario));
                $(SELECTORS.SALINIDADE).val(dados.salinidade);
                $(SELECTORS.DIVISAO).val(dados.divisao);
                $(SELECTORS.FORMA_PAGAMENTO).val(dados.forma_pagamento);
                $(SELECTORS.CONDICAO).val(dados.condicao);
                $(SELECTORS.STATUS).val(dados.status);
                $(SELECTORS.STATUS_DIA).val(dados.status_dia);

                const clienteOption = new Option(dados.cliente_nome, dados.cliente_entidade_id, true, true);
                $(SELECTORS.CLIENTE).append(clienteOption).trigger('change');

                calcularValorTotal();
                $(SELECTORS.TITULO_MODAL).text(`Editar Pedido OS: ${dados.os_numero}`);
                $(SELECTORS.BTN_SALVAR).html('<i class="fas fa-sync-alt"></i> Salvar Edição');

                $(SELECTORS.MODAL).modal('show');
            } else {
                msgErro(response.message);
            }
        } catch (error) {
            msgErro('Erro de comunicação ao buscar dados.');
        }
    }

    /**
     * Salva ou atualiza um pedido via AJAX.
     * @param {Event} e - Evento de submit do form.
     */
    async function salvarPedido(e) {
        e.preventDefault();
        const formData = new FormData($(SELECTORS.FORM)[0]);
        formData.append('csrf_token', CSRF_TOKEN);

        const $btnSalvar = $(SELECTORS.BTN_SALVAR);
        const textoOriginal = $btnSalvar.html();
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        try {
            const data = await apiPost(ROUTES.SALVAR, formData, true); // true para FormData
            if (data.success) {
                $(SELECTORS.MODAL).modal('hide');
                table.ajax.reload();
                msgSucesso(data.message);
            } else {
                msgErro(data.message);
            }
        } catch (error) {
            msgErro('Erro de comunicação.');
        } finally {
            $btnSalvar.prop('disabled', false).html(textoOriginal);
        }
    }

    /**
     * Deleta um pedido após confirmação.
     * @param {number} id - ID do pedido a deletar.
     */
    async function deletarPedido(id) {
        const result = await confirmarExclusao('Confirmar Exclusão', `Deseja deletar o Pedido OS: ${id}?`);
        if (!result.isConfirmed) return;

        try {
            const response = await apiPost(ROUTES.DELETAR, { pedido_id: id, csrf_token: CSRF_TOKEN });
            if (response.success) {
                msgSucesso(response.message);
                table.ajax.reload();
            } else {
                msgErro('Falha ao excluir: ' + response.message);
            }
        } catch (error) {
            msgErro('Erro de comunicação ao deletar.');
        }
    }

    /**
     * Carrega o próximo número de OS via AJAX.
     */
    async function carregarNextOS() {
        try {
            const response = await apiPost(ROUTES.NEXT_OS, { csrf_token: CSRF_TOKEN });
            if (response.success) {
                $(SELECTORS.OS_NUMERO).val(response.os_number);
            } else {
                msgErro('Erro ao gerar OS.');
            }
        } catch (error) {
            msgErro('Erro de comunicação.');
        }
    }

    // ------------------------------------------------------------------
    // 3.4 INICIALIZAÇÃO FINAL 
    // ------------------------------------------------------------------
    initMasks();
    initSelect2();

    // Eventos
    if ($(SELECTORS.TABELA).length > 0) {
        initDataTable();
        carregarNextOS(); // Carrega OS inicial

        $(SELECTORS.QUANTIDADE)
            .add(SELECTORS.BONUS_PERC)
            .add(SELECTORS.VALOR_UNITARIO)
            .on('input', calcularValorTotal);

        $(SELECTORS.FORM).on('submit', salvarPedido);

        $(SELECTORS.TABELA).on('click', '.btn-editar', (e) => {
            const id = $(e.currentTarget).data('id');
            editarPedido(id);
        });

        $(SELECTORS.TABELA).on('click', '.btn-deletar', (e) => {
            const id = $(e.currentTarget).data('id');
            deletarPedido(id);
        });
    }

    $(SELECTORS.MODAL).on('hidden.bs.modal', resetModal);
});
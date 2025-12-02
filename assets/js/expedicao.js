$(document).ready(function () {

    const $root = $('#expedicao-data');
    if (!$root.length) return;

    const BASE_URL = $root.data('baseUrl');
    const CSRF_TOKEN = $root.data('csrfToken');

    // ROTAS
    const ROUTE_LISTAR = BASE_URL + '/expedicao/listar';
    const ROUTE_SALVAR = BASE_URL + '/expedicao/salvar';
    const ROUTE_GET = BASE_URL + '/expedicao/get';
    const ROUTE_DEL = BASE_URL + '/expedicao/deletar';

    // ROTAS DROPDOWNS
    const ROUTE_VEICULOS = BASE_URL + '/expedicao/getVeiculosOptions';
    const ROUTE_MOTORISTAS = BASE_URL + '/expedicao/getMotoristasOptions';
    const ROUTE_TECNICOS = BASE_URL + '/expedicao/getPessoalOptions';
    const ROUTE_CLIENTES = BASE_URL + '/expedicao/getClientesOptions';

    const $modal = $('#modal-linha');
    const $cardContainer = $('#cards-container');
    const $btnSalvar = $('#btn-salvar-linha');

    let dataAtual = new Date().toISOString().split('T')[0];
    let sortable;

    // MÁSCARAS
    if ($.fn.mask) {
        $('.money').mask('#.##0,00', { reverse: true });
    }

    // ===============================================================
    // 1. FUNÇÕES AUXILIARES
    // ===============================================================
    function parseMoney(selector) {
        let val = $(selector).val();
        if (!val) return 0;
        return parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;
    }

    function initSelect2(selector, url, placeholder) {
        $(selector).select2({
            dropdownParent: $modal,
            theme: 'bootstrap-5',
            placeholder: placeholder,
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: url, type: 'POST', dataType: 'json', delay: 250, cache: false,
                data: p => ({ term: p.term || '', csrf_token: CSRF_TOKEN }),
                processResults: d => ({ results: d.results })
            }
        });
    }

    // Formatação Inteligente de PL
    function formatarQtdPL(qtd) {
        const valor = parseFloat(qtd) || 0;

        if (valor < 1000) {
            // Exibe como mil (ex: 70.000 MIL)
            const milhar = valor * 1;
            return `${milhar.toLocaleString('pt-BR')} mil`;
        } else {
            // Exibe como milhões (ex: 3.050.000 MILHÕES)
            const milhoes = valor * 1;
            return `${milhoes.toLocaleString('pt-BR')} milhões`;
        }
    }

    // Inicializa Dropdowns
    initSelect2('#veiculo', ROUTE_VEICULOS, 'Selecione o Veículo...');
    initSelect2('#motorista', ROUTE_MOTORISTAS, 'Selecione o Motorista...');
    initSelect2('#tecnico', ROUTE_TECNICOS, 'Selecione o Técnico...');
    initSelect2('#cliente', ROUTE_CLIENTES, 'Selecione o Cliente...');


    // ===============================================================
    // 2. CARREGAR CARDS (LISTAGEM)
    // ===============================================================
    function carregarCards() {
        $.post(ROUTE_LISTAR, { data: dataAtual, csrf_token: CSRF_TOKEN }, function (res) {
            const container = $('#cards-container').empty();
            const dados = res.data || [];

            if (dados.length === 0) {
                $('#empty-state').show();
                calcularTotais([]);
                return;
            }

            $('#empty-state').hide();

            dados.forEach((item, index) => {
                // Calcula custo total da linha
                const custoTotal = (parseFloat(item.valor_abastecimento || 0) +
                    parseFloat(item.valor_diaria_motorista || 0) +
                    parseFloat(item.valor_diaria_tecnico || 0));

                // Badge de Horário
                const horarioBadge = item.horario
                    ? `<span class="badge bg-dark ms-2"><i class="far fa-clock me-1"></i>${item.horario.substring(0, 5)}h</span>`
                    : '';

                // Formatação visual do Card
                const cardHtml = `
                <div class="col-12 card-entrega" data-id="${item.id}">
                    <div class="card border-left-primary shadow h-100">
                        <div class="card-body py-2 px-3">
                            <div class="row align-items-center">
                                <div class="col-md-1 text-center border-end">
                                    <h3 class="text-gray-400 fw-bold mb-0 index-numero">#${index + 1}</h3>
                                    ${horarioBadge}
                                </div>

                                <div class="col-md-4 border-end">
                                    <h5 class="text-primary fw-bold mb-1">${item.cliente_nome}</h5>
                                    <div class="small text-muted mb-1">
                                        <i class="fas fa-map-marker-alt me-1"></i> ${item.destino || 'Destino não inf.'}
                                    </div>
                                    <div class="fw-bold text-dark">
                                        <i class="fas fa-box-open me-1"></i> ${item.caixas_usadas || 'Sem volumes'}
                                    </div>
                                </div>

                                <div class="col-md-3 border-end">
                                    <div class="small"><i class="fas fa-truck me-1"></i> ${item.veiculo_nome}</div>
                                    <div class="small"><i class="fas fa-user-tie me-1"></i> Téc: ${item.tecnico_nome}</div>
                                    ${item.motorista_nome ? `<div class="small"><i class="fas fa-user me-1"></i> Mot: ${item.motorista_nome}</div>` : ''}
                                </div>

                                <div class="col-md-2 border-end text-center">
                                    <div class="h5 fw-bold text-success mb-0">${formatarQtdPL(item.qtd_pl)}</div>
                                    <small class="text-muted">Pós-Larvas</small>
                                    ${custoTotal > 0 ? `<div class="text-danger small mt-1 fw-bold">Custo: R$ ${custoTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>` : ''}
                                </div>

                                <div class="col-md-2 text-center">
                                    <button class="btn btn-outline-warning btn-sm btn-editar me-1" data-id="${item.id}" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm btn-excluir" data-id="${item.id}" title="Excluir">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <div class="mt-2 text-muted small cursor-move"><i class="fas fa-grip-lines"></i> Arrastar</div>
                                </div>
                            </div>
                            ${item.observacao ? `<div class="mt-2 small text-muted border-top pt-1"><em>Obs: ${item.observacao}</em></div>` : ''}
                        </div>
                    </div>
                </div>`;

                container.append(cardHtml);
            });

            calcularTotais(dados);
            iniciarSortable();

        }, 'json');
    }

    function calcularTotais(dados) {
        let pl = 0, custo = 0;
        dados.forEach(d => {
            pl += parseFloat(d.qtd_pl || 0);
            custo += parseFloat(d.valor_abastecimento || 0) + parseFloat(d.valor_diaria_motorista || 0) + parseFloat(d.valor_diaria_tecnico || 0);
        });
        $('#total-pl').text(pl.toLocaleString('pt-BR', { minimumFractionDigits: 3 }));
        $('#total-custo').text('R$ ' + custo.toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
    }

    /**
     * Função Auxiliar para preencher Select2 na edição
     * Se o ID já estiver na lista, seleciona. Se não, cria a opção e seleciona.
     */
    function setSelect2(selector, id, text) {
        const $sel = $(selector);
        if (id) {
            // Verifica se a opção já existe no DOM
            if ($sel.find("option[value='" + id + "']").length) {
                $sel.val(id).trigger('change');
            } else {
                // Cria a opção manual (ID, Texto, defaultSelected, selected)
                const newOption = new Option(text, id, true, true);
                $sel.append(newOption).trigger('change');
            }
        } else {
            $sel.val(null).trigger('change');
        }
    }

    // ===============================================================
    // 3. ARRASTAR E SOLTAR (ORDENAÇÃO)
    // ===============================================================
    function iniciarSortable() {
        if (sortable) sortable.destroy();

        const el = document.getElementById('cards-container');
        sortable = new Sortable(el, {
            animation: 150,
            handle: '.card-entrega', // Pode arrastar clicando em qualquer lugar do card
            ghostClass: 'bg-light',

            // Ao soltar o card:
            onEnd: function () {
                const novaOrdem = [];

                // 1. Atualiza visualmente os números (#1, #2...)
                $('.card-entrega').each(function (index) {
                    const novoNumero = index + 1;
                    $(this).find('.index-numero').text('#' + novoNumero); // Atualiza o texto na tela

                    // Prepara array para enviar ao banco
                    novaOrdem.push({
                        id: $(this).data('id'),
                        ordem: novoNumero
                    });
                });

                // 2. Salva a nova ordem no banco silenciosamente
                $.post(BASE_URL + '/expedicao/reordenar', {
                    ordem: novaOrdem,
                    csrf_token: CSRF_TOKEN
                });
            }
        });
    }

    // ===============================================================
    // 4. CRUD (SALVAR, EDITAR, EXCLUIR)
    // ===============================================================

    // Salvar
    $btnSalvar.on('click', function () {
        // Validação mais rigorosa
        const veiculo = $('#veiculo').val();
        const tecnico = $('#tecnico').val();
        const cliente = $('#cliente').val();
        const qtd_pl = $('#qtd_pl').val();

        if (!veiculo || !tecnico || !cliente || qtd_pl === '') {
            Swal.fire('Atenção', 'Preencha Veículo, Técnico, Cliente e Qtd PL!', 'warning');
            return;
        }

        // Garante que cliente seja um número inteiro (ID)
        const clienteId = parseInt(cliente, 10);
        if (isNaN(clienteId) || clienteId <= 0) {
            Swal.fire('Erro', 'Cliente inválido! Selecione um cliente válido.', 'error');
            return;
        }

        const dados = {
            id: $('#linha_id').val(), // ID oculto (vazio se novo)
            data_expedicao: dataAtual,
            veiculo: veiculo,
            tecnico: tecnico,
            motorista: $('#motorista').val() || null,

            os: $('#os').val() || null,
            cliente: clienteId,
            qtd_pl: qtd_pl,
            horario: $('#horario').val() || null,
            caixas_usadas: $('#caixas_usadas').val() || null,
            observacao: $('#observacao').val() || null,

            valor_abastecimento: parseMoney('#valor_abastecimento'),
            valor_diaria_motorista: parseMoney('#valor_diaria_motorista'),
            valor_diaria_tecnico: parseMoney('#valor_diaria_tecnico')
        };

        $.post(ROUTE_SALVAR, { linha: dados, csrf_token: CSRF_TOKEN }, function (res) {
            let r = (typeof res === 'string') ? JSON.parse(res) : res;
            if (r.success) {
                $modal.modal('hide');
                carregarCards();
                if (typeof msgSucesso === 'function') msgSucesso(r.message);
            } else {
                Swal.fire('Erro', r.message, 'error');
            }
        });
    });

    // ============================================================
    // 5. FUNÇÃO DE EDITAR
    // ============================================================
    $cardContainer.on('click', '.btn-editar', function () {
        const id = $(this).data('id');

        $.post(ROUTE_GET, { id: id, csrf_token: CSRF_TOKEN }, function (res) {
            // Garante que é objeto
            let r = (typeof res === 'string') ? JSON.parse(res) : res;

            if (r.success) {
                const d = r.data;

                // 1. Preenche Campos Normais
                $('#linha_id').val(d.id);
                $('#ordem').val(d.ordem);
                $('#os').val(d.os);
                $('#qtd_pl').val(d.qtd_pl);
                $('#horario').val(d.horario); // Formato HH:mm:ss funciona direto no input time
                $('#caixas_usadas').val(d.caixas_usadas);
                $('#observacao').val(d.observacao);

                // 2. Preenche Financeiro (Formatando para BR)
                $('#valor_abastecimento').val(parseFloat(d.valor_abastecimento || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
                $('#valor_diaria_motorista').val(parseFloat(d.valor_diaria_motorista || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
                $('#valor_diaria_tecnico').val(parseFloat(d.valor_diaria_tecnico || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));

                // 3. Preenche Select2 (Com a técnica de criar Option se não existir)
                setSelect2('#veiculo', d.veiculo, d.veiculo_nome);
                setSelect2('#tecnico', d.tecnico, d.tecnico_nome);
                setSelect2('#motorista', d.motorista, d.motorista_nome);
                setSelect2('#cliente', d.cliente, d.cliente_nome);

                // 4. Abre Modal
                $('.modal-title').text('Editar Entrega');
                $('#btn-salvar-linha').html('<i class="fas fa-check-circle me-1"></i> Salvar Edição');
                $modal.modal('show');
            } else {
                Swal.fire('Erro', r.message, 'error');
            }
        });
    });

    // ============================================================
    // 6. FUNÇÃO DE EXCLUIR
    // ============================================================
    $cardContainer.on('click', '.btn-excluir', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta entrega será removida permanentemente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ROUTE_DEL, { id: id, csrf_token: CSRF_TOKEN }, function (res) {
                    let r = (typeof res === 'string') ? JSON.parse(res) : res;

                    if (r.success) {
                        // Se tiver função de Toast global
                        if (typeof msgSucesso === 'function') msgSucesso('Excluído com sucesso!');
                        else Swal.fire('Sucesso', 'Excluído!', 'success');

                        carregarCards(); // Atualiza a lista
                    } else {
                        Swal.fire('Erro', r.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });

    // ============================================================
    // 7. BOTÃO IMPRIMIR ROMANEIO
    // ============================================================
    $('#btn-imprimir').on('click', function () {
        // Pega a data atual da variável global (que muda quando clicamos em Mudar Data)
        if (!dataAtual) {
            Swal.fire('Erro', 'Data inválida.', 'error');
            return;
        }

        // Abre a rota em uma nova aba
        const url = BASE_URL + '/expedicao/romaneio?data=' + dataAtual;
        window.open(url, '_blank');
    }); 

    // Limpar Modal ao fechar
    $modal.on('hidden.bs.modal', function () {
        $('#form-linha')[0].reset();
        $('#linha_id').val('');

        // Reseta os Select2
        $('#veiculo').val(null).trigger('change');
        $('#tecnico').val(null).trigger('change');
        $('#motorista').val(null).trigger('change');
        $('#cliente').val(null).trigger('change');

        // Restaura o título e o botão
        $('.modal-title').text('Nova Entrega');
        $('#btn-salvar-linha').html('<i class="fas fa-save me-1"></i> Salvar Entrega');
    });

    // Mudar Data
    $('#btn-mudar-data').on('click', () => {
        Swal.fire({
            title: 'Ver Programação de:',
            html: '<input type="date" id="swal-data" class="form-control" value="' + dataAtual + '">',
            showCancelButton: true,
            confirmButtonText: 'Carregar'
        }).then((result) => {
            if (result.isConfirmed) {
                dataAtual = $('#swal-data').val();
                $('#data-titulo').text(new Date(dataAtual).toLocaleDateString('pt-BR'));
                carregarCards();
            }
        });
    });

    // Inicializa
    carregarCards();
});
$(document).ready(function () {
    const $root = $('#manutencao-data');
    if (!$root.length) return;

    const BASE_URL = $root.data('baseUrl');
    const CSRF_TOKEN = $root.data('csrfToken');

    // Rotas
    const ROUTE_LISTAR = BASE_URL + '/manutencao/listar';
    const ROUTE_SALVAR = BASE_URL + '/manutencao/salvar';
    const ROUTE_GET = BASE_URL + '/manutencao/get';
    const ROUTE_DEL = BASE_URL + '/manutencao/deletar';
    const ROUTE_VEICULOS = BASE_URL + '/manutencao/getVeiculosOptions';
    const ROUTE_FORNECEDORES = BASE_URL + '/manutencao/getFornecedoresOptions';

    const $modal = $('#modal-manutencao');
    const $btnSalvar = $('#btn-salvar-tudo');

    // Armazena itens na memória antes de salvar
    let itensManutencao = [];

    // Máscaras
    if ($.fn.mask) {
        $('.money').mask('#.##0,00', { reverse: true });
    }

    // ===============================================================
    // 1. DATA TABLE (LISTAGEM)
    // ===============================================================
    let table = $('#tabela-manutencao').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": ROUTE_LISTAR,
            "type": "POST",
            "data": { csrf_token: CSRF_TOKEN }
        },
        "order": [[0, "asc"]],
        "columns": [
            {
                "data": "data_manutencao",
                "render": function (data) {
                    // Se vier vazio, retorna vazio
                    if (!data) return '';

                    return data.split('-').reverse().join('/');
                }
            },
            { "data": "veiculo_nome" },
            { "data": "fornecedor_nome" },
            { "data": "numero_os" },
            {
                "data": "qtd_servicos",
                "className": "text-center",
                "render": function (data, type, row) {
                    // Badge clicável para ver detalhes
                    return `<span class="badge bg-info text-dark btn-ver-detalhes" style="cursor:pointer" data-id="${row.id}" title="Clique para ver detalhes">${data} itens <i class="fas fa-search-plus ms-1"></i></span>`;
                }
            },
            {
                "data": "valor_liquido",
                "className": "text-end fw-bold",
                "render": function (data) { return 'R$ ' + formatMoney(data); }
            },
            {
                "data": "id",
                "orderable": false,
                "className": "text-center",
                "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm btn-deletar" data-id="${data}" title="Excluir"><i class="fas fa-trash"></i></button>
                    `;
                }
            }
        ],
        "language": window.DataTablesPtBr
    }); 

    // ===============================================================
    // 2. SELECT2 (COM POST E NO-CACHE)
    // ===============================================================
    function initSelect2(selector, url, placeholder) {
        $(selector).select2({
            dropdownParent: $modal,
            theme: 'bootstrap-5',
            placeholder: placeholder,
            ajax: {
                url: url,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                cache: false,
                data: function (params) {
                    return { term: params.term || '', csrf_token: CSRF_TOKEN };
                },
                processResults: function (data) {
                    return { results: data.results };
                }
            }
        });
    }

    initSelect2('#veiculo_id', ROUTE_VEICULOS, 'Buscar Veículo...');
    initSelect2('#fornecedor_id', ROUTE_FORNECEDORES, 'Buscar Oficina...');

    // ===============================================================
    // 3. GERENCIAMENTO DE ITENS (ADICIONAR/REMOVER)
    // ===============================================================

    let editIndex = null; // Variável de controle: null = Adicionando, número = Editando

    // Função auxiliar para converter "1.200,50" -> 1200.50
    function parseMoney(val) {
        if (!val) return 0;
        if (typeof val === 'number') return val;
        return parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;
    }

    function formatMoney(val) {
        return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }

    // Função para limpar os inputs do item e resetar o modo de edição
    function limparInputsItem() {
        $('#item_descricao').val('').focus();
        $('#item_valor_pecas').val('');
        $('#item_valor_mo').val('');
        $('#item_rateio').val('1');
        $('#item_tipo').val('Preventiva'); // Volta ao padrão

        // Reseta estado de edição
        editIndex = null;
        $('#btn-add-item').html('<i class="fas fa-plus"></i> Adicionar').removeClass('btn-warning w-50').addClass('btn-success w-100');
        $('#btn-cancelar-item').hide(); // Esconde botão cancelar se existir
    }

    // Adicionar Item
    $('#btn-add-item').on('click', function () {
        let desc = $('#item_descricao').val().trim();
        let tipo = $('#item_tipo').val();
        let pecas = parseMoney($('#item_valor_pecas').val());
        let mo = parseMoney($('#item_valor_mo').val());
        let rateio = parseInt($('#item_rateio').val()) || 1;
        let total = pecas + mo;

        // Validação
        if (desc === '') {
            msgErro('Informe a descrição do serviço.');
            return;
        }
        if (total <= 0) {
            msgErro('Informe o valor das peças ou mão de obra.');
            return;
        }

        const objetoItem = {
            tipo: tipo,
            descricao: desc,
            valor_pecas: pecas,
            valor_mao_obra: mo,
            valor_total_item: total,
            meses_rateio: rateio
        };

        if (editIndex === null) {
            // MODO ADIÇÃO: Empurra para o array
            itensManutencao.push(objetoItem);
        } else {
            // MODO EDIÇÃO: Atualiza o índice específico
            itensManutencao[editIndex] = objetoItem;
            msgSucesso('Item atualizado!');
        }

        renderizarTabela();
        limparInputsItem();
    });

    // Botão Cancelar
    $('#btn-cancelar-item').on('click', function () {
        limparInputsItem();
    });

    // Editar Item da lista
    $(document).on('click', '.btn-editar-item-temp', function () {
        let index = $(this).data('index');
        let item = itensManutencao[index];

        // 1. Carrega dados nos inputs
        $('#item_tipo').val(item.tipo);
        $('#item_descricao').val(item.descricao);
        $('#item_rateio').val(item.meses_rateio);

        // Formata dinheiro de volta para PT-BR no input
        $('#item_valor_pecas').val(item.valor_pecas.toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
        $('#item_valor_mo').val(item.valor_mao_obra.toLocaleString('pt-BR', { minimumFractionDigits: 2 }));

        // 2. Muda estado para EDIÇÃO
        editIndex = index;

        // 3. Muda visual do botão
        $('#btn-add-item').html('Salvar').removeClass('btn-success w-100').addClass('btn-warning w-50');
        $('#btn-cancelar-item').show(); // Mostra botão de cancelar

        // 4. Foca no primeiro campo
        $('#item_descricao').focus();
    });

    // Remover Item
    $(document).on('click', '.btn-remove-item', function () {
        let index = $(this).data('index');

        // Se estiver editando o item que vai ser excluído, cancela a edição
        if (editIndex === index) {
            limparInputsItem();
        } else if (editIndex !== null && index < editIndex) {
            // Se excluir um item anterior ao que está sendo editado, ajusta o índice
            editIndex--;
        }

        itensManutencao.splice(index, 1);
        renderizarTabela();
    });

    // Renderizar Tabela e Calcular Totais
    function renderizarTabela() {
        let tbody = $('#tabela-itens-temp tbody');
        tbody.empty();
        let totalBruto = 0;

        if (itensManutencao.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted">Nenhum item adicionado.</td></tr>');
        } else {
            itensManutencao.forEach((item, index) => {
                totalBruto += item.valor_total_item;

                // Adicionamos o botão de Editar (Amarelo)
                let html = `
                    <tr>
                        <td>${item.tipo}</td>
                        <td>${item.descricao}</td>
                        <td class="text-center">${item.meses_rateio}x</td>
                        <td class="text-end">R$ ${item.valor_pecas.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                        <td class="text-end">R$ ${item.valor_mao_obra.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                        <td class="text-end fw-bold">R$ ${item.valor_total_item.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button type="button" class="btn btn-sm btn-warning btn-editar-item-temp" data-index="${index}" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-remove-item" data-index="${index}" title="Remover">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(html);
            });
        }

        $('#lbl-total-bruto').text('R$ ' + totalBruto.toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
        atualizarTotalLiquido(totalBruto);
    }

    // Atualiza Líquido ao mudar Desconto
    $('#valor_desconto').on('keyup', function () {
        let totalBruto = itensManutencao.reduce((acc, item) => acc + item.valor_total_item, 0);
        atualizarTotalLiquido(totalBruto);
    });

    function atualizarTotalLiquido(bruto) {
        let desconto = parseMoney($('#valor_desconto').val());
        let liquido = bruto - desconto;
        if (liquido < 0) liquido = 0;

        $('#valor_liquido').val('R$ ' + liquido.toLocaleString('pt-BR', { minimumFractionDigits: 2 }));

        // Guarda valores raw para envio
        $('#form-header').data('bruto', bruto);
        $('#form-header').data('liquido', liquido);
    }

    // ===============================================================
    // 4. SALVAR TUDO (ENVIO JSON)
    // ===============================================================
    $btnSalvar.on('click', function () {
        // Validação Header
        let id_manutencao = $('#man_id').val();
        let veiculo = $('#veiculo_id').val();
        let fornecedor = $('#fornecedor_id').val();
        let data = $('#data_manutencao').val();

        if (!veiculo || !fornecedor || !data) {
            msgErro('Preencha Veículo, Fornecedor e Data.');
            return;
        }
        if (itensManutencao.length === 0) {
            msgErro('Adicione pelo menos um item à manutenção.');
            return;
        }

        // Monta o objeto
        let payload = {
            header: {
                id: id_manutencao,
                veiculo_id: veiculo,
                fornecedor_id: fornecedor,
                data_manutencao: data,
                numero_os: $('#numero_os').val(),
                km_atual: $('#km_atual').val() || 0,
                valor_bruto: $('#form-header').data('bruto') || 0,
                valor_desconto: parseMoney($('#valor_desconto').val()),
                valor_liquido: $('#form-header').data('liquido') || 0
            },
            itens: itensManutencao
        };

        // Bloqueia botão
        let originalText = $btnSalvar.html();
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: ROUTE_SALVAR,
            type: 'POST',
            data: JSON.stringify(payload), // Envia como JSON puro
            contentType: 'application/json',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modal.modal('hide');
                msgSucesso(response.message);
                table.ajax.reload();
            } else {
                msgErro(response.message);
            }
        }).fail(function () {
            msgErro('Erro de comunicação com o servidor.');
        }).always(function () {
            $btnSalvar.prop('disabled', false).html(originalText);
        });
    });

    // ===============================================================
    // 5. DELETAR
    // ===============================================================
    $('#tabela-manutencao').on('click', '.btn-deletar', function () {
        let id = $(this).data('id');
        confirmarExclusao('Excluir Manutenção?', 'Todos os itens e custos relacionados serão apagados.')
            .then((result) => {
                if (result.isConfirmed) {
                    $.post(ROUTE_DEL, { id: id, csrf_token: CSRF_TOKEN }, function (res) {
                        let r = (typeof res === 'string') ? JSON.parse(res) : res;
                        if (r.success) {
                            msgSucesso(r.message);
                            table.ajax.reload(null, false);
                        } else {
                            msgErro(r.message);
                        }
                    });
                }
            });
    });

    // ===============================================================
    // 6. EDITAR (CARREGAR DADOS NO FORM)
    // ===============================================================
    $('#tabela-manutencao').on('click', '.btn-editar', function () {
        let id = $(this).data('id');

        $.post(ROUTE_GET, { id: id, csrf_token: CSRF_TOKEN }, function (res) {
            let r = JSON.parse(res);
            if (r.success) {
                let h = r.data.header;
                let itensDb = r.data.itens;

                // 1. Limpa e Prepara Modal
                $('#form-header')[0].reset();
                itensManutencao = []; // Zera array global

                // 2. Preenche Cabeçalho
                $('#man_id').val(h.id); // Hidden ID é crucial para saber que é Update
                $('#data_manutencao').val(h.data_manutencao);
                $('#numero_os').val(h.numero_os);
                $('#km_atual').val(h.km_atual);

                // Formata valores do rodapé
                $('#valor_desconto').val(formatMoney(h.valor_desconto));
                $('#valor_liquido').val(formatMoney(h.valor_liquido));

                // Select2 (Cria options para exibir o texto salvo)
                if (h.veiculo_id) {
                    let opt = new Option(h.veiculo_nome_completo || h.veiculo_placa, h.veiculo_id, true, true);
                    $('#veiculo_id').append(opt).trigger('change');
                }
                if (h.fornecedor_id) {
                    let opt = new Option(h.fornecedor_nome, h.fornecedor_id, true, true);
                    $('#fornecedor_id').append(opt).trigger('change');
                }

                // 3. Preenche Array de Itens e Renderiza
                itensDb.forEach(dbItem => {
                    itensManutencao.push({
                        tipo: dbItem.tipo,
                        descricao: dbItem.descricao,
                        valor_pecas: parseFloat(dbItem.valor_pecas),
                        valor_mao_obra: parseFloat(dbItem.valor_mao_obra),
                        valor_total_item: parseFloat(dbItem.valor_total_item),
                        meses_rateio: parseInt(dbItem.meses_rateio)
                    });
                });

                renderizarTabela(); // Função que desenha a tabela temporária (já existente)

                // 4. Abre Modal
                $('#modalLabel').text('Editar Manutenção');
                $('#modal-manutencao').modal('show');
            }
        });
    });

    // ===============================================================
    // 7. VER DETALHES (RESUMO)
    // ===============================================================
    $('#tabela-manutencao').on('click', '.btn-ver-detalhes', function () {
        let id = $(this).data('id');

        $.post(ROUTE_GET, { id: id, csrf_token: CSRF_TOKEN }, function (res) {
            let r = JSON.parse(res);
            if (r.success) {
                let h = r.data.header;
                let itens = r.data.itens;

                // Preenche Cabeçalho do Modal
                $('#det-veiculo').text(h.veiculo_nome_completo || h.veiculo_placa);
                $('#det-fornecedor').text(h.fornecedor_nome);
                $('#det-data').text(new Date(h.data_manutencao).toLocaleDateString('pt-BR'));
                $('#det-os').text(h.numero_os || '-');
                $('#det-km').text(h.km_atual);

                // Preenche Tabela
                let tbody = $('#det-tbody');
                tbody.empty();
                itens.forEach(item => {
                    tbody.append(`
                        <tr>
                            <td>${item.tipo}</td>
                            <td>${item.descricao}</td>
                            <td class="text-end">R$ ${formatMoney(item.valor_pecas)}</td>
                            <td class="text-end">R$ ${formatMoney(item.valor_mao_obra)}</td>
                            <td class="text-end fw-bold">R$ ${formatMoney(item.valor_total_item)}</td>
                        </tr>
                    `);
                });

                $('#det-total').text('R$ ' + formatMoney(h.valor_liquido));
                $('#modal-detalhes').modal('show');
            }
        });
    });

    // Limpar Modal ao fechar
    $modal.on('hidden.bs.modal', function () {
        $('#form-header')[0].reset();
        $('#man_id').val('');
        $('#veiculo_id').val(null).trigger('change');
        $('#fornecedor_id').val(null).trigger('change');

        // Limpa itens
        itensManutencao = [];
        renderizarTabela();

        // Reseta inputs de adição
        $('#item_descricao').val('');
        $('#item_valor_pecas').val('');
        $('#item_valor_mo').val('');
    });
});
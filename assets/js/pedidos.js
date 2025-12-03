// Este bloco será movido para assets/js/pedidos.js e usará as rotas /pedidos/listar e /pedidos/salvar
document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('pedido-data');
    if (!rootData) return;
    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;
    const ROUTE_GET = BASE_URL + '/pedidos/get';
    const ROUTE_DELETAR = BASE_URL + '/pedidos/deletar';
    const ROUTE_NEXT_OS = BASE_URL + '/pedidos/next-os';
    const ROUTE_CLIENTES = BASE_URL + '/pedidos/getClientesOptions';

    const $modal = $('#modal-pedido');
    const $form = $('#form-pedido');
    const $qtdInput = $('#pedido-quantidade');
    const $bonusInput = $('#pedido-bonus-perc');
    const $unitarioInput = $('#pedido-valor-unitario');
    const $totalInput = $('#pedido-valor-total');
    const $idInput = $('#pedido-id');
    const $osInput = $('#pedido-os-numero');

    // ===============================================================
    // 1. MÁSCARAS
    // ===============================================================
    if ($.fn.mask) {
        // Dinheiro: 1.234,56
        $('.money').mask('#.##0,00', { reverse: true });

        // Números Inteiros: 12345 (Quantidade e OS)
        $('.mascara-numero').mask('0#');

        // Percentual / Decimal simples: 10.5
        $('.mascara-float').mask('#0.0', { reverse: true });
    }

    // ===============================================================
    // 2. FUNÇÕES AUXILIARES DE CONVERSÃO
    // ===============================================================

    // Converte "1.200,50" (BR) para 1200.50 (JS)
    function parseMoney(val) {
        if (!val) return 0;
        // Remove pontos de milhar e troca vírgula por ponto
        return parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;
    }

    // Converte 1200.50 (JS) para "1.200,50" (BR)
    function formatMoney(val) {
        return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ===============================================================
    // 3. CÁLCULO AUTOMÁTICO
    // ===============================================================
    function calcularValorTotal() {
        // Pega os valores convertidos corretamente
        const qtd = parseMoney($qtdInput.val());
        const unitario = parseMoney($unitarioInput.val());

        // Calcula
        const total = qtd * unitario;

        // Devolve para o input formatado em Reais, mas o .val() aciona a máscara
        // Para inputs com máscara 'money', usamos o formato brasileiro direto
        $totalInput.val(formatMoney(total));

        // Força atualização visual da máscara no total (bugfix para alguns browsers)
        $totalInput.trigger('input');
    }

    // Eventos de Cálculo
    $qtdInput.on('input keyup', calcularValorTotal);
    $unitarioInput.on('input keyup', calcularValorTotal); 

    function showToast(message, isSuccess = true) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: isSuccess ? 'success' : 'error',
            title: message,
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    }

    // Busca e preenche o próximo número de OS
    function loadNextOSNumber() {
        $.ajax({
            url: ROUTE_NEXT_OS,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $osInput.val(response.os_number);
                } else {
                    msgErro('Não foi possível gerar o número de OS.');
                }
            }
        });
    }

    // Eventos de Cálculo
    $qtdInput.on('input', calcularValorTotal);
    $unitarioInput.on('input', calcularValorTotal);
    // O bônus é uma regra de negócio que altera a quantidade de entrega, mas não o valor total (que é baseado na quantidade vendida)

    // Inicialização do DataTables (Esqueleto)
    const table = $('#tabela-pedidos').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": BASE_URL + "/pedidos/listar",
            "type": "POST",
            "data": { csrf_token: CSRF_TOKEN }
        },
        "columns": [
            { "data": "os_numero" },
            {
                "data": "data_saida",
                // Renderização de Data (A data deve vir no formato YYYY-MM-DD do PHP)
                "render": function (data) {
                    if (!data) return '';
                    const [year, month, day] = data.split('-');
                    return `${day}/${month}/${year}`;
                }
            },
            { "data": "cliente_nome" },
            {
                "data": "quantidade_com_bonus",
                "render": function (data) {
                    return parseFloat(data).toLocaleString('pt-BR'); // Formata números grandes
                }
            },
            {
                "data": "valor_total",
                // Renderização de Moeda
                "render": function (data) {
                    return 'R$ ' + parseFloat(data).toFixed(2).replace('.', ',');
                }
            },
            { "data": "status" },
            {
                "data": "id",
                "orderable": false,
                "render": function (data) {
                    return `<button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm btn-deletar" data-id="${data}" title="Excluir"><i class="fas fa-trash"></i></button>`;
                }
            }
        ],
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        }
    }); 

    // --- SUBMISSÃO DO FORMULÁRIO ---
    $form.on('submit', function (e) {
        e.preventDefault();

        const url = BASE_URL + '/pedidos/salvar';
        const formData = new FormData(this);
        const $btnSalvar = $('#btn-salvar-pedido'); // ID do botão no modal
        const textoOriginal = $btnSalvar.html();

        // 1. BLOQUEIA O BOTÃO E MUDA O TEXTO
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $modal.modal('hide');
                    table.ajax.reload();
                    
                    // Se usar SweetAlert toast
                    if (typeof Toast !== 'undefined') {
                         Toast.fire({ icon: 'success', title: data.message });
                    } else if (typeof msgSucesso === 'function') {
                         msgSucesso(data.message);
                    } else {
                         alert(data.message);
                    }

                } else {
                    // Se usar função de erro global
                    if (typeof msgErro === 'function') msgErro(data.message);
                    else alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error(error);
                if (typeof msgErro === 'function') msgErro('Erro de comunicação.');
            })
            .finally(() => {
                // 2. REABILITA O BOTÃO SEMPRE (NO SUCESSO OU ERRO)
                $btnSalvar.prop('disabled', false).html(textoOriginal);
            });
    });

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

    initSelect2('#pedido-cliente', ROUTE_CLIENTES, 'Selecione o Cliente...');

    // Adiciona a lógica de cálculo de valor total após a entrada do bônus
    $bonusInput.on('input', calcularValorTotal);

    // --- RESETAR E PREPARAR MODAL (CRIAR) ---
    $('#btn-adicionar-pedido').on('click', function () {
        // 1. Limpa o ID para garantir que é uma INSERÇÃO
        $idInput.val('');

        // 2. Reseta os campos nativos do HTML (inputs de texto, number, etc)
        $form[0].reset();

        // 3. Reseta o Select2 do Cliente (ESSENCIAL)
        // O Select2 precisa desse gatilho para limpar o texto visualmente
        $('#pedido-cliente').val(null).trigger('change');

        // 4. Habilita o campo OS (caso tenha sido desabilitado na edição)
        $osInput.prop('disabled', false);

        // 5. Reseta textos e carrega nova OS
        $('#modal-pedido-label').text('Adicionar Novo Pedido');
        loadNextOSNumber();
    });

    // --- GARANTIA AO FECHAR O MODAL ---
    $modal.on('hidden.bs.modal', function () {
        // Garante que se o usuário fechar clicando fora, limpa tudo também
        $idInput.val('');
        $form[0].reset();
        $('#pedido-cliente').val(null).trigger('change'); // <--- Adicione isso aqui também
        $osInput.prop('disabled', false);
    });

    // --- CRUD: EDIÇÃO ---
    $('#tabela-pedidos').on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $idInput.val(id);

        // Remove a OS e recarrega os dados
        $osInput.val('').prop('disabled', true); // Não permite edição da OS

        $.ajax({
            url: ROUTE_GET,
            type: 'POST',
            data: { pedido_id: id, csrf_token: CSRF_TOKEN },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const dados = response.data;

                    // Preenche campos do formulário (Exemplo)
                    $osInput.val(dados.os_numero);
                    $('#pedido-data-saida').val(dados.data_saida); // YYYY-MM-DD
                    $('#pedido-quantidade').val(dados.quantidade);
                    $('#pedido-bonus-perc').val(dados.percentual_bonus);
                    $('#pedido-valor-unitario').val(dados.valor_unitario);
                    $('#pedido-status').val(dados.status);
                    $('#pedido-salinidade').val(dados.salinidade);
                    $('#pedido-divisao').val(dados.divisao);

                    // Preenche o Select2 (Busca o nome e seta o valor)
                    const clienteOption = new Option(dados.cliente_nome, dados.cliente_entidade_id, true, true);
                    $('#pedido-cliente').append(clienteOption).trigger('change');

                    // Recalcula o total para exibição
                    calcularValorTotal();

                    $('#modal-pedido-label').text('Editar Pedido OS: ' + dados.os_numero);
                    $modal.modal('show');
                } else {
                    showToast(response.message, false);
                }
            }
        });
    });

    // --- CRUD: DELETAR ---
    $('#tabela-pedidos').on('click', '.btn-deletar', function () {
        const id = $(this).data('id');
        const $row = $(this).parents('tr');
        const os_numero = $row.find('td:eq(0)').text();

        // Usa a função global
        confirmarExclusao('Confirmar Exclusão', `Deseja deletar o Pedido OS: ${os_numero}?`)
            .then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: ROUTE_DELETAR,
                        type: 'POST',
                        data: { pedido_id: id, csrf_token: CSRF_TOKEN },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                // Usa o Toast global para sucesso rápido
                                Toast.fire({ icon: 'success', title: response.message });
                                table.ajax.reload(null, false);
                            } else {
                                msgErro('Falha ao excluir: ' + response.message);
                            }
                        },
                        error: function () {
                            msgErro('Erro de comunicação ao deletar.');
                        }
                    });
                }
            });
    });    
});
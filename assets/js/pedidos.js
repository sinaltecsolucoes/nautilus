// Este bloco será movido para assets/js/pedidos.js e usará as rotas /pedidos/listar e /pedidos/salvar
document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('pedido-data');
    if (!rootData) return;
    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;
    const ROUTE_GET = BASE_URL + '/pedidos/get';
    const ROUTE_DELETAR = BASE_URL + '/pedidos/deletar';
    const ROUTE_NEXT_OS = BASE_URL + '/pedidos/next-os';

    const $modal = $('#modal-pedido');
    const $form = $('#form-pedido');
    const $qtdInput = $('#pedido-quantidade');
    const $bonusInput = $('#pedido-bonus-perc');
    const $unitarioInput = $('#pedido-valor-unitario');
    const $totalInput = $('#pedido-valor-total');
    const $idInput = $('#pedido-id');
    const $osInput = $('#pedido-os-numero');



    // Funções de Cálculo
    function calcularValorTotal() {
        const qtd = parseFloat($qtdInput.val()) || 0;
        const unitario = parseFloat($unitarioInput.val()) || 0;
        const total = qtd * unitario;
        $totalInput.val(total.toFixed(2));
    }

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
                    showToast('Não foi possível gerar o número de OS.', false);
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

    // Submissão do Formulário (AJAX)
    $form.on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const url = BASE_URL + '/pedidos/salvar';

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $modal.modal('hide');
                    table.ajax.reload();
                    // Implementar notificação de sucesso
                } else {
                    // Implementar notificação de erro
                }
            });
    });

    if ($.fn.select2) {
        $('#pedido-cliente').select2({
            placeholder: "Buscar Cliente por Nome, CNPJ ou Código...",
            dropdownParent: $modal, // Garante que a lista de opções apareça sobre o modal
            minimumInputLength: 3, // Começa a buscar após 3 caracteres
            language: "pt-BR",
            ajax: {
                url: BASE_URL + "/pedidos/clientes-options", // A rota AJAX criada
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        term: params.term, // Termo de busca
                        csrf_token: CSRF_TOKEN
                    };
                },
                processResults: function (data) {
                    // O Controller já retorna no formato {results: []}
                    return {
                        results: data.results
                    };
                },
                cache: true
            }
        });
    }

    // Adiciona a lógica de cálculo de valor total após a entrada do bônus
    $bonusInput.on('input', calcularValorTotal);

    // O bônus é uma regra de negócio que altera a quantidade de entrega, mas não o valor total (que é baseado na quantidade vendida)
    function calcularValorTotal() {
        // Assume-se que o Valor Total é baseado na QUANTIDADE VENDIDA (sem bônus)
        const qtd = parseFloat($qtdInput.val()) || 0;
        const unitario = parseFloat($unitarioInput.val()) || 0;

        // Se a regra de negócio for: Valor Total = Quantidade * Valor Unitário
        const valorTotalBase = qtd * unitario;

        // Se a regra de negócio for: Valor Total = Quantidade_Com_Bônus * Valor Unitário (como está no Model)
        // Precisamos do bônus para calcular a Quantidade Com Bônus (qtd_com_bonus)
        const bonusPerc = parseFloat($bonusInput.val()) || 0;
        const fatorBonus = 1 + (bonusPerc / 100);
        const qtdComBonus = Math.round(qtd * fatorBonus);

        const valorTotalCalculado = qtdComBonus * unitario;

        $totalInput.val(valorTotalCalculado.toFixed(2));

        // Se fosse o cálculo do valor total baseado na quantidade *entregue* (com bônus):
        /* const bonusPerc = parseFloat($bonusInput.val()) || 0;
        const fatorBonus = 1 + (bonusPerc / 100);
        const qtdComBonus = qtd * fatorBonus;
        const valorTotalComBonus = qtdComBonus * unitario;
        $totalInput.val(valorTotalComBonus.toFixed(2));
        */
    }

    // Reseta o formulário e modal ao fechar/abrir em modo CREATE
    $modal.on('hidden.bs.modal', function () {
        $idInput.val('');
        $form[0].reset();
        $('#modal-pedido-label').text('Adicionar Novo Pedido');
        // Carrega a próxima OS apenas no modo CREATE
        if (!$idInput.val()) {
            loadNextOSNumber();
        }
    });

    // Abre o modal em modo 'Criar'
    $('#btn-adicionar-pedido').on('click', function () {
        $idInput.val('');
        $form[0].reset();
        $('#modal-pedido-label').text('Adicionar Novo Pedido');
        loadNextOSNumber(); // Força a busca da OS
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
        const os_numero = $row.find('td:eq(0)').text(); // Pega o número da OS da primeira coluna

        Swal.fire({
            title: 'Confirmar Exclusão',
            text: `Deseja realmente DELETAR o Pedido OS: ${os_numero}? Esta ação é IRREVERSÍVEL!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, Deletar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ROUTE_DELETAR,
                    type: 'POST',
                    data: { pedido_id: id, csrf_token: CSRF_TOKEN },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            showToast(response.message, true);
                            table.ajax.reload(null, false);
                        } else {
                            showToast('Falha ao excluir: ' + response.message, false);
                        }
                    },
                    error: function () {
                        showToast('Erro de comunicação ao deletar.', false);
                    }
                });
            }
        });
    });

    // --- SUBMISSÃO DO FORMULÁRIO (AJUSTADA PARA SWEETALERT) ---
    $form.on('submit', function (e) {
        e.preventDefault();

        const url = BASE_URL + '/pedidos/salvar';
        const formData = new FormData(this);

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $modal.modal('hide');
                    table.ajax.reload();
                    showToast(data.message, true);
                } else {
                    showToast(data.message, false);
                }
            })
            .catch(error => {
                showToast('Erro de comunicação com o servidor.', false);
            });
    });

});
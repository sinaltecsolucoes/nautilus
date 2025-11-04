document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('expedicao-data');
    if (!rootData) return;
    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;
    const $modal = $('#modal-expedicao');
    const $form = $('#form-expedicao');
    const ROUTE_GET = BASE_URL + '/expedicao/get';
    const ROUTE_DELETAR = BASE_URL + '/expedicao/deletar';

    // 1. Inicialização do DataTables (Esqueleto)
    const table = $('#tabela-expedicoes').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": BASE_URL + "/expedicao/listar",
            "type": "POST",
            "data": { csrf_token: CSRF_TOKEN }
        },
        "columns": [
            { "data": "numero_carregamento" },
            { "data": "data_carregamento" },
            { "data": "veiculo_placa" },
            { "data": "motorista_principal_nome" },
            { "data": "encarregado_nome" },
            { "data": "status_logistica" },
            {
                "data": "id", "orderable": false, "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm btn-deletar" data-id="${data}" title="Excluir"><i class="fas fa-trash"></i></button>
                    `;
                }
            }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        }
    });

    // 2. Inicialização dos Select2 (Veículos)
    $('#exp-veiculo-id').select2({
        dropdownParent: $modal,
        placeholder: 'Buscar Veículo...',
        ajax: {
            // ROTA CORRETA: O VeiculoController (ou um novo método) precisa de uma rota AJAX
            url: BASE_URL + '/veiculos/options', // Assumindo rota geral para Veículos
            dataType: 'json',
            delay: 250,
            processResults: function (data) { return data; },
            cache: true
        }
    });

    // Select2 para Pessoal (Motorista Principal, Reserva, Encarregado)
    function initPessoalSelect2(selector, placeholderText) {
        $(selector).select2({
            dropdownParent: $modal,
            placeholder: placeholderText,
            ajax: {
                // ROTA CORRETA
                url: BASE_URL + '/expedicao/pessoal-options',
                dataType: 'json',
                delay: 250,
                processResults: function (data) { return data; },
                cache: true
            }
        });
    }

    initPessoalSelect2('#exp-motorista-principal-id', 'Buscar Motorista...');
    initPessoalSelect2('#exp-motorista-reserva-id', 'Buscar Motorista Reserva...');
    initPessoalSelect2('#exp-encarregado-id', 'Buscar Encarregado...');

    // 3. Lógica de Data e Número Sequencial (Gerar o próximo Nº Carga)
    $('#modal-expedicao').on('shown.bs.modal', function () {
        const today = new Date().toISOString().split('T')[0];
        $('#exp-data-carregamento').val(today).trigger('change');
    });

    $('#exp-data-carregamento').on('change', function () {
        const dataCarregamento = $(this).val();
        if (!dataCarregamento) return;

        // Requisição para buscar o próximo número sequencial
        $.get(BASE_URL + '/expedicao/next-carga-number', { data: dataCarregamento, csrf_token: CSRF_TOKEN }) // Rota a ser criada
            .done(function (response) {
                if (response.success) {
                    $('#exp-numero-carregamento').val(response.proximo_numero);
                } else {
                    $('#exp-numero-carregamento').val('ERRO');
                }
            });
    });

    // 4. Submissão do Formulário (AJAX)
    $form.on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const url = BASE_URL + '/expedicao/salvar';

        // NOVO: Integração com SweetAlert2
        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success');
                    $modal.modal('hide');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Erro!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro de comunicação!', 'Não foi possível conectar ao servidor.', 'error');
            });
    });

    $('#exp-previsao-id').select2({
        dropdownParent: $modal,
        placeholder: 'Vincular Pedido / Previsão...',
        ajax: {
            url: BASE_URL + '/expedicao/pedidos-pendentes', // Rota que acabamos de criar
            dataType: 'json',
            delay: 250,
            processResults: function (data) { return data; },
            cache: true
        }
    });

    // --- CRUD: EDIÇÃO ---
    $('#tabela-expedicoes').on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $('#exp-id').val(id);

        // Remove os Select2 para evitar erros e os recria na função de sucesso
        $('#exp-veiculo-id').empty();
        $('#exp-motorista-principal-id').empty();
        $('#exp-motorista-reserva-id').empty();
        $('#exp-encarregado-id').empty();
        $('#exp-previsao-id').empty();

        $.ajax({
            url: ROUTE_GET,
            type: 'POST',
            data: { exp_id: id, csrf_token: CSRF_TOKEN },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const dados = response.data;

                    // Preenche campos simples
                    $('#exp-data-carregamento').val(dados.data_carregamento);
                    $('#exp-numero-carregamento').val(dados.numero_carregamento).prop('disabled', true);
                    $('#exp-horario-expedicao').val(dados.horario_expedicao);
                    $('#exp-quantidade-caixas').val(dados.quantidade_caixas_prevista);
                    $('#exp-observacao').val(dados.observacao);

                    // Preenche Select2 (Requer lógica para carregar o item e exibir o texto)
                    // Veículos
                    if (dados.veiculo_id) {
                        // Implementação simplificada: se não for Select2 com AJAX, preencheria o select normal.
                        // Para Select2 AJAX, você deve carregar a opção antes de inicializar/setar.
                        // Aqui, apenas setamos um placeholder com o ID:
                        const veicOption = new Option(`Veículo ID: ${dados.veiculo_id}`, dados.veiculo_id, true, true);
                        $('#exp-veiculo-id').append(veicOption).val(dados.veiculo_id).trigger('change');
                    }
                    // Motoristas, Encarregados, Previsão (Lógica similar acima)

                    $('#modal-expedicao-label').text('Editar Expedição Nº ' + dados.numero_carregamento);
                    $modal.modal('show');
                } else {
                    Swal.fire('Erro!', response.message, 'error');
                }
            }
        });
    });


    // --- CRUD: DELETAR ---
    $('#tabela-expedicoes').on('click', '.btn-deletar', function () {
        const id = $(this).data('id');
        const $row = $(this).parents('tr');
        const numeroCarga = $row.find('td:eq(0)').text(); // Pega o Nº Carga da primeira coluna

        Swal.fire({
            title: 'Confirmar Exclusão',
            text: `Deseja DELETAR a Expedição Nº ${numeroCarga}? Se houver registros de Frota ou Custos, a exclusão será bloqueada.`,
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
                    data: { exp_id: id, csrf_token: CSRF_TOKEN },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('Sucesso!', response.message, 'success');
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire('Erro!', response.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Erro!', 'Erro de comunicação ao deletar.', 'error');
                    }
                });
            }
        });
    });
});
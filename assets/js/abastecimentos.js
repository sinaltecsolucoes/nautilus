$(document).ready(function () {
    const $root = $('#abastecimento-data');
    const BASE_URL = $root.data('baseUrl');
    const CSRF_TOKEN = $root.data('csrfToken');

    const ROUTE_LISTAR = BASE_URL + '/abastecimentos/listar';
    const ROUTE_SALVAR = BASE_URL + '/abastecimentos/salvar';
    const ROUTE_GET = BASE_URL + '/abastecimentos/get';
    const ROUTE_DEL = BASE_URL + '/abastecimentos/deletar';
    const ROUTE_POSTOS = BASE_URL + '/abastecimentos/getPostosOptions'; // Ajuste a rota
    const ROUTE_VEICULOS = BASE_URL + '/abastecimentos/getVeiculosOptions'; // Ajuste a rota

    const $modal = $('#modal-abastecimento');
    const $form = $('#form-abastecimento');
    const $btnSalvar = $('#btn-salvar-abastecimento');

    // Máscaras (Dependência jQuery Mask)
    $('.money').mask('#.##0,00', { reverse: true });
    $('#total_litros').mask('#.##0,000', { reverse: true }); // 3 casas decimais para litros

    // 1. SELECT2 - Postos
    $('#entidade_id').select2({
        dropdownParent: $modal,
        theme: 'bootstrap-5',
        placeholder: 'Buscar Posto (Nome ou CNPJ)',
        ajax: {
            url: ROUTE_POSTOS,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { term: params.term };
            },
            processResults: function (data) {
                // PHP deve retornar: { results: [{id: 1, text: 'Posto X'}, ...] }
                return { results: data.results };
            },
            cache: true
        }
    });

    // 2. SELECT2 - Veículos
    $('#veiculo_id').select2({
        dropdownParent: $modal,
        theme: 'bootstrap-5',
        placeholder: 'Buscar Placa',
        ajax: {
            url: ROUTE_VEICULOS,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { term: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            },
            cache: true
        }
    });

    // 3. CÁLCULO AUTOMÁTICO
    function calcularTotal() {
        let litros = parseFloat($('#total_litros').val().replace('.', '').replace(',', '.')) || 0;
        let unitario = parseFloat($('#valor_unitario').val().replace('.', '').replace(',', '.')) || 0;
        let total = litros * unitario;

        $('#valor_total').val(total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    }
    $('#total_litros, #valor_unitario').on('keyup change', calcularTotal);

    // 4. DATATABLES
    let table = $('#tabela-abastecimentos').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": ROUTE_LISTAR,
            "type": "POST",
            "data": { csrf_token: CSRF_TOKEN }
        },
        "order": [[0, "desc"]],
        "columns": [
            {
                "data": "data_abastecimento",
                "render": function (data) {
                    if (!data) return '';
                    let date = new Date(data);
                    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                }
            },
            { "data": "motorista_apelido" },
            { "data": "nome_posto" }, // Agora vem do JOIN
            { "data": "descricao_combustivel" },
            { "data": "numero_cupom" },
            {
                "data": "placa_veiculo",
                "render": function (data) { return `<span class="badge bg-secondary">${data}</span>`; }
            },
            { "data": "quilometro_abast" },
            { "data": "total_litros" },
            {
                "data": "valor_total",
                "render": function (data) { return 'R$ ' + parseFloat(data).toLocaleString('pt-BR', { minimumFractionDigits: 2 }); }
            },
            {
                "data": "id",
                "orderable": false,
                "className": "text-center",
                "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                        <button class="btn btn-danger btn-sm btn-deletar" data-id="${data}" title="Excluir"><i class="fas fa-trash"></i></button>
                    `;
                }
            }
        ],
        "language": window.DataTablesPtBr
    });

    // 5. SALVAR
    $form.on('submit', function (e) {
        e.preventDefault();
        const originalText = $btnSalvar.html();
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: ROUTE_SALVAR,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modal.modal('hide');
                table.ajax.reload(null, false);
                msgSucesso(response.message);
            } else {
                msgErro(response.message);
            }
        }).always(function () {
            $btnSalvar.prop('disabled', false).html(originalText);
        });
    });

    // 6. EDITAR
    $('#tabela-abastecimentos').on('click', '.btn-editar', function () {
        let id = $(this).data('id');
        $.post(ROUTE_GET, { id: id, csrf_token: CSRF_TOKEN }, function (res) {
            let r = JSON.parse(res); // Se o controller já devolver JSON header, isso pode ser redundante, mas mal não faz
            if (r.success) {
                let d = r.data;

                $('#abast_id').val(d.id);
                $('#funcionario_id').val(d.funcionario_id);
                $('#data').val(d.data); // Controller deve devolver formatado Y-m-d
                $('#hora').val(d.hora);
                $('#quilometro_abast').val(d.quilometro_abast);
                $('#descricao_combustivel').val(d.descricao_combustivel);
                $('#numero_cupom').val(d.numero_cupom);

                // Select2: Criar Option se não existir para exibir o texto
                if (d.entidade_id) {
                    let newOption = new Option(d.nome_posto, d.entidade_id, true, true);
                    $('#entidade_id').append(newOption).trigger('change');
                }
                if (d.veiculo_id) {
                    let newOption = new Option(d.placa_veiculo, d.veiculo_id, true, true);
                    $('#veiculo_id').append(newOption).trigger('change');
                }

                // Valores
                $('#valor_unitario').val(parseFloat(d.valor_unitario).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
                $('#total_litros').val(parseFloat(d.total_litros).toLocaleString('pt-BR', { minimumFractionDigits: 3 }));
                $('#valor_total').val(parseFloat(d.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));

                $('#modalLabel').text('Editar Lançamento');
                $modal.modal('show');
            }
        });
    });

    // 7. DELETAR COM DETALHES
    $('#tabela-abastecimentos').on('click', '.btn-deletar', function () {
        let rowData = table.row($(this).parents('tr')).data();
        let id = $(this).data('id');

        // Formata os dados para exibir no alerta
        let htmlDetalhes = `
            <div class="text-start fs-6">
                <p><strong>Cupom:</strong> ${rowData.numero_cupom}</p>
                <p><strong>Data:</strong> ${new Date(rowData.data_abastecimento).toLocaleDateString('pt-BR')}</p>
                <p><strong>Combustível:</strong> ${rowData.descricao_combustivel}</p>
                <p><strong>Litros:</strong> ${rowData.total_litros}</p>
                <p><strong>Total:</strong> R$ ${parseFloat(rowData.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</p>
            </div>
            <p class="mt-3 text-danger fw-bold">Confirma a exclusão deste registro?</p>
        `;

        Swal.fire({
            title: 'Excluir Abastecimento?',
            html: htmlDetalhes,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ROUTE_DEL, { id: id, csrf_token: CSRF_TOKEN }, function (res) {
                    // let r = JSON.parse(res); // Depende do header
                    table.ajax.reload(null, false);
                    Toast.fire({ icon: 'success', title: 'Registro excluído.' });
                });
            }
        });
    });

    // Limpar ao fechar
    $modal.on('hidden.bs.modal', function () {
        $form[0].reset();
        $('#entidade_id').val(null).trigger('change');
        $('#veiculo_id').val(null).trigger('change');
        $('#abast_id').val('');
        $('#modalLabel').text('Lançar Abastecimento');
    });
});
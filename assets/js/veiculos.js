$(document).ready(function () {
    // 1. LER CONFIGURAÇÕES 
    const $root = $('#veiculo-data');
    if (!$root.length) return;

    const BASE_URL = $root.data('baseUrl');
    const CSRF_TOKEN = $root.data('csrfToken');

    const ROUTE_LISTAR = BASE_URL + '/veiculos/listar';
    const ROUTE_SALVAR = BASE_URL + '/veiculos/salvar';
    const ROUTE_GET = BASE_URL + '/veiculos/get';
    const ROUTE_DELETAR = BASE_URL + '/veiculos/deletar';
    const ROUTE_PROPRIETARIOS = BASE_URL + '/veiculos/getProprietariosOptions';

    const $modal = $('#modal-veiculo');
    const $form = $('#form-veiculo');
    const $btnSalvar = $('#btn-salvar-veiculo');
    const $proprietarioSelect = $('#proprietario_entidade_id');

    // Máscaras
    if ($.fn.mask) {
        $('.money').mask('#.##0,00', { reverse: true });
    }

    // 2. DATATABLES
    let tabelaVeiculos = $('#tabela-veiculos').DataTable({
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
                "data": "codigo_veiculo",
                "className": "text-primary fw-bold",
                "render": function (data) {
                    return data ? data.toUpperCase() : '-';
                }
            },
            { "data": "placa", "className": "fw-bold" },
            {
                "data": "modelo", // O dado principal desta coluna é o modelo
                "render": function (data, type, row) {
                    // Verifica se a marca existe E não está vazia
                    if (row.marca && row.marca.trim() !== '') {
                        return `${row.marca} / ${data}`; // Ex: Ford / Ka
                    }
                    return data; // Ex: Ka (se não tiver marca)
                }
            },
            { "data": "ano" },
            { "data": "tipo_frota" },
            {
                "data": "situacao",
                "className": "text-center",
                "render": function (data) {
                    const classes = { 'Ativo': 'bg-success', 'Inativo': 'bg-danger', 'Manutencao': 'bg-warning text-dark' };
                    return `<span class="badge ${classes[data] || 'bg-secondary'}">${data}</span>`;
                }
            },
            { "data": "proprietario_nome" },
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

    // 3. SELECT2
    $proprietarioSelect.select2({
        dropdownParent: $modal,
        theme: 'bootstrap-5',
        placeholder: "Buscar Entidade...",
        allowClear: true,
        ajax: {
            url: ROUTE_PROPRIETARIOS,
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

    // 4. SALVAR
    $form.on('submit', function (e) {
        e.preventDefault();
        const originalText = $btnSalvar.html();
        $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: ROUTE_SALVAR,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
        }).done(function (response) {
            if (response.success) {
                tabelaVeiculos.ajax.reload(null, false);
                $modal.modal('hide');
                msgSucesso(response.message);
            } else {
                msgErro(response.message);
            }
        }).always(function () {
            $btnSalvar.prop('disabled', false).html(originalText);
        });
    });

    // 5. EDITAR
    /* $('#tabela-veiculos').on('click', '.btn-editar', function () {
         const id = $(this).data('id');
 
         $.post(ROUTE_GET, { veiculo_id: id, csrf_token: CSRF_TOKEN }, function (res) {
             let response = typeof res === 'string' ? JSON.parse(res) : res;
 
             if (response.success && response.data) {
                 const d = response.data;
 
                 $('#veiculo-id').val(d.id);
                 $('#veiculo-codigo').val(d.codigo_veiculo);
                 $('#veiculo-placa').val(d.placa);
                 $('#veiculo-marca').val(d.marca);
                 $('#veiculo-modelo').val(d.modelo);
                 $('#veiculo-tipo-frota').val(d.tipo_frota);
                 $('#veiculo-ano').val(d.ano);
                 $('#veiculo-combustivel').val(d.tipo_combustivel);
                 $('#veiculo-autonomia').val(d.autonomia);
                 $('#veiculo-renavam').val(d.renavam);
                 $('#veiculo-crv').val(d.crv);
                 $('#veiculo-chassi').val(d.chassi);
                 $('#veiculo-situacao').val(d.situacao);
 
                 // Valores monetários
                 if (d.valor_licenciamento) $('#veiculo-licenciamento').val(parseFloat(d.valor_licenciamento).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
                 if (d.valor_ipva) $('#veiculo-ipva').val(parseFloat(d.valor_ipva).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
 
                 // Select2
                 if (d.proprietario_entidade_id) {
                     const newOption = new Option(d.proprietario_nome, d.proprietario_entidade_id, true, true);
                     $proprietarioSelect.append(newOption).trigger('change');
                 } else {
                     $proprietarioSelect.val(null).trigger('change');
                 }
 
                 $('#modal-veiculo-label').text(`Editar: ${d.placa}`);
                 $modal.modal('show');
             } else {
                 msgErro(response.message);
             }
         });
     }); */

    // 5. EDITAR
    $('#tabela-veiculos').on('click', '.btn-editar', function () {
        const id = $(this).data('id');

        // Feedback visual no botão enquanto carrega
        const $btn = $(this);
        const originalContent = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

        $.post(ROUTE_GET, { veiculo_id: id, csrf_token: CSRF_TOKEN }, function (res) {
            let response = typeof res === 'string' ? JSON.parse(res) : res;

            if (response.success && response.data) {
                const d = response.data;

                // === CAMPOS DE TEXTO E SELECTS ===
                $('#veiculo-id').val(d.id);
                $('#veiculo-codigo').val(d.codigo_veiculo); // Código (Apelido)
                $('#veiculo-placa').val(d.placa);
                $('#veiculo-marca').val(d.marca);
                $('#veiculo-modelo').val(d.modelo);
                $('#veiculo-tipo-frota').val(d.tipo_frota);
                $('#veiculo-ano').val(d.ano);
                $('#veiculo-combustivel').val(d.tipo_combustivel);
                $('#veiculo-autonomia').val(d.autonomia);

                // Documentação
                $('#veiculo-renavam').val(d.renavam);
                $('#veiculo-crv').val(d.crv);
                $('#veiculo-chassi').val(d.chassi);
                $('#veiculo-situacao').val(d.situacao);

                // === VALORES MONETÁRIOS ===
                // Formata para o padrão brasileiro (1.200,50) antes de jogar no input
                if (d.valor_licenciamento) {
                    $('#veiculo-licenciamento').val(parseFloat(d.valor_licenciamento).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
                } else {
                    $('#veiculo-licenciamento').val('');
                }

                if (d.valor_ipva) {
                    $('#veiculo-ipva').val(parseFloat(d.valor_ipva).toLocaleString('pt-BR', { minimumFractionDigits: 2 }));
                } else {
                    $('#veiculo-ipva').val('');
                }

                // === SELECT2 (Proprietário) ===
                if (d.proprietario_entidade_id) {
                    // Cria a opção visualmente para o Select2 saber que existe
                    const newOption = new Option(d.proprietario_nome, d.proprietario_entidade_id, true, true);
                    $proprietarioSelect.append(newOption).trigger('change');
                } else {
                    $proprietarioSelect.val(null).trigger('change');
                }

                $('#modal-veiculo-label').text(`Editar: ${d.placa}`);
                $modal.modal('show');
            } else {
                msgErro(response.message);
            }
        })
            .fail(function () {
                msgErro('Erro de comunicação ao buscar dados.');
            })
            .always(function () {
                // Restaura o botão
                $btn.html(originalContent).prop('disabled', false);
            });
    });

    // 6. DELETAR
    $('#tabela-veiculos').on('click', '.btn-deletar', function () {
        const id = $(this).data('id');
        const rowData = tabelaVeiculos.row($(this).parents('tr')).data();
        const placa = rowData ? rowData.placa : '?';

        confirmarExclusao('Excluir Veículo?', `Confirma exclusão da placa <b>${placa}</b>?`)
            .then((result) => {
                if (result.isConfirmed) {
                    $.post(ROUTE_DELETAR, { veiculo_id: id, csrf_token: CSRF_TOKEN }, function (res) {
                        let response = typeof res === 'string' ? JSON.parse(res) : res;
                        if (response.success) {
                            tabelaVeiculos.ajax.reload(null, false);
                            msgSucesso(response.message);
                        } else {
                            msgErro(response.message);
                        }
                    });
                }
            });
    });

    $modal.on('hidden.bs.modal', function () {
        $form[0].reset();
        $('#veiculo-id').val('');
        $proprietarioSelect.val(null).trigger('change');
        $('#modal-veiculo-label').text('Adicionar Novo Veículo');
    });
});
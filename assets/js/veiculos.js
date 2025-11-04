/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Veículos
 * Local: assets/js/veiculos.js
 * Descrição: Gerencia o CRUD da frota (VEICULOS) via AJAX e DataTables com Select2.
 */

document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token); ?>';

    const ROUTE_LISTAR = BASE_URL + '/veiculos/listar';
    const ROUTE_SALVAR = BASE_URL + '/veiculos/salvar';
    const ROUTE_GET = BASE_URL + '/veiculos/get';
    const ROUTE_DELETAR = BASE_URL + '/veiculos/deletar';
    const ROUTE_PROPRIETARIOS = BASE_URL + '/entidades/proprietarios-options'; // Rota que buscaremos

    const $modal = $('#modal-veiculo');
    const $form = $('#form-veiculo');
    const $idInput = $('#veiculo-id');
    const $msgArea = $('#mensagem-veiculo');
    const $proprietarioSelect = $('#veiculo-proprietario');

    let tabelaVeiculos;

    // --- FUNÇÕES AUXILIARES COM SWEETALERT2 ---
    function showToast(message, type = 'success') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 4000
        });
    }

    // ===============================================================
    // 1. Inicialização do DataTables
    // ===============================================================
    if ($('#tabela-veiculos').length) {
        tabelaVeiculos = $('#tabela-veiculos').DataTable({
            "serverSide": true,
            "processing": true,
            "ajax": {
                "url": ROUTE_LISTAR,
                "type": "POST",
                "data": { csrf_token: CSRF_TOKEN }
            },
            "columns": [
                { "data": "placa", "width": "10%" },
                { "data": "modelo" },
                { "data": "tipo_frota", "width": "15%" },
                { "data": "proprietario_nome" },
                {
                    "data": "situacao",
                    "render": function (data) {
                        const classes = { 'Ativo': 'bg-success', 'Inativo': 'bg-danger', 'Manutencao': 'bg-warning' };
                        return `<span class="badge ${classes[data] || 'bg-secondary'}">${data}</span>`;
                    },
                    "width": "12%"
                },
                {
                    "data": "id",
                    "orderable": false,
                    "render": function (data) {
                        return `
                            <button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-danger btn-sm btn-deletar" data-id="${data}" title="Excluir"><i class="fas fa-trash"></i></button>
                        `;
                    },
                    "width": "15%"
                }
            ],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" // CORRIGIDO PARA CDN
            },
            responsive: true
        });
    }

    // ===============================================================
    // 2. Integração Select2 (Proprietário - Entidade)
    // ===============================================================
    if ($.fn.select2) {
        $proprietarioSelect.select2({
            dropdownParent: $modal,
            placeholder: "Buscar Cliente/Fornecedor (Entidade)...",
            minimumInputLength: 3,
            ajax: {
                url: ROUTE_PROPRIETARIOS,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        term: params.term,
                        csrf_token: CSRF_TOKEN
                    };
                },
                processResults: function (data) {
                    return { results: data.results }; // O PHP deve retornar {results: [{id: 1, text: '...'}, ...]}
                },
                cache: true
            }
        });
    }

    // ===============================================================
    // 3. CRUD (Edição e Salvar)
    // ===============================================================

    // Reseta o formulário ao fechar/abrir em modo CREATE
    $modal.on('hidden.bs.modal', function () {
        $form[0].reset();
        $idInput.val('');
        $msgArea.html('');
        $('#modal-veiculo-label').text('Adicionar Novo Veículo');
        $proprietarioSelect.empty().val(null).trigger('change'); // Limpa Select2
    });

    // Abre Modal para Edição (GET)
    $('#tabela-veiculos').on('click', '.btn-editar', function () {
        const id = $(this).data('id');
        $idInput.val(id);

        $.ajax({
            url: ROUTE_GET,
            type: 'POST',
            data: { veiculo_id: id, csrf_token: CSRF_TOKEN },
            dataType: 'json',
        }).done(function (response) {
            if (response.success && response.data) {
                const dados = response.data;

                // 1. Preenche campos
                $('#veiculo-placa').val(dados.placa);
                $('#veiculo-marca').val(dados.marca);
                $('#veiculo-modelo').val(dados.modelo);
                $('#veiculo-tipo-frota').val(dados.tipo_frota);
                $('#veiculo-ano').val(dados.ano);
                $('#veiculo-combustivel').val(dados.tipo_combustivel);
                $('#veiculo-autonomia').val(dados.autonomia);
                $('#veiculo-renavam').val(dados.renavam);
                $('#veiculo-crv').val(dados.crv);
                $('#veiculo-situacao').val(dados.situacao);

                // 2. Preenche Select2 (Proprietário)
                if (dados.proprietario_entidade_id && dados.proprietario_nome) {
                    const nome = dados.proprietario_nome;
                    const idProprietario = dados.proprietario_entidade_id;
                    const newOption = new Option(nome, idProprietario, true, true);
                    $proprietarioSelect.append(newOption).trigger('change');
                } else {
                    $proprietarioSelect.empty().val(null).trigger('change');
                }

                $('#modal-veiculo-label').text(`Editar Veículo: ${dados.placa}`);
                $modal.modal('show');
            } else {
                showToast(response.message, 'error');
            }
        }).fail(function () {
            showToast('Erro ao buscar dados de edição.', 'error');
        });
    });

    // Submissão do Formulário (Salvar/Atualizar)
    $form.on('submit', function (e) {
        e.preventDefault();

        $btnSalvar.prop('disabled', true); // Desabilita o botão para evitar duplicidade

        $.ajax({
            url: ROUTE_SALVAR,
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
        }).done(function (response) {
            $btnSalvar.prop('disabled', false);
            if (response.success) {
                tabelaVeiculos.ajax.reload(null, false);
                $modal.modal('hide');
                showToast(response.message, 'success');
            } else {
                showToast(response.message, 'error');
            }
        }).fail(function () {
            $btnSalvar.prop('disabled', false);
            showToast('Erro de Comunicação com o servidor.', 'error');
        });
    });


    // --- CRUD: DELETAR ---
    $('#tabela-veiculos').on('click', '.btn-deletar', function () {
        const id = $(this).data('id');
        const placa = tabelaVeiculos.row($(this).parents('tr')).data().placa;

        Swal.fire({
            title: 'Confirmar Exclusão',
            text: `Deseja DELETAR o veículo de placa ${placa}? Se estiver em uso, a exclusão será bloqueada!`,
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
                    data: { veiculo_id: id, csrf_token: CSRF_TOKEN },
                    dataType: 'json',
                }).done(function (response) {
                    if (response.success) {
                        tabelaVeiculos.ajax.reload(null, false);
                        showToast(response.message, 'success');
                    } else {
                        showToast(response.message, 'error');
                    }
                }).fail(function () {
                    showToast('Erro de comunicação para exclusão.', 'error');
                });
            }
        });
    });
});
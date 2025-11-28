$(document).ready(function () {
    const BASE_URL = $('#abastecimento-data').data('baseUrl');
    const ROUTE_LISTAR = BASE_URL + '/abastecimentos/listar';
    const ROUTE_SALVAR = BASE_URL + '/abastecimentos/salvar';
    const ROUTE_GET = BASE_URL + '/abastecimentos/get';
    const ROUTE_DEL = BASE_URL + '/abastecimentos/deletar';

    let table = $('#tabela-abastecimentos').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": { "url": ROUTE_LISTAR, "type": "POST" },
        "order": [[0, "desc"]],
        "columns": [
            {
                "data": "data_abastecimento",
                "render": function (data) {
                    // Formata AAAA-MM-DD HH:MM:SS para DD/MM/YYYY HH:MM
                    let date = new Date(data);
                    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                }
            },
            { "data": "motorista" },
            {
                "data": "cnpj_posto",
                "render": function (data, type, row) {
                    if (type === 'display' || type === 'filter') {
                        const limpo = (data || '').replace(/\D/g, '');
                        if (limpo.length === 14) {
                            // CNPJ: 00.000.000/0000-00
                            return limpo.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
                        } else if (limpo.length === 11) {
                            // CPF: 000.000.000-00
                            return limpo.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
                        }
                    }
                    return data; // Retorna o dado original se não for para exibição ou se for inválido

                }
            },

            { "data": "descricao_combustivel" },
            { "data": "numero_cupom" },
            {
                "data": "placa_veiculo",
                "render": function (data) { return '<span class="badge bg-secondary">' + data + '</span>'; }
            },
            { "data": "quilometro_abast" },
            { "data": "total_litros" },
            {
                "data": "valor_total",
                "render": function (data) { return 'R$ ' + parseFloat(data).toLocaleString('pt-BR', { minimumFractionDigits: 2 }); }
            },
            {
                "data": null,
                "defaultContent": `
                    <button class="btn btn-warning btn-sm btn-edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-danger btn-sm btn-del"><i class="fas fa-trash"></i></button>
                `
            }
        ],
        "language": window.DataTablesPtBr
    });

    // Salvar
    $('#form-abastecimento').on('submit', function (e) {
        e.preventDefault();
        $.post(ROUTE_SALVAR, $(this).serialize(), function (res) {
            let data = JSON.parse(res);
            if (data.success) {
                Swal.fire('Sucesso', data.message, 'success');
                $('#modal-abastecimento').modal('hide');
                table.ajax.reload();
            } else {
                Swal.fire('Erro', data.message, 'error');
            }
        });
    });

    // Editar
    $('#tabela-abastecimentos tbody').on('click', '.btn-edit', function () {
        let data = table.row($(this).parents('tr')).data();
        $.post(ROUTE_GET, { id: data.id }, function (res) {
            let r = JSON.parse(res);
            if (r.success) {
                $('#abast_id').val(r.data.id);
                $('#funcionario_id').val(r.data.funcionario_id);
                $('#data').val(r.data.data);
                $('#hora').val(r.data.hora);
                $('#cnpj_posto').val(r.data.cnpj_posto);
                $('#placa_veiculo').val(r.data.placa_veiculo);
                $('#quilometro_abast').val(r.data.quilometro_abast);
                $('#descricao_combustivel').val(r.data.descricao_combustivel);
                $('#numero_cupom').val(r.data.numero_cupom);
                $('#valor_unitario').val(r.data.valor_unitario);
                $('#total_litros').val(r.data.total_litros);
                $('#valor_total').val(r.data.valor_total);

                $('#modalLabel').text('Editar Lançamento');
                $('#modal-abastecimento').modal('show');
            }
        });
    });

    // Limpar Modal ao fechar
    $('#modal-abastecimento').on('hidden.bs.modal', function () {
        $('#form-abastecimento')[0].reset();
        $('#abast_id').val('');
        $('#modalLabel').text('Lançar Abastecimento');
    });

    // Deletar
    $('#tabela-abastecimentos tbody').on('click', '.btn-del', function () {
        let data = table.row($(this).parents('tr')).data();
        if (confirm('Excluir abastecimento de R$ ' + data.valor_total + '?')) {
            $.post(ROUTE_DEL, { id: data.id }, function (res) {
                table.ajax.reload();
            });
        }
    });

    // ===============================================
    // 7. Funções de Máscara (CEP, CPF, CNPJ, VALORES)
    // ===============================================

    // Aplica as máscaras padrão na inicialização
    aplicarMascarasPadrao();

    // Evento de troca de Tipo de Pessoa
    $('#tipo_pessoa').on('change', function () {
        const tipoPessoa = $(this).val();
        aplicarMascaraDocumento(tipoPessoa);
    });

    $('.money').mask('000.000.000,00', { reverse: true }); // Máscara para Valores R$

    /**
     * Aplica máscaras que são estáticas (CEP)
     */
    function aplicarMascarasPadrao() {
        // Máscara de CEP (Usada na aba principal e na aba adicional)
        $('input[name*="cep"]').mask('00000-000');

        // Aplica a máscara de documento inicial (baseada no valor padrão)
        aplicarMascaraDocumento($('#tipo_pessoa').val());
    }

    /**
     * Aplica a máscara de CPF ou CNPJ dependendo do tipo de pessoa.
     * @param {string} tipoPessoa 'Fisica' ou 'Juridica'
     */
    function aplicarMascaraDocumento(tipoPessoa) {
        const $cnpjCpfInput = $('#cnpj_cpf');

        // 1. Guarda o valor original (sem máscara)
        const valorOriginal = $cnpjCpfInput.data('raw') || $cnpjCpfInput.val() || '';

        // 2. Remove máscara antiga
        $cnpjCpfInput.unmask();

        // 3. Aplica nova máscara
        if (tipoPessoa === 'Fisica') {
            $cnpjCpfInput.mask('000.000.000-00', { reverse: true });
            $('#btn-buscar-cnpj').prop('disabled', true);
        } else {
            $cnpjCpfInput.mask('00.000.000/0000-00', { reverse: true });
            $('#btn-buscar-cnpj').prop('disabled', false);
        }

        // 4. Restaura o valor original e força a formatação
        $cnpjCpfInput.val(valorOriginal).trigger('input');
    }
});
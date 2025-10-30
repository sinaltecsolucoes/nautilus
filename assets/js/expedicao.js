document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('expedicao-data');
    if (!rootData) return;
    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;
    const $modal = $('#modal-expedicao');
    const $form = $('#form-expedicao');

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
                    // Futuramente: Botões de Edição, Detalhes e Iniciar Carregamento
                    return `<a href="#" class="btn btn-info btn-sm">Detalhes</a>`;
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
    });

    // 2. Inicialização dos Select2 (Motoristas, Encarregados, Veículos)

    // Select2 para Veículos
    $('#exp-veiculo-id').select2({
        dropdownParent: $modal,
        placeholder: 'Buscar Veículo...',
        ajax: {
            url: BASE_URL + '/manutencao/veiculos-options', // Reutiliza a rota de veículos do ManutencaoController
            dataType: 'json',
            delay: 250,
            processResults: function (data) { return data; }, // O controller já retorna o formato {results: []}
            cache: true
        }
    });

    // Select2 para Pessoal (Motorista Principal, Reserva, Encarregado)
    function initPessoalSelect2(selector, placeholderText) {
        $(selector).select2({
            dropdownParent: $modal,
            placeholder: placeholderText,
            ajax: {
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

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                const msgArea = document.getElementById('mensagem-expedicao');
                if (data.success) {
                    $modal.modal('hide');
                    table.ajax.reload();
                    // Implementar notificação de sucesso
                } else {
                    msgArea.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            });
    });
});
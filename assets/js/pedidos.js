// Este bloco será movido para assets/js/pedidos.js e usará as rotas /pedidos/listar e /pedidos/salvar
document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('pedido-data');
    if (!rootData) return;
    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;

    const $modal = $('#modal-pedido');
    const $form = $('#form-pedido');
    const $qtdInput = $('#pedido-quantidade');
    const $bonusInput = $('#pedido-bonus-perc');
    const $unitarioInput = $('#pedido-valor-unitario');
    const $totalInput = $('#pedido-valor-total');

    // Funções de Cálculo
    function calcularValorTotal() {
        const qtd = parseFloat($qtdInput.val()) || 0;
        const unitario = parseFloat($unitarioInput.val()) || 0;
        const total = qtd * unitario;
        $totalInput.val(total.toFixed(2));
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
            { "data": "data_saida" },
            { "data": "cliente_nome" },
            { "data": "quantidade_com_bonus" },
            { "data": "valor_total" },
            { "data": "status" },
            {
                "data": "id", "orderable": false, "render": function (data) {
                    return '<button class="btn btn-warning btn-sm btn-editar" data-id="' + data + '">Editar</button>';
                }
            }
        ],
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" }
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

    // NOTA: O Select2 para o campo Cliente será adicionado após o próximo passo.

});
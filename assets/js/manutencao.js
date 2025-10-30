/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Manutenção
 * Local: assets/js/manutencao.js
 * Descrição: Gerencia o CRUD da tabela MANUTENCOES via AJAX e DataTables.
 */

document.addEventListener('DOMContentLoaded', function () {
    // A URL Base e o Token CSRF serão lidos de um elemento HTML (div)
    const rootData = document.getElementById('manutencao-data');
    if (!rootData) {
        console.error("Elemento de dados raiz (manutencao-data) não encontrado.");
        return;
    }
    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;

    const form = document.getElementById('form-manutencao');
    const tabela = $('#tabela-manutencoes').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": BASE_URL + "/manutencao/listar", // Usa a URL base lida
            "type": "POST",
            "data": { csrf_token: CSRF_TOKEN } // Envia o token de segurança
        },
        "columns": [
            { "data": "data_servico" },
            { "data": "placa" },
            { "data": "tipo_manutencao" },
            { "data": "servico_peca" },
            { "data": "fornecedor_nome" },
            { "data": "valor" },
            {
                "data": "id", "orderable": false, "render": function (data) {
                    return '<button class="btn btn-warning btn-sm btn-editar" data-id="' + data + '">Editar</button>';
                }
            }
        ],
        // Configuração de idioma e outras opções do DataTables
        "language": { "url": BASE_URL + "/libs/DataTables-1.10.23/Portuguese-Brasil.json" } // Assumindo que você tem essa lib
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(form);
        const url = BASE_URL + '/manutencao/salvar';

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                const msgArea = document.getElementById('mensagem-manutencao');
                if (data.success) {
                    tabela.ajax.reload();
                    form.reset();
                    msgArea.innerHTML = '<div class="alert alert-success">Registro salvo com sucesso!</div>';
                } else {
                    msgArea.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Erro de rede:', error);
                document.getElementById('mensagem-manutencao').innerHTML = '<div class="alert alert-danger">Erro de comunicação com o servidor.</div>';
            });
    });

    document.getElementById('btn-cancelar-manutencao').addEventListener('click', function () {
        form.reset();
        document.getElementById('man-id').value = '';
        document.getElementById('mensagem-manutencao').innerHTML = '';
    });
});
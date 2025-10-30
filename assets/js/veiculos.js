document.addEventListener('DOMContentLoaded', function () {
    const tabela = $('#tabela-veiculos').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": "<?php echo BASE_URL; ?>/veiculos/listar", // Rota que criaremos
            "type": "POST",
            "data": { csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>' }
        },
        "columns": [
            { "data": "placa" },
            { "data": "modelo" },
            { "data": "tipo_frota" },
            { "data": "proprietario_nome" },
            { "data": "situacao" },
            {
                "data": "id", "orderable": false, "render": function (data) {
                    return '<button class="btn btn-warning btn-sm btn-editar" data-id="' + data + '">Editar</button>';
                }
            }
        ],
        // ... idioma e outros
    });

    // Aqui virá a lógica de Select2 para Proprietário e o submit do formulário
    // $("#veiculo-proprietario").select2({ /* AJAX configuration */ });
});
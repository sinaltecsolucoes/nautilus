document.addEventListener('DOMContentLoaded', function () {
    const saveUrlElement = document.getElementById('permissoes-save-url');
    if (!saveUrlElement) return;
    const saveUrl = saveUrlElement.dataset.url;

    const btnSalvar = document.getElementById('btn-salvar-permissoes');

    btnSalvar.addEventListener('click', function () {
        // Feedback visual de carregamento
        const originalText = btnSalvar.innerHTML;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        btnSalvar.disabled = true;

        // 1. Coleta o estado de TODOS os switches
        const checkboxes = document.querySelectorAll('.permission-checkbox');
        const payload = [];

        checkboxes.forEach(function (chk) {
            payload.push({
                cargo: chk.dataset.cargo,
                modulo: chk.dataset.modulo,
                acao: chk.dataset.acao,
                status: chk.checked ? 1 : 0 // 1 = Permitido, 0 = Negado
            });
        });

        // 2. Envia o JSON gigante para o PHP
        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ permissoes: payload })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    msgSucesso(data.message);
                   
                } else {
                    msgErro('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                msgErro('Erro de comunicação ao salvar.');
            })
            .finally(() => {
                // Restaura o botão
                btnSalvar.innerHTML = originalText;
                btnSalvar.disabled = false;
            });
    });
});
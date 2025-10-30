/**
 * SCRIPT JAVASCRIPT: Lógica do Módulo de Permissões
 * Local: assets/js/permissoes.js
 * Descrição: Gerencia a interação dos switches de permissão e a comunicação AJAX.
 */

document.addEventListener('DOMContentLoaded', function () {
    // A URL para o update precisa ser obtida do HTML, pois ela é gerada pelo PHP
    // Vamos buscar um elemento HTML que contenha essa informação (Ex: um campo de dados)

    // Supondo que você adicione esta URL no seu HTML (index.php da View de Permissões)
    const updateUrlElement = document.getElementById('permissoes-update-url');
    if (!updateUrlElement) {
        console.error("URL de atualização das permissões não encontrada no HTML.");
        return;
    }
    const updateUrl = updateUrlElement.dataset.url;

    const switches = document.querySelectorAll('.permission-switch');
    const statusMessage = document.getElementById('status-message');

    switches.forEach(function (switchElement) {
        switchElement.addEventListener('change', function () {
            const regraId = this.dataset.id;
            const isChecked = this.checked;

            // 1. Prepara os dados para envio
            const formData = new FormData();
            formData.append('id', regraId);
            formData.append('status', isChecked ? 'true' : 'false');

            // 2. Faz a chamada AJAX para o PermissaoController::update()
            fetch(updateUrl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    // 3. Exibe mensagem de status
                    if (data.success) {
                        statusMessage.textContent = data.message + ` (Módulo: ${this.dataset.modulo}, Cargo: ${this.dataset.cargo}, Ação: ${this.dataset.acao})`;
                        statusMessage.style.backgroundColor = '#d4edda';
                        statusMessage.style.color = '#155724';
                    } else {
                        // Reverte o switch se houver falha no servidor
                        this.checked = !isChecked;
                        statusMessage.textContent = 'Erro: ' + data.message;
                        statusMessage.style.backgroundColor = '#f8d7da';
                        statusMessage.style.color = '#721c24';
                    }
                    statusMessage.style.display = 'block';

                    // Oculta a mensagem após 5 segundos
                    setTimeout(() => statusMessage.style.display = 'none', 5000);
                })
                .catch(error => {
                    // Erro de rede ou parse de JSON
                    this.checked = !isChecked;
                    statusMessage.textContent = 'Erro de comunicação com o servidor: ' + error;
                    statusMessage.style.backgroundColor = '#f8d7da';
                    statusMessage.style.color = '#721c24';
                    statusMessage.style.display = 'block';
                    setTimeout(() => statusMessage.style.display = 'none', 5000);
                });
        });
    });
});
document.addEventListener('DOMContentLoaded', function () {
    const rootData = document.getElementById('relatorio-data');
    if (!rootData) return;
    const BASE_URL = rootData.dataset.baseUrl;
    const CSRF_TOKEN = rootData.dataset.csrfToken;
    const btnGerar = document.getElementById('btn-gerar-manutencao');
    const statusArea = document.getElementById('status-relatorio');

    btnGerar.addEventListener('click', function () {
        // Desabilita o botão e mostra um status
        btnGerar.disabled = true;
        statusArea.classList.remove('d-none', 'alert-success', 'alert-danger');
        statusArea.classList.add('alert-info');
        statusArea.innerHTML = 'Gerando PDF... Aguarde.';

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);

        fetch(BASE_URL + '/relatorios/manutencao-pdf', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                btnGerar.disabled = false;
                if (data.success) {
                    // Sucesso: Abre o PDF em uma nova aba (Assumindo que o arquivo foi salvo em public/)
                    statusArea.classList.replace('alert-info', 'alert-success');
                    statusArea.innerHTML = '✅ PDF gerado e salvo. <a href="' + data.pdf_url + '" target="_blank">Clique para abrir o relatório</a>.';

                } else {
                    // Erro
                    statusArea.classList.replace('alert-info', 'alert-danger');
                    statusArea.innerHTML = '🛑 Falha na geração do PDF: ' + data.message;
                }
            })
            .catch(error => {
                btnGerar.disabled = false;
                statusArea.classList.replace('alert-info', 'alert-danger');
                statusArea.innerHTML = 'Erro de comunicação com o servidor.';
            });
    });
});
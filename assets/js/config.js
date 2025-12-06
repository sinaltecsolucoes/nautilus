// public/assets/js/config.js
// Configuração global única do Nautilus ERP
// Carregado antes de qualquer módulo

document.addEventListener('DOMContentLoaded', function () {
    const root =
        document.querySelector('[data-csrf-token]') ||
        document.querySelector('[data-base-url]');
    if (!root) {
        console.error('Erro crítico: data-base-url não encontrado na página.');
        return;
    }

    window.APP_CONFIG = {
        BASE_URL: root.dataset.baseUrl?.replace(/\/$/, '') || '',
        CSRF_TOKEN: root.dataset.csrfToken || '',
        PAGE_MODULE: root.dataset.pageModule || document.body.dataset.pageModule || 'unknown',
        DEBUG: root.dataset.debug === 'true'
    };

    // Recarrega CSRF a cada 30min (segurança extra)
    if (window.APP_CONFIG.CSRF_TOKEN) {
        setInterval(async () => {
            try {
                const res = await fetch(`${window.APP_CONFIG.BASE_URL}/auth/refresh-csrf`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.csrf_token) {
                    window.APP_CONFIG.CSRF_TOKEN = data.csrf_token;
                }
            } catch (e) { /* silencioso */ }
        }, 30 * 60 * 1000);
    }

    // DISPARA O EVENTO QUANDO TUDO ESTIVER PRONTO
    document.dispatchEvent(new Event('app:config-ready'));
});
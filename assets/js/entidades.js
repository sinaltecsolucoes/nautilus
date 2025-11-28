$(document).ready(function () {
    // Detecta o tipo de página (cliente/fornecedor)
    // O JS agora busca o valor do input hidden 'ent_tipo' se o body data não existir
    const pageType = $('body').data('page-type') || $('#ent_tipo').val()?.toLowerCase() || 'cliente';

    const $root = $('#entidade-data');
    if (!$root.length) return; // segurança

    const BASE_URL = $root.data('baseUrl');
    const CSRF_TOKEN = $root.data('csrfToken');
    const ROUTE_LISTAR = BASE_URL + '/entidades/listar';
    const ROUTE_SALVAR = BASE_URL + '/entidades/salvar';
    const ROUTE_GET = BASE_URL + '/entidades/getEntidade';
    const ROUTE_DELETAR = BASE_URL + '/entidades/deletar';
    const ROUTE_API_BUSCA = BASE_URL + '/entidades/api-busca';
    const ROUTE_END_LISTAR = BASE_URL + '/entidades/enderecos/listar';
    const ROUTE_END_SALVAR = BASE_URL + '/entidades/enderecos/salvar';
    const ROUTE_END_GET = BASE_URL + '/entidades/enderecos/get';
    const ROUTE_END_DELETAR = BASE_URL + '/entidades/enderecos/deletar';


    //const csrfToken = $('input[name="csrf_token"]').val(); // Pega do formulário
    const csrfToken = $root.data('csrfToken'); // Pega do formulário
    const $modalEntidade = $('#modal-entidade'); // ID correto do modal na View
    const $formEntidade = $('#form-entidade');
    const $formEndereco = $('#form-endereco-adicional');

    // SELETORES SINCRONIZADOS COM O HTML (lista_entidades.php)
    const $tipoPessoaSelect = $('#tipo_pessoa'); // Agora é um select, não radio
    const $cpfCnpjInput = $('#cnpj_cpf'); // ID: cnpj_cpf
    const $labelCpfCnpj = $('label[for="cnpj_cpf"]');
    const $btnBuscarCnpj = $('#btn-buscar-cnpj');
    const $debugApiInfo = $('#api-debug-info');

    // Botões de endereço
    const $btnBuscarCepAdicional = $('#btn-buscar-cep-adic');

    let tableEntidades, tableEnderecos;

    // =================================================================
    // 1. LÓGICA DE MÁSCARAS E CAMPOS
    // =================================================================

    function updatePessoaFields() {
        const tipo = $tipoPessoaSelect.val(); // 'Fisica' ou 'Juridica'

        $cpfCnpjInput.unmask();

        if (tipo === 'Juridica') {
            $labelCpfCnpj.text('CNPJ (*)');
            $cpfCnpjInput.attr('placeholder', '00.000.000/0000-00');
            $cpfCnpjInput.mask('00.000.000/0000-00');
            $btnBuscarCnpj.show(); // Mostra busca
        } else {
            $labelCpfCnpj.text('CPF (*)');
            $cpfCnpjInput.attr('placeholder', '000.000.000-00');
            $cpfCnpjInput.mask('000.000.000-00');
            $btnBuscarCnpj.hide(); // Esconde busca para CPF
        }
    }

    // Inicializa máscaras
    updatePessoaFields();
    $tipoPessoaSelect.on('change', updatePessoaFields);

    // =================================================================
    // 2. BUSCA DE API (CNPJ)
    // =================================================================

    $btnBuscarCnpj.on('click', function () {
        const cnpj = $cpfCnpjInput.val();
        const feedback = $debugApiInfo;

        feedback.text('Consultando API...').removeClass('text-danger text-success');
        $(this).prop('disabled', true);

        $.ajax({
            url: ROUTE_API_BUSCA, // Rota do Controller
            type: 'POST',
            data: { termo: cnpj, tipo: 'cnpj' },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                const d = response.data;

                // Preenche campos principais (IDs corrigidos conforme HTML)
                $('#razao_social').val(d.razao_social);
                $('#nome_fantasia').val(d.nome_fantasia);
                $('#inscricao_estadual_rg').val(d.inscricao_estadual);

                // Preenche Endereço Principal
                $('#end_cep').val(d.cep).trigger('input'); // Trigger para máscara funcionar
                $('#end_logradouro').val(d.logradouro);
                $('#end_numero').val(d.numero);
                $('#end_complemento').val(d.complemento);
                $('#end_bairro').val(d.bairro);
                $('#end_cidade').val(d.cidade);
                $('#end_uf').val(d.uf);

                feedback.text('Sucesso: ' + d.api_origem).addClass('text-success');
            } else {
                feedback.text('Erro: ' + response.message).addClass('text-danger');
            }
        }).fail(function () {
            feedback.text('Erro de comunicação com o servidor.').addClass('text-danger');
        }).always(function () {
            $btnBuscarCnpj.prop('disabled', false);
        });
    });

    // =================================================================
    // 3. DATATABLES (LISTAGEM)
    // =================================================================

    tableEntidades = $('#tabela-entidades').DataTable({
        "serverSide": true,
        "processing": true,
        "ajax": {
            "url": ROUTE_LISTAR,
            "type": "POST",
            "data": function (d) {
                d.tipo_entidade = pageType;
                d.csrf_token = csrfToken;
            }
        },
        "responsive": true,
        "order": [[2, "asc"]], // Ordena por Razão Social
        "columns": [
            {
                "data": "situacao",
                "className": "text-center",
                "render": data => (data === 'Ativo')
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-danger">Inativo</span>'
            },
            { "data": "tipo" }, // Cliente/Fornecedor
            { "data": "razao_social" },
            { "data": "cnpj_cpf" },
            {
                "data": "id",
                "orderable": false,
                "className": "text-center",
                "render": function (data) {
                    return `
                        <button class="btn btn-warning btn-sm btn-editar-entidade me-1" data-id="${data}">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-danger btn-sm btn-inativar-entidade" data-id="${data}">
                            <i class="fas fa-ban"></i>
                        </button>
                    `;
                }
            }
        ],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" }
    });

    // =================================================================
    // 4. CRUD (SALVAR / EDITAR / EXCLUIR)
    // =================================================================

    // SUBMIT DO FORMULÁRIO (SALVAR)
    $formEntidade.on('submit', function (e) {
        e.preventDefault(); // Impede o reload da página

        const formData = new FormData(this);
        // Adiciona a ação manualmente pois o ajax_router precisa saber
        const url = ROUTE_SALVAR;

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $modalEntidade.modal('hide');
                tableEntidades.ajax.reload(null, false);
                alert(response.message); // Ou use SweetAlert/Toast
            } else {
                alert('Erro: ' + response.message);
            }
        }).fail(function () {
            alert('Erro de comunicação ao salvar.');
        });
    });

    // BOTÃO EDITAR
    $('#tabela-entidades').on('click', '.btn-editar-entidade', function () {
        const id = $(this).data('id');
        const $btn = $(this);

        // Feedback visual
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: ROUTE_GET, 
            type: 'POST',
            data: {
                ent_codigo: id,
                csrf_token: csrfToken
            },
            dataType: 'json'
        })
            .done(function (response) {
                if (response.success && response.data) {
                    const d = response.data;

                    // === PREENCHE TODOS OS CAMPOS CORRETAMENTE ===
                    $('#entidade-id').val(d.id || '');
                    $('#tipo_pessoa').val(d.tipo_pessoa).trigger('change');
                    $('#situacao').val(d.situacao);
                    $('#cnpj_cpf').val(d.cnpj_cpf).trigger('input');
                    $('#inscricao_estadual_rg').val(d.inscricao_estadual_rg || '');
                    $('#razao_social').val(d.razao_social || '');
                    $('#nome_fantasia').val(d.nome_fantasia || '');
                    $('#codigo_interno').val(d.codigo_interno || '');

                    // === ENDEREÇO PRINCIPAL (agora preenche!) ===
                    $('#end_cep').val(d.end_cep || '').trigger('input');
                    $('#end_logradouro').val(d.end_logradouro || '');
                    $('#end_numero').val(d.end_numero || '');
                    $('#end_complemento').val(d.end_complemento || '');
                    $('#end_bairro').val(d.end_bairro || '');
                    $('#end_cidade').val(d.end_cidade || '');
                    $('#end_uf').val(d.end_uf || '');

                    // Título do modal
                    $('#modal-entidade-label').text('Editar: ' + (d.razao_social || 'Entidade'));

                    // Habilita aba de endereços adicionais
                    $('#endereco-adicional-tab').removeClass('disabled');

                    // Abre o modal
                    $modalEntidade.modal('show');
                } else {
                    alert(response.message || 'Dados não encontrados.');
                }
            })
            .fail(function (jqXHR) {
                console.error('Erro AJAX:', jqXHR.responseText);
                alert('Erro ao carregar dados. Verifique o console.');
            })
            .always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-pencil-alt"></i>');
            });
    });

    // BOTÃO ADICIONAR (LIMPAR)
    // O botão no HTML deve ter id="btn-adicionar-entidade" se não tiver, adicione ou use o seletor do modal show
    $modalEntidade.on('show.bs.modal', function (event) {
        // Se foi aberto pelo botão adicionar (não editar)
        if (!$(event.relatedTarget).hasClass('btn-editar-entidade')) {
            $formEntidade[0].reset();
            $('#entidade-id').val('');
            $('#modal-entidade-label').text('Adicionar Novo');
            $('#endereco-adicional-tab').addClass('disabled'); // Bloqueia endereços até salvar
            updatePessoaFields();
        }
    });

    // MÁSCARAS DE CEP
    $('#end_cep, #end_adic_cep').mask('00000-000');

    // BUSCA DE CEP ENDEREÇO PRINCIPAL (Via AJAX Router)
    $('#end_cep').on('blur', function () {
        const cep = $(this).val().replace(/\D/g, '');
        if (cep.length === 8) {
            $.post(ROUTE_END_GET, { termo: cep, tipo: 'cep' }, function (res) {
                const r = JSON.parse(res);
                if (r.success) {
                    const d = r.data;
                    $('#end_logradouro').val(d.logradouro);
                    $('#end_bairro').val(d.bairro);
                    $('#end_cidade').val(d.cidade);
                    $('#end_uf').val(d.uf);
                    $('#end_numero').focus();
                }
            });
        }
    });
});
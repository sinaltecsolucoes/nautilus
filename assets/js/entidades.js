/**
 * ARQUIVO JAVASCRIPT: entidades.js
 * Local: assets/js/entidades.js
 * Descrição: Lógica de front-end para o CRUD de Entidades (Clientes, Fornecedores, Transportadoras).
 */

$(document).ready(function () {// Este wrapper garante que o jQuery esteja carregado

    const $root = $('#entidade-data');
    if (!$root.length) return; // segurança

    const BASE_URL = $root.data('baseUrl');
    const CSRF_TOKEN = $root.data('csrfToken');
    const ROUTE_LISTAR = BASE_URL + '/entidades/listar';
    const ROUTE_SALVAR = BASE_URL + '/entidades/salvar';
    const ROUTE_GET = BASE_URL + '/entidades/get';
    const ROUTE_DELETAR = BASE_URL + '/entidades/deletar';
    const ROUTE_API_BUSCA = BASE_URL + '/entidades/api-busca';
    const ROUTE_END_LISTAR = BASE_URL + '/entidades/enderecos/listar';
    const ROUTE_END_SALVAR = BASE_URL + '/entidades/enderecos/salvar';
    const ROUTE_END_GET = BASE_URL + '/entidades/enderecos/get';
    const ROUTE_END_DELETAR = BASE_URL + '/entidades/enderecos/deletar';

    // Variável para a tabela de endereços adicionais (inicializada depois)
    let tabelaEnderecosAdicionais;
    let tabelaEntidades;

    // Função auxiliar para habilitar/desabilitar campos de Endereço Principal
    function toggleEnderecoPrincipalFields(enable) {
        // Habilita/Desabilita todos os campos da primeira aba, exceto os botões de busca
        $('#dados input[name^="end_"]').prop('disabled', !enable);
    }

    // Função auxiliar para exibir mensagens
    function showToast(message, isSuccess = true) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: isSuccess ? 'success' : 'error',
            title: message,
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    }

    // 1. Inicializa o DataTable da Entidade
    if ($('#tabela-entidades').length) {
        tabelaEntidades = $('#tabela-entidades').DataTable({
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": ROUTE_LISTAR,
                "type": "POST",
                "data": function (d) {
                    // Passa o tipo de entidade como parâmetro de filtro para o Controller
                    d.tipo_entidade = ENTIDADE_TYPE;
                }
            },
            "columns": [
                { "data": "situacao", "width": "10%" },
                { "data": "tipo", "width": "10%" },
                { "data": "razao_social" },
                {
                    "data": "cnpj_cpf",
                    "width": "20%",
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
                {
                    "data": null,
                    "defaultContent": `
                    <button class="btn btn-warning btn-sm btn-editar" title="Editar"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-danger btn-sm btn-deletar" title="Excluir"><i class="fas fa-trash"></i></button>
                `,
                    "orderable": false,
                    "width": "15%"
                }
            ],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
            },
            // Garantir que a tabela se ajuste ao redimensionamento
            responsive: true
        });
    }

    // Função auxiliar para exibir mensagens
    function showToast(message, isSuccess = true) {
        // Implementar uma biblioteca de toast/snackbar (ex: Toastr, SweetAlert)
        alert((isSuccess ? "SUCESSO: " : "ERRO: ") + message);
        // SweetAlert de exemplo:
        // Swal.fire({ icon: isSuccess ? 'success' : 'error', title: message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    }

    // ===============================================
    // 2. Lógica do Modal e Edição
    // ===============================================

    // Reseta o formulário e o modal ao abrir/fechar
    $('#modal-entidade').on('hidden.bs.modal', function () {
        $('#form-entidade')[0].reset();
        $('#entidade-id').val(''); // Limpa o ID para garantir que seja um CREATE
        $('#modal-entidade-label').text('Adicionar ' + ENTIDADE_TYPE.charAt(0).toUpperCase() + ENTIDADE_TYPE.slice(1));

        // Desativa a aba de endereços adicionais em um novo cadastro
        $('#endereco-adicional-tab').addClass('disabled');
        $('#dados-tab').tab('show');

        // Desabilita os campos de endereço principal em modo CREATE
        toggleEnderecoPrincipalFields(false);
    });

    // Abre o modal em modo 'Criar'
    $('.card-header button').on('click', function () {
        $('#entidade-id').val('');
        // Garante que o campo tipo esteja correto (definido pela rota /clientes, /fornecedores...)
        $('#ent_tipo').val(ENTIDADE_TYPE.charAt(0).toUpperCase() + ENTIDADE_TYPE.slice(1));
    });

    // Abre o modal em modo 'Editar'
    $('#tabela-entidades tbody').on('click', '.btn-editar', function () {
        const data = tabelaEntidades.row($(this).parents('tr')).data();
        const entidadeId = data.id;

        $.ajax({
            url: ROUTE_GET,
            type: 'POST',
            data: { ent_codigo: entidadeId, csrf_token: CSRF_TOKEN },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const dados = response.data;

                    // 1. Preenche os Dados Principais (Tabela ENTIDADES)
                    $('#entidade-id').val(dados.id);
                    $('#modal-entidade-label').text('Editar ' + dados.razao_social);
                    $('#tipo_pessoa').val(dados.tipo_pessoa);
                    $('#situacao').val(dados.situacao);
                    $('#razao_social').val(dados.razao_social);
                    $('#nome_fantasia').val(dados.nome_fantasia);
                    $('#codigo_interno').val(dados.codigo_interno);

                    //$('#cnpj_cpf').val(dados.cnpj_cpf);
                    $('#cnpj_cpf').data('raw', dados.cnpj_cpf);

                    $('#inscricao_estadual_rg').val(dados.inscricao_estadual_rg);

                    // 2. Preenche o Endereço Principal (Tabela ENDERECOS)
                    $('#end_cep').val(dados.end_cep);
                    $('#end_logradouro').val(dados.end_logradouro);
                    $('#end_numero').val(dados.end_numero);
                    $('#end_complemento').val(dados.end_complemento);
                    $('#end_bairro').val(dados.end_bairro);
                    $('#end_cidade').val(dados.end_cidade);
                    $('#end_uf').val(dados.end_uf);

                    // ----- APLICAR MÁSCARAS -----
                    // 1. CPF / CNPJ (depende do tipo de pessoa)
                    aplicarMascaraDocumento(dados.tipo_pessoa);

                    // 2. CEP (principal + adicional)
                    aplicarMascarasPadrao();   // já inclui a máscara de CEP

                    // Habilita os campos de endereço principal para edição
                    toggleEnderecoPrincipalFields(true);

                    // ATIVA a aba de Endereços Adicionais (Somente em modo edição)
                    // Implementar a lógica para carregar a listagem de Endereços Adicionais
                    $('#endereco-adicional-tab').removeClass('disabled').attr('data-bs-toggle', 'tab');
                    $('#end_adic_entidade_id').val(dados.id); // Seta o ID para o formulário de endereço adicional

                    // Inicializa e carrega a tabela de endereços adicionais
                    inicializarTabelaEnderecos(dados.id);

                    $('#modal-entidade').modal('show');
                } else {
                    showToast('Não foi possível carregar os dados: ' + response.message, false);
                }
            },
            error: function () {
                showToast('Erro de comunicação com o servidor.', false);
            }
        });
    });

    // ===============================================
    // 3. Integração de API (CNPJ e CEP)
    // ===============================================

    // Ação: Buscar dados por CNPJ (Chama EntidadeController::buscarApiDados)
    $('#btn-buscar-cnpj').on('click', function () {
        const cnpj = $('#cnpj_cpf').val().replace(/\D/g, ''); // Limpa caracteres
        if (cnpj.length !== 14) {
            showToast("CNPJ inválido (deve ter 14 dígitos).", false);
            return;
        }

        $.ajax({
            url: ROUTE_API_BUSCA,
            type: 'POST',
            data: { termo: cnpj, tipo: 'cnpj' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const dados = response.data;

                    // Preenche dados principais
                    $('#razao_social').val(dados.razao_social || '');
                    $('#nome_fantasia').val(dados.nome_fantasia || '');
                    $('#inscricao_estadual_rg').val(dados.inscricao_estadual || '');
                    $('#tipo_pessoa').val(dados.tipo_pessoa || 'Juridica');

                    // Preenche dados do endereço principal
                    $('#end_cep').val(dados.cep || '');
                    $('#end_logradouro').val(dados.logradouro || '');
                    $('#end_numero').val(dados.numero || '');
                    $('#end_bairro').val(dados.bairro || '');
                    $('#end_cidade').val(dados.cidade || '');
                    $('#end_uf').val(dados.uf || '');

                    // Habilitamos TODOS os campos de Endereço Principal para permitir ajuste
                    $('input[name^="end_"]').prop('disabled', false);

                    showToast("Dados do CNPJ e endereço preenchidos! Verifique e ajuste.", true);

                } else {
                    showToast('CNPJ não encontrado ou API indisponível. Preencha manualmente.', false);
                    // Em caso de falha, desabilitamos os campos de endereço, forçando o usuário a buscar o CEP separadamente
                    $('input[name^="end_"]').not('#end_numero').prop('disabled', true);
                    $('#end_numero').val('').prop('disabled', false); // Mantemos o número habilitado para preenchimento manual
                }
            }
        });
    });

    // Ação: Buscar Endereço por CEP (Aba Endereços Adicionais)
    $('#btn-buscar-cep-adic').on('click', function () {
        const cep = $('#end_adic_cep').val().replace(/\D/g, '');
        buscarCep(cep, '#endereco-adicional-panel');
    });

    // Função auxiliar para buscar CEP (reutilizável)
    function buscarCep(cep, scopeSelector) {
        if (cep.length !== 8) {
            showToast("CEP inválido (deve ter 8 dígitos).", false);
            return;
        }

        $.ajax({
            url: ROUTE_API_BUSCA,
            type: 'POST',
            data: { termo: cep, tipo: 'cep' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const dados = response.data;
                    // Preenche os campos dentro do escopo (Aba Principal ou Aba Adicional)
                    $(scopeSelector).find('input[name*="logradouro"]').val(dados.logradouro || '');
                    $(scopeSelector).find('input[name*="bairro"]').val(dados.bairro || '');
                    $(scopeSelector).find('input[name*="cidade"]').val(dados.cidade || '');
                    $(scopeSelector).find('input[name*="uf"]').val(dados.uf || '');
                    $(scopeSelector).find('input[name*="numero"]').focus();
                    showToast("Endereço preenchido com sucesso!");
                } else {
                    showToast('CEP não encontrado (ViaCEP).', false);
                }
            }
        });
    }


    // ===============================================
    // 4. Submissão do Formulário (Salvar/Atualizar)
    // ===============================================

    $('#form-entidade').on('submit', function (e) {
        e.preventDefault();

        // Validação básica (verificando se o número do endereço principal foi preenchido, se o endereço está habilitado)
        if ($('#end_logradouro').val() && !$('#end_numero').val()) {
            showToast("Por favor, insira o número do endereço principal.", false);
            return;
        }

        const formData = $(this).serialize();

        $.ajax({
            url: ROUTE_SALVAR,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, true);
                    tabelaEntidades.ajax.reload(null, false);

                    if (!$('#entidade-id').val()) {
                        // Se for um CREATE: Atualiza para modo EDIÇÃO e libera a segunda aba
                        $('#entidade-id').val(response.ent_codigo);
                        $('#end_adic_entidade_id').val(response.ent_codigo);
                        $('#modal-entidade-label').text('Editar Entidade #' + response.ent_codigo);
                        $('#endereco-adicional-tab').removeClass('disabled').attr('data-bs-toggle', 'tab');

                        // Inicializa a tabela de endereços para a nova entidade
                        inicializarTabelaEnderecos(response.ent_codigo);

                        // Move para a aba de endereços para incentivar o cadastro de outros tipos
                        $('#endereco-adicional-tab').tab('show');
                    }
                } else {
                    showToast('Falha ao salvar: ' + response.message, false);
                }
            }
        });
    });

    // ===============================================
    // 5. Lógica de Endereços Adicionais (CRUD Secundário)
    // ===============================================

    function inicializarTabelaEnderecos(entidadeId) {
        if ($.fn.DataTable.isDataTable('#tabela-enderecos-adicionais')) {
            tabelaEnderecosAdicionais.destroy(); // Destrói a instância anterior se existir
        }

        tabelaEnderecosAdicionais = $('#tabela-enderecos-adicionais').DataTable({
            "processing": true,
            "serverSide": false, // Usaremos carregamento simples, sem Server-Side por ser um CRUD secundário
            "ajax": {
                "url": ROUTE_END_LISTAR,
                "type": "POST",
                "data": { entidade_id: entidadeId },
                "dataSrc": "data"
            },
            "columns": [
                { "data": "tipo_endereco", "width": "15%" },
                {
                    "data": null, "render": function (data) {
                        return data.logradouro + ', ' + data.numero + (data.complemento ? ' (' + data.complemento + ')' : '');
                    }
                },
                {
                    "data": null, "render": function (data) {
                        return data.bairro + ', ' + data.cidade + '/' + data.uf;
                    }
                },
                {
                    "data": null,
                    "defaultContent": `
                        <button class="btn btn-warning btn-sm btn-editar-endereco" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm btn-deletar-endereco" title="Excluir"><i class="fas fa-trash"></i></button>
                    `,
                    "orderable": false,
                    "width": "15%"
                }
            ],
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
            }
        });
    }

    // Submissão do Formulário de Endereço Adicional
    $('#form-endereco-adicional').on('submit', function (e) {
        e.preventDefault();

        const entidadeId = $('#end_adic_entidade_id').val();
        if (!entidadeId) {
            showToast("Erro: ID da entidade não encontrado. Salve a entidade principal primeiro.", false);
            return;
        }

        //const formData = $(this).serialize() + '&entidade_id=' + entidadeId;
        const formData = $(this).serialize();

        $.ajax({
            url: ROUTE_END_SALVAR,
            type: 'POST',
            data: formData + '&entidade_id=' + entidadeId,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, true);
                    $('#form-endereco-adicional')[0].reset(); // Limpa o formulário de endereço
                    tabelaEnderecosAdicionais.ajax.reload(null, false); // Recarrega a listagem
                } else {
                    showToast('Falha ao salvar endereço: ' + response.message, false);
                }
            }
        });
    });

    // Abre o formulário de endereço adicional em modo 'Editar'
    $('#tabela-enderecos-adicionais tbody').on('click', '.btn-editar-endereco', function () {
        const data = tabelaEnderecosAdicionais.row($(this).parents('tr')).data();

        $.ajax({
            url: ROUTE_END_GET,
            type: 'POST',
            data: { end_id: data.id },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const dados = response.data;

                    // Preenche o formulário de Endereço Adicional (usando prefixo end_adic_)
                    $('#form-endereco-adicional').prepend(`<input type="hidden" name="end_id" id="end_adic_id" value="${dados.id}">`); // Adiciona o ID para UPDATE
                    $('#end_adic_tipo').val(dados.tipo_endereco);
                    $('#end_adic_cep').val(dados.cep).trigger('input');
                    $('#end_adic_logradouro').val(dados.logradouro);
                    $('#end_adic_numero').val(dados.numero);
                    $('#end_adic_complemento').val(dados.complemento);
                    $('#end_adic_bairro').val(dados.bairro);
                    $('#end_adic_cidade').val(dados.cidade);
                    $('#end_adic_uf').val(dados.uf);

                    showToast('Endereço carregado para edição!', true);
                } else {
                    showToast(response.message, false);
                }
            }
        });
    });

    // Limpar o ID oculto do endereço após o salvamento para voltar ao modo CREATE
    $('#form-endereco-adicional').on('ajaxSuccess', function (event, request, settings) {
        if (settings.url === ROUTE_END_SALVAR) {
            $('#end_adic_id').remove(); // Remove o ID oculto para voltar ao CREATE
            $('#form-endereco-adicional')[0].reset();
        }
    });

    // Lógica para Deletar Endereço Adicional (Adicionar no $(document).ready)
    $('#tabela-enderecos-adicionais tbody').on('click', '.btn-deletar-endereco', function () {
        const data = tabelaEnderecosAdicionais.row($(this).parents('tr')).data();

        if (confirm(`Tem certeza que deseja EXCLUIR o endereço ${data.logradouro} (ID: ${data.id})?`)) {
            $.ajax({
                url: ROUTE_END_DELETAR,
                type: 'POST',
                data: { end_id: data.id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showToast(response.message, true);
                        tabelaEnderecosAdicionais.ajax.reload(null, false);
                    } else {
                        showToast('Falha ao excluir: ' + response.message, false);
                    }
                }
            });
        }
    });

    // ===============================================
    // 6. Deletar Entidade
    // ===============================================

    $('#tabela-entidades tbody').on('click', '.btn-deletar', function () {
        const data = tabelaEntidades.row($(this).parents('tr')).data();
        const entidadeId = data.id;

        Swal.fire({
            title: 'Tem certeza?',
            text: `Você vai deletar a entidade ${data.razao_social}. Esta ação é IRREVERSÍVEL!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, deletar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Se confirmado, executa o AJAX de exclusão
                $.ajax({
                    url: ROUTE_DELETAR,
                    type: 'POST',
                    data: { ent_codigo: entidadeId, csrf_token: $('input[name="csrf_token"]').val() },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            showToast(response.message, true);
                            tabelaEntidades.ajax.reload(null, false);
                        } else {
                            showToast('Falha ao excluir: ' + response.message, false);
                        }
                    },
                    error: function () {
                        showToast('Erro de comunicação ao deletar.', false);
                    }
                });
            }
        });
    });

    // ===============================================
    // 7. Funções de Máscara (CEP, CPF, CNPJ)
    // ===============================================

    // Aplica as máscaras padrão na inicialização
    aplicarMascarasPadrao();

    // Evento de troca de Tipo de Pessoa
    $('#tipo_pessoa').on('change', function () {
        const tipoPessoa = $(this).val();
        aplicarMascaraDocumento(tipoPessoa);
    });

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
var dataTable;
var empreendimentoSelecionado = null;

$(document).ready(function() {
    // Inicialização
    $('#selEmpreend').change(function() {
        var id = $(this).val();
        if (id) {
            carregarFluxo(id);
        } else {
            $('#fluxoContainer').hide();
        }
    });
    
    // Inicializa DataTable
    dataTable = $('#tblEtapas').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        },
        "columns": [
            { "data": "Posicao" },
            { "data": "Etapa" },
            { "data": "Percentual" },
            { "data": "Obs" },
            { 
                "data": null,
                "render": function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-warning me-1" onclick="editarEtapa(${row.Etapa_id})">Editar</button>
                        <button class="btn btn-sm btn-danger" onclick="excluirEtapa(${row.Etapa_id})">Excluir</button>
                    `;
                },
                "orderable": false
            }
        ]
    });
});

function carregarFluxo(empreendId) {
    console.log("Iniciando carregamento para empreendId:", empreendId); // Log inicial
    
    $.ajax({
        url: 'fluxo_carregar.asp',
        type: 'POST',
        data: { empreend_id: empreendId },
        dataType: 'json',
        success: function(response, status, xhr) {
            // Exibe o JSON completo no console
            console.log("Resposta completa:", response);
            
            // Exibe também a resposta bruta (útil para debug)
            console.log("Resposta bruta:", xhr.responseText);
            
            // Exibe detalhes da primeira etapa (se existir)
            if(response.etapas && response.etapas.length > 0) {
                console.log("Primeira etapa:", response.etapas[0]);
            }

            if (response.success) {
                console.log("Carregamento bem-sucedido");
                empreendimentoSelecionado = empreendId;
                $('#nomeEmpreendimento').text(response.nomeEmpreendimento);
                
                // Exibe as etapas no console antes de adicionar à tabela
                console.log("Etapas recebidas:", response.etapas);
                
                dataTable.clear().rows.add(response.etapas).draw();
                                
                // Calcular total
                var total = 0;
                response.etapas.forEach(function(etapa) {
                    total += parseInt(etapa.Percentual);
                });
                
                // Atualizar barra de progresso
                $('#progressBar').css('width', total + '%').text(total + '%');
                $('#totalPercentual').text('Total: ' + total + '%');
                
                $('#fluxoContainer').show();
            } else {
                console.error("Erro na resposta:", response.message);
                alert(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error("Erro na requisição AJAX:", {
                empreendId: empreendId,
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            alert('Erro ao carregar fluxo de pagamento. Verifique o console para detalhes.');
        }
    });
}

function adicionarEtapa() {
    if (!empreendimentoSelecionado) return;
    
    $('#modalTitle').text('Adicionar Etapa');
    $('#formEtapa')[0].reset();
    $('#etapaId').val('');
    $('#empreendId').val(empreendimentoSelecionado);
    
    var modal = new bootstrap.Modal(document.getElementById('etapaModal'));
    modal.show();
}

function editarEtapa(etapaId) {
    $.ajax({
        url: 'fluxo_carregar.asp',
        type: 'POST',
        data: { etapa_id: etapaId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#modalTitle').text('Editar Etapa');
                $('#etapaId').val(response.etapa.Etapa_id);
                $('#empreendId').val(empreendimentoSelecionado);
                $('#etapaPosicao').val(response.etapa.Posicao);  // Preenche o campo Posicao
                $('#etapaNome').val(response.etapa.Etapa);
                $('#etapaPercentual').val(response.etapa.Percentual);
                $('#etapaObs').val(response.etapa.Obs);
                
                var modal = new bootstrap.Modal(document.getElementById('etapaModal'));
                modal.show();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('Erro ao carregar etapa.');
        }
    });
}

function salvarEtapa() {
    var formData = {
        etapa_id: $('#etapaId').val(),
        empreend_id: $('#empreendId').val(),
        posicao: $('#etapaPosicao').val(),  // Novo campo
        etapa: $('#etapaNome').val(),
        percentual: $('#etapaPercentual').val(),
        obs: $('#etapaObs').val()
    };
    
    if (!formData.posicao || !formData.etapa || !formData.percentual) {
        alert('Preencha todos os campos obrigatórios (Posição, Etapa e Percentual).');
        return;
    }
    
    $.ajax({
        url: 'fluxo_salvar_etapa.asp',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                carregarFluxo(empreendimentoSelecionado);
                var modal = bootstrap.Modal.getInstance(document.getElementById('etapaModal'));
                modal.hide();
            } else {
                alert(response.message);
            }
        },
        error: function() {
            alert('Erro ao salvar etapa.');
        }
    });
}
function excluirEtapa(etapaId) {
    if (confirm('Tem certeza que deseja excluir esta etapa?')) {
        $.ajax({
            url: 'fluxo_excluir_etapa.asp',
            type: 'POST',
            data: { etapa_id: etapaId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    carregarFluxo(empreendimentoSelecionado);
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Erro ao excluir etapa.');
            }
        });
    }
}
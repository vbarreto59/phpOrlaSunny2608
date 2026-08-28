function formatarMoeda(input) {
    // Remove tudo que não é dígito
    let value = input.value.replace(/\D/g, '');
    
    // Adiciona os zeros necessários para os centavos
    value = (value/100).toFixed(2) + '';
    
    // Separa parte inteira dos centavos
    let parts = value.split('.');
    let integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    
    // Atualiza o valor no campo
    input.value = 'R$ ' + integerPart + ',' + parts[1];
}

function calcularSimulacao() {
    const empreendId = $('#selEmpreend').val();
    const valorUnidade = $('#valorUnidade').val();
    
    if (!empreendId || !valorUnidade) {
        alert('Preencha todos os campos obrigatórios.');
        return;
    }

    // Converter valor para formato numérico
    const valorNumerico = parseFloat(valorUnidade.replace('R$ ', '').replace(/\./g, '').replace(',', '.'));
    
    console.log("Iniciando simulação para:", empreendId, "Valor:", valorNumerico); // Debug

    $.ajax({
        url: 'fluxo_carregar.asp',
        type: 'POST',
        data: { empreend_id: empreendId },
        dataType: 'json',
        success: function(response) {
            console.log("Resposta recebida:", response); // Debug
            if (response.success) {
                exibirResultado(response, valorNumerico);
                gerarResumoCliente(response, valorNumerico);
                console.log("Conteúdo do resumo:", $('#resumoCliente').val()); // Debug
            } else {
                console.error("Erro na resposta:", response.message); // Debug
                alert(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error("Erro AJAX:", status, error); // Debug
            alert('Erro ao carregar etapas do empreendimento.');
        }
    });
}



function exibirResultado(dados, valorUnidade) {
    const tabela = $('#tabelaSimulacao');
    tabela.empty();
    
    let totalPercentual = 0;
    let totalValor = 0;
    
    dados.etapas.forEach(function(etapa) {
        const valorEtapa = (valorUnidade * etapa.Percentual / 100);
        totalPercentual += parseFloat(etapa.Percentual);
        totalValor += valorEtapa;
        
        tabela.append(`
            <tr>
                <td>${etapa.Etapa}</td>
                <td>${etapa.Percentual}%</td>
                <td>${formatarMoedaParaExibicao(valorEtapa)}</td>
                <td>${etapa.Obs || '-'}</td>
            </tr>
        `);
    });
    
    $('#tituloSimulacao').html(`
        Empreendimento: <strong>${dados.nomeEmpreendimento}</strong> | 
        Valor da Unidade: <strong>${formatarMoedaParaExibicao(valorUnidade)}</strong>
    `);
    
    $('#totalPercentual').text(totalPercentual + '%');
    $('#totalValor').text(formatarMoedaParaExibicao(totalValor));
    $('#resultadoSimulacao').show();
}

function formatarMoedaParaExibicao(valor) {
    return valor.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
}

// ------------ texto para copiar -------------------------

function gerarResumoCliente(dados, valorUnidade) {
    // Verificação robusta dos dados de entrada
    if (!dados || !dados.nomeEmpreendimento || !Array.isArray(dados.etapas)) {
        console.error("Dados inválidos recebidos:", dados);
        return;
    }

    // Criação do conteúdo do resumo
    let resumoContent = `SIMULAÇÃO DE PAGAMENTO\n`;
    resumoContent += `==============================\n`;
    resumoContent += `Empreendimento: ${dados.nomeEmpreendimento}\n`;
    resumoContent += `Valor da Unidade: ${formatarMoedaParaExibicao(valorUnidade)}\n\n`;
    resumoContent += `DETALHAMENTO DAS ETAPAS:\n\n`;

    let totalPercentual = 0;
    let totalValor = 0;

    // Processamento das etapas
    dados.etapas.forEach((etapa, index) => {
        const valorEtapa = (valorUnidade * etapa.Percentual / 100);
        totalPercentual += parseFloat(etapa.Percentual);
        totalValor += valorEtapa;

        resumoContent += `${index + 1}. ${etapa.Etapa}\n`;
        resumoContent += `   Percentual: ${etapa.Percentual}%\n`;
        resumoContent += `   Valor: ${formatarMoedaParaExibicao(valorEtapa)}\n`;
        
        if (etapa.Obs && etapa.Obs.trim() !== '') {
            resumoContent += `   Observações: ${etapa.Obs.trim()}\n`;
        }
        resumoContent += `\n`;
    });

    // Adiciona totais
    resumoContent += `TOTAIS:\n`;
    resumoContent += `   Percentual Total: ${totalPercentual}%\n`;
    resumoContent += `   Valor Total: ${formatarMoedaParaExibicao(totalValor)}\n\n`;
    resumoContent += `Data da Simulação: ${new Date().toLocaleDateString('pt-BR')}\n`;

    // Atribuição ao textarea - FORMA CORRIGIDA
    const resumoTextarea = document.getElementById('resumoCliente');
    if (resumoTextarea) {
        resumoTextarea.value = resumoContent;
        console.log("Resumo atribuído ao textarea:", resumoTextarea.value); // Debug
    } else {
        console.error("Elemento textarea não encontrado!");
    }
}

function copiarResumo() {
    const resumoTextarea = document.getElementById('resumoCliente');
    if (!resumoTextarea || !resumoTextarea.value) {
        alert('Nenhum conteúdo para copiar!');
        return;
    }
    
    resumoTextarea.select();
    document.execCommand('copy');
    
    // Feedback visual
    const btn = document.querySelector('button[onclick="copiarResumo()"]');
    if (btn) {
        btn.textContent = 'Copiado!';
        setTimeout(() => {
            btn.textContent = 'Copiar para Área de Transferência';
        }, 2000);
    }
}


<?php
// calcInterativaX6a-pre-pos.php
// Calculadora de Simulação de Pagamento - Versão X6 Pré-Pós
// Alteração: % removido do input e colocado como label small ao lado - 2026

session_start();
require_once 'conn.php';
require_once 'manutencao_config.php';

if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    $log = date('Y-m-d H:i:s') . " - Acesso Calculadora X6 - " .
           strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO') . " - " .
           $_SERVER['REMOTE_ADDR'] . "\n";
    @file_put_contents('logs/acesso_calc_x6.log', $log, FILE_APPEND);
}

function gerarLinhaPagamento($nome, $id, $qtdInicial = '1') {
    return '
    <div class="payment-row mb-3">
        <div class="row align-items-center">
            <div class="col-12 col-md-2 fw-bold mb-2 mb-md-0">' . htmlspecialchars($nome) . '</div>
            <div class="col-6 col-md-2 mb-2 mb-md-0">
                <label class="d-md-none">Quantidade</label>
                <input type="number" class="form-control qtd" data-etapa="' . htmlspecialchars($id) . '" min="0" value="' . htmlspecialchars($qtdInicial) . '">
            </div>
            <div class="col-6 col-md-2 mb-2 mb-md-0">
                <label class="d-md-none">%</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control pct input-percentual text-end" data-etapa="' . htmlspecialchars($id) . '" placeholder="0,00" style="background-color: #ffe0e0;">
                    <span class="input-group-text bg-light small fw-bold">%</span>
                </div>
            </div>
            <div class="col-6 col-md-2 mb-2 mb-md-0">
                <label class="d-md-none">Valor</label>
                <input type="text" class="form-control valor input-monetario" data-etapa="' . htmlspecialchars($id) . '" placeholder="0,00">
            </div>
            <div class="col-6 col-md-2 mb-2 mb-md-0">
                <label class="d-md-none">Total %</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control percentual text-end" data-etapa="' . htmlspecialchars($id) . '" readonly>
                    <span class="input-group-text bg-light small fw-bold">%</span>
                </div>
            </div>
        </div>
    </div>';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SunnyImob Calculadora de Simulação vX6</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-section { background-color: #bbdefb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .payment-header { padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .payment-row { margin-bottom: 10px; padding: 10px; border-bottom: 1px solid #eee; }
        .results-container { background-color: #f8f9fa; padding: 15px; border-radius: 5px; height: 100%; }
        .filled { background-color: rgba(255, 182, 193, 0.2); transition: background-color 0.3s ease; }
        .input-monetario, .input-percentual { text-align: right; }
        .input-group-text.small { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
        .footer { margin-top: 20px; padding: 10px; text-align: center; font-size: 0.9rem; color: #6c757d; }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <!-- Cabeçalho -->
    <header class="container-fluid py-3 mb-4 border-bottom" style="background-color: <?= htmlspecialchars($_SESSION['conf_CorPrimaria'] ?? '#0d6efd') ?>; color: white;">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0 d-flex align-items-center">
                <img src="<?= htmlspecialchars($_SESSION['Conf_ImgLogo'] ?? 'logo.png') ?>" alt="Logo" style="height:50px; margin-right:15px;">
                <div>
                    <h1 class="mb-0 fs-4"><i class="fas fa-building me-2"></i>Calculadora Interativa X6-Pré-Pós</h1>
                    <p class="mb-0 fs-6">SunnyImob - <?= htmlspecialchars($_SESSION['Conf_Versao'] ?? 'vX6') ?></p>
                </div>
            </div>
            <div>
                <a href="#" class="btn btn-secondary" onclick="window.close()" style="background:#A0522D; border-color:#A0522D; color:white;">
                    <i class="fas fa-times me-1"></i> Fechar
                </a>
            </div>
        </div>
    </header>

    <!-- Informações Gerais -->
    <div class="header-section" style="background-color: <?= htmlspecialchars($_SESSION['conf_CorPrimaria'] ?? '#0d6efd') ?>; color: white;">
        <h4>Informações Gerais</h4>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 col-6 mb-3"><label class="form-label">Data</label><input type="date" class="form-control" id="data"></div>
        <div class="col-md-4 col-6 mb-3"><label class="form-label">Empreendimento</label><input type="text" class="form-control text-uppercase" id="empreendimento"></div>
        <div class="col-md-4 col-6 mb-3"><label class="form-label">Cliente</label><input type="text" class="form-control text-uppercase" id="cliente"></div>
        <div class="col-md-4 col-6 mb-3"><label class="form-label">Corretor</label><input type="text" class="form-control text-uppercase" id="corretor"></div>
        <div class="col-md-4 col-6 mb-3"><label class="form-label">Unidade</label><input type="text" class="form-control text-uppercase" id="unidade"></div>
        <div class="col-md-4 col-6 mb-3"><label class="form-label">Valor da Unidade</label><input type="text" class="form-control input-monetario" id="valorUnidade" placeholder="0,00"></div>
    </div>

    <div class="row">
        <!-- Etapas -->
        <div class="col-md-8" id="calcEtapas">
            <div class="payment-header" style="background-color: <?= htmlspecialchars($_SESSION['conf_CorPrimaria'] ?? '#0d6efd') ?>; color: white;">
                <h4>Etapas de Pagamentos</h4>
            </div>

            <div class="card mb-3">
                <div class="card-body bg-light">
                    <h5>Detalhes das Entradas</h5>
                    <?= gerarLinhaPagamento('Pix Reserva',   'entrada1') ?>
                    <?= gerarLinhaPagamento('Ato/Contrato',  'entrada2') ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Opções de Parcelamento</h5></div>
                <div class="card-body" style="background:#DEF7DB;">
                    <?= gerarLinhaPagamento('30 dias',  'entrada3') ?>
                    <?= gerarLinhaPagamento('60 dias',  'entrada4') ?>
                    <?= gerarLinhaPagamento('90 Dias',  'entrada5') ?>
                    <?= gerarLinhaPagamento('120 Dias', 'entrada6') ?>
                </div>
            </div>

            <div class="card mb-3" style="background:#e0f7fa;">
                <div class="card-body">
                    <h5>Parcelas Pré-Chaves</h5>
                    <?= gerarLinhaPagamento('Mensais Pré-Chaves',    'pre_mensais') ?>
                    <?= gerarLinhaPagamento('Semestrais Pré-Chaves', 'pre_semestrais') ?>
                </div>
            </div>

            <div class="card mb-3" style="background:#e8f5e9;">
                <div class="card-body">
                    <h5>Chaves</h5>
                    <?= gerarLinhaPagamento('Chaves', 'chaves') ?>
                </div>
            </div>

            <div class="card" style="background:#fff3e0;">
                <div class="card-body">
                    <h5>Parcelas Pós-Chaves</h5>
                    <?= gerarLinhaPagamento('Mensais Pós-Chaves',    'pos_mensais') ?>
                    <?= gerarLinhaPagamento('Semestrais Pós-Chaves', 'pos_semestrais') ?>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div class="col-md-4 mt-md-0 mt-4">
            <div class="results-container border border-danger" style="border-width:3px !important;">
                <div class="payment-header bg-danger text-white">
                    <h4>Resultados</h4>
                </div>

                <div class="p-3">
                    <div class="mb-3">
                        <label>Valor da Unidade:</label>
                        <input type="text" class="form-control bg-info-subtle" id="valorUnidadeResult" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Valor Total Pago:</label>
                        <input type="text" class="form-control bg-info-subtle" id="totalPago" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Percentual Total Pago:</label>
                        <input type="text" class="form-control bg-info-subtle" id="percentualTotal" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Saldo Devedor:</label>
                        <input type="text" class="form-control bg-danger-subtle" id="saldoDevedor" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Saldo Percentual:</label>
                        <input type="text" class="form-control bg-danger-subtle" id="saldoPercentual" readonly>
                    </div>
                </div>

                <div class="p-3 border-top">
                    <h6>Detalhamento por Etapa:</h6>
                    <textarea id="detalhesEtapas" class="form-control" rows="20" readonly style="font-size:0.95rem; background:#f8f9fa;"></textarea>
                    <button id="copiarDetalhes" class="btn btn-outline-primary mt-3 w-100">
                        <i class="fas fa-copy me-1"></i> Copiar Detalhamento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center text-muted mt-4 small" id="currentDate"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Funções de formatação atualizadas (sem % no valor do input)
function formatarMoeda(input) {
    let v = input.value.replace(/\D/g, '');
    v = (Number(v)/100).toFixed(2);
    v = v.replace('.', ',');
    v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.value = v;
}

function parseMoeda(str) {
    return parseFloat(str.replace(/\./g,'').replace(',','.')) || 0;
}

function toMoeda(n) {
    return n.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function formatarPercentual(input) {
    // Remove caracteres inválidos, mantém só números e uma vírgula
    let valor = input.value.replace(/[^\d,]/g, '');

    // Permite apenas uma vírgula
    valor = valor.replace(/,/g, m => (valor.indexOf(',') === valor.lastIndexOf(',')) ? ',' : '');

    // Remove zeros à esquerda (exceto se for 0,...)
    if (valor.startsWith('0') && valor.length > 1 && valor[1] !== ',') {
        valor = valor.replace(/^0+/, '') || '0';
    }

    if (valor.startsWith(',')) valor = '0' + valor;

    // Durante digitação: não força casas decimais
    input.value = valor;
}

function parsePercentual(str) {
    // Agora o valor já vem sem %, então só limpa e converte
    return parseFloat(str.replace(/[^\d,.]/g, '').replace(',', '.')) || 0;
}

function toPercentual(n) {
    return n.toFixed(2).replace('.', ',');
}

function calcularValorOuPercentual(input) {
    const row = input.closest('.payment-row');
    if (!row) return;

    const valorUnidade = parseMoeda(document.getElementById('valorUnidade').value);
    if (valorUnidade <= 0) return;

    const qtd       = Number(row.querySelector('.qtd').value) || 0;
    const valorEl   = row.querySelector('.valor');
    const pctEl     = row.querySelector('.pct');
    const percentEl = row.querySelector('.percentual');

    if (input === valorEl) {
        const v = parseMoeda(valorEl.value);
        pctEl.value = toPercentual((v / valorUnidade) * 100);
    } else if (input === pctEl) {
        const p = parsePercentual(pctEl.value);
        valorEl.value = toMoeda((valorUnidade * p) / 100);
    }

    const valorFinal = parseMoeda(valorEl.value);
    percentEl.value = toPercentual((valorFinal * qtd / valorUnidade) * 100);
}

function atualizarLinhaPreenchida(row) {
    if (!row) return;
    const qtd   = Number(row.querySelector('.qtd').value) || 0;
    const valor = parseMoeda(row.querySelector('.valor').value);
    const pct   = parsePercentual(row.querySelector('.pct').value);
    row.classList.toggle('filled', qtd > 0 || valor > 0 || pct > 0);
}

function calcularTotais() {
    const valorUnidade = parseMoeda(document.getElementById('valorUnidade').value);
    if (valorUnidade <= 0) return;

    let totalPago = 0;
    let linhas = [];
    let cabecalhoAdicionado = false;

    const info = {
        data: document.getElementById('data').value,
        imovel: document.getElementById('empreendimento').value.trim().toUpperCase(),
        unidade: document.getElementById('unidade').value.trim().toUpperCase(),
        cliente: document.getElementById('cliente').value.trim().toUpperCase(),
        corretor: document.getElementById('corretor').value.trim().toUpperCase(),
        valorUnidadeStr: document.getElementById('valorUnidade').value
    };

    let dataFmt = new Date().toLocaleDateString('pt-BR');
    if (info.data) {
        const [y,m,d] = info.data.split('-');
        dataFmt = `${d}/${m}/${y}`;
    }

    document.querySelectorAll('.payment-row').forEach(row => {
        const qtd = Number(row.querySelector('.qtd').value) || 0;
        const valor = parseMoeda(row.querySelector('.valor').value);
        if (qtd <= 0 || valor <= 0) return;

        const totalEtapa = qtd * valor;
        const pctEtapa = (totalEtapa / valorUnidade) * 100;
        totalPago += totalEtapa;

        const nomeEtapa = row.querySelector('.fw-bold')?.textContent.trim() || 'Etapa';
        const parcela = qtd > 1 ? `${qtd}x de R$ ${toMoeda(valor)}` : `R$ ${toMoeda(valor)}`;
        const totalTxt = qtd > 1 ? ` (Total R$ ${toMoeda(totalEtapa)})` : '';

        if (!cabecalhoAdicionado) {
            linhas.push(`SIMULAÇÃO DE PAGAMENTO:\n-Imóvel: ${info.imovel}\n-Unidade: ${info.unidade}\n-Valor Unidade: R$ ${info.valorUnidadeStr}\n-Cliente: ${info.cliente}\n-Corretor: ${info.corretor}\n-Data: ${dataFmt}\n\n`);
            cabecalhoAdicionado = true;
        }

        linhas.push(`${nomeEtapa}: ${parcela}${totalTxt} (${pctEtapa.toFixed(2)}%)\n`);
    });

    const pctTotal = (totalPago / valorUnidade) * 100;
    const saldo = valorUnidade - totalPago;
    const saldoPct = 100 - pctTotal;

    document.getElementById('valorUnidadeResult').value = 'R$ ' + toMoeda(valorUnidade);
    document.getElementById('totalPago').value          = 'R$ ' + toMoeda(totalPago);
    document.getElementById('percentualTotal').value    = toPercentual(pctTotal) + '%';
    document.getElementById('saldoDevedor').value       = 'R$ ' + toMoeda(saldo);
    document.getElementById('saldoPercentual').value    = toPercentual(saldoPct) + '%';

    let textoFinal = linhas.join('') + `\n----------------------------------------\nTotal Acumulado: R$ ${toMoeda(totalPago)} (${pctTotal.toFixed(2)}%)`;
    document.getElementById('detalhesEtapas').value = textoFinal;
}

// Inicialização e eventos
document.addEventListener('DOMContentLoaded', () => {
    const hoje = new Date();
    document.getElementById('data').value = hoje.toISOString().split('T')[0];
    document.getElementById('currentDate').textContent = hoje.toLocaleDateString('pt-BR');

    document.querySelectorAll('.input-monetario').forEach(el => {
        el.addEventListener('input', () => {
            formatarMoeda(el);
            calcularValorOuPercentual(el);
            calcularTotais();
            atualizarLinhaPreenchida(el.closest('.payment-row'));
        });
        el.addEventListener('blur', () => {
            if (!el.value.trim()) el.value = '0,00';
            else formatarMoeda(el);
        });
    });

    document.querySelectorAll('.input-percentual').forEach(el => {
        el.addEventListener('input', () => {
            formatarPercentual(el);
            calcularValorOuPercentual(el);
            calcularTotais();
            atualizarLinhaPreenchida(el.closest('.payment-row'));
        });

        el.addEventListener('blur', () => {
            let valor = el.value.trim();

            if (!valor || valor === ',' || valor === '0,') {
                el.value = '0,00';
                return;
            }

            let [inteiro = '0', decimal = ''] = valor.split(',');
            inteiro = inteiro.replace(/^0+/, '') || '0';
            decimal = (decimal + '00').slice(0, 2);

            el.value = inteiro + ',' + decimal;
        });
    });

    document.querySelectorAll('.qtd').forEach(el => {
        el.addEventListener('input', () => {
            calcularTotais();
            atualizarLinhaPreenchida(el.closest('.payment-row'));
        });
    });

    document.getElementById('valorUnidade').addEventListener('input', function() {
        formatarMoeda(this);
        document.querySelectorAll('.payment-row').forEach(row => {
            const pct = row.querySelector('.pct');
            if (pct && parsePercentual(pct.value) > 0) {
                calcularValorOuPercentual(pct);
            }
        });
        calcularTotais();
    });

    document.getElementById('copiarDetalhes').onclick = () => {
        const ta = document.getElementById('detalhesEtapas');
        ta.select();
        navigator.clipboard.writeText(ta.value)
            .then(() => alert('Detalhamento copiado!'))
            .catch(() => {
                document.execCommand('copy');
                alert('Copiado (método alternativo)');
            });
    };

    calcularTotais();
});
</script>
</body>
</html>
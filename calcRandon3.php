<?php
// calculadora_inteligente.php
// Conversão de ASP para PHP + PDO - 2026
// Funcional: 06/09/2025 23:59 (original) → Atualizado para PHP moderno

session_start();

// Verifica se o usuário está logado (se existir login no seu sistema)
if (!isset($_SESSION['Usuario'])) {
    header("Location: login.php"); // ajuste para sua página de login
    exit();
}

// Inclui conexão PDO (ajuste conforme seu arquivo conn.php)
require_once 'conn.php'; // deve definir $pdo como PDO object

// Log de acesso (substitui log_nanvegacao.asp)
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if ($remoteAddr !== '127.0.0.1' && $remoteAddr !== '::1') {
    $log = date('Y-m-d H:i:s') . " - Acesso Calculadora Inteligente - " .
           strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO') . " - " .
           $remoteAddr . "\n";
    @file_put_contents('logs/acesso_calc_inteligente.log', $log, FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SunnyImob - Calculadora Inteligente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .memo-container {
            border: 1px solid #ccc;
            padding: 10px;
            height: 600px;
            overflow-y: auto;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        #memoSimulacoes {
            background-color: #e6ffe6;
            font-family: monospace;
            white-space: pre-wrap;
        }
        input.form-control {
            background-color: #ffe6e6;
        }
        .simulacoes-input {
            width: 80px;
            display: inline-block;
            margin-left: 10px;
            margin-right: 10px;
        }
        .card-parametros {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .card-header-parametros {
            background-color: #f8f9fa;
            padding: 12px 18px;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
        }
        .card-body-parametros {
            padding: 18px;
        }
        .input-group-text {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Cabeçalho -->
        <header class="container-fluid py-3 mb-4 border-bottom" 
                style="background-color: <?= htmlspecialchars($_SESSION['conf_CorPrimaria'] ?? '#0d6efd') ?>; color: white; border-radius: 8px;">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-3 mb-md-0 d-flex align-items-center">
                    <img src="<?= htmlspecialchars($_SESSION['Conf_ImgLogo'] ?? 'logo.png') ?>" alt="Logotipo" style="height: 50px; margin-right: 15px;">
                    <div>
                        <h3 class="mb-0">
                            <i class="fas fa-building me-2"></i> 
                            Calculadora Inteligente - <?= htmlspecialchars($_SESSION['Conf_Versao'] ?? 'vX') ?>
                        </h3>
                        <p class="mb-0 fs-6">SunnyImob</p>
                    </div>
                </div>
                <div>
                    <a href="#" class="btn btn-secondary" onclick="window.close()" style="background-color: #A0522D; border-color: #A0522D; color: white;">
                        <i class="fas fa-times me-1"></i> Fechar
                    </a>
                </div>
            </div>
        </header>

        <div class="row g-4">
            <!-- Coluna 1 - Entradas Fixas -->
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label for="valorImovel" class="form-label">Valor do Imóvel (R$):</label>
                    <input type="text" class="form-control" id="valorImovel" placeholder="Ex: 150.000,00">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Pix Reserva (%):</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control" id="pixReservaMin" value="0" min="0" max="5" step="0.1">
                            <small class="form-text text-muted">Mínimo</small>
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" id="pixReservaMax" value="0" min="0" max="5" step="0.1">
                            <small class="form-text text-muted">Máximo</small>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Ato Contrato (%):</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control" id="atoContratoMin" value="8" min="8" max="10" step="0.1">
                            <small class="form-text text-muted">Mínimo</small>
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" id="atoContratoMax" value="10" min="8" max="10" step="0.1">
                            <small class="form-text text-muted">Máximo</small>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">30 Dias (%):</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control" id="trintaDiasMin" value="3" min="3" max="5" step="0.1">
                            <small class="form-text text-muted">Mínimo</small>
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" id="trintaDiasMax" value="5" min="3" max="5" step="0.1">
                            <small class="form-text text-muted">Máximo</small>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">60 Dias (%):</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control" id="sessentaDiasMin" value="3" min="3" max="5" step="0.1">
                            <small class="form-text text-muted">Mínimo</small>
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" id="sessentaDiasMax" value="5" min="3" max="5" step="0.1">
                            <small class="form-text text-muted">Máximo</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna 2 - Parcelas + Saldo -->
            <div class="col-md-4">
                <!-- Parcelas Mensais -->
                <div class="card-parametros">
                    <div class="card-header-parametros">Parcelas Mensais</div>
                    <div class="card-body-parametros">
                        <div class="form-group mb-3">
                            <label class="form-label">Percentual (%):</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" id="mensaisMin" value="15" min="0" step="0.1">
                                    <small class="form-text text-muted">Mínimo</small>
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" id="mensaisMax" value="20" min="0" step="0.1">
                                    <small class="form-text text-muted">Máximo</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Quantidade de Parcelas:</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" id="mensaisQtdMin" value="30" min="1">
                                    <small class="form-text text-muted">Mínimo</small>
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" id="mensaisQtdMax" value="32" min="1">
                                    <small class="form-text text-muted">Máximo</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parcelas Semestrais -->
                <div class="card-parametros">
                    <div class="card-header-parametros">Parcelas Semestrais</div>
                    <div class="card-body-parametros">
                        <div class="form-group mb-3">
                            <label class="form-label">Percentual (%):</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" id="semestraisMin" value="14" min="0" step="0.1">
                                    <small class="form-text text-muted">Mínimo</small>
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" id="semestraisMax" value="15" min="0" step="0.1">
                                    <small class="form-text text-muted">Máximo</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Quantidade de Parcelas:</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" id="semestraisQtdMin" value="4" min="1">
                                    <small class="form-text text-muted">Mínimo</small>
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" id="semestraisQtdMax" value="5" min="1">
                                    <small class="form-text text-muted">Máximo</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Saldo (Chaves) -->
                <div class="form-group mb-3">
                    <label class="form-label">Saldo (Chaves) (%):</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control" id="saldoMin" value="50" min="0" max="100" step="0.1">
                            <small class="form-text text-muted">Mínimo</small>
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" id="saldoMax" value="55" min="0" max="100" step="0.1">
                            <small class="form-text text-muted">Máximo</small>
                        </div>
                    </div>
                </div>

                <!-- Botão Calcular + Quantidade de Simulações -->
                <div class="d-flex align-items-center gap-3 mt-4">
                    <button class="btn btn-primary btn-lg" onclick="calcularFluxo()">
                        <i class="fas fa-calculator me-2"></i> Calcular
                    </button>
                    <div>
                        <label class="form-label me-2">Simulações:</label>
                        <input type="number" class="form-control simulacoes-input d-inline-block" id="quantidadeSimulacoes" value="3" min="1" max="5">
                    </div>
                </div>
            </div>

            <!-- Coluna 3 - Resultado das Simulações -->
            <div class="col-md-4">
                <h4 class="mb-3">Simulações Geradas</h4>
                <div class="memo-container">
                    <textarea class="form-control" id="memoSimulacoes" rows="25" readonly></textarea>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-secondary flex-fill" onclick="copiarSimulacoes()">
                        <i class="fas fa-copy me-1"></i> Copiar
                    </button>
                    <button class="btn btn-danger flex-fill" onclick="limparSimulacoes()">
                        <i class="fas fa-trash-alt me-1"></i> Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const valorImovelInput = document.getElementById('valorImovel');
        const memoSimulacoes = document.getElementById('memoSimulacoes');

        function formatarMoeda(valor) {
            return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });
        }

        function getRandom(min, max) {
            return Math.random() * (max - min) + min;
        }

        function efeitoPensando() {
            document.body.classList.add('loading');
            return new Promise(resolve => setTimeout(resolve, 500));
        }

        async function calcularFluxo() {
            const quantidadeSimulacoes = parseInt(document.getElementById('quantidadeSimulacoes').value) || 3;
            const qtdSimulacoes = Math.min(Math.max(quantidadeSimulacoes, 1), 5);

            for (let i = 0; i < qtdSimulacoes; i++) {
                await efeitoPensando();
                document.body.classList.remove('loading');

                // Valor do imóvel
                const valorImovelStr = valorImovelInput.value.replace(/\D/g, '');
                const valorImovel = parseFloat(valorImovelStr) / 100;
                if (isNaN(valorImovel) || valorImovel <= 0) {
                    alert('Por favor, insira um valor válido para o imóvel.');
                    return;
                }

                // Percentuais fixos
                const pixReservaPct = getRandom(
                    parseFloat(document.getElementById('pixReservaMin').value),
                    parseFloat(document.getElementById('pixReservaMax').value)
                ) / 100;

                const atoContratoPct = getRandom(
                    parseFloat(document.getElementById('atoContratoMin').value),
                    parseFloat(document.getElementById('atoContratoMax').value)
                ) / 100;

                const trintaDiasPct = getRandom(
                    parseFloat(document.getElementById('trintaDiasMin').value),
                    parseFloat(document.getElementById('trintaDiasMax').value)
                ) / 100;

                const sessentaDiasPct = getRandom(
                    parseFloat(document.getElementById('sessentaDiasMin').value),
                    parseFloat(document.getElementById('sessentaDiasMax').value)
                ) / 100;

                const saldoPct = getRandom(
                    parseFloat(document.getElementById('saldoMin').value),
                    parseFloat(document.getElementById('saldoMax').value)
                ) / 100;

                // Total fixos
                const totalPctFixos = pixReservaPct + atoContratoPct + trintaDiasPct + sessentaDiasPct + saldoPct;

                // Restante para parcelas
                const restanteParaParcelas = Math.max(0, 1 - totalPctFixos);

                // Parcelas mensais e semestrais
                const mensaisPct = Math.min(
                    getRandom(
                        parseFloat(document.getElementById('mensaisMin').value) / 100,
                        parseFloat(document.getElementById('mensaisMax').value) / 100
                    ),
                    restanteParaParcelas
                );

                const semestraisPct = restanteParaParcelas - mensaisPct;

                // Valores monetários
                const pixReservaValor     = valorImovel * pixReservaPct;
                const atoContratoValor    = valorImovel * atoContratoPct;
                const trintaDiasValor     = valorImovel * trintaDiasPct;
                const sessentaDiasValor   = valorImovel * sessentaDiasPct;
                const saldoValor          = valorImovel * saldoPct;
                const mensaisValorTotal   = valorImovel * mensaisPct;
                const semestraisValorTotal = valorImovel * semestraisPct;

                // Quantidade de parcelas
                const mensaisQtd = Math.floor(getRandom(
                    parseInt(document.getElementById('mensaisQtdMin').value),
                    parseInt(document.getElementById('mensaisQtdMax').value) + 1
                ));
                const mensaisValorParcela = mensaisValorTotal / mensaisQtd;

                const semestraisQtd = Math.floor(getRandom(
                    parseInt(document.getElementById('semestraisQtdMin').value),
                    parseInt(document.getElementById('semestraisQtdMax').value) + 1
                ));
                const semestraisValorParcela = semestraisValorTotal / (semestraisQtd > 0 ? semestraisQtd : 1);

                // Verificação
                const totalValor = pixReservaValor + atoContratoValor + trintaDiasValor +
                                  sessentaDiasValor + saldoValor + mensaisValorTotal + semestraisValorTotal;

                const totalPct = (totalPctFixos + mensaisPct + semestraisPct) * 100;

                // Texto da simulação
                const simulacaoNumero = (memoSimulacoes.value.split('Simulação nº').length);
                let simulacaoTexto = `Simulação nº ${simulacaoNumero}
--------------------------
`;

                if (pixReservaValor > 0) {
                    simulacaoTexto += `=> Pix Reserva (${(pixReservaPct * 100).toFixed(2)}%): ${formatarMoeda(pixReservaValor)}\n`;
                }

                simulacaoTexto += `=> Ato Contrato (${(atoContratoPct * 100).toFixed(2)}%): ${formatarMoeda(atoContratoValor)}
=> 30 Dias (${(trintaDiasPct * 100).toFixed(2)}%): ${formatarMoeda(trintaDiasValor)}
=> 60 Dias (${(sessentaDiasPct * 100).toFixed(2)}%): ${formatarMoeda(sessentaDiasValor)}
=> Parcelas Mensais (${(mensaisPct * 100).toFixed(2)}% em ${mensaisQtd}x): ${formatarMoeda(mensaisValorTotal)} (${formatarMoeda(mensaisValorParcela)}/mês)
=> Parcelas Semestrais (${(semestraisPct * 100).toFixed(2)}% em ${semestraisQtd}x): ${formatarMoeda(semestraisValorTotal)} (${formatarMoeda(semestraisValorParcela)}/semestre)
=> Saldo (Chaves) (${(saldoPct * 100).toFixed(2)}%): ${formatarMoeda(saldoValor)}
Total: ${formatarMoeda(totalValor)}
Total (%): ${totalPct.toFixed(2)}%
-------------------------
`;

                memoSimulacoes.value += (memoSimulacoes.value ? '\n' : '') + simulacaoTexto;

                // Reaplica máscara no valor do imóvel
                let value = valorImovelInput.value.replace(/\D/g, '');
                if (value) {
                    value = (parseInt(value) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                valorImovelInput.value = value;
            }
        }

        function copiarSimulacoes() {
            memoSimulacoes.select();
            document.execCommand('copy');
            alert('Simulações copiadas para a área de transferência!');
        }

        function limparSimulacoes() {
            memoSimulacoes.value = '';
        }

        // Máscara monetária no campo Valor do Imóvel
        valorImovelInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value) {
                value = (parseInt(value) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            this.value = value;
        });
    </script>
</body>
</html>
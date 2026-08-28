<?php
// Inclui os arquivos necessários
session_start();
require_once 'conn.php';
require_once "manutencao_config.php";
require_once 'theme_config.php'; // <-- CARREGA O TEMA
//require_once 'log_navegacao.php';

// Função para remover números (equivalente à função ASP)
function removerNumeros($strTexto) {
    if (empty($strTexto)) return '';
    $strResultado = preg_replace('/[0-9]/', '', $strTexto);
    $strResultado = str_replace(['-', '?'], '', $strResultado);
    return $strResultado;
}

// Envio de email de login (simulado)
$enviarEmail = false;
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    $enviarEmail = true;
    $logEmail = date('Y-m-d H:i:s') . " - Email simulado para: SI-CALC-X7b - " . 
                strtoupper($_SESSION['Usuario']) . " - " . $_SERVER['REMOTE_ADDR'] . "\n";
    file_put_contents('logs/email_simulado.log', $logEmail, FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Simulação com Percentuais-vX7b</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA (via variáveis CSS)                  */
        /* ============================================================ */
        :root {
            --primary: <?php echo $theme['primary']; ?>;
            --primary-dark: <?php echo $theme['primary_dark']; ?>;
            --secondary: <?php echo $theme['secondary']; ?>;
            --accent: <?php echo $theme['accent']; ?>;
            --bg-body: <?php echo $theme['bg_body']; ?>;
            --text-color: <?php echo $theme['text_color']; ?>;
            --card-bg: <?php echo $theme['card_bg']; ?>;
            --shadow-color: <?php echo $theme['shadow_color']; ?>;
            --btn-bg: <?php echo $theme['btn_bg']; ?>;
            --btn-hover: <?php echo $theme['btn_hover']; ?>;
            --btn-text: <?php echo $theme['btn_text']; ?>;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-body);
            color: var(--text-color);
            min-height: 100vh;
        }

        /* Header neobrutalista */
        .header-neo {
            background: var(--primary);
            color: #fff;
            padding: 1rem 0;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            margin-bottom: 2rem;
        }
        .header-neo .logo-img {
            height: 50px;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            background: #fff;
            padding: 4px;
            margin-right: 15px;
        }
        .header-neo h1 {
            font-weight: 800;
            font-size: 1.6rem;
            margin: 0;
        }
        .header-neo p {
            margin: 0;
            opacity: 0.9;
        }
        .btn-neo-close {
            background: #dc3545;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #b02a37;
        }

        /* Cards e seções */
        .section-header-neo {
            background: var(--primary);
            color: #fff;
            padding: 0.8rem 1.2rem;
            border-radius: 12px 12px 0 0;
            border-bottom: 3px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            font-weight: 700;
        }

        .card-neo {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .card-neo .card-body {
            padding: 1.5rem;
        }

        .payment-row {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            padding: 0.8rem;
            margin-bottom: 0.8rem;
            transition: all 0.2s;
        }
        .payment-row:hover {
            transform: translateY(-2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
        }
        .payment-row.filled {
            background: rgba(255, 182, 193, 0.15);
            border-left: 6px solid var(--primary);
        }

        .results-container {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            padding: 1.5rem;
            height: 100%;
        }

        /* Formulário */
        .form-control, .form-select {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            background: #fff;
            color: var(--text-color);
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .form-control:disabled {
            background: #e9ecef;
            opacity: 0.8;
        }

        .input-monetario, .input-percentual {
            text-align: right;
        }
        .input-percentual {
            background: #ffe0e0 !important;
        }

        /* Botões neobrutalistas */
        .btn-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.5rem 2rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo-outline {
            background: transparent;
            color: var(--text-color);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-outline:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }

        /* Select2 neobrutalista */
        .select2-container--default .select2-selection--single {
            border: 2px solid var(--shadow-color) !important;
            border-radius: 10px !important;
            height: 42px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px !important;
            color: var(--text-color);
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
        }
        .select2-dropdown {
            border: 2px solid var(--shadow-color) !important;
            border-radius: 10px !important;
        }

        /* Caixa de mensagem */
        .custom-message-box {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 15px;
            border-radius: 12px;
            border: 3px solid var(--shadow-color);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            max-width: 400px;
            font-weight: 600;
        }
        .custom-message-box.success {
            background: #d4edda;
            color: #155724;
        }
        .custom-message-box.error {
            background: #f8d7da;
            color: #721c24;
        }
        .custom-message-box.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .sucesso {
            color: #155724;
            font-weight: 700;
            padding: 10px;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            background: #d4edda;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
        }
        .erro {
            color: #721c24;
            font-weight: 700;
            padding: 10px;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            background: #f8d7da;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
        }

        .footer {
            margin-top: 2rem;
            padding: 1rem;
            text-align: center;
            border-top: 3px solid var(--shadow-color);
            font-weight: 600;
            color: var(--text-color);
            opacity: 0.7;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-neo h1 { font-size: 1.2rem; }
            .header-neo .logo-img { height: 40px; }
            .results-container { margin-top: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Caixa de mensagem flutuante -->
    <div id="customMessageBox" class="custom-message-box hidden">
        <div id="customMessageBoxText"></div>
        <button id="customMessageBoxClose" class="btn btn-sm btn-light mt-2" style="border: 2px solid var(--shadow-color); border-radius: 8px; font-weight: 700;">Fechar</button>
    </div>

    <div class="container mt-4 mb-5">
        <!-- HEADER NEOBRUTALISTA -->
        <header class="header-neo">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?php echo htmlspecialchars($_SESSION['Conf_ImgLogo']); ?>" alt="Logotipo" class="logo-img">
                        <div>
                            <h1><i class="fas fa-building me-2"></i>Calculadora Interativa X7d</h1>
                            <p>SunnyImob - <?php echo htmlspecialchars($_SESSION['Conf_Versao']); ?></p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="d-none d-md-inline" style="font-weight: 600;">Usuário: <?php echo htmlspecialchars($_SESSION['Usuario']); ?></span>
                        <button class="btn-neo-close" onclick="window.close()">
                            <i class="fas fa-times me-1"></i> Fechar
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <form id="formPagamentos" method="post" action="salvar_fluxoAPIX7d.php">        
            <!-- Informações Gerais -->
            <div class="card-neo">
                <div class="section-header-neo">
                    <h4><i class="fas fa-info-circle me-2"></i>Informações Gerais</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 col-6">
                            <label for="data" class="form-label fw-bold">Data</label>
                            <input type="date" class="form-control" id="data" name="data">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="empreendimento" class="form-label fw-bold">Empreendimento</label>
                            <select class="form-select" id="empreendimento" name="empreendimento" required>
                                <option value="">Selecione um empreendimento...</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT Empreend_ID, NomeEmpreendimento FROM empreendimento ORDER BY NomeEmpreendimento ASC");
                                    while ($row = $stmt->fetch()) {
                                        $nomeFormatado = removerNumeros($row['NomeEmpreendimento']);
                                        $selected = (isset($_POST['empreendimento']) && $_POST['empreendimento'] == $row['Empreend_ID']) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($row['Empreend_ID']) . '" ' . $selected . '>' . 
                                             htmlspecialchars(strtoupper($nomeFormatado)) . '</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<option value="">Erro ao carregar empreendimentos</option>';
                                    error_log("Erro ao carregar empreendimentos: " . $e->getMessage());
                                }
                                ?>
                            </select>
                            <div id="FluxoPadrao" class="FluxoPadrao" style="display: none;"></div>
                        </div>
                        
                        <div class="col-md-4 col-6">
                            <label for="cliente" class="form-label fw-bold">Cliente</label>
                            <input type="text" class="form-control text-uppercase" id="cliente" name="cliente" 
                                   value="<?php echo htmlspecialchars($_POST['cliente'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 col-6">
                            <label for="corretor" class="form-label fw-bold">Corretor</label>
                            <input type="text" class="form-control text-uppercase" id="corretor" name="corretor">
                        </div>
                        
                        <div class="col-md-4 col-6">
                            <label for="unidade" class="form-label fw-bold">Unidade</label>
                            <input type="text" class="form-control text-uppercase" id="unidade" name="unidade"
                                   value="<?php echo htmlspecialchars($_POST['unidade'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-2 col-3">
                            <label for="m2" class="form-label fw-bold">M2 (999,99)</label>
                            <input type="text" class="form-control" id="m2" name="m2" required
                                   value="<?php echo htmlspecialchars($_POST['m2'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 col-6">
                            <label for="valorUnidade" class="form-label fw-bold">Valor da Unidade</label>
                            <input type="text" class="form-control input-monetario" id="valorUnidade" name="valorUnidade" 
                                   placeholder="0,00" required value="<?php echo htmlspecialchars($_POST['valorUnidade'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etapas de Pagamentos -->
            <div class="card-neo">
                <div class="section-header-neo">
                    <h4><i class="fas fa-hand-holding-usd me-2"></i>Etapas de Pagamentos</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="row mb-2 fw-bold d-none d-md-flex" style="color: var(--text-color);">
                                <div class="col-md-2">Etapa</div>
                                <div class="col-md-2">Quantidade</div>
                                <div class="col-md-2">%</div>
                                <div class="col-md-2">Valor</div>
                                <div class="col-md-2">Total %</div>
                            </div>

                            <!-- Detalhes das Entradas -->
                            <div class="card-neo" style="box-shadow: 3px 3px 0px 0px var(--shadow-color);">
                                <div class="card-body" style="background: var(--secondary); border-radius: 12px;">
                                    <h5 class="card-title" style="font-weight: 700; color: var(--text-color);">
                                        <i class="fas fa-list-ul me-2"></i>Detalhes das Entradas
                                    </h5>

                                    <?php
                                    $etapas = [
                                        'entrada1' => 'Pix Reserva',
                                        'entrada2' => 'Ato/Contrato',
                                        'entrada3' => '30 dias',
                                        'entrada4' => '60 dias',
                                        'entrada5' => '90 Dias',
                                        'entrada6' => '120 Dias',
                                        'entrada7' => '150 Dias',
                                        'entrada8' => '180 Dias',
                                        'entrada9' => 'Parcelas Mensais',
                                        'entrada10' => 'Parcelas Semestrais',
                                        'entrada11' => 'Chaves'
                                    ];
                                    
                                    foreach ($etapas as $key => $nomeEtapa) {
                                        $qtd = $_POST[$key . '_qtd'] ?? '1';
                                        $pct = $_POST[$key . '_pct'] ?? '';
                                        $valor = $_POST[$key . '_valor'] ?? '';
                                        $percentual = $_POST[$key . '_percentual'] ?? '';
                                    ?>
                                    <div class="payment-row">
                                        <div class="row align-items-center">
                                            <div class="col-12 col-md-2 fw-bold mb-2 mb-md-0" style="color: var(--primary);"><?php echo $nomeEtapa; ?></div>
                                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                <label class="d-md-none fw-bold">Quantidade</label>
                                                <input type="number" class="form-control qtd" data-etapa="<?php echo $key; ?>" 
                                                       min="0" value="<?php echo htmlspecialchars($qtd); ?>" 
                                                       name="<?php echo $key; ?>_qtd">
                                            </div>
                                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                <label class="d-md-none fw-bold">%</label>
                                                <input type="text" class="form-control pct input-percentual" 
                                                       data-etapa="<?php echo $key; ?>" placeholder="0,00%" 
                                                       style="background-color: #ffe0e0;" 
                                                       name="<?php echo $key; ?>_pct" 
                                                       value="<?php echo htmlspecialchars($pct); ?>">
                                            </div>
                                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                <label class="d-md-none fw-bold">Valor</label>
                                                <input type="text" class="form-control valor input-monetario" 
                                                       data-etapa="<?php echo $key; ?>" placeholder="0,00" 
                                                       name="<?php echo $key; ?>_valor" 
                                                       value="<?php echo htmlspecialchars($valor); ?>">
                                            </div>
                                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                                <label class="d-md-none fw-bold">Total %</label>
                                                <input type="text" class="form-control percentual" 
                                                       data-etapa="<?php echo $key; ?>" readonly 
                                                       name="<?php echo $key; ?>_percentual" 
                                                       value="<?php echo htmlspecialchars($percentual); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões de ação -->
            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn-neo" id="btnSalvar">
                    <i class="fas fa-save me-1"></i> Salvar
                </button>
            </div>
            <div id="mensagemRetorno" class="mt-4" style="text-align: center;"></div>
        </form>

        <!-- Coluna dos Resultados (dentro de um container fora do form) -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="results-container">
                    <div class="section-header-neo" style="border-radius: 12px 12px 0 0; margin-bottom: 1rem;">
                        <h4><i class="fas fa-calculator me-2"></i>Resultados</h4>
                    </div>
                    <div class="row">
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label fw-bold">Valor da Unidade:</label>
                            <input type="text" class="form-control" id="valorUnidadeResult" readonly style="background-color: #e0f2f7;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label fw-bold">Valor Total Pago:</label>
                            <input type="text" class="form-control" id="totalPago" readonly style="background-color: #e0f2f7;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label fw-bold">Percentual Total Pago:</label>
                            <input type="text" class="form-control" id="percentualTotal" readonly style="background-color: #e0f2f7;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label fw-bold">Saldo Devedor:</label>
                            <input type="text" class="form-control" id="saldoDevedor" readonly style="background-color: #ffe0e0;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label fw-bold">Saldo Percentual:</label>
                            <input type="text" class="form-control" id="saldoPercentual" readonly style="background-color: #ffe0e0;">
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="fw-bold"><i class="fas fa-list-ul me-2"></i>Detalhamento por Etapa:</h6>
                        <textarea id="detalhesEtapas" class="form-control small" rows="25" readonly style="resize: vertical; background-color: #e0f2f7; border: 2px solid var(--shadow-color); border-radius: 10px;"></textarea>
                        <div class="d-grid gap-2 mt-2">
                            <button type="button" id="copiarDetalhes" class="btn-neo-outline">
                                <i class="fas fa-copy me-1"></i> Copiar Detalhamento
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <span id="currentDate"></span>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Configuração inicial da data
        const dataInput = document.getElementById('data');
        const hoje = new Date();
        dataInput.value = hoje.toISOString().substr(0, 10);
        document.getElementById('currentDate').textContent = hoje.toLocaleDateString('pt-BR');

        // 2. Funções de formatação
        function formatarMoeda(input) {
            if (typeof input === 'object') {
                let valor = input.value.replace(/\D/g, '');
                valor = valor.replace(/^0+/, '') || '0';
                valor = valor === '' ? '0' : valor;
                if (valor.length === 1) valor = valor;
                else if (valor.length === 2) valor = valor;
                valor = valor.replace(/(\d+)(\d{2})$/, '$1,$2');
                valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                input.value = valor;
            } else {
                let valor = input.replace(/\D/g, '');
                valor = valor.replace(/^0+/, '') || '0';
                valor = valor === '' ? '0' : valor;
                if (valor.length === 1) valor = valor + '00';
                if (valor.length === 2) valor = valor + '0';
                return valor.replace(/(\d+)(\d{2})$/, '$1,$2')
                           .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            }
        }

        function formatarPercentual(input) {
            if (typeof input === 'object') {
                let valor = input.value.replace(/\D/g, '');
                valor = valor.replace(/^0+/, '') || '0';
                valor = valor === '' ? '0' : valor;
                if (valor.length === 1) valor = '0' + valor + '0';
                else if (valor.length === 2) valor = valor;
                valor = valor.replace(/(\d+)(\d{2})$/, '$1,$2') + '%';
                input.value = valor;
            } else {
                let valor = input.replace(/\D/g, '');
                valor = valor.replace(/^0+/, '') || '0';
                valor = valor === '' ? '0' : valor;
                if (valor.length === 1) valor = '0' + valor + '0';
                if (valor.length === 2) valor = valor + '0';
                return valor.replace(/(\d+)(\d{2})$/, '$1,$2') + '%';
            }
        }

        function parseMoeda(valor) {
            return parseFloat(valor.replace(/\./g, '').replace(',', '.')) || 0;
        }

        function parsePercentual(valor) {
            return parseFloat(valor.replace('%', '').replace(',', '.')) || 0;
        }

        function toMoeda(numero) {
            return numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function toPercentual(numero) {
            return numero.toFixed(2).replace('.', ',') + '%';
        }

        // 3. Configuração dos listeners de input
        function configurarInputs() {
            document.querySelectorAll('.input-monetario').forEach(input => {
                if (input.value) formatarMoeda(input);
                input.addEventListener('input', function() {
                    formatarMoeda(this);
                    const row = this.closest('.payment-row');
                    if (row) {
                        calcularValorOuPercentual(this);
                        atualizarEstadoLinha(row);
                    }
                    calcularTotais();
                });
                input.addEventListener('blur', function() {
                    if (this.value === '') this.value = '0,00';
                    formatarMoeda(this);
                });
            });

            document.querySelectorAll('.input-percentual').forEach(input => {
                if (input.value) formatarPercentual(input);
                input.addEventListener('input', function() {
                    formatarPercentual(this);
                    const row = this.closest('.payment-row');
                    if (row) {
                        calcularValorOuPercentual(this);
                        atualizarEstadoLinha(row);
                    }
                    calcularTotais();
                });
                input.addEventListener('blur', function() {
                    if (this.value === '' || this.value === '%') this.value = '0,00%';
                    formatarPercentual(this);
                });
            });

            document.querySelectorAll('.qtd').forEach(input => {
                input.addEventListener('input', function() {
                    const row = this.closest('.payment-row');
                    if (row) {
                        atualizarEstadoLinha(row);
                        calcularTotais();
                    }
                });
            });

            document.getElementById('valorUnidade').addEventListener('input', function() {
                formatarMoeda(this);
                document.querySelectorAll('.payment-row').forEach(row => {
                    const pctInput = row.querySelector('.pct');
                    if (pctInput && pctInput.value !== '0,00%') {
                        calcularValorOuPercentual(pctInput);
                    }
                });
                calcularTotais();
            });
        }

        // 4. Funções de cálculo
        function calcularValorOuPercentual(input) {
            const row = input.closest('.payment-row');
            const valorUnidade = parseMoeda(document.getElementById('valorUnidade').value);
            if (valorUnidade <= 0) return;

            const qtd = parseFloat(row.querySelector('.qtd').value) || 0;
            const valorInput = row.querySelector('.valor');
            const pctInput = row.querySelector('.pct');
            const percentualInput = row.querySelector('.percentual');

            if (input === valorInput) {
                const valor = parseMoeda(valorInput.value);
                const percentual = (valor / valorUnidade) * 100;
                pctInput.value = toPercentual(percentual);
            } else if (input === pctInput) {
                const percentual = parsePercentual(pctInput.value);
                const valor = (valorUnidade * percentual) / 100;
                valorInput.value = toMoeda(valor);
            }

            const valor = parseMoeda(valorInput.value);
            const percentualCalculado = (valor * qtd / valorUnidade) * 100;
            percentualInput.value = toPercentual(percentualCalculado);
        }

        function atualizarEstadoLinha(row) {
            const qtd = row.querySelector('.qtd').value;
            const valor = row.querySelector('.valor').value;
            const pct = row.querySelector('.pct').value;
            if (qtd || valor !== '0,00' || pct !== '0,00%') {
                row.classList.add('filled');
            } else {
                row.classList.remove('filled');
            }
        }

        // Função principal de cálculo - CORRIGIDA para garantir preenchimento do textarea
        function calcularTotais() {
            const valorUnidade = parseMoeda(document.getElementById('valorUnidade').value);
            if (valorUnidade <= 0) {
                // Se valor da unidade for zero, limpar resultados
                document.getElementById('totalPago').value = 'R$ 0,00';
                document.getElementById('percentualTotal').value = '0,00%';
                document.getElementById('saldoDevedor').value = 'R$ 0,00';
                document.getElementById('saldoPercentual').value = '0,00%';
                document.getElementById('valorUnidadeResult').value = 'R$ 0,00';
                document.getElementById('detalhesEtapas').value = 'Nenhum dado para calcular.';
                return;
            }

            let totalPago = 0;
            let etapasDetalhes = [];
            const infoBase = obterInformacoesBase();

            // Cabeçalho do detalhamento
            let cabecalho = '';
            if (infoBase.empreendimento) {
                cabecalho = `SIMULAÇÃO DE PAGAMENTO:\n-Imóvel: ${infoBase.empreendimento}\n-Unidade: ${infoBase.unidade}\n-M2: ${infoBase.m2}\n-Valor Unidade: R$ ${infoBase.valorUnidade}\n-Cliente: ${infoBase.cliente}\n-Corretor: ${infoBase.corretor}\n-Data: ${infoBase.dataFormatada}\n\n`;
            } else {
                cabecalho = 'DETALHAMENTO DOS PAGAMENTOS:\n\n';
            }
            etapasDetalhes.push(cabecalho);

            // Percorrer as linhas de pagamento
            document.querySelectorAll('.payment-row').forEach(row => {
                const qtdInput = row.querySelector('.qtd');
                const valorInput = row.querySelector('.valor');
                const percentualInput = row.querySelector('.percentual');

                if (qtdInput && valorInput) {
                    const qtd = parseFloat(qtdInput.value) || 0;
                    const valor = parseMoeda(valorInput.value);
                    const valorTotalEtapa = qtd * valor;
                    const percentual = (valorUnidade > 0) ? (valorTotalEtapa / valorUnidade) * 100 : 0;

                    if (percentualInput) {
                        percentualInput.value = toPercentual(percentual);
                    }

                    if (qtd > 0 && valor > 0) {
                        // Tenta obter o nome da etapa (primeira div com texto)
                        const etapaNomeDiv = row.querySelector('div.col-md-2:first-child');
                        const etapaNome = etapaNomeDiv ? etapaNomeDiv.textContent.trim() : 'Etapa';
                        const parcelaText = qtd > 1 ? `${qtd}x de R$ ${toMoeda(valor)}` : `R$ ${toMoeda(valor)}`;
                        const totalEtapaText = qtd > 1 ? `Total de R$ ${toMoeda(valorTotalEtapa)}` : '';
                        etapasDetalhes.push(`${etapaNome}: ${parcelaText} ${totalEtapaText} (${percentual.toFixed(2)}%)\n\n`);
                    }
                    totalPago += valorTotalEtapa;
                }
            });

            const percentualTotal = (valorUnidade > 0) ? (totalPago / valorUnidade) * 100 : 0;
            const saldoDevedor = valorUnidade - totalPago;
            const saldoPercentual = 100 - percentualTotal;

            // Atualizar os campos de resultado
            document.getElementById('totalPago').value = 'R$ ' + toMoeda(totalPago);
            document.getElementById('percentualTotal').value = toPercentual(percentualTotal);
            document.getElementById('saldoDevedor').value = 'R$ ' + toMoeda(saldoDevedor);
            document.getElementById('saldoPercentual').value = toPercentual(saldoPercentual);
            document.getElementById('valorUnidadeResult').value = 'R$ ' + toMoeda(valorUnidade);

            // Se não houver detalhes, adicionar uma mensagem
            if (etapasDetalhes.length <= 1) {
                etapasDetalhes.push('Nenhum pagamento registrado.\n');
            }

            // Adicionar resumo final
            etapasDetalhes.push(`----------------------------------------\nTotal Acumulado: R$ ${toMoeda(totalPago)} (${percentualTotal.toFixed(2)}%)`);

            // Preencher o textarea
            const detalhesTextarea = document.getElementById('detalhesEtapas');
            if (detalhesTextarea) {
                detalhesTextarea.value = etapasDetalhes.join('');
            } else {
                console.warn('Elemento detalhesEtapas não encontrado.');
            }
        }

        function obterInformacoesBase() {
            const dataValue = document.getElementById('data').value;
            const empreendimentoSelect = document.getElementById('empreendimento');
            const selectedOption = empreendimentoSelect.options[empreendimentoSelect.selectedIndex];
            let dataFormatada = '';
            if (dataValue) {
                const partesData = dataValue.split('-');
                dataFormatada = `${partesData[2]}/${partesData[1]}/${partesData[0]}`;
            }
            return {
                empreendimento: selectedOption ? selectedOption.text : '',
                unidade: document.getElementById('unidade').value.toUpperCase(),
                m2: document.getElementById('m2').value,
                valorUnidade: document.getElementById('valorUnidade').value,
                cliente: document.getElementById('cliente').value.toUpperCase(),
                corretor: document.getElementById('corretor').value.toUpperCase(),
                dataFormatada: dataFormatada
            };
        }

        function showMessageBox(message, type = 'info') {
            const messageBox = document.getElementById('customMessageBox');
            const messageText = document.getElementById('customMessageBoxText');
            const closeButton = document.getElementById('customMessageBoxClose');
            if (!messageBox || !messageText || !closeButton) {
                console.warn('Elementos da caixa de mensagem não encontrados. Exibindo no console:', message);
                return;
            }
            messageText.textContent = message;
            messageBox.className = 'custom-message-box';
            if (type === 'success') messageBox.classList.add('success');
            else if (type === 'error') messageBox.classList.add('error');
            else messageBox.classList.add('info');
            messageBox.classList.remove('hidden');
            closeButton.onclick = function() { messageBox.classList.add('hidden'); };
            setTimeout(() => { messageBox.classList.add('hidden'); }, 5000);
        }

        // 5. Função para enviar email ao copiar
        document.getElementById('copiarDetalhes').addEventListener('click', function() {
            const textarea = document.getElementById('detalhesEtapas');
            if (textarea && textarea.value.trim() !== '') {
                textarea.select();
                textarea.setSelectionRange(0, 99999);
                try {
                    navigator.clipboard.writeText(textarea.value).then(() => {
                        enviarConteudoPorEmail(textarea.value)
                            .then(() => {
                                showMessageBox('Detalhamento copiado e registro enviado com sucesso!', 'success');
                            })
                            .catch((error) => {
                                showMessageBox('Detalhamento copiado, mas o registro não foi enviado. Erro: ' + error.message, 'error');
                                console.error('Erro ao enviar conteúdo por email:', error);
                            });
                    }).catch(err => {
                        document.execCommand('copy');
                        showMessageBox('Detalhamento copiado!', 'success');
                    });
                } catch (err) {
                    document.execCommand('copy');
                    showMessageBox('Detalhamento copiado!', 'success');
                }
            } else {
                showMessageBox('Nenhum conteúdo para copiar.', 'error');
            }
        });

        function enviarConteudoPorEmail(conteudo) {
            return new Promise((resolve, reject) => {
                const dados = {
                    conteudo: conteudo,
                    usuario: document.getElementById('corretor').value || 'Não informado',
                    empreendimento: document.getElementById('empreendimento').value || 'Não informado'
                };
                fetch('enviar_email_textarea.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(dados)
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.sucesso) resolve(data.mensagem);
                    else reject(data.mensagem);
                })
                .catch(error => {
                    console.error("Erro no envio:", error);
                    reject("Falha ao enviar: " + error.message);
                });
            });
        }

        // 6. Inicialização
        configurarInputs();
        calcularTotais();

        // Recalcula ao mudar o empreendimento (para atualizar o cabeçalho)
        document.getElementById('empreendimento').addEventListener('change', function() {
            calcularTotais();
        });

        $(document).ready(function () {
            $('#empreendimento').select2({
                placeholder: "Selecione um empreendimento...",
                allowClear: true,
                width: '100%'
            });
        });
    });
    </script>

    <script>
        // Script para enviar o formulário via Fetch API
        document.getElementById('formPagamentos').addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const mensagemRetorno = document.getElementById('mensagemRetorno');
            mensagemRetorno.textContent = 'Salvando...';
            mensagemRetorno.className = '';

            const formData = new FormData(form);
            const urlEncodedData = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                urlEncodedData.append(key, value);
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: urlEncodedData.toString()
                });

                if (response.ok) {
                    const result = await response.text();
                    mensagemRetorno.className = 'sucesso';
                    mensagemRetorno.innerHTML = `<strong>Sucesso!</strong> Dados salvos com sucesso!`;
                    setTimeout(() => {
                        form.reset();
                        calcularTotais();
                        document.getElementById('data').value = new Date().toISOString().substr(0, 10);
                    }, 2000);
                } else {
                    const errorText = await response.text();
                    mensagemRetorno.className = 'erro';
                    mensagemRetorno.innerHTML = `<strong>Erro ao enviar!</strong> Status: ${response.status} ${response.statusText}. Resposta do servidor: <pre>${errorText}</pre>`;
                }
            } catch (error) {
                mensagemRetorno.className = 'erro';
                mensagemRetorno.innerHTML = `<strong>Ocorreu um erro na requisição:</strong> ${error.message}`;
                console.error('Erro na requisição Fetch:', error);
            }
        });
    </script>
</body>
</html>
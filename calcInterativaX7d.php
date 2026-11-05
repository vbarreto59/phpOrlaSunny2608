<?php
// Inclui os arquivos necessários
session_start();
require_once 'conn.php';
require_once "manutencao_config.php";
require_once 'theme_config.php';

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
    @file_put_contents('logs/email_simulado.log', $logEmail, FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Simulação com Percentuais-vX7d</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA                                      */
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
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-body);
            color: var(--text-color);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
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
            font-weight: 900;
            font-size: 1.5rem;
            margin: 0;
            letter-spacing: -0.3px;
        }
        .header-neo p {
            margin: 0;
            opacity: 0.9;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .btn-neo-close {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 800;
            transition: all 0.15s;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.85rem;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #b02a37;
            color: #fff;
        }

        /* Cards e seções */
        .section-header-neo {
            background: var(--primary);
            color: #fff;
            padding: 0.8rem 1.2rem;
            border-radius: 12px 12px 0 0;
            border-bottom: 3px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            font-weight: 800;
        }
        .section-header-neo h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: -0.2px;
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
        .form-label {
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            opacity: 0.85;
            margin-bottom: 5px;
        }
        .form-control, .form-select {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            background: #fff;
            color: var(--text-color);
            font-family: inherit;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .form-control:disabled,
        .form-control[readonly] {
            background: #e9ecef;
            opacity: 0.85;
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
            padding: 0.55rem 2rem;
            font-weight: 800;
            transition: all 0.15s;
            font-family: inherit;
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
            padding: 0.5rem 1.5rem;
            font-weight: 800;
            transition: all 0.15s;
            font-family: inherit;
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
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px !important;
            color: var(--text-color);
            font-weight: 600;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
        }
        .select2-dropdown {
            border: 2px solid var(--shadow-color) !important;
            border-radius: 10px !important;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
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
            transition: opacity 0.3s;
        }
        .custom-message-box.hidden {
            display: none;
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

        #detalhesEtapas {
            font-family: 'SFMono-Regular', Consolas, 'Courier New', monospace;
            font-size: 0.76rem;
            line-height: 1.5;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            background: #f8f9fb !important;
            color: var(--text-color);
            resize: vertical;
            min-height: 420px;
            white-space: pre;
            overflow-x: auto;
            padding: 14px;
            font-weight: 500;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-neo h1 { font-size: 1.15rem; }
            .header-neo .logo-img { height: 40px; }
            .results-container { margin-top: 1rem; }
            #detalhesEtapas { font-size: 0.7rem; min-height: 320px; }
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
                        <img src="<?php echo htmlspecialchars($_SESSION['Conf_ImgLogo'] ?? 'logoSunny66a.JPG'); ?>" alt="Logotipo" class="logo-img">
                        <div>
                            <h1><i class="fas fa-calculator me-2"></i>Calculadora Interativa X7d</h1>
                            <p>SunnyImob - <?php echo htmlspecialchars($_SESSION['Conf_Versao'] ?? 'v7.0'); ?></p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="d-none d-md-inline" style="font-weight: 600;">Usuário: <?php echo htmlspecialchars($_SESSION['Usuario'] ?? ''); ?></span>
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
                            <label for="data" class="form-label">Data</label>
                            <input type="date" class="form-control" id="data" name="data">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="empreendimento" class="form-label">Empreendimento</label>
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
                            <label for="cliente" class="form-label">Cliente</label>
                            <input type="text" class="form-control text-uppercase" id="cliente" name="cliente" 
                                   value="<?php echo htmlspecialchars($_POST['cliente'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 col-6">
                            <label for="corretor" class="form-label">Corretor</label>
                            <input type="text" class="form-control text-uppercase" id="corretor" name="corretor">
                        </div>
                        
                        <div class="col-md-4 col-6">
                            <label for="unidade" class="form-label">Unidade</label>
                            <input type="text" class="form-control text-uppercase" id="unidade" name="unidade"
                                   value="<?php echo htmlspecialchars($_POST['unidade'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-2 col-3">
                            <label for="m2" class="form-label">M2 (999,99)</label>
                            <input type="text" class="form-control" id="m2" name="m2" required
                                   value="<?php echo htmlspecialchars($_POST['m2'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 col-6">
                            <label for="valorUnidade" class="form-label">Valor da Unidade</label>
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
                                    <h5 class="card-title" style="font-weight: 800; color: #fff;">
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

        <!-- Coluna dos Resultados -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="results-container">
                    <div class="section-header-neo" style="border-radius: 12px 12px 0 0; margin-bottom: 1rem;">
                        <h4><i class="fas fa-calculator me-2"></i>Resultados</h4>
                    </div>
                    <div class="row">
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label">Valor da Unidade</label>
                            <input type="text" class="form-control" id="valorUnidadeResult" readonly style="background-color: #e0f2f7;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label">Valor Total Pago</label>
                            <input type="text" class="form-control" id="totalPago" readonly style="background-color: #e0f2f7;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label">Percentual Total Pago</label>
                            <input type="text" class="form-control" id="percentualTotal" readonly style="background-color: #e0f2f7;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label">Saldo Devedor</label>
                            <input type="text" class="form-control" id="saldoDevedor" readonly style="background-color: #ffe0e0;">
                        </div>
                        <div class="col-md-6 col-6 mb-3">
                            <label class="form-label">Saldo Percentual</label>
                            <input type="text" class="form-control" id="saldoPercentual" readonly style="background-color: #ffe0e0;">
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="fw-bold"><i class="fas fa-list-ul me-2"></i>Detalhamento por Etapa:</h6>
                        <textarea id="detalhesEtapas" class="form-control" rows="25" readonly></textarea>
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

        // ============================================================
        // 1. MAPA E GRUPOS DE ETAPAS (para o detalhamento)
        // ============================================================
        const NOMES_ETAPAS = {
            entrada1:  'Pix Reserva',
            entrada2:  'Ato / Contrato',
            entrada3:  '30 dias',
            entrada4:  '60 dias',
            entrada5:  '90 dias',
            entrada6:  '120 dias',
            entrada7:  '150 dias',
            entrada8:  '180 dias',
            entrada9:  'Parcelas Mensais',
            entrada10: 'Parcelas Semestrais',
            entrada11: 'Chaves'
        };

        const GRUPOS_ETAPAS = [
            { titulo: 'SINAL / ENTRADA',            etapas: ['entrada1', 'entrada2'] },
            { titulo: 'PAGAMENTOS DE CURTO PRAZO',  etapas: ['entrada3', 'entrada4', 'entrada5', 'entrada6', 'entrada7', 'entrada8'] },
            { titulo: 'PARCELAS PERIODICAS',        etapas: ['entrada9', 'entrada10'] },
            { titulo: 'PARCELA FINAL / CHAVES',     etapas: ['entrada11'] }
        ];

        // ============================================================
        // 2. CONFIGURAÇÃO INICIAL
        // ============================================================
        const dataInput = document.getElementById('data');
        const hoje = new Date();
        dataInput.value = hoje.toISOString().substr(0, 10);
        document.getElementById('currentDate').textContent = hoje.toLocaleDateString('pt-BR');

        // ============================================================
        // 3. FUNÇÕES DE FORMATAÇÃO
        // ============================================================
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

        // Parser tolerante para "1.234,56" / "70.5" / "70,5" / "70"
        function parseNumeroAproximado(valor) {
            if (!valor) return 0;
            let str = String(valor).trim();
            if (!str) return 0;
            if (str.includes(',')) {
                str = str.replace(/\./g, '').replace(',', '.');
            }
            return parseFloat(str) || 0;
        }

        // Alinhamento de labels (padEnd + ':')
        function padLabel(label, minWidth = 22) {
            if (label.length >= minWidth) return label + ': ';
            return label.padEnd(minWidth, '.') + ': ';
        }

        // ============================================================
        // 4. CONFIGURAÇÃO DOS LISTENERS DE INPUT
        // ============================================================
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

        // ============================================================
        // 5. CÁLCULOS POR LINHA
        // ============================================================
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

        // ============================================================
        // 6. FUNÇÃO PRINCIPAL DE CÁLCULO
        // ============================================================
        function calcularTotais() {
            const valorUnidade = parseMoeda(document.getElementById('valorUnidade').value);
            const detalhesTextarea = document.getElementById('detalhesEtapas');

            // ---------- Estado vazio: sem valor de unidade ----------
            if (valorUnidade <= 0) {
                document.getElementById('totalPago').value          = 'R$ 0,00';
                document.getElementById('percentualTotal').value    = '0,00%';
                document.getElementById('saldoDevedor').value       = 'R$ 0,00';
                document.getElementById('saldoPercentual').value    = '0,00%';
                document.getElementById('valorUnidadeResult').value = 'R$ 0,00';
                if (detalhesTextarea) {
                    detalhesTextarea.value =
                        '============================================================\n' +
                        '  SIMULACAO DE PAGAMENTO - SUNNYIMOB\n' +
                        '============================================================\n\n' +
                        '  [!] Informe o valor da unidade para gerar o detalhamento\n' +
                        '      completo da simulacao.\n';
                }
                return;
            }

            // ---------- Coletar informações base ----------
            const infoBase = obterInformacoesBase();
            const m2       = parseNumeroAproximado(infoBase.m2);
            const valorM2  = m2 > 0 ? valorUnidade / m2 : 0;

            // ---------- Varrer etapas ----------
            const etapasPreenchidas = [];
            let totalPago     = 0;
            let totalParcelas = 0;
            let totalEntrada  = 0;
            let totalChaves   = 0;
            let parcelaMensalUnitaria = 0;
            let qtdMensais    = 0;

            document.querySelectorAll('.payment-row').forEach(row => {
                const qtdInput   = row.querySelector('.qtd');
                const valorInput = row.querySelector('.valor');
                const pctInput   = row.querySelector('.percentual');
                if (!qtdInput || !valorInput) return;

                const key   = qtdInput.getAttribute('data-etapa') || '';
                const qtd   = parseFloat(qtdInput.value) || 0;
                const valor = parseMoeda(valorInput.value);
                const total = qtd * valor;
                const pct   = (total / valorUnidade) * 100;

                if (pctInput) pctInput.value = toPercentual(pct);

                if (qtd > 0 && valor > 0) {
                    etapasPreenchidas.push({
                        key,
                        nome: NOMES_ETAPAS[key] || 'Etapa',
                        qtd,
                        valor,
                        total,
                        pct
                    });
                    totalPago     += total;
                    totalParcelas += qtd;

                    if (key === 'entrada1' || key === 'entrada2') totalEntrada += total;
                    if (key === 'entrada11')                      totalChaves  += total;
                    if (key === 'entrada9') {
                        parcelaMensalUnitaria = valor;
                        qtdMensais = qtd;
                    }
                }
            });

            const percentualTotal = (totalPago / valorUnidade) * 100;
            const saldoDevedor    = valorUnidade - totalPago;
            const saldoPercentual = 100 - percentualTotal;
            const pctEntrada      = (totalEntrada / valorUnidade) * 100;

            // ---------- Montar texto ----------
            const L   = [];
            const s1  = '-'.repeat(56);
            const s2  = '='.repeat(56);
            const s3  = '.'.repeat(30);
            const now = new Date();
            const timestamp = now.toLocaleString('pt-BR');

            L.push(s2);
            L.push('  SIMULACAO DE PAGAMENTO - SUNNYIMOB');
            L.push('  Gerado em ' + timestamp);
            L.push(s2);
            L.push('');

            // ============ 1. DADOS DO IMOVEL ============
            L.push('>> DADOS DO IMOVEL');
            L.push(s1);
            if (infoBase.empreendimento) L.push(padLabel('Empreendimento')    + infoBase.empreendimento);
            if (infoBase.unidade)        L.push(padLabel('Unidade')            + infoBase.unidade);
            if (m2 > 0)                  L.push(padLabel('Area total')         + m2.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' m2');
            L.push(padLabel('Valor do imovel')  + 'R$ ' + toMoeda(valorUnidade));
            if (valorM2 > 0)             L.push(padLabel('Valor por m2')       + 'R$ ' + toMoeda(valorM2) + '/m2');
            if (infoBase.cliente)        L.push(padLabel('Cliente')            + infoBase.cliente);
            if (infoBase.corretor)       L.push(padLabel('Corretor')           + infoBase.corretor);
            if (infoBase.dataFormatada)  L.push(padLabel('Data')               + infoBase.dataFormatada);
            L.push('');

            // ============ 2. RESUMO DO PLANO ============
            L.push('>> RESUMO DO PLANO');
            L.push(s1);
            L.push(padLabel('Etapas preenchidas') + etapasPreenchidas.length + ' de 11');
            L.push(padLabel('Total de parcelas')  + totalParcelas + ' parcela(s)');
            L.push(padLabel('Entrada inicial')    + 'R$ ' + toMoeda(totalEntrada) + '  (' + toPercentual(pctEntrada) + ')');
            L.push(padLabel('Total a pagar')      + 'R$ ' + toMoeda(totalPago)    + '  (' + toPercentual(percentualTotal) + ')');
            L.push(padLabel('Saldo devedor')      + 'R$ ' + toMoeda(saldoDevedor) + '  (' + toPercentual(saldoPercentual) + ')');
            L.push('');

            // ============ 3. DESTAQUES ============
            const destaques = [];
            if (totalEntrada > 0) {
                destaques.push('Entrada inicial (Sinal + Ato) : R$ ' + toMoeda(totalEntrada) + ' (' + toPercentual(pctEntrada) + ')');
            }
            if (qtdMensais > 0) {
                destaques.push('Parcelas mensais              : ' + qtdMensais + 'x de R$ ' + toMoeda(parcelaMensalUnitaria) + ' = R$ ' + toMoeda(parcelaMensalUnitaria * qtdMensais));
            }
            if (totalChaves > 0) {
                destaques.push('Parcela de chaves             : R$ ' + toMoeda(totalChaves) + ' (' + toPercentual((totalChaves / valorUnidade) * 100) + ')');
            }
            if (saldoDevedor > 0) {
                destaques.push('Saldo devedor                 : R$ ' + toMoeda(saldoDevedor) + ' (' + toPercentual(saldoPercentual) + ')');
            }

            if (destaques.length > 0) {
                L.push('>> DESTAQUES');
                L.push(s1);
                destaques.forEach(d => L.push('  ' + d));
                L.push('');
            }

            // ============ 4. DETALHAMENTO POR GRUPO ============
            L.push('>> DETALHAMENTO POR ETAPA');
            L.push(s1);

            if (etapasPreenchidas.length === 0) {
                L.push('');
                L.push('  [!] Nenhuma parcela registrada nesta simulacao.');
                L.push('');
            } else {
                let numero = 0;
                GRUPOS_ETAPAS.forEach(grupo => {
                    const doGrupo = etapasPreenchidas.filter(e => grupo.etapas.includes(e.key));
                    if (doGrupo.length === 0) return;

                    L.push('');
                    L.push('  ' + grupo.titulo);
                    L.push('  ' + s3);

                    let subtotal = 0;
                    doGrupo.forEach(e => {
                        numero++;
                        subtotal += e.total;
                        const numStr = String(numero).padStart(2, '0');
                        L.push('  ' + numStr + '. ' + e.nome);
                        if (e.qtd > 1) {
                            L.push('      ' + e.qtd + 'x de R$ ' + toMoeda(e.valor) + '  =  R$ ' + toMoeda(e.total) + '  (' + toPercentual(e.pct) + ')');
                        } else {
                            L.push('      1x R$ ' + toMoeda(e.valor) + '  (' + toPercentual(e.pct) + ')');
                        }
                    });

                    const pctGrupo = (subtotal / valorUnidade) * 100;
                    L.push('  -- Subtotal ' + grupo.titulo.toLowerCase() + ': R$ ' + toMoeda(subtotal) + ' (' + toPercentual(pctGrupo) + ')');
                });
            }
            L.push('');

            // ============ 5. RESUMO FINANCEIRO FINAL ============
            L.push(s2);
            L.push('  RESUMO FINANCEIRO');
            L.push(s2);
            L.push(padLabel('Valor total do imovel') + 'R$ ' + toMoeda(valorUnidade) + '  (100,00%)');
            L.push(padLabel('Total pago')            + 'R$ ' + toMoeda(totalPago)    + '  (' + toPercentual(percentualTotal) + ')');
            L.push(padLabel('Saldo devedor')         + 'R$ ' + toMoeda(saldoDevedor) + '  (' + toPercentual(saldoPercentual) + ')');
            L.push(s2);
            L.push('');
            L.push('SunnyImob - Sistema de Gestao Imobiliaria');
            L.push('Documento gerado em ' + timestamp);

            // ---------- Atualizar campos na tela ----------
            document.getElementById('totalPago').value          = 'R$ ' + toMoeda(totalPago);
            document.getElementById('percentualTotal').value    = toPercentual(percentualTotal);
            document.getElementById('saldoDevedor').value       = 'R$ ' + toMoeda(saldoDevedor);
            document.getElementById('saldoPercentual').value    = toPercentual(saldoPercentual);
            document.getElementById('valorUnidadeResult').value = 'R$ ' + toMoeda(valorUnidade);

            if (detalhesTextarea) {
                detalhesTextarea.value = L.join('\n');
            }
        }

        // ============================================================
        // 7. INFORMAÇÕES BASE
        // ============================================================
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

        // ============================================================
        // 8. CAIXA DE MENSAGEM
        // ============================================================
        function showMessageBox(message, type = 'info') {
            const messageBox = document.getElementById('customMessageBox');
            const messageText = document.getElementById('customMessageBoxText');
            const closeButton = document.getElementById('customMessageBoxClose');
            if (!messageBox || !messageText || !closeButton) {
                console.warn('Caixa de mensagem não encontrada:', message);
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

        // ============================================================
        // 9. COPIAR DETALHAMENTO + ENVIO POR E-MAIL
        // ============================================================
        document.getElementById('copiarDetalhes').addEventListener('click', function() {
            const textarea = document.getElementById('detalhesEtapas');
            if (!textarea || textarea.value.trim() === '') {
                showMessageBox('Nenhum conteúdo para copiar.', 'error');
                return;
            }

            const conteudo = textarea.value;

            // Tenta Clipboard API, com fallback
            const copiar = () => {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(conteudo);
                }
                textarea.select();
                textarea.setSelectionRange(0, 99999);
                document.execCommand('copy');
                return Promise.resolve();
            };

            copiar()
                .then(() => {
                    return enviarConteudoPorEmail(conteudo)
                        .then(() => {
                            showMessageBox('Detalhamento copiado e registro enviado com sucesso!', 'success');
                        })
                        .catch((error) => {
                            showMessageBox('Detalhamento copiado, mas o registro não foi enviado. Erro: ' + error.message, 'error');
                            console.error('Erro ao enviar conteúdo por email:', error);
                        });
                })
                .catch((err) => {
                    console.error('Erro ao copiar:', err);
                    showMessageBox('Não foi possível copiar. Selecione o texto manualmente.', 'error');
                });
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
                    else reject(new Error(data.mensagem || 'Erro desconhecido'));
                })
                .catch(error => {
                    console.error("Erro no envio:", error);
                    reject(error);
                });
            });
        }

        // ============================================================
        // 10. INICIALIZAÇÃO
        // ============================================================
        configurarInputs();
        calcularTotais();

        // Recalcula ao mudar o empreendimento
        document.getElementById('empreendimento').addEventListener('change', function() {
            calcularTotais();
        });

        // Recalcula ao mudar dados que afetam o cabeçalho
        ['unidade', 'm2', 'cliente', 'corretor', 'data'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', calcularTotais);
            document.getElementById(id)?.addEventListener('change', calcularTotais);
        });

        // Select2
        $(document).ready(function () {
            $('#empreendimento').select2({
                placeholder: "Selecione um empreendimento...",
                allowClear: true,
                width: '100%'
            });

            // Atualiza o detalhamento quando o select2 muda
            $('#empreendimento').on('change', calcularTotais);
        });
    });
    </script>

    <script>
    // ============================================================
    // SUBMIT VIA FETCH
    // ============================================================
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
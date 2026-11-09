<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php'; // <-- TEMA

// ============================================================
// CONEXÃO — fallback robusto
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// ============================================================
// TEMA SEGURO
// ============================================================
$themeDefaults = [
    'primary' => '#800020', 'primary_dark' => '#4B0C1D',
    'secondary' => '#6c757d', 'accent' => '#ffc107',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ============================================================
// PROCESSAMENTO POST
// ============================================================
$erroMsg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $NomeEmpresa = trim($_POST['NomeEmpresa'] ?? '');
    $Descricao   = trim($_POST['Descricao']   ?? '');
    $CNPJ        = trim($_POST['CNPJ']        ?? '');
    $Endereco    = trim($_POST['Endereco']    ?? '');
    $Cidade      = trim($_POST['Cidade']      ?? '');
    $Bairro      = trim($_POST['Bairro']      ?? '');
    $CEP         = trim($_POST['CEP']         ?? '');
    $Contato     = trim($_POST['Contato']     ?? '');
    $Telefone    = trim($_POST['Telefone']    ?? '');
    $Email       = trim($_POST['Email']       ?? '');
    $Obs         = trim($_POST['Obs']         ?? '');

    // Mapa campo => valor (mantém a lógica dinâmica do INSERT original)
    $mapaCampos = [
        'NomeEmpresa' => $NomeEmpresa,
        'Descricao'   => $Descricao,
        'CNPJ'        => $CNPJ,
        'Endereco'    => $Endereco,
        'Cidade'      => $Cidade,
        'Bairro'      => $Bairro,
        'CEP'         => $CEP,
        'Contato'     => $Contato,
        'Telefone'    => $Telefone,
        'Email'       => $Email,
        'Obs'         => $Obs,
    ];

    $campos = [];
    $valores = [];
    $params = [];

    foreach ($mapaCampos as $nomeCampo => $valor) {
        if ($valor !== '') {
            $campos[]  = $nomeCampo;
            $valores[] = '?';
            $params[]  = $valor;
        }
    }

    if (!empty($campos)) {
        $sql = "INSERT INTO empresa (" . implode(',', $campos) . ") VALUES (" . implode(',', $valores) . ")";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            header("Location: gestao_empresa_list2.php");
            exit();
        } catch (PDOException $e) {
            error_log("[EMPRESA_CREATE] " . $e->getMessage());
            $erroMsg = "Erro ao salvar: " . $e->getMessage();
        }
    } else {
        $erroMsg = "Nenhum campo foi preenchido. A inserção foi cancelada.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Empresa | SunnyImob</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --primary-dark: <?= $themeSafe['primary_dark'] ?>;
            --secondary: <?= $themeSafe['secondary'] ?>;
            --accent: <?= $themeSafe['accent'] ?>;
            --bg-body: <?= $themeSafe['bg_body'] ?>;
            --text-color: <?= $themeSafe['text_color'] ?>;
            --card-bg: <?= $themeSafe['card_bg'] ?>;
            --shadow-color: <?= $themeSafe['shadow_color'] ?>;
            --btn-bg: <?= $themeSafe['btn_bg'] ?>;
            --btn-hover: <?= $themeSafe['btn_hover'] ?>;
            --btn-text: <?= $themeSafe['btn_text'] ?>;

            --radius: 12px;
            --radius-sm: 8px;
            --border: 2.5px solid var(--shadow-color);
            --shadow-sm: 3px 3px 0 var(--shadow-color);
            --shadow-md: 4px 4px 0 var(--shadow-color);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }

        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            padding: 1.5rem;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .app-wrap { max-width: 1320px; margin: 0 auto; }

        /* ================= HEADER ================= */
        .app-header {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 1.15rem 1.6rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .header-left { display: flex; align-items: center; gap: 1rem; min-width: 0; }
        .header-icon {
            width: 52px; height: 52px;
            border: var(--border);
            border-radius: var(--radius-sm);
            background: var(--primary);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }
        .app-header h1 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 900;
            color: var(--text-color);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .app-header p {
            margin: 0.15rem 0 0;
            color: #6b7280;
            font-size: 0.78rem;
            font-weight: 500;
        }
        .header-right { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }

        /* ================= BOTÕES ================= */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.55rem 1.1rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            font-weight: 800;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            background: var(--btn-bg);
            color: var(--btn-text);
            font-family: inherit;
            white-space: nowrap;
        }
        .btn:hover { transform: translate(-2px, -2px); box-shadow: var(--shadow-md); }
        .btn:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 var(--shadow-color); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #fff; color: var(--text-color); }
        .btn-secondary:hover { background: #f3f4f6; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }

        /* ================= ALERTA ================= */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.9rem 1.1rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.25rem;
            font-weight: 600;
        }
        .alert-danger { background: #fee2e2; color: #7f1d1d; border-color: #991b1b; box-shadow: 3px 3px 0 #991b1b; }
        .alert i { font-size: 1.15rem; margin-top: 1px; flex-shrink: 0; }
        .alert-body { flex: 1; }

        /* ================= LAYOUT ================= */
        .form-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .form-layout { grid-template-columns: 1fr; }
        }
        .form-main { min-width: 0; }

        /* ================= SEÇÕES ================= */
        .section {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 1.25rem;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        .section:hover { box-shadow: 5px 5px 0 var(--shadow-color); }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.8rem 1.2rem;
            background: var(--primary);
            color: #fff;
            border-bottom: var(--border);
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .section-header h2 {
            margin: 0;
            font-size: inherit;
            font-weight: inherit;
            color: inherit;
            flex: 1;
        }
        .section-header i { font-size: 0.95rem; opacity: 0.95; }
        .section-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #fff;
            color: var(--primary);
            font-size: 0.72rem;
            font-weight: 900;
            border: 2px solid var(--shadow-color);
            flex-shrink: 0;
        }
        .section-body { padding: 1.25rem 1.4rem; }

        /* ================= GRID INTERNO ================= */
        .row { display: grid; gap: 1rem; margin-bottom: 1rem; }
        .row:last-child { margin-bottom: 0; }
        .row.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .row.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .row.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 720px) {
            .row.cols-2, .row.cols-3, .row.cols-4 { grid-template-columns: 1fr; }
        }

        /* ================= CAMPOS ================= */
        .field { min-width: 0; }
        .field label {
            display: block;
            font-weight: 700;
            font-size: 0.7rem;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4b5563;
        }
        .field label .req { color: #ef4444; margin-left: 2px; }

        .field input[type="text"],
        .field input[type="email"],
        .field textarea {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--text-color);
            font-size: 0.9rem;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.15s;
        }
        .field input:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 3px 3px 0 var(--shadow-color);
        }
        .field input::placeholder,
        .field textarea::placeholder { color: #a3aab4; font-weight: 400; }
        .field textarea { resize: vertical; min-height: 78px; }

        /* ================= INPUT GROUP ================= */
        .input-group {
            display: flex;
            align-items: stretch;
            border: var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: #fff;
            transition: all 0.15s;
        }
        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 3px 3px 0 var(--shadow-color);
        }
        .input-group input {
            flex: 1;
            min-width: 0;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            background: transparent;
            padding: 0.65rem 0.9rem;
            font-size: 0.9rem;
            font-weight: 500;
            font-family: inherit;
            color: var(--text-color);
            outline: none;
        }
        .input-group .prefix {
            display: flex;
            align-items: center;
            padding: 0 0.85rem;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* ================= SIDEBAR PREVIEW ================= */
        .form-summary {
            min-width: 0;
            position: sticky;
            top: 1.5rem;
        }
        .summary-card {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .summary-header {
            background: var(--accent);
            color: var(--shadow-color);
            border-bottom: var(--border);
            padding: 0.95rem 1.2rem;
            font-weight: 900;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .summary-body { padding: 1.15rem; }

        .avatar-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 0 1.15rem;
            border-bottom: 2px dashed #e5e7eb;
            margin-bottom: 1.15rem;
        }
        .avatar-square {
            width: 72px; height: 72px;
            border-radius: 14px;
            background: var(--primary);
            color: #fff;
            border: var(--border);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 900;
            margin-bottom: 0.7rem;
            text-transform: uppercase;
        }
        .avatar-name {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--text-color);
            text-align: center;
            line-height: 1.2;
            word-break: break-word;
        }
        .avatar-sub {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.2rem;
            font-weight: 500;
            text-align: center;
        }

        .preview-field { margin-bottom: 0.85rem; }
        .preview-field:last-child { margin-bottom: 0; }
        .preview-label {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .preview-label i { color: var(--primary); }
        .preview-value {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-color);
            padding: 0.5rem 0.7rem;
            background: #f8fafc;
            border: 1.5px solid #e5e7eb;
            border-radius: var(--radius-sm);
            word-break: break-word;
            line-height: 1.35;
        }
        .preview-value.mono {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
        }
        .preview-value.empty {
            color: #94a3b8;
            font-style: italic;
            font-weight: 500;
        }

        .summary-divider {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0.9rem 0 0.75rem;
            color: #94a3b8;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .summary-divider::before,
        .summary-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .completion-track {
            height: 8px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
            border: 1.5px solid var(--shadow-color);
            margin-top: 0.4rem;
        }
        .completion-bar {
            height: 100%;
            width: 0;
            transition: width 0.25s, background 0.25s;
            background: #dc2626;
        }

        /* ================= AÇÃO INFERIOR ================= */
        .action-bar {
            display: flex;
            gap: 0.75rem;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px dashed #cbd5e0;
        }
        .action-bar-right { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        /* ================= HELPER ================= */
        .helper-text {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-top: 0.4rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* ================= RESPONSIVO ================= */
        @media (max-width: 640px) {
            body { padding: 0.75rem; }
            .app-header { padding: 1rem 1.15rem; }
            .app-header h1 { font-size: 1.15rem; }
            .header-icon { width: 44px; height: 44px; font-size: 1.15rem; }
            .section-body { padding: 1rem; }
            .header-right { width: 100%; justify-content: flex-end; }
        }
    </style>
</head>
<body>

<div class="app-wrap">

    <!-- ================= HEADER ================= -->
    <header class="app-header">
        <div class="header-left">
            <div class="header-icon"><i class="fas fa-building"></i></div>
            <div>
                <h1>Nova Empresa</h1>
                <p>Cadastro de empresa no sistema</p>
            </div>
        </div>
        <div class="header-right">
            <a href="gestao_empresa_list2.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> Lista
            </a>
            <a href="gestao_empresa_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </header>

    <?php if ($erroMsg): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div class="alert-body"><?= htmlspecialchars($erroMsg) ?></div>
    </div>
    <?php endif; ?>

    <!-- ================= FORM ================= -->
    <form method="post" action="gestao_empresa_create.php" class="form-layout" id="formEmpresa">

        <!-- ========== COLUNA ESQUERDA ========== -->
        <main class="form-main">

            <!-- 1 · IDENTIFICAÇÃO -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">1</span>
                    <i class="fas fa-tag"></i>
                    <h2>Identificação</h2>
                </div>
                <div class="section-body">
                    <div class="row cols-2">
                        <div class="field">
                            <label for="NomeEmpresa">Nome da Empresa <span class="req">*</span></label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-building"></i></span>
                                <input type="text" id="NomeEmpresa" name="NomeEmpresa" required
                                       placeholder="Ex.: Sunny Imóveis Ltda"
                                       autocomplete="organization">
                            </div>
                        </div>
                        <div class="field">
                            <label for="CNPJ">CNPJ</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-id-card"></i></span>
                                <input type="text" id="CNPJ" name="CNPJ"
                                       placeholder="00.000.000/0000-00">
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label for="Descricao">Descrição</label>
                        <textarea id="Descricao" name="Descricao" rows="2"
                                  placeholder="Breve descrição da empresa (ramo de atuação, etc.)"></textarea>
                    </div>
                </div>
            </section>

            <!-- 2 · ENDEREÇO -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">2</span>
                    <i class="fas fa-map-marker-alt"></i>
                    <h2>Endereço</h2>
                </div>
                <div class="section-body">
                    <div class="row cols-2">
                        <div class="field">
                            <label for="Endereco">Logradouro</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-map-pin"></i></span>
                                <input type="text" id="Endereco" name="Endereco"
                                       placeholder="Rua, avenida, nº, complemento">
                            </div>
                        </div>
                        <div class="field">
                            <label for="Bairro">Bairro</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-city"></i></span>
                                <input type="text" id="Bairro" name="Bairro"
                                       placeholder="Ex.: Boa Viagem">
                            </div>
                        </div>
                    </div>
                    <div class="row cols-2">
                        <div class="field">
                            <label for="Cidade">Cidade</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-map"></i></span>
                                <input type="text" id="Cidade" name="Cidade"
                                       placeholder="Ex.: Recife">
                            </div>
                        </div>
                        <div class="field">
                            <label for="CEP">CEP</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-mail-bulk"></i></span>
                                <input type="text" id="CEP" name="CEP"
                                       placeholder="00000-000">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 3 · CONTATO -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">3</span>
                    <i class="fas fa-address-book"></i>
                    <h2>Contato</h2>
                </div>
                <div class="section-body">
                    <div class="row cols-3">
                        <div class="field">
                            <label for="Contato">Pessoa de Contato</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-user"></i></span>
                                <input type="text" id="Contato" name="Contato"
                                       placeholder="Nome do responsável">
                            </div>
                        </div>
                        <div class="field">
                            <label for="Telefone">Telefone</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-phone"></i></span>
                                <input type="text" id="Telefone" name="Telefone"
                                       placeholder="(00) 00000-0000">
                            </div>
                        </div>
                        <div class="field">
                            <label for="Email">E-mail</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-envelope"></i></span>
                                <input type="email" id="Email" name="Email"
                                       placeholder="contato@empresa.com.br"
                                       autocomplete="email">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4 · OBSERVAÇÕES -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">4</span>
                    <i class="fas fa-comment-alt"></i>
                    <h2>Observações</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label for="Obs">Notas adicionais</label>
                        <textarea id="Obs" name="Obs" rows="3"
                                  placeholder="Informações complementares sobre a empresa…"></textarea>
                        <div class="helper-text">
                            <i class="fas fa-info-circle"></i>
                            Campos opcionais — preencha apenas o que desejar
                        </div>
                    </div>
                </div>
            </section>

            <!-- AÇÕES -->
            <div class="action-bar">
                <button type="button" class="btn btn-secondary" onclick="limparForm()">
                    <i class="fas fa-eraser"></i> Limpar
                </button>
                <div class="action-bar-right">
                    <a href="gestao_empresa_list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Empresa
                    </button>
                </div>
            </div>

        </main>

        <!-- ========== COLUNA DIREITA (PREVIEW) ========== -->
        <aside class="form-summary">
            <div class="summary-card">
                <div class="summary-header">
                    <i class="fas fa-eye"></i>
                    <span>Pré-visualização</span>
                </div>
                <div class="summary-body">

                    <div class="avatar-preview">
                        <div class="avatar-square" id="avatarInitial">?</div>
                        <div class="avatar-name" id="previewNome">Nome da Empresa</div>
                        <div class="avatar-sub" id="previewCnpj"></div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-map-marker-alt"></i> Localização</div>
                        <div class="preview-value empty" id="previewLocal">— cidade não informada —</div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-phone"></i> Contato</div>
                        <div class="preview-value empty" id="previewContato">— não informado —</div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-envelope"></i> E-mail</div>
                        <div class="preview-value empty" id="previewEmail">— não informado —</div>
                    </div>

                    <div class="summary-divider"><span>Preenchimento</span></div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-tasks"></i> Progresso</div>
                        <div class="completion-track">
                            <div class="completion-bar" id="completionBar"></div>
                        </div>
                        <div class="helper-text" style="margin-top:0.5rem;">
                            <span id="completionPct">0%</span> dos campos preenchidos
                        </div>
                    </div>

                </div>
            </div>
        </aside>

    </form>
</div>

<!-- ================= SCRIPTS ================= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script>
$(document).ready(function () {
    // Máscaras (mantidas)
    $('#CNPJ').mask('00.000.000/0000-00');
    $('#CEP').mask('00000-000');
    $('#Telefone').mask('(00) 00000-0000');

    // ============================================================
    // PREVIEW EM TEMPO REAL
    // ============================================================
    function fmt(v) { return (v || '').trim(); }

    function atualizarPreview() {
        const nome     = fmt($('#NomeEmpresa').val());
        const cnpj     = fmt($('#CNPJ').val());
        const cidade   = fmt($('#Cidade').val());
        const bairro   = fmt($('#Bairro').val());
        const contato  = fmt($('#Contato').val());
        const telefone = fmt($('#Telefone').val());
        const email    = fmt($('#Email').val());

        // Avatar
        const av = document.getElementById('avatarInitial');
        av.textContent = nome ? nome.charAt(0).toUpperCase() : '?';

        // Nome
        const elNome = document.getElementById('previewNome');
        if (nome) {
            elNome.textContent = nome;
            elNome.style.color = '';
            elNome.style.fontStyle = '';
        } else {
            elNome.textContent = 'Nome da Empresa';
            elNome.style.color = '#94a3b8';
            elNome.style.fontStyle = 'italic';
        }

        // CNPJ
        const elCnpj = document.getElementById('previewCnpj');
        elCnpj.textContent = cnpj ? 'CNPJ ' + cnpj : '';

        // Localização
        const elLoc = document.getElementById('previewLocal');
        const partesLoc = [bairro, cidade].filter(Boolean);
        if (partesLoc.length) {
            elLoc.textContent = partesLoc.join(' · ');
            elLoc.classList.remove('empty');
        } else {
            elLoc.textContent = '— cidade não informada —';
            elLoc.classList.add('empty');
        }

        // Contato
        const elCont = document.getElementById('previewContato');
        const partesCont = [contato, telefone].filter(Boolean);
        if (partesCont.length) {
            elCont.textContent = partesCont.join(' · ');
            elCont.classList.remove('empty');
        } else {
            elCont.textContent = '— não informado —';
            elCont.classList.add('empty');
        }

        // Email
        const elEmail = document.getElementById('previewEmail');
        if (email) {
            elEmail.textContent = email;
            elEmail.classList.remove('empty');
        } else {
            elEmail.textContent = '— não informado —';
            elEmail.classList.add('empty');
        }

        // Progresso
        const campos = [
            '#NomeEmpresa','#Descricao','#CNPJ','#Endereco','#Cidade',
            '#Bairro','#CEP','#Contato','#Telefone','#Email','#Obs'
        ];
        let preenchidos = 0;
        campos.forEach(function(sel) {
            if (fmt($(sel).val())) preenchidos++;
        });
        const pct = Math.round((preenchidos / campos.length) * 100);

        const bar = document.getElementById('completionBar');
        bar.style.width = pct + '%';
        if (pct < 30) bar.style.background = '#dc2626';
        else if (pct < 60) bar.style.background = '#f59e0b';
        else if (pct < 90) bar.style.background = '#06b6d4';
        else bar.style.background = '#16a34a';

        document.getElementById('completionPct').textContent = pct + '%';
    }

    // Bind em todos os campos
    $('#formEmpresa').on('input change', 'input, textarea', atualizarPreview);

    // Inicializa
    atualizarPreview();

    // ============================================================
    // LIMPAR FORMULÁRIO
    // ============================================================
    window.limparForm = function () {
        if (!confirm('Limpar todos os campos preenchidos?')) return;
        document.getElementById('formEmpresa').reset();
        atualizarPreview();
    };
});
</script>

</body>
</html>
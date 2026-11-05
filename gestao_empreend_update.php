<?php
// Inclui a conexão PDO
require_once 'conn.php';

// ✅ CARREGA O TEMA
require_once 'theme_config.php';

session_start();

// Função para converter valor de checkbox
function getCheckboxValue($valor) {
    return ($valor == 1) ? 1 : 0;
}

// Função para registrar histórico de alterações
function registrarHistorico($conn, $idEmpreend, $idEmpresa, $acao) {
    try {
        $userId = $_SESSION['UserId'] ?? 0;
        $usuario = $_SESSION['usuario'] ?? 'Sistema';
        
        $sql = "INSERT INTO histatulog (IdEmpresa, IdEmpreend, IdUser, Usuario, Historico) 
                VALUES (:idEmpresa, :idEmpreend, :idUser, :usuario, :historico)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':idEmpresa' => $idEmpresa,
            ':idEmpreend' => $idEmpreend,
            ':idUser'    => $userId,
            ':usuario'   => $usuario,
            ':historico' => $acao
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar histórico: " . $e->getMessage());
        return false;
    }
}

$sucesso = false;
$idEmpreend = $_REQUEST['id'] ?? '';

if (empty($idEmpreend)) {
    die("<div class='alert alert-danger'>Erro: ID do empreendimento não fornecido.</div>");
}

// --- PROCESSAMENTO DO UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btnAtualizar'])) {
    try {
        $empresaID = $_POST['EmpresaID'] ?? '';
        $nome = strtoupper(trim($_POST['Nome'] ?? 'X'));
        $descricao = strtoupper(trim($_POST['Descricao'] ?? 'X'));
        
        $sql = "UPDATE empreendimento SET 
                Empresa_Id = :emp_id, NomeEmpreendimento = :nome, Descricao = :desc,
                LinkMapa = :mapa, LinkFicha = :ficha, LinkSite = :site,
                LinkVideo = :video, LinkTocca = :tocca, LinkConstrutora = :const,
                LinkTabela = :tab, RespCadastro = :resp, Carteira = :cart,
                Vendido = :vend, PercentualVendido = :perc, EtapaObra = :etapa, DataTabela = :dt
                WHERE Empreend_ID = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':emp_id' => $empresaID, 
            ':nome'   => $nome, 
            ':desc'   => $descricao,
            ':mapa'   => substr(trim($_POST['LinkMapa'] ?? ''), 0, 255),
            ':ficha'  => substr(trim($_POST['LinkFicha'] ?? ''), 0, 255),
            ':site'   => substr(trim($_POST['LinkSite'] ?? ''), 0, 255),
            ':video'  => substr(trim($_POST['LinkVideo'] ?? ''), 0, 255),
            ':tocca'  => substr(trim($_POST['LinkTocca'] ?? ''), 0, 255),
            ':const'  => substr(trim($_POST['LinkConstrutora'] ?? ''), 0, 255),
            ':tab'    => substr(trim($_POST['LinkTabela'] ?? ''), 0, 255),
            ':resp'   => $_POST['RespCadastro'] ?? '',
            ':cart'   => getCheckboxValue($_POST['Carteira'] ?? 0),
            ':vend'   => getCheckboxValue($_POST['Vendido'] ?? 0),
            ':perc'   => $_POST['PercentualVendido'] ?? 0,
            ':etapa'  => $_POST['EtapaObra'] ?? 'Ausente',
            ':dt'     => $_POST['DataTabela'] ?? date('Y-m-d'),
            ':id'     => $idEmpreend
        ]);

        registrarHistorico($conn, $idEmpreend, $empresaID, "Atualização do Cadastro");
        $sucesso = true;
        
    } catch (PDOException $e) {
        die("Erro ao atualizar: " . $e->getMessage());
    }
}

// --- BUSCA DE DADOS ---
try {
    $stmt = $conn->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = ?");
    $stmt->execute([$idEmpreend]);
    $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empreendimento) die("Empreendimento não encontrado.");

    $stmtEmp = $conn->query("SELECT Empresa_Id, NomeEmpresa FROM empresa ORDER BY NomeEmpresa");
    $empresas = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

    $idEmpresaSalva = $empreendimento['Empresa_Id'] ?? $empreendimento['Empresa_ID'] ?? null;
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empreendimento - ID <?= htmlspecialchars($idEmpreend) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* ============================================================ */
        /* VARIÁVEIS DO TEMA                                            */
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

            /* Estados fixos */
            --success: #10b981;
            --danger:  #ef4444;
            --warning: #f59e0b;
            --info:    #3b82f6;

            /* Derivadas do tema (para superfícies) */
            --surface-soft: rgba(0,0,0,0.04);
            --surface-soft-2: rgba(0,0,0,0.08);
            --border-soft: rgba(0,0,0,0.12);
            --primary-soft: color-mix(in srgb, var(--primary) 10%, transparent);

            --radius: 14px;
        }

        /* ============================================================ */
        /* RESET + BASE                                                 */
        /* ============================================================ */
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, var(--surface-soft) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            margin: 0;
            padding: 0;
            padding-bottom: 96px; /* espaço para a action bar */
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================ */
        /* HEADER STICKY (com logo)                                     */
        /* ============================================================ */
        .page-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--primary);
            color: #fff;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 16px rgba(0,0,0,0.18), 0 4px 0 0 var(--shadow-color);
            padding: 12px 0;
        }
        .page-header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .page-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        /* --- LOGO --- */
        .page-header-logo {
            height: 52px;
            width: auto;
            max-width: 150px;
            min-width: 52px;
            object-fit: contain;
            flex-shrink: 0;
            background: #fff;
            border-radius: 10px;
            padding: 5px 8px;
            border: 2px solid rgba(255,255,255,0.5);
            box-shadow: 0 3px 10px rgba(0,0,0,0.18);
            transition: transform 0.15s;
        }
        .page-header-logo:hover { transform: scale(1.03); }

        .page-header-divider {
            width: 1px;
            height: 34px;
            background: rgba(255,255,255,0.3);
            flex-shrink: 0;
            margin: 0 4px;
        }

        .page-header-icon {
            width: 46px;
            height: 46px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .page-header .header-sub {
            margin: 2px 0 0;
            font-size: 0.8rem;
            opacity: 0.85;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .page-header .header-sub strong {
            background: rgba(255,255,255,0.18);
            padding: 1px 8px;
            border-radius: 6px;
            font-weight: 700;
        }
        .btn-header-close {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-header-close:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-1px);
        }

        /* ============================================================ */
        /* CONTAINER                                                    */
        /* ============================================================ */
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        /* ============================================================ */
        /* FORM CARD (seções)                                          */
        /* ============================================================ */
        .form-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            margin-bottom: 20px;
            overflow: hidden;
            border: 3px solid var(--shadow-color);
        }
        .form-card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 22px;
            background: var(--primary);
            color: #fff;
            border-bottom: 3px solid var(--shadow-color);
        }
        .form-card-number {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: rgba(0,0,0,0.25);
            color: #fff;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 2px 2px 0 rgba(0,0,0,0.3);
        }
        .form-card-title {
            flex: 1;
            min-width: 0;
        }
        .form-card-title h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.2px;
        }
        .form-card-title p {
            margin: 2px 0 0;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
        }
        .form-card-body {
            padding: 22px;
        }

        /* ============================================================ */
        /* LABELS + INPUTS                                              */
        /* ============================================================ */
        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-color);
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }
        .field-label .req { color: var(--danger); }
        .field-label i { font-size: 0.85rem; color: var(--primary); }

        .form-control, .form-select {
            border: 1.5px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.92rem;
            font-weight: 500;
            color: var(--text-color);
            background-color: #fff;
            transition: all 0.15s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft), 3px 3px 0 0 var(--shadow-color);
            outline: none;
        }
        .form-control::placeholder { color: #b8bcc4; font-weight: 400; }
        .text-uppercase { text-transform: uppercase; }
        textarea.form-control { resize: vertical; min-height: 80px; }

        /* ============================================================ */
        /* LINK GROUP (input + botão abrir)                             */
        /* ============================================================ */
        .link-group {
            display: flex;
            align-items: stretch;
            border: 1.5px solid var(--shadow-color);
            border-radius: 10px;
            background: #fff;
            transition: all 0.15s;
            overflow: hidden;
        }
        .link-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft), 3px 3px 0 0 var(--shadow-color);
        }
        .link-group .link-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            background: var(--surface-soft);
            color: var(--primary);
            flex-shrink: 0;
            border-right: 1px solid var(--border-soft);
            font-size: 0.92rem;
        }
        .link-group input {
            flex: 1;
            border: none;
            padding: 10px 12px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-color);
            outline: none;
            background: transparent;
            min-width: 0;
        }
        .link-group input::placeholder { color: #b8bcc4; font-weight: 400; }
        .link-group .btn-test {
            border: none;
            background: var(--surface-soft);
            color: var(--text-color);
            border-left: 1px solid var(--border-soft);
            padding: 0 14px;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .link-group .btn-test:hover:not(.disabled) {
            background: var(--primary);
            color: #fff;
        }
        .link-group .btn-test.disabled {
            opacity: 0.35;
            pointer-events: none;
            cursor: not-allowed;
        }

        /* ============================================================ */
        /* SWITCH CARDS (carteira / vendido)                            */
        /* ============================================================ */
        .switch-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            background: var(--surface-soft);
            border: 1.5px solid var(--shadow-color);
            border-radius: 12px;
            transition: all 0.15s;
            cursor: pointer;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .switch-card:hover {
            border-color: var(--primary);
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .switch-card-body {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .switch-card-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: #fff;
            flex-shrink: 0;
            border: 2px solid var(--shadow-color);
        }
        .switch-card-icon.bg-info    { background: var(--info); }
        .switch-card-icon.bg-success { background: var(--success); }
        .switch-card-text { min-width: 0; }
        .switch-card-text strong {
            display: block;
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--text-color);
            line-height: 1.2;
        }
        .switch-card-text small {
            font-size: 0.76rem;
            color: var(--text-color);
            opacity: 0.65;
            font-weight: 500;
        }
        .form-check-switch-custom {
            position: relative;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
        }
        .form-check-switch-custom input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }
        .form-check-switch-custom .slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 999px;
            cursor: pointer;
            transition: background 0.2s;
            border: 2px solid var(--shadow-color);
        }
        .form-check-switch-custom .slider::before {
            content: "";
            position: absolute;
            height: 16px;
            width: 16px;
            left: 2px;
            top: 2px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }
        .form-check-switch-custom input:checked + .slider { background: var(--primary); }
        .form-check-switch-custom input:checked + .slider::before { transform: translateX(22px); }

        /* ============================================================ */
        /* PERCENTUAL (slider + número)                                 */
        /* ============================================================ */
        .percentual-box {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            background: var(--surface-soft);
            border: 1.5px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .percentual-box .form-range {
            flex: 1;
            accent-color: var(--primary);
        }
        .percentual-num {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #fff;
            border: 1.5px solid var(--shadow-color);
            border-radius: 10px;
            padding: 4px 10px;
            flex-shrink: 0;
        }
        .percentual-num input {
            width: 52px;
            border: none;
            outline: none;
            text-align: right;
            font-weight: 800;
            font-size: 1rem;
            color: var(--text-color);
            background: transparent;
        }
        .percentual-num span {
            font-weight: 800;
            color: var(--primary);
            font-size: 0.92rem;
        }

        /* ============================================================ */
        /* ACTION BAR (sticky no rodapé)                                */
        /* ============================================================ */
        .action-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--card-bg);
            border-top: 3px solid var(--shadow-color);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08), 0 -4px 0 0 var(--shadow-color);
            padding: 14px 24px;
            z-index: 40;
        }
        .action-bar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .action-bar-info {
            font-size: 0.82rem;
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .action-bar-info i { color: var(--primary); }
        .action-bar-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-custom {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 12px 26px;
            font-weight: 800;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-custom:hover {
            background: var(--btn-hover);
            color: #fff;
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
        }
        .btn-custom:active {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 0 var(--shadow-color);
        }
        .btn-custom-cancel {
            background: #fff;
            color: var(--text-color);
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 12px 22px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .btn-custom-cancel:hover {
            background: var(--surface-soft);
            transform: translate(-1px, -1px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
        }

        /* ============================================================ */
        /* RESPONSIVO                                                   */
        /* ============================================================ */
        @media (max-width: 768px) {
            .page-header-inner { padding: 0 16px; gap: 10px; }
            .page-header h1 { font-size: 1rem; }
            .page-header .header-sub { font-size: 0.72rem; }
            .page-header-icon { width: 40px; height: 40px; font-size: 16px; }
            .page-header-logo { height: 42px; max-width: 120px; padding: 4px 6px; }
            .page-header-divider { height: 26px; }
            .btn-header-close span { display: none; }
            .btn-header-close { padding: 8px 12px; }
            .page-container { padding: 16px; }
            .form-card-body { padding: 16px; }
            .form-card-header { padding: 14px 16px; }
            .action-bar { padding: 12px 16px; }
            .action-bar-buttons { width: 100%; }
            .action-bar-buttons > * { flex: 1; justify-content: center; }
            .action-bar-info { display: none; }
        }
        @media (max-width: 480px) {
            .page-header-divider { display: none; }
            .page-header-logo { height: 38px; max-width: 100px; }
        }
    </style>
</head>
<body>

<?php if ($sucesso): ?>
<script>
    Swal.fire({
        title: 'Sucesso!',
        text: 'O registro foi salvo com sucesso.',
        icon: 'success',
        confirmButtonText: 'Fechar Aba',
        confirmButtonColor: '<?php echo $theme['primary']; ?>'
    }).then((result) => {
        window.onbeforeunload = null;
        if (result.isConfirmed) { window.close(); }
    });
</script>
<?php endif; ?>

<!-- ============================================================ -->
<!-- HEADER STICKY COM LOGO                                       -->
<!-- ============================================================ -->
<header class="page-header">
    <div class="page-header-inner">
        <div class="page-header-left">

            <!-- 🖼️ Logo do tema (usa o mesmo padrão da mainpage) -->
            <img src="<?php echo (!empty($_SESSION['Conf_ImgLogo'])) ? $_SESSION['Conf_ImgLogo'] : 'logoSunny66a.JPG'; ?>"
                 alt="Logo Empresa"
                 class="page-header-logo">

            <span class="page-header-divider"></span>

            <div class="page-header-icon">
                <i class="fas fa-edit"></i>
            </div>

            <div style="min-width:0;">
                <h1>Editar Empreendimento</h1>
                <p class="header-sub">
                    ID <strong>#<?= htmlspecialchars($idEmpreend) ?></strong>
                    · <?= strtoupper(htmlspecialchars($empreendimento['NomeEmpreendimento'])) ?>
                </p>
            </div>

        </div>
        <button type="button" class="btn-header-close" onclick="tentarFechar()">
            <i class="fas fa-times"></i> <span>Fechar</span>
        </button>
    </div>
</header>

<!-- ============================================================ -->
<!-- FORM                                                          -->
<!-- ============================================================ -->
<main class="page-container">
    <form method="post" id="formEmpreend" novalidate>
        <input type="hidden" name="id" value="<?= htmlspecialchars($idEmpreend) ?>">

        <!-- ================= 1. DADOS PRINCIPAIS ================= -->
        <section class="form-card">
            <div class="form-card-header">
                <div class="form-card-number">1</div>
                <div class="form-card-title">
                    <h2>Dados Principais</h2>
                    <p>Identificação básica do empreendimento</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="field-label" for="EmpresaID">
                            <i class="fas fa-building"></i>
                            Empresa <span class="req">*</span>
                        </label>
                        <select class="form-select" id="EmpresaID" name="EmpresaID" required>
                            <option value="">Selecione uma empresa</option>
                            <?php foreach ($empresas as $empresa): 
                                $selected = (trim($empresa['Empresa_Id']) == trim($idEmpresaSalva)) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($empresa['Empresa_Id']) ?>" <?= $selected ?>>
                                    <?= strtoupper(htmlspecialchars($empresa['NomeEmpresa'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="field-label" for="Nome">
                            <i class="fas fa-signature"></i>
                            Nome do Empreendimento <span class="req">*</span>
                        </label>
                        <input type="text" class="form-control text-uppercase" id="Nome" name="Nome"
                               value="<?= htmlspecialchars($empreendimento['NomeEmpreendimento']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="field-label" for="Descricao">
                            <i class="fas fa-align-left"></i>
                            Descrição
                        </label>
                        <textarea class="form-control text-uppercase" id="Descricao" name="Descricao"
                                  rows="3"><?= htmlspecialchars($empreendimento['Descricao']) ?></textarea>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= 2. LINKS E DOCUMENTOS ================= -->
        <section class="form-card">
            <div class="form-card-header">
                <div class="form-card-number">2</div>
                <div class="form-card-title">
                    <h2>Links e Documentos</h2>
                    <p>URLs com até 255 caracteres · Clique no ícone <i class="fas fa-external-link-alt"></i> para abrir</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <?php
                    $camposLink = [
                        ['LinkMapa',        'Link Mapa',        'fa-map-marker-alt', 'LinkMapa'],
                        ['LinkFicha',       'Link Ficha',       'fa-file-pdf',       'LinkFicha'],
                        ['LinkSite',        'Link Site',        'fa-globe',          'LinkSite'],
                        ['LinkVideo',       'Link Vídeo',       'fa-video',          'LinkVideo'],
                        ['LinkTocca',       'Tocca Drive',      'fa-folder',         'LinkTocca'],
                        ['LinkConstrutora', 'Construtora Drive','fa-hard-hat',       'LinkConstrutora'],
                        ['LinkTabela',      'Link Tabela',      'fa-table',          'LinkTabela'],
                    ];
                    foreach ($camposLink as $campo):
                        $name  = $campo[0];
                        $label = $campo[1];
                        $icon  = $campo[2];
                        $value = htmlspecialchars($empreendimento[$name] ?? '');
                    ?>
                    <div class="col-md-6">
                        <label class="field-label" for="<?= $name ?>">
                            <i class="fas <?= $icon ?>"></i>
                            <?= $label ?>
                        </label>
                        <div class="link-group">
                            <span class="link-icon"><i class="fas <?= $icon ?>"></i></span>
                            <input type="text" id="<?= $name ?>" name="<?= $name ?>"
                                   value="<?= $value ?>"
                                   placeholder="https://…">
                            <a class="btn-test <?= $value ? '' : 'disabled' ?>"
                               id="test_<?= $name ?>"
                               href="<?= $value ?: '#' ?>"
                               target="_blank"
                               rel="noopener"
                               title="Abrir link">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Data da Tabela -->
                    <div class="col-md-6">
                        <label class="field-label" for="DataTabela">
                            <i class="fas fa-calendar-alt"></i>
                            Data da Tabela
                        </label>
                        <input type="date" class="form-control" id="DataTabela" name="DataTabela"
                               value="<?= $empreendimento['DataTabela'] ?>">
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= 3. DISPONIBILIDADE ================= -->
        <section class="form-card">
            <div class="form-card-header">
                <div class="form-card-number">3</div>
                <div class="form-card-title">
                    <h2>Disponibilidade</h2>
                    <p>Status de vendas e etapa da obra</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <!-- Carteira -->
                    <div class="col-md-6">
                        <label class="switch-card" for="Carteira">
                            <div class="switch-card-body">
                                <div class="switch-card-icon bg-info">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <div class="switch-card-text">
                                    <strong>Carteira</strong>
                                    <small>Disponível na carteira de vendas</small>
                                </div>
                            </div>
                            <div class="form-check-switch-custom">
                                <input type="checkbox" id="Carteira" name="Carteira" value="1"
                                       <?= ($empreendimento['Carteira'] == 1) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </div>
                        </label>
                    </div>

                    <!-- Vendido -->
                    <div class="col-md-6">
                        <label class="switch-card" for="Vendido">
                            <div class="switch-card-body">
                                <div class="switch-card-icon bg-success">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="switch-card-text">
                                    <strong>Vendido</strong>
                                    <small>Marcar como 100% vendido</small>
                                </div>
                            </div>
                            <div class="form-check-switch-custom">
                                <input type="checkbox" id="Vendido" name="Vendido" value="1"
                                       <?= ($empreendimento['Vendido'] == 1) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </div>
                        </label>
                    </div>

                    <!-- Percentual -->
                    <div class="col-12">
                        <label class="field-label">
                            <i class="fas fa-chart-line"></i>
                            Percentual Vendido
                        </label>
                        <div class="percentual-box">
                            <input type="range" class="form-range" id="percRange" min="0" max="100" step="1"
                                   value="<?= (int)$empreendimento['PercentualVendido'] ?>">
                            <div class="percentual-num">
                                <input type="number" name="PercentualVendido" id="percNum" min="0" max="100"
                                       value="<?= (int)$empreendimento['PercentualVendido'] ?>">
                                <span>%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Etapa -->
                    <div class="col-12">
                        <label class="field-label" for="EtapaObra">
                            <i class="fas fa-hard-hat"></i>
                            Etapa da Obra
                        </label>
                        <select class="form-select" id="EtapaObra" name="EtapaObra">
                            <?php 
                            $etapas = ['Lançamento', 'Em construção', 'Pronto', 'Entregue', 'Obra parada'];
                            foreach($etapas as $e): ?>
                                <option value="<?= $e ?>" <?= ($empreendimento['EtapaObra'] == $e) ? 'selected' : '' ?>><?= $e ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= 4. RESPONSÁVEL ================= -->
        <section class="form-card">
            <div class="form-card-header">
                <div class="form-card-number">4</div>
                <div class="form-card-title">
                    <h2>Responsável</h2>
                    <p>Quem cadastrou / mantém este registro</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label" for="RespCadastro">
                            <i class="fas fa-user-tie"></i>
                            Responsável pelo Cadastro
                        </label>
                        <input type="text" class="form-control" id="RespCadastro" name="RespCadastro"
                               value="<?= htmlspecialchars($empreendimento['RespCadastro']) ?>">
                    </div>
                </div>
            </div>
        </section>

    </form>
</main>

<!-- ============================================================ -->
<!-- ACTION BAR                                                   -->
<!-- ============================================================ -->
<div class="action-bar">
    <div class="action-bar-inner">
        <div class="action-bar-info">
            <i class="fas fa-info-circle"></i>
            Campos com <span style="color:var(--danger); font-weight:800;">*</span> são obrigatórios
        </div>
        <div class="action-bar-buttons">
            <button type="button" class="btn-custom-cancel" onclick="tentarFechar()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formEmpreend" name="btnAtualizar" class="btn-custom">
                <i class="fas fa-save"></i> Salvar Registro
            </button>
        </div>
    </div>
</div>

<script>
$(function() {
    // ============================================================
    // 1) Sincroniza slider <-> número do percentual
    // ============================================================
    var $percRange = $('#percRange');
    var $percNum   = $('#percNum');
    var $vendido   = $('#Vendido');

    $percRange.on('input', function() { $percNum.val($(this).val()); });
    $percNum.on('input change blur', function() {
        var v = parseInt($(this).val() || 0, 10);
        if (isNaN(v)) v = 0;
        if (v < 0) v = 0;
        if (v > 100) v = 100;
        $percRange.val(v);
    });

    // Auto-marcar/desmarcar "Vendido" conforme percentual
    $percRange.on('input', function() {
        var v = parseInt($(this).val(), 10);
        $vendido.prop('checked', v === 100);
    });

    // ============================================================
    // 2) Botões "abrir link" refletem o valor do input
    // ============================================================
    $('.link-group').each(function() {
        var $input = $(this).find('input');
        var $btn = $(this).find('.btn-test');

        function syncBtn() {
            var v = $.trim($input.val());
            if (v) {
                $btn.attr('href', v).removeClass('disabled');
            } else {
                $btn.attr('href', '#').addClass('disabled');
            }
        }
        $input.on('input', syncBtn);
        syncBtn();
    });

    // ============================================================
    // 3) Detecta alterações e avisa ao sair
    // ============================================================
    var formOriginal = $('#formEmpreend').serialize();
    var permitirSair = false;

    window.addEventListener('beforeunload', function(e) {
        if (permitirSair) return;
        if ($('#formEmpreend').serialize() !== formOriginal) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    $('#formEmpreend').on('submit', function() {
        permitirSair = true;
    });

    window.tentarFechar = function() {
        if ($('#formEmpreend').serialize() !== formOriginal) {
            Swal.fire({
                title: 'Sair sem salvar?',
                text: 'Você tem alterações não salvas. Se fechar agora, elas serão perdidas.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sair mesmo assim',
                cancelButtonText: 'Ficar',
                confirmButtonColor: '<?php echo $theme['primary']; ?>',
                cancelButtonColor: '#6b7280'
            }).then(function(r) {
                if (r.isConfirmed) {
                    permitirSair = true;
                    window.close();
                }
            });
        } else {
            window.close();
        }
    };
});
</script>

<script>
// Fallback em JS puro (caso o jQuery do CDN falhe)
if (typeof jQuery === 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        var range = document.getElementById('percRange');
        var num   = document.getElementById('percNum');
        if (range && num) {
            range.addEventListener('input', function(){ num.value = range.value; });
            num.addEventListener('input', function(){
                var v = parseInt(num.value||0,10);
                if (isNaN(v)) v = 0;
                if (v < 0) v = 0;
                if (v > 100) v = 100;
                range.value = v;
            });
        }
        document.querySelectorAll('.link-group').forEach(function(group) {
            var input = group.querySelector('input');
            var btn = group.querySelector('.btn-test');
            if (!input || !btn) return;
            function sync() {
                var v = (input.value || '').trim();
                if (v) { btn.setAttribute('href', v); btn.classList.remove('disabled'); }
                else { btn.setAttribute('href', '#'); btn.classList.add('disabled'); }
            }
            input.addEventListener('input', sync);
            sync();
        });
    });
    window.tentarFechar = function() { window.close(); };
}
</script>

</body>
</html>
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
    'secondary' => '#6c757d', 'accent' => '#D4AF37',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ============================================================
// VARIÁVEIS DE ESTADO (mantidas do original)
// ============================================================
$mensagemSucesso = "";
$showSuccess = false;
$novoID = null;
$nomeEmpreendimentoCadastrado = "";
$nomeEmpresa = "";
$Localidade = "";
$Nome = "";
$EmpresaID = "";
$emailEnviado = false;

// ============================================================
// PROCESSAMENTO POST (CREATE) — lógica original preservada
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $urlRetorno = $_POST['urlRetorno'] ?? '';
    $EmpresaID  = $_POST['EmpresaID']  ?? '';

    $Nome       = isset($_POST['Nome'])       ? mb_strtoupper(trim($_POST['Nome']), 'UTF-8')       : '';
    $Localidade = isset($_POST['Localidade']) ? mb_strtoupper(trim($_POST['Localidade']), 'UTF-8') : '';

    if (!empty($EmpresaID) && !empty($Nome) && !empty($Localidade)) {
        try {
            $sql = "INSERT INTO empreendimento (Empresa_Id, NomeEmpreendimento, Localidade)
                    VALUES (:empresa_id, :nome, :localidade)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':empresa_id', $EmpresaID, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $Nome, PDO::PARAM_STR);
            $stmt->bindParam(':localidade', $Localidade, PDO::PARAM_STR);
            $stmt->execute();

            $novoID = $db->lastInsertId();
            $nomeEmpreendimentoCadastrado = $Nome;

            // Nome da empresa
            $stmtEmpresa = $db->prepare("SELECT NomeEmpresa FROM empresa WHERE Empresa_Id = :empresa_id");
            $stmtEmpresa->bindParam(':empresa_id', $EmpresaID, PDO::PARAM_INT);
            $stmtEmpresa->execute();
            $empresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);
            $nomeEmpresa = isset($empresa['NomeEmpresa']) ? mb_strtoupper($empresa['NomeEmpresa'], 'UTF-8') : '';

            // ── E-mail (preservado, mas isolado) ──
            try {
                $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
                $usuario = isset($_SESSION['Usuario']) ? strtoupper($_SESSION['Usuario']) : 'DESCONHECIDO';
                $data    = date('d/m/Y');
                $hora    = date('H:i:s');

                $para = "valterpb@gmail.com, valterpb@hotmail.com";
                $de   = "sendmail@sunnyimob.com.br";

                $assunto = "SI-NOVO-EMPREEND-" . $usuario . " - " . $ip . " - " . $data . " - " . $hora;

                $mensagemEmail  = "NOVO EMPREENDIMENTO CADASTRADO NO SISTEMA\r\n";
                $mensagemEmail .= "===========================================\r\n\r\n";
                $mensagemEmail .= "ID do Empreendimento: #" . $novoID . "\r\n";
                $mensagemEmail .= "Nome: " . $Nome . "\r\n";
                $mensagemEmail .= "Empresa: " . $nomeEmpresa . "\r\n";
                $mensagemEmail .= "Localidade: " . $Localidade . "\r\n\r\n";
                $mensagemEmail .= "Cadastrado por: " . $usuario . "\r\n";
                $mensagemEmail .= "Data/Hora: " . $data . " - " . $hora . "\r\n";
                $mensagemEmail .= "IP: " . $ip . "\r\n";

                $headers  = "From: " . $de . "\r\n" .
                            "Reply-To: " . $de . "\r\n" .
                            "Content-Type: text/plain; charset=utf-8\r\n" .
                            "X-Mailer: PHP/" . phpversion();

                $emailEnviado = @mail($para, $assunto, $mensagemEmail, $headers);

                if (!$emailEnviado) {
                    error_log("Falha ao enviar email de novo empreendimento: " . $assunto);
                } else {
                    error_log("Email enviado: Novo empreendimento cadastrado - " . $Nome);
                }
            } catch (Exception $e) {
                error_log("[EMPREEND_EMAIL] " . $e->getMessage());
            }

            $mensagemSucesso = "Empreendimento cadastrado com sucesso!";
            $showSuccess = true;

        } catch (PDOException $e) {
            $mensagemSucesso = "Erro ao cadastrar empreendimento: " . $e->getMessage();
            $showSuccess = false;
            error_log("[EMPREEND_CREATE] " . $e->getMessage());
        }
    } else {
        $mensagemSucesso = "Por favor, preencha todos os campos obrigatórios.";
        $showSuccess = false;
    }
}

// ============================================================
// CARREGA EMPRESAS
// ============================================================
try {
    $empresas = $db->query("SELECT Empresa_Id, NomeEmpresa FROM empresa ORDER BY NomeEmpresa")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar empresas: " . $e->getMessage());
}

$urlAnterior = $_SERVER['HTTP_REFERER'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Empreendimento | SunnyImob</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            --success: #16a34a;
            --danger: #dc2626;
            --info: #3b82f6;
            --warning: #f59e0b;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            padding: 2.5rem 1rem;
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- CARD PRINCIPAL ---------- */
        .card-neo {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
        }
        .card-neo .card-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 3px solid var(--shadow-color);
            padding: 1rem 1.5rem;
            font-weight: 900;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            letter-spacing: -0.01em;
        }
        .card-neo .card-body { padding: 1.8rem; }

        /* ---------- HEADER ICON ---------- */
        .page-header-icon {
            width: 56px; height: 56px;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 4px 4px 0 var(--shadow-color);
            margin-bottom: 1rem;
        }

        /* ---------- FIELDS ---------- */
        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #4b5563;
            margin-bottom: 8px;
        }
        .field-label i { color: var(--primary); font-size: 0.8rem; }
        .field-label .req { color: var(--danger); }

        .form-control, .form-select {
            border: 2.5px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 14px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-color);
            background: #fff;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .form-control::placeholder { color: #b8bcc4; font-weight: 400; }

        /* ---------- BOTÕES ---------- */
        .btn-neo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 10px 22px;
            border: 2.5px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 var(--shadow-color);
            font-weight: 800;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            font-family: inherit;
            background: var(--btn-bg);
            color: var(--btn-text);
        }
        .btn-neo:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 var(--shadow-color); }
        .btn-neo:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 var(--shadow-color); }
        .btn-neo.primary { background: var(--primary); color: #fff; }
        .btn-neo.primary:hover { background: var(--primary-dark); }
        .btn-neo.success { background: var(--success); color: #fff; }
        .btn-neo.success:hover { background: #15803d; }
        .btn-neo.secondary { background: #fff; color: var(--text-color); }
        .btn-neo.secondary:hover { background: #f3f4f6; }
        .btn-neo.sm { padding: 7px 14px; font-size: 0.72rem; }

        /* ---------- ALERTAS ---------- */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            font-weight: 700;
            font-size: 0.88rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            box-shadow: 4px 4px 0 var(--shadow-color);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .alert-neo i { font-size: 1.2rem; margin-top: 1px; flex-shrink: 0; }
        .alert-neo.success { background: #d1fae5; color: #065f46; border-color: #065f46; box-shadow: 4px 4px 0 #065f46; }
        .alert-neo.danger  { background: #fee2e2; color: #7f1d1d; border-color: #991b1b; box-shadow: 4px 4px 0 #991b1b; }
        .alert-neo.info    { background: #dbeafe; color: #1e3a8a; border-color: #1e3a8a; box-shadow: 4px 4px 0 #1e3a8a; }
        .alert-neo.warning { background: #fef3c7; color: #92400e; border-color: #92400e; box-shadow: 4px 4px 0 #92400e; }

        /* ---------- PAINEL DE SUCESSO ---------- */
        .success-panel {
            max-width: 800px;
            margin: 0 auto 1.5rem;
            background: var(--card-bg);
            border: 3px solid var(--success);
            border-radius: 14px;
            box-shadow: 5px 5px 0 var(--success);
            overflow: hidden;
        }
        .success-panel .panel-header {
            background: var(--success);
            color: #fff;
            border-bottom: 3px solid var(--success);
            padding: 0.9rem 1.3rem;
            font-weight: 900;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .success-panel .panel-body { padding: 1.3rem; }

        .info-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.55rem 0;
            border-bottom: 1px dashed #e5e7eb;
            font-size: 0.88rem;
        }
        .info-item:last-child { border-bottom: none; }
        .info-label {
            font-weight: 800;
            min-width: 160px;
            color: #4b5563;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.04em;
            padding-top: 2px;
        }
        .info-value { font-weight: 600; flex: 1; }

        .id-badge {
            display: inline-flex;
            align-items: center;
            background: var(--primary);
            color: #fff;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 900;
            font-size: 0.78rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 var(--shadow-color);
            font-family: 'SFMono-Regular', Consolas, monospace;
        }

        .email-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.75rem;
            border: 2px solid #1e40af;
            margin-top: 0.8rem;
        }

        /* ---------- RESPONSIVO ---------- */
        @media (max-width: 640px) {
            body { padding: 1rem 0.75rem; }
            .card-neo .card-body { padding: 1.2rem; }
            .card-neo .card-header { padding: 0.9rem 1.1rem; font-size: 1rem; }
            .success-panel .panel-body { padding: 1rem; }
            .info-label { min-width: 120px; font-size: 0.68rem; }
            .info-value { font-size: 0.82rem; }
            .btn-neo { width: 100%; }
        }
    </style>
</head>
<body>

<!-- ================= PAINEL DE SUCESSO ================= -->
<?php if ($showSuccess): ?>
<div class="success-panel">
    <div class="panel-header">
        <i class="fas fa-check-circle"></i>
        <span>Cadastro realizado com sucesso</span>
    </div>
    <div class="panel-body">
        <div class="info-item">
            <span class="info-label">ID do Empreendimento</span>
            <span class="info-value"><span class="id-badge">#<?= htmlspecialchars($novoID) ?></span></span>
        </div>
        <div class="info-item">
            <span class="info-label">Nome</span>
            <span class="info-value"><?= htmlspecialchars($nomeEmpreendimentoCadastrado) ?></span>
        </div>
        <?php if (!empty($nomeEmpresa)): ?>
        <div class="info-item">
            <span class="info-label">Empresa</span>
            <span class="info-value"><?= htmlspecialchars($nomeEmpresa) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($Localidade)): ?>
        <div class="info-item">
            <span class="info-label">Localidade</span>
            <span class="info-value"><?= htmlspecialchars($Localidade) ?></span>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <span class="info-label">Data/Hora</span>
            <span class="info-value"><?= date('d/m/Y H:i:s') ?></span>
        </div>

        <?php
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($emailEnviado && $ip !== '127.0.0.1' && $ip !== '::1'):
        ?>
        <div class="email-badge">
            <i class="fas fa-envelope"></i>
            E-mail de notificação enviado à equipe
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="alert-neo info">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>Dica:</strong> Você pode fechar esta janela ou cadastrar um novo empreendimento.
        <div class="mt-2 d-flex gap-2 flex-wrap">
            <button type="button" onclick="window.close();" class="btn-neo secondary sm">
                <i class="fas fa-times"></i> Fechar Janela
            </button>
            <button type="button" onclick="location.reload();" class="btn-neo primary sm">
                <i class="fas fa-plus"></i> Novo Cadastro
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= FORMULÁRIO ================= -->
<div class="card-neo">
    <div class="card-header">
        <i class="fas fa-building"></i>
        <span>Cadastrar Empreendimento</span>
    </div>
    <div class="card-body">
        <form method="post" id="formCreate">
            <input type="hidden" name="urlRetorno" value="<?= htmlspecialchars($urlAnterior) ?>">

            <div class="mb-4">
                <label for="EmpresaID" class="field-label">
                    <i class="fas fa-building"></i> Empresa <span class="req">*</span>
                </label>
                <select class="form-select" id="EmpresaID" name="EmpresaID" required>
                    <option value="">Selecione uma empresa</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?= htmlspecialchars($empresa['Empresa_Id']) ?>"
                            <?= ($showSuccess && $EmpresaID == $empresa['Empresa_Id']) ? 'selected' : '' ?>>
                            <?= mb_strtoupper(htmlspecialchars($empresa['NomeEmpresa'] ?? ''), 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="Nome" class="field-label">
                        <i class="fas fa-city"></i> Nome do Empreendimento <span class="req">*</span>
                    </label>
                    <input type="text" class="form-control" id="Nome" name="Nome"
                           value="<?= $showSuccess ? '' : htmlspecialchars($Nome ?? '') ?>"
                           required maxlength="255"
                           placeholder="Ex.: Residencial Bosque Verde">
                </div>
                <div class="col-md-6">
                    <label for="Localidade" class="field-label">
                        <i class="fas fa-map-marker-alt"></i> Localidade <span class="req">*</span>
                    </label>
                    <input type="text" class="form-control" id="Localidade" name="Localidade"
                           value="<?= $showSuccess ? '' : htmlspecialchars($Localidade ?? '') ?>"
                           required maxlength="255"
                           placeholder="Ex.: Boa Viagem, Recife-PE">
                </div>
            </div>

            <div class="d-flex justify-content-between gap-2 mt-4 flex-wrap">
                <button type="button" onclick="window.close();" class="btn-neo secondary">
                    <i class="fas fa-times"></i> Fechar
                </button>
                <button type="submit" class="btn-neo success">
                    <i class="fas fa-save"></i> Salvar Empreendimento
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= SCRIPTS ================= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {

    // ============================================================
    // Foco automático no campo Nome após sucesso
    // ============================================================
    <?php if ($showSuccess): ?>
    setTimeout(function () {
        $('#Nome').focus();
    }, 400);
    <?php endif; ?>

    // ============================================================
    // Botão "Novo Cadastro" (classe original mantida por compat.)
    // ============================================================
    $('.btn-novo-cadastro').on('click', function () {
        $('#Nome').val('');
        $('#Localidade').val('');
        $('#Nome').focus();
        $('.success-panel').hide();
        $('.alert-neo').hide();
        $('html, body').animate({
            scrollTop: $('.card-neo').offset().top - 40
        }, 400);
    });

    // ============================================================
    // Submit: bloqueia botão para evitar duplo envio
    // ============================================================
    $('#formCreate').on('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            this.reportValidity();
            return;
        }
        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Salvando…');
    });

    // ============================================================
    // Toast SweetAlert (só quando NÃO está no modo sucesso inline)
    // ============================================================
    <?php if (!$showSuccess && !empty($mensagemSucesso)): ?>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: <?= json_encode(strip_tags($mensagemSucesso)) ?>,
        timer: 5000,
        timerProgressBar: true,
        showConfirmButton: false
    });
    <?php endif; ?>

});
</script>

</body>
</html>
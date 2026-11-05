<?php
// ============================================================
// SESSION PRIMEIRO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';

// ============================================================
// LOGIN (bug #1)
// ============================================================
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// ============================================================
// CONEXÃO (bug #6)
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
// CSRF (bug #2)
// ============================================================
if (empty($_SESSION['csrf_img'])) {
    $_SESSION['csrf_img'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_img'];

// ============================================================
// PASTA DESTINO — relativa ao arquivo (bug #3, #12)
// ============================================================
$pastaRelativa = 'card/';
$pastaDestino  = __DIR__ . '/' . $pastaRelativa;

if (!is_dir($pastaDestino)) {
    @mkdir($pastaDestino, 0755, true);
}

// ============================================================
// FUNÇÃO: processa imagem com GD (resize + JPEG)
// ============================================================
function processarImagem($tmpPath, $destPath, $maxDim = 1200) {
    $info = @getimagesize($tmpPath);
    if (!$info) return false;

    $src = @imagecreatefromstring(file_get_contents($tmpPath));
    if (!$src) return false;

    $w = $info[0];
    $h = $info[1];

    if ($w > $maxDim || $h > $maxDim) {
        $ratio = min($maxDim / $w, $maxDim / $h);
        $newW = (int)round($w * $ratio);
        $newH = (int)round($h * $ratio);
    } else {
        $newW = $w;
        $newH = $h;
    }

    $dst   = imagecreatetruecolor($newW, $newH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white); // fundo branco para PNG transparente
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $ok = @imagejpeg($dst, $destPath, 88);

    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

// ============================================================
// ID: prioriza POST (hidden) > GET > último registro (bug #8)
// ============================================================
$idEmpreendimento = (int)($_POST['id'] ?? $_GET['Empreend_id'] ?? 0);

try {
    if ($idEmpreendimento <= 0) {
        $stmtUltimo = $db->query("SELECT Empreend_ID FROM empreendimento ORDER BY Empreend_ID DESC LIMIT 1");
        $ultimoId   = $stmtUltimo->fetchColumn();
        if ($ultimoId) $idEmpreendimento = (int)$ultimoId;
    }

    $stmtLista = $db->query("
        SELECT Empreend_ID, NomeEmpreendimento, Localidade
        FROM empreendimento
        ORDER BY NomeEmpreendimento ASC
    ");
    $listaEmpreendimentos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

    $nomeEmpreendimento   = '';
    $localidade           = '';
    $empreendimentoValido = false;

    if ($idEmpreendimento > 0) {
        $stmt = $db->prepare("SELECT NomeEmpreendimento, Localidade FROM empreendimento WHERE Empreend_ID = ?");
        $stmt->execute([$idEmpreendimento]);
        $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($empreendimento) {
            $nomeEmpreendimento   = $empreendimento['NomeEmpreendimento'];
            $localidade           = $empreendimento['Localidade'] ?? '';
            $empreendimentoValido = true;
        }
    }
} catch (PDOException $e) {
    die("Erro no banco de dados: " . htmlspecialchars($e->getMessage()));
}

// ============================================================
// UPLOAD (bugs #3, #4, #5, #13)
// ============================================================
$msg     = null;
$msgType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (!hash_equals($CSRF, $_POST['csrf_token'] ?? '')) {
        $msg = 'Falha de segurança (CSRF). Recarregue a página.';
        $msgType = 'danger';
    } elseif (!$empreendimentoValido) {
        $msg = 'Empreendimento inválido.';
        $msgType = 'danger';
    } elseif (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Nenhuma imagem enviada ou erro no upload.';
        $msgType = 'warning';
    } else {
        $file      = $_FILES['foto'];
        $maxBytes  = 8 * 1024 * 1024;
        $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if ($file['size'] > $maxBytes) {
            $msg = 'Imagem muito grande. Máximo 8 MB.';
            $msgType = 'danger';
        } else {
            // MIME real via finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed, true)) {
                $msg = 'Formato inválido. Use JPG, PNG, WebP ou GIF.';
                $msgType = 'danger';
            } elseif (!@getimagesize($file['tmp_name'])) {
                $msg = 'Arquivo não é uma imagem válida.';
                $msgType = 'danger';
            } else {
                $caminhoFinal = $pastaDestino . $idEmpreendimento . '.jpg';

                if (processarImagem($file['tmp_name'], $caminhoFinal)) {
                    $_SESSION['MsgSucesso'] = 'Imagem atualizada com sucesso!';
                    // PRG pattern — evita reenvio no F5
                    $redirectUrl = $_SERVER['PHP_SELF'] . '?Empreend_id=' . $idEmpreendimento;
                    header("Location: " . $redirectUrl);
                    exit;
                } else {
                    $msg = 'Falha ao processar imagem. Verifique a extensão GD do PHP.';
                    $msgType = 'danger';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Imagens · SunnyImob</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --primary-dark: <?= $themeSafe['primary_dark'] ?>;
            --bg-body: <?= $themeSafe['bg_body'] ?>;
            --text-color: <?= $themeSafe['text_color'] ?>;
            --card-bg: <?= $themeSafe['card_bg'] ?>;
            --shadow-color: <?= $themeSafe['shadow_color'] ?>;
            --btn-bg: <?= $themeSafe['btn_bg'] ?>;
            --btn-hover: <?= $themeSafe['btn_hover'] ?>;
            --btn-text: <?= $themeSafe['btn_text'] ?>;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
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
            -webkit-font-smoothing: antialiased;
        }

        .main-wrapper { display: flex; height: 100vh; overflow: hidden; }

        /* ============================================================ */
        /* SIDEBAR                                                      */
        /* ============================================================ */
        .sidebar {
            width: 380px;
            min-width: 380px;
            background: var(--card-bg);
            border-right: 3px solid var(--shadow-color);
            box-shadow: 4px 0 0 0 var(--shadow-color);
            display: flex;
            flex-direction: column;
            z-index: 10;
        }
        .sidebar-header {
            padding: 18px 20px;
            background: var(--primary);
            color: #fff;
            border-bottom: 3px solid var(--shadow-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sidebar-header h5 {
            margin: 0;
            font-weight: 900;
            font-size: 1rem;
            letter-spacing: -0.2px;
        }
        .sidebar-header i { opacity: 0.7; }

        .sidebar-search {
            padding: 14px 16px;
            border-bottom: 2px solid rgba(0,0,0,0.08);
            background: rgba(0,0,0,0.02);
        }
        .search-input-wrap {
            position: relative;
        }
        .search-input-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-color);
            opacity: 0.5;
            font-size: 0.85rem;
            pointer-events: none;
        }
        .search-input-wrap input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-color);
            background: #fff;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            transition: all 0.15s;
        }
        .search-input-wrap input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }

        .empreend-list { flex-grow: 1; overflow-y: auto; padding: 8px; }

        .empreend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            margin-bottom: 6px;
            border: 2px solid transparent;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.15s;
        }
        .empreend-item:hover {
            background: rgba(0,0,0,0.03);
            color: var(--text-color);
            text-decoration: none;
            border-color: rgba(0,0,0,0.08);
        }
        .empreend-item.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .empreend-item.active strong,
        .empreend-item.active small { color: #fff; opacity: 0.95; }

        .thumb-list {
            width: 56px;
            height: 56px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: #e5e7eb;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            display: block;
        }
        .thumb-placeholder {
            width: 56px; height: 56px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e5e7eb;
            color: #6b7280;
            font-size: 1.2rem;
            flex-shrink: 0;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .item-info { min-width: 0; flex: 1; }
        .item-info strong {
            display: block;
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .item-info small {
            display: block;
            font-size: 0.7rem;
            opacity: 0.65;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ============================================================ */
        /* CONTEÚDO                                                     */
        /* ============================================================ */
        .content-area {
            flex-grow: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .card-panel {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
        }

        .card-panel-header {
            background: var(--primary);
            color: #fff;
            padding: 16px 22px;
            border-bottom: 3px solid var(--shadow-color);
        }
        .card-panel-header h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 900;
            letter-spacing: -0.3px;
            text-transform: uppercase;
        }
        .card-panel-header p {
            margin: 4px 0 0;
            font-size: 0.78rem;
            opacity: 0.9;
            font-weight: 600;
        }
        .card-panel-body { padding: 22px; }

        /* Imagem principal */
        .img-main-wrap {
            background: #f8f9fb;
            border: 2px dashed rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .img-main {
            max-height: 420px;
            max-width: 100%;
            border-radius: 8px;
            border: 4px solid #fff;
            box-shadow: 4px 4px 0 0 var(--shadow-color), 0 8px 20px rgba(0,0,0,0.12);
            display: block;
        }
        .img-empty {
            text-align: center;
            color: var(--text-color);
            opacity: 0.5;
            font-weight: 600;
        }
        .img-empty i {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
            opacity: 0.6;
        }

        /* Paste area */
        #pasteArea {
            border: 3px dashed var(--primary);
            background: rgba(0,0,0,0.02);
            padding: 24px 20px;
            text-align: center;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.2s;
            margin-bottom: 16px;
        }
        #pasteArea:hover,
        #pasteArea.highlight {
            border-color: var(--primary-dark);
            background: rgba(0,0,0,0.05);
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        #pasteArea i {
            font-size: 2.2rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: block;
        }
        #pasteArea h5 {
            margin: 0 0 6px;
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--text-color);
        }
        #pasteArea p {
            margin: 0;
            font-size: 0.78rem;
            opacity: 0.6;
            font-weight: 500;
        }

        /* File input */
        .form-label {
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            opacity: 0.85;
            margin-bottom: 6px;
        }

        .form-control-file-custom {
            display: flex;
            align-items: stretch;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .form-control-file-custom input[type=file] {
            flex: 1;
            border: none;
            padding: 10px 12px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.82rem;
            color: var(--text-color);
            cursor: pointer;
            background: transparent;
        }
        .form-control-file-custom input[type=file]::file-selector-button {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 10px 16px;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.78rem;
            margin-right: 12px;
        }
        .form-control-file-custom input[type=file]::file-selector-button:hover {
            background: var(--primary-dark);
        }

        /* Preview */
        #previewContainer {
            display: none;
            text-align: center;
            padding: 14px;
            background: rgba(16,185,129,0.08);
            border: 2px solid var(--success);
            border-radius: 10px;
            margin: 14px 0;
        }
        #previewImagem {
            max-height: 180px;
            max-width: 100%;
            border-radius: 8px;
            border: 3px solid #fff;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            display: block;
            margin: 0 auto 8px;
        }
        #previewContainer .preview-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--success);
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            border: 2px solid var(--shadow-color);
        }

        /* Botões */
        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: inherit;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            text-decoration: none;
            width: 100%;
        }
        .btn-neo:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            color: #fff;
        }
        .btn-neo:active:not(:disabled) {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 0 var(--shadow-color);
        }
        .btn-neo-primary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo-primary:hover:not(:disabled) { background: var(--btn-hover); }
        .btn-neo:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #9ca3af;
            color: #fff;
        }

        /* Alert */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            padding: 12px 18px;
            font-weight: 700;
            font-size: 0.86rem;
            margin-bottom: 18px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .alert-neo.success { background: var(--success); color: #fff; }
        .alert-neo.danger  { background: var(--danger);  color: #fff; }
        .alert-neo.warning { background: var(--warning); color: #000; }

        /* Scrollbar */
        .sidebar::-webkit-scrollbar,
        .empreend-list::-webkit-scrollbar,
        .content-area::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-thumb,
        .empreend-list::-webkit-scrollbar-thumb,
        .content-area::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
        }

        /* ============================================================ */
        /* RESPONSIVO (bug #14)                                        */
        /* ============================================================ */
        @media (max-width: 900px) {
            .main-wrapper {
                flex-direction: column;
                height: auto;
                overflow: visible;
            }
            .sidebar {
                width: 100%;
                min-width: 0;
                max-height: 45vh;
                border-right: none;
                border-bottom: 3px solid var(--shadow-color);
                box-shadow: 0 4px 0 0 var(--shadow-color);
            }
            .content-area {
                padding: 16px;
                overflow-y: visible;
            }
        }
    </style>
</head>
<body>

<div class="main-wrapper">

    <!-- ============================================================ -->
    <!-- SIDEBAR                                                       -->
    <!-- ============================================================ -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h5><i class="fas fa-building me-2"></i> Empreendimentos</h5>
            <i class="fas fa-images"></i>
        </div>

        <div class="sidebar-search">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="buscaInput" placeholder="Procurar empreendimento..." autocomplete="off">
            </div>
        </div>

        <div class="empreend-list" id="listaMenu">
            <?php if (empty($listaEmpreendimentos)): ?>
                <div style="text-align:center; padding:30px 20px; opacity:0.5; font-weight:600; font-size:0.85rem;">
                    <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                    Nenhum empreendimento
                </div>
            <?php else: foreach ($listaEmpreendimentos as $emp):
                $thumbPath = $pastaRelativa . $emp['Empreend_ID'] . '.jpg';
                $fullPath  = __DIR__ . '/' . $thumbPath;
                $isActive  = ((int)$emp['Empreend_ID'] === $idEmpreendimento);
                $hasThumb  = file_exists($fullPath);
            ?>
                <a href="?Empreend_id=<?= (int)$emp['Empreend_ID'] ?>"
                   class="empreend-item <?= $isActive ? 'active' : '' ?>">
                    <?php if ($hasThumb): ?>
                        <img src="<?= htmlspecialchars($thumbPath) ?>?v=<?= filemtime($fullPath) ?>"
                             alt="" class="thumb-list"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="thumb-placeholder" style="display:none;"><i class="fas fa-image"></i></div>
                    <?php else: ?>
                        <div class="thumb-placeholder"><i class="fas fa-image"></i></div>
                    <?php endif; ?>

                    <div class="item-info">
                        <strong><?= htmlspecialchars($emp['NomeEmpreendimento']) ?></strong>
                        <small>
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($emp['Localidade'] ?: 'Não informada') ?>
                        </small>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </aside>

    <!-- ============================================================ -->
    <!-- WORKSPACE                                                     -->
    <!-- ============================================================ -->
    <main class="content-area">

        <?php if (isset($_SESSION['MsgSucesso'])): ?>
            <div class="alert-neo success">
                <i class="fas fa-check-circle"></i>
                <div><?= $_SESSION['MsgSucesso']; unset($_SESSION['MsgSucesso']); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($msg): ?>
            <div class="alert-neo <?= htmlspecialchars($msgType) ?>">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($msg) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($empreendimentoValido): ?>

            <!-- Header do card -->
            <div class="card-panel mb-4">
                <div class="card-panel-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2><?= htmlspecialchars($nomeEmpreendimento) ?></h2>
                        <p>
                            <i class="fas fa-location-arrow me-1"></i>
                            <?= htmlspecialchars($localidade ?: 'Localidade não informada') ?>
                            · <span style="background:rgba(0,0,0,0.2);padding:1px 8px;border-radius:6px;font-family:monospace;font-weight:800;">
                                ID #<?= $idEmpreendimento ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Preview atual -->
                <div class="col-lg-7">
                    <div class="card-panel">
                        <div class="card-panel-body">
                            <h6 style="font-size:0.74rem;text-transform:uppercase;letter-spacing:0.4px;font-weight:800;opacity:0.7;margin:0 0 14px;">
                                <i class="fas fa-eye me-1"></i> Imagem Atual
                            </h6>
                            <div class="img-main-wrap">
                                <?php
                                $mainPath = $pastaRelativa . $idEmpreendimento . '.jpg';
                                $mainFull = __DIR__ . '/' . $mainPath;
                                if (file_exists($mainFull)):
                                ?>
                                    <img src="<?= htmlspecialchars($mainPath) ?>?v=<?= filemtime($mainFull) ?>"
                                         alt="Card do empreendimento"
                                         class="img-main">
                                <?php else: ?>
                                    <div class="img-empty">
                                        <i class="fas fa-image"></i>
                                        <span>Nenhuma imagem definida para este empreendimento.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload -->
                <div class="col-lg-5">
                    <div id="pasteArea">
                        <i class="fas fa-paste"></i>
                        <h5>Colar imagem</h5>
                        <p>Pressione <kbd>Ctrl + V</kbd> em qualquer lugar da página</p>
                    </div>

                    <div class="card-panel">
                        <div class="card-panel-body">
                            <form method="post" enctype="multipart/form-data" id="formUpload">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
                                <input type="hidden" name="id" value="<?= (int)$idEmpreendimento ?>">

                                <label class="form-label d-block">Ou escolha um arquivo</label>
                                <div class="form-control-file-custom">
                                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
                                </div>
                                <small style="font-size:0.72rem;opacity:0.6;font-weight:500;margin-top:6px;display:block;">
                                    JPG · PNG · WebP · GIF · Máx. 8 MB
                                </small>

                                <div id="previewContainer">
                                    <img id="previewImagem" src="" alt="Preview">
                                    <span class="preview-badge">
                                        <i class="fas fa-check"></i> Pronto para salvar
                                    </span>
                                </div>

                                <button type="submit" class="btn-neo btn-neo-primary mt-3" id="btnSubmit" disabled>
                                    <i class="fas fa-save"></i> Salvar Imagem
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="card-panel">
                <div class="card-panel-body text-center py-5">
                    <i class="fas fa-exclamation-circle" style="font-size:3rem;opacity:0.3;margin-bottom:14px;display:block;"></i>
                    <h4 style="font-weight:900;">Nenhum empreendimento disponível</h4>
                    <p style="opacity:0.6;font-weight:500;">Cadastre um empreendimento antes de gerenciar imagens.</p>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    'use strict';

    const MAX_BYTES = 8 * 1024 * 1024;
    const ALLOWED   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // ============================================================
    // Filtro de busca
    // ============================================================
    const busca = document.getElementById('buscaInput');
    if (busca) {
        busca.addEventListener('input', function () {
            const v = this.value.toLowerCase().trim();
            document.querySelectorAll('#listaMenu .empreend-item').forEach(function (item) {
                item.style.display = item.textContent.toLowerCase().includes(v) ? '' : 'none';
            });
        });
    }

    // ============================================================
    // Handle file (valida cliente antes de mostrar preview)
    // ============================================================
    const fotoInput = document.getElementById('foto');
    const btnSubmit = document.getElementById('btnSubmit');
    const previewImg = document.getElementById('previewImagem');
    const previewContainer = document.getElementById('previewContainer');

    function toast(icon, title) {
        Swal.mixin({
            toast: true, position: 'top-end',
            showConfirmButton: false, timer: 3500, timerProgressBar: true
        }).fire({ icon: icon, title: title });
    }

    function handleFile(file) {
        if (!file) return;

        if (!ALLOWED.includes(file.type)) {
            toast('error', 'Formato inválido. Use JPG, PNG, WebP ou GIF.');
            return;
        }
        if (file.size > MAX_BYTES) {
            toast('error', 'Imagem muito grande (' + (file.size / 1048576).toFixed(1) + ' MB). Máx 8 MB.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewContainer.style.display = 'block';
            btnSubmit.disabled = false;
        };
        reader.readAsDataURL(file);

        // Sincroniza no <input type=file> via DataTransfer
        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            fotoInput.files = dt.files;
        } catch (err) {
            // Fallback silencioso para navegadores antigos
            console.warn('DataTransfer não suportado:', err);
        }
    }

    // ============================================================
    // Colar (Ctrl+V) — só captura se for imagem
    // ============================================================
    window.addEventListener('paste', function (e) {
        if (!e.clipboardData) return;
        const items = e.clipboardData.items;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                handleFile(items[i].getAsFile());
                return;
            }
        }
    });

    // ============================================================
    // Seleção manual
    // ============================================================
    if (fotoInput) {
        fotoInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) handleFile(this.files[0]);
        });
    }

    // ============================================================
    // Click na pasteArea = abrir seletor de arquivo
    // ============================================================
    const pasteArea = document.getElementById('pasteArea');
    if (pasteArea) {
        pasteArea.addEventListener('click', function () {
            if (fotoInput) fotoInput.click();
        });

        // Efeito visual ao colar
        window.addEventListener('paste', function () {
            pasteArea.classList.add('highlight');
            setTimeout(function () { pasteArea.classList.remove('highlight'); }, 600);
        });
    }

    // ============================================================
    // Submit com loading
    // ============================================================
    const form = document.getElementById('formUpload');
    if (form) {
        form.addEventListener('submit', function () {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
        });
    }

})();
</script>
</body>
</html>
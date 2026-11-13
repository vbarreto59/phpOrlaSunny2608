<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php';

// ============================================================
// CONEXÃO
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// ============================================================
// TEMA
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
// CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_links'])) {
    $_SESSION['csrf_links'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_links'];

// ============================================================
// HELPERS
// ============================================================
function usuarioPodeEditar() {
    return isset($_SESSION['Usuario']) && !empty($_SESSION['Usuario']);
}

function registrarHistorico($idEmpreend, $idEmpresa, $acao) {
    global $db;
    try {
        $userId = isset($_SESSION['UserId']) ? $_SESSION['UserId'] : 0;
        $sql = "INSERT INTO histatulog (IdEmpresa, IdEmpreend, IdUser, Usuario, Historico)
                VALUES (:idEmpresa, :idEmpreend, :idUser, :usuario, :historico)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':idEmpresa'  => $idEmpresa,
            ':idEmpreend' => $idEmpreend,
            ':idUser'     => $userId,
            ':usuario'    => $_SESSION['Usuario'],
            ':historico'  => $acao
        ]);
    } catch (PDOException $e) {
        error_log("[HISTORICO] " . $e->getMessage());
    }
}

function escape($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// ============================================================
// LOGIN E ID
// ============================================================
if (empty($_SESSION['Usuario'])) {
    header("Location: login_v3a.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id === 0) die("ID do empreendimento não fornecido");

// ============================================================
// PROCESSAMENTO POST (UPDATE)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Valida CSRF
    if (!hash_equals($_SESSION['csrf_links'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['mensagem'] = "Erro de segurança: Token inválido. Recarregue a página.";
        $_SESSION['tipo_mensagem'] = "danger";
        header("Location: ?id=" . $id);
        exit();
    }

    $id              = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // PROCESSAMENTO DE LATITUDE E LONGITUDE
    $latitude        = isset($_POST['Latitude'])        ? trim($_POST['Latitude'])        : '';
    $longitude       = isset($_POST['Longitude'])       ? trim($_POST['Longitude'])       : '';
    
    // Concatena de volta para o formato do banco "lat, long"
    $localizacao = '';
    if ($latitude !== '' && $longitude !== '') {
        $localizacao = $latitude . ', ' . $longitude;
    } elseif ($latitude !== '') {
        $localizacao = $latitude;
    } elseif ($longitude !== '') {
        $localizacao = $longitude;
    }

    $linkMapa        = isset($_POST['LinkMapa'])        ? trim($_POST['LinkMapa'])        : '';
    $linkFicha       = isset($_POST['LinkFicha'])       ? trim($_POST['LinkFicha'])       : '';
    $linkSite        = isset($_POST['LinkSite'])        ? trim($_POST['LinkSite'])        : '';
    $linkVideo       = isset($_POST['LinkVideo'])       ? trim($_POST['LinkVideo'])       : '';
    $linkTocca       = isset($_POST['LinkTocca'])       ? trim($_POST['LinkTocca'])       : '';
    $linkConstrutora = isset($_POST['LinkConstrutora']) ? trim($_POST['LinkConstrutora']) : '';
    $linkTabela      = isset($_POST['LinkTabela'])      ? trim($_POST['LinkTabela'])      : '';
    $linkCard        = isset($_POST['LinkCard'])        ? trim($_POST['LinkCard'])        : '';
    $idEmpresa       = isset($_POST['idEmpresa'])       ? intval($_POST['idEmpresa'])     : 0;
    $nomeEmpreend    = isset($_POST['NomeEmpreend'])    ? trim($_POST['NomeEmpreend'])    : '';

    if ($id > 0) {
        try {
            $sql = "UPDATE empreendimento SET
                        Localizacao = :localizacao,
                        LinkMapa = :linkMapa,
                        LinkFicha = :linkFicha,
                        LinkSite = :linkSite,
                        LinkVideo = :linkVideo,
                        LinkTocca = :linkTocca,
                        LinkConstrutora = :linkConstrutora,
                        LinkTabela = :linkTabela,
                        LinkCard = :linkCard,
                        DataAtualizacao = NOW(),
                        UsuarioAtu = :usuarioAtu
                    WHERE Empreend_ID = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':localizacao'     => $localizacao,
                ':linkMapa'        => $linkMapa,
                ':linkFicha'       => $linkFicha,
                ':linkSite'        => $linkSite,
                ':linkVideo'       => $linkVideo,
                ':linkTocca'       => $linkTocca,
                ':linkConstrutora' => $linkConstrutora,
                ':linkTabela'      => $linkTabela,
                ':linkCard'        => $linkCard,
                ':usuarioAtu'      => $_SESSION['Usuario'],
                ':id'              => $id
            ]);

            registrarHistorico($id, $idEmpresa, "Atualização de Links");

            $_SESSION['mensagem'] = "Links atualizados com sucesso!";
            $_SESSION['tipo_mensagem'] = "success";
        } catch (PDOException $e) {
            $_SESSION['mensagem'] = "Erro ao atualizar: " . $e->getMessage();
            $_SESSION['tipo_mensagem'] = "danger";
            error_log("[EMPREEND_LINKS_UPDATE] " . $e->getMessage());
        }
    }

    // Renova o token CSRF
    $_SESSION['csrf_links'] = bin2hex(random_bytes(32));
    
    header("Location: ?id=" . $id);
    exit();
}

// ============================================================
// BUSCAR EMPREENDIMENTO
// ============================================================
try {
    $stmt = $db->prepare("
        SELECT e.*, COALESCE(emp.NomeEmpresa, '') AS NomeEmpresa
        FROM empreendimento e
        LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
        WHERE e.Empreend_ID = :id
    ");
    $stmt->execute([':id' => $id]);
    $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empreendimento) {
        die("Empreendimento não encontrado");
    }
} catch (PDOException $e) {
    die("Erro ao buscar empreendimento: " . $e->getMessage());
}

// ============================================================
// SEPARAR LATITUDE E LONGITUDE PARA O FORMULÁRIO
// ============================================================
$latAtual = '';
$lngAtual = '';
if (!empty($empreendimento['Localizacao'])) {
    // Tenta separar por vírgula
    $parts = explode(',', $empreendimento['Localizacao']);
    if (count($parts) >= 2) {
        $latAtual = trim($parts[0]);
        $lngAtual = trim($parts[1]);
    } else {
        // Se não tiver vírgula, joga tudo na latitude (o usuário corrige)
        $latAtual = trim($empreendimento['Localizacao']);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Links do Empreendimento | SunnyImob</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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

        /* HEADER */
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
        .header-logo {
            height: 56px; width: auto; max-width: 140px; object-fit: contain;
            border: var(--border); border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm); background: #fff; padding: 4px 8px; flex-shrink: 0;
        }
        .header-title h1 {
            margin: 0; font-size: 1.25rem; font-weight: 900; letter-spacing: -0.02em; line-height: 1.2;
        }
        .header-title p { margin: 0.15rem 0 0; color: #6b7280; font-size: 0.78rem; font-weight: 500; }
        .header-title .empresa-tag {
            display: inline-flex; align-items: center; gap: 0.35rem;
            background: var(--primary); color: #fff; padding: 0.15rem 0.6rem;
            border-radius: 20px; font-size: 0.7rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.04em;
            border: 2px solid var(--shadow-color); box-shadow: 2px 2px 0 var(--shadow-color);
            margin-top: 0.35rem;
        }
        .header-right { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }

        /* BOTÕES */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.55rem 1.1rem; border: var(--border); border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm); font-weight: 800; font-size: 0.78rem;
            text-transform: uppercase; letter-spacing: 0.04em; text-decoration: none;
            cursor: pointer; transition: all 0.15s ease; background: var(--btn-bg);
            color: var(--btn-text); font-family: inherit; white-space: nowrap;
        }
        .btn:hover { transform: translate(-2px, -2px); box-shadow: var(--shadow-md); color: var(--btn-text); }
        .btn:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 var(--shadow-color); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); color: #fff; }
        .btn-secondary { background: #fff; color: var(--text-color); }
        .btn-secondary:hover { background: #f3f4f6; color: var(--text-color); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #15803d; color: #fff; }
        .btn-google { background: #fff; color: var(--text-color); border-color: var(--shadow-color); }
        .btn-google:hover { background: #f3f4f6; color: var(--text-color); }

        /* ALERTAS */
        .alert-neo {
            display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.9rem 1.1rem;
            border: var(--border); border-radius: var(--radius-sm); box-shadow: var(--shadow-sm);
            margin-bottom: 1.25rem; font-weight: 600;
        }
        .alert-neo i { font-size: 1.15rem; margin-top: 1px; flex-shrink: 0; }
        .alert-neo.success { background: #d1fae5; color: #065f46; border-color: #065f46; box-shadow: 3px 3px 0 #065f46; }
        .alert-neo.danger  { background: #fee2e2; color: #7f1d1d; border-color: #991b1b; box-shadow: 3px 3px 0 #991b1b; }

        /* LAYOUT */
        .form-layout { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start; }
        @media (max-width: 1100px) { .form-layout { grid-template-columns: 1fr; } }
        .form-main { min-width: 0; }

        /* SEÇÕES */
        .section {
            background: var(--card-bg); border: var(--border); border-radius: var(--radius);
            box-shadow: var(--shadow-md); margin-bottom: 1.15rem; overflow: hidden; transition: box-shadow 0.2s ease;
        }
        .section:hover { box-shadow: 5px 5px 0 var(--shadow-color); }
        .section-header {
            display: flex; align-items: center; gap: 0.7rem; padding: 0.75rem 1.15rem;
            background: var(--primary); color: #fff; border-bottom: var(--border);
            font-weight: 800; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .section-header h2 { margin: 0; font-size: inherit; font-weight: inherit; color: inherit; flex: 1; }
        .section-header i { font-size: 0.95rem; opacity: 0.95; }
        .section-number {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px; border-radius: 50%; background: #fff; color: var(--primary);
            font-size: 0.72rem; font-weight: 900; border: 2px solid var(--shadow-color); flex-shrink: 0;
        }
        .section-body { padding: 1rem 1.2rem; }

        /* CAMPOS */
        .field-label {
            display: block; font-weight: 700; font-size: 0.7rem; margin-bottom: 0.4rem;
            text-transform: uppercase; letter-spacing: 0.05em; color: #4b5563;
        }
        .form-control {
            width: 100%; padding: 0.65rem 0.9rem; border: var(--border); border-radius: var(--radius-sm);
            background: #fff; color: var(--text-color); font-size: 0.9rem; font-weight: 500;
            font-family: inherit; transition: all 0.15s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 3px 3px 0 var(--shadow-color); }
        .form-control::placeholder { color: #a3aab4; font-weight: 400; }
        .input-highlight { background: #fff7ed; }

        /* SIDEBAR */
        .form-side { min-width: 0; position: sticky; top: 1.5rem; }
        .side-card {
            background: var(--card-bg); border: var(--border); border-radius: var(--radius);
            box-shadow: var(--shadow-md); overflow: hidden;
        }
        .side-card .side-header {
            background: var(--accent); color: var(--shadow-color); border-bottom: var(--border);
            padding: 0.85rem 1.15rem; font-weight: 900; font-size: 0.8rem; text-transform: uppercase;
            letter-spacing: 0.06em; display: flex; align-items: center; gap: 0.5rem;
        }
        .side-card .side-body { padding: 1rem; }
        .card-image-wrap {
            width: 100%; aspect-ratio: 4 / 3; border: var(--border); border-radius: var(--radius-sm);
            overflow: hidden; background: #f3f4f6; margin-bottom: 0.85rem; box-shadow: var(--shadow-sm); position: relative;
        }
        .card-image-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; transition: opacity 0.2s; }
        .card-image-wrap img.img-loading { opacity: 0.4; }
        .card-image-wrap img.img-fallback { opacity: 0.85; }
        .card-image-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.35rem; }
        .card-image-name { font-weight: 800; font-size: 0.92rem; color: var(--text-color); margin-bottom: 0.2rem; word-break: break-word; }
        .card-image-company { font-size: 0.78rem; color: #6b7280; font-weight: 600; word-break: break-word; }
        .preview-mini { margin-top: 0.85rem; padding: 0.7rem; border: 2px dashed #e5e7eb; border-radius: var(--radius-sm); background: #f8fafc; }
        .preview-mini-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.4rem; }
        .preview-mini img { width: 100%; max-width: 200px; border: 2px solid var(--shadow-color); border-radius: 6px; display: block; }

        /* ACTION BAR */
        .action-bar {
            display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;
            margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed #cbd5e0;
        }

        @media (max-width: 640px) {
            body { padding: 0.75rem; }
            .app-header { padding: 1rem 1.15rem; }
            .app-header h1 { font-size: 1.05rem; }
            .header-logo { height: 44px; }
            .section-body { padding: 0.9rem 1rem; }
            .header-right { width: 100%; justify-content: flex-end; }
        }
    </style>
</head>
<body>

<div class="app-wrap">

    <!-- HEADER -->
    <header class="app-header">
        <div class="header-left">
            <img src="img/logosunny.jpg" alt="SunnyImob" class="header-logo" onerror="this.style.display='none'">
            <div class="header-title">
                <h1><i class="fas fa-link me-2"></i>Links do Empreendimento</h1>
                <p><?= escape($empreendimento['NomeEmpreendimento']) ?></p>
                <?php if (!empty($empreendimento['NomeEmpresa'])): ?>
                    <span class="empresa-tag">
                        <i class="fas fa-building"></i>
                        <?= escape($empreendimento['NomeEmpresa']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <button type="button" class="btn btn-secondary" onclick="fecharAba()">
                <i class="fas fa-times"></i> Fechar
            </button>
        </div>
    </header>

    <!-- ALERTAS -->
    <?php if (isset($_SESSION['mensagem'])): ?>
        <div class="alert-neo <?= escape($_SESSION['tipo_mensagem']) ?>">
            <i class="fas fa-<?= $_SESSION['tipo_mensagem'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <div><?= $_SESSION['mensagem'] ?></div>
        </div>
        <?php
        unset($_SESSION['mensagem']);
        unset($_SESSION['tipo_mensagem']);
        ?>
    <?php endif; ?>

    <!-- FORM -->
    <form method="post" class="form-layout" id="formLinks">
        
        <!-- PROTEÇÃO CSRF -->
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="idEmpresa" value="<?= (int)($empreendimento['Empresa_Id'] ?? 0) ?>">
        <input type="hidden" name="NomeEmpreend" value="<?= escape($empreendimento['NomeEmpreendimento']) ?>">

        <!-- COLUNA ESQUERDA -->
        <main class="form-main">

            <!-- 1 · LOCALIZAÇÃO (AGORA COM LATITUDE E LONGITUDE SEPARADAS) -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">1</span> <i class="fas fa-map-marker-alt"></i> <h2>Latitude e Longitude</h2>
                </div>
                <div class="section-body">
                    <label class="field-label">Cole as coordenadas no campo Latitude (ex: <code>-8.123456, -34.876543</code>) — o sistema separará automaticamente.</label>
                    
                    <div class="row g-2 mt-1">
                        <!-- Campo de Latitude -->
                        <div class="col-md-6">
                            <label for="latitudeInput" class="field-label" style="font-size:0.65rem; color:#6b7280;">Latitude</label>
                            <input type="text" class="form-control input-highlight" name="Latitude" id="latitudeInput"
                                   value="<?= escape($latAtual) ?>" maxlength="50" 
                                   placeholder="-8.123456"
                                   onpaste="handlePasteLatitude(event)">
                        </div>
                        
                        <!-- Campo de Longitude -->
                        <div class="col-md-6">
                            <label for="longitudeInput" class="field-label" style="font-size:0.65rem; color:#6b7280;">Longitude</label>
                            <input type="text" class="form-control input-highlight" name="Longitude" id="longitudeInput"
                                   value="<?= escape($lngAtual) ?>" maxlength="50" 
                                   placeholder="-34.876543">
                        </div>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-google" onclick="openGoogleMaps()">
                            <i class="fas fa-map"></i> Abrir no Google Maps
                        </button>
                        <small class="text-muted" style="font-size:0.75rem;">
                            O sistema aceita vírgula, espaço ou ponto e vírgula como separador.
                        </small>
                    </div>
                </div>
            </section>

            <!-- 2 · MAPA -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">2</span> <i class="fas fa-map"></i> <h2>Link do Mapa</h2>
                </div>
                <div class="section-body">
                    <label for="LinkMapa" class="field-label">Google Maps ou similar</label>
                    <input type="text" class="form-control input-highlight" name="LinkMapa" id="LinkMapa"
                           value="<?= escape($empreendimento['LinkMapa']) ?>" maxlength="255" placeholder="https://maps.google.com/...">
                </div>
            </section>

            <!-- 3 · FICHA TÉCNICA -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">3</span> <i class="fas fa-file-alt"></i> <h2>Ficha Técnica</h2>
                </div>
                <div class="section-body">
                    <label for="LinkFicha" class="field-label">Link da Ficha Técnica</label>
                    <input type="text" class="form-control input-highlight" name="LinkFicha" id="LinkFicha"
                           value="<?= escape($empreendimento['LinkFicha']) ?>" maxlength="255" placeholder="https://...">
                </div>
            </section>

            <!-- 4 · SITE OFICIAL -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">4</span> <i class="fas fa-globe"></i> <h2>Site Oficial</h2>
                </div>
                <div class="section-body">
                    <label for="LinkSite" class="field-label">Site oficial do empreendimento</label>
                    <input type="text" class="form-control input-highlight" name="LinkSite" id="LinkSite"
                           value="<?= escape($empreendimento['LinkSite']) ?>" maxlength="255" placeholder="https://...">
                </div>
            </section>

            <!-- 5 · VÍDEO -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">5</span> <i class="fas fa-video"></i> <h2>Vídeo</h2>
                </div>
                <div class="section-body">
                    <label for="LinkVideo" class="field-label">Link do vídeo</label>
                    <input type="text" class="form-control input-highlight" name="LinkVideo" id="LinkVideo"
                           value="<?= escape($empreendimento['LinkVideo']) ?>" maxlength="255" placeholder="https://youtube.com/...">
                </div>
            </section>

            <!-- 6 · BOOK -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">6</span> <i class="fas fa-book"></i> <h2>Book</h2>
                </div>
                <div class="section-body">
                    <label for="LinkTocca" class="field-label">Link do Book</label>
                    <input type="text" class="form-control input-highlight" name="LinkTocca" id="LinkTocca"
                           value="<?= escape($empreendimento['LinkTocca']) ?>" maxlength="255" placeholder="https://...">
                </div>
            </section>

            <!-- 7 · DRIVE DA CONSTRUTORA -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">7</span> <i class="fab fa-google-drive"></i> <h2>Drive da Construtora</h2>
                </div>
                <div class="section-body">
                    <label for="LinkConstrutora" class="field-label">Link do Drive</label>
                    <input type="text" class="form-control input-highlight" name="LinkConstrutora" id="LinkConstrutora"
                           value="<?= escape($empreendimento['LinkConstrutora']) ?>" maxlength="255" placeholder="https://drive.google.com/...">
                </div>
            </section>

            <!-- 8 · TABELA DE VALORES -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">8</span> <i class="fas fa-table"></i> <h2>Tabela de Valores</h2>
                </div>
                <div class="section-body">
                    <label for="LinkTabela" class="field-label">Link da tabela</label>
                    <input type="text" class="form-control input-highlight" name="LinkTabela" id="LinkTabela"
                           value="<?= escape($empreendimento['LinkTabela']) ?>" maxlength="255" placeholder="https://...">
                </div>
            </section>

            <!-- 9 · CARD -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">9</span> <i class="fas fa-image"></i> <h2>Card (Imagem)</h2>
                </div>
                <div class="section-body">
                    <label for="linkCardInput" class="field-label">Link da imagem do card</label>
                    <input type="text" class="form-control input-highlight" name="LinkCard" id="linkCardInput"
                           value="<?= escape($empreendimento['LinkCard']) ?>" maxlength="255"
                           oninput="atualizarImagemCard()" placeholder="https://.../imagem.jpg">
                    <div id="previewCard" class="preview-mini">
                        <div class="preview-mini-label">Pré-visualização</div>
                        <img id="previewImagemCard" src="" alt="Preview do Card" style="display:none;">
                    </div>
                </div>
            </section>

            <!-- AÇÕES -->
            <div class="action-bar">
                <a href="javascript:fecharAba()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <?php if (usuarioPodeEditar()): ?>
                    <button type="submit" class="btn btn-success" id="btnSalvar">
                        <i class="fas fa-check-circle"></i> Atualizar Links
                    </button>
                <?php endif; ?>
            </div>

        </main>

        <!-- COLUNA DIREITA (CARD) -->
        <aside class="form-side">
            <div class="side-card">
                <div class="side-header">
                    <i class="fas fa-building"></i> <span>Empreendimento</span>
                </div>
                <div class="side-body">
                    <div class="card-image-label">Imagem do Card</div>
                    <div class="card-image-wrap">
                        <img src="card/<?= (int)$empreendimento['Empreend_ID'] ?>.jpg"
                             class="img-loading"
                             alt="<?= escape($empreendimento['NomeEmpreendimento']) ?>"
                             onload="this.classList.remove('img-loading')"
                             onerror="this.onerror=null;this.src='card/default.jpg';this.classList.add('img-fallback');this.classList.remove('img-loading')">
                    </div>
                    <div class="card-image-name"><?= escape($empreendimento['NomeEmpreendimento']) ?></div>
                    <?php if (!empty($empreendimento['NomeEmpresa'])): ?>
                        <div class="card-image-company">
                            <i class="fas fa-building"></i> <?= escape($empreendimento['NomeEmpresa']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    'use strict';

    let isDirty = false;

    // ============================================================
    // Fechar Aba
    // ============================================================
    window.fecharAba = function () {
        if (isDirty) {
            Swal.fire({
                title: 'Sair sem salvar?',
                text: 'Você tem alterações não salvas. Elas serão perdidas.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sair mesmo assim',
                cancelButtonText: 'Ficar',
                confirmButtonColor: '<?= $themeSafe['primary'] ?>',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.onbeforeunload = null;
                    try { window.open('', '_self', ''); } catch(e) {}
                    window.close();
                }
            });
        } else {
            window.onbeforeunload = null;
            try { window.open('', '_self', ''); } catch(e) {}
            window.close();
        }
    };

    // ============================================================
    // Separação automática ao colar coordenadas
    // ============================================================
    window.handlePasteLatitude = function (e) {
        // Captura o texto da área de transferência
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        
        // Regex para detectar o padrão "latitude, longitude" (aceita vírgula, ponto e vírgula ou espaço)
        const match = pastedText.match(/^\s*([-+]?\d{1,3}(?:\.\d+)?)\s*[,;\s]+\s*([-+]?\d{1,3}(?:\.\d+)?)\s*$/);

        if (match) {
            // Se encontrou duas coordenadas, previne o comportamento padrão (que jogaria tudo na lat)
            e.preventDefault();
            
            const lat = match[1].trim();
            const lng = match[2].trim();

            document.getElementById('latitudeInput').value = lat;
            document.getElementById('longitudeInput').value = lng;

            // Dispara o aviso de "alterações pendentes"
            isDirty = true;
            
            // Opcional: Pequeno feedback visual
            const lngInput = document.getElementById('longitudeInput');
            lngInput.style.transition = 'background-color 0.4s';
            lngInput.style.backgroundColor = '#d1fae5';
            setTimeout(() => { lngInput.style.backgroundColor = '#fff7ed'; }, 800);
        }
        // Se não der match (ex: colou só um número), deixa o comportamento normal de colagem
    };

    // ============================================================
    // Abrir Google Maps
    // ============================================================
    window.openGoogleMaps = function () {
        const lat = document.getElementById('latitudeInput').value.trim();
        const lng = document.getElementById('longitudeInput').value.trim();

        if (!lat || !lng) {
            Swal.fire({
                icon: 'warning',
                title: 'Coordenadas incompletas',
                text: 'Por favor, preencha a Latitude e a Longitude antes de abrir o mapa.',
                confirmButtonText: 'Entendi',
                confirmButtonColor: '<?= $themeSafe['primary'] ?>'
            });
            return;
        }

        const query = `${lat},${lng}`;
        window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query), '_blank');
    };

    // ============================================================
    // Preview do card
    // ============================================================
    window.atualizarImagemCard = function () {
        const linkInput = document.getElementById('linkCardInput');
        const imagemPreview = document.getElementById('previewImagemCard');
        const novoLink = linkInput.value.trim();

        if (novoLink !== '') {
            imagemPreview.src = novoLink;
            imagemPreview.style.display = 'block';
        } else {
            imagemPreview.src = '';
            imagemPreview.style.display = 'none';
        }
    };

    // ============================================================
    // Init
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        atualizarImagemCard();

        // Monitorar alterações
        document.querySelectorAll('#formLinks input[type="text"]').forEach(input => {
            input.addEventListener('input', () => { isDirty = true; });
        });
    });

    // ============================================================
    // Submit
    // ============================================================
    document.getElementById('formLinks')?.addEventListener('submit', function (e) {
        const btn = document.getElementById('btnSalvar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
        }
    });

    // Aviso ao tentar fechar a aba sem salvar
    window.addEventListener('beforeunload', function (e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

})();
</script>

</body>
</html>
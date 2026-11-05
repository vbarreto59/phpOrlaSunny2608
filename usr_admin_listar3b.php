<?php
// ============================================================
// SESSION PRIMEIRO (bug #1)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'conn.php';
require_once 'theme_config.php';

// ============================================================
// CONEXÃO
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão não encontrada.");

// ============================================================
// TEMA SEGURO (bug #12)
// ============================================================
$themeDefaults = [
    'primary' => '#4361ee', 'primary_dark' => '#3a0ca3',
    'secondary' => '#6c757d', 'accent' => '#ffc107',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#4361ee', 'btn_hover' => '#3a0ca3', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ============================================================
// CSRF (bug #2)
// ============================================================
if (empty($_SESSION['csrf_usr_admin3c'])) {
    $_SESSION['csrf_usr_admin3c'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_usr_admin3c'];

// ============================================================
// AJAX HANDLER 1 — Toggles (Ativo/Licenca/Grupo/Diretoria/Gerencia)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET')
    && isset($_GET['ajax']) && $_GET['ajax'] == '1'
    && !isset($_GET['action'])) {

    header('Content-Type: application/json; charset=utf-8');
    // Bug #3: log vai para o log do PHP, não para arquivo no webroot
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    // 🔒 CSRF obrigatório
    $csrfEnviado = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!hash_equals($CSRF, $csrfEnviado)) {
        echo json_encode(["status" => "error", "message" => "Falha de segurança (CSRF)."]);
        exit;
    }

    $userId = $_POST['userId'] ?? $_GET['userId'] ?? null;
    $field  = $_POST['field']  ?? $_GET['field']  ?? null;
    $value  = $_POST['value']  ?? $_GET['value']  ?? null;

    if (!$userId || !isset($field)) {
        echo json_encode(["status" => "error", "message" => "Parâmetros inválidos"]);
        exit;
    }

    try {
        switch ($field) {
            case "ativo":
                $ativo = ($value === "1" || $value === 1 || $value === true || $value === "true") ? 1 : 0;
                $stmt = $db->prepare("UPDATE usuarios SET Ativo = ? WHERE UserID = ?");
                $stmt->execute([$ativo, $userId]);
                break;

            case "licenca":
                $lic = ($value === "1" || $value === 1 || $value === true || $value === "true") ? 1 : 0;
                $stmt = $db->prepare("UPDATE usuarios SET Licenca = ? WHERE UserID = ?");
                $stmt->execute([$lic, $userId]);
                break;

            case "grupo":
                $stmt = $db->prepare("SELECT COUNT(*) FROM usuario_grupo WHERE UserId = ?");
                $stmt->execute([$userId]);
                $exists = $stmt->fetchColumn() > 0;

                if (empty($value)) {
                    if ($exists) $db->prepare("UPDATE usuario_grupo SET ID_Grupo = NULL WHERE UserId = ?")->execute([$userId]);
                    $db->prepare("UPDATE usuarios SET GrupoId = NULL, Grupo = NULL WHERE UserID = ?")->execute([$userId]);
                } else {
                    if ($exists) {
                        $db->prepare("UPDATE usuario_grupo SET ID_Grupo = ? WHERE UserId = ?")->execute([$value, $userId]);
                    } else {
                        $db->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)")->execute([$userId, $value]);
                    }
                    $stmt = $db->prepare("SELECT Nome_Grupo FROM grupo WHERE ID_Grupo = ?");
                    $stmt->execute([$value]);
                    $grupoNome = $stmt->fetchColumn() ?: null;
                    $db->prepare("UPDATE usuarios SET GrupoId = ?, Grupo = ? WHERE UserID = ?")
                       ->execute([$value, $grupoNome, $userId]);
                }
                break;

            case "diretoria":
                if (empty($value)) {
                    $db->prepare("UPDATE usuarios SET DiretoriaID = NULL, Diretoria = NULL, GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?")
                       ->execute([$userId]);
                } else {
                    $stmt = $db->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
                    $stmt->execute([$value]);
                    $nomeDir = $stmt->fetchColumn();
                    if ($nomeDir === false) {
                        echo json_encode(["status" => "error", "message" => "Diretoria não encontrada"]);
                        exit;
                    }
                    $db->prepare("UPDATE usuarios SET DiretoriaID = ?, Diretoria = ?, GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?")
                       ->execute([$value, $nomeDir, $userId]);
                }
                break;

            case "gerencia":
                if (empty($value)) {
                    $db->prepare("UPDATE usuarios SET GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?")->execute([$userId]);
                } else {
                    $stmt = $db->prepare("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = ?");
                    $stmt->execute([$value]);
                    $nomeGer = $stmt->fetchColumn();
                    if ($nomeGer === false) {
                        echo json_encode(["status" => "error", "message" => "Gerência não encontrada"]);
                        exit;
                    }
                    $db->prepare("UPDATE usuarios SET GerenciaID = ?, Gerencia = ? WHERE UserID = ?")
                       ->execute([$value, $nomeGer, $userId]);
                }
                break;

            default:
                echo json_encode(["status" => "error", "message" => "Campo inválido"]);
                exit;
        }

        echo json_encode(["status" => "success", "message" => "Atualizado com sucesso"]);
    } catch (PDOException $e) {
        error_log("[USR_ADMIN3C] " . $e->getMessage()); // ✅ vai para o log do PHP
        echo json_encode(["status" => "error", "message" => "Erro interno no banco."]);
    }
    exit;
}

// ============================================================
// AJAX HANDLER 2 — Carregar gerências por diretoria
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['ajax']) && $_GET['ajax'] == '1'
    && ($_GET['action'] ?? '') === 'get-gerencias') {

    header('Content-Type: application/json; charset=utf-8');
    $DiretoriaId = (int)($_GET['DiretoriaId'] ?? 0);
    if ($DiretoriaId <= 0) { echo json_encode([]); exit; }

    try {
        $stmt = $db->prepare("SELECT GerenciaID, NomeGerencia FROM gerencias WHERE DiretoriaID = ? ORDER BY NomeGerencia");
        $stmt->execute([$DiretoriaId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log("[USR_ADMIN3C] " . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// ============================================================
// HTML OUTPUT
// ============================================================
header('Content-Type: text/html; charset=utf-8');

try {
    $grupos     = $db->query("SELECT * FROM grupo ORDER BY Nome_Grupo")->fetchAll(PDO::FETCH_ASSOC);
    $diretorias = $db->query("SELECT * FROM diretorias ORDER BY NomeDiretoria")->fetchAll(PDO::FETCH_ASSOC);
    $gerencias  = $db->query("SELECT * FROM gerencias ORDER BY NomeGerencia")->fetchAll(PDO::FETCH_ASSOC);

    // Bug #9: COALESCE garante 0 (não NULL) em tabela vazia
    $stmt = $db->query("
        SELECT COUNT(*) AS total,
               COALESCE(SUM(CASE WHEN Ativo = 1 THEN 1 ELSE 0 END), 0) AS ativos,
               COALESCE(SUM(CASE WHEN Licenca = 1 THEN 1 ELSE 0 END), 0) AS licenciados,
               COALESCE(SUM(CASE WHEN Ativo = 1 AND Licenca = 1 THEN 1 ELSE 0 END), 0) AS ativos_e_licenciados
        FROM usuarios
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $usersAtivosLicenciados = $db->query("SELECT * FROM usuarios WHERE Ativo = 1 AND Licenca = 1 ORDER BY UserId DESC")->fetchAll(PDO::FETCH_ASSOC);
    $usersDemais            = $db->query("SELECT * FROM usuarios WHERE Ativo = 0 OR Licenca = 0 ORDER BY UserId DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('<div class="alert alert-danger text-center m-5">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// ============================================================
// HELPERS
// ============================================================
function getBadgeClass($p) {
    return match((int)$p) {
        1 => 'badge-superadmin',
        2 => 'badge-admin',
        3 => 'badge-gestor',
        4 => 'badge-editor',
        5, 6 => 'badge-corretor',
        7 => 'badge-visualizador',
        default => 'badge-secondary',
    };
}

function renderUserRow($user, $grupos, $diretorias, $gerencias) {
    $uid              = $user['UserId'] ?? 0;
    $nome             = $user['Nome'] ?? $user['Usuario'] ?? '—';
    $senha            = $user['SenhaAtual'] ?? '*';
    $usuario          = $user['Usuario'] ?? '—';
    $email            = $user['Email'] ?? '—';
    $creci            = $user['CRECI'] ?? '';
    $ativo            = (int)($user['Ativo'] ?? 0) === 1;
    $licenca          = (int)($user['Licenca'] ?? 0) === 1;
    $funcao           = $user['Funcao'] ?? '—';
    $permissao        = (int)($user['Permissao'] ?? 0);
    $grupoIdAtual     = $user['GrupoId'] ?? null;
    $DiretoriaIDAtual = $user['DiretoriaID'] ?? null;
    $GerenciaIDAtual  = $user['GerenciaID']  ?? null;
    $badgeClass       = getBadgeClass($permissao);
    $inativo          = !$ativo ? 'user-inativo' : '';
    $semLicenca       = !$licenca ? 'user-sem-licenca' : '';

    ob_start(); ?>
    <tr class="<?= $inativo ?> <?= $semLicenca ?>"
        data-user-id="<?= $uid ?>"
        data-grupo-id="<?= $grupoIdAtual ?>"
        data-permissao="<?= $permissao ?>"
        data-search="<?= htmlspecialchars(mb_strtolower($nome . ' ' . $usuario . ' ' . $email)) ?>">
        <td class="fw-bold text-center"><?= $uid ?></td>
        <td>
            <div class="d-flex align-items-start">
                <div class="icon-circle bg-light text-primary me-3"><i class="fas fa-user"></i></div>
                <div>
                    <div class="fw-bold mb-1"><?= htmlspecialchars($nome) ?></div>
                    <small class="d-block text-muted mb-1"><i class="fas fa-at me-1"></i><?= htmlspecialchars($usuario) ?></small>
                    <small class="d-block text-muted mb-1"><i class="fas fa-lock me-1"></i><?= htmlspecialchars($senha) ?></small>
                    <small class="d-block text-muted mb-1"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($email) ?></small>
                    <?php if ($creci): ?>
                        <small class="d-block text-muted"><i class="fas fa-id-card me-1"></i>CRECI: <?= htmlspecialchars($creci) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td>
            <div class="form-check form-switch">
                <input class="form-check-input toggle-ativo" type="checkbox" role="switch"
                       data-user-id="<?= $uid ?>" <?= $ativo ? 'checked' : '' ?>>
                <label class="form-check-label">
                    <span class="status-indicator <?= $ativo ? 'status-active' : 'status-inactive' ?>"></span>
                    <span class="status-text"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
                </label>
            </div>
        </td>
        <td>
            <div class="form-check form-switch">
                <input class="form-check-input toggle-licenca" type="checkbox" role="switch"
                       data-user-id="<?= $uid ?>" <?= $licenca ? 'checked' : '' ?>>
                <label class="form-check-label">
                    <span class="status-indicator <?= $licenca ? 'status-active' : 'status-inactive' ?>"></span>
                    <span class="status-text"><?= $licenca ? 'Licenciada' : 'Sem Licença' ?></span>
                </label>
            </div>
        </td>
        <td>
            <span class="badge <?= $badgeClass ?> badge-permissao">
                <i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($funcao) ?>
            </span>
        </td>
        <td>
            <select class="form-select form-select-sm grupo-select select2" data-user-id="<?= $uid ?>">
                <option value="">— Nenhum —</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?= $g['ID_Grupo'] ?>" <?= $grupoIdAtual == $g['ID_Grupo'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['Nome_Grupo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm diretoria-select select2" data-user-id="<?= $uid ?>">
                <option value="">— Nenhum —</option>
                <?php foreach ($diretorias as $d): ?>
                    <option value="<?= $d['DiretoriaId'] ?>" <?= $DiretoriaIDAtual == $d['DiretoriaId'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['NomeDiretoria']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm gerencia-select select2" data-user-id="<?= $uid ?>"
                    <?= empty($DiretoriaIDAtual) ? 'disabled' : '' ?>>
                <option value="">— Nenhum —</option>
                <?php if (!empty($DiretoriaIDAtual)): ?>
                    <?php foreach ($gerencias as $ger): ?>
                        <?php if ($ger['DiretoriaID'] == $DiretoriaIDAtual): ?>
                            <option value="<?= $ger['GerenciaID'] ?>" <?= $GerenciaIDAtual == $ger['GerenciaID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ger['NomeGerencia']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </td>
        <td class="text-center">
            <div class="d-flex gap-2 justify-content-center">
                <a href="usr_admin_editar3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-warning" title="Editar" target="_blank">
                    <i class="fas fa-pencil-alt"></i>
                </a>
                <a href="usr_email_credencial.php?UserID=<?= $uid ?>" class="btn-action btn btn-outline-info" title="Enviar Credenciais"
                   onclick="return confirm('Enviar credenciais por e-mail?')">
                    <i class="fas fa-envelope"></i>
                </a>
                <a href="usr_admin_excluir3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-danger" title="Excluir"
                   onclick="return confirm('Confirma exclusão permanente?')">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

function renderMobileCard($user, $grupos, $diretorias, $gerencias) {
    $uid              = $user['UserId'] ?? 0;
    $nome             = $user['Nome'] ?? $user['Usuario'] ?? '—';
    $usuario          = $user['Usuario'] ?? '—';
    $email            = $user['Email'] ?? '—';
    $creci            = $user['CRECI'] ?? '';
    $ativo            = (int)($user['Ativo'] ?? 0) === 1;
    $licenca          = (int)($user['Licenca'] ?? 0) === 1;
    $funcao           = $user['Funcao'] ?? '—';
    $permissao        = (int)($user['Permissao'] ?? 0);
    $grupoIdAtual     = $user['GrupoId'] ?? null;
    $DiretoriaIDAtual = $user['DiretoriaID'] ?? null;
    $GerenciaIDAtual  = $user['GerenciaID']  ?? null;
    $badgeClass       = getBadgeClass($permissao);

    $grupoNome = ''; foreach ($grupos as $g)     if ($g['ID_Grupo']    == $grupoIdAtual)    { $grupoNome = $g['Nome_Grupo']; break; }
    $dirNome   = ''; foreach ($diretorias as $d) if ($d['DiretoriaId'] == $DiretoriaIDAtual){ $dirNome   = $d['NomeDiretoria']; break; }
    $gerNome   = ''; foreach ($gerencias as $ger)if ($ger['GerenciaID']== $GerenciaIDAtual) { $gerNome   = $ger['NomeGerencia']; break; }
    ?>
    <div class="user-card-mobile"
         data-user-id="<?= $uid ?>"
         data-grupo-id="<?= $grupoIdAtual ?>"
         data-permissao="<?= $permissao ?>"
         data-search="<?= htmlspecialchars(mb_strtolower($nome . ' ' . $usuario . ' ' . $email)) ?>">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="d-flex align-items-center">
                <div class="icon-circle bg-light text-primary me-3"><i class="fas fa-user"></i></div>
                <div>
                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($nome) ?></h6>
                    <small class="text-muted">ID: <?= $uid ?></small>
                </div>
            </div>
            <span class="badge <?= $badgeClass ?> badge-permissao"><?= htmlspecialchars($funcao) ?></span>
        </div>

        <small class="d-block text-muted mb-1"><i class="fas fa-at me-1"></i><?= htmlspecialchars($usuario) ?></small>
        <small class="d-block text-muted mb-1"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($email) ?></small>
        <?php if ($creci): ?>
            <small class="d-block text-muted mb-1"><i class="fas fa-id-card me-1"></i>CRECI: <?= htmlspecialchars($creci) ?></small>
        <?php endif; ?>

        <div class="row g-2 my-2">
            <div class="col-6">
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-ativo" type="checkbox" role="switch" data-user-id="<?= $uid ?>" <?= $ativo ? 'checked' : '' ?>>
                    <label class="form-check-label">
                        <span class="status-indicator <?= $ativo ? 'status-active' : 'status-inactive' ?>"></span>
                        <span class="status-text"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
                    </label>
                </div>
            </div>
            <div class="col-6">
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-licenca" type="checkbox" role="switch" data-user-id="<?= $uid ?>" <?= $licenca ? 'checked' : '' ?>>
                    <label class="form-check-label">
                        <span class="status-indicator <?= $licenca ? 'status-active' : 'status-inactive' ?>"></span>
                        <span class="status-text"><?= $licenca ? 'Licenciada' : 'Sem Licença' ?></span>
                    </label>
                </div>
            </div>
        </div>

        <small class="text-muted d-block mb-1"><i class="fas fa-users me-1"></i>Grupo: <b><?= $grupoNome ?: '—' ?></b></small>
        <small class="text-muted d-block mb-1"><i class="fas fa-building me-1"></i>Diretoria: <b><?= $dirNome ?: '—' ?></b></small>
        <small class="text-muted d-block mb-2"><i class="fas fa-sitemap me-1"></i>Gerência: <b><?= $gerNome ?: '—' ?></b></small>

        <div class="d-flex gap-2 justify-content-end">
            <a href="usr_admin_editar3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-warning btn-sm" title="Editar" target="_blank"><i class="fas fa-pencil-alt"></i></a>
            <a href="usr_email_credencial.php?UserID=<?= $uid ?>" class="btn-action btn btn-outline-info btn-sm" title="Enviar Credenciais" onclick="return confirm('Enviar credenciais?')"><i class="fas fa-envelope"></i></a>
            <a href="usr_admin_excluir3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-danger btn-sm" title="Excluir" onclick="return confirm('Confirma exclusão permanente?')"><i class="fas fa-trash"></i></a>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunny • Gestão de Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --secondary: <?= $themeSafe['primary_dark'] ?>;
            --success: #06d6a0;
            --danger: #ef476f;
            --warning: #ffd166;
            --info: #118ab2;
            --dark: <?= $themeSafe['text_color'] ?>;
            --light: <?= $themeSafe['bg_body'] ?>;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        body {
            background: <?= $themeSafe['bg_body'] ?>;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            color: var(--dark);
        }
        .glass-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: var(--box-shadow);
            overflow: visible;
        }
        .header-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 1.5rem 2rem;
            color: #fff;
        }
        .stat-card {
            border-radius: 12px; padding: 1.25rem; border: none;
            transition: transform 0.3s ease; min-height: 120px;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.total              { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; }
        .stat-card.ativos             { background: linear-gradient(135deg, #06d6a0, #04b489); color: white; }
        .stat-card.licenciados        { background: linear-gradient(135deg, #ffd166, #ffb142); color: white; }
        .stat-card.ativos-licenciados { background: linear-gradient(135deg, #0f67fe, #0a4cbe); color: white; }
        .table-header {
            background: linear-gradient(90deg, var(--success), #04b489);
            color: white; padding: 1rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 600;
        }
        .table-header-secondary {
            background: linear-gradient(90deg, #6c757d, #495057);
            color: white; padding: 1rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 600;
        }
        .table thead { background: linear-gradient(90deg, var(--primary), var(--secondary)); color: white; }
        .table th { font-weight: 600; border-bottom: none; padding: 1rem; white-space: nowrap; }
        .table td { padding: 1rem; vertical-align: middle; border-color: rgba(0,0,0,0.05); }
        .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(67,97,238,0.03); }
        .table-hover tbody tr:hover { background-color: rgba(67,97,238,0.08); }
        .badge-permissao { padding: 0.5em 1em; border-radius: 50px; font-weight: 600; font-size: 0.85rem; border: none; }
        .badge-superadmin  { background: linear-gradient(135deg, #ef476f, #e63946); color: white; }
        .badge-admin       { background: linear-gradient(135deg, #ffd166, #ffb142); color: var(--dark); }
        .badge-gestor      { background: linear-gradient(135deg, #06d6a0, #04b489); color: white; }
        .badge-editor      { background: linear-gradient(135deg, #118ab2, #0a6c8f); color: white; }
        .badge-corretor    { background: linear-gradient(135deg, #7209b7, #560bad); color: white; }
        .badge-visualizador{ background: linear-gradient(135deg, #6c757d, #495057); color: white; }
        .form-switch .form-check-input {
            width: 3.5em; height: 1.8em; cursor: pointer;
            border-color: #dee2e6; box-shadow: none;
        }
        .form-switch .form-check-input:checked { background-color: var(--success); border-color: var(--success); }
        .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; border: 1px solid #dee2e6; min-height: 38px; padding: 0.25rem 0.5rem; }
        .btn-action {
            width: 36px; height: 36px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.3s ease;
        }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .status-indicator { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; }
        .status-active   { background-color: var(--success); }
        .status-inactive { background-color: var(--danger); }
        .user-card-mobile { display: none; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1); padding: 1.25rem; margin-bottom: 1rem; background: white; }
        @media (max-width: 768px) {
            .table-container { display: none; }
            .user-card-mobile { display: block; }
        }
        .fade-in { animation: fadeIn 0.5s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .loader {
            display: inline-block; width: 20px; height: 20px;
            border: 3px solid rgba(255,255,255,0.3); border-radius: 50%;
            border-top-color: white; animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .icon-circle {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-right: 1rem; font-size: 1.5rem;
        }
        .section-divider { height: 3px; background: linear-gradient(90deg, var(--success), var(--primary), var(--danger)); margin: 2rem 0; border-radius: 3px; }
    </style>
</head>
<body class="fade-in">
<div class="container-fluid py-3 py-md-4">

    <!-- Header + Stats -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card">
                <div class="header-gradient">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                        <div class="d-flex align-items-center mb-3 mb-md-0">
                            <div class="icon-circle bg-white text-primary me-3"><i class="fas fa-users"></i></div>
                            <div>
                                <h1 class="h3 mb-1">Gestão de Usuários</h1>
                                <p class="mb-0 opacity-75">Gerencie colaboradores, permissões e acessos</p>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="usr_admin_novo_usuario3.php" class="btn btn-light btn-sm shadow-sm"><i class="fas fa-user-plus me-1"></i> Novo Usuário</a>
                            <a href="usr_grupo_list.php" class="btn btn-outline-light btn-sm shadow-sm"><i class="fas fa-users-gear me-1"></i> Grupos</a>
                        </div>
                    </div>
                </div>

                <div class="row g-3 p-4">
                    <div class="col-md-3">
                        <div class="stat-card total">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-primary me-3"><i class="fas fa-users"></i></div>
                                <div><h3 class="mb-0"><?= $stats['total'] ?></h3><p class="mb-0 opacity-75">Total</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card ativos-licenciados">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-primary me-3"><i class="fas fa-check-double"></i></div>
                                <div><h3 class="mb-0"><?= $stats['ativos_e_licenciados'] ?></h3><p class="mb-0 opacity-75">Ativos + Licenciados</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card ativos">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-success me-3"><i class="fas fa-user-check"></i></div>
                                <div><h3 class="mb-0"><?= $stats['ativos'] ?></h3><p class="mb-0 opacity-75">Ativos</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card licenciados">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-warning me-3"><i class="fas fa-id-card"></i></div>
                                <div><h3 class="mb-0"><?= $stats['licenciados'] ?></h3><p class="mb-0 opacity-75">Licenciados</p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="mx-4 mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" placeholder="Buscar nome, e-mail ou usuário…" id="searchInput">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="">Todos os status</option>
                                <option value="ativo">Apenas ativos</option>
                                <option value="inativo">Apenas inativos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="filterPermission">
                                <option value="">Todas permissões</option>
                                <option value="1">SUPERADMIN</option>
                                <option value="2">ADMIN</option>
                                <option value="3">GESTOR-EDITOR</option>
                                <option value="4">EDITOR</option>
                                <option value="5">CORRETOR</option>
                                <option value="6">CORRETOR-EDITOR</option>
                                <option value="7">VISUALIZADOR</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="filterGrupo">
                                <option value="">Todos os grupos</option>
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?= $g['ID_Grupo'] ?>"><?= htmlspecialchars($g['Nome_Grupo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="resetFilters()"><i class="fas fa-redo"></i> Limpar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABELA 1 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card table-container">
                <div class="table-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Usuários Ativos e Licenciados</strong>
                        <span class="badge bg-white text-success ms-2"><?= count($usersAtivosLicenciados) ?></span>
                    </div>
                    <small class="opacity-75">Ativo = 1 e Licença = 1</small>
                </div>
                <div class="table-responsive">
                    <table id="tabelaAtivosLicenciados" class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Usuário / Dados</th>
                                <th width="100">Ativo</th>
                                <th width="120">Licença</th>
                                <th width="120">Função</th>
                                <th width="180">Grupo</th>
                                <th width="180">Diretoria</th>
                                <th width="180">Gerência</th>
                                <th width="160" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersAtivosLicenciados as $u): ?>
                                <?= renderUserRow($u, $grupos, $diretorias, $gerencias) ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row"><div class="col-12"><div class="section-divider"></div></div></div>

    <!-- TABELA 2 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card table-container">
                <div class="table-header-secondary d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Demais Usuários</strong>
                        <span class="badge bg-white text-secondary ms-2"><?= count($usersDemais) ?></span>
                    </div>
                    <small class="opacity-75">Inativos ou Sem Licença</small>
                </div>
                <div class="table-responsive">
                    <table id="tabelaDemais" class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Usuário / Dados</th>
                                <th width="100">Ativo</th>
                                <th width="120">Licença</th>
                                <th width="120">Função</th>
                                <th width="180">Grupo</th>
                                <th width="180">Diretoria</th>
                                <th width="180">Gerência</th>
                                <th width="160" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersDemais as $u): ?>
                                <?= renderUserRow($u, $grupos, $diretorias, $gerencias) ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile -->
    <div class="row d-md-none">
        <div class="col-12 mb-3"><h5 class="text-success"><i class="fas fa-check-circle me-2"></i>Ativos e Licenciados</h5></div>
        <?php foreach ($usersAtivosLicenciados as $u) renderMobileCard($u, $grupos, $diretorias, $gerencias); ?>
        <div class="col-12 mb-3 mt-4"><h5 class="text-secondary"><i class="fas fa-exclamation-triangle me-2"></i>Demais Usuários</h5></div>
        <?php foreach ($usersDemais as $u) renderMobileCard($u, $grupos, $diretorias, $gerencias); ?>
    </div>

    <div class="row mt-4">
        <div class="col-12 text-center text-muted small">
            <span>Sunny System • <?= date('Y') ?> • v3.2 (corrigido)</span>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function () {
    'use strict';

    // 🔒 CSRF token para AJAX
    const CSRF_TOKEN = <?= json_encode($CSRF) ?>;

    // ============================================================
    // AJAX helper
    // ============================================================
    async function ajaxPost(data) {
        const url = window.location.pathname + '?ajax=1';
        const fd = new FormData();
        fd.append('csrf_token', CSRF_TOKEN);
        for (const k in data) fd.append(k, data[k]);

        const res = await fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return await res.json();
    }

    function showToast(msg, type) {
        Swal.mixin({
            toast: true, position: 'top-end',
            showConfirmButton: false, timer: 3000, timerProgressBar: true
        }).fire({ icon: type || 'success', title: msg });
    }

    function addLoading($el) {
        $el.prop('disabled', true);
        if (!$el.next('.loader').length) {
            $el.after('<span class="loader ms-2" style="border-top-color:#4361ee"></span>');
        }
    }
    function removeLoading($el) {
        $el.prop('disabled', false).next('.loader').remove();
    }

    // ============================================================
    // Boot
    // ============================================================
    $(document).ready(function () {

        // Inicializa DataTables 2.x (sem `responsive: true`, sem jQuery bridge)
const ptBR = {
    "emptyTable": "Nenhum registro encontrado",
    "info": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
    "infoFiltered": "(Filtrados de _MAX_ registros)",
    "infoThousands": ".",
    "loadingRecords": "Carregando...",
    "zeroRecords": "Nenhum registro encontrado",
    "search": "Pesquisar",
    "paginate": {
        "next": "Próximo",
        "previous": "Anterior",
        "first": "Primeiro",
        "last": "Último"
    },
    "aria": {
        "sortAscending": ": Ordenar colunas de forma ascendente",
        "sortDescending": ": Ordenar colunas de forma descendente"
    },
    "lengthMenu": "Exibir _MENU_ resultados por página"
    // Adicione outras chaves conforme necessário
};

const tableAtivos = new DataTable('#tabelaAtivosLicenciados', {
    order: [[0, 'desc']],
    pageLength: 50,
    language: ptBR, // <-- Objeto local, sem URL
    columnDefs: [
        { orderable: false, targets: [2,3,5,6,7,8] }
    ]
});

const tableDemais = new DataTable('#tabelaDemais', {
    order: [[0, 'desc']],
    pageLength: 50,
    language: ptBR, // <-- Objeto local, sem URL
    columnDefs: [
        { orderable: false, targets: [2,3,5,6,7,8] }
    ]
});

        // Select2 — usa 'body' como pai (corrige bug #6: dropdowns da 2ª tabela)
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $('body')
        });

        // Inicializa valor "prev" de todos os selects
        $('select').each(function () { $(this).data('prev', $(this).val()); });

        // ============================================================
        // Filtros via ext.search (bug #4: preserva paginação)
        // ============================================================
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaAtivosLicenciados' &&
                settings.nTable.id !== 'tabelaDemais') return true;

            const tr = settings.aoData[dataIndex].nTr;
            const $tr = $(tr);

            const status     = $('#filterStatus').val();
            const permission = $('#filterPermission').val();
            const grupo      = $('#filterGrupo').val();

            const isAtivo       = $tr.find('.toggle-ativo').is(':checked');
            const rowPermissao  = String($tr.data('permissao') ?? '');
            const rowGrupoId    = String($tr.data('grupo-id') ?? '');

            if (status === 'ativo' && !isAtivo) return false;
            if (status === 'inativo' && isAtivo) return false;
            if (permission && rowPermissao !== String(permission)) return false;
            if (grupo && rowGrupoId !== String(grupo)) return false;
            return true;
        });

        function redrawFilters() {
            tableAtivos.draw();
            tableDemais.draw();
            // mobile (usa .each().toggle() — funciona)
            const term = ($('#searchInput').val() || '').toLowerCase();
            const status = $('#filterStatus').val();
            const perm = $('#filterPermission').val();
            const grupo = $('#filterGrupo').val();

            $('.user-card-mobile').each(function () {
                const $c = $(this);
                const search = ($c.data('search') || '');
                const isAtivo = $c.find('.toggle-ativo').is(':checked');
                const cPerm = String($c.data('permissao') ?? '');
                const cGrupo = String($c.data('grupo-id') ?? '');

                let show = true;
                if (term && !search.includes(term)) show = false;
                if (status === 'ativo' && !isAtivo) show = false;
                if (status === 'inativo' && isAtivo) show = false;
                if (perm && cPerm !== String(perm)) show = false;
                if (grupo && cGrupo !== String(grupo)) show = false;
                $c.toggle(show);
            });
        }

        // Filtros
        $('#searchInput').on('input', function () {
            const v = $(this).val();
            tableAtivos.search(v).draw();
            tableDemais.search(v).draw();
            redrawFilters();
        });
        $('#filterStatus, #filterPermission, #filterGrupo').on('change', redrawFilters);

        window.resetFilters = function () {
            $('#searchInput').val('');
            $('#filterStatus, #filterPermission, #filterGrupo').val('');
            tableAtivos.search('').draw();
            tableDemais.search('').draw();
            redrawFilters();
        };

        // ============================================================
        // Toggle Ativo
        // ============================================================
        $(document).on('change', '.toggle-ativo', async function () {
            const $cb = $(this);
            const uid = $cb.data('user-id');
            const ativo = $cb.is(':checked') ? 1 : 0;
            addLoading($cb);
            try {
                const res = await ajaxPost({ userId: uid, field: 'ativo', value: ativo });
                if (res.status === 'success') {
                    $cb.closest('.form-check').find('.status-text').text(ativo ? 'Ativo' : 'Inativo');
                    $cb.closest('.form-check').find('.status-indicator')
                        .removeClass('status-active status-inactive')
                        .addClass(ativo ? 'status-active' : 'status-inactive');
                    showToast(ativo ? 'Ativado!' : 'Inativado!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    $cb.prop('checked', !ativo);
                    showToast(res.message || 'Erro', 'error');
                }
            } catch (e) {
                $cb.prop('checked', !ativo);
                showToast('Erro de comunicação', 'error');
            } finally {
                removeLoading($cb);
            }
        });

        // ============================================================
        // Toggle Licença
        // ============================================================
        $(document).on('change', '.toggle-licenca', async function () {
            const $cb = $(this);
            const uid = $cb.data('user-id');
            const lic = $cb.is(':checked') ? 1 : 0;
            addLoading($cb);
            try {
                const res = await ajaxPost({ userId: uid, field: 'licenca', value: lic });
                if (res.status === 'success') {
                    $cb.closest('.form-check').find('.status-text').text(lic ? 'Licenciada' : 'Sem Licença');
                    $cb.closest('.form-check').find('.status-indicator')
                        .removeClass('status-active status-inactive')
                        .addClass(lic ? 'status-active' : 'status-inactive');
                    showToast(lic ? 'Licença ativada!' : 'Licença removida!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    $cb.prop('checked', !lic);
                    showToast(res.message || 'Erro', 'error');
                }
            } catch (e) {
                $cb.prop('checked', !lic);
                showToast('Erro de comunicação', 'error');
            } finally {
                removeLoading($cb);
            }
        });

        // ============================================================
        // Grupo — bug #5: usa .data() não .attr() para invalidar cache
        // ============================================================
        $(document).on('change', '.grupo-select', async function () {
            const $sel = $(this);
            const uid = $sel.data('user-id');
            const val = $sel.val();
            const prev = $sel.data('prev');
            addLoading($sel);
            try {
                const res = await ajaxPost({ userId: uid, field: 'grupo', value: val });
                if (res.status === 'success') {
                    $sel.data('prev', val);
                    // ✅ atualiza CACHE jQuery (não só o atributo DOM)
                    $('tr[data-user-id="' + uid + '"]').data('grupo-id', val || '');
                    $('div.user-card-mobile[data-user-id="' + uid + '"]').data('grupo-id', val || '');
                    showToast('Grupo atualizado!', 'success');
                } else {
                    $sel.val(prev).trigger('change.select2');
                    showToast(res.message || 'Erro', 'error');
                }
            } catch (e) {
                $sel.val(prev).trigger('change.select2');
                showToast('Erro de comunicação', 'error');
            } finally {
                removeLoading($sel);
            }
        });

        // ============================================================
        // Diretoria
        // ============================================================
        $(document).on('change', '.diretoria-select', async function () {
            const $sel = $(this);
            const uid = $sel.data('user-id');
            const val = $sel.val();
            const prev = $sel.data('prev');
            const $gerSel = $sel.closest('tr').find('.gerencia-select');
            addLoading($sel);
            try {
                const res = await ajaxPost({ userId: uid, field: 'diretoria', value: val });
                if (res.status === 'success') {
                    $sel.data('prev', val);
                    if (val) {
                        const resp = await fetch(window.location.pathname + '?ajax=1&action=get-gerencias&DiretoriaId=' + encodeURIComponent(val));
                        const gers = await resp.json();
                        $gerSel.empty().append('<option value="">— Nenhum —</option>');
                        gers.forEach(g => {
                            $gerSel.append('<option value="' + g.GerenciaID + '">' + g.NomeGerencia + '</option>');
                        });
                        $gerSel.prop('disabled', false).trigger('change.select2');
                        $gerSel.data('prev', '');
                    } else {
                        $gerSel.empty().append('<option value="">— Nenhum —</option>');
                        $gerSel.prop('disabled', true).trigger('change.select2');
                        $gerSel.data('prev', '');
                    }
                    showToast('Diretoria atualizada!', 'success');
                } else {
                    $sel.val(prev).trigger('change.select2');
                    showToast(res.message || 'Erro', 'error');
                }
            } catch (e) {
                $sel.val(prev).trigger('change.select2');
                showToast('Erro de comunicação', 'error');
            } finally {
                removeLoading($sel);
            }
        });

        // ============================================================
        // Gerência
        // ============================================================
        $(document).on('change', '.gerencia-select', async function () {
            const $sel = $(this);
            const uid = $sel.data('user-id');
            const val = $sel.val();
            const prev = $sel.data('prev');
            const dirVal = $sel.closest('tr').find('.diretoria-select').val();
            if (val && !dirVal) {
                showToast('Selecione primeiro uma diretoria', 'warning');
                $sel.val('').trigger('change.select2');
                return;
            }
            addLoading($sel);
            try {
                const res = await ajaxPost({ userId: uid, field: 'gerencia', value: val });
                if (res.status === 'success') {
                    $sel.data('prev', val);
                    showToast('Gerência atualizada!', 'success');
                } else {
                    $sel.val(prev).trigger('change.select2');
                    showToast(res.message || 'Erro', 'error');
                }
            } catch (e) {
                $sel.val(prev).trigger('change.select2');
                showToast('Erro de comunicação', 'error');
            } finally {
                removeLoading($sel);
            }
        });

    }); // end ready
})();
</script>
</body>
</html>
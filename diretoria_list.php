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
// LOGIN
// ============================================================
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}
$loggedUser = $_SESSION['Usuario'];

// ⬇️ NOVO: detecta "barreto" (case-insensitive)
$isBarreto = (mb_strtolower(trim((string)$loggedUser)) === 'barreto');

// ============================================================
// CONEXÃO
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// ============================================================
// DETECÇÃO DA COLUNA gerencias.DiretoriaID
// ============================================================
$gerenciaDirCol = 'DiretoriaID';
$gerenciaHasDir = false;
try {
    $colsG = $db->query("SHOW COLUMNS FROM gerencias")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['DiretoriaID', 'DiretoriaId', 'Diretoria_Id', 'DirID'] as $candidate) {
        if (in_array($candidate, $colsG, true)) {
            $gerenciaDirCol = $candidate;
            $gerenciaHasDir = true;
            break;
        }
    }
} catch (Exception $e) {
    error_log("[DIRETORIA_CRUD] detect gerencia col: " . $e->getMessage());
}

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
if (!isset($theme['bg_body']) && isset($theme['body_bg'])) {
    $themeSafe['bg_body'] = $theme['body_bg'];
}

function hexToRgb($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (!ctype_xdigit($hex) || strlen($hex) !== 6) return '0,0,0';
    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
}
$primaryRgb = hexToRgb($themeSafe['primary']);

// ============================================================
// CSRF
// ============================================================
if (empty($_SESSION['csrf_dir'])) {
    $_SESSION['csrf_dir'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_dir'];

// ============================================================
// ESTADOS VÁLIDOS
// ============================================================
$ESTADOS_VALIDOS = ['ATIVO', 'INATIVO'];

// ============================================================
// PROCESSAR POST
// ============================================================
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($CSRF, $_POST['csrf_token'] ?? '')) {
        $_SESSION['MsgErro'] = "Falha de segurança (CSRF). Recarregue a página.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ========================= CREATE / UPDATE =========================
    if ($action === 'create' || $action === 'update') {
        $nome     = trim($_POST['NomeDiretoria']     ?? '');
        $diretor  = trim($_POST['NomeDiretor']       ?? '');
        $telefone = trim($_POST['TelefoneDiretor']   ?? '');
        $userId   = (int)($_POST['UserId']           ?? 0);
        $estado   = strtoupper(trim($_POST['EstadoDiretoria'] ?? 'ATIVO'));

        if (empty($nome)) $erros[] = "O nome da diretoria é obrigatório.";
        if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
            $erros[] = "Estado inválido. Use ATIVO ou INATIVO.";
        }

        if (empty($erros)) {
            try {
                if ($action === 'create') {
                    $stmt = $db->prepare("
                        INSERT INTO diretorias
                            (NomeDiretoria, NomeDiretor, TelefoneDiretor, UserId, EstadoDiretoria)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $nome,
                        $diretor !== '' ? $diretor : null,
                        $telefone !== '' ? $telefone : null,
                        $userId > 0 ? $userId : null,
                        $estado
                    ]);
                    $_SESSION['MsgSucesso'] = "Diretoria <strong>" . htmlspecialchars($nome) . "</strong> criada com sucesso!";
                } else {
                    $id = (int)($_POST['DiretoriaID'] ?? 0);
                    if ($id <= 0) throw new Exception("ID inválido.");

                    $stmt = $db->prepare("
                        UPDATE diretorias
                        SET NomeDiretoria = ?, NomeDiretor = ?, TelefoneDiretor = ?,
                            UserId = ?, EstadoDiretoria = ?
                        WHERE DiretoriaID = ?
                    ");
                    $stmt->execute([
                        $nome,
                        $diretor !== '' ? $diretor : null,
                        $telefone !== '' ? $telefone : null,
                        $userId > 0 ? $userId : null,
                        $estado,
                        $id
                    ]);
                    $_SESSION['MsgSucesso'] = "Diretoria <strong>" . htmlspecialchars($nome) . "</strong> atualizada!";
                }

                header("Location: " . $_SERVER['PHP_SELF']);
                exit;

            } catch (Exception $e) {
                $erros[] = "Erro ao salvar: " . $e->getMessage();
                error_log("[DIRETORIA_CRUD] " . $e->getMessage());
            }
        }

        if (!empty($erros)) {
            $_SESSION['MsgErro'] = implode("<br>", $erros);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // ========================= DELETE =========================
    if ($action === 'delete') {
        $id = (int)($_POST['DiretoriaID'] ?? 0);

        if ($id > 0) {
            try {
                // ── Contagem de dependentes ──
                $countUsr = 0;
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE DiretoriaID = ?");
                    $stmt->execute([$id]);
                    $countUsr = (int)$stmt->fetchColumn();
                } catch (Exception $e) {
                    error_log("[DIRETORIA_CRUD] check usuarios: " . $e->getMessage());
                }

                $countGer = 0;
                if ($gerenciaHasDir) {
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) FROM gerencias WHERE `$gerenciaDirCol` = ?");
                        $stmt->execute([$id]);
                        $countGer = (int)$stmt->fetchColumn();
                    } catch (Exception $e) {
                        error_log("[DIRETORIA_CRUD] check gerencias: " . $e->getMessage());
                    }
                }

                // ⬇️ NOVO: barreto tem override com cascade
                if ($isBarreto) {
                    $db->beginTransaction();
                    try {
                        // Nome antes de excluir
                        $stmt = $db->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
                        $stmt->execute([$id]);
                        $nomeExcluido = $stmt->fetchColumn();

                        // 1) Desvincula usuários (preserva cadastro)
                        if ($countUsr > 0) {
                            $db->prepare("UPDATE usuarios SET DiretoriaID = NULL WHERE DiretoriaID = ?")
                               ->execute([$id]);
                        }

                        // 2) Exclui gerências em cascata
                        if ($countGer > 0 && $gerenciaHasDir) {
                            $db->prepare("DELETE FROM gerencias WHERE `$gerenciaDirCol` = ?")
                               ->execute([$id]);
                        }

                        // 3) Exclui a diretoria
                        $db->prepare("DELETE FROM diretorias WHERE DiretoriaID = ?")
                           ->execute([$id]);

                        $db->commit();

                        // Mensagem de sucesso com detalhes da cascata
                        $extras = [];
                        if ($countGer > 0) $extras[] = "<b>$countGer</b> gerência(s) excluída(s)";
                        if ($countUsr > 0) $extras[] = "<b>$countUsr</b> usuário(s) desvinculado(s)";

                        $_SESSION['MsgSucesso'] = "Diretoria <strong>" . htmlspecialchars($nomeExcluido ?: "#$id") . "</strong> excluída.";
                        if (!empty($extras)) {
                            $_SESSION['MsgSucesso'] .= " <small style='opacity:0.85;'>( " . implode(" · ", $extras) . " )</small>";
                        }

                        // Log admin
                        error_log("[DIRETORIA_CRUD] ADMIN 'barreto' excluiu DiretoriaID=$id (gerências=$countGer, usuários desvinculados=$countUsr)");

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }

                } else {
                    // ── Fluxo normal (com bloqueios) ──
                    $bloqueios = [];
                    if ($countUsr > 0) $bloqueios[] = "<b>$countUsr</b> usuário(s)";
                    if ($countGer > 0) $bloqueios[] = "<b>$countGer</b> gerência(s)";

                    if (!empty($bloqueios)) {
                        $_SESSION['MsgErro'] = "Não é possível excluir: existe(m) " . implode(" e ", $bloqueios) . " vinculado(s) a esta diretoria.";
                    } else {
                        $stmt = $db->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
                        $stmt->execute([$id]);
                        $nomeExcluido = $stmt->fetchColumn();

                        $db->prepare("DELETE FROM diretorias WHERE DiretoriaID = ?")->execute([$id]);
                        $_SESSION['MsgSucesso'] = "Diretoria <strong>" . htmlspecialchars($nomeExcluido ?: "#$id") . "</strong> excluída.";
                    }
                }

                header("Location: " . $_SERVER['PHP_SELF']);
                exit;

            } catch (Exception $e) {
                $_SESSION['MsgErro'] = "Erro ao excluir: " . $e->getMessage();
                error_log("[DIRETORIA_CRUD] " . $e->getMessage());
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

// ============================================================
// CARREGAR DADOS
// ============================================================
$diretorias = [];
$usuarios   = [];
$erro       = null;

// Subqueries de contagem
$qtdGerSelect = "0 AS QtdGerencias";
if ($gerenciaHasDir) {
    $qtdGerSelect = "(SELECT COUNT(*) FROM gerencias g WHERE g.`$gerenciaDirCol` = d.DiretoriaID) AS QtdGerencias";
}
$qtdUsrSelect = "(SELECT COUNT(*) FROM usuarios usr WHERE usr.DiretoriaID = d.DiretoriaID) AS QtdUsuarios";

try {
    $stmt = $db->prepare("
        SELECT d.DiretoriaID, d.NomeDiretoria, d.NomeDiretor, d.TelefoneDiretor,
               d.UserId, d.EstadoDiretoria,
               u.Nome AS NomeUsuario, u.Usuario,
               $qtdGerSelect,
               $qtdUsrSelect
        FROM diretorias d
        LEFT JOIN usuarios u ON d.UserId = u.UserId
        ORDER BY d.NomeDiretoria ASC
    ");
    $stmt->execute();
    $diretorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usuarios = $db->query("
        SELECT UserId, Usuario, Nome
        FROM usuarios
        WHERE Ativo = 1
        ORDER BY Nome ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erro = $e->getMessage();
    error_log("[DIRETORIA_LIST] " . $e->getMessage());
}

// ============================================================
// ESTATÍSTICAS
// ============================================================
$total     = count($diretorias);
$comUser   = 0;
$semUser   = 0;
$ativas    = 0;
$inativas  = 0;
$comGer    = 0;
$totalGer  = 0;

foreach ($diretorias as $d) {
    if (!empty($d['UserId']) && (int)$d['UserId'] > 0) $comUser++;
    else $semUser++;

    $est = strtoupper(trim((string)($d['EstadoDiretoria'] ?? '')));
    if ($est === 'ATIVO')        $ativas++;
    elseif ($est === 'INATIVO')  $inativas++;

    $qg = (int)($d['QtdGerencias'] ?? 0);
    if ($qg > 0) { $comGer++; $totalGer += $qg; }
}

// JSON para o JS
$diretoriasJson = json_encode($diretorias, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if (json_last_error() !== JSON_ERROR_NONE) $diretoriasJson = '[]';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diretorias · SunnyImob</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --primary-dark: <?= $themeSafe['primary_dark'] ?>;
            --primary-rgb: <?= $primaryRgb ?>;
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
            padding-bottom: 40px;
            -webkit-font-smoothing: antialiased;
        }

        .container-neo { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        /* HEADER */
        .app-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 50;
            margin-bottom: 24px;
        }
        .app-header::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .header-logo {
            height: 54px; width: auto; max-width: 150px; min-width: 54px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 4px 8px;
            flex-shrink: 0;
        }
        .header-title h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 900;
            letter-spacing: -0.3px;
            line-height: 1.15;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .header-title p {
            margin: 4px 0 0;
            font-size: 0.74rem;
            font-weight: 600;
            opacity: 0.9;
        }

        /* ⬇️ NOVO: badge modo admin */
        .badge-admin {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--warning);
            color: #1f2937;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            padding: 3px 10px;
            font-size: 0.64rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-left: 4px;
        }

        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }
        .btn-header {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-header:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: rgba(255,255,255,0.25);
            color: #fff;
        }
        .btn-header.primary { background: var(--primary-dark); }
        .btn-header.danger  { background: var(--danger); }

        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.15s;
        }
        .stat-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            flex-shrink: 0;
        }
        .stat-icon.i-primary  { background: var(--primary); }
        .stat-icon.i-success  { background: var(--success); }
        .stat-icon.i-danger   { background: var(--danger); }
        .stat-icon.i-warning  { background: var(--warning); color: #000; }
        .stat-icon.i-info     { background: var(--info); }
        .stat-info { min-width: 0; }
        .stat-num {
            font-size: 1.5rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.8px;
        }
        .stat-lbl {
            font-size: 0.64rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            opacity: 0.6;
            margin-top: 4px;
        }

        /* TOOLBAR */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; flex-wrap: wrap; margin-bottom: 16px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1 1 320px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }

        .search-box {
            position: relative;
            display: flex; align-items: center;
            flex: 1 1 320px;
            max-width: 480px;
        }
        .search-box input {
            width: 100%;
            background: var(--card-bg);
            color: var(--text-color);
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 10px 44px 10px 44px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .search-box input:focus {
            outline: none;
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .search-box input::placeholder { color: var(--text-color); opacity: 0.5; font-weight: 500; }
        .search-box .icon {
            position: absolute; left: 14px;
            color: var(--text-color); opacity: 0.6;
            pointer-events: none; font-size: 14px;
        }
        .search-box:focus-within .icon { color: var(--primary); opacity: 1; }
        .search-box .clear {
            position: absolute; right: 10px;
            background: var(--danger); color: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 50%;
            width: 24px; height: 24px;
            cursor: pointer;
            display: none; align-items: center; justify-content: center;
            font-size: 10px; padding: 0;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .search-box .clear.visible { display: flex; }

        /* BOTÕES */
        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            text-decoration: none;
        }
        .btn-neo:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 0 var(--shadow-color); }
        .btn-neo:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 0 var(--shadow-color); }
        .btn-neo-primary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo-primary:hover { background: var(--btn-hover); color: #fff; }
        .btn-neo-secondary { background: #fff; color: var(--text-color); }
        .btn-neo-secondary:hover { background: #f8f9fb; color: var(--text-color); }

        .btn-icon {
            width: 32px; height: 32px;
            padding: 0;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            display: inline-flex;
            align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            font-size: 0.78rem;
            text-decoration: none;
            background: #fff;
            color: var(--text-color);
        }
        .btn-icon:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); color: var(--text-color); }
        .btn-icon.edit   { background: var(--warning); color: #000; }
        .btn-icon.delete { background: var(--danger);  color: #fff; }
        .btn-icon.delete.is-blocked {
            background: #9ca3af;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .btn-icon.delete.is-blocked:hover { transform: none; box-shadow: 2px 2px 0 0 var(--shadow-color); }
        /* ⬇️ NOVO: barreto com cascade pendente */
        .btn-icon.delete.has-cascade {
            background: var(--warning);
            color: #1f2937;
        }
        .btn-icon.delete.has-cascade:hover {
            background: #d97706;
            color: #fff;
        }

        /* TABELA */
        .table-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
            padding: 6px;
        }
        table.dataTable.table-neo {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: var(--card-bg);
            color: var(--text-color);
            margin: 0 !important;
        }
        table.dataTable.table-neo thead th {
            background: var(--primary) !important;
            color: #fff !important;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 1px;
            padding: 14px 12px !important;
            border: none !important;
            border-bottom: 3px solid var(--shadow-color) !important;
        }
        table.dataTable.table-neo thead th:first-child { border-top-left-radius: 10px; }
        table.dataTable.table-neo thead th:last-child  { border-top-right-radius: 10px; }
        table.dataTable.table-neo tbody td {
            padding: 11px 12px !important;
            font-size: 13px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }

        .cell-id {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.74rem;
            background: rgba(0,0,0,0.06);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 800;
        }
        .cell-nome { font-weight: 800; font-size: 0.86rem; }
        .cell-user {
            font-family: 'SFMono-Regular', Consolas, monospace;
            background: rgba(0,0,0,0.06);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        /* Badge de gerências vinculadas */
        .badge-gerencias {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-left: 6px;
            padding: 2px 8px;
            background: rgba(59,130,246,0.15);
            color: #1e40af;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            border: 1.5px solid rgba(59,130,246,0.4);
            vertical-align: middle;
        }
        /* ⬇️ NOVO: badge cascade (barreto) */
        .badge-gerencias.cascade {
            background: rgba(245,158,11,0.18);
            color: #92400e;
            border-color: rgba(245,158,11,0.5);
        }

        /* Badge ATIVO / INATIVO */
        .badge-estado {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .badge-estado.ativo   { background: var(--success); color: #fff; }
        .badge-estado.inativo { background: var(--danger);  color: #fff; }
        .badge-estado.empty {
            background: rgba(0,0,0,0.08);
            color: var(--text-color);
            opacity: 0.5;
            box-shadow: none;
            text-transform: none;
        }

        /* DataTables */
        .dataTables_wrapper .dataTables_filter { display: none; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info {
            padding: 12px 8px !important;
            font-size: 13px; font-weight: 600;
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 4px 8px;
            background: #fff; color: var(--text-color);
            font-weight: 700; margin: 0 4px;
        }
        .dataTables_wrapper .dataTables_paginate { padding: 12px 8px !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-color) !important;
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 5px 12px !important;
            margin: 0 2px !important;
            font-weight: 700 !important;
            background: #fff !important;
            font-size: 13px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary) !important;
            color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--shadow-color) !important;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.4; transform: none; box-shadow: none;
        }
        .dataTables_wrapper .dataTables_empty {
            padding: 40px 20px !important;
            font-weight: 700 !important;
            opacity: 0.6 !important;
            font-style: italic;
        }

        /* Alert */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 700; font-size: 0.88rem;
            margin-bottom: 18px;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .alert-neo.success { background: var(--success); color: #fff; }
        .alert-neo.danger  { background: var(--danger);  color: #fff; }

        /* MODAL */
        .modal-content {
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 8px 8px 0 0 var(--shadow-color);
            overflow: hidden;
        }
        .modal-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 3px solid var(--shadow-color);
            padding: 16px 22px;
        }
        .modal-title {
            font-weight: 900;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header .btn-close {
            filter: invert(1) brightness(2);
            opacity: 1;
        }
        .modal-body { padding: 22px; background: var(--card-bg); }
        .modal-footer {
            background: var(--card-bg);
            border-top: 3px solid var(--shadow-color);
            padding: 16px 22px;
            gap: 10px;
        }

        /* Form */
        .field-label {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.74rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.4px;
            color: var(--text-color); opacity: 0.85;
            margin-bottom: 6px;
        }
        .field-label i { color: var(--primary); font-size: 0.82rem; }
        .field-label .req { color: var(--danger); }

        .form-control, .form-select {
            border: 2px solid var(--shadow-color);
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

        /* FOOTER */
        .page-footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px 10px;
            font-size: 0.78rem;
            color: var(--text-color);
            opacity: 0.6;
            font-weight: 500;
        }
        .page-footer strong { color: var(--primary); opacity: 1; }

        /* Responsivo */
        @media (max-width: 768px) {
            .app-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; }
            .header-logo { height: 40px; max-width: 100px; }
            .header-title h1 { font-size: 0.95rem; }
            .header-title p { font-size: 0.68rem; }
            .header-actions { width: 100%; }
            .btn-header { flex: 1; justify-content: center; }
            .container-neo { padding: 0 12px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar-left, .toolbar-right { width: 100%; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .btn-neo { flex: 1; justify-content: center; }
            .modal-body { padding: 16px; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logo" class="header-logo" onerror="this.style.display='none'">
            <div class="header-title">
                <h1>
                    <i class="fas fa-building"></i> Diretorias
                    <?php if ($isBarreto): ?>
                        <span class="badge-admin" title="Você pode excluir mesmo com dependências (cascade)">
                            <i class="fas fa-shield-halved"></i> Modo Admin
                        </span>
                    <?php endif; ?>
                </h1>
                <p>Gestão de estrutura organizacional · <?= $total ?> registros</p>
            </div>
        </div>
        <div class="header-actions">
            <button type="button" class="btn-header primary" onclick="abrirCriacao()">
                <i class="fas fa-plus"></i> Nova Diretoria
            </button>
            <a href="#" onclick="window.close(); return false;" class="btn-header danger">
                <i class="fas fa-times"></i> Fechar
            </a>
        </div>
    </div>
</header>

<div class="container-neo">

    <?php if (isset($_SESSION['MsgSucesso'])): ?>
        <div class="alert-neo success">
            <i class="fas fa-check-circle"></i>
            <div><?= $_SESSION['MsgSucesso']; unset($_SESSION['MsgSucesso']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['MsgErro'])): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= $_SESSION['MsgErro']; unset($_SESSION['MsgErro']); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div>Erro ao carregar: <?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon i-primary"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $total ?></div>
                <div class="stat-lbl">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $ativas ?></div>
                <div class="stat-lbl">Ativas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-danger"><i class="fas fa-ban"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $inativas ?></div>
                <div class="stat-lbl">Inativas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-info"><i class="fas fa-sitemap"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $totalGer ?></div>
                <div class="stat-lbl">Gerências (<?= $comGer ?> dir.)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-warning"><i class="fas fa-user-slash"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $semUser ?></div>
                <div class="stat-lbl">Sem usuário</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar diretoria, diretor, usuário…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="toolbar-right">
            <button type="button" class="btn-neo btn-neo-primary" onclick="abrirCriacao()">
                <i class="fas fa-plus"></i> Nova Diretoria
            </button>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-card">
        <table id="tabelaDiretorias" class="table table-neo" style="width:100%">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Diretoria</th>
                    <th>Diretor</th>
                    <th style="width: 150px;">Telefone</th>
                    <th style="width: 130px;">Usuário</th>
                    <th>Nome do Usuário</th>
                    <th style="width: 110px;">Estado</th>
                    <th style="width: 100px;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($diretorias) && !$erro): ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:40px 20px; opacity:0.6; font-weight:700; font-style:italic;">
                        <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                        Nenhuma diretoria cadastrada. Clique em "Nova Diretoria".
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($diretorias as $d):
                    $id       = (int)$d['DiretoriaID'];
                    $nomeDir  = $d['NomeDiretoria'] ?? '—';
                    $diretor  = $d['NomeDiretor']   ?? '—';
                    $telefone = $d['TelefoneDiretor'] ?? '—';
                    $usuario  = $d['Usuario']        ?? '';
                    $nomeUsr  = $d['NomeUsuario']    ?? '';
                    $estado   = strtoupper(trim((string)($d['EstadoDiretoria'] ?? '')));
                    $qtdGer   = (int)($d['QtdGerencias'] ?? 0);
                    $qtdUsr   = (int)($d['QtdUsuarios']  ?? 0);

                    $badgeCls = '';
                    $badgeIcon = '';
                    $badgeTxt = '—';
                    if ($estado === 'ATIVO') {
                        $badgeCls = 'ativo';
                        $badgeIcon = 'fa-check-circle';
                        $badgeTxt = 'Ativo';
                    } elseif ($estado === 'INATIVO') {
                        $badgeCls = 'inativo';
                        $badgeIcon = 'fa-ban';
                        $badgeTxt = 'Inativo';
                    }

                    // ⬇️ Decide comportamento do botão de excluir
                    $hasDeps       = ($qtdGer > 0 || $qtdUsr > 0);
                    $btnBlocked    = ($qtdGer > 0 && !$isBarreto);       // bloqueio só p/ não-barreto
                    $btnCascade    = ($hasDeps && $isBarreto);            // laranja p/ barreto

                    $btnClass = 'btn-icon delete';
                    if ($btnBlocked) $btnClass .= ' is-blocked';
                    if ($btnCascade) $btnClass .= ' has-cascade';

                    $btnIcon  = $btnBlocked ? 'fa-lock' : 'fa-trash';
                    if ($btnCascade) $btnIcon = 'fa-trash-can';

                    $btnTitle = $btnBlocked
                        ? 'Possui gerências vinculadas'
                        : ($btnCascade
                            ? 'Excluir (cascade: ' . $qtdGer . ' ger., ' . $qtdUsr . ' usr.)'
                            : 'Excluir');

                    $badgeGerClass = $btnCascade ? 'badge-gerencias cascade' : 'badge-gerencias';
                ?>
                    <tr data-id="<?= $id ?>">
                        <td><span class="cell-id">#<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></span></td>
                        <td>
                            <span class="cell-nome"><?= htmlspecialchars($nomeDir) ?></span>
                            <?php if ($qtdGer > 0): ?>
                                <span class="<?= $badgeGerClass ?>" title="<?= $qtdGer ?> gerência(s) vinculada(s)">
                                    <i class="fas fa-sitemap"></i> <?= $qtdGer ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($diretor ?: '—') ?></td>
                        <td><?= htmlspecialchars($telefone ?: '—') ?></td>
                        <td>
                            <?php if ($usuario): ?>
                                <span class="cell-user"><?= htmlspecialchars(strtolower($usuario)) ?></span>
                            <?php else: ?>
                                <span style="opacity:0.4;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($nomeUsr): ?>
                                <?= htmlspecialchars($nomeUsr) ?>
                            <?php else: ?>
                                <span style="opacity:0.4;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($badgeCls): ?>
                                <span class="badge-estado <?= $badgeCls ?>">
                                    <i class="fas <?= $badgeIcon ?>"></i> <?= $badgeTxt ?>
                                </span>
                            <?php else: ?>
                                <span class="badge-estado empty">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button type="button" class="btn-icon edit" title="Editar"
                                        onclick="abrirEdicao(<?= $id ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button"
                                        class="<?= $btnClass ?>"
                                        title="<?= htmlspecialchars($btnTitle) ?>"
                                        onclick="confirmarExclusao(<?= $id ?>, <?= htmlspecialchars(json_encode($nomeDir), ENT_QUOTES) ?>, <?= $qtdGer ?>, <?= $qtdUsr ?>)">
                                    <i class="fas <?= $btnIcon ?>"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="page-footer">
        <p style="margin:0;">
            &copy; <?= date('Y') ?> <strong>SunnyImob</strong> · Todos os direitos reservados
        </p>
    </footer>
</div>

<!-- ============================================================ -->
<!-- MODAL CRIAR / EDITAR                                        -->
<!-- ============================================================ -->
<div class="modal fade" id="modalDiretoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="formDiretoria">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="DiretoriaID" id="formId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-plus-circle"></i> Nova Diretoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="field-label" for="fieldNomeDiretoria">
                                <i class="fas fa-building"></i> Nome da Diretoria <span class="req">*</span>
                            </label>
                            <input type="text" class="form-control" id="fieldNomeDiretoria"
                                   name="NomeDiretoria" required maxlength="255"
                                   placeholder="Ex.: Diretoria Comercial">
                        </div>

                        <div class="col-md-6">
                            <label class="field-label" for="fieldNomeDiretor">
                                <i class="fas fa-user-tie"></i> Nome do Diretor
                            </label>
                            <input type="text" class="form-control" id="fieldNomeDiretor"
                                   name="NomeDiretor" maxlength="255"
                                   placeholder="Ex.: João Silva">
                        </div>

                        <div class="col-md-6">
                            <label class="field-label" for="fieldTelefoneDiretor">
                                <i class="fas fa-phone"></i> Telefone do Diretor
                            </label>
                            <input type="text" class="form-control" id="fieldTelefoneDiretor"
                                   name="TelefoneDiretor" maxlength="30"
                                   placeholder="(81) 99999-9999">
                        </div>

                        <div class="col-md-6">
                            <label class="field-label" for="fieldUserId">
                                <i class="fas fa-user"></i> Usuário vinculado
                            </label>
                            <select class="form-select" id="fieldUserId" name="UserId">
                                <option value="">— Nenhum —</option>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= (int)$u['UserId'] ?>">
                                        <?= htmlspecialchars($u['Nome'] ?: $u['Usuario']) ?>
                                        (<?= htmlspecialchars($u['Usuario']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="field-label" for="fieldEstado">
                                <i class="fas fa-toggle-on"></i> Estado <span class="req">*</span>
                            </label>
                            <select class="form-select" id="fieldEstado" name="EstadoDiretoria" required>
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-neo btn-neo-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-neo btn-neo-primary" id="btnSubmit">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- FORM OCULTO PARA DELETE                                     -->
<!-- ============================================================ -->
<form method="POST" id="formDelete" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="DiretoriaID" id="deleteId" value="">
</form>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    const DIRETORIAS = <?= $diretoriasJson ?>;
    const IS_BARRETO = <?= $isBarreto ? 'true' : 'false' ?>;
    const PRIMARY_COLOR = <?= json_encode($themeSafe['primary']) ?>;
    const WARNING_COLOR = '#f59e0b';

    const ptBR = {
        emptyTable:     "Nenhum registro encontrado",
        info:           "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty:      "Mostrando 0 a 0 de 0 registros",
        infoFiltered:   "(filtrado de _MAX_ registros no total)",
        infoThousands:  ".",
        lengthMenu:     "Exibir _MENU_ por página",
        loadingRecords: "Carregando...",
        processing:     "Processando...",
        search:         "Pesquisar:",
        zeroRecords:    "Nenhum registro corresponde à busca",
        paginate: {
            first: "Primeiro", last: "Último",
            next: "Próximo", previous: "Anterior"
        },
        aria: {
            sortAscending:  ": ordenar crescente",
            sortDescending: ": ordenar decrescente"
        }
    };

    // ============================================================
    // DATATABLES
    // ============================================================
    let table = null;
    if (document.getElementById('tabelaDiretorias')) {
        table = new DataTable('#tabelaDiretorias', {
            language: ptBR,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            order: [[1, 'asc']],
            autoWidth: false,
            columnDefs: [
                { orderable: false, targets: [7] },
                { searchable: false, targets: [7] }
            ]
        });
    }

    // ============================================================
    // BUSCA
    // ============================================================
    const $search = document.getElementById('searchInput');
    const $clear  = document.getElementById('searchClear');
    let timer;

    if ($search) {
        $search.addEventListener('input', function () {
            const v = this.value;
            $clear.classList.toggle('visible', v.length > 0);
            clearTimeout(timer);
            timer = setTimeout(() => {
                if (table) table.search(v).draw();
            }, 150);
        });

        $search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { this.value = ''; this.dispatchEvent(new Event('input')); }
        });

        $clear.addEventListener('click', () => {
            $search.value = '';
            $search.dispatchEvent(new Event('input'));
            $search.focus();
        });
    }

    // ============================================================
    // MODAL
    // ============================================================
    const modalEl = document.getElementById('modalDiretoria');
    const modal   = new bootstrap.Modal(modalEl);

    window.abrirCriacao = function () {
        document.getElementById('modalTitle').innerHTML =
            '<i class="fas fa-plus-circle"></i> Nova Diretoria';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formId').value = '';
        document.getElementById('formDiretoria').reset();
        document.getElementById('fieldEstado').value = 'ATIVO';
        document.getElementById('btnSubmit').disabled = false;
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Salvar';
        modal.show();
        setTimeout(() => document.getElementById('fieldNomeDiretoria').focus(), 300);
    };

    window.abrirEdicao = function (id) {
        const d = DIRETORIAS.find(x => Number(x.DiretoriaID) === Number(id));
        if (!d) return;

        document.getElementById('modalTitle').innerHTML =
            '<i class="fas fa-pen"></i> Editar Diretoria';
        document.getElementById('formAction').value = 'update';
        document.getElementById('formId').value = d.DiretoriaID;

        document.getElementById('fieldNomeDiretoria').value = d.NomeDiretoria || '';
        document.getElementById('fieldNomeDiretor').value   = d.NomeDiretor || '';
        document.getElementById('fieldTelefoneDiretor').value = d.TelefoneDiretor || '';
        document.getElementById('fieldUserId').value = d.UserId || '';

        let est = String(d.EstadoDiretoria || '').toUpperCase().trim();
        if (est !== 'ATIVO' && est !== 'INATIVO') est = 'ATIVO';
        document.getElementById('fieldEstado').value = est;

        document.getElementById('btnSubmit').disabled = false;
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Salvar';

        modal.show();
    };

    // ============================================================
    // EXCLUSÃO COM DUPLA CONFIRMAÇÃO
    // ⬇️ Barreto: override (cascade) — sem pré-check de bloqueio
    // ============================================================
    window.confirmarExclusao = function (id, nome, qtdGerencias, qtdUsuarios) {
        qtdGerencias = Number(qtdGerencias) || 0;
        qtdUsuarios  = Number(qtdUsuarios)  || 0;

        // ── Pré-check: bloqueia ANTES (apenas para NÃO-barreto) ──
        if (!IS_BARRETO && qtdGerencias > 0) {
            Swal.fire({
                title: 'Exclusão bloqueada',
                html:
                    `A diretoria <b>${nome}</b> possui ` +
                    `<b style="color:#1e40af;">${qtdGerencias} gerência(s)</b> vinculada(s).<br><br>` +
                    `<span style="font-size:0.88rem;opacity:0.85;">` +
                    `Remova ou reassocie as gerências antes de excluir esta diretoria.` +
                    `</span>`,
                icon: 'error',
                confirmButtonText: 'Entendi',
                confirmButtonColor: PRIMARY_COLOR,
                allowOutsideClick: false
            });
            return;
        }

        // ── Monta HTML da 1ª confirmação ──
        let html1 = `A diretoria <b>${nome}</b> será excluída permanentemente.`;

        if (IS_BARRETO) {
            // Card amarelo explicando a cascata
            if (qtdGerencias > 0 || qtdUsuarios > 0) {
                html1 +=
                    `<div style="text-align:left;background:#fef3c7;padding:12px;border-radius:8px;` +
                    `border:2px solid ${WARNING_COLOR};margin-top:14px;color:#92400e;">` +
                    `<b style="display:block;margin-bottom:6px;font-size:0.9rem;">` +
                    `<i class="fas fa-shield-halved"></i> Modo administrador (barreto)` +
                    `</b>` +
                    `<span style="font-size:0.85rem;line-height:1.6;">` +
                    `Você tem permissão para excluir mesmo com vínculos. ` +
                    `Ao confirmar:` +
                    (qtdGerencias > 0
                        ? `<br>• <b>${qtdGerencias}</b> gerência(s) serão <b>excluídas em cascata</b>`
                        : '') +
                    (qtdUsuarios > 0
                        ? `<br>• <b>${qtdUsuarios}</b> usuário(s) serão <b>desvinculados</b> (cadastro preservado)`
                        : '') +
                    `</span></div>`;
            } else {
                html1 +=
                    `<div style="margin-top:14px;color:#16a34a;font-weight:700;font-size:0.85rem;">` +
                    `<i class="fas fa-check-circle"></i> Sem dependências vinculadas.</div>`;
            }
        } else {
            html1 +=
                `<br><br>` +
                `<span style="color:${WARNING_COLOR};font-weight:700;font-size:0.85rem;">` +
                `<i class="fas fa-exclamation-triangle"></i> ` +
                `Diretorias com usuários vinculados não podem ser excluídas.` +
                `</span>`;
        }

        // ── 1ª CONFIRMAÇÃO ──
        Swal.fire({
            title: 'Excluir diretoria?',
            html: html1,
            icon: IS_BARRETO && (qtdGerencias > 0 || qtdUsuarios > 0) ? 'warning' : 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-arrow-right"></i> Continuar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            focusCancel: true,
            width: 520
        }).then(function (r1) {
            if (!r1.isConfirmed) return;

            // ── HTML da 2ª confirmação ──
            let html2 =
                `Tem <b>certeza absoluta</b> que deseja excluir<br>` +
                `<b style="color:#ef4444;font-size:1.05rem;">${nome}</b>?<br><br>` +
                `<span style="color:#ef4444;font-weight:800;font-size:0.85rem;">` +
                `<i class="fas fa-skull-crossbones"></i> ` +
                `Esta ação NÃO pode ser desfeita.</span>`;

            if (IS_BARRETO && (qtdGerencias > 0 || qtdUsuarios > 0)) {
                html2 +=
                    `<div style="margin-top:14px;padding:10px;background:#fee2e2;` +
                    `border:2px solid #ef4444;border-radius:8px;color:#7f1d1d;` +
                    `font-size:0.82rem;font-weight:700;">` +
                    `<i class="fas fa-triangle-exclamation"></i> ` +
                    `Cascade: ${qtdGerencias} gerência(s) + ${qtdUsuarios} usuário(s) afetado(s)` +
                    `</div>`;
            }

            // ── 2ª CONFIRMAÇÃO (final) ──
            Swal.fire({
                title: 'Confirmação final',
                html: html2,
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Sim, excluir definitivamente',
                cancelButtonText: '<i class="fas fa-shield-alt"></i> Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                focusCancel: true,
                allowOutsideClick: false,
                width: 520
            }).then(function (r2) {
                if (!r2.isConfirmed) return;

                // Só chega aqui após DUAS confirmações explícitas
                document.getElementById('deleteId').value = id;
                document.getElementById('formDelete').submit();
            });
        });
    };

    // ============================================================
    // SUBMIT
    // ============================================================
    document.getElementById('formDiretoria')?.addEventListener('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            this.reportValidity();
            return;
        }
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
    });

    // ============================================================
    // TOAST pós-ação
    // ============================================================
    <?php if (isset($_SESSION['MsgSucesso']) && !empty($_SESSION['MsgSucesso'])): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: <?= json_encode(strip_tags($_SESSION['MsgSucesso'])) ?>,
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false
        });
    <?php endif; ?>

})();
</script>
</body>
</html>
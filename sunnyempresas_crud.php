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
// CONEXÃO
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
// CSRF
// ============================================================
if (empty($_SESSION['csrf_sunnyemp'])) {
    $_SESSION['csrf_sunnyemp'] = bin2hex(random_bytes(32));
}

// ============================================================
// UFs
// ============================================================
$UFs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

// ============================================================
// CAMPOS DO FORMULÁRIO
// NOTA: Conf_CorPrimaria, Conf_CorSecundaria e Conf_CorFundo
//       NÃO são gerenciados aqui — vêm de theme_config.php.
// ============================================================
function coletarCampos() {
    return [
        'EmpresaSunny'        => trim($_POST['EmpresaSunny']        ?? ''),
        'CNPJ'                => trim($_POST['CNPJ']                ?? ''),
        'Endereco'            => trim($_POST['Endereco']            ?? ''),
        'Cidade'              => trim($_POST['Cidade']              ?? ''),
        'UF'                  => strtoupper(trim($_POST['UF']       ?? '')),
        'Responsavel1'        => trim($_POST['Responsavel1']        ?? ''),
        'Email1'              => trim($_POST['Email1']              ?? ''),
        'Telefone1'           => trim($_POST['Telefone1']           ?? ''),
        'Obs'                 => trim($_POST['Obs']                 ?? ''),
        'Conf_ImgLogo'        => trim($_POST['Conf_ImgLogo']        ?? ''),
        'Conf_PastaCarrossel' => trim($_POST['Conf_PastaCarrossel'] ?? ''),
        'Conf_PastaCard'      => trim($_POST['Conf_PastaCard']      ?? ''),
        'Conf_PastaImg'       => trim($_POST['Conf_PastaImg']       ?? ''),
        'Conf_ImgPontoMapa'   => trim($_POST['Conf_ImgPontoMapa']   ?? ''),
        'Conf_MainPage'       => trim($_POST['Conf_MainPage']       ?? ''),
        'Conf_Versao'         => trim($_POST['Conf_Versao']         ?? ''),
    ];
}

// ============================================================
// PROCESSA POST
// ============================================================
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_sunnyemp'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['MsgErro'] = "Falha na verificação de segurança. Recarregue a página.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $fields = coletarCampos();

    // ========================= CREATE =========================
    if ($action === 'create') {
        if (empty($fields['EmpresaSunny'])) {
            $erros[] = "O nome da empresa é obrigatório.";
        }

        if (empty($erros)) {
            try {
                $sql = "INSERT INTO sunnyempresas (
                            EmpresaSunny, CNPJ, Endereco, Cidade, UF,
                            Responsavel1, Email1, Telefone1, Obs,
                            Conf_ImgLogo, Conf_PastaCarrossel, Conf_PastaCard, Conf_PastaImg,
                            Conf_ImgPontoMapa, Conf_MainPage, Conf_Versao, DataCadastro
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_values($fields));
                $_SESSION['MsgSucesso'] = "Empresa <strong>" . htmlspecialchars($fields['EmpresaSunny']) . "</strong> criada com sucesso!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $erros[] = "Erro ao criar: " . $e->getMessage();
                error_log("[SUNNYEMP] " . $e->getMessage());
            }
        }

    // ========================= UPDATE =========================
    } elseif ($action === 'update') {
        $id = (int)($_POST['IdEmp'] ?? 0);
        if ($id <= 0) $erros[] = "ID inválido.";
        if (empty($fields['EmpresaSunny'])) $erros[] = "O nome da empresa é obrigatório.";

        if (empty($erros)) {
            try {
                $sql = "UPDATE sunnyempresas SET
                            EmpresaSunny = ?, CNPJ = ?, Endereco = ?, Cidade = ?, UF = ?,
                            Responsavel1 = ?, Email1 = ?, Telefone1 = ?, Obs = ?,
                            Conf_ImgLogo = ?, Conf_PastaCarrossel = ?, Conf_PastaCard = ?,
                            Conf_PastaImg = ?, Conf_ImgPontoMapa = ?, Conf_MainPage = ?, Conf_Versao = ?
                        WHERE IdEmp = ?";
                $stmt = $db->prepare($sql);
                $params = array_values($fields);
                $params[] = $id;
                $stmt->execute($params);
                $_SESSION['MsgSucesso'] = "Empresa <strong>" . htmlspecialchars($fields['EmpresaSunny']) . "</strong> atualizada!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $erros[] = "Erro ao atualizar: " . $e->getMessage();
                error_log("[SUNNYEMP] " . $e->getMessage());
            }
        }

    // ========================= DELETE =========================
    } elseif ($action === 'delete') {
        $id = (int)($_POST['IdEmp'] ?? 0);

        if ($id <= 0) {
            $erros[] = "ID inválido.";
        } elseif ($id === 1) {
            $erros[] = "A empresa #1 (master) não pode ser excluída por segurança.";
        } else {
            try {
                $chk = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE IdEmp = ?");
                $chk->execute([$id]);
                $totalUsers = (int)$chk->fetchColumn();

                if ($totalUsers > 0) {
                    $erros[] = "Não é possível excluir: existe(m) <b>$totalUsers</b> usuário(s) vinculado(s).";
                } else {
                    $q = $db->prepare("SELECT EmpresaSunny FROM sunnyempresas WHERE IdEmp = ?");
                    $q->execute([$id]);
                    $nome = $q->fetchColumn();

                    $stmt = $db->prepare("DELETE FROM sunnyempresas WHERE IdEmp = ?");
                    $stmt->execute([$id]);

                    $_SESSION['MsgSucesso'] = "Empresa <strong>" . htmlspecialchars($nome ?: "#$id") . "</strong> excluída.";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } catch (PDOException $e) {
                $erros[] = "Erro ao excluir: " . $e->getMessage();
                error_log("[SUNNYEMP] " . $e->getMessage());
            }
        }
    }

    if (!empty($erros)) {
        $_SESSION['MsgErro'] = implode("<br>", $erros);
    }
}

// ============================================================
// LISTAGEM
// ============================================================
$empresas = [];
try {
    $stmt = $db->query("
        SELECT e.*,
               (SELECT COUNT(*) FROM usuarios u WHERE u.IdEmp = e.IdEmp) AS TotalUsuarios
        FROM sunnyempresas e
        ORDER BY e.EmpresaSunny
    ");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[SUNNYEMP] Lista: " . $e->getMessage());
}

$totalEmpresas = count($empresas);
$totalUsuariosVinculados = 0;
foreach ($empresas as $e) $totalUsuariosVinculados += (int)($e['TotalUsuarios'] ?? 0);

// JSON para o JS
$empresasJson = json_encode($empresas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if (json_last_error() !== JSON_ERROR_NONE) $empresasJson = '[]';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestão de Empresas · SunnyImob</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?php echo $themeSafe['primary']; ?>;
            --primary-dark: <?php echo $themeSafe['primary_dark']; ?>;
            --bg-body: <?php echo $themeSafe['bg_body']; ?>;
            --text-color: <?php echo $themeSafe['text_color']; ?>;
            --card-bg: <?php echo $themeSafe['card_bg']; ?>;
            --shadow-color: <?php echo $themeSafe['shadow_color']; ?>;
            --btn-bg: <?php echo $themeSafe['btn_bg']; ?>;
            --btn-hover: <?php echo $themeSafe['btn_hover']; ?>;
            --btn-text: <?php echo $themeSafe['btn_text']; ?>;
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
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .header-logo {
            height: 58px; width: auto; max-width: 160px; min-width: 58px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 5px 8px;
            flex-shrink: 0;
        }
        .header-title h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: -0.4px;
            line-height: 1.15;
        }
        .header-title p {
            margin: 4px 0 0;
            font-size: 0.78rem;
            font-weight: 600;
            opacity: 0.9;
        }

        .header-stats { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
        .stat-badge {
            background: rgba(0,0,0,0.18);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 8px 16px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 88px;
        }
        .stat-badge .num { font-size: 1.3rem; font-weight: 900; line-height: 1; letter-spacing: -0.5px; }
        .stat-badge .label {
            font-size: 0.6rem; text-transform: uppercase;
            letter-spacing: 1px; margin-top: 4px;
            font-weight: 700; opacity: 0.9;
        }
        .stat-badge.is-info { background: var(--info); }

        /* TOOLBAR */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1 1 320px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
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
            padding: 11px 46px 11px 46px;
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
            position: absolute;
            left: 16px;
            color: var(--text-color);
            opacity: 0.6;
            pointer-events: none;
            font-size: 14px;
        }
        .search-box:focus-within .icon { color: var(--primary); opacity: 1; }
        .search-box .clear {
            position: absolute;
            right: 10px;
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 50%;
            width: 26px; height: 26px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            padding: 0;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            transition: all 0.12s;
        }
        .search-box .clear.visible { display: flex; }
        .search-box .clear:hover { transform: scale(1.12); background: #b02a37; }

        /* BOTÕES */
        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 800;
            font-size: 0.85rem;
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
            width: 34px; height: 34px;
            padding: 0;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            font-size: 0.82rem;
            text-decoration: none;
        }
        .btn-icon:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); }
        .btn-icon.edit   { background: var(--warning); color: #000; }
        .btn-icon.delete { background: var(--danger);  color: #fff; }

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
            padding: 12px !important;
            font-size: 13.5px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }

        .cell-id {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.75rem;
            background: rgba(0,0,0,0.06);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 800;
        }
        .cell-empresa {
            font-weight: 800;
            color: var(--text-color);
        }
        .cell-empresa small {
            display: block;
            font-size: 0.7rem;
            color: var(--text-color);
            opacity: 0.55;
            font-weight: 500;
            margin-top: 2px;
        }
        .badge-users {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--info);
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.7rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .badge-users.zero {
            background: rgba(0,0,0,0.08);
            color: var(--text-color);
            opacity: 0.5;
            box-shadow: none;
        }

        /* DataTables */
        .dataTables_wrapper .dataTables_filter { display: none; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info {
            padding: 12px 8px !important;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 4px 8px;
            background: #fff;
            color: var(--text-color);
            font-weight: 700;
            margin: 0 4px;
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
            transition: all 0.15s !important;
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
            opacity: 0.4;
            transform: none;
            box-shadow: none;
        }
        .dataTables_wrapper .dataTables_empty {
            padding: 40px 20px !important;
            font-weight: 700 !important;
            opacity: 0.6 !important;
            font-style: italic;
        }

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
        .modal-body {
            padding: 22px;
            background: var(--card-bg);
            max-height: 68vh;
            overflow-y: auto;
        }
        .modal-footer {
            background: var(--card-bg);
            border-top: 3px solid var(--shadow-color);
            padding: 16px 22px;
            gap: 10px;
        }

        /* FORM */
        .form-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-color);
            opacity: 0.7;
            margin: 20px 0 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid rgba(0,0,0,0.08);
        }
        .form-section-title:first-child { margin-top: 0; }
        .form-section-title i { color: var(--primary); font-size: 0.85rem; }

        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--text-color);
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 5px;
        }
        .field-label i { color: var(--primary); font-size: 0.8rem; }
        .field-label .req { color: var(--danger); }

        .form-control, .form-select {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 9px 12px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.88rem;
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
        textarea.form-control { resize: vertical; min-height: 70px; }

        /* Banner master */
        .master-banner {
            background: rgba(245,158,11,0.1);
            border: 2px dashed var(--warning);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.78rem;
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 16px;
        }
        .master-banner i { color: var(--warning); margin-right: 6px; }

        /* Empty */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: var(--card-bg);
            border: 3px dashed var(--shadow-color);
            border-radius: 16px;
            color: var(--text-color);
        }
        .empty-state i { font-size: 3rem; color: var(--primary); opacity: 0.4; display: block; margin-bottom: 12px; }
        .empty-state h3 { font-weight: 900; margin: 0 0 6px; }
        .empty-state p { opacity: 0.65; font-weight: 500; margin: 0 0 18px; }

        /* Responsivo */
        @media (max-width: 768px) {
            .app-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; }
            .header-logo { height: 44px; max-width: 110px; }
            .header-title h1 { font-size: 1rem; }
            .header-title p { font-size: 0.7rem; }
            .header-stats { width: 100%; }
            .stat-badge { flex: 1; min-width: 0; }
            .container-neo { padding: 0 12px; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .toolbar-left, .toolbar-right { width: 100%; }
            .btn-neo { flex: 1; justify-content: center; }
            .modal-body { padding: 16px; max-height: 72vh; }
        }
    </style>
</head>
<body>

<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="<?= htmlspecialchars($themeSafe['primary'] ? 'img/logosunny.jpg' : 'img/logosunny.jpg') ?>"
                 alt="Logo" class="header-logo" onerror="this.style.display='none'">
            <div class="header-title">
                <h1><i class="fas fa-building me-2"></i> Gestão de Empresas</h1>
                <p>Cadastro e configuração · SunnyImob</p>
            </div>
        </div>
        <div class="header-stats">
            <div class="stat-badge">
                <span class="num"><?= $totalEmpresas ?></span>
                <span class="label">Empresas</span>
            </div>
            <div class="stat-badge is-info">
                <span class="num"><?= $totalUsuariosVinculados ?></span>
                <span class="label">Usuários</span>
            </div>
        </div>
    </div>
</header>

<div class="container-neo">

    <?php if (isset($_SESSION['MsgSucesso'])): ?>
        <div style="background:var(--success);color:#fff;border:3px solid var(--shadow-color);border-radius:14px;padding:14px 18px;margin-bottom:18px;font-weight:700;display:flex;gap:10px;align-items:flex-start;">
            <i class="fas fa-check-circle" style="font-size:1.1rem;margin-top:1px;"></i>
            <div><?= $_SESSION['MsgSucesso']; unset($_SESSION['MsgSucesso']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['MsgErro'])): ?>
        <div style="background:var(--danger);color:#fff;border:3px solid var(--shadow-color);border-radius:14px;padding:14px 18px;margin-bottom:18px;font-weight:700;display:flex;gap:10px;align-items:flex-start;">
            <i class="fas fa-exclamation-circle" style="font-size:1.1rem;margin-top:1px;"></i>
            <div><?= $_SESSION['MsgErro']; unset($_SESSION['MsgErro']); ?></div>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar empresa, CNPJ, cidade, responsável…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="toolbar-right">
            <button type="button" class="btn-neo btn-neo-primary" onclick="abrirCriacao()">
                <i class="fas fa-plus"></i> Nova Empresa
            </button>
        </div>
    </div>

    <?php if ($totalEmpresas === 0): ?>
        <div class="empty-state">
            <i class="fas fa-building"></i>
            <h3>Nenhuma empresa cadastrada</h3>
            <p>Comece criando a primeira empresa do sistema.</p>
            <button type="button" class="btn-neo btn-neo-primary" onclick="abrirCriacao()">
                <i class="fas fa-plus"></i> Nova Empresa
            </button>
        </div>
    <?php else: ?>
        <div class="table-card">
            <table id="tabelaEmpresas" class="table table-neo" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Empresa</th>
                        <th style="width: 160px;">CNPJ</th>
                        <th style="width: 200px;">Cidade / UF</th>
                        <th style="width: 180px;">Responsável</th>
                        <th style="width: 100px;">Usuários</th>
                        <th style="width: 110px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresas as $e): 
                        $id = (int)$e['IdEmp'];
                        $total = (int)($e['TotalUsuarios'] ?? 0);
                    ?>
                        <tr>
                            <td><span class="cell-id">#<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></span></td>
                            <td>
                                <div class="cell-empresa">
                                    <?= htmlspecialchars($e['EmpresaSunny'] ?: '—') ?>
                                    <?php if (!empty($e['Conf_Versao'])): ?>
                                        <small>v<?= htmlspecialchars($e['Conf_Versao']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.78rem;opacity:0.75;">
                                    <?= htmlspecialchars($e['CNPJ'] ?: '—') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($e['Cidade']) || !empty($e['UF'])): ?>
                                    <?= htmlspecialchars($e['Cidade'] ?? '') ?>
                                    <?php if (!empty($e['UF'])): ?>
                                        <span style="background:rgba(0,0,0,0.08);padding:1px 6px;border-radius:4px;font-size:0.68rem;font-weight:800;margin-left:4px;">
                                            <?= htmlspecialchars($e['UF']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="opacity:0.4;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($e['Responsavel1'])): ?>
                                    <div style="font-weight:700;font-size:0.82rem;"><?= htmlspecialchars($e['Responsavel1']) ?></div>
                                    <?php if (!empty($e['Email1'])): ?>
                                        <div style="font-size:0.72rem;opacity:0.65;"><?= htmlspecialchars($e['Email1']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="opacity:0.4;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-users <?= $total === 0 ? 'zero' : '' ?>">
                                    <i class="fas fa-users"></i> <?= $total ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" class="btn-icon edit" onclick="abrirEdicao(<?= $id ?>)" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="post" style="margin:0;" onsubmit="return confirmarExclusao(this)">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_sunnyemp']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="IdEmp" value="<?= $id ?>">
                                        <button type="submit" class="btn-icon delete" title="Excluir"
                                            <?= ($id === 1) ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : '' ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================================ -->
<!-- MODAL CRIAR/EDITAR                                           -->
<!-- ============================================================ -->
<div class="modal fade" id="modalEmpresa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" id="formEmpresa" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_sunnyemp']) ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="IdEmp" id="formIdEmp" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-plus-circle"></i> Nova Empresa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div id="masterBanner" class="master-banner" style="display:none;">
                        <i class="fas fa-crown"></i>
                        <strong>Empresa Master (#1)</strong> — pode ser editada, mas não excluída.
                    </div>

                    <!-- DADOS PRINCIPAIS -->
                    <div class="form-section-title">
                        <i class="fas fa-building"></i> Dados da Empresa
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="field-label" for="field_EmpresaSunny">
                                <i class="fas fa-signature"></i> Nome da Empresa <span class="req">*</span>
                            </label>
                            <input type="text" class="form-control" id="field_EmpresaSunny" name="EmpresaSunny" required
                                   placeholder="Ex.: Orla Imob">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="field_CNPJ">
                                <i class="fas fa-id-card"></i> CNPJ
                            </label>
                            <input type="text" class="form-control" id="field_CNPJ" name="CNPJ"
                                   placeholder="00.000.000/0000-00">
                        </div>
                        <div class="col-md-8">
                            <label class="field-label" for="field_Endereco">
                                <i class="fas fa-map-marker-alt"></i> Endereço
                            </label>
                            <input type="text" class="form-control" id="field_Endereco" name="Endereco"
                                   placeholder="Rua, número, bairro">
                        </div>
                        <div class="col-md-3">
                            <label class="field-label" for="field_Cidade">
                                <i class="fas fa-city"></i> Cidade
                            </label>
                            <input type="text" class="form-control" id="field_Cidade" name="Cidade">
                        </div>
                        <div class="col-md-1">
                            <label class="field-label" for="field_UF">UF</label>
                            <select class="form-select" id="field_UF" name="UF">
                                <option value="">—</option>
                                <?php foreach ($UFs as $uf): ?>
                                    <option value="<?= $uf ?>"><?= $uf ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="field-label" for="field_Responsavel1">
                                <i class="fas fa-user-tie"></i> Responsável
                            </label>
                            <input type="text" class="form-control" id="field_Responsavel1" name="Responsavel1">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="field_Email1">
                                <i class="fas fa-envelope"></i> E-mail
                            </label>
                            <input type="email" class="form-control" id="field_Email1" name="Email1">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="field_Telefone1">
                                <i class="fas fa-phone"></i> Telefone
                            </label>
                            <input type="text" class="form-control" id="field_Telefone1" name="Telefone1">
                        </div>

                        <div class="col-12">
                            <label class="field-label" for="field_Obs">
                                <i class="fas fa-align-left"></i> Observações
                            </label>
                            <textarea class="form-control" id="field_Obs" name="Obs" rows="2"
                                      placeholder="Anotações internas, contrato, etc."></textarea>
                        </div>
                    </div>

                    <!-- IMAGENS E PASTAS -->
                    <div class="form-section-title">
                        <i class="fas fa-folder"></i> Imagens e Pastas
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field-label" for="field_Conf_ImgLogo">
                                <i class="fas fa-image"></i> Caminho do Logo
                            </label>
                            <input type="text" class="form-control" id="field_Conf_ImgLogo" name="Conf_ImgLogo"
                                   placeholder="img/logosunny.jpg"
                                   style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.82rem;">
                        </div>
                        <div class="col-md-6">
                            <label class="field-label" for="field_Conf_ImgPontoMapa">
                                <i class="fas fa-map-pin"></i> Imagem do Marcador (Mapa)
                            </label>
                            <input type="text" class="form-control" id="field_Conf_ImgPontoMapa" name="Conf_ImgPontoMapa"
                                   placeholder="icon/iconSunny1.png"
                                   style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.82rem;">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="field_Conf_PastaCarrossel">
                                <i class="fas fa-images"></i> Pasta Carrossel
                            </label>
                            <input type="text" class="form-control" id="field_Conf_PastaCarrossel" name="Conf_PastaCarrossel"
                                   placeholder="carrossel"
                                   style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.82rem;">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="field_Conf_PastaCard">
                                <i class="fas fa-id-card"></i> Pasta Card
                            </label>
                            <input type="text" class="form-control" id="field_Conf_PastaCard" name="Conf_PastaCard"
                                   placeholder="card"
                                   style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.82rem;">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="field_Conf_PastaImg">
                                <i class="fas fa-folder-open"></i> Pasta Imagens
                            </label>
                            <input type="text" class="form-control" id="field_Conf_PastaImg" name="Conf_PastaImg"
                                   placeholder="img"
                                   style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.82rem;">
                        </div>
                    </div>

                    <!-- SISTEMA -->
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Configurações do Sistema
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field-label" for="field_Conf_MainPage">
                                <i class="fas fa-home"></i> Página Inicial
                            </label>
                            <input type="text" class="form-control" id="field_Conf_MainPage" name="Conf_MainPage"
                                   placeholder="mainpage_v66a.php"
                                   style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.82rem;">
                        </div>
                        <div class="col-md-6">
                            <label class="field-label" for="field_Conf_Versao">
                                <i class="fas fa-code-branch"></i> Versão
                            </label>
                            <input type="text" class="form-control" id="field_Conf_Versao" name="Conf_Versao"
                                   placeholder="v7.0"
                                   style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.82rem;">
                        </div>
                    </div>

                    <div style="background:rgba(59,130,246,0.08);border:2px dashed var(--info);border-radius:10px;padding:10px 14px;font-size:0.78rem;color:var(--text-color);font-weight:600;margin-top:20px;">
                        <i class="fas fa-palette" style="color:var(--info);margin-right:6px;"></i>
                        <strong>Nota:</strong> as cores do tema (primária, secundária e fundo) são definidas em <code>theme_config.php</code> e não são configuradas por empresa.
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    const EMPRESAS = <?= $empresasJson ?>;

    const modalEl = document.getElementById('modalEmpresa');
    const modal   = new bootstrap.Modal(modalEl);

    // DATATABLE
    let table = null;
    if (document.getElementById('tabelaEmpresas')) {
        table = $('#tabelaEmpresas').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
            pageLength: 25,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            ordering: true,
            order: [[1, 'asc']],
            columnDefs: [
                { orderable: false, targets: [6] },
                { searchable: false, targets: [6] }
            ]
        });
    }

    // BUSCA
    const $search = document.getElementById('searchInput');
    const $clear  = document.getElementById('searchClear');
    let searchTimer;

    if ($search) {
        $search.addEventListener('input', function () {
            const v = this.value;
            $clear.classList.toggle('visible', v.length > 0);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                if (table) table.search(v).draw();
            }, 150);
        });

        $search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { this.value = ''; this.dispatchEvent(new Event('input')); }
        });

        $clear.addEventListener('click', function () {
            $search.value = '';
            $search.dispatchEvent(new Event('input'));
            $search.focus();
        });
    }

    // CRIAR
    window.abrirCriacao = function () {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Nova Empresa';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formIdEmp').value = '';
        document.getElementById('formEmpresa').reset();
        document.getElementById('masterBanner').style.display = 'none';
        modal.show();
    };

    // EDITAR
    window.abrirEdicao = function (id) {
        const emp = EMPRESAS.find(e => Number(e.IdEmp) === Number(id));
        if (!emp) return;

        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen"></i> Editar Empresa';
        document.getElementById('formAction').value = 'update';
        document.getElementById('formIdEmp').value = emp.IdEmp;

        document.getElementById('masterBanner').style.display = (Number(id) === 1) ? 'block' : 'none';

        const campos = [
            'EmpresaSunny', 'CNPJ', 'Endereco', 'Cidade', 'UF',
            'Responsavel1', 'Email1', 'Telefone1', 'Obs',
            'Conf_ImgLogo', 'Conf_PastaCarrossel', 'Conf_PastaCard', 'Conf_PastaImg',
            'Conf_ImgPontoMapa', 'Conf_MainPage', 'Conf_Versao'
        ];
        campos.forEach(k => {
            const el = document.getElementById('field_' + k);
            if (el) el.value = emp[k] ?? '';
        });

        modal.show();
    };

    // SUBMIT
    document.getElementById('formEmpresa')?.addEventListener('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            this.reportValidity();
            return;
        }
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
    });

    // EXCLUIR
    window.confirmarExclusao = function (form) {
        event.preventDefault();
        const id = form.querySelector('input[name="IdEmp"]').value;

        Swal.fire({
            title: 'Excluir empresa?',
            html: 'A empresa <strong>#' + id + '</strong> será removida permanentemente.<br>Esta ação não pode ser desfeita.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280'
        }).then(r => {
            if (r.isConfirmed) form.submit();
        });

        return false;
    };

    // TOAST
    <?php if (isset($_SESSION['MsgSucesso'])): ?>
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
<?php
if (isset($conn) && $conn instanceof PDO) { $conn = null; }
if (isset($pdo)  && $pdo  instanceof PDO) { $pdo  = null; }
?>
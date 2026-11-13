<?php
// usr_edit.php
// ============================================================
// session_start PRIMEIRO
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
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// FUNÇÕES
// ============================================================
function getPermissaoInfo($codigo) {
    $map = [
        1 => ['nome' => 'SUPERADMIN',    'acoes' => '/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/', 'cor' => 'danger',    'desc' => 'Acesso total ao sistema'],
        2 => ['nome' => 'ADMIN',         'acoes' => '/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/',                 'cor' => 'warning',   'desc' => 'Gestão de usuários'],
        3 => ['nome' => 'GESTOR-EDITOR', 'acoes' => '/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/',                 'cor' => 'info',      'desc' => 'Gestor de equipe'],
        4 => ['nome' => 'EDITOR',        'acoes' => '/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/',                        'cor' => 'primary',   'desc' => 'Edita todos os registros'],
        5 => ['nome' => 'CORRETOR',      'acoes' => '/CONSULTAR/',                                              'cor' => 'secondary', 'desc' => 'Apenas consulta'],
        6 => ['nome' => 'CORRETOR-EDITOR','acoes' => '/CONSULTAR/EDITAR/',                                     'cor' => 'purple',    'desc' => 'Consulta + edita próprios'],
        7 => ['nome' => 'VISUALIZADOR',  'acoes' => '/CONSULTAR/',                                              'cor' => 'success',   'desc' => 'Somente leitura'],
    ];
    return $map[$codigo] ?? ['nome' => '—', 'acoes' => '', 'cor' => 'light', 'desc' => ''];
}

// ============================================================
// VALIDA ID
// ============================================================
$vUserId = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0);

if ($vUserId <= 0) {
    $_SESSION['MsgErro'] = "ID de usuário inválido.";
    header("Location: usr_listar.php");
    exit();
}

// ============================================================
// CARREGA USUÁRIO
// ============================================================
try {
    $stmt = $db->prepare("
        SELECT UserId, Usuario, SenhaAtual, Nome, CRECI, Email, Telefones,
               Acoes, Permissao, Funcao, Ativo, IdEmp, Empresa,
               GrupoId, Grupo, DataHoraCadastro, UsuarioCadastro, Opr, Licenca
        FROM usuarios 
        WHERE UserId = ?
    ");
    $stmt->execute([$vUserId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        $_SESSION['MsgErro'] = "Usuário não encontrado.";
        header("Location: usr_listar.php");
        exit();
    }
} catch (Exception $e) {
    $_SESSION['MsgErro'] = "Erro ao carregar usuário: " . $e->getMessage();
    header("Location: usr_listar.php");
    exit();
}

// form_data (prioriza POST em caso de erro) + normalização de chaves
$form_data = $_SESSION['form_data'] ?? $userData;
unset($_SESSION['form_data']);
if (isset($form_data['ID_Grupo']) && !isset($form_data['GrupoId'])) {
    $form_data['GrupoId'] = (int)$form_data['ID_Grupo'];
}

// ============================================================
// ESTADO INICIAL DA FOTO
// ============================================================
$fotoPath      = __DIR__ . '/fotos/' . $vUserId . '.jpg';
$temFoto       = file_exists($fotoPath);
$fotoTimestamp = $temFoto ? filemtime($fotoPath) : 0;
$fotoUrl       = $temFoto ? 'fotos/' . $vUserId . '.jpg?t=' . $fotoTimestamp : '';

// ============================================================
// PROCESSAMENTO DO POST
// ============================================================
$erros = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfOk = isset($_POST['csrf_token'])
           && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

    if (!$csrfOk) {
        $erros[] = "Falha na verificação de segurança (CSRF). Recarregue a página.";
    } else {
        $vUsuario   = trim($_POST['Usuario']   ?? '');
        $vSenha     = trim($_POST['Senha']     ?? '');
        $vNome      = trim($_POST['Nome']      ?? '');
        $vCRECI     = trim($_POST['CRECI']     ?? '');
        $vEmail     = trim($_POST['Email']     ?? '');
        $vTelefones = trim($_POST['Telefones'] ?? '');
        $vIdEmp     = (int)($_POST['IdEmp']    ?? 0);
        $vID_Grupo  = (int)($_POST['ID_Grupo'] ?? 0);
        $vPermissao = (int)($_POST['Permissao'] ?? 0);
        $vAtivo     = isset($_POST['Ativo']) ? 1 : 0;
        $vFuncao    = trim($_POST['Funcao']    ?? '');
        $vAcoes     = trim($_POST['Acoes']     ?? '');

        // Validações básicas
        if (empty($vUsuario) || empty($vNome) || $vPermissao < 1 || $vPermissao > 7 ||
            $vIdEmp <= 0 || $vID_Grupo <= 0) {
            $erros[] = "Campos obrigatórios não preenchidos corretamente.";
        }

        $senhaSalvar = !empty($vSenha) ? $vSenha : $userData['SenhaAtual'];

        // Duplicidade de usuário
        if (empty($erros) && $vUsuario !== $userData['Usuario']) {
            $chk = $db->prepare("SELECT 1 FROM usuarios WHERE Usuario = ? AND UserId != ?");
            $chk->execute([$vUsuario, $vUserId]);
            if ($chk->fetch()) $erros[] = "Este nome de usuário já está em uso.";
        }

        // Empresa
        $empresa_nome = $userData['Empresa'];
        if (empty($erros)) {
            $stmt = $db->prepare("SELECT EmpresaSunny FROM sunnyempresas WHERE IdEmp = ?");
            $stmt->execute([$vIdEmp]);
            if ($row = $stmt->fetch()) {
                $empresa_nome = $row['EmpresaSunny'];
            } else {
                $erros[] = "Empresa não encontrada.";
            }
        }

        // Grupo
        $grupo_nome = $userData['Grupo'];
        if (empty($erros)) {
            $stmt = $db->prepare("SELECT Nome_Grupo FROM grupo WHERE ID_Grupo = ?");
            $stmt->execute([$vID_Grupo]);
            if ($row = $stmt->fetch()) {
                $grupo_nome = $row['Nome_Grupo'];
            } else {
                $erros[] = "Grupo não encontrado.";
            }
        }

        // Fallback função/ações
        if (empty($vFuncao) || empty($vAcoes)) {
            $permInfo = getPermissaoInfo($vPermissao);
            $vFuncao = $permInfo['nome'];
            $vAcoes  = $permInfo['acoes'];
        }

        // -------- VALIDAÇÃO DA FOTO (antes da transação) --------
        $fotoFile    = $_FILES['Foto'] ?? null;
        $removerFoto = isset($_POST['RemoverFoto']) && $_POST['RemoverFoto'] === '1';
        $fotoValida  = false;
        $fotoInfo    = null;

        if ($fotoFile && $fotoFile['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($fotoFile['error'] !== UPLOAD_ERR_OK) {
                $erros[] = "Falha no upload da imagem (código " . (int)$fotoFile['error'] . ").";
            } elseif ($fotoFile['size'] > 5 * 1024 * 1024) {
                $erros[] = "A imagem excede o limite de 5 MB.";
            } else {
                $fotoInfo = @getimagesize($fotoFile['tmp_name']);
                if (!$fotoInfo) {
                    $erros[] = "O arquivo enviado não é uma imagem válida.";
                } elseif (!in_array($fotoInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    $erros[] = "Formato de imagem não suportado. Use JPG, PNG ou WEBP.";
                } else {
                    $fotoValida = true;
                }
            }
        }

        // -------- UPDATE + GRUPO --------
        if (empty($erros)) {
            try {
                $db->beginTransaction();

                $sql = "
                    UPDATE usuarios SET
                        Usuario = ?, SenhaAtual = ?, Nome = ?, CRECI = ?, Email = ?,
                        Telefones = ?, Acoes = ?, Permissao = ?, Funcao = ?, Ativo = ?,
                        IdEmp = ?, Empresa = ?, GrupoId = ?, Grupo = ?, Opr = ?
                    WHERE UserId = ?
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $vUsuario, $senhaSalvar, $vNome, $vCRECI, $vEmail, $vTelefones,
                    $vAcoes, $vPermissao, $vFuncao, $vAtivo,
                    $vIdEmp, $empresa_nome, $vID_Grupo, $grupo_nome,
                    $_SESSION['Usuario'] ?? 'SISTEMA',
                    $vUserId
                ]);

                $db->prepare("DELETE FROM usuario_grupo WHERE UserId = ?")->execute([$vUserId]);
                $db->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)")->execute([$vUserId, $vID_Grupo]);

                $db->commit();

                // -------- FOTO (não-crítica: após commit) --------
                $fotoErro = null;
                try {
                    $destDir   = __DIR__ . '/fotos';
                    $finalPath = $destDir . '/' . $vUserId . '.jpg';

                    if ($fotoValida && $fotoFile) {
                        if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
                            throw new Exception("Não foi possível criar a pasta fotos/.");
                        }
                        $tmpPath = $finalPath . '.tmp.' . uniqid();

                        $img = null;
                        switch ($fotoInfo[2]) {
                            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($fotoFile['tmp_name']); break;
                            case IMAGETYPE_PNG:  $img = @imagecreatefrompng($fotoFile['tmp_name']);  break;
                            case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($fotoFile['tmp_name']); break;
                        }

                        if ($img) {
                            $w = imagesx($img); $h = imagesy($img);
                            $max = 500;
                            if ($w > $max || $h > $max) {
                                $ratio = min($max / $w, $max / $h);
                                $nw = max(1, (int)round($w * $ratio));
                                $nh = max(1, (int)round($h * $ratio));
                                $resized = imagecreatetruecolor($nw, $nh);
                                imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
                                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                                imagedestroy($img);
                                $img = $resized;
                            }
                            $ok = imagejpeg($img, $tmpPath, 88);
                            imagedestroy($img);
                            if (!$ok) { @unlink($tmpPath); throw new Exception("Falha ao processar imagem."); }
                        } else {
                            if (!@copy($fotoFile['tmp_name'], $tmpPath)) {
                                throw new Exception("Falha ao salvar a imagem temporária.");
                            }
                        }

                        if (!@rename($tmpPath, $finalPath)) {
                            @unlink($tmpPath);
                            throw new Exception("Falha ao mover a imagem para o destino final.");
                        }
                    } elseif ($removerFoto && file_exists($finalPath)) {
                        @unlink($finalPath);
                    }
                } catch (Exception $e) {
                    $fotoErro = $e->getMessage();
                    error_log('[USR_EDIT_FOTO] ' . $fotoErro);
                }

                $_SESSION['MsgSucesso'] = "Usuário <strong>" . htmlspecialchars($vNome) . "</strong> atualizado com sucesso!";
                if ($fotoErro) {
                    $_SESSION['MsgSucesso'] .= " <small>(aviso: foto não atualizada: " . htmlspecialchars($fotoErro) . ")</small>";
                }
                header("Location: usr_listar.php");
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $erros[] = "Erro ao atualizar: " . $e->getMessage();
            }
        }
    }

    // -------- REDIRECT EM CASO DE ERRO (CSRF + validações) --------
    if (!empty($erros)) {
        $_SESSION['MsgErro'] = implode("<br>", $erros);
        $_SESSION['form_data'] = $_POST;
        header("Location: " . basename(__FILE__) . "?id={$vUserId}");
        exit();
    }
}

// ============================================================
// CARREGAR EMPRESAS E GRUPOS
// ============================================================
$listaEmpresas = [];
$listaGrupos   = [];
try {
    $stmt = $db->query("SELECT IdEmp, EmpresaSunny FROM sunnyempresas ORDER BY EmpresaSunny");
    $listaEmpresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT ID_Grupo, Nome_Grupo, Descricao_Grupo FROM grupo ORDER BY Nome_Grupo");
    $listaGrupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[EDITAR_USU] " . $e->getMessage());
}

$permAtual = getPermissaoInfo($form_data['Permissao'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Usuário · <?= htmlspecialchars($form_data['Usuario'] ?? '') ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --purple: #6f42c1;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            color: var(--text-color);
            min-height: 100vh;
            padding-bottom: 96px;
            -webkit-font-smoothing: antialiased;
        }

        .container-neo { max-width: 960px; margin: 0 auto; padding: 0 20px; }

        /* HEADER */
        .page-header {
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
        .page-header::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            max-width: 960px;
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
            height: 54px; width: auto; max-width: 140px; min-width: 54px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            background: #fff;
            padding: 4px 8px;
            flex-shrink: 0;
        }
        .header-title { min-width: 0; }
        .header-title h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 900;
            letter-spacing: -0.3px;
            line-height: 1.15;
        }
        .header-title p {
            margin: 4px 0 0;
            font-size: 0.76rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .header-title p strong {
            background: rgba(255,255,255,0.18);
            padding: 1px 8px;
            border-radius: 6px;
        }
        .header-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
        .meta-chip {
            background: rgba(0,0,0,0.2);
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* CARDS */
        .card-neo {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            margin-bottom: 22px;
            overflow: hidden;
        }
        .card-neo-header {
            background: var(--primary);
            color: #fff;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 3px solid var(--shadow-color);
        }
        .card-neo-header i { font-size: 1rem; }
        .card-neo-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: -0.2px;
        }
        .card-neo-body { padding: 22px; }

        /* FORM */
        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-color);
            opacity: 0.85;
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
        .form-control[readonly] { background: #f8f9fb; cursor: not-allowed; }
        .form-control.font-monospace { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.78rem; }

        /* INPUT GROUP */
        .input-group-neo { display: flex; align-items: stretch; }
        .input-group-neo .prefix {
            background: rgba(0,0,0,0.05);
            border: 2px solid var(--shadow-color);
            border-right: none;
            border-radius: 10px 0 0 10px;
            padding: 0 12px;
            display: flex; align-items: center;
            color: var(--primary);
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .input-group-neo .form-control { border-radius: 0; }
        .input-group-neo .form-control:first-of-type { border-radius: 0; }
        .input-group-neo .btn-toggle-pwd,
        .input-group-neo .btn-magic {
            border: 2px solid var(--shadow-color);
            border-left: none;
            background: rgba(0,0,0,0.05);
            color: var(--text-color);
            padding: 0 14px;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0;
        }
        .input-group-neo .btn-magic:hover,
        .input-group-neo .btn-toggle-pwd:hover {
            background: var(--primary); color: #fff;
        }
        .input-group-neo > :last-child { border-radius: 0 10px 10px 0 !important; }

        /* FOTO UPLOAD */
        .foto-upload-wrap {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 14px;
            background: rgba(0,0,0,0.02);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
        }
        .foto-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            border: 3px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            flex-shrink: 0;
            overflow: hidden;
        }
        .foto-preview img {
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .foto-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 0;
            justify-content: center;
        }
        .foto-helper {
            font-size: 0.72rem;
            opacity: 0.7;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* OPTION CARDS */
        .option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
        }
        .option-card {
            position: relative;
            display: block;
            cursor: pointer;
            background: #f8f9fb;
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 12px 14px;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            user-select: none;
        }
        .option-card:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            background: #fff;
        }
        .option-card input { position: absolute; opacity: 0; pointer-events: none; }
        .option-card .opt-title {
            display: flex; align-items: center; gap: 8px;
            font-weight: 800; font-size: 0.88rem;
            color: var(--text-color);
            margin-bottom: 3px;
        }
        .option-card .opt-title i { color: var(--primary); font-size: 0.85rem; }
        .option-card .opt-id {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.62rem;
            background: rgba(0,0,0,0.08);
            padding: 1px 6px;
            border-radius: 4px;
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 700;
            margin-left: auto;
        }
        .option-card .opt-desc {
            font-size: 0.72rem;
            color: var(--text-color);
            opacity: 0.65;
            font-weight: 500;
            line-height: 1.3;
        }
        .option-card .opt-color-bar {
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 5px;
            border-radius: 12px 0 0 12px;
        }
        .option-card.is-checked {
            background: var(--primary);
            border-color: var(--primary-dark);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .option-card.is-checked .opt-title,
        .option-card.is-checked .opt-title i,
        .option-card.is-checked .opt-desc,
        .option-card.is-checked .opt-id { color: #fff; }
        .option-card.is-checked .opt-id { background: rgba(255,255,255,0.2); }
        .option-card.is-checked .opt-color-bar { display: none; }

        .perm-danger    .opt-color-bar { background: var(--danger); }
        .perm-warning   .opt-color-bar { background: var(--warning); }
        .perm-info      .opt-color-bar { background: var(--info); }
        .perm-primary   .opt-color-bar { background: var(--primary); }
        .perm-secondary .opt-color-bar { background: #6b7280; }
        .perm-purple    .opt-color-bar { background: var(--purple); }
        .perm-success   .opt-color-bar { background: var(--success); }

        /* BADGE */
        .perm-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            color: #fff;
        }

        /* META */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
        }
        .meta-item {
            background: rgba(0,0,0,0.03);
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 14px;
        }
        .meta-item small {
            display: block;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
            color: var(--text-color);
            opacity: 0.65;
            margin-bottom: 3px;
        }
        .meta-item strong {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--text-color);
        }

        /* ACTION BAR */
        .action-bar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: var(--card-bg);
            border-top: 3px solid var(--shadow-color);
            box-shadow: 0 -4px 0 0 var(--shadow-color);
            padding: 14px 20px;
            z-index: 40;
        }
        .action-bar-inner {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .action-info {
            font-size: 0.82rem;
            font-weight: 600;
            opacity: 0.75;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dirty-badge {
            background: var(--warning);
            color: #000;
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.72rem;
            display: none;
        }
        .dirty-badge.visible { display: inline-block; }

        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 22px;
            font-weight: 800;
            font-size: 0.88rem;
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
        .btn-neo:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-neo-primary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo-primary:hover:not(:disabled) { background: var(--btn-hover); color: #fff; }
        .btn-neo-secondary { background: #fff; color: var(--text-color); }
        .btn-neo-secondary:hover { background: #f8f9fb; color: var(--text-color); }
        .btn-neo-sm { padding: 6px 12px; font-size: 0.76rem; box-shadow: 2px 2px 0 0 var(--shadow-color); }
        .btn-neo-sm:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); }

        @media (max-width: 768px) {
            .page-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; }
            .header-logo { height: 42px; max-width: 100px; }
            .header-title h1 { font-size: 0.98rem; }
            .header-title p { font-size: 0.7rem; }
            .header-meta { width: 100%; }
            .meta-chip { flex: 1; justify-content: center; font-size: 0.66rem; padding: 4px 8px; }
            .container-neo { padding: 0 12px; }
            .card-neo-body { padding: 14px; }
            .card-neo-header { padding: 12px 16px; }
            .option-grid { grid-template-columns: 1fr; }
            .action-bar-inner { flex-direction: column; align-items: stretch; }
            .action-bar-inner .btn-neo { width: 100%; justify-content: center; }
            .action-info { justify-content: center; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="page-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logo" class="header-logo" onerror="this.style.display='none'">
            <div class="header-title">
                <h1><i class="fas fa-user-pen me-2"></i> Editar Usuário</h1>
                <p>
                    <strong>#<?= (int)$vUserId ?></strong>
                    <?= htmlspecialchars(strtoupper($form_data['Usuario'] ?? '')) ?>
                    <?php if (!empty($form_data['Nome'])): ?>
                        · <?= htmlspecialchars($form_data['Nome']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="header-meta">
            <span class="meta-chip">
                <i class="fas fa-user-shield"></i>
                <?= htmlspecialchars($permAtual['nome']) ?>
            </span>
            <span class="meta-chip">
                <i class="fas fa-<?= !empty($form_data['Ativo']) ? 'check-circle' : 'times-circle' ?>"></i>
                <?= !empty($form_data['Ativo']) ? 'Ativo' : 'Inativo' ?>
            </span>
        </div>
    </div>
</header>

<!-- CONTEÚDO -->
<div class="container-neo">

    <?php if (isset($_SESSION['MsgErro'])): ?>
        <div class="alert-neo" style="background:var(--danger);color:#fff;border:3px solid var(--shadow-color);border-radius:14px;padding:14px 18px;margin-bottom:18px;font-weight:700;display:flex;gap:10px;">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= $_SESSION['MsgErro']; unset($_SESSION['MsgErro']); ?></div>
        </div>
    <?php endif; ?>

    <form method="post" id="formEditar" enctype="multipart/form-data" novalidate autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="RemoverFoto" id="RemoverFoto" value="0">

        <!-- METADADOS -->
        <div class="card-neo">
            <div class="card-neo-header">
                <i class="fas fa-info-circle"></i>
                <h2>Informações do Registro</h2>
            </div>
            <div class="card-neo-body">
                <div class="meta-grid">
                    <div class="meta-item">
                        <small>Cadastrado por</small>
                        <strong><?= htmlspecialchars($userData['UsuarioCadastro'] ?? 'SISTEMA') ?></strong>
                    </div>
                    <div class="meta-item">
                        <small>Data do cadastro</small>
                        <strong>
                            <?php
                                $dt = $userData['DataHoraCadastro'] ?? null;
                                echo $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
                            ?>
                        </strong>
                    </div>
                    <div class="meta-item">
                        <small>Licença</small>
                        <strong><?= htmlspecialchars($userData['Licenca'] ?? '—') ?></strong>
                    </div>
                    <div class="meta-item">
                        <small>Operador</small>
                        <strong><?= htmlspecialchars($userData['Opr'] ?? '—') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- DADOS PESSOAIS -->
        <div class="card-neo">
            <div class="card-neo-header">
                <i class="fas fa-user"></i>
                <h2>Dados Pessoais</h2>
            </div>
            <div class="card-neo-body">

                <!-- FOTO -->
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="field-label">
                            <i class="fas fa-camera"></i> Foto do Usuário
                        </label>
                        <div class="foto-upload-wrap">
                            <div class="foto-preview" id="fotoPreview">
                                <?php if ($temFoto): ?>
                                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-camera"></i>
                                <?php endif; ?>
                            </div>
                            <div class="foto-controls">
                                <input type="file" id="Foto" name="Foto"
                                       accept="image/jpeg,image/png,image/webp"
                                       style="display:none">
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <button type="button" class="btn-neo btn-neo-secondary btn-neo-sm"
                                            onclick="document.getElementById('Foto').click()">
                                        <i class="fas fa-upload"></i> Selecionar
                                    </button>
                                    <button type="button" class="btn-neo btn-neo-secondary btn-neo-sm"
                                            id="btnRemoverFoto"
                                            style="<?= $temFoto ? '' : 'display:none;' ?>">
                                        <i class="fas fa-trash"></i> Remover
                                    </button>
                                </div>
                                <div class="foto-helper">
                                    <i class="fas fa-info-circle"></i>
                                    JPG, PNG ou WEBP · máx 5&nbsp;MB · salvo como <code>fotos/<?= (int)$vUserId ?>.jpg</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOME + CRECI -->
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="field-label" for="Nome">
                            <i class="fas fa-signature"></i> Nome completo <span class="req">*</span>
                        </label>
                        <input type="text" class="form-control" id="Nome" name="Nome" required
                               autocomplete="off"
                               value="<?= htmlspecialchars($form_data['Nome'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="CRECI">
                            <i class="fas fa-id-badge"></i> CRECI
                        </label>
                        <input type="text" class="form-control" id="CRECI" name="CRECI"
                               autocomplete="off"
                               value="<?= htmlspecialchars($form_data['CRECI'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="field-label" for="Email">
                            <i class="fas fa-envelope"></i> E-mail
                        </label>
                        <input type="email" class="form-control" id="Email" name="Email"
                               autocomplete="off"
                               value="<?= htmlspecialchars($form_data['Email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="field-label" for="Telefones">
                            <i class="fas fa-phone"></i> Telefones
                        </label>
                        <input type="text" class="form-control" id="Telefones" name="Telefones"
                               autocomplete="off"
                               value="<?= htmlspecialchars($form_data['Telefones'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ACESSO -->
        <div class="card-neo">
            <div class="card-neo-header">
                <i class="fas fa-key"></i>
                <h2>Acesso & Credenciais</h2>
            </div>
            <div class="card-neo-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="field-label" for="Usuario">
                            <i class="fas fa-at"></i> Usuário <span class="req">*</span>
                        </label>
                        <div class="input-group-neo">
                            <span class="prefix"><i class="fas fa-at"></i></span>
                            <input type="text" class="form-control" id="Usuario" name="Usuario" required
                                   autocomplete="off"
                                   value="<?= htmlspecialchars($form_data['Usuario'] ?? '') ?>">
                            <button type="button" class="btn-magic" id="btnGerarUsuario"
                                    title="Gerar usuário a partir do nome">
                                <i class="fas fa-wand-magic-sparkles"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="field-label" for="Senha">
                            <i class="fas fa-lock"></i> Nova senha (opcional)
                        </label>
                        <div class="input-group-neo">
                            <span class="prefix"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="Senha" name="Senha"
                                   placeholder="Deixe em branco para manter a atual"
                                   autocomplete="new-password">
                            <button type="button" class="btn-magic" id="btnGerarSenha"
                                    title="Gerar senha a partir do nome">
                                <i class="fas fa-wand-magic-sparkles"></i>
                            </button>
                            <button type="button" class="btn-toggle-pwd" id="toggleSenha" title="Mostrar/ocultar senha">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="field-label" for="IdEmp">
                            <i class="fas fa-building"></i> Empresa <span class="req">*</span>
                        </label>
                        <select class="form-select" id="IdEmp" name="IdEmp" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($listaEmpresas as $emp): ?>
                                <option value="<?= (int)$emp['IdEmp'] ?>"
                                    <?= ((int)($form_data['IdEmp'] ?? 0) === (int)$emp['IdEmp']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['EmpresaSunny']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6" style="display:flex;align-items:flex-end;">
                        <label class="option-card" style="width:100%;">
                            <input type="checkbox" id="Ativo" name="Ativo" value="1"
                                   <?= !empty($form_data['Ativo']) ? 'checked' : '' ?>>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                                <div>
                                    <div class="opt-title" style="margin-bottom:0;">
                                        <i class="fas fa-power-off"></i>
                                        <span>Usuário Ativo</span>
                                    </div>
                                    <div class="opt-desc">Permite login no sistema</div>
                                </div>
                                <div class="form-check form-switch m-0" style="pointer-events:none;">
                                    <input class="form-check-input" type="checkbox"
                                           <?= !empty($form_data['Ativo']) ? 'checked' : '' ?> disabled>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRUPO -->
        <div class="card-neo">
            <div class="card-neo-header">
                <i class="fas fa-users"></i>
                <h2>Grupo de Acesso <span style="font-size:0.7rem;opacity:0.8;font-weight:600;margin-left:6px;">(obrigatório)</span></h2>
            </div>
            <div class="card-neo-body">
                <?php if (empty($listaGrupos)): ?>
                    <div style="padding:20px;text-align:center;opacity:0.6;">Nenhum grupo cadastrado.</div>
                <?php else: ?>
                    <div class="option-grid">
                        <?php
                        $grupoSelecionado = (int)($form_data['GrupoId'] ?? $form_data['ID_Grupo'] ?? 0);
                        foreach ($listaGrupos as $g):
                            $gId   = (int)$g['ID_Grupo'];
                            $gNome = $g['Nome_Grupo'];
                            $gDesc = $g['Descricao_Grupo'] ?? '';
                            $checked = ($grupoSelecionado === $gId) ? 'checked' : '';
                        ?>
                            <label class="option-card <?= $checked ? 'is-checked' : '' ?>">
                                <input type="radio" name="ID_Grupo" value="<?= $gId ?>" <?= $checked ?> required>
                                <div class="opt-title">
                                    <i class="fas fa-users"></i>
                                    <span><?= htmlspecialchars($gNome) ?></span>
                                    <span class="opt-id">#<?= $gId ?></span>
                                </div>
                                <?php if (!empty($gDesc)): ?>
                                    <div class="opt-desc"><?= htmlspecialchars($gDesc) ?></div>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PERMISSÃO -->
        <div class="card-neo">
            <div class="card-neo-header">
                <i class="fas fa-user-shield"></i>
                <h2>Nível de Permissão</h2>
            </div>
            <div class="card-neo-body">
                <div class="option-grid">
                    <?php
                    $permSelecionada = (int)($form_data['Permissao'] ?? 0);
                    $permissoes = [
                        1 => "SUPERADMIN",
                        2 => "ADMIN",
                        3 => "GESTOR-EDITOR",
                        4 => "EDITOR",
                        5 => "CORRETOR",
                        6 => "CORRETOR-EDITOR",
                        7 => "VISUALIZADOR",
                    ];
                    foreach ($permissoes as $cod => $nome):
                        $info = getPermissaoInfo($cod);
                        $checked = ($permSelecionada === $cod) ? 'checked' : '';
                    ?>
                        <label class="option-card perm-<?= $info['cor'] ?> <?= $checked ? 'is-checked' : '' ?>">
                            <input type="radio" name="Permissao" value="<?= $cod ?>"
                                   data-funcao="<?= htmlspecialchars($info['nome']) ?>"
                                   data-acoes="<?= htmlspecialchars($info['acoes']) ?>"
                                   <?= $checked ?> required>
                            <div class="opt-color-bar"></div>
                            <div class="opt-title">
                                <i class="fas fa-shield-alt"></i>
                                <span><?= htmlspecialchars($nome) ?></span>
                                <span class="opt-id"><?= $cod ?></span>
                            </div>
                            <div class="opt-desc"><?= htmlspecialchars($info['desc']) ?></div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="field-label">
                            <i class="fas fa-briefcase"></i> Função
                        </label>
                        <input type="text" class="form-control" id="Funcao" name="Funcao" readonly
                               value="<?= htmlspecialchars($form_data['Funcao'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="field-label">
                            <i class="fas fa-list-check"></i> Ações permitidas
                        </label>
                        <input type="text" class="form-control font-monospace" id="Acoes" name="Acoes" readonly
                               value="<?= htmlspecialchars($form_data['Acoes'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

<!-- ACTION BAR -->
<div class="action-bar">
    <div class="action-bar-inner">
        <div class="action-info">
            <i class="fas fa-info-circle"></i>
            <span id="actionHint">Nenhuma alteração pendente</span>
            <span class="dirty-badge" id="dirtyBadge">Alterações pendentes</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="usr_listar.php" class="btn-neo btn-neo-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <button type="submit" form="formEditar" class="btn-neo btn-neo-primary" id="btnSalvar">
                <i class="fas fa-floppy-disk"></i> Salvar Alterações
            </button>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    const THEME_PRIMARY = '<?= $themeSafe['primary'] ?>';
    const HAS_FOTO_INICIAL = <?= $temFoto ? 'true' : 'false' ?>;
    const USER_ID = <?= (int)$vUserId ?>;

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true
    });

    // ============================================================
    // GERAÇÃO DE CREDENCIAIS
    // ============================================================
    function removerAcentos(texto) {
        return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }

    function gerarNomeUsuario(nomeCompleto) {
        if (!nomeCompleto || !nomeCompleto.trim()) return '';
        const partes = nomeCompleto.toLowerCase().trim().split(/\s+/).filter(Boolean);
        if (partes.length === 0) return '';

        const particulas = ['da', 'de', 'do', 'das', 'dos', 'e'];
        const primeiro = removerAcentos(partes[0]);
        const sobrenomes = partes.slice(1).filter(p => !particulas.includes(p) && p.length > 0);

        if (sobrenomes.length === 0) return primeiro;

        let iniciais = '';
        for (let i = 0; i < Math.min(sobrenomes.length, 2); i++) {
            iniciais += sobrenomes[i][0];
        }
        return primeiro + removerAcentos(iniciais);
    }

    function gerarSenha(nomeCompleto) {
        if (!nomeCompleto || !nomeCompleto.trim()) return '';
        const partes = nomeCompleto.trim().split(/\s+/);
        const primeiro = removerAcentos(partes[0].toLowerCase());
        const num = Math.floor(Math.random() * 99) + 1;
        return primeiro + '@' + num.toString().padStart(2, '0');
    }

    // ============================================================
    // BOTÕES "GERAR" (🪄)
    // ============================================================
    document.getElementById('btnGerarUsuario')?.addEventListener('click', function () {
        const nome = document.getElementById('Nome')?.value || '';
        if (!nome.trim()) {
            Swal.fire({
                icon: 'info',
                title: 'Nome vazio',
                text: 'Preencha o nome completo antes de gerar o usuário.'
            });
            return;
        }
        const el = document.getElementById('Usuario');
        el.value = gerarNomeUsuario(nome);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        verificarDirty();
        Toast.fire({ icon: 'success', title: 'Usuário gerado: ' + el.value });
    });

    document.getElementById('btnGerarSenha')?.addEventListener('click', function () {
        const nome = document.getElementById('Nome')?.value || '';
        if (!nome.trim()) {
            Swal.fire({
                icon: 'info',
                title: 'Nome vazio',
                text: 'Preencha o nome completo antes de gerar a senha.'
            });
            return;
        }
        const el = document.getElementById('Senha');
        el.value = gerarSenha(nome);
        // garante que está visível para o usuário ver a senha gerada
        if (el.type === 'password') {
            el.type = 'text';
            const icon = document.getElementById('toggleSenha')?.querySelector('i');
            if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        verificarDirty();
        Toast.fire({ icon: 'success', title: 'Senha gerada: ' + el.value });
    });

    // Auto-preenchimento defensivo (só se campo vazio)
    document.getElementById('Nome')?.addEventListener('input', function () {
        const valor = this.value.trim();
        if (!valor) return;
        const elU = document.getElementById('Usuario');
        const elS = document.getElementById('Senha');
        if (elU && !elU.value) {
            elU.value = gerarNomeUsuario(valor);
            elU.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (elS && !elS.value) {
            elS.value = gerarSenha(valor);
            elS.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    // ============================================================
    // FOTO — PREVIEW / UPLOAD / REMOÇÃO
    // ============================================================
    const fotoInput    = document.getElementById('Foto');
    const fotoPreview  = document.getElementById('fotoPreview');
    const btnRemoverFoto = document.getElementById('btnRemoverFoto');
    const removerFotoHidden = document.getElementById('RemoverFoto');

    let currentFotoDataUrl = HAS_FOTO_INICIAL
        ? (fotoPreview.querySelector('img')?.getAttribute('src') || null)
        : null;
    let fotoTouched = false;

    function atualizarFotoUI() {
        if (currentFotoDataUrl) {
            fotoPreview.innerHTML = '<img src="' + currentFotoDataUrl + '" alt="">';
            if (btnRemoverFoto) btnRemoverFoto.style.display = '';
        } else {
            fotoPreview.innerHTML = '<i class="fas fa-camera"></i>';
            if (btnRemoverFoto) btnRemoverFoto.style.display = 'none';
        }
    }

    fotoInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            Swal.fire({ icon: 'error', title: 'Arquivo inválido', text: 'Selecione uma imagem JPG, PNG ou WEBP.' });
            this.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: 'error', title: 'Imagem muito grande', text: 'O limite é 5 MB.' });
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            currentFotoDataUrl = e.target.result;
            removerFotoHidden.value = '0';
            atualizarFotoUI();
            fotoTouched = true;
            verificarDirty();
        };
        reader.readAsDataURL(file);
    });

    btnRemoverFoto?.addEventListener('click', function () {
        Swal.fire({
            title: 'Remover foto?',
            text: 'A foto atual do usuário será excluída ao salvar.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280'
        }).then(r => {
            if (!r.isConfirmed) return;
            if (fotoInput) fotoInput.value = '';
            currentFotoDataUrl = null;
            removerFotoHidden.value = '1';
            atualizarFotoUI();
            fotoTouched = true;
            verificarDirty();
        });
    });

    // ============================================================
    // Toggle senha
    // ============================================================
    document.getElementById('toggleSenha')?.addEventListener('click', function () {
        const input = document.getElementById('Senha');
        const icon  = this.querySelector('i');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });

    // ============================================================
    // Permissão → Função + Ações
    // ============================================================
    function aplicarPermissao(radio) {
        if (!radio) return;
        document.getElementById('Funcao').value = radio.dataset.funcao || '';
        document.getElementById('Acoes').value  = radio.dataset.acoes  || '';
        document.querySelectorAll('input[name="Permissao"]').forEach(r => {
            r.closest('.option-card')?.classList.toggle('is-checked', r.checked);
        });
    }
    document.querySelectorAll('input[name="Permissao"]').forEach(radio => {
        radio.addEventListener('change', () => aplicarPermissao(radio));
    });
    aplicarPermissao(document.querySelector('input[name="Permissao"]:checked'));

    // ============================================================
    // Grupo
    // ============================================================
    document.querySelectorAll('input[name="ID_Grupo"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('input[name="ID_Grupo"]').forEach(r => {
                r.closest('.option-card')?.classList.toggle('is-checked', r.checked);
            });
        });
    });

    // ============================================================
    // Dirty state
    // ============================================================
    let formOriginal = null;

    function capturarEstado() {
        const form = document.getElementById('formEditar');
        const data = new FormData(form);
        const obj = {};
        for (const [k, v] of data.entries()) {
            if (k === 'csrf_token' || k === 'Foto') continue;
            obj[k] = v;
        }
        return JSON.stringify(obj);
    }

    function verificarDirty() {
        if (!formOriginal) return false;
        const dirty = capturarEstado() !== formOriginal || fotoTouched;
        const badge = document.getElementById('dirtyBadge');
        const hint  = document.getElementById('actionHint');
        badge.classList.toggle('visible', dirty);
        hint.textContent = dirty ? 'Alterações não salvas' : 'Nenhuma alteração pendente';
        return dirty;
    }

    formOriginal = capturarEstado();
    document.querySelectorAll('#formEditar input, #formEditar select, #formEditar textarea').forEach(el => {
        el.addEventListener('input', verificarDirty);
        el.addEventListener('change', verificarDirty);
    });

    window.addEventListener('beforeunload', function (e) {
        if (verificarDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ============================================================
    // Submit
    // ============================================================
    document.getElementById('formEditar').addEventListener('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            this.reportValidity();
            return;
        }
        const btn = document.getElementById('btnSalvar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
    });

    // ============================================================
    // Flash
    // ============================================================
    <?php if (isset($_SESSION['MsgSucesso'])): ?>
        Toast.fire({ icon: 'success', title: <?= json_encode(strip_tags($_SESSION['MsgSucesso'])) ?> });
        <?php unset($_SESSION['MsgSucesso']); ?>
    <?php endif; ?>

})();
</script>

</body>
</html>
<?php
if (isset($conn) && $conn instanceof PDO) { $conn = null; }
if (isset($pdo)  && $pdo  instanceof PDO) { $pdo  = null; }
?>
<?php
// ============================================================
// session_start() PRIMEIRO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';

// ============================================================
// CONEXÃO — aceita $conn ou $pdo
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// ============================================================
// TEMA SEGURO
// ============================================================
$themeDefaults = [
    'primary'      => '#800020',
    'primary_dark' => '#4B0C1D',
    'secondary'    => '#6c757d',
    'accent'       => '#ffc107',
    'bg_body'      => '#f0f2f5',
    'text_color'   => '#1a1a2e',
    'card_bg'      => '#ffffff',
    'shadow_color' => '#1a1a2e',
    'btn_bg'       => '#800020',
    'btn_hover'    => '#4B0C1D',
    'btn_text'     => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ============================================================
// CONFIGURAÇÕES FIXAS
// ============================================================
$ID_EMP_FIXO    = 3;
$NOME_EMP_FIXO  = "Orla Imob";
$PERMISSAO_FIXA = 5;
$FUNCAO_FIXA    = "CORRETOR";
$ACOES_FIXAS    = "/CONSULTAR/";
$ATIVO_FIXO     = 0;

$ID_GRUPO_DEFAULT   = 11;
$NOME_GRUPO_DEFAULT = "ORLA_IMOB";

// ============================================================
// CARREGA GRUPOS DA TABELA `grupo`
// ============================================================
$grupos = [];
$erro_grupos = null;

try {
    $stmt = $db->query("SELECT ID_Grupo, Nome_Grupo, Descricao_Grupo 
                        FROM grupo 
                        ORDER BY Nome_Grupo ASC");
    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro_grupos = "Erro ao carregar grupos: " . $e->getMessage();
    error_log("[CADASTRO_USU] " . $e->getMessage());
}

$hasGrupos = !empty($grupos);

if (!$hasGrupos) {
    $grupos = [[
        'ID_Grupo'        => $ID_GRUPO_DEFAULT,
        'Nome_Grupo'      => $NOME_GRUPO_DEFAULT,
        'Descricao_Grupo' => 'Grupo padrão (fallback)',
    ]];
}

// ============================================================
// LIMPEZA DE SESSÃO EM GET
// ============================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['form_data']);
    unset($_SESSION['MsgErro']);
}

$erros = [];
$form_data = [
    'Nome' => '', 'Usuario' => '', 'Senha' => '', 'Email' => '',
    'CRECI' => '', 'Telefones' => '', 'GrupoId' => '',
];

// ============================================================
// PROCESSA POST
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome      = trim($_POST['Nome']      ?? '');
    $usuario   = trim($_POST['Usuario']   ?? '');
    $senha     = trim($_POST['Senha']     ?? '');
    $email     = trim($_POST['Email']     ?? '');
    $creci     = trim($_POST['CRECI']     ?? '');
    $telefones = trim($_POST['Telefones'] ?? '');
    $grupoId   = (int)($_POST['GrupoId']  ?? 0);

    $form_data = [
        'Nome'      => $nome,
        'Usuario'   => $usuario,
        'Senha'     => $senha,
        'Email'     => $email,
        'CRECI'     => $creci,
        'Telefones' => $telefones,
        'GrupoId'   => $grupoId,
    ];

    // Validações básicas
    if (empty($nome))    $erros[] = "Nome é obrigatório.";
    if (empty($usuario)) $erros[] = "Usuário é obrigatório.";
    if (empty($senha))   $erros[] = "Senha é obrigatória.";

    // Valida grupo
    $nomeGrupoSelecionado = null;
    if ($grupoId <= 0) {
        $erros[] = "Selecione um grupo de acesso.";
    } else {
        foreach ($grupos as $g) {
            if ((int)$g['ID_Grupo'] === $grupoId) {
                $nomeGrupoSelecionado = $g['Nome_Grupo'];
                break;
            }
        }
        if ($nomeGrupoSelecionado === null) {
            $erros[] = "Grupo selecionado (#$grupoId) não existe.";
        }
    }

    // Verifica duplicidade
    if (empty($erros)) {
        $stmt = $db->prepare("SELECT 1 FROM usuarios WHERE Usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) {
            $erros[] = "O usuário '$usuario' já existe.";
        }
    }

    // INSERT
    if (empty($erros)) {
        try {
            $db->beginTransaction();

            $sql = "INSERT INTO usuarios (
                Usuario, SenhaAtual, Nome, CRECI, Email, Telefones,
                Acoes, Permissao, Funcao, Ativo, idEmp, Empresa,
                GrupoId, Grupo, Data, UsuarioCadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

            $stmt = $db->prepare($sql);
            $usuarioLogado = $_SESSION['Usuario'] ?? 'SISTEMA';

            $stmt->execute([
                $usuario, $senha, $nome, $creci, $email, $telefones,
                $ACOES_FIXAS, $PERMISSAO_FIXA, $FUNCAO_FIXA, $ATIVO_FIXO,
                $ID_EMP_FIXO, $NOME_EMP_FIXO,
                $grupoId, $nomeGrupoSelecionado,
                $usuarioLogado
            ]);

            $new_user_id = $db->lastInsertId();

            // Vínculo pivô
            $stmt = $db->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)");
            $stmt->execute([$new_user_id, $grupoId]);

            $db->commit();

            // ==========================================================
            // EMAIL DE NOTIFICAÇÃO
            // ==========================================================
            $to      = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
            $subject = "SI-Novo Usuário-" . strtoupper($usuarioLogado) . " - " . $_SERVER['REMOTE_ADDR'] . " - " . date('Y-m-d H:i:s');

            $message  = "NOVO CORRETOR CADASTRADO NO SISTEMA\n";
            $message .= "==========================================\n\n";
            $message .= "DADOS DO NOVO USUÁRIO:\n";
            $message .= "------------------------------------------\n";
            $message .= "Nome Completo: " . $nome . "\n";
            $message .= "Usuário Login: " . $usuario . "\n";
            $message .= "Senha Inicial: " . $senha . "\n";
            $message .= "E-mail:        " . $email . "\n";
            $message .= "CRECI:         " . $creci . "\n";
            $message .= "Telefone:      " . $telefones . "\n";
            $message .= "Empresa:       " . $NOME_EMP_FIXO . "\n";
            $message .= "Grupo:         " . $nomeGrupoSelecionado . " (ID: " . $grupoId . ")\n";
            $message .= "Status:        PENDENTE (INATIVO)\n\n";

            $message .= "INFORMAÇÕES DE AUDITORIA:\n";
            $message .= "------------------------------------------\n";
            $message .= "Cadastrado por: " . $usuarioLogado . "\n";
            $message .= "Data/Hora:      " . date('d/m/Y H:i:s') . "\n";
            $message .= "IP Origem:      " . $_SERVER['REMOTE_ADDR'] . "\n";
            $message .= "Navegador:      " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";

            $headers  = "From: sendmail@gabnetweb.com.br\r\n";
            $headers .= "Reply-To: sendmail@gabnetweb.com.br\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            @mail($to, $subject, $message, $headers);

            $_SESSION['MsgSucesso'] = "Corretor <strong>" . htmlspecialchars($nome) . "</strong> cadastrado no grupo <strong>" . htmlspecialchars($nomeGrupoSelecionado) . "</strong>!";

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $erros[] = "Erro ao salvar: " . $e->getMessage();
            error_log("[CADASTRO_USU] " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro de Corretor · Gestão de Acesso</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            color: var(--text-color);
            min-height: 100vh;
            padding: 24px 16px 40px;
        }

        .page-container { max-width: 860px; margin: 0 auto; }

        /* ============================================================ */
        /* HEADER                                                       */
        /* ============================================================ */
        .page-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            border-radius: 18px 18px 0 0;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            margin-bottom: 24px;
        }
        .page-header::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
            border-radius: 18px 18px 0 0;
        }
        .header-logo {
            height: 54px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
            background: #fff;
            border: 3px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 4px 8px;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }
        .header-text { flex: 1; min-width: 0; position: relative; z-index: 1; }
        .header-text h1 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 900;
            letter-spacing: -0.3px;
            line-height: 1.15;
        }
        .header-text p {
            margin: 4px 0 0;
            font-size: 0.78rem;
            font-weight: 600;
            opacity: 0.9;
        }

        /* ============================================================ */
        /* CARDS                                                        */
        /* ============================================================ */
        .card-neo {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            margin-bottom: 24px;
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
            font-size: 1.02rem;
            font-weight: 900;
            letter-spacing: -0.2px;
        }
        .card-neo-body { padding: 22px; }

        /* ============================================================ */
        /* FORM                                                         */
        /* ============================================================ */
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

        .form-control {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 14px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.92rem;
            color: var(--text-color);
            background: #fff;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .form-control::placeholder { color: #b8bcc4; font-weight: 400; }

        /* ============================================================ */
        /* GRUPO — CARDS RADIO                                          */
        /* ============================================================ */
        .grupo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 6px;
        }
        .grupo-card {
            position: relative;
            display: block;
            cursor: pointer;
            background: #f8f9fb;
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            user-select: none;
        }
        .grupo-card:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            background: #fff;
        }
        .grupo-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .grupo-card .grupo-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .grupo-card .grupo-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 0.92rem;
            color: var(--text-color);
        }
        .grupo-card .grupo-title i {
            color: var(--primary);
            font-size: 0.9rem;
            transition: transform 0.15s;
        }
        .grupo-card .grupo-desc {
            font-size: 0.76rem;
            color: var(--text-color);
            opacity: 0.65;
            font-weight: 500;
            line-height: 1.35;
        }
        .grupo-card .grupo-id {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.66rem;
            background: rgba(0,0,0,0.08);
            padding: 1px 6px;
            border-radius: 4px;
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 700;
            margin-left: auto;
        }
        /* Estado selecionado (JS aplica .is-checked) */
        .grupo-card.is-checked {
            background: var(--primary);
            border-color: var(--primary-dark);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .grupo-card.is-checked .grupo-title,
        .grupo-card.is-checked .grupo-title i,
        .grupo-card.is-checked .grupo-desc,
        .grupo-card.is-checked .grupo-id {
            color: #fff;
        }
        .grupo-card.is-checked .grupo-id { background: rgba(255,255,255,0.2); }

        /* ============================================================ */
        /* BOTÃO                                                        */
        /* ============================================================ */
        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 13px 24px;
            font-weight: 900;
            font-size: 0.92rem;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            width: 100%;
        }
        .btn-neo-primary {
            background: var(--btn-bg);
            color: var(--btn-text);
        }
        .btn-neo-primary:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ============================================================ */
        /* ALERTAS                                                      */
        /* ============================================================ */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 0.88rem;
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-neo i { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
        .alert-neo.alert-success { background: var(--success); color: #fff; }
        .alert-neo.alert-danger  { background: var(--danger);  color: #fff; }
        .alert-neo.alert-warning { background: var(--warning); color: #000; }
        .alert-neo.alert-info    { background: var(--info);    color: #fff; }
        .alert-neo ul { margin: 0; padding-left: 18px; }

        /* ============================================================ */
        /* TABELA HISTÓRICO                                             */
        /* ============================================================ */
        table.table-neo {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--card-bg);
            color: var(--text-color);
        }
        table.table-neo thead th {
            background: var(--primary);
            color: #fff;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.8px;
            padding: 12px;
            border: none;
            border-bottom: 3px solid var(--shadow-color);
        }
        table.table-neo thead th:first-child { border-top-left-radius: 10px; }
        table.table-neo thead th:last-child  { border-top-right-radius: 10px; }
        table.table-neo tbody td {
            padding: 12px;
            font-size: 0.85rem;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.07);
            vertical-align: middle;
        }
        table.table-neo tbody tr:last-child td { border-bottom: none; }
        table.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }

        .badge-grupo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary);
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.68rem;
            letter-spacing: 0.3px;
            border: 1.5px solid var(--shadow-color);
        }
        .cell-nome { font-weight: 800; color: var(--text-color); }
        .cell-user {
            font-family: 'SFMono-Regular', Consolas, monospace;
            background: rgba(0,0,0,0.06);
            color: var(--text-color);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .cell-date { color: var(--text-color); opacity: 0.7; font-size: 0.78rem; white-space: nowrap; }

        .table-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-color);
            opacity: 0.55;
            font-weight: 600;
            font-style: italic;
        }

        /* ============================================================ */
        /* FOOTER                                                       */
        /* ============================================================ */
        .page-footer {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-color);
            opacity: 0.65;
            padding: 26px 10px 0;
            font-weight: 500;
        }
        .page-footer strong { color: var(--primary); }

        /* ============================================================ */
        /* RESPONSIVO                                                   */
        /* ============================================================ */
        @media (max-width: 576px) {
            body { padding: 12px; }
            .page-header { padding: 12px 16px; border-radius: 14px 14px 0 0; }
            .header-logo { height: 42px; max-width: 100px; padding: 3px 5px; }
            .header-text h1 { font-size: 1rem; }
            .header-text p { font-size: 0.7rem; }
            .card-neo-body { padding: 16px; }
            .card-neo-header { padding: 12px 16px; }
            .grupo-grid { grid-template-columns: 1fr; }
            table.table-neo thead th,
            table.table-neo tbody td { padding: 8px; font-size: 0.78rem; }
        }
    </style>
</head>
<body>

<div class="page-container">

    <!-- ============================================ -->
    <!-- HEADER                                        -->
    <!-- ============================================ -->
    <header class="page-header">
        <img src="img/logosunny.jpg" alt="Logo" class="header-logo" onerror="this.style.display='none'">
        <div class="header-text">
            <h1><i class="fas fa-user-plus me-2"></i> Cadastro de Corretor</h1>
            <p>Associação automática a grupo · <?= htmlspecialchars($NOME_EMP_FIXO) ?></p>
        </div>
    </header>

    <!-- ============================================ -->
    <!-- MENSAGENS                                     -->
    <!-- ============================================ -->
    <?php if (isset($_SESSION['MsgSucesso'])): ?>
        <div class="alert-neo alert-success">
            <i class="fas fa-check-circle"></i>
            <div><?= $_SESSION['MsgSucesso']; unset($_SESSION['MsgSucesso']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($erros)): ?>
        <div class="alert-neo alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php if (count($erros) === 1): ?>
                    <?= htmlspecialchars($erros[0]); ?>
                <?php else: ?>
                    <ul>
                        <?php foreach ($erros as $e): ?>
                            <li><?= htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$hasGrupos): ?>
        <div class="alert-neo alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Tabela <code>grupo</code> está vazia.</strong>
                Usando grupo padrão (ID <?= $ID_GRUPO_DEFAULT ?>). Cadastre grupos no banco para opções adicionais.
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- FORMULÁRIO                                    -->
    <!-- ============================================ -->
    <div class="card-neo">
        <div class="card-neo-header">
            <i class="fas fa-id-card"></i>
            <h2>Dados do Novo Corretor</h2>
        </div>
        <div class="card-neo-body">
            <form method="post" id="formCadastro" autocomplete="off">
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="field-label" for="Nome">
                            <i class="fas fa-user"></i> Nome Completo <span class="req">*</span>
                        </label>
                        <input type="text" class="form-control" name="Nome" id="Nome"
                               value="<?= htmlspecialchars($form_data['Nome']) ?>"
                               placeholder="Ex.: João Silva" required autofocus>
                    </div>

                    <div class="col-md-4">
                        <label class="field-label" for="Usuario">
                            <i class="fas fa-user-tag"></i> Usuário <span class="req">*</span>
                        </label>
                        <input type="text" class="form-control" name="Usuario" id="Usuario"
                               value="<?= htmlspecialchars($form_data['Usuario']) ?>"
                               placeholder="joaosilva" required>
                    </div>

                    <div class="col-md-4">
                        <label class="field-label" for="Senha">
                            <i class="fas fa-key"></i> Senha Inicial <span class="req">*</span>
                        </label>
                        <input type="text" class="form-control" name="Senha" id="Senha"
                               value="<?= htmlspecialchars($form_data['Senha']) ?>"
                               placeholder="joao@123" required>
                    </div>

                    <div class="col-md-4">
                        <label class="field-label" for="Telefones">
                            <i class="fas fa-phone"></i> Telefone
                        </label>
                        <input type="text" class="form-control" name="Telefones" id="Telefones"
                               value="<?= htmlspecialchars($form_data['Telefones']) ?>"
                               placeholder="(81) 99999-9999">
                    </div>

                    <div class="col-md-8">
                        <label class="field-label" for="Email">
                            <i class="fas fa-envelope"></i> E-mail
                        </label>
                        <input type="email" class="form-control" name="Email" id="Email"
                               value="<?= htmlspecialchars($form_data['Email']) ?>"
                               placeholder="joao@empresa.com.br">
                    </div>

                    <div class="col-md-4">
                        <label class="field-label" for="CRECI">
                            <i class="fas fa-id-badge"></i> CRECI
                        </label>
                        <input type="text" class="form-control" name="CRECI" id="CRECI"
                               value="<?= htmlspecialchars($form_data['CRECI']) ?>"
                               placeholder="123456">
                    </div>
                </div>

                <!-- ==================================== -->
                <!-- GRUPO DE ACESSO                       -->
                <!-- ==================================== -->
                <div class="mt-4 mb-3" style="padding-top:18px;border-top:2px dashed rgba(0,0,0,0.12);">
                    <label class="field-label" style="font-size:0.82rem;">
                        <i class="fas fa-users"></i> Grupo de Acesso <span class="req">*</span>
                        <span style="font-weight:500; opacity:0.6; text-transform:none; letter-spacing:0; margin-left:6px;">
                            — define permissões do novo usuário
                        </span>
                    </label>

                    <div class="grupo-grid">
                        <?php foreach ($grupos as $g):
                            $gId    = (int)$g['ID_Grupo'];
                            $gNome  = $g['Nome_Grupo'];
                            $gDesc  = $g['Descricao_Grupo'] ?? '';
                            $checked = ((int)$form_data['GrupoId'] === $gId) ? 'checked' : '';
                        ?>
                            <label class="grupo-card <?= $checked ? 'is-checked' : '' ?>">
                                <input type="radio" name="GrupoId" value="<?= $gId ?>" <?= $checked ?> required>
                                <div class="grupo-content">
                                    <div class="grupo-title">
                                        <i class="fas fa-users"></i>
                                        <span><?= htmlspecialchars($gNome) ?></span>
                                        <span class="grupo-id">#<?= $gId ?></span>
                                    </div>
                                    <?php if (!empty($gDesc)): ?>
                                        <div class="grupo-desc"><?= htmlspecialchars($gDesc) ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ==================================== -->
                <!-- BOTÃO                                 -->
                <!-- ==================================== -->
                <div class="mt-4">
                    <button type="submit" class="btn-neo btn-neo-primary" id="btnGravar">
                        <i class="fas fa-save"></i> Gravar Corretor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- HISTÓRICO                                     -->
    <!-- ============================================ -->
    <div class="card-neo">
        <div class="card-neo-header">
            <i class="fas fa-history"></i>
            <h2>Histórico Recente — Últimos 20</h2>
        </div>
        <div style="overflow-x:auto;">
            <table class="table-neo">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Grupo</th>
                        <th>Cadastrado por</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                try {
                    $stmt = $db->query("
                        SELECT Nome, Usuario, UsuarioCadastro, Data, GrupoId, Grupo
                        FROM usuarios
                        ORDER BY Data DESC
                        LIMIT 20
                    ");
                    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $registros = [];
                    error_log("[CADASTRO_USU] Histórico: " . $e->getMessage());
                }

                if (empty($registros)):
                ?>
                    <tr><td colspan="5" class="table-empty">
                        <i class="fas fa-inbox me-2"></i> Nenhum cadastro recente
                    </td></tr>
                <?php else: foreach ($registros as $row): ?>
                    <tr>
                        <td class="cell-nome"><?= htmlspecialchars($row['Nome']) ?></td>
                        <td><span class="cell-user"><?= htmlspecialchars($row['Usuario']) ?></span></td>
                        <td>
                            <?php if (!empty($row['Grupo'])): ?>
                                <span class="badge-grupo">
                                    <i class="fas fa-users"></i>
                                    <?= htmlspecialchars($row['Grupo']) ?>
                                    <?php if (!empty($row['GrupoId'])): ?>
                                        <span style="opacity:0.7;font-size:0.6rem;">#<?= (int)$row['GrupoId'] ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span style="opacity:0.4;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-transform:uppercase; font-size:0.78rem; font-weight:700; opacity:0.7;">
                            <?= htmlspecialchars($row['UsuarioCadastro'] ?? 'SISTEMA') ?>
                        </td>
                        <td class="cell-date"><?= date('d/m/Y H:i', strtotime($row['Data'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FOOTER                                        -->
    <!-- ============================================ -->
    <footer class="page-footer">
        <p style="margin:0;">
            &copy; <?= date("Y") ?> Desenvolvido por <strong>Valter Barreto</strong> · Todos os direitos reservados
        </p>
    </footer>

</div>

<script>
(function () {
    'use strict';

    // ============================================================
    // Auto-fill: usuário/senha/email a partir do nome
    // Só preenche se o campo não foi editado manualmente
    // ============================================================
    const formatar = t => t.normalize("NFD").replace(/[\u0300-\u036f]/g, "")
                          .toLowerCase().replace(/[^a-z]/g, "");

    const nomeEl  = document.getElementById('Nome');
    const userEl  = document.getElementById('Usuario');
    const senhaEl = document.getElementById('Senha');
    const emailEl = document.getElementById('Email');

    [userEl, senhaEl, emailEl].forEach(el => {
        if (!el) return;
        el.addEventListener('input', () => el.dataset.touched = '1');
    });

    if (nomeEl) {
        nomeEl.addEventListener('input', function () {
            const val = this.value.trim();
            if (val.length <= 2) return;

            const partes = val.split(/\s+/);
            const pri    = formatar(partes[0] || '');
            const sub    = partes[1] ? formatar(partes[1])[0] : '';
            const novoUser = pri + sub;

            if (userEl && (!userEl.dataset.touched || userEl.value === userEl.dataset.lastGen)) {
                userEl.value = novoUser;
                userEl.dataset.lastGen = novoUser;
            }
            if (senhaEl && (!senhaEl.dataset.touched || !senhaEl.value)) {
                senhaEl.value = pri + '@123';
                senhaEl.dataset.lastGen = pri + '@123';
            }
            if (emailEl && !emailEl.dataset.touched && !emailEl.value) {
                emailEl.value = pri + '@meuemail.com.br';
            }
        });
    }

    // ============================================================
    // Grupo — radio cards
    // ============================================================
    const grupoCards = document.querySelectorAll('.grupo-card');
    grupoCards.forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        if (!radio) return;

        if (radio.checked) card.classList.add('is-checked');

        radio.addEventListener('change', () => {
            grupoCards.forEach(c => c.classList.remove('is-checked'));
            if (radio.checked) card.classList.add('is-checked');
        });
    });

    // ============================================================
    // Submit — valida grupo e bloqueia botão
    // ============================================================
    const form = document.getElementById('formCadastro');
    const btn  = document.getElementById('btnGravar');

    if (form && btn) {
        form.addEventListener('submit', function (e) {
            const grupoSelecionado = form.querySelector('input[name="GrupoId"]:checked');
            if (!grupoSelecionado) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Selecione um grupo',
                    text: 'O novo corretor precisa ser associado a um grupo de acesso.',
                    confirmButtonColor: '<?= $themeSafe['primary'] ?>'
                });
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gravando…';
        });
    }

    // ============================================================
    // Auto-dismiss do alerta de sucesso
    // ============================================================
    const okAlert = document.querySelector('.alert-neo.alert-success');
    if (okAlert) {
        setTimeout(() => {
            okAlert.style.transition = 'opacity 0.4s, transform 0.4s';
            okAlert.style.opacity = '0';
            okAlert.style.transform = 'translateY(-10px)';
            setTimeout(() => okAlert.remove(), 500);
        }, 4000);
    }
})();
</script>

</body>
</html>
<?php
// Fecha conexão com segurança
if (isset($conn) && $conn instanceof PDO) { $conn = null; }
if (isset($pdo)  && $pdo  instanceof PDO) { $pdo  = null; }
?>
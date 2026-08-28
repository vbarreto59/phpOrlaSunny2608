<?php
// login_p1.php - Versão com tema neobrutalista
session_start();

require_once "conn.php";
require_once "usr_acoes.php";
require_once 'send_email.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

// Configuração da Manutenção
$sistema_id = 'sunny'; 
$arquivo_manut = $_SERVER['DOCUMENT_ROOT'] . "/manut/manut_{$sistema_id}.txt";

// =================================================================================
// PROCESSAMENTO DO LOGIN (POST)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = trim($_POST['senha']   ?? '');

    // --- VERIFICAÇÃO DE MANUTENÇÃO APÓS SUBMISSÃO ---
    if (file_exists($arquivo_manut)) {
        if (strtolower($usuario) !== 'barreto') {
            header("Location: paginaManut.php");
            exit;
        }
    }

    if (empty($usuario) || empty($senha)) {
        header("Location: login_p1.php?msg=" . urlencode("Usuário e senha são obrigatórios.") . "&tipo=warning&usuario=" . urlencode($usuario));
        exit;
    }

    $loginResult = VerificarLogin($usuario, $senha);

    if ($loginResult['success']) {
        $userData = $loginResult['data'];
        $ativo   = (int)($userData['Ativo'] ?? 0);
        $licenca = (bool)($userData['Licenca'] ?? false);

        $_SESSION['UserId']    = (int)$userData['UserId'];
        $_SESSION['IdEmp']    = (int)$userData['IdEmp'];
        $_SESSION['Usuario']   = $userData['Usuario'];
        $_SESSION['Permissao'] = Nz($userData['Permissao']);
        $_SESSION['Ativo']     = $ativo;
        $_SESSION['Licenca']   = $licenca;

        // --- ENVIO DE E-MAIL (Lógica preservada) ---
        $nomeExibido = Nz($userData['Nome'], $userData['Usuario']);
        $assunto = mb_encode_mimeheader("SI Login - " . $userData['Usuario'], 'UTF-8', 'B');
        $destinatario = 'valterpb@gmail.com,valterpb@hotmail.com';
        // enviarEmailSGVendas($destinatario, $assunto, $mensagemHTML);

        if ($ativo === 1 && $licenca === true) {
            ?>
            <!DOCTYPE html>
            <html lang="pt-br">
            <head>
                <meta charset="UTF-8">
                <title>Redirecionando...</title>
                <style>
                    :root {
                        --primary: <?php echo $theme['primary']; ?>;
                        --shadow-color: <?php echo $theme['shadow_color']; ?>;
                        --bg-body: <?php echo $theme['bg_body']; ?>;
                        --text-color: <?php echo $theme['text_color']; ?>;
                    }
                    body { margin:0; background:var(--bg-body); display:flex; flex-direction:column; justify-content:center; align-items:center; height:100vh; font-family:'Inter',system-ui,sans-serif; color:var(--text-color); }
                    .logo-load { max-height:60vh; margin-bottom:2rem; border:4px solid var(--shadow-color); border-radius:16px; box-shadow:6px 6px 0px 0px var(--shadow-color); }
                    .spinner { width:60px; height:60px; border:8px solid #f3f3f3; border-top:8px solid var(--primary); border-radius:50%; animation:spin 1.2s linear infinite; }
                    @keyframes spin { to { transform:rotate(360deg); } }
                    p { color:var(--text-color); font-size:1.4rem; margin-top:1.8rem; font-weight:600; }
                </style>
            </head>
            <body>
                <img src="img/sunnyimob.jpg" class="logo-load" alt="SunnyImob">
                <div class="spinner"></div>
                <p>Carregando sistema...</p>
                <script>setTimeout(() => { window.location.href = 'mainpage_v66a.php'; }, 2000);</script>
            </body>
            </html>
            <?php
            exit();
        } else {
            $msg = ($ativo === 0) ? "Conta inativa." : "Sem licença ativa.";
            header("Location: login_p1.php?msg=" . urlencode($msg) . "&tipo=danger&usuario=" . urlencode($usuario));
            exit;
        }
    } else {
        header("Location: login_p1.php?msg=" . urlencode("Usuário ou senha inválidos.") . "&tipo=danger&usuario=" . urlencode($usuario));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SunnyImob - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA (via variáveis CSS)                  */
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
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            margin: 0;
        }

        /* Card de login neobrutalista */
        .login-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 20px;
            box-shadow: 8px 8px 0px 0px var(--shadow-color);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            transition: all 0.2s;
        }
        .login-card:hover {
            transform: translateY(-3px);
            box-shadow: 10px 10px 0px 0px var(--shadow-color);
        }

        .card-header {
            background: var(--primary);
            color: #fff;
            text-align: center;
            padding: 1.8rem 1rem;
            border-bottom: 3px solid var(--shadow-color);
        }
        .card-header h3 {
            font-weight: 800;
            font-size: 2rem;
            margin: 0;
        }
        .card-header small {
            display: block;
            font-weight: 600;
            opacity: 0.9;
        }

        .maint-badge {
            background: #fff3cd;
            color: #856404;
            padding: 0.8rem 1rem;
            font-size: 0.9rem;
            text-align: center;
            border-bottom: 3px solid var(--shadow-color);
            font-weight: 600;
        }
        .maint-badge i {
            margin-right: 6px;
        }

        .card-body {
            padding: 2rem 1.8rem;
        }

        /* Alertas neobrutalistas */
        .alert-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            padding: 0.8rem 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-danger-neo {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        .alert-warning-neo {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }

        .form-control {
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 0.7rem 1rem 0.7rem 2.8rem;
            background: #fff;
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.2s;
            width: 100%;
        }
        .form-control:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .input-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--shadow-color);
            font-size: 1.2rem;
            z-index: 10;
            opacity: 0.7;
        }
        .position-relative {
            position: relative;
        }

        .btn-primary-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-radius: 12px;
            padding: 0.8rem;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-primary-neo:active {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }

        .footer-text {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            text-align: center;
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 600;
        }

        .logo-img {
            display: block;
            margin: 0 auto 1.5rem;
            max-width: 85%;
            height: auto;
            border-radius: 12px;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
        }

        /* Responsivo */
        @media (max-width: 576px) {
            .login-card { max-width: 100%; }
            .card-body { padding: 1.5rem; }
            .card-header h3 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card-header">
        <h3>SunnyImob</h3>
        <small>Sistema de Gestão Imobiliária</small>
    </div>

    <?php if (file_exists($arquivo_manut)): ?>
        <div class="maint-badge">
            <i class="fas fa-tools"></i> <strong>Modo Manutenção Ativo</strong><br>
            Acesso exclusivo para administradores.
        </div>
    <?php endif; ?>

    <div class="card-body">
        <?php if (isset($_GET['msg'])): ?>
            <?php 
                $tipo = $_GET['tipo'] ?? 'danger';
                $classe = ($tipo === 'warning') ? 'alert-warning-neo' : 'alert-danger-neo';
            ?>
            <div class="alert-neo <?= $classe ?> mb-4" role="alert">
                <i class="fas <?= ($tipo === 'warning') ? 'fa-exclamation-triangle' : 'fa-times-circle' ?>"></i>
                <?= htmlspecialchars(urldecode($_GET['msg'])) ?>
            </div>
        <?php endif; ?>

        <img src="img/logoSunny66a.JPG" alt="Logo SunnyImob" class="logo-img">

        <form method="post">
            <div class="mb-3 position-relative">
                <i class="fas fa-user input-icon"></i>
                <input type="text" class="form-control" name="usuario" placeholder="Usuário" 
                       value="<?= htmlspecialchars($_GET['usuario'] ?? '') ?>" required autofocus>
            </div>

            <div class="mb-4 position-relative">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" class="form-control" name="senha" placeholder="Senha" required>
            </div>

            <button type="submit" class="btn-primary-neo">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>

        <div class="footer-text">
            SunnyImob • 2026
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
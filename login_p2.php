<?php
// =======================================================
// SunnyImob - versão PHP com PDO - Início 16 01 2026
// Valter Barreto - Convertido para PDO
// Modificação: tratamento específico para usuário inativo
// =======================================================
session_start();

// Incluir arquivos de configuração
require_once "conn.php";
require_once "usr_acoes.php";
require_once 'send_email.php';

// =================================================================================
// FUNÇÕES DE LOGIN E AUXILIARES
// =================================================================================

function Nz($value) {
    return ($value === null) ? "" : $value;
}

/**
 * Verifica login válido e conta ATIVA
 */
function VerificarLogin($usuario, $senha) {
    global $pdo;
    $success = false;
   
    $sql = "SELECT UserId, Usuario, Permissao FROM usuarios
            WHERE Ativo = 1
            AND LOWER(Usuario) = LOWER(:usuario)
            AND LOWER(SenhaAtual) = LOWER(:senha)";
   
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->bindParam(':senha', $senha, PDO::PARAM_STR);
        $stmt->execute();
       
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $success = true;
            $_SESSION['UserId'] = $row['UserId'];
            $_SESSION['Usuario'] = $row['Usuario'];
            $_SESSION['Permissao'] = $row['Permissao'];

        }
       
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Erro na consulta SQL de login: " . $e->getMessage());
    }
   
    return $success;
}

/**
 * Verifica se existe conta com credenciais corretas mas INATIVA
 */
function VerificarContaInativa($usuario, $senha) {
    global $pdo;
    
    $sql = "SELECT UserId FROM usuarios
            WHERE Ativo = 0
            AND LOWER(Usuario) = LOWER(:usuario)
            AND LOWER(SenhaAtual) = LOWER(:senha)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->bindParam(':senha', $senha, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar conta inativa: " . $e->getMessage());
        return false;
    }
}

// Mantidas exatamente como no original
function VerificarDiretor($UserId) {
    global $pdo;
   
    $sql = "SELECT NomeDiretoria, NomeDiretor, DiretoriaId, Nome As Diretor
            FROM diretorias
            WHERE UserId = :userid";
   
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':userid', $UserId, PDO::PARAM_INT);
        $stmt->execute();
       
        $_SESSION['Dir_Nome'] = "";
        $_SESSION['Dir_UserId'] = "";
        $_SESSION['Dir_DiretoriaID'] = "";
        $_SESSION['Dir_NomeDiretoria'] = "";
       
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['NomeDiretor'])) {
                $_SESSION['Dir_Nome'] = $row['Diretor'];
                $_SESSION['Dir_UserId'] = $UserId;
                $_SESSION['Dir_DiretoriaID'] = $row['DiretoriaId'];
                $_SESSION['Dir_NomeDiretoria'] = $row['NomeDiretoria'];
               
                $logMessage = date('Y-m-d H:i:s') . " - Usuário ID: $UserId identificado como Diretor: " .
                             $_SESSION['Dir_Nome'] . " - Diretoria: " . $_SESSION['Dir_NomeDiretoria'] .
                             " (ID: " . $_SESSION['Dir_DiretoriaID'] . ")" . PHP_EOL;
               
                file_put_contents('diretor.log', $logMessage, FILE_APPEND | LOCK_EX);
               
                echo "<script>console.log('Usuário identificado como Diretor: " . addslashes($_SESSION['Dir_Nome']) . "');</script>";
            } else {
                echo "<script>console.log('Usuário não é um Diretor.');</script>";
            }
        } else {
            echo "<script>console.log('Usuário não encontrado na tabela de diretorias.');</script>";
        }
       
        $stmt->closeCursor();
    } catch (PDOException $e) {
        $errorMessage = date('Y-m-d H:i:s') . " - Erro ao verificar diretor para UserId: $UserId - " .
                       $e->getMessage() . PHP_EOL;
        file_put_contents('diretor.log', $errorMessage, FILE_APPEND | LOCK_EX);
       
        error_log("Erro ao verificar diretor: " . $e->getMessage());
        echo "<script>console.error('Erro ao verificar diretor');</script>";
    }
}

function IdentificarEmpresaUsuario($usuario) {
    global $pdo;
   
    $sql = "SELECT e.* FROM sunnyempresas e
            INNER JOIN usuarios u ON u.IdEmp = e.IdEmp
            WHERE u.usuario = :usuario";
   
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
       
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['IdEmp'] = $row['IdEmp'];
            $_SESSION['EmpresaNome'] = $row['EmpresaSunny'];
           
            foreach ($row as $nomeCampo => $valor) {
                if (strpos($nomeCampo, 'Conf_') === 0) {
                    $_SESSION[$nomeCampo] = Nz($valor);
                }
            }
        } else {
            $sql2 = "SELECT * FROM sunnyempresas ORDER BY IdEmp LIMIT 1";
            $stmt2 = $pdo->query($sql2);
           
            if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['IdEmp'] = $row2['IdEmp'];
                $_SESSION['EmpresaNome'] = $row2['EmpresaSunny'];
               
                foreach ($row2 as $nomeCampo => $valor) {
                    if (strpos($nomeCampo, 'Conf_') === 0) {
                        $_SESSION[$nomeCampo] = Nz($valor);
                    }
                }
            }
        }
       
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Erro ao identificar empresa: " . $e->getMessage());
        echo "<script>console.error('Erro ao identificar empresa');</script>";
    }
}

function RegistrarLog($usuario, $sucesso) {
    global $pdo;
   
    $sql = "SELECT UserId FROM usuarios WHERE LOWER(usuario) = LOWER(:usuario)";
   
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
        $stmt->execute();
       
        $idUsuario = 0;
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $idUsuario = $row['UserId'];
        }
       
        $stmt->closeCursor();
       
        $resultado = $sucesso
            ? strtoupper($usuario) . ": Login realizado com sucesso"
            : "Tentativa de login falhou";
       
        if ($idUsuario > 0) {
            $sql2 = "INSERT INTO histatulog (idUser, Usuario, Historico)
                     VALUES (:idUser, :usuario, :historico)";
           
            $stmt2 = $pdo->prepare($sql2);
           
            if ($stmt2) {
                $stmt2->bindParam(':idUser', $idUsuario, PDO::PARAM_INT);
                $stmt2->bindParam(':usuario', $usuario, PDO::PARAM_STR);
                $stmt2->bindParam(':historico', $resultado, PDO::PARAM_STR);
                $stmt2->execute();
                $stmt2->closeCursor();
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

// =================================================================================
// BLOCO DE PROCESSAMENTO PRINCIPAL (POST)
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';
   
    if (empty($usuario) || empty($senha)) {
        $error_message = "Usuário e senha são obrigatórios!";
    } else {
        // Caso 1: login válido e ativo
        if (VerificarLogin($usuario, $senha)) {
           
            if (isset($_SESSION['UserId']) && is_numeric($_SESSION['UserId']) && $_SESSION['UserId'] > 0) {
                VerificarDiretor($_SESSION['UserId']);
            }
           
            RegistrarLog($usuario, true);
            IdentificarEmpresaUsuario($_SESSION['Usuario']);
           
            $_SESSION['Acoes'] = AcoesPermitidas();
            $_SESSION['Funcao'] = UserFuncao();
                       
            // E-mail de login bem-sucedido (mantido exatamente como original)
            $destinatario = 'valterpb@gmail.com,valterpb@hotmail.com';
            $assunto = 'SI Login - '.$_SESSION['Usuario'];
            $mensagemHTML = "
                <div style='background-color: #f4f4f4; padding: 20px;'>
                    <h2 style='color: #0056b3;'>Login no sistema</h2>
                    <p>Data: " . date('d/m/Y') . "</p>
                </div>
            ".$_SESSION['Usuario'];
           
            enviarEmailSGVendas($destinatario, $assunto, $mensagemHTML);
           
            // Tela de loading original (overlay + imagem + spinner)
            ?>
            <!DOCTYPE html>
            <html lang="pt-br">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Redirecionando...</title>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    @media (min-width: 577px) {
                        div#overlay {
                            background: transparent;
                        }
                        img#login-img {
                            width: auto;
                            height: 100%;
                            object-fit: contain;
                        }
                    }
                </style>
            </head>
            <body>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const overlay = document.createElement('div');
                        overlay.id = 'overlay';
                        overlay.style.cssText = `
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: <?php echo isset($_SESSION['conf_CorFundo']) ? $_SESSION['conf_CorFundo'] : '#b3cce6'; ?>;
                            display: flex;
                            flex-direction: column;
                            justify-content: center;
                            align-items: center;
                            z-index: 2000;
                        `;
                       
                        const img = document.createElement('img');
                        img.id = 'login-img';
                        img.src = 'img/sunnyimob.jpg';
                        img.style.cssText = `
                            height: 100%;
                            object-fit: cover;
                        `;
                       
                        const spinner = document.createElement('div');
                        spinner.style.cssText = `
                            width: 40px;
                            height: 40px;
                            border: 4px solid #f3f3f3;
                            border-top: 4px solid #004d99;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            position: absolute;
                            bottom: 20px;
                        `;
                       
                        overlay.appendChild(img);
                        overlay.appendChild(spinner);
                        document.body.appendChild(overlay);
                       
                        setTimeout(function() {
                            window.location.href = 'mainpage_v66a.php';
                        }, 1500);
                    });
                </script>
            </body>
            </html>
            <?php
            exit();
        } 
        // Caso 2: credenciais corretas, mas conta INATIVA
        elseif (VerificarContaInativa($usuario, $senha)) {
            RegistrarLog($usuario, false);
            
            // Enviar e-mail específico
            $destInativo = 'valterpb@hotmail.com';
            $assuntoInativo = 'LOGIN INATIVO-' . htmlspecialchars($usuario);
            $mensagemInativo = "
                <h2>Tentativa de login em conta inativa</h2>
                <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>Usuário:</strong> " . htmlspecialchars($usuario) . "</p>
                <p><strong>IP:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido') . "</p>
            ";
            
            enviarEmailSGVendas($destInativo, $assuntoInativo, $mensagemInativo);
            
            // Redireciona para página de erro
            header("Location: erro_login.php");
            exit();
        } 
        // Caso 3: credenciais inválidas (usuário não existe ou senha errada)
        else {
            RegistrarLog($usuario, false);
            $error_message = "Erro de acesso. Procure o suporte.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #004d99;
        --primary-light: #4c88c7;
        --primary-lighter: #e1f0ff;
        --primary-soft: #f0f8ff;
        --white: #FFFFFF;
        --bordo-principal: #003366;
        --light-gray: #dee2e6;
        --shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
        --text-color: #333;
        --secondary-color: #f8f9fa;
    }
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Segoe UI', 'Roboto', sans-serif;
        background-color: #b3cce6;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        color: var(--text-color);
    }
    .login-container {
        width: 100%;
        max-width: 450px;
        background: var(--white);
        border-radius: 16px;
        box-shadow: var(--shadow);
        overflow: hidden;
        position: relative;
        z-index: 1;
    }
    .login-header {
        background: var(--primary-color);
        color: var(--white);
        padding: 1.5rem;
        text-align: center;
        position: relative;
    }
    .login-header h1 {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .login-header p {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    .login-body {
        padding: 2rem;
    }
    .logo-container {
        display: flex;
        justify-content: center;
        margin-bottom: 2rem;
    }
    .logo-animado {
        max-width: 80%;
        height: auto;
        opacity: 0;
        transform: scale(0.8);
        animation: fadeInZoom 1s ease-out forwards;
        animation-delay: 0.3s;
    }
    @keyframes fadeInZoom {
        0% { opacity: 0; transform: scale(0.8); }
        100% { opacity: 1; transform: scale(1); }
    }
    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-color);
    }
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        font-size: 1rem;
        border: 1px solid var(--light-gray);
        border-radius: 8px;
        background-color: var(--secondary-color);
        transition: var(--transition);
    }
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 77, 153, 0.1);
        outline: none;
        background-color: var(--white);
    }
    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #777;
    }
    .btn-login {
        background: var(--primary-color);
        color: var(--white);
        border: none;
        padding: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-login:hover {
        background: var(--bordo-principal);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 77, 153, 0.2);
    }
    .btn-login:active {
        transform: translateY(0);
    }
    .footer-links {
        margin-top: 1.5rem;
        text-align: center;
        font-size: 0.85rem;
    }
    .footer-links a {
        color: var(--primary-color);
        text-decoration: none;
        transition: var(--transition);
    }
    .footer-links a:hover {
        text-decoration: underline;
    }
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        z-index: 1000;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .modal-content {
        background: var(--white);
        margin: 10% auto;
        max-width: 400px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .modal-header {
        padding: 1.5rem;
        background: var(--primary-color);
        color: var(--white);
        position: relative;
    }
    .modal-title {
        font-size: 1.5rem;
        font-weight: 600;
    }
    .close {
        position: absolute;
        right: 1.5rem;
        top: 1.5rem;
        font-size: 1.5rem;
        cursor: pointer;
        transition: var(--transition);
    }
    .close:hover {
        color: var(--light-gray);
    }
    /* Responsive adjustments */
    @media (max-width: 576px) {
        .login-container {
            margin: 1rem;
            border-radius: 12px;
        }
       
        .login-body {
            padding: 1.5rem;
        }
    }
    /* Floating animation */
    .floating {
        animation: floating 3s ease-in-out infinite;
    }
    @keyframes floating {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
        100% { transform: translateY(0px); }
    }
   
    .alert-danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
    }
    </style>
</head>
<body>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
   
    <div class="login-container">
        <div class="login-header">
            <h1>Bem-vindo</h1>
            <p>Sistema SunnyImob - Muitos Imóveis num só lugar!</p>
        </div>
       
        <div class="login-body">
            <div class="logo-container floating">
                <img src="img/logosunny.jpg" alt="Logo da Empresa" class="logo-animado">
            </div>
           
            <button class="btn-login" onclick="abrirModal()">
                <i class="fas fa-sign-in-alt"></i> Acessar Sistema
            </button>
           
            <div class="footer-links">
                <p>Problemas para acessar? <a href="#">Contate o administrador</a></p>
                <p>SunnyImob - v7.1a - Jan/2026</p>
            </div>
        </div>
    </div>
   
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Login</h2>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
           
            <form method="post" action="" class="login-body">
                <div class="form-group">
                    <label for="usuario">Usuário</label>
                    <div style="position: relative;">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Digite seu usuário" required>
                    </div>
                </div>
               
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div style="position: relative;">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="senha" name="senha" class="form-control" placeholder="Digite sua senha" required>
                    </div>
                </div>
               
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
        </div>
    </div>
   
    <script>
        function abrirModal() {
            document.getElementById('loginModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(function() {
                document.getElementById('usuario').focus();
            }, 100);
        }
       
        function fecharModal() {
            document.getElementById('loginModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
       
        window.onclick = function(event) {
            if (event.target == document.getElementById('loginModal')) {
                fecharModal();
            }
        }
       
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModal();
            }
        });
       
        <?php if (isset($error_message)): ?>
            setTimeout(function() {
                const alert = document.querySelector('.alert-danger');
                if (alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
<?php
session_start();
require_once 'conn.php';

// ====================================================================================
// BLOCO PHP: Verificar se usuário está logado
// ====================================================================================
if (!isset($_SESSION['UserId']) || !isset($_SESSION['Usuario'])) {
    $_SESSION['MsgErro'] = "Você precisa estar logado para acessar esta página!";
    header("Location: login.php");
    exit();
}

// ====================================================================================
// BLOCO PHP: Processamento do formulário de troca de senha
// ====================================================================================
$erros = [];
$sucesso = false;
$usuario_info = [];

// Buscar informações do usuário logado
try {
    $sql_usuario = "SELECT UserId, Usuario, Nome, Email, SenhaAtual FROM usuarios WHERE UserId = :id";
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->execute([':id' => intval($_SESSION['UserId'])]);
    $usuario_info = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_info) {
        $_SESSION['MsgErro'] = "Usuário não encontrado!";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['MsgErro'] = "Erro ao buscar usuário: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Processar alteração de senha
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $senha_atual = trim($_POST['senha_atual'] ?? '');
    $nova_senha = trim($_POST['nova_senha'] ?? '');
    $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
    
    // Validações
    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
        $erros[] = "Todos os campos são obrigatórios!";
    }
    
    // Verificar se senha atual está correta
    // ATENÇÃO: Se estiver usando password_hash(), use password_verify() aqui
    if ($senha_atual !== $usuario_info['SenhaAtual']) {
        $erros[] = "Senha atual incorreta!";
    }
    
    if ($nova_senha !== $confirmar_senha) {
        $erros[] = "As novas senhas não coincidem!";
    }
    
    // Senha mínima de 4 caracteres
    if (strlen($nova_senha) < 4) {
        $erros[] = "A nova senha deve ter no mínimo 4 caracteres!";
    }
    
    // Verificar se nova senha é diferente da atual
    if ($nova_senha === $senha_atual) {
        $erros[] = "A nova senha deve ser diferente da senha atual!";
    }
    
    // Se não houver erros, atualizar a senha
    if (empty($erros)) {
        try {
            // Guardar senha antiga para possível registro
            $senha_anterior = $usuario_info['SenhaAtual'];
            
            // ATENÇÃO: Considere usar password_hash() em produção
            // $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            
            $sql_update = "UPDATE usuarios SET SenhaAtual = :senha WHERE UserId = :id";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->execute([
                ':senha' => $nova_senha, // Use $senha_hash se usar password_hash()
                ':id' => intval($_SESSION['UserId'])
            ]);
            
            // ====================================================================
            // ENVIO DE EMAIL SIMPLIFICADO - APENAS SE NÃO FOR LOCALHOST
            // ====================================================================
            if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
                // Usando função mail() nativa (requer configuração SMTP no php.ini)
                $to = "sendmail@sunnyimob.com.br, valterpb@hotmail.com";
                $subject = "SUNNY-TROCA-SENHA-" . strtoupper($_SESSION['Usuario']) . 
                          " - " . $_SERVER['REMOTE_ADDR'] . 
                          " - " . date('Y-m-d') . 
                          " - " . date('H:i:s');
                
                // Mensagem simples
                $message = "TROCA DE SENHA - SISTEMA SUNNY\n";
                $message .= "===============================\n\n";
                $message .= "USUÁRIO: " . $usuario_info['Usuario'] . "\n";
                $message .= "NOME: " . $usuario_info['Nome'] . "\n";
                $message .= "EMAIL: " . $usuario_info['Email'] . "\n";
                $message .= "SENHA ANTERIOR: " . $senha_anterior . "\n";
                $message .= "NOVA SENHA: " . $nova_senha . "\n";
                $message .= "DATA/HORA: " . date('d/m/Y H:i:s') . "\n";
                $message .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
                $message .= "NAVEGADOR: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n\n";
                $message .= "Este é um email automático do Sistema Sunny.";
                
                $headers = "From: sendmail@sunnyimob.com.br\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                // Tentar enviar o email
                $email_enviado = mail($to, $subject, $message, $headers);
                
                if ($email_enviado) {
                    $mensagem_email = " Email de notificação enviado.";
                } else {
                    $mensagem_email = " Observação: Não foi possível enviar o email de notificação.";
                }
            } else {
                $mensagem_email = " (localhost - email não enviado)";
            }
            // ----------- fim envio de email
            
            $sucesso = true;
            $mensagem_sucesso = "Senha alterada com sucesso!" . $mensagem_email;
            
            $_SESSION['MsgSucesso'] = $mensagem_sucesso;
            
            // Limpar dados do formulário
            unset($_POST);
            
        } catch (PDOException $e) {
            $erros[] = "Erro ao atualizar senha: " . $e->getMessage();
        }
    }
    
    if (!empty($erros)) {
        $_SESSION['MsgErro'] = implode("<br>", $erros);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sunny • Trocar Senha</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9fd 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .card {
            border: none;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .card-header {
            background: #198754;
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        .user-info-card {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #198754;
        }
        .form-section {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e0e7ff;
        }
        .form-label {
            font-weight: 500;
            color: #444;
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
            font-size: 0.95em;
        }
        .input-group-text {
            background-color: #f1f3f5;
        }
        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25,135,84,.15);
        }
        .btn-lg-custom {
            padding: 0.75rem 1.5rem;
            font-size: 1.05rem;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
        }
        .weak { background-color: #dc3545; width: 25%; }
        .medium { background-color: #ffc107; width: 50%; }
        .strong { background-color: #198754; width: 100%; }
        .step-number {
            display: inline-block;
            width: 28px;
            height: 28px;
            background: #198754;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 28px;
            font-size: 0.9rem;
            margin-right: 10px;
        }
        .email-notice {
            background-color: #e8f4fd;
            border-left: 4px solid #0d6efd;
            padding: 10px;
            margin: 10px 0;
            border-radius: 0 4px 4px 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="container" style="max-width: 600px;">
    <div class="card shadow-lg">
        <div class="card-header text-center">
            <h4 class="mb-0">
                <i class="fa-solid fa-key me-2"></i>
                Alterar Minha Senha
            </h4>
        </div>

        <div class="card-body p-4 p-md-5">

            <?php if (isset($_SESSION['MsgErro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <?= $_SESSION['MsgErro'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['MsgErro']); endif; ?>

            <?php if (isset($_SESSION['MsgSucesso']) || $sucesso): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i>
                <?= $_SESSION['MsgSucesso'] ?? 'Senha alterada com sucesso!' ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['MsgSucesso']); endif; ?>

            <!-- Informações do usuário -->
            <div class="user-info-card">
                <h6 class="text-muted mb-3">Olá, <?= htmlspecialchars($usuario_info['Nome'] ?? '') ?>!</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <small class="text-muted">Usuário:</small>
                        <p class="mb-0 fw-bold"><?= htmlspecialchars($usuario_info['Usuario'] ?? '') ?></p>
                    </div>
                    <div class="col-md-6 mb-2">
                        <small class="text-muted">E-mail:</small>
                        <p class="mb-0"><?= htmlspecialchars($usuario_info['Email'] ?? '') ?></p>
                    </div>
                </div>
                <?php if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1'): ?>
                <div class="email-notice mt-2">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    <small>Localhost detectado - Email não será enviado</small>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$sucesso): ?>
            <form method="post" class="needs-validation" novalidate>

                <fieldset class="form-section">
                    <legend class="float-none w-auto px-3 text-primary fw-bold">Alteração de Senha</legend>

                    <!-- Passo 1: Senha Atual -->
                    <div class="mb-4">
                        <label class="form-label required-field">
                            <span class="step-number">1</span>Senha Atual
                        </label>
                        <div class="input-group password-toggle">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual" 
                                   placeholder="Digite sua senha atual" required>
                            <span class="input-group-text" id="togglePasswordCurrent" role="button">
                                <i class="fa-solid fa-eye"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Passo 2: Nova Senha -->
                    <div class="mb-4">
                        <label class="form-label required-field">
                            <span class="step-number">2</span>Nova Senha
                        </label>
                        <div class="input-group password-toggle">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                                   placeholder="Digite a nova senha (mín. 4 caracteres)" required minlength="4">
                            <span class="input-group-text" id="togglePasswordNew" role="button">
                                <i class="fa-solid fa-eye"></i>
                            </span>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        <small class="form-text text-muted mt-1 d-block">
                            A senha deve conter no mínimo <strong>4 caracteres</strong>.
                        </small>
                    </div>

                    <!-- Passo 3: Confirmar Nova Senha -->
                    <div class="mb-3">
                        <label class="form-label required-field">
                            <span class="step-number">3</span>Confirmar Nova Senha
                        </label>
                        <div class="input-group password-toggle">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                                   placeholder="Digite novamente a nova senha" required minlength="4">
                            <span class="input-group-text" id="togglePasswordConfirm" role="button">
                                <i class="fa-solid fa-eye"></i>
                            </span>
                        </div>
                        <div class="mt-2" id="passwordMatch"></div>
                    </div>

                    <?php if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1'): ?>
                    <div class="alert alert-info mt-3">
                        <i class="fa-solid fa-envelope me-2"></i>
                        <small>Um email será enviado para notificar a alteração da senha.</small>
                    </div>
                    <?php endif; ?>
                </fieldset>

                <div class="d-flex gap-3 justify-content-end mt-5">
                    <a href="index.php" class="btn btn-outline-secondary btn-lg px-4">
                        <i class="fa-solid fa-xmark me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-success btn-lg px-5 btn-lg-custom">
                        <i class="fa-solid fa-key me-2"></i>Alterar Senha
                    </button>
                </div>

            </form>
            <?php else: ?>
                <div class="text-center py-4">
                    <div class="alert alert-success mb-4">
                        <i class="fa-solid fa-shield-check fa-2x mb-3"></i>
                        <h5 class="mb-2">Senha Alterada com Sucesso!</h5>
                        <p class="mb-0">Sua senha foi atualizada. Use a nova senha no próximo login.</p>
                    </div>
                    <a href="index.php" class="btn btn-primary btn-lg px-5">
                        <i class="fa-solid fa-home me-2"></i>Voltar para o Início
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    // Toggle de visualização da senha para todos os campos
    const setupPasswordToggle = (toggleId, inputId) => {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);
        
        if (toggle && input) {
            toggle.addEventListener("click", () => {
                const type = input.type === "password" ? "text" : "password";
                input.type = type;
                const icon = toggle.querySelector("i");
                icon.classList.toggle("fa-eye");
                icon.classList.toggle("fa-eye-slash");
            });
        }
    };
    
    // Configurar toggles para todos os campos de senha
    setupPasswordToggle("togglePasswordCurrent", "senha_atual");
    setupPasswordToggle("togglePasswordNew", "nova_senha");
    setupPasswordToggle("togglePasswordConfirm", "confirmar_senha");
    
    // Verificar força da nova senha (mínimo de 4 caracteres)
    const passwordInput = document.getElementById("nova_senha");
    const strengthBar = document.getElementById("passwordStrength");
    const confirmInput = document.getElementById("confirmar_senha");
    const matchDiv = document.getElementById("passwordMatch");
    
    function checkPasswordStrength(password) {
        let strength = 0;
        
        // Critérios de força ajustados para mínimo de 4 caracteres
        if (password.length >= 4) strength += 1;
        if (password.length >= 6) strength += 1;
        if (password.length >= 8) strength += 1;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
        if (password.match(/\d/)) strength += 1;
        if (password.match(/[^a-zA-Z\d]/)) strength += 1;
        
        return strength;
    }
    
    function updateStrengthBar(strength, password) {
        if (!strengthBar) return;
        
        strengthBar.className = "password-strength";
        if (password.length === 0) {
            strengthBar.style.width = "0%";
        } else if (strength <= 2) {
            strengthBar.classList.add("weak");
        } else if (strength <= 4) {
            strengthBar.classList.add("medium");
        } else {
            strengthBar.classList.add("strong");
        }
    }
    
    function checkPasswordMatch() {
        const password = passwordInput?.value || "";
        const confirm = confirmInput?.value || "";
        
        if (!matchDiv) return;
        
        if (confirm.length === 0) {
            matchDiv.innerHTML = "";
        } else if (password === confirm) {
            matchDiv.innerHTML = '<span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>Senhas coincidem</span>';
        } else {
            matchDiv.innerHTML = '<span class="text-danger"><i class="fa-solid fa-times-circle me-1"></i>Senhas não coincidem</span>';
        }
    }
    
    // Event listeners para validação em tempo real
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener("input", function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updateStrengthBar(strength, password);
            checkPasswordMatch();
        });
    }
    
    if (confirmInput) {
        confirmInput.addEventListener("input", checkPasswordMatch);
    }
    
    // Validação do formulário
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            const senhaAtual = document.getElementById('senha_atual')?.value || "";
            const novaSenha = document.getElementById('nova_senha')?.value || "";
            const confirmarSenha = document.getElementById('confirmar_senha')?.value || "";
            
            // Limpar mensagens anteriores
            const errorAlerts = document.querySelectorAll('.alert-danger');
            errorAlerts.forEach(alert => alert.remove());
            
            let errors = [];
            
            if (!senhaAtual) {
                errors.push('A senha atual é obrigatória!');
            }
            
            // Validação para mínimo de 4 caracteres
            if (novaSenha.length < 4) {
                errors.push('A nova senha deve ter no mínimo 4 caracteres!');
            }
            
            if (novaSenha !== confirmarSenha) {
                errors.push('As novas senhas não coincidem!');
            }
            
            if (novaSenha === senhaAtual) {
                errors.push('A nova senha deve ser diferente da senha atual!');
            }
            
            if (errors.length > 0) {
                event.preventDefault();
                
                // Exibir erros
                const errorHtml = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        ${errors.join('<br>')}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                const cardBody = document.querySelector('.card-body');
                if (cardBody) {
                    cardBody.insertAdjacentHTML('afterbegin', errorHtml);
                }
                
                // Rolar para o topo para mostrar os erros
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }
});
</script>

</body>
</html>

<?php $conn = null; ?>
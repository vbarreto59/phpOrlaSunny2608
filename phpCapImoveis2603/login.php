<?php
// login.php
// Página de autenticação do sistema de captação de imóveis
// Última atualização: adaptado para redirecionar para dash.php

// Inicia sessão antes de qualquer saída
session_start();

// ================================================
// Inclusões
// ================================================
require_once __DIR__ . '/conn_cap.php';
require_once __DIR__ . '/includes/functions.php';

// Se já está logado → vai direto para o painel
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    header('Location: dash.php', true, 302);
    exit;
}

// Variáveis de controle
$erro = '';
$debug = '';  // só aparece se houver falha (para ajudar a diagnosticar)


// ================================================
// Processa formulário de login
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $erro = 'Informe usuário e senha';
    } else {
        try {
            $stmt = $conn->prepare("
                SELECT id, username, password, role
                FROM usuarios
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Login OK
                $_SESSION['user_id']  = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'] ?? 'user';

                // Registra acesso (log + e-mail)
                if (function_exists('log_acesso')) {
                    log_acesso($user['id']);
                }

                // Garante que não há saída pendente
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                header('Location: dash.php', true, 302);
                exit;
            }

            // Falha na autenticação
            $erro = 'Usuário ou senha inválidos';

            // Depuração simples (remova depois de testar)
            $debug = "Tentativa de login falhou.\n";
            $debug .= "• Usuário informado: " . htmlspecialchars($username) . "\n";
            $debug .= "• Senha informada (tamanho): " . strlen($password) . " caracteres\n";

            if ($user) {
                $debug .= "• Usuário encontrado no banco\n";
                $debug .= "• Hash armazenado (início): " . htmlspecialchars(substr($user['password'], 0, 30)) . "...\n";
                $debug .= "• password_verify(): FALSO\n";
            } else {
                $debug .= "• Nenhum registro encontrado para este usuário\n";
            }

        } catch (PDOException $e) {
            $erro = 'Erro ao consultar o banco de dados';
            $debug = "Erro PDO: " . htmlspecialchars($e->getMessage());
            error_log("Erro login: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login • Sistema de Captação de Imóveis</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <style>
    body {
      background: linear-gradient(135deg, #0d6efd 0%, #004085 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: system-ui, -apple-system, sans-serif;
      color: white;
    }
    .card-login {
      background: rgba(255,255,255,0.97);
      border-radius: 16px;
      box-shadow: 0 15px 40px rgba(0,0,0,0.35);
      max-width: 420px;
      width: 100%;
      color: #212529;
      padding: 2.5rem 2rem;
    }
    .btn-primary {
      background-color: #0d6efd;
      border-color: #0d6efd;
      padding: 0.75rem;
      font-size: 1.1rem;
    }
    .btn-primary:hover {
      background-color: #0b5ed7;
      border-color: #0a58ca;
    }
    .form-control-lg {
      font-size: 1.1rem;
      padding: 0.75rem 1.25rem;
    }
    .debug-box {
      background: #fff3cd;
      border: 1px solid #ffeeba;
      border-radius: 8px;
      padding: 1rem;
      margin-top: 1.5rem;
      font-size: 0.95rem;
      color: #856404;
      white-space: pre-wrap;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="card-login mx-auto text-center">

    <h3 class="fw-bold text-primary mb-3">Captação de Imóveis</h3>
    <h5 class="text-muted mb-4">Acesso ao sistema</h5>

    <?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($erro) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <div class="mb-3 text-start">
        <label for="username" class="form-label">Usuário</label>
        <input type="text" class="form-control form-control-lg" id="username" name="username"
               value="barreto" required autofocus placeholder="Digite o usuário">
      </div>

      <div class="mb-4 text-start">
        <label for="password" class="form-label">Senha</label>
        <input type="password" class="form-control form-control-lg" id="password" name="password"
               value="caio" required placeholder="Digite a senha">
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-100">
        Entrar
      </button>
    </form>

    <div class="mt-4 small text-muted">
      <p>Credenciais padrão: <strong>barreto</strong> / <strong>caio</strong></p>
    </div>

    <?php if ($debug): ?>
    <div class="debug-box text-start">
      <?= nl2br(htmlspecialchars($debug)) ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
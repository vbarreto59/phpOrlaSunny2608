<?php

session_start();
require_once 'conn.php';

// ────────────────────────────────────────────────
//  BLOQUEIO DE ACESSO NÃO AUTORIZADO
// ────────────────────────────────────────────────
if (!isset($_SESSION['Usuario'])) {
    $_SESSION['MsgErro'] = "Acesso não autorizado. Faça login primeiro.";
    header("Location: login_p1.php");
    exit();
}

// Apenas SUPERADMIN (1) e ADMIN (2) podem excluir
if (!isset($_SESSION['Permissao']) || $_SESSION['Permissao'] > 2) {
    $_SESSION['MsgErro'] = "Você não possui permissão para excluir usuários.";
    header("Location: rh_list_admin.php");
    exit();
}

// ────────────────────────────────────────────────
//  VARIÁVEIS DE CONTROLE
// ────────────────────────────────────────────────
$usuario_logado         = $_SESSION['Usuario'] ?? '(desconhecido)';
$permissao_logado       = $_SESSION['Permissao'] ?? 0;
$erro                   = '';
$usuario_id             = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0;
$usuario_info           = null;
$usuario_ja_foi_excluido = false;

// ────────────────────────────────────────────────
//  1. CONSULTA – VERIFICA SE O USUÁRIO AINDA EXISTE
// ────────────────────────────────────────────────
if ($usuario_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE UserId = ? LIMIT 1");
        $stmt->execute([$usuario_id]);
        $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_info) {
            $usuario_ja_foi_excluido = true;
            $erro = "Este usuário já foi excluído ou não existe mais no sistema.";
        }
    } catch (PDOException $e) {
        $erro = "Erro ao consultar informações do usuário: " . htmlspecialchars($e->getMessage());
    }
} else {
    $erro = "ID de usuário inválido ou não informado.";
}

// ────────────────────────────────────────────────
//  2. PROCESSAMENTO DA EXCLUSÃO (só se ainda existir)
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    if ($usuario_ja_foi_excluido) {
        $erro = "Não é possível excluir: este usuário já foi removido anteriormente.";
    } elseif (!isset($_POST['confirmacao']) || $_POST['confirmacao'] !== 'on') {
        $erro = "Você precisa marcar a caixa de confirmação para prosseguir.";
    } else {
        try {
            $conn->beginTransaction();

            // Remove vínculos com grupos (se a tabela existir)
            $stmt = $conn->prepare("DELETE FROM usuario_grupo WHERE UserId = ?");
            $stmt->execute([$usuario_id]);

            // Exclui o usuário principal
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE UserId = ?");
            $stmt->execute([$usuario_id]);

            $conn->commit();

            $_SESSION['MsgSucesso'] = "Usuário excluído com sucesso!";
            header("Location: rh_list_admin.php");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $erro = "Erro durante a exclusão: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ────────────────────────────────────────────────
//  FUNÇÕES AUXILIARES
// ────────────────────────────────────────────────
function getPermissaoTexto($cod) {
    $map = [
        1 => "SUPERADMIN",
        2 => "ADMIN",
        3 => "GESTOR-EDITOR",
        4 => "EDITOR",
        5 => "CORRETOR",
        6 => "CORRETOR-EDITOR",
        7 => "VISUALIZADOR"
    ];
    return $map[$cod] ?? "DESCONHECIDO ($cod)";
}

function getPermissaoClasse($cod) {
    $map = [
        1 => "superadmin",
        2 => "admin",
        3 => "gestor-editor",
        4 => "editor",
        5 => "corretor",
        6 => "corretor-editor",
        7 => "visualizador"
    ];
    return $map[$cod] ?? "";
}

$permissao_texto  = getPermissaoTexto($permissao_logado);
$permissao_classe = getPermissaoClasse($permissao_logado);
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Excluir Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9fd 100%);
            min-height: 100vh;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            max-width: 800px;
            margin: 2.5rem auto;
        }
        .card-header {
            background: linear-gradient(to right, #dc3545, #c82333);
            color: white;
            border-bottom: none;
            padding: 1.75rem;
        }
        .usuario-ja-excluido {
            background: #fff5f5;
            border: 2px solid #f8d7da;
            border-radius: 0.75rem;
            padding: 3rem 1.5rem;
            text-align: center;
            margin: 2rem 0;
        }
        .btn-danger {
            background: linear-gradient(to right, #dc3545, #c82333);
            border: none;
        }
        .form-check-input:checked {
            background-color: #dc3545;
            border-color: #dc3545;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="painel.php">Admin</a>
        <div class="ms-auto text-white">
            <small>Logado como:</small>
            <strong><?= htmlspecialchars($usuario_logado) ?></strong>
            <span class="badge <?= $permissao_classe ?> ms-2"><?= $permissao_texto ?></span>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card">
        <div class="card-header text-center">
            <h4 class="mb-1"><i class="fa-solid fa-user-slash me-2"></i>Excluir Usuário</h4>
            <small>Ação permanente e irreversível</small>
        </div>

        <div class="card-body p-4 p-md-5">

            <?php if ($erro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <?= $erro ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if ($usuario_ja_foi_excluido): ?>

                <div class="usuario-ja-excluido">
                    <i class="fa-solid fa-user-xmark fa-5x text-danger mb-4"></i>
                    <h3 class="text-danger mb-3">Usuário já excluído</h3>
                    <p class="lead mb-4">
                        Este registro não existe mais no banco de dados.
                    </p>
                    <p class="text-muted mb-4">
                        Provavelmente já foi removido anteriormente por você ou por outro administrador.
                    </p>
                    <a href="rh_list_admin.php" class="btn btn-primary btn-lg px-5">
                        <i class="fa-solid fa-arrow-left me-2"></i>Voltar para a lista
                    </a>
                </div>

            <?php elseif ($usuario_info): ?>

                <div class="alert alert-warning mb-4 text-center">
                    <strong>Atenção:</strong> esta ação <strong>não pode ser desfeita</strong>.
                </div>

                <div class="border rounded p-4 mb-4 bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <div class="bg-danger text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:90px; height:90px; font-size:2.5rem;">
                                <?= strtoupper(substr($usuario_info['Nome'] ?? '?', 0, 1)) ?>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <h5 class="mb-2"><?= htmlspecialchars($usuario_info['Nome'] ?? '—') ?></h5>
                            <p class="mb-1"><strong>Usuário:</strong> <?= htmlspecialchars($usuario_info['Usuario'] ?? '—') ?></p>
                            <p class="mb-1"><strong>E-mail:</strong> <?= htmlspecialchars($usuario_info['Email'] ?? '—') ?></p>
                            <p class="mb-1">
                                <strong>Permissão:</strong>
                                <span class="badge <?= getPermissaoClasse($usuario_info['Permissao'] ?? 0) ?> ms-2">
                                    <?= getPermissaoTexto($usuario_info['Permissao'] ?? 0) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <form method="post" id="formExcluir">
                    <div class="border border-danger rounded p-4 mb-4 bg-white">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirmacao" id="confirmaExclusao" required>
                            <label class="form-check-label fw-bold text-danger" for="confirmaExclusao">
                                CONFIRMO QUE DESEJO EXCLUIR ESTE USUÁRIO PERMANENTEMENTE
                            </label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-5">
                        <a href="rh_list_admin.php" class="btn btn-outline-secondary btn-lg px-5">
                            <i class="fa-solid fa-arrow-left me-2"></i>Cancelar
                        </a>
                        <button type="submit" name="confirmar" class="btn btn-danger btn-lg px-5" id="btnExcluir" disabled>
                            <i class="fa-solid fa-trash-can me-2"></i>Excluir Usuário
                        </button>
                    </div>
                </form>

            <?php else: ?>

                <div class="text-center py-5">
                    <i class="fa-solid fa-circle-question fa-4x text-muted mb-3"></i>
                    <h5>Nenhum usuário selecionado ou ID inválido</h5>
                    <a href="rh_list_admin.php" class="btn btn-primary mt-3 px-5">Voltar à lista</a>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const check = document.getElementById('confirmaExclusao');
    const btn = document.getElementById('btnExcluir');

    if (check && btn) {
        check.addEventListener('change', () => {
            btn.disabled = !check.checked;
        });
    }
</script>
</body>
</html>

<?php
$conn = null;
?>
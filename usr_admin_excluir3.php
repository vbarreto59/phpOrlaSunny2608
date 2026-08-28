<?php
session_start();
require_once 'conn.php';

// ────────────────────────────────────────────────
//  1. BLOQUEIO DE ACESSO E PERMISSÕES
// ────────────────────────────────────────────────
if (!isset($_SESSION['Usuario'])) {
    $_SESSION['MsgErro'] = "Acesso não autorizado. Faça login primeiro.";
    header("Location: login_p1.php");
    exit();
}

/*if (!isset($_SESSION['Permissao']) || $_SESSION['Permissao'] > 2) {
    $_SESSION['MsgErro'] = "Você não possui permissão para excluir usuários.";
    header("Location: usr_admin_listar3b.php");
    exit();
}*/


// ────────────────────────────────────────────────
//  2. VARIÁVEIS DE CONTROLE E CAPTURA DO ID
// ────────────────────────────────────────────────
$usuario_logado          = $_SESSION['Usuario'] ?? '(desconhecido)';
$permissao_logado        = $_SESSION['Permissao'] ?? 0;
$erro                    = '';
$usuario_info            = null;
$usuario_ja_foi_excluido = false;

// Recebe o ID da URL e limpa o valor
$usuario_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


// ────────────────────────────────────────────────
//  3. CONSULTA – VERIFICA SE O USUÁRIO EXISTE
// ────────────────────────────────────────────────
if ($usuario_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE UserId = ? LIMIT 1");
        $stmt->execute([$usuario_id]);
        $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_info) {
            $usuario_ja_foi_excluido = true;
            $erro = "Atenção: O usuário com ID #$usuario_id não foi encontrado ou já foi removido.";
        }
    } catch (PDOException $e) {
        $erro = "Erro de banco de dados: " . htmlspecialchars($e->getMessage());
    }
} else {
    $erro = "ID de usuário inválido ou não informado na URL.";
}

// ────────────────────────────────────────────────
//  4. PROCESSAMENTO DA EXCLUSÃO (VIA POST)
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    if ($usuario_ja_foi_excluido) {
        $erro = "Ação abortada: o registro já não existe no banco de dados.";
    } elseif (!isset($_POST['confirmacao']) || $_POST['confirmacao'] !== 'on') {
        $erro = "Você precisa marcar a caixa de confirmação para prosseguir.";
    } else {
        try {
            $conn->beginTransaction();

            // 1º: Remove vínculos (se houver a tabela de grupos)
            $stmtViculo = $conn->prepare("DELETE FROM usuario_grupo WHERE UserId = ?");
            $stmtViculo->execute([$usuario_id]);

            // 2º: Exclui o usuário principal
            $stmtDel = $conn->prepare("DELETE FROM usuarios WHERE UserId = ?");
            $stmtDel->execute([$usuario_id]);

            $conn->commit();

            $_SESSION['MsgSucesso'] = "O usuário foi removido permanentemente.";
            header("Location: usr_admin_listar3b.php");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $erro = "Falha crítica ao excluir: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ────────────────────────────────────────────────
//  5. FUNÇÕES AUXILIARES DE INTERFACE
// ────────────────────────────────────────────────
function getPermissaoTexto($cod) {
    $map = [
        1 => "SUPERADMIN", 2 => "ADMIN", 3 => "GESTOR-EDITOR",
        4 => "EDITOR", 5 => "CORRETOR", 6 => "CORRETOR-EDITOR", 7 => "VISUALIZADOR"
    ];
    return $map[$cod] ?? "DESCONHECIDO ($cod)";
}

function getPermissaoClasse($cod) {
    $map = [
        1 => "bg-danger", 2 => "bg-warning text-dark", 3 => "bg-info",
        4 => "bg-primary", 5 => "bg-secondary", 6 => "bg-dark", 7 => "bg-light text-dark"
    ];
    return $map[$cod] ?? "bg-secondary";
}

$permissao_texto  = getPermissaoTexto($permissao_logado);
$permissao_classe = getPermissaoClasse($permissao_logado);
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Excluir Usuário | Administração</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f4f7f6; min-height: 100vh; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-top: 3rem; }
        .card-header { background: #dc3545; color: white; border-radius: 1rem 1rem 0 0 !important; padding: 1.5rem; }
        .user-avatar { width: 80px; height: 80px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="painel.php">Sistema Admin</a>
        <div class="ms-auto text-white">
            <small>Olá, <strong><?= htmlspecialchars($usuario_logado) ?></strong></small>
            <span class="badge <?= $permissao_classe ?> ms-2"><?= $permissao_texto ?></span>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header text-center">
                    <h4 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Confirmação de Exclusão</h4>
                </div>
                <div class="card-body p-4">

                    <?php if ($erro): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                            <i class="fa-solid fa-circle-xmark me-2"></i> <?= $erro ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($usuario_ja_foi_excluido || !$usuario_info): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-user-slash fa-4x text-muted mb-3"></i>
                            <h3 class="text-muted">Usuário Inexistente</h3>
                            <p>O registro que você está tentando acessar não está disponível.</p>
                            <a href="usr_admin_listar3b.php" class="btn btn-primary px-4 mt-3">
                                <i class="fa-solid fa-list me-2"></i>Voltar para a Lista
                            </a>
                        </div>

                    <?php else: ?>
                        <div class="bg-light p-4 rounded mb-4 border">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-4 shadow-sm">
                                    <?= strtoupper(substr($usuario_info['Nome'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h5 class="mb-1 text-dark"><?= htmlspecialchars($usuario_info['Nome']) ?></h5>
                                    <p class="mb-0 text-muted"><strong>Email:</strong> <?= htmlspecialchars($usuario_info['Email']) ?></p>
                                    <p class="mb-0 text-muted"><strong>Usuário:</strong> <?= htmlspecialchars($usuario_info['Usuario']) ?></p>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <div class="alert alert-warning border-warning">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="confirmacao" id="checkOk" required>
                                    <label class="form-check-label fw-bold" for="checkOk">
                                        Compreendo que esta ação é permanente e os dados não poderão ser recuperados.
                                    </label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="usr_admin_listar3b.php" class="btn btn-outline-secondary px-4">
                                    <i class="fa-solid fa-xmark me-2"></i>Cancelar
                                </a>
                                <button type="submit" name="confirmar" id="btnSubmit" class="btn btn-danger px-4" disabled>
                                    <i class="fa-solid fa-trash-can me-2"></i>Excluir Agora
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Ativa/Desativa o botão conforme o checkbox
    const check = document.getElementById('checkOk');
    const btn = document.getElementById('btnSubmit');
    if(check && btn) {
        check.addEventListener('change', function() {
            btn.disabled = !this.checked;
        });
    }
</script>

</body>
</html>
<?php $conn = null; ?>
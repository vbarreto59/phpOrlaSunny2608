<?php
session_start();
require_once 'conn.php';

// Proteção CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Função auxiliar de permissões
function getPermissaoInfo($codigo) {
    $map = [
        1 => ['nome' => 'SUPERADMIN',    'acoes' => '/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/', 'cor' => 'danger'],
        2 => ['nome' => 'ADMIN',         'acoes' => '/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/',                 'cor' => 'warning'],
        3 => ['nome' => 'GESTOR-EDITOR', 'acoes' => '/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/',                 'cor' => 'info'],
        4 => ['nome' => 'EDITOR',        'acoes' => '/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/',                        'cor' => 'primary'],
        5 => ['nome' => 'CORRETOR',      'acoes' => '/CONSULTAR/',                                              'cor' => 'secondary'],
        6 => ['nome' => 'CORRETOR-EDITOR','acoes' => '/CONSULTAR/EDITAR/',                                     'cor' => 'purple'],
        7 => ['nome' => 'VISUALIZADOR',  'acoes' => '/CONSULTAR/',                                              'cor' => 'success'],
    ];
    return $map[$codigo] ?? ['nome' => '—', 'acoes' => '', 'cor' => 'light'];
}

// 1. Obtém e valida o ID do usuário
$vUserId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0;

if ($vUserId <= 0) {
    $_SESSION['MsgErro'] = "ID de usuário inválido.";
    header("Location: usr_listar.php");
    exit();
}

// 2. Carrega os dados do usuário (colunas reais da tabela)
try {
    $stmt = $conn->prepare("
        SELECT 
            UserId,
            Usuario,
            SenhaAtual,
            Nome,
            CRECI,
            Email,
            Telefones,
            Acoes,
            Permissao,
            Funcao,
            Ativo,
            IdEmp,
            Empresa,
            GrupoId,
            Grupo,
            DataHoraCadastro,
            UsuarioCadastro,
            Opr,
            Licenca
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

// 3. Dados para o formulário (prioriza POST em caso de erro)
$form_data = $_SESSION['form_data'] ?? $userData;
unset($_SESSION['form_data']);

// 4. Processamento do formulário
$erros = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erros[] = "Falha na verificação de segurança (CSRF).";
    } else {
        $vUsuario    = trim($_POST['Usuario']    ?? '');
        $vSenha      = trim($_POST['Senha']      ?? '');
        $vNome       = trim($_POST['Nome']       ?? '');
        $vCRECI      = trim($_POST['CRECI']      ?? '');
        $vEmail      = trim($_POST['Email']      ?? '');
        $vTelefones  = trim($_POST['Telefones']  ?? '');
        $vIdEmp      = (int)($_POST['IdEmp']     ?? 0);
        $vID_Grupo   = (int)($_POST['ID_Grupo']  ?? 0);
        $vPermissao  = (int)($_POST['Permissao'] ?? 0);
        $vAtivo      = isset($_POST['Ativo']) ? 1 : 0;
        $vFuncao     = trim($_POST['Funcao']     ?? '');
        $vAcoes      = trim($_POST['Acoes']      ?? '');

        // Validações
        if (empty($vUsuario) || empty($vNome) || $vPermissao < 1 || $vPermissao > 7 ||
            $vIdEmp <= 0 || $vID_Grupo <= 0) {
            $erros[] = "Campos obrigatórios não preenchidos corretamente.";
        }

        $senhaSalvar = !empty($vSenha) ? $vSenha : $userData['SenhaAtual'];

        // Duplicidade de usuário
        if (empty($erros) && $vUsuario !== $userData['Usuario']) {
            $chk = $conn->prepare("SELECT 1 FROM usuarios WHERE Usuario = ? AND UserId != ?");
            $chk->execute([$vUsuario, $vUserId]);
            if ($chk->fetch()) $erros[] = "Este nome de usuário já está em uso.";
        }

        // Empresa
        $empresa_nome = $userData['Empresa'];
        if (empty($erros)) {
            $stmt = $conn->prepare("SELECT EmpresaSunny FROM sunnyempresas WHERE IdEmp = ?");
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
            $stmt = $conn->prepare("SELECT Nome_Grupo FROM grupo WHERE ID_Grupo = ?");
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

        if (empty($erros)) {
            try {
                $conn->beginTransaction();

                $sql = "
                    UPDATE usuarios SET
                        Usuario = ?,
                        SenhaAtual = ?,
                        Nome = ?,
                        CRECI = ?,
                        Email = ?,
                        Telefones = ?,
                        Acoes = ?,
                        Permissao = ?,
                        Funcao = ?,
                        Ativo = ?,
                        IdEmp = ?,
                        Empresa = ?,
                        GrupoId = ?,
                        Grupo = ?,
                        Opr = ?
                    WHERE UserId = ?
                ";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $vUsuario,
                    $senhaSalvar,
                    $vNome,
                    $vCRECI,
                    $vEmail,
                    $vTelefones,
                    $vAcoes,
                    $vPermissao,
                    $vFuncao,
                    $vAtivo,
                    $vIdEmp,
                    $empresa_nome,
                    $vID_Grupo,
                    $grupo_nome,
                    $_SESSION['Usuario'] ?? 'SISTEMA',
                    $vUserId
                ]);

                // Relacionamento (se a tabela existir)
                $conn->prepare("DELETE FROM usuario_grupo WHERE UserId = ?")->execute([$vUserId]);
                $conn->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)")->execute([$vUserId, $vID_Grupo]);

                $conn->commit();

                $_SESSION['MsgSucesso'] = "Usuário atualizado com sucesso!";
                header("Location: usr_listar.php");
                exit;

            } catch (Exception $e) {
                $conn->rollBack();
                $erros[] = "Erro ao atualizar: " . $e->getMessage();
            }
        }

        if (!empty($erros)) {
            $_SESSION['MsgErro'] = implode("<br>", $erros);
            $_SESSION['form_data'] = $_POST;
            header("Location: usr_editar.php?id={$vUserId}");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sunny • Editar Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e9fd 100%); min-height:100vh; padding:2.5rem 1rem; }
        .card { border:none; border-radius:1rem; box-shadow:0 10px 30px rgba(0,0,0,0.12); }
        .card-header { background:#198754; color:white; padding:1.5rem; border-bottom:none; }
        .form-section { background:#fff; border-radius:0.75rem; padding:1.75rem; margin-bottom:1.5rem; border:1px solid #e0e7ff; }
        .form-label { font-weight:500; color:#444; }
        .required-field::after { content:" *"; color:#dc3545; font-size:0.95em; }
        .badge-permissao { font-size:0.85rem; padding:0.45em 0.9em; }
        .purple { background:#6f42c1; color:white; }
        @media (min-width:992px) { .two-columns { column-count:2; column-gap:2.5rem; } }
    </style>
</head>
<body>

<div class="container" style="max-width:920px;">
    <div class="card shadow-lg">
        <div class="card-header text-center">
            <h4 class="mb-0"><i class="fa-solid fa-user-pen me-2"></i>Editar Usuário</h4>
        </div>

        <div class="card-body p-4 p-lg-5">

            <?php if (isset($_SESSION['MsgErro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <?= $_SESSION['MsgErro'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['MsgErro']); endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Dados Pessoais -->
                <fieldset class="form-section">
                    <legend class="float-none w-auto px-3 text-success fw-bold">Dados Pessoais</legend>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="Nome" class="form-label required-field">Nome completo</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" class="form-control" id="Nome" name="Nome" required value="<?= htmlspecialchars($form_data['Nome'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="CRECI" class="form-label">CRECI</label>
                            <input type="text" class="form-control" id="CRECI" name="CRECI" value="<?= htmlspecialchars($form_data['CRECI'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label for="Email" class="form-label">E-mail</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control" id="Email" name="Email" value="<?= htmlspecialchars($form_data['Email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="Telefones" class="form-label">Telefones</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                <input type="text" class="form-control" id="Telefones" name="Telefones" value="<?= htmlspecialchars($form_data['Telefones'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- Acesso e Permissões -->
                <fieldset class="form-section">
                    <legend class="float-none w-auto px-3 text-success fw-bold">Acesso & Permissões</legend>
                    <div class="two-columns">
                        <div>
                            <label for="Usuario" class="form-label required-field">Usuário</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                <input type="text" class="form-control" id="Usuario" name="Usuario" required value="<?= htmlspecialchars($form_data['Usuario'] ?? '') ?>">
                            </div>

                            <label for="Senha" class="form-label">Nova senha (opcional)</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control" id="Senha" name="Senha">
                                <button class="btn btn-outline-secondary" type="button" id="toggleSenha">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Deixe em branco para manter a senha atual.</small>

                            <label for="IdEmp" class="form-label required-field">Empresa</label>
                            <select class="form-select mb-3" id="IdEmp" name="IdEmp" required>
                                <option value="">Selecione...</option>
                                <?php
                                $stmt = $conn->query("SELECT IdEmp, EmpresaSunny FROM sunnyempresas ORDER BY EmpresaSunny");
                                while ($row = $stmt->fetch()) {
                                    $selected = ($form_data['IdEmp'] ?? 0) == $row['IdEmp'] ? 'selected' : '';
                                    echo "<option value='{$row['IdEmp']}' $selected>" . htmlspecialchars($row['EmpresaSunny']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label for="ID_Grupo" class="form-label required-field">Grupo</label>
                            <select class="form-select mb-3" id="ID_Grupo" name="ID_Grupo" required>
                                <option value="">Selecione...</option>
                                <?php
                                $stmt = $conn->query("SELECT ID_Grupo, Nome_Grupo FROM grupo ORDER BY Nome_Grupo");
                                while ($row = $stmt->fetch()) {
                                    $selected = ($form_data['GrupoId'] ?? 0) == $row['ID_Grupo'] ? 'selected' : '';
                                    echo "<option value='{$row['ID_Grupo']}' $selected>" . htmlspecialchars($row['Nome_Grupo']) . "</option>";
                                }
                                ?>
                            </select>

                            <label for="Permissao" class="form-label required-field">Nível de Permissão</label>
                            <select class="form-select mb-3" id="Permissao" name="Permissao" required onchange="atualizarFuncao()">
                                <option value="" disabled <?= empty($form_data['Permissao']) ? 'selected' : '' ?>>Selecione...</option>
                                <?php
                                $permissoes = [
                                    1 => "SUPERADMIN",
                                    2 => "ADMIN",
                                    3 => "GESTOR-EDITOR",
                                    4 => "EDITOR",
                                    5 => "CORRETOR",
                                    6 => "CORRETOR-EDITOR",
                                    7 => "VISUALIZADOR"
                                ];
                                foreach ($permissoes as $valor => $texto) {
                                    $selected = ($form_data['Permissao'] ?? 0) == $valor ? 'selected' : '';
                                    echo "<option value=\"$valor\" $selected>$texto</option>";
                                }
                                ?>
                            </select>

                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="Ativo" name="Ativo" value="1"
                                       <?= ($form_data['Ativo'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="Ativo">Usuário Ativo</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label">Função</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="Funcao" name="Funcao" readonly value="<?= htmlspecialchars($form_data['Funcao'] ?? '') ?>">
                            <span id="badgePermissao" class="input-group-text badge-permissao bg-<?= getPermissaoInfo($form_data['Permissao'] ?? 0)['cor'] ?>">
                                <i class="fa-solid fa-user-shield"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Ações permitidas</label>
                        <textarea class="form-control font-monospace" id="Acoes" name="Acoes" rows="3" readonly><?= htmlspecialchars($form_data['Acoes'] ?? '') ?></textarea>
                    </div>
                </fieldset>

                <div class="d-flex gap-3 justify-content-end mt-5">
                    <a href="usr_listar.php" class="btn btn-outline-secondary btn-lg px-5">
                        <i class="fa-solid fa-arrow-left me-2"></i>Voltar
                    </a>
                    <button type="submit" class="btn btn-success btn-lg px-5">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const permissoes = {
    "1": { funcao: "SUPERADMIN", acoes: "/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/", cor: "danger" },
    "2": { funcao: "ADMIN", acoes: "/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/", cor: "warning" },
    "3": { funcao: "GESTOR-EDITOR", acoes: "/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/", cor: "info" },
    "4": { funcao: "EDITOR", acoes: "/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/", cor: "primary" },
    "5": { funcao: "CORRETOR", acoes: "/CONSULTAR/", cor: "secondary" },
    "6": { funcao: "CORRETOR-EDITOR", acoes: "/CONSULTAR/EDITAR/", cor: "purple" },
    "7": { funcao: "VISUALIZADOR", acoes: "/CONSULTAR/", cor: "success" }
};

function atualizarFuncao() {
    const perm = document.getElementById("Permissao")?.value || "";
    const info = permissoes[perm] || { funcao: "", acoes: "", cor: "light" };

    document.getElementById("Funcao").value = info.funcao;
    document.getElementById("Acoes").value = info.acoes;

    const badge = document.getElementById("badgePermissao");
    if (badge) badge.className = `input-group-text badge-permissao bg-${info.cor} text-white`;
}

document.addEventListener("DOMContentLoaded", () => {
    atualizarFuncao();

    document.getElementById("toggleSenha")?.addEventListener("click", function() {
        const input = document.getElementById("Senha");
        const icon = this.querySelector("i");
        input.type = input.type === "password" ? "text" : "password";
        icon.classList.toggle("fa-eye");
        icon.classList.toggle("fa-eye-slash");
    });
});
</script>
</body>
</html>

<?php $conn = null; ?>
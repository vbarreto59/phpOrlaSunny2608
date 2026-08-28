<?php
session_start();
require_once 'conn.php';

// Função auxiliar
function getPermissaoTexto($codigo) {
    $map = [
        1 => "SUPERADMIN", 2 => "ADMIN", 3 => "GESTOR-EDITOR",
        4 => "EDITOR", 5 => "CORRETOR", 6 => "CORRETOR-EDITOR",
        7 => "VISUALIZADOR"
    ];
    return $map[$codigo] ?? "DESCONHECIDO";
}

// ====================================================================================
// LIMPA DADOS DA SESSÃO NO CARREGAMENTO INICIAL (GET)
// Só mantém se veio de erro (POST com falha)
// ====================================================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['form_data']);
    unset($_SESSION['MsgErro']);
}

// ====================================================================================
// PROCESSAMENTO DO FORMULÁRIO (POST)
// ====================================================================================
$erros = [];
$form_data = $_SESSION['form_data'] ?? [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario   = trim($_POST['Usuario']   ?? '');
    $senha     = trim($_POST['Senha']     ?? '');
    $email     = trim($_POST['Email']     ?? '');
    $telefones = trim($_POST['Telefones'] ?? '');
    $permissao = $_POST['Permissao']      ?? '';
    $id_grupo  = trim($_POST['ID_Grupo']  ?? '');
    $id_emp    = trim($_POST['idEmp']     ?? '');
    $nome      = trim($_POST['Nome']      ?? '');
    $creci     = trim($_POST['CRECI']     ?? '');
    $ativo     = isset($_POST['Ativo']) ? 1 : 0;

    $funcao = trim($_POST['Funcao'] ?? '');
    $acoes  = trim($_POST['Acoes']  ?? '');

    // Fallback para função e ações (caso JS não preencha)
    if (empty($funcao) || empty($acoes)) {
        $map = [
            '1' => ['funcao' => 'SUPERADMIN',     'acoes' => '/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '2' => ['funcao' => 'ADMIN',           'acoes' => '/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '3' => ['funcao' => 'GESTOR-EDITOR',   'acoes' => '/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '4' => ['funcao' => 'EDITOR',          'acoes' => '/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '5' => ['funcao' => 'CORRETOR',        'acoes' => '/CONSULTAR/'],
            '6' => ['funcao' => 'CORRETOR-EDITOR', 'acoes' => '/CONSULTAR/EDITAR/'],
            '7' => ['funcao' => 'VISUALIZADOR',    'acoes' => '/CONSULTAR/'],
        ];
        if (isset($map[$permissao])) {
            $funcao = $map[$permissao]['funcao'];
            $acoes  = $map[$permissao]['acoes'];
        }
    }

    // Validações
    if (empty($usuario) || empty($senha) || empty($permissao) ||
        empty($id_grupo) || empty($id_emp) || empty($nome)) {
        $erros[] = "Todos os campos obrigatórios devem ser preenchidos.";
    }

    if (!is_numeric($permissao) || $permissao < 1 || $permissao > 7) {
        $erros[] = "Nível de permissão inválido.";
    }

    if (!is_numeric($id_grupo) || (int)$id_grupo <= 0) {
        $erros[] = "Selecione um grupo válido.";
    }

    if (!is_numeric($id_emp) || (int)$id_emp <= 0) {
        $erros[] = "Selecione uma empresa válida.";
    }

    // Verifica duplicidade
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT 1 FROM usuarios WHERE Usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) {
            $erros[] = "Este nome de usuário já está cadastrado.";
        }
    }

    // Nome da empresa
    $empresa_nome = "";
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT EmpresaSunny FROM sunnyempresas WHERE IdEmp = ?");
        $stmt->execute([(int)$id_emp]);
        if ($row = $stmt->fetch()) {
            $empresa_nome = $row['EmpresaSunny'];
        } else {
            $erros[] = "Empresa selecionada não encontrada.";
        }
    }

    // Nome do grupo
    $nome_grupo = "";
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT Nome_Grupo FROM grupo WHERE ID_Grupo = ?");
        $stmt->execute([(int)$id_grupo]);
        if ($row = $stmt->fetch()) {
            $nome_grupo = $row['Nome_Grupo'];
        } else {
            $erros[] = "Grupo selecionado não encontrado.";
        }
    }

    // Inserção
    if (empty($erros)) {
        try {
            $conn->beginTransaction();

            $sql = "INSERT INTO usuarios (
                Usuario, SenhaAtual, Nome, CRECI, Email, Telefones,
                Acoes, Permissao, Funcao, Ativo, idEmp, Empresa,
                GrupoId, Grupo, Data, UsuarioCadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $usuario,
                $senha,  // ← texto plano, conforme solicitado
                $nome,
                $creci,
                $email,
                $telefones,
                $acoes,
                (int)$permissao,
                $funcao,
                $ativo,
                (int)$id_emp,
                $empresa_nome,
                (int)$id_grupo,
                $nome_grupo,
                $_SESSION['Usuario'] ?? 'SISTEMA'
            ]);

            $new_user_id = $conn->lastInsertId();

            // Relacionamento
            $stmt = $conn->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)");
            $stmt->execute([$new_user_id, (int)$id_grupo]);

            $conn->commit();

            unset($_SESSION['form_data']); // limpa após sucesso
            $_SESSION['MsgSucesso'] = "Usuário criado com sucesso!";
            header("Location: usr_listar.php");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $erros[] = "Erro ao criar usuário: " . $e->getMessage();
        }
    }

    if (!empty($erros)) {
        $_SESSION['MsgErro'] = implode("<br>", $erros);
        $_SESSION['form_data'] = $_POST;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sunny • Novo Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e9fd 100%); min-height:100vh; padding:2rem 1rem; }
        .card { border:none; border-radius:1rem; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.12); }
        .card-header { background:#0d6efd; color:white; padding:1.5rem; border-bottom:none; }
        .form-section { background:#ffffff; border-radius:0.75rem; padding:1.5rem; margin-bottom:1.5rem; border:1px solid #e0e7ff; }
        .form-label { font-weight:500; color:#444; }
        .required-field::after { content:" *"; color:#dc3545; font-size:0.95em; }
        .input-group-text { background:#f1f3f5; }
        .form-control:focus { border-color:#0d6efd; box-shadow:0 0 0 0.25rem rgba(13,110,253,.15); }
        .badge-permissao { font-size:0.85rem; padding:0.4em 0.8em; }
        .superadmin { background:#dc3545; color:white; }
        .admin { background:#ffc107; color:#212529; }
        .gestor-editor { background:#0dcaf0; color:#212529; }
        .editor { background:#0d6efd; color:white; }
        .corretor { background:#6c757d; color:white; }
        .corretor-editor { background:#6610f2; color:white; }
        .visualizador { background:#198754; color:white; }
        @media (min-width:992px) { .two-columns { column-count:2; column-gap:2.5rem; } }
    </style>
</head>
<body>

<div class="container" style="max-width:900px;">
    <div class="card shadow-lg">
        <div class="card-header text-center">
            <h4 class="mb-0"><i class="fa-solid fa-user-plus me-2"></i>Novo Usuário</h4>
        </div>

        <div class="card-body p-4 p-md-5">

            <?php if (isset($_SESSION['MsgErro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <?= $_SESSION['MsgErro'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <!-- Dados Pessoais -->
                <fieldset class="form-section">
                    <legend class="float-none w-auto px-3 text-primary fw-bold">Dados Pessoais</legend>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="Nome" class="form-label required-field">Nome completo</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" class="form-control" id="Nome" name="Nome" required
                                       value="<?= htmlspecialchars($form_data['Nome'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="CRECI" class="form-label">CRECI</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                                <input type="text" class="form-control" id="CRECI" name="CRECI"
                                       value="<?= htmlspecialchars($form_data['CRECI'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="Email" class="form-label">E-mail</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control" id="Email" name="Email"
                                       value="<?= htmlspecialchars($form_data['Email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="Telefones" class="form-label">Telefones</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                <input type="text" class="form-control" id="Telefones" name="Telefones"
                                       value="<?= htmlspecialchars($form_data['Telefones'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- Acesso e Permissões -->
                <fieldset class="form-section">
                    <legend class="float-none w-auto px-3 text-primary fw-bold">Acesso & Permissões</legend>
                    <div class="two-columns">
                        <div>
                            <label for="Usuario" class="form-label required-field">Usuário</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" class="form-control" id="Usuario" name="Usuario" required
                                       value="<?= htmlspecialchars($form_data['Usuario'] ?? '') ?>">
                            </div>

                            <label for="Senha" class="form-label required-field">Senha inicial</label>
                            <div class="input-group mb-3 password-toggle">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control" id="Senha" name="Senha" required
                                       value="<?= htmlspecialchars($form_data['Senha'] ?? '') ?>">
                                <span class="input-group-text" id="togglePassword"><i class="fa-solid fa-eye"></i></span>
                            </div>

                            <label for="idEmp" class="form-label required-field">Empresa</label>
                            <select class="form-select mb-3" id="idEmp" name="idEmp" required>
                                <option value="" disabled <?= empty($form_data['idEmp']) ? 'selected' : '' ?>>Selecione...</option>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT IdEmp, EmpresaSunny FROM sunnyempresas ORDER BY EmpresaSunny");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $sel = ($form_data['idEmp'] ?? '') == $row['IdEmp'] ? 'selected' : '';
                                        echo "<option value='{$row['IdEmp']}' $sel>" . htmlspecialchars($row['EmpresaSunny']) . "</option>";
                                    }
                                } catch (Exception $e) {
                                    echo "<option value=''>Erro ao carregar empresas</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label for="ID_Grupo" class="form-label required-field">Grupo</label>
                            <select class="form-select mb-3" id="ID_Grupo" name="ID_Grupo" required onchange="atualizarFuncao()">
                                <option value="" disabled <?= empty($form_data['ID_Grupo']) ? 'selected' : '' ?>>Selecione...</option>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT ID_Grupo, Nome_Grupo FROM grupo ORDER BY Nome_Grupo");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $sel = ($form_data['ID_Grupo'] ?? '') == $row['ID_Grupo'] ? 'selected' : '';
                                        echo "<option value='{$row['ID_Grupo']}' $sel>" . htmlspecialchars($row['Nome_Grupo']) . "</option>";
                                    }
                                } catch (Exception $e) {
                                    echo "<option value=''>Erro ao carregar grupos</option>";
                                }
                                ?>
                            </select>

                            <label for="Permissao" class="form-label required-field">Nível de Permissão</label>
                            <select class="form-select mb-2" id="Permissao" name="Permissao" required onchange="atualizarFuncao()">
                                <option value="" disabled <?= empty($form_data['Permissao']) ? 'selected' : '' ?>>Selecione...</option>
                                <?php
                                $permissoes = [1=>'SUPERADMIN',2=>'ADMIN',3=>'GESTOR-EDITOR',4=>'EDITOR',5=>'CORRETOR',6=>'CORRETOR-EDITOR',7=>'VISUALIZADOR'];
                                $perm_atual = $form_data['Permissao'] ?? '';
                                foreach ($permissoes as $v => $txt) {
                                    $sel = ($perm_atual == $v) ? 'selected' : '';
                                    echo "<option value='$v' $sel>$txt</option>";
                                }
                                ?>
                            </select>

                            <div class="d-flex align-items-center gap-3 mt-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="Ativo" name="Ativo" value="1"
                                           <?= (!isset($form_data['Ativo']) || $form_data['Ativo'] == '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="Ativo">ATIVO</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="Funcao" class="form-label">Função</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="Funcao" name="Funcao" readonly
                                   value="<?= htmlspecialchars($form_data['Funcao'] ?? '') ?>">
                            <span class="input-group-text badge-permissao" id="badgeFuncao">
                                <i class="fa-solid fa-user-tag"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="Acoes" class="form-label">Ações Permitidas</label>
                        <textarea class="form-control" id="Acoes" name="Acoes" rows="3" readonly>
                            <?= htmlspecialchars($form_data['Acoes'] ?? '') ?>
                        </textarea>
                    </div>
                </fieldset>

                <div class="d-flex gap-3 justify-content-end mt-5">
                    <a href="usr_listar.php" class="btn btn-outline-secondary btn-lg px-4">
                        <i class="fa-solid fa-xmark me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Criar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Funções auxiliares
function removerAcentos(texto) {
    return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

function gerarNomeUsuario(nomeCompleto) {
    if (!nomeCompleto.trim()) return '';
    const partes = nomeCompleto.toLowerCase().trim().split(/\s+/);
    const primeiro = removerAcentos(partes[0]);
    let iniciais = '';
    for (let i = 1; i < partes.length; i++) {
        if (partes[i]) iniciais += partes[i][0];
    }
    return primeiro + iniciais;
}

function gerarSenha(nomeCompleto) {
    if (!nomeCompleto.trim()) return '';
    const partes = nomeCompleto.trim().split(/\s+/);
    const primeiro = removerAcentos(partes[0].toLowerCase());
    const num = Math.floor(Math.random() * 99) + 1;
    return primeiro + '@tv' + num.toString().padStart(2, '0');
}

function atualizarFuncao() {
    const perm = document.getElementById("Permissao")?.value || "";
    const funcaoEl = document.getElementById("Funcao");
    const acoesEl = document.getElementById("Acoes");
    const badgeEl = document.getElementById("badgeFuncao");
    let funcao = "", acoes = "", classe = "";

    switch(perm) {
        case "1": funcao="SUPERADMIN"; acoes="/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="superadmin"; break;
        case "2": funcao="ADMIN"; acoes="/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="admin"; break;
        case "3": funcao="GESTOR-EDITOR"; acoes="/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="gestor-editor"; break;
        case "4": funcao="EDITOR"; acoes="/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="editor"; break;
        case "5": funcao="CORRETOR"; acoes="/CONSULTAR/"; classe="corretor"; break;
        case "6": funcao="CORRETOR-EDITOR"; acoes="/CONSULTAR/EDITAR/"; classe="corretor-editor"; break;
        case "7": funcao="VISUALIZADOR"; acoes="/CONSULTAR/"; classe="visualizador"; break;
    }

    if (funcaoEl) funcaoEl.value = funcao;
    if (acoesEl) acoesEl.value = acoes;
    if (badgeEl) {
        badgeEl.className = `input-group-text badge-permissao ${classe}`;
        badgeEl.innerHTML = '<i class="fa-solid fa-user-tag"></i>';
    }
}

// Inicialização
document.addEventListener("DOMContentLoaded", () => {
    const nomeInput = document.getElementById("Nome");
    if (nomeInput) {
        nomeInput.addEventListener("input", function() {
            const valor = this.value.trim();
            document.getElementById("Usuario").value = valor ? gerarNomeUsuario(valor) : "";
            document.getElementById("Senha").value = valor ? gerarSenha(valor) : "";
            atualizarFuncao(); // atualiza também ao digitar nome (se permissão já estiver selecionada)
        });
    }

    const toggle = document.getElementById("togglePassword");
    const senhaInput = document.getElementById("Senha");
    if (toggle && senhaInput) {
        toggle.addEventListener("click", () => {
            const type = senhaInput.type === "password" ? "text" : "password";
            senhaInput.type = type;
            toggle.querySelector("i").classList.toggle("fa-eye");
            toggle.querySelector("i").classList.toggle("fa-eye-slash");
        });
    }

    const ativoCheck = document.getElementById("Ativo");
    if (ativoCheck) {
        ativoCheck.addEventListener("change", function() {
            const label = this.nextElementSibling;
            if (label) label.textContent = this.checked ? "ATIVO" : "INATIVO";
        });
        ativoCheck.dispatchEvent(new Event('change'));
    }

    // Garante que o form comece vazio (exceto se veio de erro)
    atualizarFuncao();
});
</script>
</body>
</html>

<?php $conn = null; ?>
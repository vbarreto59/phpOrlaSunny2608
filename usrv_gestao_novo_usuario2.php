<?php
// usrv_gestao_novo_usuario.php
session_start();
require_once 'conn.php';
require_once 'send_email.php';

// Função auxiliar
function safeValue($value, $default = '') {
    return ($value === null || $value === '') ? $default : trim($value);
}

// Carregar diretorias
$diretorias = $conn->query("SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria")
                   ->fetchAll(PDO::FETCH_ASSOC);

// Carregar gerências
$gerencias = $conn->query("SELECT GerenciaID, DiretoriaID, NomeGerencia FROM gerencias ORDER BY NomeGerencia")
                  ->fetchAll(PDO::FETCH_ASSOC);

// Valores fixos da empresa
$idEmp   = 2;
$empresa = 'Tocca Onze';

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = safeValue($_POST['Nome']);
    $email      = safeValue($_POST['Email']);
    $telefones  = safeValue($_POST['Telefones']);
    $creci      = safeValue($_POST['CRECI']);
    $usuario    = safeValue($_POST['Usuario']);
    $senha      = safeValue($_POST['Senha']);
    $diretoriaID = $_POST['DiretoriaID'] ?? '';
    $gerenciaID  = $_POST['GerenciaID']  ?? '';

    // Valores sempre fixos / padrão
    $ativo     = 0;     // INATIVO
    $licenca   = 0;     // LICENÇA INATIVA / NÃO LIBERADA
    $permissao = 5;
    $funcao    = 'CORRETOR';
    $acao      = '/CONSULTAR/';
    $grupoId   = 2;
    $grupo     = 'TOCCA_ONZE';

    $nomeDiretoria = $nomeGerencia = '';

    if ($diretoriaID) {
        $stmt = $conn->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
        $stmt->execute([(int)$diretoriaID]);
        $nomeDiretoria = $stmt->fetchColumn() ?: '';
    }

    if ($gerenciaID) {
        $stmt = $conn->prepare("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = ?");
        $stmt->execute([(int)$gerenciaID]);
        $nomeGerencia = $stmt->fetchColumn() ?: '';
    }

    $parDiretoriaID = $diretoriaID ? (int)$diretoriaID : null;
    $parGerenciaID  = $gerenciaID  ? (int)$gerenciaID  : null;

    try {
        $conn->beginTransaction();

        $sql = "INSERT INTO usuarios (
            Usuario, SenhaAtual, Nome, Creci, Email, Telefones, Acoes,
            Permissao, Funcao, Ativo, UsuarioCadastro,
            GrupoId, Grupo, DiretoriaID, Diretoria,
            GerenciaID, Gerencia, IdEmp, Empresa,
            Licenca
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $usuario,
            $senha,
            $nome,
            $creci,
            $email,
            $telefones,
            $acao,
            $permissao,
            $funcao,
            $ativo,
            $_SESSION['Usuario'] ?? 'SISTEMA',
            $grupoId,
            $grupo,
            $parDiretoriaID,
            $nomeDiretoria,
            $parGerenciaID,
            $nomeGerencia,
            $idEmp,
            $empresa,
            $licenca
        ]);

        $newUserId = $conn->lastInsertId();

        // Relação grupo (usando o grupoId fixo)
        $conn->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)")
             ->execute([$newUserId, $grupoId]);

        $conn->commit();

        // Envio de e-mails (fora do localhost)
        if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            $assunto = "NOVO CORRETOR CADASTRADO (INATIVO) - " . ($_SESSION['Usuario'] ?? 'SISTEMA');
            $mensagem = "Novo usuário criado (inativo e licença bloqueada):\n\n" .
                        "Nome: $nome\nUsuário: $usuario\nSenha: $senha\nEmail: $email\n" .
                        "Empresa: $empresa\nGrupo: $grupo\nAtivo: $ativo\nLicenca: $licenca\n";

            enviarEmailSGVendas('valterpb@hotmail.com', $assunto, nl2br($mensagem));

            if ($email) {
                $msgUsuario = "Olá $nome,<br><br>"
                            . "Sua conta foi criada no sistema!<br><br>"
                            . "<strong>Empresa:</strong> $empresa<br>"
                            . "<strong>Usuário:</strong> $usuario<br>"
                            . "<strong>Senha:</strong> $senha<br><br>"
                            . "<strong>IMPORTANTE:</strong> Sua conta está <strong>INATIVA</strong> e a <strong>LICENÇA NÃO ESTÁ LIBERADA</strong>.<br>"
                            . "Entre em contato com o administrador para ativação.<br><br>"
                            . "Acesse: http://{$_SERVER['HTTP_HOST']}<br><br>"
                            . "Atenciosamente,<br>Equipe $empresa";

                enviarEmailSGVendas($email, "Bem-vindo ao $empresa", $msgUsuario);
            }
        }

        header("Location: usrv_gestao_listar2.php?cad=sucesso");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        $erro = "Erro ao cadastrar usuário: " . htmlspecialchars($e->getMessage());
    }
}

// Preparar dados para JavaScript (gerências por diretoria)
$gerenciasPorDiretoria = [];
foreach ($gerencias as $g) {
    $gerenciasPorDiretoria[$g['DiretoriaID']][] = [
        'id'   => $g['GerenciaID'],
        'nome' => $g['NomeGerencia']
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo Corretor - <?= htmlspecialchars($empresa) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e9f0ff 100%);
            min-height: 100vh;
            padding-bottom: 3rem;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.07);
        }
        .card-header {
            background: #0d6efd;
            color: white;
            border-radius: 1rem 1rem 0 0;
            padding: 1.5rem;
        }
        .form-label {
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 0.75rem;
            padding: 0.7rem 1rem;
        }
        .fixed-value {
            background-color: #f1f3f5 !important;
            color: #495057;
            cursor: not-allowed;
        }
        .info-box {
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .btn-primary {
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">

            <?php if (isset($erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $erro ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header text-center">
                    <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Novo Corretor</h4>
                </div>

                <div class="card-body p-4 p-lg-5">

                    <div class="info-box bg-info-subtle border-start border-info border-5">
                        <strong><i class="fas fa-building me-2"></i>Empresa:</strong> <?= htmlspecialchars($empresa) ?> 
                    </div>



                    <form id="formCadastro" method="post" class="needs-validation" novalidate>

                        <div class="mb-4">
                            <label for="Nome" class="form-label required">Nome Completo</label>
                            <input type="text" class="form-control" id="Nome" name="Nome" required autofocus placeholder="Ex: Maria Oliveira Santos">
                            <div class="invalid-feedback">O nome completo é obrigatório.</div>
                            <small class="text-muted">Usuário e senha serão gerados automaticamente</small>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="Usuario" class="form-label required">Usuário</label>
                                <input type="text" class="form-control fixed-value" id="Usuario" name="Usuario" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label for="Senha" class="form-label required">Senha</label>
                                <div class="input-group">
                                    <input type="password" class="form-control fixed-value" id="Senha" name="Senha" readonly required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleSenha">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="Email" class="form-label required">E-mail</label>
                            <input type="email" class="form-control" id="Email" name="Email" required placeholder="exemplo@dominio.com.br">
                            <div class="invalid-feedback">Informe um e-mail válido.</div>
                        </div>

                        <div class="mb-4">
                            <label for="Telefones" class="form-label">Telefones</label>
                            <input type="text" class="form-control" id="Telefones" name="Telefones" placeholder="(81) 99999-9999 / (81) 3333-4444">
                        </div>

                        <div class="mb-4">
                            <label for="CRECI" class="form-label">CRECI</label>
                            <input type="text" class="form-control" id="CRECI" name="CRECI" placeholder="Ex: 54321-PE">
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="DiretoriaID" class="form-label required">Diretoria</label>
                                <select class="form-select" id="DiretoriaID" name="DiretoriaID" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($diretorias as $d): ?>
                                        <option value="<?= $d['DiretoriaID'] ?>"><?= htmlspecialchars($d['NomeDiretoria']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Selecione a diretoria.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="GerenciaID" class="form-label required">Gerência</label>
                                <select class="form-select" id="GerenciaID" name="GerenciaID" required>
                                    <option value="">Selecione a diretoria primeiro...</option>
                                </select>
                                <div class="invalid-feedback">Selecione a gerência.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-5 border-top pt-4">
                            <div class="col-md-6">
                                <label class="form-label">Grupo</label>
                                <input type="text" class="form-control fixed-value" value="TOCCA_ONZE" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Empresa</label>
                                <input type="text" class="form-control fixed-value" value="<?= htmlspecialchars($empresa) ?>" readonly>
                            </div>
                        </div>

                        <div class="d-grid gap-3 d-md-flex justify-content-md-end">
                            <a href="usrv_gestao_listar2.php" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-arrow-left me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary px-5" id="btnSubmit">
                                <i class="fas fa-user-plus me-2"></i>Cadastrar
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <p class="text-center text-muted mt-4 small">
                <?= htmlspecialchars($empresa) ?> © <?= date('Y') ?>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Gerências por diretoria
const gerenciasPorDiretoria = <?= json_encode($gerenciasPorDiretoria, JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('DiretoriaID')?.addEventListener('change', function() {
    const dir = this.value;
    const sel = document.getElementById('GerenciaID');
    sel.innerHTML = '<option value="">Selecione...</option>';
    if (dir && gerenciasPorDiretoria[dir]) {
        gerenciasPorDiretoria[dir].forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id;
            opt.text = g.nome;
            sel.appendChild(opt);
        });
    }
});

// Geração automática usuário/senha
function removerAcentos(str) {
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

document.getElementById('Nome')?.addEventListener('input', function() {
    const nome = this.value.trim();
    if (!nome) return;

    const partes = removerAcentos(nome).toLowerCase().split(/\s+/);
    let usr = partes[0] || '';
    if (partes[1]) usr += partes[1][0];
    if (partes[2]) usr += partes[2][0];

    const prim = removerAcentos(partes[0] || '').toLowerCase();
    const num = String(Math.floor(Math.random() * 90) + 10).padStart(2, '0');

    document.getElementById('Usuario').value = usr;
    document.getElementById('Senha').value = prim + '@tv' + num;
});

// Toggle senha
document.getElementById('toggleSenha')?.addEventListener('click', function() {
    const input = document.getElementById('Senha');
    input.type = input.type === 'password' ? 'text' : 'password';
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

// Validação + loading
const form = document.getElementById('formCadastro');
form?.addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');

    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Cadastrando...';
});
</script>
</body>
</html>
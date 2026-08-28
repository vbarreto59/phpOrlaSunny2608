<?php
// usr_novo.php
session_start();
require_once 'conn.php'; // seu arquivo de conexão PDO

// =============================================
// PROCESSAMENTO DO FORMULÁRIO
// =============================================
$erro = '';
$sucesso = false;
$sucessoMensagem = '';
$formData = $_POST; // para manter valores em caso de erro

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['Nome']       ?? '');
    $usuario     = trim($_POST['Usuario']    ?? '');
    $senha       = trim($_POST['Senha']      ?? '');
    $email       = trim($_POST['Email']      ?? '');
    $telefones   = trim($_POST['Telefones']  ?? '');
    $creci       = trim($_POST['CRECI']      ?? '');
    $acoes       = trim($_POST['Acoes']      ?? '');
    $permissao   = (int)($_POST['Permissao'] ?? 0);
    $funcao      = trim($_POST['Funcao']     ?? '');
    $idEmp       = (int)($_POST['idEmp']     ?? 0);
    $idGrupo     = (int)($_POST['ID_Grupo']  ?? 0);
    $diretoriaID = (int)($_POST['DiretoriaID'] ?? 0);
    $gerenciaID  = (int)($_POST['GerenciaID']  ?? 0);
    $ativo       = isset($_POST['Ativo']) ? 1 : 0;

    // Validações básicas
    if (empty($usuario) || empty($senha) || $permissao < 1 || $permissao > 7 || empty($idEmp) || empty($idGrupo)) {
        $erro = "Campos obrigatórios não preenchidos.";
    } elseif (empty($funcao) || empty($acoes)) {
        $erro = "Função e ações não foram definidas corretamente.";
    } else {
        try {
            $pdo->beginTransaction();

            // Verifica duplicidade
            $stmt = $pdo->prepare("SELECT UserId FROM Usuarios WHERE Usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) {
                throw new Exception("Este nome de usuário já está cadastrado.");
            }

            // Nome da empresa
            $stmtEmp = $pdo->prepare("SELECT EmpresaSunny FROM SunnyEmpresas WHERE IdEmp = ?");
            $stmtEmp->execute([$idEmp]);
            $empresa = $stmtEmp->fetchColumn();
            if (!$empresa) {
                throw new Exception("Empresa não encontrada.");
            }

            // Nome da diretoria e gerência
            $nomeDiretoria = $nomeGerencia = '';
            if ($diretoriaID) {
                $stmtDir = $pdo->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
                $stmtDir->execute([$diretoriaID]);
                $nomeDiretoria = $stmtDir->fetchColumn() ?: '';
            }
            if ($gerenciaID) {
                $stmtGer = $pdo->prepare("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = ?");
                $stmtGer->execute([$gerenciaID]);
                $nomeGerencia = $stmtGer->fetchColumn() ?: '';
            }

            // Inserção na tabela Usuarios
            $sqlUser = "
                INSERT INTO Usuarios (
                    Usuario, SenhaAtual, Nome, CRECI, Email, Telefones, Acoes,
                    Permissao, Funcao, Ativo, idEmp, Empresa,
                    DiretoriaID, Diretoria, GerenciaID, Gerencia
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtUser = $pdo->prepare($sqlUser);
            $stmtUser->execute([
                $usuario, $senha, $nome, $creci, $email, $telefones, $acoes,
                $permissao, $funcao, $ativo, $idEmp, $empresa,
                $diretoriaID ?: null, $nomeDiretoria, $gerenciaID ?: null, $nomeGerencia
            ]);

            $newUserId = $pdo->lastInsertId();

            // Relacionamento com grupo
            $stmtGrupo = $pdo->prepare("INSERT INTO Usuario_Grupo (UserId, ID_Grupo) VALUES (?, ?)");
            $stmtGrupo->execute([$newUserId, $idGrupo]);

            $pdo->commit();

            $sucesso = true;
            $sucessoMensagem = "Usuário <strong>" . htmlspecialchars($usuario) . "</strong> criado com sucesso!<br>ID gerado: <strong>$newUserId</strong>";

            // Limpa formulário após sucesso
            $formData = [];

        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro ao criar usuário: " . htmlspecialchars($e->getMessage());
        }
    }
}

// =============================================
// CARREGAR DADOS PARA OS SELECTS
// =============================================

$stmtEmpresas = $pdo->query("SELECT IdEmp, EmpresaSunny FROM SunnyEmpresas ORDER BY EmpresaSunny");
$empresas = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);

$stmtGrupos = $pdo->query("SELECT ID_Grupo, Nome_Grupo FROM Grupo ORDER BY Nome_Grupo");
$grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

$stmtDiretorias = $pdo->query("SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria");
$diretorias = $stmtDiretorias->fetchAll(PDO::FETCH_ASSOC);

$gerenciasPorDiretoria = [];
$stmtGerencias = $pdo->query("SELECT GerenciaID, DiretoriaID, NomeGerencia FROM gerencias ORDER BY NomeGerencia");
while ($row = $stmtGerencias->fetch(PDO::FETCH_ASSOC)) {
    $gerenciasPorDiretoria[$row['DiretoriaID']][] = [
        'id'   => $row['GerenciaID'],
        'nome' => $row['NomeGerencia']
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sunny - Criar Novo Usuário</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card-form { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-header { background-color: #343a40; color: white; border-radius: 10px 10px 0 0 !important; }
        .btn-custom { min-width: 140px; }
        .required-field::after { content: " *"; color: red; }
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #28a745; }
        input:checked + .slider:before { transform: translateX(26px); }
        .status-label { margin-left: 10px; font-weight: 500; }
        .status-ativo { color: #28a745; }
        .status-inativo { color: #dc3545; }
        .input-group.password-toggle .form-control { padding-right: 40px; }
        .input-group.password-toggle .input-group-append { position: absolute; right: 0; top: 0; bottom: 0; z-index: 5; display: flex; align-items: center; }
        .input-group.password-toggle .input-group-text { background: transparent; border: none; padding: 0.375rem 0.75rem; cursor: pointer; color: #888; }
        .badge-permissao { padding: .25em .5em; font-size: 75%; font-weight: 700; border-radius: .25rem; }
        .superadmin     { background-color: #dc3545; color: white; }
        .admin          { background-color: #ffc107; color: #343a40; }
        .gestor-editor, .editor { background-color: #007bff; color: white; }
        .corretor, .corretor-editor { background-color: #6c757d; color: white; }
        .visualizador   { background-color: #17a2b8; color: white; }
        .desconhecido   { background-color: #6f42c1; color: white; }
        .alert-success-custom { background-color: #d4edda; border-color: #c3e6cb; color: #155724; font-weight: 500; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card card-form">
                <div class="card-header form-header">
                    <h4 class="mb-0"><i class="fas fa-user-plus mr-2"></i>Criar Novo Usuário</h4>
                </div>

                <div class="card-body">

                    <?php if ($erro): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= htmlspecialchars($erro) ?>
                        <button type="button" class="close" data-dismiss="alert">×</button>
                    </div>
                    <?php endif; ?>

                    <?php if ($sucesso): ?>
                    <div class="alert alert-success alert-dismissible fade show alert-success-custom" role="alert">
                        <i class="fas fa-check-circle fa-lg mr-2"></i>
                        <?= $sucessoMensagem ?>
                        <hr class="my-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="usr_novo.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-plus mr-1"></i>Criar outro usuário
                            </a>
                            <a href="usr_listar.asp" class="btn btn-success btn-sm">
                                <i class="fas fa-list mr-1"></i>Ver lista de usuários
                            </a>
                        </div>
                        <button type="button" class="close" data-dismiss="alert">×</button>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="" id="formUsuario">

                        <div class="form-group">
                            <label for="Nome">Nome Completo</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-signature"></i></span></div>
                                <input type="text" class="form-control" id="Nome" name="Nome" 
                                       value="<?= htmlspecialchars($formData['Nome'] ?? '') ?>" 
                                       placeholder="Digite o nome completo">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Usuario" class="required-field">Usuário</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                <input type="text" class="form-control" id="Usuario" name="Usuario" required
                                       value="<?= htmlspecialchars($formData['Usuario'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Senha" class="required-field">Senha</label>
                            <div class="input-group password-toggle">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                                <input type="password" class="form-control" id="Senha" name="Senha" required
                                       value="<?= htmlspecialchars($formData['Senha'] ?? '') ?>">
                                <div class="input-group-append">
                                    <span class="input-group-text" id="togglePassword"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Email">Email</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                                <input type="email" class="form-control" id="Email" name="Email"
                                       value="<?= htmlspecialchars($formData['Email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Telefones">Telefones</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                <input type="text" class="form-control" id="Telefones" name="Telefones"
                                       value="<?= htmlspecialchars($formData['Telefones'] ?? '') ?>"
                                       placeholder="Ex: (81) 99999-9999">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="idEmp" class="required-field">Empresa</label>
                            <select class="form-control" id="idEmp" name="idEmp" required>
                                <option value="">Selecione uma empresa</option>
                                <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['IdEmp'] ?>" 
                                            <?= ($emp['IdEmp'] == ($formData['idEmp'] ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['EmpresaSunny']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ID_Grupo" class="required-field">Grupo</label>
                            <select class="form-control" id="ID_Grupo" name="ID_Grupo" required>
                                <option value="">Selecione um grupo</option>
                                <?php foreach ($grupos as $grp): ?>
                                    <option value="<?= $grp['ID_Grupo'] ?>" 
                                            <?= ($grp['ID_Grupo'] == ($formData['ID_Grupo'] ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($grp['Nome_Grupo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="DiretoriaID">Diretoria</label>
                            <select class="form-control" id="DiretoriaID" name="DiretoriaID">
                                <option value="">— selecione —</option>
                                <?php foreach ($diretorias as $dir): ?>
                                    <option value="<?= $dir['DiretoriaID'] ?>" 
                                            <?= ($dir['DiretoriaID'] == ($formData['DiretoriaID'] ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dir['NomeDiretoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="GerenciaID">Gerência</label>
                            <select class="form-control" id="GerenciaID" name="GerenciaID">
                                <option value="">Selecione a diretoria primeiro</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="Permissao" class="required-field">Nível de Permissão</label>
                            <select class="form-control" id="Permissao" name="Permissao" required onchange="atualizarFuncao()">
                                <option value="">Selecione o nível</option>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($i == ($formData['Permissao'] ?? '')) ? 'selected' : '' ?>>
                                        <?= $i ?> – <?= ['SUPERADMIN','ADMIN','GESTOR-EDITOR','EDITOR','CORRETOR','CORRETOR-EDITOR','VISUALIZADOR'][$i-1] ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="Funcao" class="required-field">Função</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="Funcao" name="Funcao" readonly
                                       value="<?= htmlspecialchars($formData['Funcao'] ?? '') ?>">
                                <div class="input-group-append">
                                    <span class="input-group-text badge-permissao desconhecido" id="badgePermissao">
                                        <i class="fas fa-user-tag"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Acoes" class="required-field">Ações Permitidas</label>
                            <textarea class="form-control" id="Acoes" name="Acoes" rows="3" readonly><?= htmlspecialchars($formData['Acoes'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="CRECI">CRECI</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-card-alt"></i></span></div>
                                <input type="text" class="form-control" id="CRECI" name="CRECI"
                                       value="<?= htmlspecialchars($formData['CRECI'] ?? '') ?>"
                                       placeholder="Digite o CRECI (opcional)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Status do Usuário</label>
                            <div class="d-flex align-items-center">
                                <label class="switch">
                                    <input type="checkbox" id="Ativo" name="Ativo" value="1" 
                                           <?= isset($formData['Ativo']) ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="status-label <?= isset($formData['Ativo']) ? 'status-ativo' : 'status-inativo' ?>" id="statusLabel">
                                    <?= isset($formData['Ativo']) ? 'ATIVO' : 'INATIVO' ?>
                                </span>
                            </div>
                        </div>

                        <div class="form-group text-right mt-4">
                            <button type="submit" class="btn btn-primary btn-custom" id="btnSubmit">
                                <i class="fas fa-save mr-2"></i>Criar Usuário
                            </button>
                            <a href="usr_listar.asp" class="btn btn-secondary btn-custom">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
// Dados gerências por diretoria
const gerenciasPorDiretoria = <?= json_encode($gerenciasPorDiretoria, JSON_UNESCAPED_UNICODE) ?>;

// Carrega gerências
document.getElementById('DiretoriaID').addEventListener('change', function() {
    const dirId = this.value;
    const selGer = document.getElementById('GerenciaID');
    selGer.innerHTML = '<option value="">Selecione...</option>';
    if (dirId && gerenciasPorDiretoria[dirId]) {
        gerenciasPorDiretoria[dirId].forEach(g => {
            let opt = document.createElement('option');
            opt.value = g.id;
            opt.text = g.nome;
            selGer.appendChild(opt);
        });
    }
});

// Remove acentos
function removerAcentos(str) {
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

// Gera usuário e senha
function gerarUsuarioSenha(nome) {
    if (!nome.trim()) return { usuario: '', senha: '' };
    const partes = removerAcentos(nome.trim().toLowerCase()).split(/\s+/);
    let usuario = partes[0];
    for (let i = 1; i < partes.length; i++) {
        if (partes[i]) usuario += partes[i][0];
    }
    const primeiro = removerAcentos(partes[0].toLowerCase());
    const num = String(Math.floor(Math.random() * 99) + 1).padStart(2, '0');
    return { usuario, senha: primeiro + '@tv' + num };
}

// Atualiza função, ações e badge
function atualizarFuncao() {
    const perm = document.getElementById('Permissao').value;
    const funcaoEl = document.getElementById('Funcao');
    const acoesEl  = document.getElementById('Acoes');
    const badge    = document.getElementById('badgePermissao');

    let funcao = '', acoes = '', classe = 'desconhecido';

    switch(perm) {
        case '1': funcao = 'SUPERADMIN'; acoes = '/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; classe = 'superadmin'; break;
        case '2': funcao = 'ADMIN'; acoes = '/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; classe = 'admin'; break;
        case '3': funcao = 'GESTOR-EDITOR'; acoes = '/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; classe = 'gestor-editor'; break;
        case '4': funcao = 'EDITOR'; acoes = '/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; classe = 'editor'; break;
        case '5': funcao = 'CORRETOR'; acoes = '/CONSULTAR/'; classe = 'corretor'; break;
        case '6': funcao = 'CORRETOR-EDITOR'; acoes = '/CONSULTAR/EDITAR/'; classe = 'corretor-editor'; break;
        case '7': funcao = 'VISUALIZADOR'; acoes = '/CONSULTAR/'; classe = 'visualizador'; break;
    }

    funcaoEl.value = funcao;
    acoesEl.value  = acoes;
    badge.className = 'input-group-text badge-permissao ' + classe;
}

// Eventos
document.addEventListener('DOMContentLoaded', () => {
    // Toggle senha
    document.getElementById('togglePassword')?.addEventListener('click', e => {
        const input = document.getElementById('Senha');
        const icon = e.currentTarget.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });

    // Geração automática
    document.getElementById('Nome')?.addEventListener('input', e => {
        const res = gerarUsuarioSenha(e.target.value);
        document.getElementById('Usuario').value = res.usuario;
        document.getElementById('Senha').value   = res.senha;
    });

    // Switch Ativo/Inativo
    document.getElementById('Ativo')?.addEventListener('change', e => {
        const label = document.getElementById('statusLabel');
        if (e.target.checked) {
            label.textContent = 'ATIVO';
            label.className = 'status-label status-ativo';
        } else {
            label.textContent = 'INATIVO';
            label.className = 'status-label status-inativo';
        }
    });

    // Previne duplo submit
    document.getElementById('formUsuario')?.addEventListener('submit', function() {
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Criando...';
    });

    atualizarFuncao();
});
</script>
</body>
</html>
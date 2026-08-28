<?php
// usr_novo.php
session_start();
require_once 'conn.php'; // Certifique-once que este arquivo existe e define $pdo

// =============================================
// PROCESSAMENTO DO FORMULÁRIO
// =============================================
$erro = '';
$sucesso = false;
$sucessoMensagem = '';
$formData = $_POST; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['Nome']        ?? '');
    $usuario     = trim($_POST['Usuario']     ?? '');
    $senha       = trim($_POST['Senha']       ?? '');
    $email       = trim($_POST['Email']       ?? '');
    $telefones   = trim($_POST['Telefones']   ?? '');
    $creci       = trim($_POST['CRECI']       ?? '');
    $acoes       = trim($_POST['Acoes']       ?? '');
    $permissao   = (int)($_POST['Permissao']  ?? 0);
    $funcao      = trim($_POST['Funcao']      ?? '');
    $idEmp       = (int)($_POST['idEmp']      ?? 0);
    $idGrupo     = (int)($_POST['ID_Grupo']   ?? 0);
    $diretoriaID = (int)($_POST['DiretoriaID'] ?? 0);
    $gerenciaID  = (int)($_POST['GerenciaID']  ?? 0);
    $ativo       = isset($_POST['Ativo']) ? 1 : 0;

    if (empty($usuario) || empty($senha) || $permissao < 1 || empty($idEmp) || empty($idGrupo)) {
        $erro = "Campos obrigatórios não preenchidos.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT UserId FROM Usuarios WHERE Usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) {
                throw new Exception("Este nome de usuário já está cadastrado.");
            }

            $stmtEmp = $pdo->prepare("SELECT EmpresaSunny FROM SunnyEmpresas WHERE IdEmp = ?");
            $stmtEmp->execute([$idEmp]);
            $empresa = $stmtEmp->fetchColumn();

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

            $sqlUser = "INSERT INTO Usuarios (Usuario, SenhaAtual, Nome, CRECI, Email, Telefones, Acoes, Permissao, Funcao, Ativo, idEmp, Empresa, DiretoriaID, Diretoria, GerenciaID, Gerencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtUser = $pdo->prepare($sqlUser);
            $stmtUser->execute([$usuario, $senha, $nome, $creci, $email, $telefones, $acoes, $permissao, $funcao, $ativo, $idEmp, $empresa, $diretoriaID ?: null, $nomeDiretoria, $gerenciaID ?: null, $nomeGerencia]);

            $newUserId = $pdo->lastInsertId();
            $stmtGrupo = $pdo->prepare("INSERT INTO Usuario_Grupo (UserId, ID_Grupo) VALUES (?, ?)");
            $stmtGrupo->execute([$newUserId, $idGrupo]);

            $pdo->commit();
            $sucesso = true;
            $sucessoMensagem = "Usuário <strong>" . htmlspecialchars($usuario) . "</strong> criado com sucesso!";
            $formData = []; 
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro: " . $e->getMessage();
        }
    }
}

// CARREGAR DADOS
$empresas = $pdo->query("SELECT IdEmp, EmpresaSunny FROM SunnyEmpresas ORDER BY EmpresaSunny")->fetchAll(PDO::FETCH_ASSOC);
$grupos = $pdo->query("SELECT ID_Grupo, Nome_Grupo FROM Grupo ORDER BY Nome_Grupo")->fetchAll(PDO::FETCH_ASSOC);
$diretorias = $pdo->query("SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria")->fetchAll(PDO::FETCH_ASSOC);
$gerenciasPorDiretoria = [];
$stmtGerencias = $pdo->query("SELECT GerenciaID, DiretoriaID, NomeGerencia FROM gerencias");
while ($row = $stmtGerencias->fetch(PDO::FETCH_ASSOC)) {
    $gerenciasPorDiretoria[$row['DiretoriaID']][] = ['id' => $row['GerenciaID'], 'nome' => $row['NomeGerencia']];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sunny - Novo Usuário</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root { --primary: #4e73df; --success: #1cc88a; --dark: #343a40; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card-form { border: none; border-radius: 12px; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08); overflow: hidden; }
        .form-header { background: linear-gradient(135deg, #343a40 0%, #212529 100%); color: white; padding: 1.5rem; }
        .section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--primary); margin: 1.5rem 0 1rem; padding-bottom: 5px; border-bottom: 2px solid #e3e6f0; letter-spacing: 0.5px; }
        .form-control { border-radius: 8px; border: 1px solid #d1d3e2; }
        .input-group-text { background-color: #f8f9fc; border-color: #d1d3e2; color: #b7b9cc; border-radius: 8px 0 0 8px; }
        .switch { position: relative; display: inline-block; width: 46px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(22px); }
        .status-ativo { color: var(--success); font-weight: bold; }
        .status-inativo { color: #e74a3b; font-weight: bold; }
        .badge-permissao { border-radius: 0 8px 8px 0; min-width: 45px; justify-content: center; }
        .superadmin { background-color: #e74a3b !important; color: white; }
        .admin { background-color: #f6c23e !important; color: white; }
        .gestor-editor, .editor { background-color: #4e73df !important; color: white; }
        .visualizador { background-color: #36b9cc !important; color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-form">
                <div class="form-header text-center">
                    <h4 class="mb-0"><i class="fas fa-user-plus mr-2"></i>Admin - Cadastro de Usuário - v3</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle mr-2"></i><?= $erro ?><button class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>

                    <?php if ($sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle mr-2"></i><?= $sucessoMensagem ?>
                            <div class="mt-2"><a href="usr_listar.asp" class="btn btn-sm btn-success">Ir para lista</a></div>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="formUsuario">
                        <div class="section-title">Acesso e Identificação</div>
                        <div class="form-group">
                            <label class="small font-weight-bold">NOME COMPLETO</label>
                            <input type="text" class="form-control" name="Nome" id="Nome" value="<?= htmlspecialchars($formData['Nome'] ?? '') ?>" placeholder="João Silva...">
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-primary">USUÁRIO *</label>
                                <input type="text" class="form-control" name="Usuario" id="Usuario" required value="<?= htmlspecialchars($formData['Usuario'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-primary">SENHA *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="Senha" id="Senha" required value="<?= htmlspecialchars($formData['Senha'] ?? '') ?>">
                                    <div class="input-group-append"><span class="input-group-text" id="togglePassword" style="cursor:pointer"><i class="fas fa-eye"></i></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">EMAIL</label>
                                <input type="email" class="form-control" name="Email" value="<?= htmlspecialchars($formData['Email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">TELEFONES</label>
                                <input type="text" class="form-control" name="Telefones" placeholder="(00) 00000-0000" value="<?= htmlspecialchars($formData['Telefones'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="section-title">Vínculos Institucionais</div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-primary">EMPRESA *</label>
                                <select class="form-control" name="idEmp" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($empresas as $emp): ?>
                                        <option value="<?= $emp['IdEmp'] ?>" <?= ($emp['IdEmp'] == ($formData['idEmp'] ?? '')) ? 'selected' : '' ?>><?= $emp['EmpresaSunny'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-primary">GRUPO *</label>
                                <select class="form-control" name="ID_Grupo" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($grupos as $grp): ?>
                                        <option value="<?= $grp['ID_Grupo'] ?>" <?= ($grp['ID_Grupo'] == ($formData['ID_Grupo'] ?? '')) ? 'selected' : '' ?>><?= $grp['Nome_Grupo'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">DIRETORIA</label>
                                <select class="form-control" name="DiretoriaID" id="DiretoriaID">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($diretorias as $dir): ?>
                                        <option value="<?= $dir['DiretoriaID'] ?>"><?= $dir['NomeDiretoria'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">GERÊNCIA</label>
                                <select class="form-control" name="GerenciaID" id="GerenciaID">
                                    <option value="">Aguardando diretoria...</option>
                                </select>
                            </div>
                        </div>

                        <div class="section-title">Permissões e Status</div>
                        <div class="row align-items-end">
                            <div class="col-md-8 form-group">
                                <label class="small font-weight-bold text-primary">NÍVEL DE ACESSO *</label>
                                <select class="form-control" name="Permissao" id="Permissao" required onchange="atualizarFuncao()">
                                    <option value="">Selecione o nível...</option>
                                    <option value="1">1 – SUPERADMIN</option>
                                    <option value="2">2 – ADMIN</option>
                                    <option value="3">3 – GESTOR-EDITOR</option>
                                    <option value="4">4 – EDITOR</option>
                                    <option value="5">5 – CORRETOR</option>
                                    <option value="6">6 – CORRETOR-EDITOR</option>
                                    <option value="7">7 – VISUALIZADOR</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <div class="d-flex align-items-center mb-2">
                                    <label class="switch mb-0"><input type="checkbox" name="Ativo" id="Ativo" value="1" checked><span class="slider"></span></label>
                                    <span class="ml-2 small font-weight-bold status-ativo" id="statusLabel">ATIVO</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold">FUNÇÃO SISTÊMICA</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="Funcao" id="Funcao" readonly>
                                <div class="input-group-append"><span class="input-group-text badge-permissao" id="badgePermissao"><i class="fas fa-shield-alt"></i></span></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold">AÇÕES PERMITIDAS</label>
                            <textarea class="form-control" name="Acoes" id="Acoes" rows="2" readonly style="font-size:0.75rem; background:#f8f9fc"></textarea>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                            <a href="usr_listar.asp" class="btn btn-light px-4 mr-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-5 font-weight-bold" id="btnSubmit">Criar Usuário</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
const gerenciasPorDiretoria = <?= json_encode($gerenciasPorDiretoria) ?>;

document.getElementById('DiretoriaID').addEventListener('change', function() {
    const dirId = this.value;
    const selGer = document.getElementById('GerenciaID');
    selGer.innerHTML = '<option value="">Selecione...</option>';
    if (dirId && gerenciasPorDiretoria[dirId]) {
        gerenciasPorDiretoria[dirId].forEach(g => {
            let opt = document.createElement('option');
            opt.value = g.id; opt.text = g.nome; selGer.appendChild(opt);
        });
    }
});

function atualizarFuncao() {
    const perm = document.getElementById('Permissao').value;
    const funcaoEl = document.getElementById('Funcao');
    const acoesEl = document.getElementById('Acoes');
    const badge = document.getElementById('badgePermissao');
    let f = '', a = '', c = '';

    switch(perm) {
        case '1': f='SUPERADMIN'; a='/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; c='superadmin'; break;
        case '2': f='ADMIN'; a='/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; c='admin'; break;
        case '3': f='GESTOR-EDITOR'; a='/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; c='gestor-editor'; break;
        case '4': f='EDITOR'; a='/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; c='editor'; break;
        case '5': f='CORRETOR'; a='/CONSULTAR/'; c='visualizador'; break;
        case '6': f='CORRETOR-EDITOR'; a='/CONSULTAR/EDITAR/'; c='gestor-editor'; break;
        case '7': f='VISUALIZADOR'; a='/CONSULTAR/'; c='visualizador'; break;
    }
    funcaoEl.value = f; acoesEl.value = a;
    badge.className = 'input-group-text badge-permissao ' + c;
}

document.getElementById('Nome').addEventListener('input', function(e) {
    const nome = e.target.value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
    if(nome) {
        const partes = nome.split(/\s+/);
        let usr = partes[0];
        for(let i=1; i<partes.length; i++) usr += partes[i][0];
        document.getElementById('Usuario').value = usr;
        document.getElementById('Senha').value = partes[0] + '@tv' + Math.floor(Math.random()*90 + 10);
    }
});

document.getElementById('togglePassword').addEventListener('click', function() {
    const pwd = document.getElementById('Senha');
    const icon = this.querySelector('i');
    if(pwd.type === 'password') { pwd.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
    else { pwd.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
});

document.getElementById('Ativo').addEventListener('change', function() {
    const lbl = document.getElementById('statusLabel');
    lbl.textContent = this.checked ? 'ATIVO' : 'INATIVO';
    lbl.className = this.checked ? 'ml-2 small font-weight-bold status-ativo' : 'ml-2 small font-weight-bold status-inativo';
});
</script>
</body>
</html>
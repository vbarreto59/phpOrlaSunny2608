<?php
// usr_edita.php
session_start();
require_once 'conn.php';

// =============================================
// 1. CARREGAMENTO DOS DADOS DO USUÁRIO
// =============================================
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$erro = '';
$sucesso = false;

if (!$id) {
    die("ID do usuário não fornecido.");
}

// Busca dados atuais
$stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE UserId = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Usuário não encontrado.");
}

// Busca o grupo atual do usuário
$stmtGrp = $pdo->prepare("SELECT ID_Grupo FROM Usuario_Grupo WHERE UserId = ?");
$stmtGrp->execute([$id]);
$idGrupoAtual = $stmtGrp->fetchColumn();

// =============================================
// 2. PROCESSAMENTO DA ATUALIZAÇÃO (POST)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['Nome']        ?? '');
    $usuario     = trim($_POST['Usuario']     ?? '');
    $senhaNova   = trim($_POST['Senha']       ?? '');
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

    try {
        $pdo->beginTransaction();

        // 2.1 Buscar nomes para denormalização
        $empresa = $pdo->query("SELECT EmpresaSunny FROM SunnyEmpresas WHERE IdEmp = $idEmp")->fetchColumn();
        $nomeDir = $diretoriaID ? $pdo->query("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = $diretoriaID")->fetchColumn() : '';
        $nomeGer = $gerenciaID ? $pdo->query("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = $gerenciaID")->fetchColumn() : '';

        // 2.2 Montagem do Update
        $sql = "UPDATE Usuarios SET 
                Usuario = ?, Nome = ?, CRECI = ?, Email = ?, Telefones = ?, 
                Acoes = ?, Permissao = ?, Funcao = ?, Ativo = ?, 
                idEmp = ?, Empresa = ?, DiretoriaID = ?, Diretoria = ?, 
                GerenciaID = ?, Gerencia = ?";
        
        $params = [$usuario, $nome, $creci, $email, $telefones, $acoes, $permissao, $funcao, $ativo, $idEmp, $empresa, $diretoriaID ?: null, $nomeDir, $gerenciaID ?: null, $nomeGer];

        // Se digitou senha nova, adiciona ao SQL
        if (!empty($senhaNova)) {
            $sql .= ", SenhaAtual = ?";
            $params[] = $senhaNova;
        }

        $sql .= " WHERE UserId = ?";
        $params[] = $id;

        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute($params);

        // 2.3 Atualizar Grupo (Delete e Insert na Pivot)
        $pdo->prepare("DELETE FROM Usuario_Grupo WHERE UserId = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO Usuario_Grupo (UserId, ID_Grupo) VALUES (?, ?)")->execute([$id, $idGrupo]);

        $pdo->commit();
        $sucesso = true;
        
        // Recarrega os dados para o formulário refletir as mudanças
        $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE UserId = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $idGrupoAtual = $idGrupo;

    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = "Erro ao atualizar: " . $e->getMessage();
    }
}

// =============================================
// 3. CARREGAR LISTAS PARA SELECTS
// =============================================
$empresas = $pdo->query("SELECT IdEmp, EmpresaSunny FROM SunnyEmpresas ORDER BY EmpresaSunny")->fetchAll(PDO::FETCH_ASSOC);
$grupos = $pdo->query("SELECT ID_Grupo, Nome_Grupo FROM Grupo ORDER BY Nome_Grupo")->fetchAll(PDO::FETCH_ASSOC);
$diretorias = $pdo->query("SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria")->fetchAll(PDO::FETCH_ASSOC);
$gerenciasPorDiretoria = [];
$stmtGer = $pdo->query("SELECT GerenciaID, DiretoriaID, NomeGerencia FROM gerencias");
while ($row = $stmtGer->fetch(PDO::FETCH_ASSOC)) {
    $gerenciasPorDiretoria[$row['DiretoriaID']][] = ['id' => $row['GerenciaID'], 'nome' => $row['NomeGerencia']];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sunny - Editar Usuário</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root { --primary: #4e73df; --success: #1cc88a; --warning: #f6c23e; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card-form { border: none; border-radius: 12px; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08); overflow: hidden; }
        .form-header { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; padding: 1.5rem; }
        .section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--primary); margin: 1.5rem 0 1rem; padding-bottom: 5px; border-bottom: 2px solid #e3e6f0; letter-spacing: 0.5px; }
        .form-control { border-radius: 8px; border: 1px solid #d1d3e2; font-size: 0.95rem; }
        .switch { position: relative; display: inline-block; width: 46px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(22px); }
        .status-ativo { color: var(--success); font-weight: bold; }
        .status-inativo { color: #e74a3b; font-weight: bold; }
        .btn-custom { border-radius: 8px; font-weight: 600; padding: 0.6rem 1.2rem; transition: all 0.3s; }
        .badge-permissao { border-radius: 0 8px 8px 0; background: #f8f9fc; border-left: none; color: #b7b9cc; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-form">
                <div class="form-header text-center">
                    <h4 class="mb-0"><i class="fas fa-user-edit mr-2"></i>Editar: <?= htmlspecialchars($user['Usuario']) ?></h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle mr-2"></i> Alterações salvas com sucesso!
                            <button class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger"><?= $erro ?></div>
                    <?php endif; ?>

                    <form method="post" id="formUsuario">
                        
                        <div class="section-title">Dados de Identificação</div>
                        <div class="form-group">
                            <label class="small font-weight-bold">NOME COMPLETO</label>
                            <input type="text" class="form-control" name="Nome" value="<?= htmlspecialchars($user['Nome']) ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-primary">USUÁRIO</label>
                                <input type="text" class="form-control" name="Usuario" value="<?= htmlspecialchars($user['Usuario']) ?>" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-warning">ALTERAR SENHA (OPCIONAL)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="Senha" id="Senha" placeholder="Deixe vazio para manter">
                                    <div class="input-group-append"><span class="input-group-text" id="togglePassword" style="cursor:pointer"><i class="fas fa-eye"></i></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">EMAIL</label>
                                <input type="email" class="form-control" name="Email" value="<?= htmlspecialchars($user['Email']) ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">TELEFONES</label>
                                <input type="text" class="form-control" name="Telefones" value="<?= htmlspecialchars($user['Telefones']) ?>">
                            </div>
                        </div>

                        <div class="section-title">Vínculos Institucionais</div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">EMPRESA</label>
                                <select class="form-control" name="idEmp" required>
                                    <?php foreach ($empresas as $emp): ?>
                                        <option value="<?= $emp['IdEmp'] ?>" <?= ($emp['IdEmp'] == $user['IdEmp']) ? 'selected' : '' ?>>
                                            <?= $emp['EmpresaSunny'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">GRUPO</label>
                                <select class="form-control" name="ID_Grupo" required>
                                    <?php foreach ($grupos as $grp): ?>
                                        <option value="<?= $grp['ID_Grupo'] ?>" <?= ($grp['ID_Grupo'] == $idGrupoAtual) ? 'selected' : '' ?>>
                                            <?= $grp['Nome_Grupo'] ?>
                                        </option>
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
                                        <option value="<?= $dir['DiretoriaID'] ?>" <?= ($dir['DiretoriaID'] == $user['DiretoriaID']) ? 'selected' : '' ?>>
                                            <?= $dir['NomeDiretoria'] ?>
                                        </option>
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

                        <div class="section-title">Acesso e Status</div>
                        <div class="row align-items-end">
                            <div class="col-md-8 form-group">
                                <label class="small font-weight-bold">NÍVEL DE PERMISSÃO</label>
                                <select class="form-control" name="Permissao" id="Permissao" required onchange="atualizarFuncao()">
                                    <?php 
                                    $niveis = ['SUPERADMIN','ADMIN','GESTOR-EDITOR','EDITOR','CORRETOR','CORRETOR-EDITOR','VISUALIZADOR'];
                                    foreach($niveis as $k => $v): 
                                        $val = $k + 1;
                                    ?>
                                        <option value="<?= $val ?>" <?= ($user['Permissao'] == $val) ? 'selected' : '' ?>><?= $val ?> – <?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <div class="d-flex align-items-center mb-2">
                                    <label class="switch mb-0"><input type="checkbox" name="Ativo" id="Ativo" value="1" <?= $user['Ativo'] ? 'checked' : '' ?>><span class="slider"></span></label>
                                    <span class="ml-2 small font-weight-bold <?= $user['Ativo'] ? 'status-ativo' : 'status-inativo' ?>" id="statusLabel"><?= $user['Ativo'] ? 'ATIVO' : 'INATIVO' ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold">FUNÇÃO SISTÊMICA</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="Funcao" id="Funcao" value="<?= htmlspecialchars($user['Funcao']) ?>" readonly>
                                <div class="input-group-append"><span class="input-group-text badge-permissao"><i class="fas fa-lock"></i></span></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold">AÇÕES PERMITIDAS</label>
                            <textarea class="form-control" name="Acoes" id="Acoes" rows="2" readonly style="font-size:0.75rem; background:#f8f9fc"><?= htmlspecialchars($user['Acoes']) ?></textarea>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-between">
                            <a href="usr_listar.asp" class="btn btn-light btn-custom">Voltar para Lista</a>
                            <button type="submit" class="btn btn-primary btn-custom shadow-sm px-5">Atualizar Usuário</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
const gerenciasData = <?= json_encode($gerenciasPorDiretoria) ?>;
const gerenciaSalva = "<?= $user['GerenciaID'] ?>";

// Gerencia o carregamento dinâmico das gerências
function carregarGerencias(dirId, selecionada = null) {
    const selGer = document.getElementById('GerenciaID');
    selGer.innerHTML = '<option value="">Selecione...</option>';
    if (dirId && gerenciasData[dirId]) {
        gerenciasData[dirId].forEach(g => {
            let opt = document.createElement('option');
            opt.value = g.id;
            opt.text = g.nome;
            if (selecionada && g.id == selecionada) opt.selected = true;
            selGer.appendChild(opt);
        });
    }
}

document.getElementById('DiretoriaID').addEventListener('change', function() {
    carregarGerencias(this.value);
});

// Atualiza Função e Ações conforme nível
function atualizarFuncao() {
    const perm = document.getElementById('Permissao').value;
    const acoesEl = document.getElementById('Acoes');
    const funcaoEl = document.getElementById('Funcao');
    let f = '', a = '';

    switch(perm) {
        case '1': f='SUPERADMIN'; a='/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; break;
        case '2': f='ADMIN'; a='/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; break;
        case '3': f='GESTOR-EDITOR'; a='/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; break;
        case '4': f='EDITOR'; a='/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'; break;
        case '5': f='CORRETOR'; a='/CONSULTAR/'; break;
        case '6': f='CORRETOR-EDITOR'; a='/CONSULTAR/EDITAR/'; break;
        case '7': f='VISUALIZADOR'; a='/CONSULTAR/'; break;
    }
    funcaoEl.value = f;
    acoesEl.value = a;
}

// Inicialização da página
$(document).ready(function() {
    // Carregar gerências salvas ao entrar
    const dirInicial = document.getElementById('DiretoriaID').value;
    if(dirInicial) carregarGerencias(dirInicial, gerenciaSalva);

    // Toggle senha
    $('#togglePassword').click(function() {
        const input = document.getElementById('Senha');
        const icon = $(this).find('i');
        if(input.type === 'password') {
            input.type = 'text';
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Toggle Switch
    $('#Ativo').change(function() {
        const lbl = $('#statusLabel');
        if(this.checked) {
            lbl.text('ATIVO').addClass('status-ativo').removeClass('status-inativo');
        } else {
            lbl.text('INATIVO').addClass('status-inativo').removeClass('status-ativo');
        }
    });
});
</script>
</body>
</html>
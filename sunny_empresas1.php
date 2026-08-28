<?php
// =======================================================
// SunnyImob - Gestão de Empresas (CRUD)
// Arquivo: sunny_empresas1.php
// =======================================================
session_start();
require_once "conn.php"; // Certifique-se que este arquivo contém a conexão PDO em $conn

// Verificação de segurança simples (opcional, baseada na sua sessão anterior)
if (!isset($_SESSION['UserId'])) {
    die("Acesso negado. Por favor, faça login.");
}

$mensagem = "";
$tipoMsg = "";

// =================================================================================
// LÓGICA DE PROCESSAMENTO (AÇÕES)
// =================================================================================

// 1. EXCLUSÃO
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $sql = "DELETE FROM sunnyempresas WHERE IdEmp = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt->execute([$id])) {
        $mensagem = "Empresa excluída com sucesso!";
        $tipoMsg = "success";
    }
}

// 2. SALVAR (INSERIR OU ATUALIZAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar'])) {
    $id = $_POST['IdEmp'] !== "" ? (int)$_POST['IdEmp'] : null;
    
    $dados = [
        'EmpresaSunny'        => $_POST['EmpresaSunny'],
        'CNPJ'                => $_POST['CNPJ'],
        'Endereco'            => $_POST['Endereco'],
        'Cidade'              => $_POST['Cidade'],
        'UF'                  => $_POST['UF'],
        'Responsavel1'        => $_POST['Responsavel1'],
        'Email1'              => $_POST['Email1'],
        'Telefone1'           => $_POST['Telefone1'],
        'Obs'                 => $_POST['Obs'],
        'Conf_CorPrimaria'    => $_POST['Conf_CorPrimaria'],
        'Conf_CorSecundaria'  => $_POST['Conf_CorSecundaria'],
        'Conf_CorFundo'       => $_POST['Conf_CorFundo'],
        'Conf_ImgLogo'        => $_POST['Conf_ImgLogo'],
        'Conf_PastaCarrossel' => $_POST['Conf_PastaCarrossel'],
        'Conf_PastaCard'      => $_POST['Conf_PastaCard'],
        'Conf_PastaImg'       => $_POST['Conf_PastaImg'],
        'Conf_ImgPontoMapa'   => $_POST['Conf_ImgPontoMapa'],
        'Conf_MainPage'       => $_POST['Conf_MainPage'],
        'Conf_Versao'         => $_POST['Conf_Versao']
    ];

    if ($id) {
        // UPDATE
        $sql = "UPDATE sunnyempresas SET 
                EmpresaSunny=:EmpresaSunny, CNPJ=:CNPJ, Endereco=:Endereco, Cidade=:Cidade, UF=:UF, 
                Responsavel1=:Responsavel1, Email1=:Email1, Telefone1=:Telefone1, Obs=:Obs, 
                Conf_CorPrimaria=:Conf_CorPrimaria, Conf_CorSecundaria=:Conf_CorSecundaria, 
                Conf_CorFundo=:Conf_CorFundo, Conf_ImgLogo=:Conf_ImgLogo, Conf_PastaCarrossel=:Conf_PastaCarrossel, 
                Conf_PastaCard=:Conf_PastaCard, Conf_PastaImg=:Conf_PastaImg, Conf_ImgPontoMapa=:Conf_ImgPontoMapa, 
                Conf_MainPage=:Conf_MainPage, Conf_Versao=:Conf_Versao 
                WHERE IdEmp = :id";
        $dados['id'] = $id;
    } else {
        // INSERT
        $sql = "INSERT INTO sunnyempresas 
                (EmpresaSunny, CNPJ, Endereco, Cidade, UF, Responsavel1, Email1, Telefone1, Obs, 
                Conf_CorPrimaria, Conf_CorSecundaria, Conf_CorFundo, Conf_ImgLogo, Conf_PastaCarrossel, 
                Conf_PastaCard, Conf_PastaImg, Conf_ImgPontoMapa, Conf_MainPage, Conf_Versao) 
                VALUES 
                (:EmpresaSunny, :CNPJ, :Endereco, :Cidade, :UF, :Responsavel1, :Email1, :Telefone1, :Obs, 
                :Conf_CorPrimaria, :Conf_CorSecundaria, :Conf_CorFundo, :Conf_ImgLogo, :Conf_PastaCarrossel, 
                :Conf_PastaCard, :Conf_PastaImg, :Conf_ImgPontoMapa, :Conf_MainPage, :Conf_Versao)";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt->execute($dados)) {
        $mensagem = "Dados salvos com sucesso!";
        $tipoMsg = "success";
    } else {
        $mensagem = "Erro ao salvar os dados.";
        $tipoMsg = "danger";
    }
}

// 3. BUSCA PARA EDIÇÃO
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM sunnyempresas WHERE IdEmp = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. LISTAGEM GERAL
$lista = $conn->query("SELECT * FROM sunnyempresas ORDER BY IdEmp DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SunnyImob - Gestão de Empresas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; padding: 20px; }
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .table-responsive { background: white; border-radius: 10px; padding: 15px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-building text-primary"></i> Cadastro de Empresas Sunny</h2>
        <a href="mainpage_v66a.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMsg ?> alert-dismissible fade show" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 <?= isset($_GET['edit']) || isset($_GET['new']) ? '' : 'd-none' ?>" id="formEmpresa">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><?= $editData ? 'Editar Empresa: ' . $editData['EmpresaSunny'] : 'Nova Empresa' ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="sunny_empresas1.php">
                <input type="hidden" name="IdEmp" value="<?= $editData['IdEmp'] ?? '' ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" class="form-control" name="EmpresaSunny" value="<?= $editData['EmpresaSunny'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CNPJ</label>
                        <input type="text" class="form-control" name="CNPJ" value="<?= $editData['CNPJ'] ?? '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Responsável</label>
                        <input type="text" class="form-control" name="Responsavel1" value="<?= $editData['Responsavel1'] ?? '' ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Endereço</label>
                        <input type="text" class="form-control" name="Endereco" value="<?= $editData['Endereco'] ?? '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cidade</label>
                        <input type="text" class="form-control" name="Cidade" value="<?= $editData['Cidade'] ?? '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UF</label>
                        <input type="text" class="form-control" name="UF" maxlength="2" value="<?= $editData['UF'] ?? '' ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="Email1" value="<?= $editData['Email1'] ?? '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control" name="Telefone1" value="<?= $editData['Telefone1'] ?? '' ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="Obs" rows="2"><?= $editData['Obs'] ?? '' ?></textarea>
                    </div>

                    <hr>
                    <h6 class="text-muted">Configurações e Layout</h6>
                    
                    <div class="col-md-2">
                        <label class="form-label">Cor Primária</label>
                        <input type="color" class="form-control form-control-color w-100" name="Conf_CorPrimaria" value="<?= $editData['Conf_CorPrimaria'] ?? '#004d99' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cor Secundária</label>
                        <input type="color" class="form-control form-control-color w-100" name="Conf_CorSecundaria" value="<?= $editData['Conf_CorSecundaria'] ?? '#ffffff' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Logo (Nome arquivo)</label>
                        <input type="text" class="form-control" name="Conf_ImgLogo" value="<?= $editData['Conf_ImgLogo'] ?? '' ?>" placeholder="logo.jpg">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Versão Sistema</label>
                        <input type="text" class="form-control" name="Conf_Versao" value="<?= $editData['Conf_Versao'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Pasta Carrossel</label>
                        <input type="text" class="form-control" name="Conf_PastaCarrossel" value="<?= $editData['Conf_PastaCarrossel'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pasta Card</label>
                        <input type="text" class="form-control" name="Conf_PastaCard" value="<?= $editData['Conf_PastaCard'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pasta Imagem</label>
                        <input type="text" class="form-control" name="Conf_PastaImg" value="<?= $editData['Conf_PastaImg'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Main Page</label>
                        <input type="text" class="form-control" name="Conf_MainPage" value="<?= $editData['Conf_MainPage'] ?? '' ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="btn_salvar" class="btn btn-success"><i class="fas fa-save"></i> Salvar Dados</button>
                    <a href="sunny_empresas1.php" class="btn btn-light border">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive shadow-sm">
        <div class="d-flex justify-content-between mb-3">
            <h5 class="text-muted">Empresas Cadastradas</h5>
            <a href="?new=1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nova Empresa</a>
        </div>
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>CNPJ</th>
                    <th>Cidade/UF</th>
                    <th>Responsável</th>
                    <th>Versão</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $row): ?>
                <tr>
                    <td><?= $row['IdEmp'] ?></td>
                    <td><strong><?= $row['EmpresaSunny'] ?></strong></td>
                    <td><?= $row['CNPJ'] ?></td>
                    <td><?= $row['Cidade'] ?>/<?= $row['UF'] ?></td>
                    <td><?= $row['Responsavel1'] ?></td>
                    <td><span class="badge bg-info text-dark"><?= $row['Conf_Versao'] ?></span></td>
                    <td class="text-center">
                        <a href="?edit=<?= $row['IdEmp'] ?>" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a>
                        <a href="?delete=<?= $row['IdEmp'] ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lista)): ?>
                <tr><td colspan="7" class="text-center">Nenhum registro encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Inclui a conexão PDO
require_once 'conn.php';

session_start();

// Função para converter valor de checkbox
function getCheckboxValue($valor) {
    return ($valor == 1) ? 1 : 0;
}

// Função para registrar histórico de alterações
function registrarHistorico($conn, $idEmpreend, $idEmpresa, $acao) {
    try {
        $userId = $_SESSION['UserId'] ?? 0;
        $usuario = $_SESSION['usuario'] ?? 'Sistema';
        
        $sql = "INSERT INTO histatulog (IdEmpresa, IdEmpreend, IdUser, Usuario, Historico) 
                VALUES (:idEmpresa, :idEmpreend, :idUser, :usuario, :historico)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':idEmpresa' => $idEmpresa,
            ':idEmpreend' => $idEmpreend,
            ':idUser'    => $userId,
            ':usuario'   => $usuario,
            ':historico' => $acao
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar histórico: " . $e->getMessage());
        return false;
    }
}

$sucesso = false;
$idEmpreend = $_REQUEST['id'] ?? '';

if (empty($idEmpreend)) {
    die("<div class='alert alert-danger'>Erro: ID do empreendimento não fornecido.</div>");
}

// --- PROCESSAMENTO DO UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btnAtualizar'])) {
    try {
        $empresaID = $_POST['EmpresaID'] ?? '';
        $nome = strtoupper(trim($_POST['Nome'] ?? 'X'));
        $descricao = strtoupper(trim($_POST['Descricao'] ?? 'X'));
        
        $sql = "UPDATE empreendimento SET 
                Empresa_Id = :emp_id, NomeEmpreendimento = :nome, Descricao = :desc,
                LinkMapa = :mapa, LinkFicha = :ficha, LinkSite = :site,
                LinkVideo = :video, LinkTocca = :tocca, LinkConstrutora = :const,
                LinkTabela = :tab, RespCadastro = :resp, Carteira = :cart,
                Vendido = :vend, PercentualVendido = :perc, EtapaObra = :etapa, DataTabela = :dt
                WHERE Empreend_ID = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':emp_id' => $empresaID, 
            ':nome'   => $nome, 
            ':desc'   => $descricao,
            ':mapa'   => substr(trim($_POST['LinkMapa'] ?? ''), 0, 255),
            ':ficha'  => substr(trim($_POST['LinkFicha'] ?? ''), 0, 255),
            ':site'   => substr(trim($_POST['LinkSite'] ?? ''), 0, 255),
            ':video'  => substr(trim($_POST['LinkVideo'] ?? ''), 0, 255),
            ':tocca'  => substr(trim($_POST['LinkTocca'] ?? ''), 0, 255),
            ':const'  => substr(trim($_POST['LinkConstrutora'] ?? ''), 0, 255),
            ':tab'    => substr(trim($_POST['LinkTabela'] ?? ''), 0, 255),
            ':resp'   => $_POST['RespCadastro'] ?? '',
            ':cart'   => getCheckboxValue($_POST['Carteira'] ?? 0),
            ':vend'   => getCheckboxValue($_POST['Vendido'] ?? 0),
            ':perc'   => $_POST['PercentualVendido'] ?? 0,
            ':etapa'  => $_POST['EtapaObra'] ?? 'Ausente',
            ':dt'     => $_POST['DataTabela'] ?? date('Y-m-d'),
            ':id'     => $idEmpreend
        ]);

        registrarHistorico($conn, $idEmpreend, $empresaID, "Atualização do Cadastro");
        $sucesso = true;
        
    } catch (PDOException $e) {
        die("Erro ao atualizar: " . $e->getMessage());
    }
}

// --- BUSCA DE DADOS ---
try {
    $stmt = $conn->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = ?");
    $stmt->execute([$idEmpreend]);
    $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empreendimento) die("Empreendimento não encontrado.");

    $stmtEmp = $conn->query("SELECT Empresa_Id, NomeEmpresa FROM empresa ORDER BY NomeEmpresa");
    $empresas = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

    $idEmpresaSalva = $empreendimento['Empresa_Id'] ?? $empreendimento['Empresa_ID'] ?? null;
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empreendimento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --bordo: #800020; }
        body { background-color: #f8f9fa; }
        .card-header-custom { background-color: var(--bordo); color: white; }
        .btn-custom { background-color: var(--bordo); color: white; border: none; }
        .btn-custom:hover { background-color: #4B0C1D; color: white; }
    </style>
</head>
<body>
    <div class="container py-4">
        
        <?php if ($sucesso): ?>
        <script>
            Swal.fire({
                title: 'Sucesso!',
                text: 'O registro foi salvo com sucesso.',
                icon: 'success',
                confirmButtonText: 'Fechar Aba',
                confirmButtonColor: '#800020'
            }).then((result) => { if (result.isConfirmed) { window.close(); } });
        </script>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fas fa-building me-2"></i>Editar Empreendimento - ID: <?= htmlspecialchars($idEmpreend) ?></h3>
                <button type="button" onclick="window.close()" class="btn btn-sm btn-light">FECHAR ABA</button>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($idEmpreend) ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Empresa *</label>
                        <select class="form-select" name="EmpresaID" required>
                            <option value="">Selecione uma empresa</option>
                            <?php foreach ($empresas as $empresa): 
                                $selected = (trim($empresa['Empresa_Id']) == trim($idEmpresaSalva)) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($empresa['Empresa_Id']) ?>" <?= $selected ?>>
                                    <?= strtoupper(htmlspecialchars($empresa['NomeEmpresa'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nome Empreendimento *</label>
                        <input type="text" class="form-control text-uppercase" name="Nome" value="<?= htmlspecialchars($empreendimento['NomeEmpreendimento']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Descrição</label>
                        <textarea class="form-control text-uppercase" name="Descricao" rows="3"><?= htmlspecialchars($empreendimento['Descricao']) ?></textarea>
                    </div>

                    <div class="card mb-4 bg-light">
                        <div class="card-header fw-bold text-muted">LINKS E DOCUMENTOS (Máx 255 carac.)</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="small">Link Mapa</label>
                                    <input type="text" class="form-control" name="LinkMapa" value="<?= htmlspecialchars($empreendimento['LinkMapa'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small">Link Ficha</label>
                                    <input type="text" class="form-control" name="LinkFicha" value="<?= htmlspecialchars($empreendimento['LinkFicha'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small">Link Site</label>
                                    <input type="text" class="form-control" name="LinkSite" value="<?= htmlspecialchars($empreendimento['LinkSite'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small">Link Vídeo</label>
                                    <input type="text" class="form-control" name="LinkVideo" value="<?= htmlspecialchars($empreendimento['LinkVideo'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small">Link Tocca</label>
                                    <input type="text" class="form-control" name="LinkTocca" value="<?= htmlspecialchars($empreendimento['LinkTocca'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small">Link Construtora</label>
                                    <input type="text" class="form-control" name="LinkConstrutora" value="<?= htmlspecialchars($empreendimento['LinkConstrutora'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small">Link Tabela</label>
                                    <input type="text" class="form-control" name="LinkTabela" value="<?= htmlspecialchars($empreendimento['LinkTabela'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="small">Data da Tabela</label>
                                    <input type="date" class="form-control" name="DataTabela" value="<?= $empreendimento['DataTabela'] ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header fw-bold">DISPONIBILIDADE</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="Carteira" name="Carteira" value="1" <?= ($empreendimento['Carteira'] == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="Carteira">Carteira</label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="Vendido" name="Vendido" value="1" <?= ($empreendimento['Vendido'] == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="Vendido">Vendido</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="small">Percentual Vendido (%)</label>
                                    <input type="number" class="form-control" name="PercentualVendido" min="0" max="100" value="<?= $empreendimento['PercentualVendido'] ?>">
                                </div>
                                <div class="col-md-12 mt-3">
                                    <label class="small">Etapa da Obra</label>
                                    <select class="form-select" name="EtapaObra">
                                        <?php 
                                        $etapas = ['Lançamento', 'Em construção', 'Pronto', 'Entregue', 'Obra parada'];
                                        foreach($etapas as $e): ?>
                                            <option value="<?= $e ?>" <?= ($empreendimento['EtapaObra'] == $e) ? 'selected' : '' ?>><?= $e ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small">Responsável pelo Cadastro</label>
                        <input type="text" class="form-control" name="RespCadastro" value="<?= htmlspecialchars($empreendimento['RespCadastro']) ?>">
                    </div>

                    <div class="d-flex justify-content-between mt-5">
                        <button type="button" onclick="window.close()" class="btn btn-secondary px-4">FECHAR SEM SALVAR</button>
                        <button type="submit" name="btnAtualizar" class="btn btn-custom px-5 fw-bold">
                            <i class="fas fa-save me-2"></i>SALVAR REGISTRO
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
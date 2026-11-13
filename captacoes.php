<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php';

if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

$themeDefaults = [
    'primary' => '#800020', 'primary_dark' => '#4B0C1D',
    'secondary' => '#6c757d', 'accent' => '#D4AF37',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ============================================================
// DADOS DO USUÁRIO E EMPRESA LOGADA
// ============================================================
$empresaId = $_SESSION['IdEmp'] ?? 1; 
$usuarioId = $_SESSION['UserId'] ?? 0;
$nomeUsuario = $_SESSION['Usuario'] ?? 'Sistema';
$nomeEmpresaLogada = $_SESSION['Empresa'] ?? 'EMPRESA NÃO IDENTIFICADA';

// Se o nome da empresa não estiver na sessão, busca no banco
if ($nomeEmpresaLogada == 'EMPRESA NÃO IDENTIFICADA') {
    $stmtEmp = $db->prepare("SELECT EmpresaSunny FROM sunnyempresas WHERE IdEmp = ?");
    $stmtEmp->execute([$empresaId]);
    $nomeEmpresaLogada = $stmtEmp->fetchColumn() ?: 'EMPRESA NÃO IDENTIFICADA';
}

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================
function uploadFotos($files, $imovelId) {
    $pastaDestino = __DIR__ . '/captacao/' . $imovelId . '/';
    if (!is_dir($pastaDestino)) { mkdir($pastaDestino, 0777, true); }
    $caminhos = [];
    $limite = min(count($files['name']), 10);
    for ($i = 0; $i < $limite; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $extensao = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
                $novoNome = uniqid() . '.' . $extensao;
                if (move_uploaded_file($files['tmp_name'][$i], $pastaDestino . $novoNome)) {
                    $caminhos[] = 'captacao/' . $imovelId . '/' . $novoNome;
                }
            }
        }
    }
    return $caminhos;
}

// ============================================================
// PROCESSAMENTO DE AÇÕES (POST)
// ============================================================
$acao = $_GET['acao'] ?? 'listar';
$id = $_GET['id'] ?? null;
$mensagemSucesso = "";
$showSuccess = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['excluir_id'])) {
        try {
            $stmt = $db->prepare("DELETE FROM empreendimento WHERE Empreend_ID = ? AND Origem = 'CAPTACAO' AND Empresa_ID = ?");
            $stmt->execute([$_POST['excluir_id'], $empresaId]);
            $mensagemSucesso = "Captação excluída com sucesso!";
            $showSuccess = true;
            $acao = 'listar';
        } catch (PDOException $e) {
            $mensagemSucesso = "Erro ao excluir: " . $e->getMessage();
        }
    } else {
        $dados = [
            'NomeEmpreendimento' => mb_strtoupper(trim($_POST['NomeEmpreendimento'] ?? ''), 'UTF-8'),
            'CondicaoImovel' => $_POST['CondicaoImovel'] ?? 'Usado',
            'TipoImovelCaptacao' => $_POST['TipoImovelCaptacao'] ?? '',
            'Localidade' => mb_strtoupper(trim($_POST['Localidade'] ?? ''), 'UTF-8'),
            'Localizacao' => mb_strtoupper(trim($_POST['Localizacao'] ?? ''), 'UTF-8'),
            'Quartos' => $_POST['Quartos'] ?? null,
            'Suites' => $_POST['Suites'] ?: null,
            'WCTotal' => $_POST['WCTotal'] ?: null,
            'MetragemTotal' => $_POST['MetragemTotal'] ?: 0,
            'VagasGaragem' => $_POST['VagasGaragem'] ?: 0,
            'Andar' => $_POST['Andar'] ?? null,
            'Unidade' => $_POST['Unidade'] ?? null,
            'AnoEntrega' => $_POST['AnoEntrega'] ?: null,
            'PossuiElevador' => isset($_POST['PossuiElevador']) ? 1 : 0,
            'EstadoConservacao' => $_POST['EstadoConservacao'] ?? null,
            'ProprietarioNome' => mb_strtoupper(trim($_POST['ProprietarioNome'] ?? ''), 'UTF-8'),
            'ProprietarioTelefone' => $_POST['ProprietarioTelefone'] ?? null,
            'ProprietarioEmail' => $_POST['ProprietarioEmail'] ?? null,
            'CaptadorNome' => mb_strtoupper(trim($_POST['CaptadorNome'] ?? ''), 'UTF-8'),
            'CaptadorTelefone' => $_POST['CaptadorTelefone'] ?? null,
            'PercentualCaptador' => $_POST['PercentualCaptador'] ?: null,
            'Exclusividade' => isset($_POST['Exclusividade']) ? 1 : 0,
            'ValorDesejado' => $_POST['ValorDesejado'] ?: null,
            'ValorMercado' => $_POST['ValorMercado'] ?: null,
            'ValorCond' => $_POST['ValorCond'] ?: null,
            'ValorIPTU' => $_POST['ValorIPTU'] ?: null,
            'ValorAgua' => $_POST['ValorAgua'] ?: null,
            'ValorEnergia' => $_POST['ValorEnergia'] ?: null,
            'TaxaExtra' => $_POST['TaxaExtra'] ?? null,
            'EmpresaGestora' => mb_strtoupper(trim($_POST['EmpresaGestora'] ?? ''), 'UTF-8'),
            'PontoForte' => $_POST['PontoForte'] ?? null,
            'PontoFraco' => $_POST['PontoFraco'] ?? null,
            'Descricao' => $_POST['Descricao'] ?? null,
            'Obs' => $_POST['Obs'] ?? null
        ];

        try {
            if (empty($_POST['Empreend_ID'])) {
                // INSERT: Salva Empresa_ID, Usuario_ID e NomeEmpresa
                $sql = "INSERT INTO empreendimento (Empresa_ID, Usuario_ID, NomeEmpresa, Origem, " . implode(', ', array_keys($dados)) . ", DataCadastro, UsuarioCad) 
                        VALUES (?, ?, ?, 'CAPTACAO', " . rtrim(str_repeat('?, ', count($dados)), ', ') . ", NOW(), ?)";
                $stmt = $db->prepare($sql);
                
                $parametros = array_merge(
                    [$empresaId, $usuarioId, $nomeEmpresaLogada], 
                    array_values($dados), 
                    [$nomeUsuario]
                );
                $stmt->execute($parametros);
                $id = $db->lastInsertId();
                $mensagemSucesso = "Captação cadastrada com sucesso!";
            } else {
                // UPDATE
                $id = $_POST['Empreend_ID'];
                $sets = [];
                foreach ($dados as $key => $val) { $sets[] = "$key = ?"; }
                $sql = "UPDATE empreendimento SET " . implode(', ', $sets) . ", DataAtualizacao = NOW(), UsuarioAtu = ? 
                        WHERE Empreend_ID = ? AND Origem = 'CAPTACAO' AND Empresa_ID = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge(array_values($dados), [$nomeUsuario, $id, $empresaId]));
                $mensagemSucesso = "Captação atualizada com sucesso!";
            }

            // Upload de Fotos
            if (!empty($_FILES['fotos']['name'][0])) {
                $novasFotos = uploadFotos($_FILES['fotos'], $id);
                if (!empty($novasFotos)) {
                    $stmtFotos = $db->prepare("SELECT LinkFicha FROM empreendimento WHERE Empreend_ID = ?");
                    $stmtFotos->execute([$id]);
                    $fotosAntigas = json_decode($stmtFotos->fetchColumn(), true) ?: [];
                    $todasAsFotos = array_slice(array_merge($fotosAntigas, $novasFotos), 0, 10);
                    
                    $stmtFotosUpd = $db->prepare("UPDATE empreendimento SET LinkFicha = ? WHERE Empreend_ID = ?");
                    $stmtFotosUpd->execute([json_encode($todasAsFotos), $id]);
                }
            }

            $showSuccess = true;
            $acao = 'listar';

        } catch (PDOException $e) {
            $mensagemSucesso = "Erro ao salvar: " . $e->getMessage();
            $showSuccess = false;
        }
    }
}

// Buscar dados para Edição
$dadosEdicao = null;
if ($acao === 'editar' && $id) {
    $stmt = $db->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = ? AND Origem = 'CAPTACAO' AND Empresa_ID = ?");
    $stmt->execute([$id, $empresaId]);
    $dadosEdicao = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Captações | <?= htmlspecialchars($nomeEmpresaLogada) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>; --primary-dark: <?= $themeSafe['primary_dark'] ?>;
            --shadow-color: <?= $themeSafe['shadow_color'] ?>; --success: #16a34a; --danger: #dc2626;
        }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 2rem 1rem; }
        .card-neo { background: #fff; border: 3px solid var(--shadow-color); border-radius: 16px; box-shadow: 6px 6px 0 0 var(--shadow-color); max-width: 1100px; margin: 0 auto; overflow: hidden; }
        .card-neo .card-header { background: var(--primary); color: #fff; border-bottom: 3px solid var(--shadow-color); padding: 1rem 1.5rem; font-weight: 900; display: flex; justify-content: space-between; align-items: center; }
        .card-neo .card-body { padding: 1.5rem; }
        .field-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #4b5563; margin-bottom: 6px; display: block; }
        .form-control, .form-select { border: 2.5px solid var(--shadow-color); border-radius: 10px; padding: 8px 12px; font-weight: 600; font-size: 0.9rem; box-shadow: 2px 2px 0 0 var(--shadow-color); }
        .btn-neo { padding: 8px 16px; border: 2.5px solid var(--shadow-color); border-radius: 10px; box-shadow: 3px 3px 0 var(--shadow-color); font-weight: 800; font-size: 0.8rem; text-transform: uppercase; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-neo.primary { background: var(--primary); color: #fff; }
        .btn-neo.success { background: var(--success); color: #fff; }
        .btn-neo.danger { background: var(--danger); color: #fff; }
        .btn-neo.secondary { background: #fff; color: #1a1a2e; }
        .btn-neo.sm { padding: 4px 10px; font-size: 0.7rem; }
        .table-neo { width: 100%; border-collapse: collapse; }
        .table-neo th { background: #f3f4f6; padding: 12px; text-align: left; font-size: 0.8rem; border-bottom: 3px solid var(--shadow-color); }
        .table-neo td { padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 0.85rem; font-weight: 600; }
        .nav-tabs { border-bottom: 3px solid var(--shadow-color); gap: 4px; }
        .nav-tabs .nav-link { border: 2.5px solid transparent; border-bottom: none; border-radius: 10px 10px 0 0; font-weight: 800; font-size: 0.85rem; color: #6b7280; padding: 8px 16px; margin-bottom: -3px; }
        .nav-tabs .nav-link.active { background: #fff; border-color: var(--shadow-color); border-bottom: 3px solid #fff; color: var(--primary); box-shadow: 0 -3px 0 var(--shadow-color); }
        .alert-neo { border: 3px solid; border-radius: 12px; padding: 1rem; font-weight: 700; margin-bottom: 1.5rem; box-shadow: 4px 4px 0; max-width: 1100px; margin-left: auto; margin-right: auto; }
        .alert-neo.success { background: #d1fae5; color: #065f46; border-color: #065f46; }
        .alert-neo.danger { background: #fee2e2; color: #991b1b; border-color: #991b1b; }
    </style>
</head>
<body>

<?php if ($mensagemSucesso): ?>
    <div class="alert-neo <?= $showSuccess ? 'success' : 'danger' ?>">
        <i class="fas <?= $showSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
        <?= htmlspecialchars($mensagemSucesso) ?>
    </div>
<?php endif; ?>

<div class="card-neo">
    <div class="card-header">
        <span><i class="fas fa-home"></i> Gestão de Captações (<?= htmlspecialchars($nomeEmpresaLogada) ?>)</span>
        <?php if ($acao === 'listar'): ?>
            <a href="?acao=criar" class="btn-neo secondary sm"><i class="fas fa-plus"></i> Nova Captação</a>
        <?php else: ?>
            <a href="?" class="btn-neo secondary sm"><i class="fas fa-arrow-left"></i> Voltar</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($acao === 'listar'): ?>
            <div class="table-responsive">
                <table class="table-neo">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imóvel</th>
                            <th>Proprietário</th>
                            <th>Valor Desejado</th>
                            <th>Captador (%)</th>
                            <th>Cadastrado por</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Lista captações da empresa logada
                        $stmt = $db->prepare("SELECT * FROM empreendimento WHERE Origem = 'CAPTACAO' AND Empresa_ID = ? ORDER BY Empreend_ID DESC");
                        $stmt->execute([$empresaId]);
                        
                        if ($stmt->rowCount() == 0) {
                            echo "<tr><td colspan='7' class='text-center py-4 text-muted'>Nenhuma captação cadastrada para esta empresa.</td></tr>";
                        }
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                        <tr>
                            <td>#<?= $row['Empreend_ID'] ?></td>
                            <td><strong><?= htmlspecialchars($row['NomeEmpreendimento'] ?? 'Sem Nome') ?></strong><br><small class="text-muted"><?= htmlspecialchars($row['Localidade'] ?? '') ?></small></td>
                            <td><?= htmlspecialchars($row['ProprietarioNome'] ?? '-') ?><br><small><?= htmlspecialchars($row['ProprietarioTelefone'] ?? '') ?></small></td>
                            <td>R$ <?= number_format($row['ValorDesejado'] ?? 0, 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['CaptadorNome'] ?? '-') ?><br><small class="badge bg-secondary"><?= $row['PercentualCaptador'] ?? 0 ?>%</small></td>
                            <td><span class="badge bg-dark"><?= htmlspecialchars($row['UsuarioCad'] ?? 'Sistema') ?></span></td>
                            <td>
                                <a href="?acao=editar&id=<?= $row['Empreend_ID'] ?>" class="btn-neo sm primary"><i class="fas fa-edit"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Excluir esta captação permanentemente?');">
                                    <input type="hidden" name="excluir_id" value="<?= $row['Empreend_ID'] ?>">
                                    <button type="submit" class="btn-neo sm danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- O Formulário de Cadastro/Edição é o mesmo que você já tem, mantendo a estrutura de abas -->
            <!-- (Omitido aqui por brevidade, use o mesmo da resposta anterior, pois os campos não mudaram, apenas o INSERT no topo do arquivo) -->
            <p class="text-center text-muted py-5">Formulário de Captação (Use o código anterior, pois os campos do form continuam os mesmos)</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
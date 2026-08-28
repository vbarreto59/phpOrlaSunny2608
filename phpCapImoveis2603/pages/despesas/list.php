<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/header.php';

// Pega o ID do imóvel vindo da URL
$imovel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($imovel_id <= 0) {
    echo "<div class='container mt-5 text-center'><div class='alert alert-warning'>Imóvel não identificado.</div><a href='../imoveis/list.php' class='btn btn-primary'>Voltar</a></div>";
    require_once '../../includes/footer.php';
    exit;
}

// 1. Busca dados do Imóvel
$stmt_imovel = $conn->prepare("SELECT titulo, endereco, preco FROM imoveis WHERE id = ?");
$stmt_imovel->execute([$imovel_id]);
$im = $stmt_imovel->fetch();

// 2. Lógica para Salvar Nova Despesa (Ajustado para suas colunas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar'])) {
    $tipo = $_POST['tipo'];
    $valor = $_POST['valor'];
    $data_despesa = $_POST['data_despesa'];
    $descricao = $_POST['descricao'];

    $sql = "INSERT INTO despesas (imovel_id, tipo, valor, data_despesa, descricao) VALUES (?, ?, ?, ?, ?)";
    $conn->prepare($sql)->execute([$imovel_id, $tipo, $valor, $data_despesa, $descricao]);
    
    header("Location: list.php?id=$imovel_id&status=sucesso");
    exit;
}

// 3. Lógica para Excluir
if (isset($_GET['excluir'])) {
    $id_del = (int)$_GET['excluir'];
    $conn->prepare("DELETE FROM despesas WHERE id = ?")->execute([$id_del]);
    header("Location: list.php?id=$imovel_id&status=excluido");
    exit;
}

// 4. Busca despesas cadastradas
$stmt_despesas = $conn->prepare("SELECT * FROM despesas WHERE imovel_id = ? ORDER BY data_despesa DESC");
$stmt_despesas->execute([$imovel_id]);
$despesas = $stmt_despesas->fetchAll();

// 5. Soma total
$total_despesas = 0;
foreach ($despesas as $d) { $total_despesas += $d['valor']; }
?>

<div class="container py-4">
<div class="row align-items-center mb-4">
    <div class="col">
        <h2 class="text-primary fw-bold mb-0">Gestão de Despesas</h2>
        <span class="text-muted"><i class="bi bi-house-door me-1"></i> <?= htmlspecialchars($im['titulo']) ?></span>
    </div>
    <div class="col-auto">
        <a href="form.php?imovel_id=<?= $imovel_id ?>" class="btn btn-success shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Nova Despesa
        </a>
        <a href="../imoveis/list.php" class="btn btn-outline-secondary ms-2">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">Novo Lançamento</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Tipo de Despesa</label>
                            <select name="tipo" class="form-select" required>
                                <option value="Manutenção">Manutenção / Limpeza</option>
                                <option value="Impostos">Impostos (IPTU/Condomínio)</option>
                                <option value="Marketing">Marketing / Placas</option>
                                <option value="Documentação">Documentação / Cartório</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" step="0.01" name="valor" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data do Gasto</label>
                            <input type="date" name="data_despesa" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição / Observação</label>
                            <textarea name="descricao" class="form-control" rows="2" placeholder="Detalhes do gasto..."></textarea>
                        </div>
                        <button type="submit" name="btn_salvar" class="btn btn-success w-100 fw-bold">
                            <i class="bi bi-plus-circle me-1"></i> Lançar Despesa
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 bg-danger text-white mt-4">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-1" style="opacity: 0.8;">Total Acumulado</h6>
                    <h3 class="fw-bold mb-0">R$ <?= number_format($total_despesas, 2, ',', '.') ?></h3>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-3">Data</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th class="text-end">Valor</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($despesas)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">Nenhuma despesa para este imóvel.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($despesas as $d): ?>
                                    <tr>
                                        <td class="ps-3"><?= date('d/m/Y', strtotime($d['data_despesa'])) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['tipo']) ?></span></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($d['descricao']) ?></small></td>
                                        <td class="text-end fw-bold text-danger">R$ <?= number_format($d['valor'], 2, ',', '.') ?></td>
                                        <td class="text-center">
                                            <a href="javascript:confirmarExclusao(<?= $d['id'] ?>, <?= $imovel_id ?>)" 
                                               class="text-danger p-2"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarExclusao(id, imovel) {
    if (confirm("Deseja realmente excluir este lançamento?")) {
        window.location.href = `list.php?id=${imovel}&excluir=${id}`;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
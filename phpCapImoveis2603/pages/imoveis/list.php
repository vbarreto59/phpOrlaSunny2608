<?php
session_start();

require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// Filtros (busca, bairro, preço, status, tipo)
// ================================================
$where = "WHERE i.deleted_at IS NULL";
$params = [];

if (!empty($_GET['busca'])) {
    $busca = '%' . trim($_GET['busca']) . '%';
    $where .= " AND (i.titulo LIKE ? OR i.bairro LIKE ? OR i.cidade LIKE ? OR i.endereco LIKE ? OR i.descricao LIKE ?)";
    $params = [$busca, $busca, $busca, $busca, $busca];
}

if (!empty($_GET['bairro'])) {
    $where .= " AND i.bairro LIKE ?";
    $params[] = '%' . trim($_GET['bairro']) . '%';
}

if (!empty($_GET['preco_min'])) {
    $where .= " AND i.preco >= ?";
    $params[] = (float) $_GET['preco_min'];
}

if (!empty($_GET['preco_max'])) {
    $where .= " AND i.preco <= ?";
    $params[] = (float) $_GET['preco_max'];
}

if (!empty($_GET['status'])) {
    $where .= " AND i.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['tipo'])) {
    $where .= " AND i.tipo = ?";
    $params[] = $_GET['tipo'];
}

// Consulta principal
$sql = "
    SELECT 
        i.id,
        i.titulo,
        i.endereco,
        i.bairro,
        i.cidade,
        i.estado,
        i.preco,
        i.quartos,
        i.banheiros,
        i.area,
        i.tipo,
        i.status,
        i.created_at,
        (SELECT caminho FROM fotos_imoveis WHERE imovel_id = i.id LIMIT 1) AS foto_principal
    FROM imoveis i
    $where
    ORDER BY i.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../../includes/header.php'; ?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h1 class="text-primary mb-1">Imóveis Captados</h1>
        <p class="text-muted lead">Todos os imóveis registrados no sistema</p>
    </div>
    <div class="col-auto">
        <a href="form.php" class="btn btn-primary btn-lg shadow">
            <i class="bi bi-plus-circle-fill me-2"></i>Novo Imóvel
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Busca geral</label>
                <input type="text" name="busca" class="form-control" 
                       value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>" 
                       placeholder="Título, bairro, endereço...">
            </div>

            <div class="col-md-3">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" class="form-control" 
                       value="<?= htmlspecialchars($_GET['bairro'] ?? '') ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Preço mín. (R$)</label>
                <input type="number" name="preco_min" class="form-control" 
                       value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Preço máx. (R$)</label>
                <input type="number" name="preco_max" class="form-control" 
                       value="<?= htmlspecialchars($_GET['preco_max'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="captado" <?= ($_GET['status']??'')==='captado'?'selected':'' ?>>Captado</option>
                    <option value="em_negociacao" <?= ($_GET['status']??'')==='em_negociacao'?'selected':'' ?>>Em negociação</option>
                    <option value="vendido" <?= ($_GET['status']??'')==='vendido'?'selected':'' ?>>Vendido</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="casa" <?= ($_GET['tipo']??'')==='casa'?'selected':'' ?>>Casa</option>
                    <option value="apartamento" <?= ($_GET['tipo']??'')==='apartamento'?'selected':'' ?>>Apartamento</option>
                    <option value="terreno" <?= ($_GET['tipo']??'')==='terreno'?'selected':'' ?>>Terreno</option>
                    <option value="comercial" <?= ($_GET['tipo']??'')==='comercial'?'selected':'' ?>>Comercial</option>
                    <option value="outro" <?= ($_GET['tipo']??'')==='outro'?'selected':'' ?>>Outro</option>
                </select>
            </div>

            <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
                <a href="list.php" class="btn btn-outline-secondary px-4 ms-2">Limpar filtros</a>
            </div>
        </form>
    </div>
</div>

<!-- Cards de imóveis -->
<?php if (empty($imoveis)): ?>
    <div class="alert alert-info text-center py-5">
        <h4 class="mb-3">Nenhum imóvel encontrado</h4>
        <p class="mb-0">Tente ajustar os filtros ou cadastre um novo imóvel.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($imoveis as $im): 
            $foto = $im['foto_principal'] ?: 'https://via.placeholder.com/400x260?text=Sem+Foto';
            $preco = $im['preco'] ? number_format($im['preco'], 2, ',', '.') : 'Consulte';
            $status_class = match($im['status']) {
                'captado'       => 'success',
                'em_negociacao' => 'warning',
                'vendido'       => 'danger',
                default         => 'secondary'
            };
        ?>
        <div class="col">
            <div class="card h-100 shadow border-0 hover-shadow">
                <div class="position-relative">
                    <img src="<?= htmlspecialchars($foto) ?>" class="card-img-top" alt="<?= htmlspecialchars($im['titulo']) ?>"
                         style="height: 220px; object-fit: cover;">
                    <span class="position-absolute top-0 end-0 m-3 badge bg-<?= $status_class ?> fs-6 px-3 py-2">
                        <?= ucfirst(str_replace('_', ' ', $im['status'])) ?>
                    </span>
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-primary fw-bold mb-2">
                        <?= htmlspecialchars($im['titulo']) ?>
                    </h5>
                    
                    <p class="text-muted small mb-3">
                        <?= htmlspecialchars($im['bairro']) ?>, <?= htmlspecialchars($im['cidade']) ?> - <?= htmlspecialchars($im['estado']) ?>
                    </p>

                    <p class="fs-4 fw-bold text-dark mb-3">
                        <?= $im['preco'] ? 'R$ ' . $preco : 'Preço sob consulta' ?>
                    </p>

                    <div class="d-flex flex-wrap gap-3 mb-4 text-muted small">
                        <span><i class="bi bi-door-open me-1"></i> <?= $im['quartos'] ?: '–' ?> dorm.</span>
                        <span><i class="bi bi-water me-1"></i> <?= $im['banheiros'] ?: '–' ?> ban.</span>
                        <span><i class="bi bi-rulers me-1"></i> <?= $im['area'] ? $im['area'] . ' m²' : '–' ?></span>
                        <span><i class="bi bi-house-door me-1"></i> <?= ucfirst($im['tipo'] ?: '–') ?></span>
                    </div>

                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <a href="view.php?id=<?= $im['id'] ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-eye me-1"></i> Detalhes
                        </a>
                        <a href="..\despesas\list.php?id=<?= $im['id'] ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-eye me-1"></i> Despesas
                        </a>                        
                        <div>
                            <a href="form.php?id=<?= $im['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <!-- Botão excluir (opcional - com modal) -->
                            <button type="button" class="btn btn-sm btn-danger btn-excluir ms-1" 
                                    data-id="<?= $im['id'] ?>" title="Excluir imóvel">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar exclusão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Deseja realmente excluir este imóvel?<br>
                <strong>Esta ação é irreversível (exclusão lógica).</strong><br><br>
                Fotos, visitas e histórico serão mantidos para consulta futura.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a id="btnConfirmarExcluir" href="#" class="btn btn-danger">Excluir</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-excluir').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            document.getElementById('btnConfirmarExcluir').setAttribute('href', `delete.php?id=${id}`);
            new bootstrap.Modal(document.getElementById('modalExcluir')).show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
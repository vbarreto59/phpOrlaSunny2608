<?php
// pages/proprietarios/list.php
session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// Lógica de Busca e Filtros
// ================================================
$where = "WHERE p.deleted_at IS NULL";
$params = [];

if (!empty($_GET['busca'])) {
    $busca = '%' . trim($_GET['busca']) . '%';
    $where .= " AND (p.nome LIKE ? OR p.telefone LIKE ? OR p.email LIKE ? OR p.cpf LIKE ?)";
    $params = array_fill(0, 4, $busca);
}

// Consulta principal com contagem de imóveis vinculados
$sql = "
    SELECT 
        p.id,
        p.nome,
        p.telefone,
        p.email,
        p.cpf,
        p.endereco,
        p.created_at,
        COUNT(i.id) AS total_imoveis
    FROM proprietarios p
    LEFT JOIN imoveis i ON i.proprietario_id = p.id AND i.deleted_at IS NULL
    $where
    GROUP BY p.id
    ORDER BY p.nome ASC
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $proprietarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar dados: " . $e->getMessage();
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="text-primary"><i class="bi bi-people-fill me-2"></i>Proprietários</h2>
            <p class="text-muted">Gerenciamento de contatos e captações</p>
        </div>
        <div class="col-auto">
            <a href="form.php" class="btn btn-primary btn-lg shadow-sm">
                <i class="bi bi-person-plus-fill me-1"></i> Novo Proprietário
            </a>
        </div>
    </div>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['sucesso']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_GET['erro']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="busca" class="form-control form-control-lg" 
                               value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>" 
                               placeholder="Buscar por nome, telefone, email ou CPF...">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1">Filtrar</button>
                    <?php if (!empty($_GET['busca'])): ?>
                        <a href="list.php" class="btn btn-outline-secondary btn-lg">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tabelaProprietarios" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nome</th>
                            <th>Contato</th>
                            <th>Documento</th>
                            <th>Endereço</th>
                            <th class="text-center">Imóveis</th>
                            <th>Cadastro</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proprietarios)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <img src="../../assets/img/empty.svg" alt="Vazio" style="width: 150px;" class="mb-3 opacity-50"><br>
                                    <span class="text-muted">Nenhum proprietário encontrado para os termos da busca.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($proprietarios as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['nome']) ?></div>
                                        <small class="text-muted">ID: #<?= $p['id'] ?></small>
                                    </td>
                                    <td>
                                        <div><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($p['telefone'] ?: 'N/A') ?></div>
                                        <div class="small text-muted"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($p['email'] ?: 'N/A') ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['cpf'] ?: 'Não Inf.') ?></span></td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars(mb_strimwidth($p['endereco'] ?? '-', 0, 40, "...")) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($p['total_imoveis'] > 0): ?>
                                            <a href="../imoveis/list.php?proprietario_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success rounded-pill">
                                                <strong><?= $p['total_imoveis'] ?></strong> imóvel(is)
                                            </a>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-secondary opacity-75">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm">
                                            <a href="form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-white text-warning border" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-white text-danger border btn-excluir" 
                                                    data-id="<?= $p['id'] ?>" 
                                                    data-nome="<?= htmlspecialchars($p['nome']) ?>"
                                                    data-imoveis="<?= $p['total_imoveis'] ?>"
                                                    title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
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

<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-octagon" style="font-size: 3rem;"></i>
                </div>
                <p class="mb-1">Você está prestes a remover o proprietário:</p>
                <h5 id="nomeProprietarioExcluir" class="fw-bold"></h5>
                <p class="text-muted small mt-3">
                    <strong>Atenção:</strong> Esta é uma exclusão lógica. <br>
                    O registro será marcado como excluído mas permanecerá no banco.
                </p>
                <div id="avisoImoveis" class="alert alert-warning d-none">
                    <i class="bi bi-info-circle me-2"></i>
                    Este proprietário possui imóveis vinculados e não pode ser excluído agora.
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <a id="btnConfirmarExcluir" href="#" class="btn btn-danger px-4">Confirmar Exclusão</a>
            </div>
        </div>
    </div>
</div>

</div> </div> </div> <?php require_once '../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Inicialização do DataTable
    if ($.fn.DataTable) {
        $('#tabelaProprietarios').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json' },
            pageLength: 20,
            order: [[0, 'asc']]
        });
    }

    // Delegação de evento para funcionar em todas as páginas do DataTable
    $(document).on('click', '.btn-excluir', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        const totalImoveis = parseInt($(this).data('imoveis'));
        
        $('#nomeProprietarioExcluir').text(nome);
        
        if (totalImoveis > 0) {
            $('#avisoImoveis').removeClass('d-none');
            $('#btnConfirmarExcluir').addClass('disabled').attr('href', '#');
        } else {
            $('#avisoImoveis').addClass('d-none');
            $('#btnConfirmarExcluir').removeClass('disabled').attr('href', `delete.php?id=${id}`);
        }
        
        // Ativa o modal
        const modalEl = document.getElementById('modalExcluir');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
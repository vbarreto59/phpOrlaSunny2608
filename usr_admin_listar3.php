<?php
// usr_admin_listar3.php - Versão com botão Excluir e Confirmação

// Força exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); 

session_start();
require_once 'conn.php';

$SQL_LOGGING_ENABLED = 1;

function logSQL($sql, $params = [], $userId = 0) {
    global $SQL_LOGGING_ENABLED;
    if (!$SQL_LOGGING_ENABLED) return;
    $logFile = 'sql.log';
    $timestamp = date('Y-m-d H:i:s');
    $msg = "[$timestamp] SQL: $sql | Params: " . json_encode($params);
    file_put_contents($logFile, $msg . PHP_EOL, FILE_APPEND);
}

if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// =============================================
// PROCESSAMENTO POST (TOGGLE E EXCLUSÃO)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $userId = (int)($_POST['User_Id'] ?? 0);
    
    if ($userId > 0) {
        try {
            if ($acao === 'toggle_status') {
                $novoStatus = (int)($_POST['novo_status'] ?? 0);
                $sql = "UPDATE usuarios SET Ativo = ? WHERE UserID = ? AND IdEmp = 2";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$novoStatus, $userId]);
                header("Location: ?success=status_alterado");
                exit();
            } 
            
            // LÓGICA DE EXCLUSÃO
            elseif ($acao === 'excluir') {
                // Inicia transação para garantir que remova das tabelas relacionadas se houver
                $conn->beginTransaction();
                
                // Remove vínculo de grupo primeiro (tabela pivot)
                $stmtGrp = $conn->prepare("DELETE FROM Usuario_Grupo WHERE UserId = ?");
                $stmtGrp->execute([$userId]);
                
                // Remove o usuário
                $stmtUser = $conn->prepare("DELETE FROM usuarios WHERE UserID = ? AND IdEmp = 2");
                $stmtUser->execute([$userId]);
                
                $conn->commit();
                logSQL("USUÁRIO EXCLUÍDO", ['UserID' => $userId]);
                header("Location: ?success=excluido");
                exit();
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            logSQL("ERRO: " . $e->getMessage());
            header("Location: ?error=1&msg=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// CARREGAMENTO DE DADOS
try {
    $stats['totalAtivos'] = $conn->query("SELECT COUNT(*) FROM usuarios WHERE IdEmp = 2 AND Ativo = 1")->fetchColumn() ?: 0;
    $stats['totalVazio']  = $conn->query("SELECT COUNT(*) FROM usuarios WHERE IdEmp = 2 AND Ativo = 1 AND (DiretoriaID IS NULL OR DiretoriaID = 0)")->fetchColumn() ?: 0;

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE IdEmp = 2 ORDER BY Usuario ASC");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>ADMIN - Usuários</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .table-responsive { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 20px; }
        .title-card { background-color: #dc3545; color: white; border-radius: 8px; margin-bottom: 20px; }
        .btn-group-sm > .btn { margin-bottom: 2px; }
        .badge-status { border-radius: 50px; min-width: 70px; }
    </style>
</head>
<body>

<div class="container-fluid py-4">

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check mr-2"></i> Operação realizada com sucesso!
            <button class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="card title-card">
        <div class="card-body text-center">
            <h2 class="mb-0"><i class="fas fa-users-cog mr-2"></i> Gerenciamento de Usuários</h2>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6><i class="fas fa-user-check"></i> Ativos</h6>
                    <p class="h4"><?= $stats['totalAtivos'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-9 text-right pt-4">
            <a href="usr_admin_novo_usuario3.php" class="btn btn-primary btn-lg"><i class="fas fa-plus"></i> Novo Usuário</a>
        </div>
    </div>

    <div class="table-responsive">
        <table id="tabelaUsuarios" class="table table-striped table-bordered table-hover w-100">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Status</th>
                    <th>Função</th>
                    <th>Diretoria/Gerência</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): 
                    $uid = $u['UserID'] ?? $u['UserId'] ?? $u['id'] ?? 0;
                    $ativo = !empty($u['Ativo']);
                ?>
                <tr>
                    <td><strong><?= $uid ?></strong></td>
                    <td><?= htmlspecialchars($u['Usuario']) ?></td>
                    <td>
                        <span class="badge badge-status <?= $ativo ? 'badge-success' : 'badge-danger' ?>">
                            <?= $ativo ? 'ATIVO' : 'INATIVO' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($u['Funcao'] ?? '-') ?></td>
                    <td>
                        <small>
                            D: <?= htmlspecialchars($u['Diretoria'] ?? 'N/D') ?><br>
                            G: <?= htmlspecialchars($u['Gerencia'] ?? 'N/D') ?>
                        </small>
                    </td>
                    <td class="text-center">
                        <div class="btn-group-vertical btn-group-sm">
                            <a href="usr_admin_editar3.php?id=<?= $uid ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="acao" value="toggle_status">
                                <input type="hidden" name="User_Id" value="<?= $uid ?>">
                                <input type="hidden" name="novo_status" value="<?= $ativo ? 0 : 1 ?>">
                                <button type="submit" class="btn <?= $ativo ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-sm w-100">
                                    <i class="fas <?= $ativo ? 'fa-user-slash' : 'fa-user-plus' ?>"></i> <?= $ativo ? 'Inativar' : 'Ativar' ?>
                                </button>
                            </form>

                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmarExclusao(<?= $uid ?>, '<?= htmlspecialchars($u['Usuario']) ?>')">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalExcluir" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir permanentemente o usuário <strong id="nomeUsuarioExcluir"></strong>?</p>
                    <p class="text-danger small font-weight-bold">Esta ação não poderá ser desfeita!</p>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="User_Id" id="idUsuarioExcluir">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Sim, Excluir Agora</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tabelaUsuarios').DataTable({
            "order": [[ 0, "desc" ]],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.22/i18n/Portuguese-Brasil.json" },
            "pageLength": 50
        });
    });

    function confirmarExclusao(id, nome) {
        $('#nomeUsuarioExcluir').text(nome);
        $('#idUsuarioExcluir').val(id);
        $('#modalExcluir').modal('show');
    }
</script>

</body>
</html>
<?php ob_end_flush(); ?>
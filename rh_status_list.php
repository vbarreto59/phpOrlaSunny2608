<?php
// rh_status_list.php
session_start();
require_once 'conn.php'; 

header('Content-Type: text/html; charset=utf-8');

// =========================================================
// 1. DEFINIÇÃO DA FUNÇÃO DE CONEXÃO
// =========================================================
if (!function_exists('getOpenConnection')) {
    function getOpenConnection() {
        global $conn; 
        return $conn;
    }
}

// =========================================================
// 2. AJAX - ATUALIZAÇÃO DE STATUS
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $userId = $_POST['userId'] ?? '';
    $field  = $_POST['field'] ?? '';
    $value  = $_POST['value'] ?? '';

    $db = getOpenConnection();
    if (!$db) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erro de conexão"]);
        exit;
    }

    try {
        $allowed = ['Ativo', 'Licenca'];
        if (!in_array($field, $allowed)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Campo inválido"]);
            exit;
        }

        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $sql = "UPDATE usuarios SET $field = ? WHERE UserID = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$boolValue, $userId]);

        echo json_encode(["status" => "success"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// =========================================================
// 3. CARREGAMENTO DOS DADOS (FILTRO TOCCA_ONZE + GERENTE)
// =========================================================
$db = getOpenConnection();
if (!$db) {
    die("Erro: Falha na conexão com o banco de dados.");
}

$ativosLicenciados = [];
$restante = [];

try {
    // SQL com JOIN para verificar se o ID existe na tabela de gerencias
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM gerencias g WHERE g.UserId = u.UserId) as IsGerente
            FROM usuarios u 
            WHERE u.Grupo = 'TOCCA_ONZE' 
            ORDER BY u.Nome ASC";
            
    $stmt = $db->query($sql);
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allUsers as $u) {
        $isAtivo   = (bool)($u['Ativo'] ?? 0);
        $isLicenca = (bool)($u['Licenca'] ?? 0);

        if ($isAtivo && $isLicenca) {
            $ativosLicenciados[] = $u;
        } else {
            $restante[] = $u;
        }
    }
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

$totalMisto = count($ativosLicenciados);

// =========================================================
// 4. FUNÇÃO DE RENDERIZAÇÃO DA TABELA
// =========================================================
function renderUserTable($users, $id) {
    if (empty($users)) {
        echo '<div class="p-4 text-center text-muted">Nenhum registro encontrado.</div>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table id="<?= $id ?>" class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Colaborador</th>
                    <th class="text-center">Status Ativo</th>
                    <th class="text-center">Status Licença</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div>
                                <div class="fw-bold text-dark">
                                    <?= htmlspecialchars(strtoupper($u['Usuario'] ?? '')) ?>
                                    <?php if ($u['IsGerente'] > 0): ?>
                                        <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">GERENTE</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted"><?= htmlspecialchars($u['Nome'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input type="checkbox" class="form-check-input btn-update" 
                                   data-id="<?= $u['UserId'] ?>" data-field="Ativo" <?= $u['Ativo'] ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input type="checkbox" class="form-check-input btn-update" 
                                   data-id="<?= $u['UserId'] ?>" data-field="Licenca" <?= $u['Licenca'] ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-center">
                        <a href="usr_edit.php?id=<?= $u['UserId'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sunny • Gestão TOCCA_ONZE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .card-header { background: white; font-weight: bold; padding: 1rem 1.25rem; border-bottom: 2px solid #f1f1f1; }
        .card-misto { background: linear-gradient(45deg, #1d2b64, #f8cdda); color: white; max-width: 320px; margin: 0 auto 2rem; border-radius: 15px; }
        .form-check-input { cursor: pointer; scale: 1.2; }
    </style>
</head>
<body>

<div class="container py-5">
    
    <div class="card card-misto shadow text-center border-0">
        <div class="card-body py-4">
            <h6 class="text-uppercase small mb-2 fw-bold" style="letter-spacing: 1px;">Ativos & Licenciados</h6>
            <h1 class="display-5 fw-bold mb-0"><?= $totalMisto ?></h1>
            <small class="opacity-75">Grupo: TOCCA_ONZE</small>
        </div>
    </div>

    <div class="card">
        <div class="card-header text-success">
            <i class="fa-solid fa-user-check me-2"></i> OPERACIONAIS (ATIVO + LICENÇA)
        </div>
        <?php renderUserTable($ativosLicenciados, 'tabAtivos'); ?>
    </div>

    <div class="card">
        <div class="card-header text-muted">
            <i class="fa-solid fa-user-slash me-2"></i> PENDENTES OU INATIVOS
        </div>
        <?php renderUserTable($restante, 'tabRestante'); ?>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    const dtConfig = { 
        pageLength: 100, 
        lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "Tudo"] ],
        language: { url: "https://cdn.datatables.net/plug-ins/2.1.8/i18n/pt-BR.json" } 
    };

    $('#tabAtivos, #tabRestante').DataTable(dtConfig);

    // Ajax para troca de status
    $(document).on('change', '.btn-update', function() {
        const userId = $(this).data('id');
        const field  = $(this).data('field');
        const value  = this.checked;

        $.post('?ajax=1', { userId, field, value }, function() {
            // Recarrega para mover o usuário de tabela conforme o novo status
            location.reload(); 
        });
    });
});
</script>
</body>
</html>
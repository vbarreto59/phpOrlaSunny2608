<?php
// ==============================================
// usr_dupe1.php - DUPLICIDADES COM GERÊNCIAS E ANOS
// ==============================================
session_start();
require_once 'conn.php';

header('Content-Type: text/html; charset=utf-8');

// ==============================================
// HANDLER AJAX
// ==============================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if (in_array($_GET['action'], ['ativo', 'licenca']) && isset($_POST['userId'])) {
        $userId = (int)$_POST['userId'];
        $value  = (int)$_POST['value'];
        $campo  = $_GET['action'] === 'ativo' ? 'Ativo' : 'Licenca';
        try {
            global $conn;
            $stmt = $conn->prepare("UPDATE usuarios SET $campo = ? WHERE UserID = ?");
            $stmt->execute([$value, $userId]);
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error"]);
        }
        exit;
    }

    if ($_GET['action'] === 'excluir' && isset($_GET['id'])) {
        $userId = (int)$_GET['id'];
        try {
            global $conn;
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE UserID = ?");
            $stmt->execute([$userId]);
            echo json_encode(["status" => "success", "message" => "Usuário excluído!"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'unificar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $keepId = (int)$_POST['keepId'];
        $oldId  = (int)$_POST['oldId'];
        try {
            global $conn;
            $updated = 0;
            $queries = [
                "UPDATE vendas SET UserIdDiretoria = ? WHERE UserIdDiretoria = ?",
                "UPDATE vendas SET UserIdGerencia = ? WHERE UserIdGerencia = ?",
                "UPDATE vendas SET CorretorId = ? WHERE CorretorId = ?"
            ];
            foreach ($queries as $sql) {
                $stmt = $conn->prepare($sql);
                $stmt->execute([$keepId, $oldId]);
                $updated += $stmt->rowCount();
            }
            echo json_encode(["status" => "success", "message" => "$updated vendas transferidas!"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }
}

// ==============================================
// FUNÇÕES
// ==============================================
function getUserCargo($userId) {
    global $conn;
    $sql = "SELECT CASE 
                WHEN d.UserId IS NOT NULL AND g.UserId IS NOT NULL THEN 'AMBOS'
                WHEN d.UserId IS NOT NULL THEN 'DIRETOR'
                WHEN g.UserId IS NOT NULL THEN 'GERENTE'
                ELSE 'COMUM' 
            END AS cargo
            FROM usuarios u
            LEFT JOIN diretorias d ON d.UserId = u.UserId
            LEFT JOIN gerencias g ON g.UserId = u.UserId
            WHERE u.UserID = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() ?: 'COMUM';
}

function getCargoBadge($cargo) {
    switch ($cargo) {
        case 'AMBOS':   return '<span class="badge bg-dark">📌 Dir+Ger</span>';
        case 'DIRETOR': return '<span class="badge bg-danger">👔 Diretor</span>';
        case 'GERENTE': return '<span class="badge bg-warning text-dark">🧑‍💼 Gerente</span>';
        default:        return '<span class="badge bg-secondary">Colaborador</span>';
    }
}

// Retorna total de vendas + detalhamento por ano e gerências envolvidas
function getVendasInfo($userId) {
    global $conn;
    // Buscamos o ano, a quantidade e os nomes das gerências (agrupados para não repetir no mesmo ano)
    $sql = "SELECT v.AnoVenda, COUNT(*) as qtd, GROUP_CONCAT(DISTINCT g.NomeGerencia SEPARATOR ', ') as gerencias
            FROM vendas v
            LEFT JOIN gerencias g ON v.GerenciaId = g.GerenciaID
            WHERE (v.UserIdDiretoria = ? OR v.UserIdGerencia = ? OR v.CorretorId = ?) 
              AND v.AnoVenda IS NOT NULL 
            GROUP BY v.AnoVenda 
            ORDER BY v.AnoVenda DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $linhas = [];
    foreach ($rows as $row) {
        $total += (int)$row['qtd'];
        $gerStr = !empty($row['gerencias']) ? " <span class='text-primary'>[" . $row['gerencias'] . "]</span>" : "";
        $linhas[] = "<strong>{$row['AnoVenda']}</strong>: {$row['qtd']} vds{$gerStr}";
    }

    return [
        'total' => $total, 
        'html' => implode("<br>", $linhas)
    ];
}

function hasSalesInMultipleIds($groupIds) {
    if (count($groupIds) < 2) return false;
    global $conn;
    $ph = str_repeat('?,', count($groupIds)-1) . '?';
    $sql = "SELECT COUNT(DISTINCT user_id) FROM (
                SELECT UserIdDiretoria as user_id FROM vendas WHERE UserIdDiretoria IN ($ph)
                UNION SELECT UserIdGerencia as user_id FROM vendas WHERE UserIdGerencia IN ($ph)
                UNION SELECT CorretorId as user_id FROM vendas WHERE CorretorId IN ($ph)
            ) t WHERE user_id IS NOT NULL";
    $params = array_merge($groupIds, $groupIds, $groupIds);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 1;
}

function getUsuariosByField($field, $value) {
    global $conn;
    $stmt = $conn->prepare("SELECT UserID, Nome, Usuario, Email, Telefones, Ativo, Licenca 
                            FROM usuarios WHERE $field = ? ORDER BY UserID DESC");
    $stmt->execute([$value]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Carregar duplicidades
global $conn;
$duplicadosEmail   = $conn->query("SELECT Email, COUNT(*) as qtd FROM usuarios WHERE Email != '' GROUP BY Email HAVING COUNT(*) > 1 ORDER BY qtd DESC")->fetchAll(PDO::FETCH_ASSOC);
$duplicadosNome    = $conn->query("SELECT Nome, COUNT(*) as qtd FROM usuarios WHERE Nome != '' GROUP BY Nome HAVING COUNT(*) > 1 ORDER BY qtd DESC")->fetchAll(PDO::FETCH_ASSOC);
$duplicadosCelular = $conn->query("SELECT Telefones, COUNT(*) as qtd FROM usuarios WHERE Telefones != '' GROUP BY Telefones HAVING COUNT(*) > 1 ORDER BY qtd DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SunnyImob • Gestão de Duplicidades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f7f6; font-size: 0.9rem; }
        .glass-card { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: none; }
        .header-section { background: #1e293b; color: white; padding: 20px; border-radius: 12px 12px 0 0; }
        .duplicate-group { border-left: 5px solid #ef476f; margin-bottom: 30px; padding: 15px; }
        .vendas-info { line-height: 1.3; font-size: 0.82rem; }
    </style>
</head>
<body class="p-4">

<div class="container-fluid">
    <div class="glass-card mb-4">
        <div class="header-section">
            <h3><i class="fas fa-users-slash me-2"></i> Unificação de Cadastros Duplicados</h3>
            <p class="mb-0 opacity-75">Identifique usuários repetidos e consolide o histórico de vendas.</p>
        </div>
    </div>

    <?php 
    $secoes = [
        ['dados' => $duplicadosEmail, 'campo' => 'Email', 'titulo' => 'E-mail', 'icon' => 'envelope', 'color' => 'danger'],
        ['dados' => $duplicadosNome, 'campo' => 'Nome', 'titulo' => 'Nome', 'icon' => 'user', 'color' => 'primary'],
        ['dados' => $duplicadosCelular, 'campo' => 'Telefones', 'titulo' => 'Celular', 'icon' => 'mobile-alt', 'color' => 'info']
    ];

    foreach ($secoes as $secao): ?>
        <h4 class="text-<?= $secao['color'] ?> mt-4"><i class="fas fa-<?= $secao['icon'] ?> me-2"></i> Por <?= $secao['titulo'] ?></h4>
        
        <?php foreach ($secao['dados'] as $dup): 
            $usuarios = getUsuariosByField($secao['campo'], $dup[$secao['campo']]);
            $groupIds = array_column($usuarios, 'UserID');
            $canUnify = hasSalesInMultipleIds($groupIds);
        ?>
            <div class="duplicate-group glass-card">
                <h6 class="fw-bold text-dark">
                    <?= htmlspecialchars($dup[$secao['campo']]) ?> 
                    <span class="badge bg-secondary ms-2"><?= $dup['qtd'] ?> cadastros</span>
                </h6>
                
                <table class="table table-sm table-hover align-middle mt-2">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nome/Usuário</th>
                            <th>Histórico de Vendas (Ano [Gerência])</th>
                            <th>Cargo</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): 
                            $info = getVendasInfo($u['UserID']);
                            $cargo = getUserCargo($u['UserID']);
                        ?>
                        <tr data-user-id="<?= $u['UserID'] ?>">
                            <td><span class="badge bg-light text-dark border">#<?= $u['UserID'] ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($u['Nome']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($u['Usuario'] ?? '---') ?></small>
                            </td>
                            <td class="vendas-info">
                                <?= $info['total'] > 0 ? $info['html'] : '<span class="text-muted">Nenhuma venda</span>' ?>
                            </td>
                            <td><?= getCargoBadge($cargo) ?></td>
                            <td>
                                <div class="form-check form-switch d-inline-block me-2">
                                    <input class="form-check-input toggle-ativo" type="checkbox" <?= $u['Ativo'] ? 'checked' : '' ?> title="Ativo">
                                </div>
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input toggle-licenca" type="checkbox" <?= $u['Licenca'] ? 'checked' : '' ?> title="Licença">
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="usr_admin_editar3.php?id=<?= $u['UserID'] ?>" class="btn btn-outline-primary btn-sm" target="_blank"><i class="fas fa-edit"></i></a>
                                    <button onclick="excluirUsuario(<?= $u['UserID'] ?>)" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    <?php if ($info['total'] > 0): ?>
                                        <button onclick="abrirUnificacao(<?= $u['UserID'] ?>, '<?= addslashes($u['Nome']) ?>', [<?= implode(',', $groupIds) ?>])" class="btn btn-success btn-sm">
                                            <i class="fas fa-compress-alt"></i> Unificar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// Atualização de Ativo/Licença
$(document).on('change', '.toggle-ativo, .toggle-licenca', async function() {
    const userId = $(this).closest('tr').data('user-id');
    const action = $(this).hasClass('toggle-ativo') ? 'ativo' : 'licenca';
    const val = this.checked ? 1 : 0;
    try {
        await $.post(`usr_dupe1.php?action=${action}`, { userId: userId, value: val });
    } catch(e) {
        Swal.fire('Erro', 'Falha na atualização', 'error');
    }
});

async function excluirUsuario(id) {
    const r = await Swal.fire({
        title: 'Confirmar Exclusão?',
        text: "Se houver vendas ligadas a este ID, o banco pode impedir a exclusão.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33'
    });
    if (r.isConfirmed) {
        const res = await fetch(`usr_dupe1.php?action=excluir&id=${id}`);
        const data = await res.json();
        if(data.status==='success') location.reload();
        else Swal.fire('Atenção', data.message, 'error');
    }
}

function abrirUnificacao(currentId, nome, groupIds) {
    const options = groupIds
        .filter(id => id != currentId)
        .map(id => `<option value="${id}">Transferir do ID: ${id}</option>`)
        .join('');

    Swal.fire({
        title: 'Unificar Vendas',
        html: `
            <div class="text-start">
                <p>Mover vendas para o ID <strong>${currentId}</strong> (${nome})</p>
                <label class="small fw-bold">Origem das vendas:</label>
                <select id="oldId" class="form-select mb-3">${options}</select>
                <input type="hidden" id="keepId" value="${currentId}">
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Executar Unificação',
        confirmButtonColor: '#198754'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const keepId = document.getElementById('keepId').value;
            const oldId = document.getElementById('oldId').value;
            const res = await fetch('usr_dupe1.php?action=unificar', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `keepId=${keepId}&oldId=${oldId}`
            });
            const data = await res.json();
            Swal.fire(data.status === 'success' ? 'Sucesso!' : 'Erro', data.message, data.status).then(() => {
                if(data.status === 'success') location.reload();
            });
        }
    });
}
</script>
</body>
</html>
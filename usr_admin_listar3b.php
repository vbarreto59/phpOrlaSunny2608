<?php
// usr_admin_listar3c.php - Versão com DUAS TABELAS separadas
// Tabela 1: Usuários Ativos + Licenciados (Ativo=1 AND Licenca=1)
// Tabela 2: Demais usuários (inativos ou sem licença)
// Inclui filtro por grupo na barra de filtros
session_start();
require_once 'conn.php';

// --------------------------------------------------------------
// HANDLER AJAX (POST) - atualização inline dos toggles
// --------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET')
    && isset($_GET['ajax']) && $_GET['ajax'] == '1'
    && !isset($_GET['action'])) {

    header('Content-Type: application/json');
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php_errors.log');

    $userId = $_POST['userId'] ?? $_GET['userId'] ?? null;
    $field  = $_POST['field']  ?? $_GET['field']  ?? null;
    $value  = $_POST['value']  ?? $_GET['value']  ?? null;

    error_log("AJAX [usr_admin_listar3c]: userId=$userId, field=$field, value=$value, method=" . $_SERVER['REQUEST_METHOD']);

    if (!$userId || !isset($field)) {
        echo json_encode(["status" => "error", "message" => "Parâmetros inválidos"]);
        exit;
    }

    global $conn;
    if (!$conn) {
        echo json_encode(["status" => "error", "message" => "Falha na conexão com o banco"]);
        exit;
    }

    try {
        switch ($field) {
            case "ativo":
                $ativo = ($value === "1" || $value === 1 || $value === true || $value === "true") ? 1 : 0;
                $stmt = $conn->prepare("UPDATE usuarios SET Ativo = ? WHERE UserID = ?");
                $stmt->execute([$ativo, $userId]);
                break;

            case "licenca":
                $licenca = ($value === "1" || $value === 1 || $value === true || $value === "true") ? 1 : 0;
                $stmt = $conn->prepare("UPDATE usuarios SET Licenca = ? WHERE UserID = ?");
                $stmt->execute([$licenca, $userId]);
                break;

            case "grupo":
                $stmt = $conn->prepare("SELECT COUNT(*) FROM usuario_grupo WHERE UserId = ?");
                $stmt->execute([$userId]);
                $exists = $stmt->fetchColumn() > 0;

                if (empty($value)) {
                    if ($exists) {
                        $stmt = $conn->prepare("UPDATE usuario_grupo SET ID_Grupo = NULL WHERE UserId = ?");
                        $stmt->execute([$userId]);
                    }
                    $stmt = $conn->prepare("UPDATE usuarios SET GrupoId = NULL, Grupo = NULL WHERE UserID = ?");
                    $stmt->execute([$userId]);
                } else {
                    if ($exists) {
                        $stmt = $conn->prepare("UPDATE usuario_grupo SET ID_Grupo = ? WHERE UserId = ?");
                        $stmt->execute([$value, $userId]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)");
                        $stmt->execute([$userId, $value]);
                    }
                    $stmt = $conn->prepare("SELECT Nome_Grupo FROM grupo WHERE ID_Grupo = ?");
                    $stmt->execute([$value]);
                    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
                    $grupoNome = $grupo ? $grupo['Nome_Grupo'] : null;

                    $stmt = $conn->prepare("UPDATE usuarios SET GrupoId = ?, Grupo = ? WHERE UserID = ?");
                    $stmt->execute([$value, $grupoNome, $userId]);
                }
                break;

            case "diretoria":
                if (empty($value)) {
                    $sql = "UPDATE usuarios SET DiretoriaID = NULL, Diretoria = NULL, GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$userId]);
                } else {
                    $stmt = $conn->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
                    $stmt->execute([$value]);
                    $dir = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($dir) {
                        $sql = "UPDATE usuarios SET DiretoriaID = ?, Diretoria = ?, GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$value, $dir['NomeDiretoria'], $userId]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "Diretoria não encontrada"]);
                        exit;
                    }
                }
                break;

            case "gerencia":
                if (empty($value)) {
                    $sql = "UPDATE usuarios SET GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$userId]);
                } else {
                    $stmt = $conn->prepare("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = ?");
                    $stmt->execute([$value]);
                    $ger = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($ger) {
                        $sql = "UPDATE usuarios SET GerenciaID = ?, Gerencia = ? WHERE UserID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$value, $ger['NomeGerencia'], $userId]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "Gerência não encontrada"]);
                        exit;
                    }
                }
                break;

            default:
                echo json_encode(["status" => "error", "message" => "Campo inválido"]);
                exit;
        }

        echo json_encode(["status" => "success", "message" => "Atualizado com sucesso"]);
    } catch (PDOException $e) {
        error_log("ERRO AJAX PDO: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Erro interno no banco de dados"]);
    }
    exit;
}

// --------------------------------------------------------------
// AJAX - Carregar gerências por diretoria (GET)
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['action']) && $_GET['action'] === 'get-gerencias') {
    header('Content-Type: application/json');
    $DiretoriaId = $_GET['DiretoriaId'] ?? null;
    if (!$DiretoriaId) {
        echo json_encode([]);
        exit;
    }
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT GerenciaID, NomeGerencia FROM gerencias WHERE DiretoriaID = ? ORDER BY NomeGerencia");
        $stmt->execute([$DiretoriaId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
    } catch (Exception $e) {
        error_log("Erro ao carregar gerências: " . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// --------------------------------------------------------------
// A PARTIR DAQUI: SAÍDA HTML
// --------------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');

// Carregar dados principais
global $conn;
if (!$conn) {
    die('<div class="alert alert-danger text-center m-5">Erro de conexão com o banco de dados.</div>');
}

try {
    $grupos     = $conn->query("SELECT * FROM grupo ORDER BY Nome_Grupo")->fetchAll(PDO::FETCH_ASSOC);
    $diretorias = $conn->query("SELECT * FROM diretorias ORDER BY NomeDiretoria")->fetchAll(PDO::FETCH_ASSOC);
    $gerencias  = $conn->query("SELECT * FROM gerencias ORDER BY NomeGerencia")->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $stmt = $conn->prepare("SELECT COUNT(*) as total, 
                                   SUM(CASE WHEN Ativo = 1 THEN 1 ELSE 0 END) as ativos,
                                   SUM(CASE WHEN Licenca = 1 THEN 1 ELSE 0 END) as licenciados,
                                   SUM(CASE WHEN Ativo = 1 AND Licenca = 1 THEN 1 ELSE 0 END) as ativos_e_licenciados
                            FROM usuarios");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Separar usuários em dois grupos
    // Grupo 1: Ativos E Licenciados (Ativo=1 AND Licenca=1)
    $stmtAtivosLicenciados = $conn->prepare("SELECT * FROM usuarios WHERE Ativo = 1 AND Licenca = 1 ORDER BY UserId DESC");
    $stmtAtivosLicenciados->execute();
    $usersAtivosLicenciados = $stmtAtivosLicenciados->fetchAll(PDO::FETCH_ASSOC);

    // Grupo 2: Demais usuários (inativos OU sem licença)
    $stmtDemais = $conn->prepare("SELECT * FROM usuarios WHERE Ativo = 0 OR Licenca = 0 ORDER BY UserId DESC");
    $stmtDemais->execute();
    $usersDemais = $stmtDemais->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('<div class="alert alert-danger text-center m-5">Erro ao carregar dados: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// Função para renderizar linha da tabela (reutilizável)
function renderUserRow($user, $grupos, $diretorias, $gerencias) {
    $uid              = $user['UserId'] ?? 0;
    $nome             = $user['Nome'] ?? $user['Usuario'] ?? '—';
    $senha            = $user['SenhaAtual'] ?? '*';
    $usuario          = $user['Usuario'] ?? '—';
    $email            = $user['Email'] ?? '—';
    $telefones        = $user['Telefones'] ?? '—';
    $creci            = $user['CRECI'] ?? '';
    $ativo            = (int)($user['Ativo'] ?? 0) === 1;
    $licenca          = (int)($user['Licenca'] ?? 0) === 1;
    $inativo          = !$ativo ? 'user-inativo' : '';
    $semLicenca       = !$licenca ? 'user-sem-licenca' : '';
    $funcao           = $user['Funcao'] ?? '—';
    $permissao        = (int)($user['Permissao'] ?? 0);
    $grupoIdAtual     = $user['GrupoId'] ?? null;
    $DiretoriaIDAtual = $user['DiretoriaID'] ?? null;
    $GerenciaIDAtual  = $user['GerenciaID']  ?? null;

    $badgeClass = getBadgeClass($permissao);
    
    ob_start();
    ?>
    <tr class="<?= $inativo ?> <?= $semLicenca ?>" data-user-id="<?= $uid ?>" data-grupo-id="<?= $grupoIdAtual ?>" data-search="<?= htmlspecialchars(strtolower($nome . ' ' . $usuario . ' ' . $email)) ?>">
        <td class="fw-bold text-center"><?= $uid ?></td>
        <td>
            <div class="d-flex align-items-start">
                <div class="icon-circle bg-light text-primary me-3">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="fw-bold mb-1"><?= htmlspecialchars($nome) ?></div>
                    <small class="d-block text-muted mb-1">
                        <i class="fas fa-at me-1"></i><?= htmlspecialchars($usuario) ?>
                    </small>
                    <small class="d-block text-muted mb-1">
                        <i class="fas fa-lock me-1"></i><?= htmlspecialchars($senha) ?>
                    </small>
                    <small class="d-block text-muted mb-1">
                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($email) ?>
                    </small>
                    <?php if ($creci): ?>
                    <small class="d-block text-muted">
                        <i class="fas fa-id-card me-1"></i>CRECI: <?= htmlspecialchars($creci) ?>
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td>
            <div class="form-check form-switch">
                <input class="form-check-input toggle-ativo" type="checkbox" role="switch"
                       data-user-id="<?= $uid ?>"
                       <?= $ativo ? 'checked' : '' ?>>
                <label class="form-check-label">
                    <span class="status-indicator <?= $ativo ? 'status-active' : 'status-inactive' ?>"></span>
                    <?= $ativo ? 'Ativo' : 'Inativo' ?>
                </label>
            </div>
        </td>
        <td>
            <div class="form-check form-switch">
                <input class="form-check-input toggle-licenca" type="checkbox" role="switch"
                       data-user-id="<?= $uid ?>"
                       <?= $licenca ? 'checked' : '' ?>>
                <label class="form-check-label">
                    <span class="status-indicator <?= $licenca ? 'status-active' : 'status-inactive' ?>"></span>
                    <?= $licenca ? 'Licenciada' : 'Sem Licença' ?>
                </label>
            </div>
        </td>
        <td>
            <span class="badge <?= $badgeClass ?> badge-permissao">
                <i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($funcao) ?>
            </span>
        </td>
        <td>
            <select class="form-select form-select-sm grupo-select select2" data-user-id="<?= $uid ?>">
                <option value="">— Nenhum —</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?= $g['ID_Grupo'] ?>"
                            <?= $grupoIdAtual == $g['ID_Grupo'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['Nome_Grupo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm diretoria-select select2" data-user-id="<?= $uid ?>">
                <option value="">— Nenhum —</option>
                <?php foreach ($diretorias as $d): ?>
                    <option value="<?= $d['DiretoriaId'] ?>"
                            <?= $DiretoriaIDAtual == $d['DiretoriaId'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['NomeDiretoria']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm gerencia-select select2" data-user-id="<?= $uid ?>"
                    <?= empty($DiretoriaIDAtual) ? 'disabled' : '' ?>>
                <option value="">— Nenhum —</option>
                <?php if (!empty($DiretoriaIDAtual)): ?>
                    <?php foreach ($gerencias as $ger): ?>
                        <?php if ($ger['DiretoriaID'] == $DiretoriaIDAtual): ?>
                            <option value="<?= $ger['GerenciaID'] ?>"
                                    <?= $GerenciaIDAtual == $ger['GerenciaID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ger['NomeGerencia']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </td>
        <td class="text-center">
            <div class="d-flex gap-2 justify-content-center">
                <a href="usr_admin_editar3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-warning" title="Editar" target="_blank">
                    <i class="fas fa-pencil-alt"></i>
                </a>
                <a href="usr_email_credencial.php?UserID=<?= $uid ?>" class="btn-action btn btn-outline-info" title="Enviar Credenciais"
                   onclick="return confirm('Enviar credenciais por e-mail?')">
                    <i class="fas fa-envelope"></i>
                </a>
                <a href="usr_admin_excluir3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-danger" title="Excluir"
                   onclick="return confirm('Confirma exclusão permanente?')">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// Função compatível com PHP 7 para badge de permissão
function getBadgeClass($permissao) {
    switch ($permissao) {
        case 1: return 'badge-superadmin';
        case 2: return 'badge-admin';
        case 3: return 'badge-gestor';
        case 4: return 'badge-editor';
        case 5:
        case 6: return 'badge-corretor';
        case 7: return 'badge-visualizador';
        default: return 'badge-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunny • Gestão de Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #06d6a0;
            --danger: #ef476f;
            --warning: #ffd166;
            --info: #118ab2;
            --dark: #2b2d42;
            --light: #f8f9fa;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            color: var(--dark);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        .header-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 1.5rem 2rem;
        }
        .stat-card {
            border-radius: 12px;
            padding: 1.25rem;
            border: none;
            transition: transform 0.3s ease;
            min-height: 120px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.total { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; }
        .stat-card.ativos { background: linear-gradient(135deg, #06d6a0, #04b489); color: white; }
        .stat-card.licenciados { background: linear-gradient(135deg, #ffd166, #ffb142); color: white; }
        .stat-card.inativos { background: linear-gradient(135deg, #ef476f, #e63946); color: white; }
        .stat-card.ativos-licenciados { background: linear-gradient(135deg, #0f67fe, #0a4cbe); color: white; }
        .table-container {
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        .table-header {
            background: linear-gradient(90deg, var(--success), #04b489);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 600;
        }
        .table-header-secondary {
            background: linear-gradient(90deg, #6c757d, #495057);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 600;
        }
        .table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }
        .table th {
            font-weight: 600;
            border-bottom: none;
            padding: 1rem;
            white-space: nowrap;
        }
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: rgba(0,0,0,0.05);
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(67, 97, 238, 0.03);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.08);
            transform: scale(1.002);
            transition: all 0.2s ease;
        }
        .badge-permissao {
            padding: 0.5em 1em;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
        }
        .badge-superadmin { background: linear-gradient(135deg, #ef476f, #e63946); color: white; }
        .badge-admin { background: linear-gradient(135deg, #ffd166, #ffb142); color: var(--dark); }
        .badge-gestor { background: linear-gradient(135deg, #06d6a0, #04b489); color: white; }
        .badge-editor { background: linear-gradient(135deg, #118ab2, #0a6c8f); color: white; }
        .badge-corretor { background: linear-gradient(135deg, #7209b7, #560bad); color: white; }
        .badge-visualizador { background: linear-gradient(135deg, #6c757d, #495057); color: white; }
        .form-switch .form-check-input {
            width: 3.5em;
            height: 1.8em;
            cursor: pointer;
            border-color: #dee2e6;
            box-shadow: none;
        }
        .form-switch .form-check-input:checked {
            background-color: var(--success);
            border-color: var(--success);
        }
        .form-switch .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            min-height: 38px;
            padding: 0.25rem 0.5rem;
        }
        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-active { background-color: var(--success); }
        .status-inactive { background-color: var(--danger); }
        .user-card-mobile {
            display: none;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: white;
        }
        @media (max-width: 768px) {
            .table-container {
                display: none;
            }
            .user-card-mobile {
                display: block;
            }
        }
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        .section-divider {
            height: 3px;
            background: linear-gradient(90deg, var(--success), var(--primary), var(--danger));
            margin: 2rem 0;
            border-radius: 3px;
        }
    </style>
</head>
<body class="fade-in">
<div class="container-fluid py-3 py-md-4">
    <!-- Header Principal -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card">
                <div class="header-gradient text-white">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                        <div class="d-flex align-items-center mb-3 mb-md-0">
                            <div class="icon-circle bg-white text-primary me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h1 class="h3 mb-1">Gestão de Usuários</h1>
                                <p class="mb-0 opacity-75">Gerencie colaboradores, permissões e acessos</p>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 header-buttons">
                            <a href="usr_admin_novo_usuario3.php" class="btn btn-light btn-sm shadow-sm">
                                <i class="fas fa-user-plus me-1"></i> Novo Usuário
                            </a>
                            <a href="usr_grupo_list.php" class="btn btn-outline-light btn-sm shadow-sm">
                                <i class="fas fa-users-gear me-1"></i> Grupos
                            </a>
                            <button class="btn btn-outline-light btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#filtroModal">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="row g-3 p-4">
                    <div class="col-md-3">
                        <div class="stat-card total">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-primary me-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?= $stats['total'] ?? 0 ?></h3>
                                    <p class="mb-0 opacity-75">Total de Usuários</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card ativos-licenciados">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-primary me-3">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?= $stats['ativos_e_licenciados'] ?? 0 ?></h3>
                                    <p class="mb-0 opacity-75">Ativos + Licenciados</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card ativos">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-success me-3">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?= $stats['ativos'] ?? 0 ?></h3>
                                    <p class="mb-0 opacity-75">Usuários Ativos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card licenciados">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-white text-warning me-3">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?= $stats['licenciados'] ?? 0 ?></h3>
                                    <p class="mb-0 opacity-75">Licenciados</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros Rápidos -->
                <div class="filter-section mx-4 mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" placeholder="Buscar por nome, e-mail ou usuário..." id="searchInput">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="">Todos os status</option>
                                <option value="ativo">Apenas ativos</option>
                                <option value="inativo">Apenas inativos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="filterPermission">
                                <option value="">Todas as permissões</option>
                                <option value="1">SUPERADMIN</option>
                                <option value="2">ADMIN</option>
                                <option value="3">GESTOR-EDITOR</option>
                                <option value="4">EDITOR</option>
                                <option value="5">CORRETOR</option>
                                <option value="6">CORRETOR-EDITOR</option>
                                <option value="7">VISUALIZADOR</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="filterGrupo">
                                <option value="">Todos os grupos</option>
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?= $g['ID_Grupo'] ?>"><?= htmlspecialchars($g['Nome_Grupo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Limpar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABELA 1: Usuários Ativos + Licenciados -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card table-container">
                <div class="table-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Usuários Ativos e Licenciados</strong>
                        <span class="badge bg-white text-success ms-2"><?= count($usersAtivosLicenciados) ?></span>
                    </div>
                    <small class="opacity-75">Ativo = 1 e Licença = 1</small>
                </div>
                <div class="table-responsive">
                    <table id="tabelaAtivosLicenciados" class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Usuário / Dados</th>
                                <th width="100">Ativo</th>
                                <th width="120">Licença</th>
                                <th width="120">Função</th>
                                <th width="180">Grupo</th>
                                <th width="180">Diretoria</th>
                                <th width="180">Gerência</th>
                                <th width="160" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usersAtivosLicenciados as $user): ?>
                            <?= renderUserRow($user, $grupos, $diretorias, $gerencias) ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Divisor visual -->
    <div class="row">
        <div class="col-12">
            <div class="section-divider"></div>
        </div>
    </div>

    <!-- TABELA 2: Demais Usuários (Inativos ou Sem Licença) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card table-container">
                <div class="table-header-secondary d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Demais Usuários</strong>
                        <span class="badge bg-white text-secondary ms-2"><?= count($usersDemais) ?></span>
                    </div>
                    <small class="opacity-75">Inativos ou Sem Licença</small>
                </div>
                <div class="table-responsive">
                    <table id="tabelaDemais" class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Usuário / Dados</th>
                                <th width="100">Ativo</th>
                                <th width="120">Licença</th>
                                <th width="120">Função</th>
                                <th width="180">Grupo</th>
                                <th width="180">Diretoria</th>
                                <th width="180">Gerência</th>
                                <th width="160" class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usersDemais as $user): ?>
                            <?= renderUserRow($user, $grupos, $diretorias, $gerencias) ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards Mobile -->
    <div class="row d-md-none">
        <!-- Mobile: Ativos + Licenciados -->
        <div class="col-12 mb-3">
            <h5 class="text-success"><i class="fas fa-check-circle me-2"></i>Ativos e Licenciados</h5>
        </div>
        <?php foreach ($usersAtivosLicenciados as $user):
            renderMobileCard($user, $grupos, $diretorias, $gerencias);
        endforeach; ?>
        
        <!-- Mobile: Demais -->
        <div class="col-12 mb-3 mt-4">
            <h5 class="text-secondary"><i class="fas fa-exclamation-triangle me-2"></i>Demais Usuários</h5>
        </div>
        <?php foreach ($usersDemais as $user):
            renderMobileCard($user, $grupos, $diretorias, $gerencias);
        endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="text-center text-muted small">
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <span>Sunny System • <?= date('Y') ?> • v3.1</span>
                    <span>Total: <?= $stats['total'] ?? 0 ?> usuários</span>
                    <span>Ativos+Licenciados: <?= $stats['ativos_e_licenciados'] ?? 0 ?></span>
                    <span>Demais: <?= ($stats['total'] ?? 0) - ($stats['ativos_e_licenciados'] ?? 0) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Função para renderizar card mobile
function renderMobileCard($user, $grupos, $diretorias, $gerencias) {
    $uid              = $user['UserId'] ?? 0;
    $nome             = $user['Nome'] ?? $user['Usuario'] ?? '—';
    $usuario          = $user['Usuario'] ?? '—';
    $email            = $user['Email'] ?? '—';
    $telefones        = $user['Telefones'] ?? '—';
    $creci            = $user['CRECI'] ?? '';
    $ativo            = (int)($user['Ativo'] ?? 0) === 1;
    $licenca          = (int)($user['Licenca'] ?? 0) === 1;
    $funcao           = $user['Funcao'] ?? '—';
    $permissao        = (int)($user['Permissao'] ?? 0);
    $grupoIdAtual     = $user['GrupoId'] ?? null;
    $DiretoriaIDAtual = $user['DiretoriaID'] ?? null;
    $GerenciaIDAtual  = $user['GerenciaID']  ?? null;

    $badgeClass = getBadgeClass($permissao);

    $grupoNome = '';
    foreach ($grupos as $g) {
        if ($g['ID_Grupo'] == $grupoIdAtual) {
            $grupoNome = $g['Nome_Grupo'];
            break;
        }
    }

    $diretoriaNome = '';
    foreach ($diretorias as $d) {
        if ($d['DiretoriaId'] == $DiretoriaIDAtual) {
            $diretoriaNome = $d['NomeDiretoria'];
            break;
        }
    }

    $gerenciaNome = '';
    foreach ($gerencias as $ger) {
        if ($ger['GerenciaID'] == $GerenciaIDAtual) {
            $gerenciaNome = $ger['NomeGerencia'];
            break;
        }
    }
?>
    <div class="user-card-mobile" data-user-id="<?= $uid ?>" data-grupo-id="<?= $grupoIdAtual ?>" data-search="<?= htmlspecialchars(strtolower($nome . ' ' . $usuario . ' ' . $email)) ?>">
        <div class="user-info">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-light text-primary me-3">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($nome) ?></h6>
                        <small class="text-muted">ID: <?= $uid ?></small>
                    </div>
                </div>
                <span class="badge <?= $badgeClass ?> badge-permissao">
                    <?= htmlspecialchars($funcao) ?>
                </span>
            </div>

            <div class="mb-2">
                <small class="d-block text-muted mb-1">
                    <i class="fas fa-at me-1"></i><?= htmlspecialchars($usuario) ?>
                </small>
                <small class="d-block text-muted mb-1">
                    <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($email) ?>
                </small>
                <?php if ($creci): ?>
                <small class="d-block text-muted mb-1">
                    <i class="fas fa-id-card me-1"></i>CRECI: <?= htmlspecialchars($creci) ?>
                </small>
                <?php endif; ?>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input toggle-ativo" type="checkbox" role="switch"
                               data-user-id="<?= $uid ?>"
                               <?= $ativo ? 'checked' : '' ?>>
                        <label class="form-check-label">
                            <span class="status-indicator <?= $ativo ? 'status-active' : 'status-inactive' ?>"></span>
                            <?= $ativo ? 'Ativo' : 'Inativo' ?>
                        </label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input toggle-licenca" type="checkbox" role="switch"
                               data-user-id="<?= $uid ?>"
                               <?= $licenca ? 'checked' : '' ?>>
                        <label class="form-check-label">
                            <span class="status-indicator <?= $licenca ? 'status-active' : 'status-inactive' ?>"></span>
                            <?= $licenca ? 'Licenciada' : 'Sem Licença' ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-2">
                <small class="text-muted d-block mb-1">
                    <i class="fas fa-users me-1"></i>Grupo: 
                    <span class="fw-medium"><?= $grupoNome ?: '—' ?></span>
                </small>
                <small class="text-muted d-block mb-1">
                    <i class="fas fa-building me-1"></i>Diretoria: 
                    <span class="fw-medium"><?= $diretoriaNome ?: '—' ?></span>
                </small>
                <small class="text-muted d-block">
                    <i class="fas fa-sitemap me-1"></i>Gerência: 
                    <span class="fw-medium"><?= $gerenciaNome ?: '—' ?></span>
                </small>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="usr_admin_editar3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-warning btn-sm" title="Editar" target="_blank">
                    <i class="fas fa-pencil-alt"></i>
                </a>
                <a href="usr_email_credencial.php?UserID=<?= $uid ?>" class="btn-action btn btn-outline-info btn-sm" title="Enviar Credenciais"
                   onclick="return confirm('Enviar credenciais por e-mail?')">
                    <i class="fas fa-envelope"></i>
                </a>
                <a href="usr_admin_excluir3.php?id=<?= $uid ?>" class="btn-action btn btn-outline-danger btn-sm" title="Excluir"
                   onclick="return confirm('Confirma exclusão permanente?')">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
    </div>
<?php
}
?>

<!-- Modal Filtros Avançados -->
<div class="modal fade" id="filtroModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-filter me-2"></i>Filtros Avançados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="modalStatus">
                        <option value="">Todos</option>
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Licença</label>
                    <select class="form-select" id="modalLicenca">
                        <option value="">Todos</option>
                        <option value="com">Com Licença</option>
                        <option value="sem">Sem Licença</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Grupo</label>
                    <select class="form-select" id="modalGrupo">
                        <option value="">Todos os grupos</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?= $g['ID_Grupo'] ?>"><?= htmlspecialchars($g['Nome_Grupo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Diretoria</label>
                    <select class="form-select" id="modalDiretoria">
                        <option value="">Todas as diretorias</option>
                        <?php foreach ($diretorias as $d): ?>
                            <option value="<?= $d['DiretoriaId'] ?>"><?= htmlspecialchars($d['NomeDiretoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="applyAdvancedFilters()">Aplicar Filtros</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// =========================================================
// AJAX CORRIGIDO PARA PRODUÇÃO
// =========================================================
async function ajaxPost(data) {
    const url = window.location.pathname + '?ajax=1';
    const formData = new FormData();
    for (const key in data) {
        formData.append(key, data[key]);
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            redirect: 'error'
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('AJAX error:', error);
        if (error.message.includes('redirect')) {
            const fallbackUrl = window.location.origin + window.location.pathname + '?ajax=1';
            const fallbackResp = await fetch(fallbackUrl, { method: 'POST', body: formData });
            return await fallbackResp.json();
        }
        throw error;
    }
}

function showToast(message, type = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    Toast.fire({ icon: type, title: message });
}

function addLoading(el) {
    el.prop('disabled', true);
    el.closest('.form-check, td').append('<span class="loader ms-2"></span>');
}

function removeLoading(el) {
    el.prop('disabled', false);
    el.closest('.form-check, td').find('.loader').remove();
}

$(document).ready(function() {
    // Inicializar DataTables para ambas as tabelas
    const tableAtivos = $('#tabelaAtivosLicenciados').DataTable({
        order: [[0, 'desc']],
        pageLength: 50,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
        },
        responsive: true,
        columnDefs: [
            { responsivePriority: 1, targets: 1 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: [2,3,5,6,7,8] }
        ]
    });

    const tableDemais = $('#tabelaDemais').DataTable({
        order: [[0, 'desc']],
        pageLength: 50,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
        },
        responsive: true,
        columnDefs: [
            { responsivePriority: 1, targets: 1 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: [2,3,5,6,7,8] }
        ]
    });

    $('.select2').select2({
        theme: "bootstrap-5",
        width: '100%',
        dropdownParent: $('.table-container')
    });

    // Filtro de busca em ambas as tabelas
    $('#searchInput').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        tableAtivos.search(searchTerm).draw();
        tableDemais.search(searchTerm).draw();
        
        $('.user-card-mobile').each(function() {
            const cardSearch = $(this).data('search') || '';
            if (cardSearch.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Filtro por grupo
    $('#filterGrupo').on('change', function() {
        const grupoId = $(this).val();
        filterByGrupo(grupoId);
    });

    function filterByGrupo(grupoId) {
        // Filtrar tabela de ativos+licenciados
        tableAtivos.rows().every(function() {
            const row = this.node();
            const $row = $(row);
            const rowGrupoId = $row.data('grupo-id');
            
            if (!grupoId || rowGrupoId == grupoId) {
                $row.show();
            } else {
                $row.hide();
            }
        });

        // Filtrar tabela de demais
        tableDemais.rows().every(function() {
            const row = this.node();
            const $row = $(row);
            const rowGrupoId = $row.data('grupo-id');
            
            if (!grupoId || rowGrupoId == grupoId) {
                $row.show();
            } else {
                $row.hide();
            }
        });

        // Filtrar cards mobile
        $('.user-card-mobile').each(function() {
            const $card = $(this);
            const cardGrupoId = $card.data('grupo-id');
            
            if (!grupoId || cardGrupoId == grupoId) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    }

    function filterUsers() {
        const status = $('#filterStatus').val();
        const permission = $('#filterPermission').val();
        const grupo = $('#filterGrupo').val();

        // Aplicar filtros em ambas as tabelas
        [tableAtivos, tableDemais].forEach(table => {
            table.rows().every(function() {
                const row = this.node();
                const $row = $(row);
                const isActive = $row.find('.toggle-ativo').is(':checked');
                const permissionText = $row.find('.badge').text().toLowerCase();
                const rowGrupoId = $row.data('grupo-id');
                
                let show = true;

                if (status === 'ativo' && !isActive) show = false;
                if (status === 'inativo' && isActive) show = false;
                if (permission && !permissionText.includes($('#filterPermission option:selected').text().toLowerCase())) show = false;
                if (grupo && rowGrupoId != grupo) show = false;

                if (show) $row.show();
                else $row.hide();
            });
        });

        // Filtrar cards mobile
        $('.user-card-mobile').each(function() {
            const $card = $(this);
            const isActive = $card.find('.toggle-ativo').is(':checked');
            const permissionText = $card.find('.badge').text().toLowerCase();
            const cardGrupoId = $card.data('grupo-id');
            
            let show = true;

            if (status === 'ativo' && !isActive) show = false;
            if (status === 'inativo' && isActive) show = false;
            if (permission && !permissionText.includes($('#filterPermission option:selected').text().toLowerCase())) show = false;
            if (grupo && cardGrupoId != grupo) show = false;

            if (show) $card.show();
            else $card.hide();
        });
    }

    $('#filterStatus, #filterPermission').on('change', filterUsers);

    window.resetFilters = function() {
        $('#searchInput').val('');
        $('#filterStatus').val('');
        $('#filterPermission').val('');
        $('#filterGrupo').val('');
        tableAtivos.search('').draw();
        tableDemais.search('').draw();
        $('.user-card-mobile').show();
    };

    // Toggle Ativo
    $('.toggle-ativo').on('change', async function() {
        const checkbox = $(this);
        const uid = checkbox.data('user-id');
        const ativo = checkbox.is(':checked') ? 1 : 0;

        addLoading(checkbox);

        try {
            const res = await ajaxPost({ userId: uid, field: 'ativo', value: ativo });
            if (res.status === 'success') {
                showToast(`Status ${ativo ? 'ativado' : 'inativado'} com sucesso!`, 'success');
                const label = checkbox.closest('.form-check').find('label');
                label.html(`<span class="status-indicator ${ativo ? 'status-active' : 'status-inactive'}"></span> ${ativo ? 'Ativo' : 'Inativo'}`);
                
                // Recarregar página após mudança de status para mover entre tabelas
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.message || 'Erro ao atualizar status', 'error');
                checkbox.prop('checked', !ativo);
            }
        } catch (error) {
            showToast('Erro de comunicação com servidor', 'error');
            checkbox.prop('checked', !ativo);
        } finally {
            removeLoading(checkbox);
        }
    });

    // Toggle Licença
    $('.toggle-licenca').on('change', async function() {
        const checkbox = $(this);
        const uid = checkbox.data('user-id');
        const licenca = checkbox.is(':checked') ? 1 : 0;

        addLoading(checkbox);

        try {
            const res = await ajaxPost({ userId: uid, field: 'licenca', value: licenca });
            if (res.status === 'success') {
                showToast(`Licença ${licenca ? 'ativada' : 'desativada'}!`, 'success');
                const label = checkbox.closest('.form-check').find('label');
                label.html(`<span class="status-indicator ${licenca ? 'status-active' : 'status-inactive'}"></span> ${licenca ? 'Licenciada' : 'Sem Licença'}`);
                
                // Recarregar página após mudança de licença para mover entre tabelas
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.message || 'Erro ao atualizar licença', 'error');
                checkbox.prop('checked', !licenca);
            }
        } catch (error) {
            showToast('Erro de comunicação', 'error');
            checkbox.prop('checked', !licenca);
        } finally {
            removeLoading(checkbox);
        }
    });

    // Grupo
    $('.grupo-select').on('change', async function() {
        const sel = $(this);
        const uid = sel.data('user-id');
        const value = sel.val();
        const prevValue = sel.data('prev');

        addLoading(sel);

        try {
            const res = await ajaxPost({ userId: uid, field: 'grupo', value: value });
            if (res.status === 'success') {
                showToast('Grupo atualizado com sucesso!', 'success');
                sel.data('prev', value);
                // Atualizar data-grupo-id na linha
                sel.closest('tr').attr('data-grupo-id', value);
            } else {
                showToast(res.message || 'Erro ao atualizar grupo', 'error');
                sel.val(prevValue);
            }
        } catch (error) {
            showToast('Erro de comunicação', 'error');
            sel.val(prevValue);
        } finally {
            removeLoading(sel);
        }
    });

    // Diretoria
    $('.diretoria-select').on('change', async function() {
        const sel = $(this);
        const uid = sel.data('user-id');
        const val = sel.val();
        const prevValue = sel.data('prev');
        const gerSel = sel.closest('tr').find('.gerencia-select');

        addLoading(sel);

        try {
            const res = await ajaxPost({ userId: uid, field: 'diretoria', value: val });
            if (res.status === 'success') {
                showToast('Diretoria atualizada com sucesso!', 'success');
                sel.data('prev', val);

                if (val) {
                    $.getJSON(`usr_admin_listar3c.php?ajax=1&action=get-gerencias&DiretoriaId=${val}`, data => {
                        gerSel.empty().append('<option value="">— Nenhum —</option>');
                        data.forEach(g => {
                            gerSel.append(`<option value="${g.GerenciaID}">${g.NomeGerencia}</option>`);
                        });
                        gerSel.prop('disabled', false);
                        gerSel.data('prev', '');
                    }).fail(() => {
                        showToast('Erro ao carregar gerências', 'error');
                    });
                } else {
                    gerSel.empty().append('<option value="">— Nenhum —</option>');
                    gerSel.prop('disabled', true);
                    gerSel.data('prev', '');
                }
            } else {
                showToast(res.message || 'Erro ao atualizar diretoria', 'error');
                sel.val(prevValue);
            }
        } catch (error) {
            showToast('Erro de comunicação', 'error');
            sel.val(prevValue);
        } finally {
            removeLoading(sel);
        }
    });

    // Gerência
    $('.gerencia-select').on('change', async function() {
        const sel = $(this);
        const uid = sel.data('user-id');
        const val = sel.val();
        const prevValue = sel.data('prev');
        const dirVal = sel.closest('tr').find('.diretoria-select').val();

        if (val && !dirVal) {
            showToast('Selecione primeiro uma diretoria', 'warning');
            sel.val("");
            return;
        }

        addLoading(sel);

        try {
            const res = await ajaxPost({ userId: uid, field: 'gerencia', value: val });
            if (res.status === 'success') {
                showToast('Gerência atualizada com sucesso!', 'success');
                sel.data('prev', val);
            } else {
                showToast(res.message || 'Erro ao atualizar gerência', 'error');
                sel.val(prevValue);
            }
        } catch (error) {
            showToast('Erro de comunicação', 'error');
            sel.val(prevValue);
        } finally {
            removeLoading(sel);
        }
    });

    $('select').each(function() {
        $(this).data('prev', $(this).val());
    });

    window.applyAdvancedFilters = function() {
        const status = $('#modalStatus').val();
        const licenca = $('#modalLicenca').val();
        const grupo = $('#modalGrupo').val();
        const diretoria = $('#modalDiretoria').val();
        
        $('#filterStatus').val(status);
        $('#filterGrupo').val(grupo);
        
        filterUsers();
        $('#filtroModal').modal('hide');
        showToast('Filtros aplicados', 'success');
    };
});
</script>
</body>
</html>
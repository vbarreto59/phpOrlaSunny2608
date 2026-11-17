<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php';

if (file_exists(__DIR__ . '/config_empresa.php')) { require_once __DIR__ . '/config_empresa.php'; }

if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

$themeDefaults = ['primary' => '#800020', 'primary_dark' => '#4B0C1D', 'bg_body' => '#f8f9fa', 'text_color' => '#1a1a2e', 'shadow_color' => '#1a1a2e'];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

$logoSistema = (!empty($_SESSION['Conf_ImgLogo'])) ? $_SESSION['Conf_ImgLogo'] : 'logoSunny66a.JPG';
$empresaId = $_SESSION['IdEmp'] ?? 1;
$nomeEmpresa = $_SESSION['Empresa'] ?? 'Minha Imobiliária';
$usuarioId = $_SESSION['UserId'] ?? 0;
$nomeUsuario = $_SESSION['Usuario'] ?? 'Sistema';

$acao = $_GET['acao'] ?? 'listar';
$id = $_GET['id'] ?? null;
$mensagem = "";
$tipoMsg = "success";

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

function getBaseUrl() {
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if ($scriptDir === '.' || $scriptDir === '\\' || $scriptDir === '') $scriptDir = '/';
    $scriptDir = rtrim($scriptDir, '/');
    return $protocol . '://' . $host . $scriptDir . '/';
}

function normalizeFotoUrl($foto) {
    $placeholder = 'https://placehold.co/600x400/e2e8f0/64748b?text=Sem+Foto';
    if (empty($foto)) return $placeholder;

    $foto = trim($foto);
    if ($foto === '') return $placeholder;

    if (preg_match('#^https?://#i', $foto)) return $foto;

    // Normaliza separadores e corrige caso o banco tenha salvo "captacao" no singular
    $foto = str_replace('\\', '/', $foto);
    $foto = str_replace('captacao/', 'captacoes/', $foto);

    $dirReal = str_replace('\\', '/', __DIR__);
    if (strpos($foto, $dirReal) === 0) {
        $foto = ltrim(substr($foto, strlen($dirReal)), '/');
    }

    while (strpos($foto, './') === 0) {
        $foto = substr($foto, 2);
    }

    if (strpos($foto, '/') === 0) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . $foto;
    }

    return getBaseUrl() . $foto;
}

function uploadFotos($files, $imovelId) {
    // CORRIGIDO PARA O PLURAL: captacoes
    $pastaDestino = __DIR__ . '/captacoes/' . $imovelId . '/';
    
    if (!is_dir($pastaDestino)) { 
        if (!@mkdir($pastaDestino, 0777, true)) {
            throw new Exception("Não foi possível criar a pasta de destino: $pastaDestino");
        }
    }
    
    if (!is_writable($pastaDestino)) {
        throw new Exception("A pasta não tem permissão de escrita: $pastaDestino");
    }

    $caminhos = [];
    $limite = min(count($files['name']), 10);
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

    for ($i = 0; $i < $limite; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $extensao = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($extensao, $permitidas, true)) continue;

        $novoNome = uniqid() . '.' . $extensao;
        if (move_uploaded_file($files['tmp_name'][$i], $pastaDestino . $novoNome)) {
            // CORRIGIDO PARA O PLURAL: captacoes
            $caminhos[] = 'captacoes/' . $imovelId . '/' . $novoNome;
        }
    }
    return $caminhos;
}

function limparMoeda($valor) {
    if (empty($valor)) return null;
    $valorLimpo = str_replace(['R$', '.', ' '], '', $valor);
    $valorLimpo = str_replace(',', '.', $valorLimpo);
    return (float) $valorLimpo;
}

function formatarInputMoeda($valor) {
    if (!$valor) return '';
    return number_format($valor, 2, ',', '.');
}

/**
 * BUSCA AS FOTOS NO BANCO E TAMBÉM FISICAMENTE NA PASTA
 */
function getArrayFotos($linkFicha, $imovelId = null) {
    $placeholder = 'https://placehold.co/600x400/e2e8f0/64748b?text=Sem+Foto';
    $normalizadas = [];

    // 1. Tenta pegar do Banco de Dados (LinkFicha)
    if (!empty($linkFicha)) {
        $fotos = json_decode($linkFicha, true);
        if (!is_array($fotos)) {
            $fotos = [$linkFicha];
        }

        foreach ($fotos as $foto) {
            if (is_array($foto)) {
                $foto = $foto['url'] ?? $foto['caminho'] ?? $foto['foto'] ?? '';
            }
            if (!empty($foto) && is_string($foto)) {
                // Verifica se o arquivo realmente existe para não quebrar a imagem
                $caminhoFisico = __DIR__ . '/' . ltrim(str_replace('captacao/', 'captacoes/', $foto), '/');
                if (file_exists($caminhoFisico)) {
                    $normalizadas[] = normalizeFotoUrl($foto);
                }
            }
        }
    }

    // 2. Se não achou nada no banco (ou se o arquivo não existe), VAI NA PASTA FÍSICA
    if (empty($normalizadas) && $imovelId) {
        $pastaFisica = __DIR__ . '/captacoes/' . $imovelId . '/';
        if (is_dir($pastaFisica)) {
            $arquivos = scandir($pastaFisica);
            $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($arquivos as $arq) {
                if ($arq === '.' || $arq === '..') continue;
                $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
                if (in_array($ext, $permitidas)) {
                    $normalizadas[] = getBaseUrl() . 'captacoes/' . $imovelId . '/' . $arq;
                }
            }
        }
    }

    return count($normalizadas) > 0 ? $normalizadas : [$placeholder];
}

// ============================================================
// PROCESSAMENTO DE AÇÕES (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['excluir_id'])) {
        try {
            $stmt = $db->prepare("DELETE FROM empreendimento WHERE Empreend_ID = ? AND Origem = 'CAPTACAO' AND Empresa_ID = ?");
            $stmt->execute([$_POST['excluir_id'], $empresaId]);
            $mensagem = "Captação excluída com sucesso!";
            $acao = 'listar';
        } catch (PDOException $e) {
            $mensagem = "Erro ao excluir: " . $e->getMessage(); $tipoMsg = "danger";
        }
    }
    elseif (isset($_POST['salvar'])) {
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
            
            'ValorDesejado' => limparMoeda($_POST['ValorDesejado'] ?? ''),
            'ValorMercado' => limparMoeda($_POST['ValorMercado'] ?? ''),
            'ValorCond' => limparMoeda($_POST['ValorCond'] ?? ''),
            'ValorIPTU' => limparMoeda($_POST['ValorIPTU'] ?? ''),
            'ValorAgua' => limparMoeda($_POST['ValorAgua'] ?? ''),
            'ValorEnergia' => limparMoeda($_POST['ValorEnergia'] ?? ''),
            
            'TaxaExtra' => $_POST['TaxaExtra'] ?? null,
            'EmpresaGestora' => mb_strtoupper(trim($_POST['EmpresaGestora'] ?? ''), 'UTF-8'),
            'PontoForte' => $_POST['PontoForte'] ?? null,
            'PontoFraco' => $_POST['PontoFraco'] ?? null,
            'Descricao' => $_POST['Descricao'] ?? null,
            'Obs' => $_POST['Obs'] ?? null,
            
            'Latitude' => trim($_POST['Latitude'] ?? ''),
            'Longitude' => trim($_POST['Longitude'] ?? ''),
            'LinkVideo' => trim($_POST['LinkVideo'] ?? '')
        ];

        try {
            if (empty($_POST['Empreend_ID'])) {
                $sql = "INSERT INTO empreendimento (Empresa_ID, Usuario_ID, NomeEmpresa, Origem, is_teste, " . implode(', ', array_keys($dados)) . ", DataCadastro, UsuarioCad) 
                        VALUES (?, ?, ?, 'CAPTACAO', 0, " . rtrim(str_repeat('?, ', count($dados)), ', ') . ", NOW(), ?)";
                $stmt = $db->prepare($sql);
                $parametros = array_merge([$empresaId, $usuarioId, $nomeEmpresa], array_values($dados), [$nomeUsuario]);
                $stmt->execute($parametros);
                $id = $db->lastInsertId();
                $mensagem = "Captação cadastrada com sucesso!";
            } else {
                $id = $_POST['Empreend_ID'];
                $sets = [];
                foreach ($dados as $key => $val) { $sets[] = "$key = ?"; }
                $sql = "UPDATE empreendimento SET " . implode(', ', $sets) . ", DataAtualizacao = NOW(), UsuarioAtu = ? 
                        WHERE Empreend_ID = ? AND Origem = 'CAPTACAO' AND Empresa_ID = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge(array_values($dados), [$nomeUsuario, $id, $empresaId]));
                $mensagem = "Captação atualizada com sucesso!";
            }

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
            $acao = 'listar';
        } catch (Exception $e) {
            $mensagem = "Erro ao salvar: " . $e->getMessage(); $tipoMsg = "danger";
        }
    }
}

// ============================================================
// BUSCAR DADOS PARA EDIÇÃO
// ============================================================
$dadosEdicao = null;
if ($acao === 'editar' && $id) {
    $stmt = $db->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = ? AND Origem = 'CAPTACAO' AND Empresa_ID = ?");
    $stmt->execute([$id, $empresaId]);
    $dadosEdicao = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dadosEdicao) { $mensagem = "Imóvel não encontrado."; $tipoMsg = "danger"; $acao = 'listar'; }
}

// ============================================================
// BUSCAR DADOS PARA LISTAGEM
// ============================================================
if ($acao === 'listar') {
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $limite = 12; if ($pagina < 1) $pagina = 1;
    $offset = ($pagina - 1) * $limite;

    $filtroTipo = $_GET['tipo'] ?? '';
    $filtroLocalidade = trim($_GET['localidade'] ?? '');

    $where = ["Origem = 'CAPTACAO'", "Empresa_ID = ?"];
    $params = [$empresaId];

    if (!empty($filtroTipo)) { $where[] = "TipoImovelCaptacao = ?"; $params[] = $filtroTipo; }
    if (!empty($filtroLocalidade)) {
        $where[] = "(Localidade LIKE ? OR Localizacao LIKE ?)";
        $params[] = "%{$filtroLocalidade}%"; $params[] = "%{$filtroLocalidade}%";
    }

    $whereSql = implode(" AND ", $where);
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM empreendimento WHERE $whereSql");
    $stmtCount->execute($params);
    $totalImoveis = $stmtCount->fetchColumn();
    $totalPaginas = ceil($totalImoveis / $limite);

    $sql = "SELECT * FROM empreendimento WHERE $whereSql ORDER BY Empreend_ID DESC LIMIT $limite OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formataPreco($valor) { return $valor ? 'R$ ' . number_format($valor, 2, ',', '.') : 'Consulte'; }
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Captações | <?= htmlspecialchars($nomeEmpresa) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: <?= $themeSafe['primary'] ?>; --primary-dark: <?= $themeSafe['primary_dark'] ?>; --text-color: <?= $themeSafe['text_color'] ?>; --shadow-color: <?= $themeSafe['shadow_color'] ?>; --success: #16a34a; --danger: #dc2626; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; color: var(--text-color); padding-bottom: 3rem; }
        
        /* Navbar */
        .navbar-custom { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 0.75rem 0; margin-bottom: 2rem; }
        .navbar-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-img { height: 55px; width: auto; max-width: 200px; object-fit: contain; }
        .logo-text { font-weight: 800; font-size: 1.4rem; color: var(--primary); margin: 0; }

        /* Cards de Imóveis */
        .property-card { background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; }
        .property-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: var(--primary); }
        .card-img-wrapper { position: relative; padding-top: 66.66%; background: #e2e8f0; overflow: hidden; }
        .slider-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .slider-img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.3s, transform 0.3s; }
        .slider-img.active { opacity: 1; }
        .slider-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10; opacity: 0; transition: all 0.2s; }
        .property-card:hover .slider-btn { opacity: 1; }
        .prev-btn { left: 10px; } .next-btn { right: 10px; }
        
        .badge-tipo { position: absolute; top: 12px; left: 12px; background: rgba(0,0,0,0.7); color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; z-index: 5; }
        .badge-teste { position: absolute; top: 12px; right: 12px; background: #dc2626; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; z-index: 6; text-transform: uppercase; border: 2px solid #fff; }
        .badge-fotos { position: absolute; bottom: 12px; right: 12px; background: rgba(0,0,0,0.7); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; z-index: 5; }
        
        .card-body-custom { padding: 1.25rem; display: flex; flex-direction: column; flex: 1; }
        .property-price { font-size: 1.4rem; font-weight: 800; color: var(--primary); }
        .property-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .property-location { font-size: 0.85rem; color: #64748b; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 5px; }
        .property-captador { font-size: 0.8rem; color: #64748b; margin-bottom: 1rem; display: flex; align-items: center; gap: 5px; font-weight: 500; }
        .property-features { display: flex; gap: 1rem; border-top: 1px solid #e2e8f0; padding-top: 1rem; margin-top: auto; font-size: 0.85rem; }
        .property-features i { color: var(--primary); }

        /* Formulário */
        .card-neo { background: #fff; border: 3px solid var(--shadow-color); border-radius: 16px; box-shadow: 6px 6px 0 0 var(--shadow-color); overflow: hidden; margin-bottom: 2rem; }
        .card-neo .card-header { background: var(--primary); color: #fff; border-bottom: 3px solid var(--shadow-color); padding: 1rem 1.5rem; font-weight: 900; display: flex; justify-content: space-between; }
        .field-label { display: flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #4b5563; margin-bottom: 6px; }
        .form-control, .form-select { border: 2.5px solid var(--shadow-color); border-radius: 10px; padding: 8px 12px; font-weight: 600; font-size: 0.9rem; box-shadow: 2px 2px 0 0 var(--shadow-color); }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 3px 3px 0 0 var(--shadow-color); }
        
        .btn-neo { padding: 8px 16px; border: 2.5px solid var(--shadow-color); border-radius: 10px; box-shadow: 3px 3px 0 var(--shadow-color); font-weight: 800; font-size: 0.8rem; text-transform: uppercase; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.15s; }
        .btn-neo:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 var(--shadow-color); }
        .btn-neo.primary { background: var(--primary); color: #fff; }
        .btn-neo.success { background: var(--success); color: #fff; }
        .btn-neo.danger { background: var(--danger); color: #fff; }
        .btn-neo.secondary { background: #fff; color: #1a1a2e; }
        .btn-neo.sm { padding: 4px 10px; font-size: 0.7rem; }
        
        .nav-tabs { border-bottom: 3px solid var(--shadow-color); gap: 4px; }
        .nav-tabs .nav-link { border: 2.5px solid transparent; border-bottom: none; border-radius: 10px 10px 0 0; font-weight: 800; font-size: 0.85rem; color: #6b7280; padding: 8px 16px; margin-bottom: -3px; }
        .nav-tabs .nav-link.active { background: #fff; border-color: var(--shadow-color); border-bottom: 3px solid #fff; color: var(--primary); box-shadow: 0 -3px 0 var(--shadow-color); }
        
        .alert-neo { border: 3px solid; border-radius: 12px; padding: 1rem; font-weight: 700; margin-bottom: 1.5rem; box-shadow: 4px 4px 0; }
        .alert-neo.success { background: #d1fae5; color: #065f46; border-color: #065f46; }
        .alert-neo.danger { background: #fee2e2; color: #991b1b; border-color: #991b1b; }

        /* ESTILOS MOBILE */
        @media (max-width: 767.98px) {
            body { padding-bottom: 90px; }
            .mobile-sticky-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 10px 15px; box-shadow: 0 -4px 15px rgba(0,0,0,0.15); border-top: 3px solid var(--shadow-color); z-index: 1050; display: flex; justify-content: space-between; gap: 10px; }
            .mobile-sticky-bar .btn-neo { flex: 1; justify-content: center; margin: 0; }
            .btn-close-top { display: none !important; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="listar_captacoes.php">
            <img src="<?= htmlspecialchars($logoSistema) ?>" alt="Logo" class="logo-img">
            <h1 class="logo-text d-none d-sm-block"><?= htmlspecialchars($nomeEmpresa) ?></h1>
        </a>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($acao === 'listar'): ?>
                <a href="listar_captacoes.php?acao=criar" class="btn-neo success"><i class="fas fa-plus-circle"></i> <span class="d-none d-md-inline">Nova Captação</span></a>
            <?php else: ?>
                <a href="listar_captacoes.php" class="btn-neo secondary"><i class="fas fa-arrow-left"></i> <span class="d-none d-md-inline">Voltar à Vitrine</span></a>
            <?php endif; ?>
            
            <button onclick="window.close();" class="btn-neo danger ms-2 btn-close-top"><i class="fas fa-times"></i> Fechar Aba</button>
        </div>
    </div>
</nav>

<div class="container">
    <?php if ($mensagem): ?>
        <div class="alert-neo <?= $tipoMsg ?>">
            <i class="fas <?= $tipoMsg == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i> <?= $mensagem ?>
        </div>
    <?php endif; ?>

    <?php if ($acao === 'listar'): ?>
        <!-- ================= MODO LISTAGEM ================= -->
        
        <div class="filter-bar bg-white p-3 rounded-3 shadow-sm mb-4 border">
            <form method="GET" action="listar_captacoes.php" class="row g-2">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="localidade" placeholder="Bairro ou Cidade..." value="<?= htmlspecialchars($filtroLocalidade ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="tipo">
                        <option value="">Todos os Tipos</option>
                        <option value="Apartamento" <?= ($filtroTipo ?? '') == 'Apartamento' ? 'selected' : '' ?>>Apartamento</option>
                        <option value="Casa" <?= ($filtroTipo ?? '') == 'Casa' ? 'selected' : '' ?>>Casa</option>
                        <option value="Cobertura" <?= ($filtroTipo ?? '') == 'Cobertura' ? 'selected' : '' ?>>Cobertura</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="listar_captacoes.php" class="btn btn-outline-secondary w-100 fw-bold">Limpar</a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0" style="color: #1e293b;"><?= $totalImoveis ?> imóveis encontrados</h4>
        </div>

        <?php if (count($imoveis) > 0): ?>
            <div class="row g-4">
                <?php foreach ($imoveis as $imovel): 
                    // Passa o ID para a função buscar na pasta física!
                    $fotos = getArrayFotos($imovel['LinkFicha'], $imovel['Empreend_ID']);
                    $qtdFotos = count($fotos);
                    $preco = $imovel['ValorDesejado'] ?? $imovel['ValorMercado'] ?? null;
                    $isTeste = isset($imovel['is_teste']) && $imovel['is_teste'] == 1;
                    
                    $temMapa = (!empty($imovel['Latitude']) && !empty($imovel['Longitude']));
                    $temVideo = (!empty($imovel['LinkVideo']));
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="property-card">
                        <div class="card-img-wrapper">
                            <div class="slider-container">
                                <?php foreach ($fotos as $index => $foto): ?>
                                    <img src="<?= htmlspecialchars($foto) ?>" class="slider-img <?= $index === 0 ? 'active' : '' ?>" alt="<?= htmlspecialchars($imovel['NomeEmpreendimento']) ?>" loading="lazy" onerror="this.onerror=null; this.src='https://placehold.co/600x400/e2e8f0/64748b?text=Erro+na+Imagem';">
                                <?php endforeach; ?>
                            </div>
                            <?php if ($qtdFotos > 1): ?>
                                <button type="button" class="slider-btn prev-btn" onclick="mudarFoto(event, this, -1)"><i class="fas fa-chevron-left"></i></button>
                                <button type="button" class="slider-btn next-btn" onclick="mudarFoto(event, this, 1)"><i class="fas fa-chevron-right"></i></button>
                            <?php endif; ?>
                            
                            <span class="badge-tipo"><?= htmlspecialchars($imovel['TipoImovelCaptacao'] ?? 'Imóvel') ?></span>
                            <?php if($isTeste): ?><span class="badge-teste">MODO TESTE</span><?php endif; ?>
                            <?php if ($qtdFotos > 1): ?><span class="badge-fotos"><i class="fas fa-camera"></i> <?= $qtdFotos ?></span><?php endif; ?>
                        </div>
                        <div class="card-body-custom">
                            <div class="property-price"><?= formataPreco($preco) ?></div>
                            <h5 class="property-title"><?= htmlspecialchars($imovel['NomeEmpreendimento'] ?? 'Imóvel sem título') ?></h5>
                            
                            <div class="property-location">
                                <i class="fas fa-map-marker-alt" style="color: #94a3b8;"></i>
                                <?= htmlspecialchars($imovel['Localidade'] ?? $imovel['Localizacao'] ?? 'Localização não informada') ?>
                            </div>

                            <?php if (!empty($imovel['CaptadorNome'])): ?>
                                <div class="property-captador">
                                    <i class="fas fa-user-tie text-primary"></i> Captador: <strong><?= htmlspecialchars($imovel['CaptadorNome']) ?></strong>
                                </div>
                            <?php endif; ?>

                            <div class="property-features mb-3">
                                <?php if (!empty($imovel['MetragemTotal'])): ?><span><i class="fas fa-ruler-combined"></i> <?= number_format($imovel['MetragemTotal'], 0, ',', '.') ?> m²</span><?php endif; ?>
                                <?php if (!empty($imovel['Quartos'])): ?><span><i class="fas fa-bed"></i> <?= htmlspecialchars($imovel['Quartos']) ?> Qts</span><?php endif; ?>
                                <?php if (!empty($imovel['WCTotal'])): ?><span><i class="fas fa-bath"></i> <?= htmlspecialchars($imovel['WCTotal']) ?> WC</span><?php endif; ?>
                            </div>

                            <?php if ($temMapa || $temVideo): ?>
                                <div class="d-flex gap-2 mb-3 flex-wrap">
                                    <?php if ($temMapa): ?>
                                        <a href="https://www.google.com/maps?q=<?= $imovel['Latitude'] ?>,<?= $imovel['Longitude'] ?>" target="_blank" class="btn btn-outline-danger btn-sm fw-bold" style="font-size: 0.75rem;">
                                            <i class="fas fa-map-marked-alt"></i> Ver no Mapa
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($temVideo): 
                                        $videoUrl = $imovel['LinkVideo'];
                                        if (!preg_match('/^https?:\/\//i', $videoUrl)) { $videoUrl = 'https://' . $videoUrl; }
                                    ?>
                                        <a href="<?= htmlspecialchars($videoUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm fw-bold" style="font-size: 0.75rem;">
                                            <i class="fas fa-play-circle"></i> Assistir Vídeo
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2 border-top pt-3 mt-auto">
                                <a href="listar_captacoes.php?acao=editar&id=<?= $imovel['Empreend_ID'] ?>" class="btn btn-warning btn-sm w-50 fw-bold text-dark"><i class="fas fa-edit"></i> Editar</a>
                                <form method="POST" class="w-50" onsubmit="return confirm('Excluir esta captação?');">
                                    <input type="hidden" name="excluir_id" value="<?= $imovel['Empreend_ID'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold"><i class="fas fa-trash"></i> Excluir</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5 my-5 bg-white rounded-3 shadow-sm border">
                <i class="fas fa-search-location fa-4x text-muted mb-3" style="opacity: 0.3;"></i>
                <h4 class="fw-bold text-dark">Nenhum imóvel encontrado</h4>
                <a href="listar_captacoes.php?acao=criar" class="btn btn-success mt-2 fw-bold"><i class="fas fa-plus-circle"></i> Cadastrar Nova Captação</a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ================= MODO FORMULÁRIO (CRIAR/EDITAR) ================= -->
        
        <div class="card-neo">
            <div class="card-header">
                <span><i class="fas fa-home"></i> <?= $acao === 'editar' ? 'Editar Captação #' . $id : 'Nova Captação' ?></span>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" id="formCaptacao">
                    <input type="hidden" name="Empreend_ID" value="<?= $dadosEdicao['Empreend_ID'] ?? '' ?>">
                    <input type="hidden" name="salvar" value="1">

                    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-imovel" type="button">Imóvel</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-proprietario" type="button">Proprietário / Captador</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-valores" type="button">Valores</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-links" type="button">Links / Localização</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-fotos" type="button">Fotos</button></li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        <!-- ABA 1 -->
                        <div class="tab-pane fade show active" id="tab-imovel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="field-label">Nome do Imóvel <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="NomeEmpreendimento" value="<?= htmlspecialchars($dadosEdicao['NomeEmpreendimento'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Tipo</label>
                                    <select class="form-select" name="TipoImovelCaptacao">
                                        <option value="Apartamento" <?= ($dadosEdicao['TipoImovelCaptacao'] ?? '') == 'Apartamento' ? 'selected' : '' ?>>Apartamento</option>
                                        <option value="Casa" <?= ($dadosEdicao['TipoImovelCaptacao'] ?? '') == 'Casa' ? 'selected' : '' ?>>Casa</option>
                                        <option value="Cobertura" <?= ($dadosEdicao['TipoImovelCaptacao'] ?? '') == 'Cobertura' ? 'selected' : '' ?>>Cobertura</option>
                                        <option value="Flat" <?= ($dadosEdicao['TipoImovelCaptacao'] ?? '') == 'Flat' ? 'selected' : '' ?>>Flat</option>
                                        <option value="Studio" <?= ($dadosEdicao['TipoImovelCaptacao'] ?? '') == 'Studio' ? 'selected' : '' ?>>Studio</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Condição</label>
                                    <select class="form-select" name="CondicaoImovel">
                                        <option value="Usado" <?= ($dadosEdicao['CondicaoImovel'] ?? '') == 'Usado' ? 'selected' : '' ?>>Usado</option>
                                        <option value="Novo" <?= ($dadosEdicao['CondicaoImovel'] ?? '') == 'Novo' ? 'selected' : '' ?>>Novo</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="field-label">Bairro / Localidade <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="Localidade" value="<?= htmlspecialchars($dadosEdicao['Localidade'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="field-label">Endereço Completo</label>
                                    <input type="text" class="form-control" name="Localizacao" value="<?= htmlspecialchars($dadosEdicao['Localizacao'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="field-label">Quartos</label>
                                    <input type="text" class="form-control" name="Quartos" value="<?= htmlspecialchars($dadosEdicao['Quartos'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="field-label">Suítes</label>
                                    <input type="number" class="form-control" name="Suites" value="<?= htmlspecialchars($dadosEdicao['Suites'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="field-label">WC Total</label>
                                    <input type="number" class="form-control" name="WCTotal" value="<?= htmlspecialchars($dadosEdicao['WCTotal'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="field-label">Área (m²)</label>
                                    <input type="number" step="0.01" class="form-control" name="MetragemTotal" value="<?= htmlspecialchars($dadosEdicao['MetragemTotal'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="field-label">Vagas</label>
                                    <input type="number" class="form-control" name="VagasGaragem" value="<?= htmlspecialchars($dadosEdicao['VagasGaragem'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="field-label">Ano Constr.</label>
                                    <input type="number" class="form-control" name="AnoEntrega" value="<?= htmlspecialchars($dadosEdicao['AnoEntrega'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Andar</label>
                                    <input type="text" class="form-control" name="Andar" value="<?= htmlspecialchars($dadosEdicao['Andar'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Unidade</label>
                                    <input type="text" class="form-control" name="Unidade" value="<?= htmlspecialchars($dadosEdicao['Unidade'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Estado Conservação</label>
                                    <select class="form-select" name="EstadoConservacao">
                                        <option value="">Selecione...</option>
                                        <option value="Ruim" <?= ($dadosEdicao['EstadoConservacao'] ?? '') == 'Ruim' ? 'selected' : '' ?>>Ruim</option>
                                        <option value="Regular" <?= ($dadosEdicao['EstadoConservacao'] ?? '') == 'Regular' ? 'selected' : '' ?>>Regular</option>
                                        <option value="Bom" <?= ($dadosEdicao['EstadoConservacao'] ?? '') == 'Bom' ? 'selected' : '' ?>>Bom</option>
                                        <option value="Ótimo" <?= ($dadosEdicao['EstadoConservacao'] ?? '') == 'Ótimo' ? 'selected' : '' ?>>Ótimo</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="PossuiElevador" value="1" <?= !empty($dadosEdicao['PossuiElevador']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold">Possui Elevador</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ABA 2 -->
                        <div class="tab-pane fade" id="tab-proprietario">
                            <h6 class="fw-bold text-muted mb-3 border-bottom pb-2">DADOS DO PROPRIETÁRIO</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4"><label class="field-label">Nome</label><input type="text" class="form-control" name="ProprietarioNome" value="<?= htmlspecialchars($dadosEdicao['ProprietarioNome'] ?? '') ?>"></div>
                                <div class="col-md-4"><label class="field-label">Telefone</label><input type="text" class="form-control" name="ProprietarioTelefone" value="<?= htmlspecialchars($dadosEdicao['ProprietarioTelefone'] ?? '') ?>"></div>
                                <div class="col-md-4"><label class="field-label">E-mail</label><input type="email" class="form-control" name="ProprietarioEmail" value="<?= htmlspecialchars($dadosEdicao['ProprietarioEmail'] ?? '') ?>"></div>
                            </div>
                            <h6 class="fw-bold text-muted mb-3 border-bottom pb-2 mt-4">DADOS DO CAPTADOR</h6>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="field-label">Nome do Captador</label><input type="text" class="form-control" name="CaptadorNome" value="<?= htmlspecialchars($dadosEdicao['CaptadorNome'] ?? '') ?>"></div>
                                <div class="col-md-4"><label class="field-label">Telefone</label><input type="text" class="form-control" name="CaptadorTelefone" value="<?= htmlspecialchars($dadosEdicao['CaptadorTelefone'] ?? '') ?>"></div>
                                <div class="col-md-2"><label class="field-label">% Captador</label><input type="number" step="0.01" class="form-control" name="PercentualCaptador" value="<?= htmlspecialchars($dadosEdicao['PercentualCaptador'] ?? '') ?>"></div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="Exclusividade" value="1" <?= !empty($dadosEdicao['Exclusividade']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold">Exclusividade</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ABA 3 -->
                        <div class="tab-pane fade" id="tab-valores">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="field-label">Valor Desejado (R$)</label>
                                    <input type="text" class="form-control money" name="ValorDesejado" value="<?= formatarInputMoeda($dadosEdicao['ValorDesejado'] ?? '') ?>" placeholder="0,00">
                                </div>
                                <div class="col-md-4">
                                    <label class="field-label">Valor Mercado (R$)</label>
                                    <input type="text" class="form-control money" name="ValorMercado" value="<?= formatarInputMoeda($dadosEdicao['ValorMercado'] ?? '') ?>" placeholder="0,00">
                                </div>
                                <div class="col-md-4">
                                    <label class="field-label">Condomínio (R$)</label>
                                    <input type="text" class="form-control money" name="ValorCond" value="<?= formatarInputMoeda($dadosEdicao['ValorCond'] ?? '') ?>" placeholder="0,00">
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">IPTU (R$)</label>
                                    <input type="text" class="form-control money" name="ValorIPTU" value="<?= formatarInputMoeda($dadosEdicao['ValorIPTU'] ?? '') ?>" placeholder="0,00">
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Água (R$)</label>
                                    <input type="text" class="form-control money" name="ValorAgua" value="<?= formatarInputMoeda($dadosEdicao['ValorAgua'] ?? '') ?>" placeholder="0,00">
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Energia (R$)</label>
                                    <input type="text" class="form-control money" name="ValorEnergia" value="<?= formatarInputMoeda($dadosEdicao['ValorEnergia'] ?? '') ?>" placeholder="0,00">
                                </div>
                                <div class="col-md-3">
                                    <label class="field-label">Taxa Extra</label>
                                    <input type="text" class="form-control" name="TaxaExtra" value="<?= htmlspecialchars($dadosEdicao['TaxaExtra'] ?? '') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="field-label">Empresa Gestora</label>
                                    <input type="text" class="form-control" name="EmpresaGestora" value="<?= htmlspecialchars($dadosEdicao['EmpresaGestora'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- ABA 4: LINKS E LOCALIZAÇÃO -->
                        <div class="tab-pane fade" id="tab-links">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="field-label">Latitude</label>
                                    <input type="text" class="form-control" name="Latitude" id="Latitude" value="<?= htmlspecialchars($dadosEdicao['Latitude'] ?? '') ?>" placeholder="Cole aqui ou digite Ex: -8.1265">
                                    <small class="text-muted d-block mt-1">Dica: Cole as coordenadas completas aqui (-8.123, -35.123) e a Longitude será preenchida automaticamente.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="field-label">Longitude</label>
                                    <input type="text" class="form-control" name="Longitude" id="Longitude" value="<?= htmlspecialchars($dadosEdicao['Longitude'] ?? '') ?>" placeholder="Ex: -34.9041">
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button type="button" class="btn btn-outline-secondary fw-bold" onclick="abrirMapa()">
                                        <i class="fas fa-map-marker-alt text-danger"></i> Abrir no Google Maps
                                    </button>
                                </div>
                                
                                <hr class="my-4">

                                <div class="col-md-12">
                                    <label class="field-label">Link do Vídeo (YouTube, Vimeo, Drive, etc)</label>
                                    <input type="url" class="form-control" name="LinkVideo" id="LinkVideo" value="<?= htmlspecialchars($dadosEdicao['LinkVideo'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button type="button" class="btn btn-outline-primary fw-bold" onclick="abrirVideo()">
                                        <i class="fas fa-play"></i> Assistir Vídeo
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ABA 5 -->
                        <div class="tab-pane fade" id="tab-fotos">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6"><label class="field-label text-success">Ponto Forte</label><textarea class="form-control" name="PontoForte" rows="3"><?= htmlspecialchars($dadosEdicao['PontoForte'] ?? '') ?></textarea></div>
                                <div class="col-md-6"><label class="field-label text-danger">Ponto Fraco</label><textarea class="form-control" name="PontoFraco" rows="3"><?= htmlspecialchars($dadosEdicao['PontoFraco'] ?? '') ?></textarea></div>
                                <div class="col-md-6"><label class="field-label">Descrição Geral</label><textarea class="form-control" name="Descricao" rows="3"><?= htmlspecialchars($dadosEdicao['Descricao'] ?? '') ?></textarea></div>
                                <div class="col-md-6"><label class="field-label">Observações Internas</label><textarea class="form-control" name="Obs" rows="3"><?= htmlspecialchars($dadosEdicao['Obs'] ?? '') ?></textarea></div>
                            </div>
                            <div class="border-top pt-4">
                                <label class="field-label mb-2"><i class="fas fa-images"></i> Fotos do Imóvel (Máx 10)</label>
                                <input class="form-control" type="file" name="fotos[]" multiple accept="image/*">
                                <?php if (!empty($dadosEdicao['LinkFicha']) || $dadosEdicao): 
                                    $fotosEdicao = getArrayFotos($dadosEdicao['LinkFicha'] ?? null, $dadosEdicao['Empreend_ID'] ?? null);
                                    if (count($fotosEdicao) > 0 && strpos($fotosEdicao[0], 'placehold') === false): ?>
                                    <div class="mt-3 d-flex flex-wrap gap-2">
                                        <?php foreach ($fotosEdicao as $foto): ?>
                                            <img src="<?= htmlspecialchars($foto) ?>" class="img-thumbnail" style="height: 80px; width: 100px; object-fit: cover; border: 2px solid #1a1a2e;" onerror="this.onerror=null; this.src='https://placehold.co/100x80?text=Erro';">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RODAPÉ DESKTOP -->
                    <div class="d-none d-md-flex justify-content-between gap-2 mt-5 pt-3 border-top flex-wrap">
                        <div class="d-flex gap-2">
                            <a href="listar_captacoes.php" class="btn-neo secondary"><i class="fas fa-times"></i> Cancelar</a>
                            <button type="button" onclick="window.close();" class="btn-neo danger"><i class="fas fa-power-off"></i> Fechar Aba</button>
                        </div>
                        <button type="submit" class="btn-neo success px-5"><i class="fas fa-save"></i> Salvar Captação</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ================= BARRA MOBILE (FIXA NO RODAPÉ) ================= -->
<div class="mobile-sticky-bar d-md-none">
    <?php if ($acao === 'listar'): ?>
        <a href="listar_captacoes.php?acao=criar" class="btn-neo success"><i class="fas fa-plus-circle"></i> Nova</a>
        <button onclick="window.close();" class="btn-neo danger"><i class="fas fa-times"></i> Fechar</button>
    <?php else: ?>
        <button type="submit" form="formCaptacao" class="btn-neo success"><i class="fas fa-save"></i> Salvar</button>
        <button type="button" onclick="window.close();" class="btn-neo danger"><i class="fas fa-times"></i> Fechar</button>
    <?php endif; ?>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script>
    $(document).ready(function() {
        $('.money').mask('000.000.000.000.000,00', {reverse: true});

        const campoLatitude = document.getElementById('Latitude');
        const campoLongitude = document.getElementById('Longitude');

        if(campoLatitude) {
            campoLatitude.addEventListener('paste', function(e) {
                let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                if (pasteData.includes(',') || pasteData.includes(' ')) {
                    e.preventDefault(); 
                    let matches = pasteData.match(/-?\d+(\.\d+)?/g);
                    if (matches && matches.length >= 2) {
                        campoLatitude.value = matches[0];
                        campoLongitude.value = matches[1];
                    }
                }
            });
        }
    });

    function abrirMapa() {
        const lat = document.getElementById('Latitude').value.trim();
        const lng = document.getElementById('Longitude').value.trim();
        if (lat && lng) { window.open(`https://www.google.com/maps?q=${lat},${lng}`, '_blank'); } 
        else { alert('Preencha a Latitude e a Longitude para abrir o mapa.'); }
    }

    function abrirVideo() {
        const link = document.getElementById('LinkVideo').value.trim();
        if (link) {
            let url = link;
            if (!/^https?:\/\//i.test(url)) { url = 'https://' + url; }
            window.open(url, '_blank');
        } else { alert('Nenhum link de vídeo cadastrado para este imóvel.'); }
    }

    function mudarFoto(event, botao, direcao) {
        event.preventDefault(); event.stopPropagation();
        const wrapper = botao.closest('.card-img-wrapper');
        const container = wrapper.querySelector('.slider-container');
        const imagens = container.querySelectorAll('.slider-img');
        let indexAtual = Array.from(imagens).findIndex(img => img.classList.contains('active'));
        imagens[indexAtual].classList.remove('active');
        indexAtual += direcao;
        if (indexAtual >= imagens.length) indexAtual = 0;
        if (indexAtual < 0) indexAtual = imagens.length - 1;
        imagens[indexAtual].classList.add('active');
    }
</script>
</body>
</html>
<?php
session_start();

// Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== CONEXÃO COM PDO ====================
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

// Verifica se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header('Location: login_v3a.php');
    exit();
}

// ==================== ENVIO DE EMAIL DE LOGIN ====================
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
if ($remote_addr !== '127.0.0.1' && $remote_addr !== '::1') {
    $to = "sendmail@sunnyimob.com.br, valterpb@hotmail.com";
    $subject = "SUN-CARDS-" . strtoupper($_SESSION['Usuario']) . " - " .
               $remote_addr . " - " . date('Y-m-d') . " - " . date('H:i:s');
    $headers = "From: sendmail@sunnyimob.com.br\r\n";
    @mail($to, $subject, '', $headers);
}

// ==================== CONSULTAS AO BANCO DE DADOS ====================
try {
    $stmtLocalidades = $pdo->prepare("SELECT DISTINCT Localidade FROM empreendimento WHERE Ocultar = 0 ORDER BY Localidade");
    $stmtLocalidades->execute();
    $localidades = $stmtLocalidades->fetchAll(PDO::FETCH_ASSOC);

    $stmtEmpresas = $pdo->prepare("SELECT DISTINCT Empresa_Id, NomeEmpresa FROM empreendimento WHERE Ocultar = 0 ORDER BY NomeEmpresa");
    $stmtEmpresas->execute();
    $empresas = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);

    $stmtEmpreendimentosTotal = $pdo->prepare("SELECT DISTINCT Empreend_ID, NomeEmpreendimento FROM empreendimento WHERE Ocultar = 0 ORDER BY NomeEmpreendimento");
    $stmtEmpreendimentosTotal->execute();
    $empreendimentosTotal = $stmtEmpreendimentosTotal->fetchAll(PDO::FETCH_ASSOC);

    $localidadeSelecionada    = $_GET['localidade']     ?? '';
    $tipologiaSelecionada     = $_GET['tipoImovel']     ?? '';
    $pavimentos               = $_GET['pavimentos']     ?? '';
    $precoInicial             = $_GET['precoInicial']   ?? '';
    $beiraMar                 = $_GET['beiraMar']       ?? '';
    $empresaSelecionada       = $_GET['empresa']        ?? '';
    $empreendimentoSelecionado= $_GET['empreendimento'] ?? '';
    $buscaNome                = trim($_GET['busca_nome'] ?? '');

    $whereClause = "WHERE Ocultar = 0 AND MenorPreco > 0";
    $params = [];

    // ===== BUSCA POR PARTE DO NOME =====
    if ($buscaNome !== '') {
        $whereClause .= " AND NomeEmpreendimento LIKE :buscaNome";
        $params[':buscaNome'] = '%' . $buscaNome . '%';
    }
    if (!empty($localidadeSelecionada)) {
        $whereClause .= " AND Localidade = :localidade";
        $params[':localidade'] = $localidadeSelecionada;
    }
    if (!empty($tipologiaSelecionada)) {
        $whereClause .= " AND Tipologia = :tipologia";
        $params[':tipologia'] = $tipologiaSelecionada;
    }
    if (!empty($pavimentos) && is_numeric($pavimentos)) {
        $whereClause .= " AND Pavimentos >= :pavimentos";
        $params[':pavimentos'] = (int)$pavimentos;
    }
    if (!empty($precoInicial) && is_numeric($precoInicial)) {
        $precoNumerico = $precoInicial / 100;
        if ($precoNumerico > 0) {
            $precoMin = $precoNumerico * 0.8;
            $precoMax = $precoNumerico * 1.2;
            $whereClause .= " AND MenorPreco BETWEEN :precoMin AND :precoMax";
            $params[':precoMin'] = $precoMin;
            $params[':precoMax'] = $precoMax;
        }
    }
    if ($beiraMar === 'true') {
        $whereClause .= " AND BeiraMar = -1";
    }
    if (!empty($empresaSelecionada)) {
        $whereClause .= " AND Empresa_Id = :empresa";
        $params[':empresa'] = $empresaSelecionada;
    }
    if (!empty($empreendimentoSelecionado)) {
        $whereClause .= " AND Empreend_ID = :empreendimento";
        $params[':empreendimento'] = $empreendimentoSelecionado;
    }

    $sqlQuery = "SELECT * FROM empreendimento " . $whereClause . " ORDER BY Localidade, MenorPreco";
    $stmtEmpreendimentos = $pdo->prepare($sqlQuery);
    $stmtEmpreendimentos->execute($params);
    $empreendimentos = $stmtEmpreendimentos->fetchAll(PDO::FETCH_ASSOC);

    $isBarreto = (strtoupper($_SESSION['Usuario'] ?? '') === 'BARRETO');
    $selecoes = [];
    if ($isBarreto && isset($_SESSION['UserId'])) {
        $stmtSelecao = $pdo->prepare("SELECT Empreend_ID FROM selecao WHERE UsuarioID = :usuario_id");
        $stmtSelecao->execute([':usuario_id' => $_SESSION['UserId']]);
        $selecoes = $stmtSelecao->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empreendimentos por Localidade</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA (via variáveis CSS)                  */
        /* ============================================================ */
        :root {
            --primary: <?php echo $theme['primary']; ?>;
            --primary-dark: <?php echo $theme['primary_dark']; ?>;
            --secondary: <?php echo $theme['secondary']; ?>;
            --accent: <?php echo $theme['accent']; ?>;
            --bg-body: <?php echo $theme['bg_body']; ?>;
            --text-color: <?php echo $theme['text_color']; ?>;
            --card-bg: <?php echo $theme['card_bg']; ?>;
            --shadow-color: <?php echo $theme['shadow_color']; ?>;
            --btn-bg: <?php echo $theme['btn_bg']; ?>;
            --btn-hover: <?php echo $theme['btn_hover']; ?>;
            --btn-text: <?php echo $theme['btn_text']; ?>;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #fd7e14;
            --info: #17a2b8;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-color);
            padding: 0;
            margin: 0;
        }

        /* Header neobrutalista */
        .header-neo {
            background: var(--primary);
            color: #fff;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .header-neo .logo-img {
            height: 60px;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            background: #fff;
            padding: 4px;
        }
        .header-neo h1 {
            font-weight: 800;
            font-size: 1.6rem;
            margin: 0;
        }
        .btn-neo-close {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #b02a37;
        }

        /* Seletor (filtros) neobrutalista */
        .seletor-localidade {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            padding: 1.25rem;
            margin-bottom: 2rem;
        }
        .seletor-localidade .form-label {
            font-weight: 700;
            color: var(--text-color);
        }
        .seletor-localidade .form-control {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            background: #fff;
            color: var(--text-color);
            transition: all 0.2s;
        }
        .seletor-localidade .form-control:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .seletor-localidade .form-check-input {
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .seletor-localidade .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-primary-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-outline-neo {
            background: transparent;
            color: var(--text-color);
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.8rem;
            font-weight: 700;
            transition: all 0.15s;
            text-decoration: none;
        }
        .btn-outline-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }

        /* ============================================================ */
        /* GRID DE CARDS COMPACTOS                                       */
        /* ============================================================ */
        .empreendimentos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .empreendimento-container {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.2s;
        }
        .empreendimento-container:hover {
            transform: translateY(-3px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
        }

        .empreendimento-header {
            background: var(--primary);
            color: #fff;
            padding: 0.55rem 0.85rem;
            border-bottom: 2px solid var(--shadow-color);
        }
        .empreendimento-header .loc {
            display: block;
            font-size: 0.66rem;
            font-weight: 600;
            letter-spacing: 0.6px;
            opacity: 0.85;
        }
        .empreendimento-header h3 {
            margin: 2px 0;
            font-size: 0.92rem;
            font-weight: 800;
            line-height: 1.15;
        }
        .empreendimento-header .empresa {
            display: block;
            font-size: 0.66rem;
            opacity: 0.85;
        }

        .empreendimento-body {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .empreendimento-imagem {
            position: relative;
            width: 100%;
            height: 150px;
            background: var(--secondary);
            border-bottom: 2px solid var(--shadow-color);
        }
        .empreendimento-imagem img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .empreendimento-info {
            padding: 0.7rem 0.85rem;
            flex: 1;
        }

        /* Badge verde Vendido (overlay) */
        .badge-vendido {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--success);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.18rem 0.65rem;
            font-weight: 800;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Linhas de detalhe compactas */
        .detalhe-item {
            margin-bottom: 0.35rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        .detalhe-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detalhe-titulo {
            font-weight: 700;
            color: var(--text-color);
            opacity: 0.8;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: flex;
            align-items: center;
        }
        .detalhe-titulo i {
            margin-right: 5px;
            color: var(--primary);
            font-size: 0.68rem;
        }
        .detalhe-valor {
            font-size: 0.82rem;
            padding-left: 16px;
            margin-top: 1px;
        }

        .badge-neo {
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.18rem 0.65rem;
            font-weight: 700;
            font-size: 0.72rem;
        }
        .badge-success-neo { background: var(--success); color: #fff; }
        .badge-warning-neo { background: var(--warning); color: #000; }
        .badge-secondary-neo { background: #6c757d; color: #fff; }
        .badge-info-neo { background: var(--info); color: #fff; }

        .valor-destaque {
            color: var(--danger);
            font-weight: 700;
        }

        /* Responsivo */
        @media (max-width: 575px) {
            .empreendimentos-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Fallback imagem */
        .img-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--secondary);
            color: var(--text-color);
            font-size: 0.9rem;
            height: 100%;
        }
        .img-loading {
            background: linear-gradient(90deg, var(--secondary) 25%, #e9ecef 50%, var(--secondary) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Botão Voltar */
        .btn-voltar-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.5rem 2rem;
            font-weight: 700;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-voltar-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
    </style>
</head>
<body>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$linkTarget   = $_SESSION['link_target'] ?? '_self';
$abrirNovaAba = !empty($_SESSION['abrir_nova_aba']) || ($linkTarget === '_blank');
$logoSessao   = trim((string)($_SESSION['Conf_ImgLogo'] ?? ''));
$logoFallback = 'https://placehold.co/96x96/6B2117/FFFFFF?text=LOGO';
?>
<!-- HEADER NEOBRUTALISTA -->
<header class="header-neo">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= htmlspecialchars($logoSessao !== '' ? $logoSessao : $logoFallback, ENT_QUOTES) ?>"
                     alt="Logo" class="logo-img"
                     onerror="this.onerror=null;this.src='<?= htmlspecialchars($logoFallback, ENT_QUOTES) ?>';">
                <div>
                    <h1><i class="fas fa-building me-2"></i>Empreendimentos</h1>
                    <small class="d-block opacity-75">SunnyImob</small>
                </div>
            </div>
            <button type="button"
                    id="btnAcaoHeader"
                    data-target="<?= htmlspecialchars($linkTarget, ENT_QUOTES) ?>"
                    data-nova-aba="<?= $abrirNovaAba ? '1' : '0' ?>"
                    class="btn-neo-close">
                <i class="fas <?= $abrirNovaAba ? 'fa-times me-1' : 'fa-arrow-left me-1' ?>"></i>
                <span><?= $abrirNovaAba ? 'Fechar' : 'Voltar' ?></span>
            </button>
        </div>
    </div>
</header>

<div class="container">

    <!-- Filtros Neobrutalistas -->
    <div class="seletor-localidade">
        <form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="row">
                <!-- 🔎 NOVO: busca por parte do nome do empreendimento -->
                <div class="col-12 col-md-6 mb-2">
                    <b><label for="busca_nome" class="form-label fw-bold mb-1">Buscar por Nome:</label></b>
                    <input type="text" id="busca_nome" name="busca_nome"
                           class="form-control w-100"
                           placeholder="Digite parte do nome do empreendimento…"
                           value="<?= htmlspecialchars($buscaNome) ?>">
                </div>

                <div class="col-6 col-md-3 mb-2">
                    <b><label for="localidade" class="form-label fw-bold mb-1">Localidade:</label></b>
                    <select name="localidade" id="localidade" class="form-control w-100">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($localidades as $localidade): ?>
                            <option value="<?= htmlspecialchars($localidade['Localidade']) ?>"
                                <?= ($localidadeSelecionada === $localidade['Localidade']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($localidade['Localidade'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <b><label class="form-label fw-bold mb-1">Tipologia:</label></b>
                    <select id="tipoImovel" name="tipoImovel" class="form-control w-100">
                        <option value="">Selecione</option>
                        <?php
                        $tipologias = ['STUDIO', 'FLAT', 'CASA', '1Q', '2Q', '3Q', '4Q', '5Q', '6Q'];
                        foreach ($tipologias as $tipologia):
                        ?>
                            <option value="<?= $tipologia ?>" <?= ($tipologiaSelecionada === $tipologia) ? 'selected' : '' ?>>
                                <?= $tipologia ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <b><label class="form-label fw-bold mb-1">Pavimentos:</label></b>
                    <select id="pavimentos" name="pavimentos" class="form-control w-100">
                        <option value="">Selecione</option>
                        <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= ($pavimentos !== '' && (int)$pavimentos === $i) ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <b><label for="precoInicial" class="form-label fw-bold mb-1">Preço Inicial:</label></b>
                    <input type="hidden" id="precoInicial" name="precoInicial" value="<?= htmlspecialchars($precoInicial) ?>">
                    <input type="text" id="precoInicialVisivel" class="form-control w-100" placeholder="Ex: 500.000,00" value="">
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <b><label for="empresa" class="form-label fw-bold mb-1">Empresa:</label></b>
                    <select name="empresa" id="empresa" class="form-control w-100">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?= htmlspecialchars($empresa['Empresa_Id']) ?>"
                                <?= ($empresaSelecionada === $empresa['Empresa_Id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($empresa['NomeEmpresa'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <b><label for="empreendimento" class="form-label fw-bold mb-1">Empreendimento:</label></b>
                    <select name="empreendimento" id="empreendimento" class="form-control w-100">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($empreendimentosTotal as $empreend): ?>
                            <option value="<?= htmlspecialchars($empreend['Empreend_ID']) ?>"
                                <?= ($empreendimentoSelecionado === $empreend['Empreend_ID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($empreend['NomeEmpreendimento']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="beiraMar" name="beiraMar" value="true" <?= ($beiraMar === 'true') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="beiraMar">
                            <strong>Beira Mar</strong>
                        </label>
                    </div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12 text-center">
                    <button type="submit" class="btn-primary-neo me-2">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn-outline-neo me-2">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                    <?php if ($isBarreto): ?>
                        <a href="selecionados.php" class="btn-primary-neo me-2" target="_blank" style="background: var(--info); color: #fff;">
                            <i class="fas fa-eye"></i> Exibir Selecionados
                        </a>
                        <button type="button" class="btn-primary-neo btn-limpar-selecionados" style="background: var(--danger); color: #fff;">
                            <i class="fas fa-trash"></i> Limpar Selecionados
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista de Empreendimentos -->
    <div id="conteudo-para-pdf">
        <?php if (empty($empreendimentos)): ?>
            <div class="alert alert-info text-center" style="border: 2px solid var(--shadow-color); box-shadow: 4px 4px 0px 0px var(--shadow-color); border-radius: 12px; background: var(--card-bg);">
                Nenhum empreendimento encontrado com os filtros aplicados.
            </div>
        <?php endif; ?>

        <div class="empreendimentos-grid">
            <?php foreach ($empreendimentos as $empreendimento):
                $isVendido = isset($empreendimento['Vendido']) &&
                            ($empreendimento['Vendido'] == 1 || $empreendimento['Vendido'] == -1 || $empreendimento['Vendido'] === true);
            ?>
                <div class="empreendimento-container">
                    <div class="empreendimento-header">
                        <span class="loc"><?= htmlspecialchars(strtoupper($empreendimento['Localidade'])) ?></span>
                        <h3><i class="fas fa-building"></i> <?= htmlspecialchars(strtoupper($empreendimento['NomeEmpreendimento'])) ?></h3>
                        <span class="empresa"><?= htmlspecialchars(strtoupper($empreendimento['NomeEmpresa'])) ?></span>
                    </div>
                    <div class="empreendimento-body">
                        <div class="empreendimento-imagem">
                            <?php if ($isVendido): ?>
                                <span class="badge-vendido"><i class="fas fa-check"></i> VENDIDO</span>
                            <?php endif; ?>
                            <img src="card/<?= htmlspecialchars($empreendimento['Empreend_ID']) ?>.jpg"
                                 alt="<?= htmlspecialchars($empreendimento['NomeEmpreendimento']) ?>"
                                 class="img-loading"
                                 onload="this.classList.remove('img-loading')"
                                 onerror="this.onerror=null;this.src='card/default.jpg';this.classList.add('img-fallback');this.classList.remove('img-loading');this.alt='Imagem não disponível'">
                        </div>
                        <div class="empreendimento-info">
                            <!-- Status Entrega -->
                            <div class="detalhe-item">
                                <div class="detalhe-titulo"><i class="fas fa-calendar-check"></i> Status de Entrega</div>
                                <div class="detalhe-valor">
                                    <?php if ($empreendimento['Entregue'] == true || $empreendimento['Entregue'] == 1 || $empreendimento['Entregue'] == -1): ?>
                                        <span class="badge badge-neo badge-success-neo"><i class="fas fa-check-circle"></i> ENTREGUE</span>
                                    <?php else:
                                        $mesEntrega = $empreendimento['MesEntrega'] ?? '';
                                        $anoEntrega = $empreendimento['AnoEntrega'] ?? '';
                                        $entregaTexto = '';
                                        if (is_numeric($mesEntrega) && is_numeric($anoEntrega)) {
                                            $mesEntrega = (int)$mesEntrega;
                                            $anoEntrega = (int)$anoEntrega;
                                            if ($mesEntrega >= 1 && $mesEntrega <= 12 && $anoEntrega >= 2025) {
                                                $entregaTexto = str_pad($mesEntrega, 2, '0', STR_PAD_LEFT) . "/" . $anoEntrega;
                                            }
                                        }
                                        if (!empty($entregaTexto)): ?>
                                            <span class="badge badge-neo badge-warning-neo"><i class="fas fa-clock"></i> <?= $entregaTexto ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-neo badge-secondary-neo"><i class="fas fa-question-circle"></i> SEM PREVISÃO</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Preço -->
                            <div class="detalhe-item">
                                <div class="detalhe-titulo"><i class="fas fa-tag"></i> Faixa de Preço</div>
                                <div class="detalhe-valor">
                                    <?php
                                    $menorPreco = number_format($empreendimento['MenorPreco'], 2, ',', '.');
                                    if (!empty($empreendimento['MaiorPreco']) && $empreendimento['MaiorPreco'] > 0) {
                                        $maiorPreco = number_format($empreendimento['MaiorPreco'], 2, ',', '.');
                                        echo "<span class='valor-destaque'>R$ " . $menorPreco . "</span> a R$ " . $maiorPreco;
                                    } else {
                                        echo "<span class='valor-destaque'>R$ " . $menorPreco . "</span>";
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- Metragem -->
                            <div class="detalhe-item">
                                <div class="detalhe-titulo"><i class="fas fa-ruler-combined"></i> Metragem</div>
                                <div class="detalhe-valor">
                                    <?php
                                    if (!empty($empreendimento['MetragemMin']) && !empty($empreendimento['MetragemMax'])) {
                                        if ($empreendimento['MetragemMin'] > 0 && $empreendimento['MetragemMax'] > 0) {
                                            echo $empreendimento['MetragemMin'] . "m² a " . $empreendimento['MetragemMax'] . "m²";
                                        } elseif ($empreendimento['MetragemMin'] > 0) {
                                            echo $empreendimento['MetragemMin'] . "m²";
                                        } elseif ($empreendimento['MetragemMax'] > 0) {
                                            echo $empreendimento['MetragemMax'] . "m²";
                                        } else {
                                            echo "Não informado";
                                        }
                                    } else {
                                        echo "Não informado";
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- Tipologia -->
                            <div class="detalhe-item">
                                <div class="detalhe-titulo"><i class="fas fa-bed"></i> Tipologia</div>
                                <div class="detalhe-valor">
                                    <?php if (!empty(trim($empreendimento['Tipologia'] ?? ''))): ?>
                                        <?= htmlspecialchars(strtoupper($empreendimento['Tipologia'])) ?>
                                    <?php else: ?>
                                        Não informado
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Pavimentos -->
                            <div class="detalhe-item">
                                <div class="detalhe-titulo"><i class="fas fa-building"></i> Pavimentos</div>
                                <div class="detalhe-valor">
                                    <?= htmlspecialchars($empreendimento['Pavimentos']) ?>
                                </div>
                            </div>
                            <!-- Beira Mar -->
                            <?php if ($empreendimento['BeiraMar'] == true || $empreendimento['BeiraMar'] == 1 || $empreendimento['BeiraMar'] == -1): ?>
                                <div class="detalhe-item">
                                    <div class="detalhe-titulo"><i class="fas fa-umbrella-beach"></i> Localização</div>
                                    <div class="detalhe-valor">
                                        <span style="color: var(--primary); font-weight: 700;"><i class="fas fa-check-circle"></i> BEIRA MAR</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- Localização no mapa -->
                            <?php if (!empty($empreendimento['Localizacao'])): ?>
                                <div class="detalhe-item">
                                    <div class="detalhe-titulo"><i class="fas fa-map-marker-alt"></i> Localização</div>
                                    <div class="detalhe-valor">
                                        <a href="https://maps.google.com/maps/search/?api=1&query=<?= urlencode($empreendimento['Localizacao']) ?>" target="_blank" style="color: var(--primary); font-weight: 600;">
                                            Ver no Mapa
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- Seleção (Barreto) -->
                            <?php if ($isBarreto): ?>
                                <div class="detalhe-item">
                                    <div class="detalhe-valor">
                                        <?php $isSelected = in_array($empreendimento['Empreend_ID'], $selecoes); ?>
                                        <?php if ($isSelected): ?>
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Selecionado</span>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Botão Voltar -->
    <div class="text-center my-4">
        <a class="btn-voltar-neo" href="javascript:window.close()">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Limpar Selecionados
        $('.btn-limpar-selecionados').on('click', function() {
            if (confirm('Tem certeza que deseja limpar todas as seleções?')) {
                $.ajax({
                    url: 'selecao_salvar.php',
                    type: 'POST',
                    data: { delete_all: 'true', usuario_id: '<?= $_SESSION['UserId'] ?? '' ?>' },
                    success: function(response) {
                        if (response === 'success') {
                            alert('Todas as seleções foram limpas com sucesso.');
                            $('.btn-selecionar').show();
                            $('.text-success').remove();
                        } else {
                            alert('Erro ao limpar seleções: ' + response);
                        }
                    },
                    error: function() { alert('Erro ao conectar com o servidor.'); }
                });
            }
        });

        // Selecionar Empreendimento
        $('.btn-selecionar').on('click', function() {
            var empreendId = $(this).data('empreend-id');
            var button = $(this);
            $.ajax({
                url: 'selecao_salvar.php',
                type: 'POST',
                data: { empreend_id: empreendId, usuario_id: '<?= $_SESSION['UserId'] ?? '' ?>' },
                success: function(response) {
                    if (response === 'success') {
                        button.hide();
                        button.after('<span class="text-success"><i class="fas fa-check-circle"></i> Selecionado</span>');
                    } else {
                        alert('Erro ao salvar seleção: ' + response);
                    }
                },
                error: function() { alert('Erro ao conectar com o servidor.'); }
            });
        });
    });

    // Máscara de preço
    document.addEventListener('DOMContentLoaded', function() {
        const inputPrecoInicial = document.getElementById('precoInicial');
        const inputPrecoInicialVisivel = document.getElementById('precoInicialVisivel');

        function formatarValor(valor) {
            let valorLimpo = valor.replace(/\D/g, '');
            if (!valorLimpo) return '';
            valorLimpo = parseInt(valorLimpo, 10).toString();
            if (valorLimpo === 'NaN') return '';
            if (valorLimpo.length === 1) valorLimpo = '0' + valorLimpo;
            const reais = valorLimpo.slice(0, -2) || '0';
            const centavos = valorLimpo.slice(-2);
            const reaisFormatados = reais.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return reaisFormatados + ',' + centavos;
        }
        function removerMascara(valor) { return valor.replace(/\D/g, ''); }

        if (inputPrecoInicial.value && inputPrecoInicial.value !== '0') {
            inputPrecoInicialVisivel.value = formatarValor(inputPrecoInicial.value);
        }
        inputPrecoInicialVisivel.addEventListener('input', function(e) {
            const valorSemMascara = removerMascara(e.target.value);
            inputPrecoInicial.value = valorSemMascara;
            e.target.value = formatarValor(valorSemMascara);
        });
        inputPrecoInicialVisivel.addEventListener('focus', function() {
            if (this.value === '') {
                this.value = '0,00';
                inputPrecoInicial.value = '000';
            }
        });
    });
</script>

<script>
(function () {
    const FALLBACK_VOLTAR = 'mainpage_v66a.php';

    function obterBotao() {
        return document.getElementById('btnAcaoHeader');
    }

    function ehModoBlank(btn) {
        if (!btn) return false;
        const target = (btn.dataset.target || '').trim().toLowerCase();
        const novaAba = (btn.dataset.novaAba || '').trim();

        if (novaAba === '1' || target === '_blank') return true;
        if (target === '_self') return false;
        return Boolean(window.opener && !window.opener.closed);
    }

    function sincronizarVisualBotao() {
        const btn = obterBotao();
        if (!btn) return;

        const isBlank = ehModoBlank(btn);
        const icon = btn.querySelector('i');
        const span = btn.querySelector('span');

        if (icon) {
            icon.className = isBlank ? 'fas fa-times me-1' : 'fas fa-arrow-left me-1';
        }
        if (span) {
            span.textContent = isBlank ? 'Fechar' : 'Voltar';
        }

        btn.setAttribute('title', isBlank ? 'Fechar aba/janela' : 'Voltar para a tela anterior');
    }

    function tratarCliqueBotao(event) {
        if (event) event.preventDefault();

        const btn = obterBotao();
        if (ehModoBlank(btn)) {
            if (typeof fecharJanela === 'function') {
                fecharJanela(event);
                return;
            }
            window.close();
            setTimeout(function () {
                if (!window.closed) {
                    window.location.href = FALLBACK_VOLTAR;
                }
            }, 150);
            return;
        }

        const mesmoDominio =
            window.history.length > 1 &&
            document.referrer &&
            document.referrer.indexOf(window.location.origin) === 0;

        if (mesmoDominio) {
            window.history.back();
        } else {
            window.location.href = FALLBACK_VOLTAR;
        }
    }

    function inicializarHeaderScript() {
        const btn = obterBotao();
        if (!btn) return;

        sincronizarVisualBotao();
        btn.removeEventListener('click', tratarCliqueBotao);
        btn.addEventListener('click', tratarCliqueBotao);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarHeaderScript);
    } else {
        inicializarHeaderScript();
    }
})();
</script>


</body>
</html>
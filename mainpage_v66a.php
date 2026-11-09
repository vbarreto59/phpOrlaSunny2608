<?php
session_start();

// ============================================================
// DEPURAÇÃO (remova após identificar o erro)
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/erros.log');

// =======================================================
// SunnyImob - versão PHP com PDO
// =======================================================
require_once "conn.php";
require_once "usr_acoes.php";
require_once "theme_config.php";
AcoesPermitidas();
require_once "manutencao_config.php";
require_once "log_navegacao.php";

// ==== pegar dados da empresa 09 11 2026
require_once __DIR__ . '/config_empresa.php';

protegerPagina(true);

if (!registrarNavegacao($conn, $_SESSION)) {
    echo "Erro no registro da navegação.";
    exit;
}

$mostrarAlertaManutencao = EstaEmManutencao('sunny');

$_SESSION['menuPrincipal'] = "mainpage_v66a.php";

// Variaveis para armazenar imagens do carrossel
$carouselImages = [];
$carouselPath = dirname(__FILE__) . "/carrossel";

if (is_dir($carouselPath)) {
    $files = scandir($carouselPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $carouselPath . '/' . $file;
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $carouselImages[] = "carrossel/" . $file;
        }
    }
}

// ============= 26 04 2026 - Criar as variaveis de sessao da empresa
$idEmp = $_SESSION['IdEmp'];
CarregarConfiguracoesEmpresa($idEmp);

// =======================================================
// FUNÇÕES PARA CONSULTAS COM PDO
// =======================================================
function getLocalidades() {
    global $conn;
    $localidades = [];
    try {
        $sql = "SELECT DISTINCT Localidade FROM empreendimento
                WHERE Localidade IS NOT NULL
                ORDER BY Localidade";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $localidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar localidades: " . $e->getMessage());
    }
    return $localidades;
}

function getAnosEntrega() {
    global $conn;
    $anos = [];
    try {
        $sql = "SELECT DISTINCT AnoEntrega FROM empreendimento
                WHERE AnoEntrega IS NOT NULL
                ORDER BY AnoEntrega";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $anos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar anos de entrega: " . $e->getMessage());
    }
    return $anos;
}

function getEmpresas() {
    global $conn;
    $empresas = [];
    try {
        $sql = "SELECT NomeEmpresa FROM empresa ORDER BY NomeEmpresa";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar empresas: " . $e->getMessage());
    }
    return $empresas;
}

/**
 * Busca 1 empreendimento destaque verificando OBRIGATORIAMENTE 
 * se existe a imagem 'card/Empreend_ID.jpg' na pasta.
 * A cada refresh, embaralha os resultados e pega um diferente.
 */
function getDestaqueEmpreendimento(array $localidades) {
    global $conn;
    if (empty($localidades)) return null;

    try {
        $placeholders = implode(',', array_fill(0, count($localidades), '?'));
        
        // Busca TODOS os empreendimentos da região (sem LIMIT)
        $sql = "SELECT * FROM empreendimento 
                WHERE UPPER(TRIM(Localidade)) IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_map('strtoupper', $localidades));
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Embaralha os resultados para que a cada refresh seja um imóvel diferente
        shuffle($resultados);

        foreach ($resultados as $row) {
            // REGRA DE OURO: A imagem é card/ID.jpg
            $nomeArquivo = $row['Empreend_ID'] . '.jpg';
            $caminhoFisico = __DIR__ . '/card/' . $nomeArquivo;
            $caminhoUrl = 'card/' . $nomeArquivo;

            // Se o arquivo existir no servidor, esse é o nosso destaque!
            if (file_exists($caminhoFisico)) {
                $row['_img_resolvida'] = $caminhoUrl;
                return $row;
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar destaque: " . $e->getMessage());
    }
    return null; 
}

function formataMoeda($valor) {
    if (is_numeric($valor)) return 'R$ ' . number_format($valor, 2, ',', '.');
    return $valor ?: 'Consulte';
}

function formataArea($valor) {
    if (is_numeric($valor)) return number_format($valor, 2, '.', '') . 'm²';
    return $valor ?: 'Consulte';
}

// =======================================================
// EXECUTAR CONSULTAS
// =======================================================
$localidades  = getLocalidades();
$anosEntrega  = getAnosEntrega();
$empresas     = getEmpresas();

// Buscando 1 destaque aleatório para cada região
$destaquePorto = getDestaqueEmpreendimento(['PORTO DE GALINHAS', 'CUPE', 'MURO ALTO']);
$destaqueCarneiros = getDestaqueEmpreendimento(['CARNEIROS', 'MARAGOGI']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
    <title>SunnyImob · Painel Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
/* ============================================================ */
/* VARIÁVEIS DO TEMA                                            */
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
    
    --danger: #dc3545;
    --success: #28a745;
    --warning: #ffc107;
    --info: #17a2b8;
    --dark: #343a40;
}

/* ============================================================ */
/* RESET + BASE                                                 */
/* ============================================================ */
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;
    background-color: var(--bg-body);
    background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
    background-size: 24px 24px;
    color: var(--text-color);
    min-height: 100vh;
    margin: 0;
    line-height: 1.5;
    padding-top: <?php echo $mostrarAlertaManutencao ? '60px' : '0'; ?>;
}

.main-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* ============================================================ */
/* HEADER UNIFICADO                                             */
/* ============================================================ */
.app-header {
    background: var(--primary);
    color: #fff;
    border-bottom: 5px solid var(--shadow-color);
    box-shadow: 0 6px 0 0 var(--shadow-color);
    margin: 24px 0 0;
    border-radius: 18px 18px 0 0;
    overflow: hidden;
    position: relative;
}
.app-header::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
    background-size: 40px 40px;
    pointer-events: none;
}
.header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 16px 24px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}
.header-brand {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
}
.header-logo {
    height: 62px;
    width: auto;
    max-width: 220px;
    min-width: 62px;
    object-fit: contain;
    background: #fff;
    border: 3px solid var(--shadow-color);
    border-radius: 12px;
    box-shadow: 4px 4px 0 0 var(--shadow-color);
    padding: 6px 10px;
    flex-shrink: 0;
}
.header-brand-text { min-width: 0; }
.header-brand-text h1 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 900;
    letter-spacing: -0.5px;
    line-height: 1.1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.header-brand-text p {
    margin: 4px 0 0;
    font-size: 0.78rem;
    font-weight: 600;
    opacity: 0.9;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.header-brand-text .version-tag {
    display: inline-block;
    background: rgba(0,0,0,0.25);
    padding: 1px 8px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 0.7rem;
    margin-left: 4px;
}

/* User block no header */
.header-user {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(0,0,0,0.15);
    border: 2px solid var(--shadow-color);
    border-radius: 14px;
    padding: 6px 14px 6px 6px;
    box-shadow: 3px 3px 0 0 var(--shadow-color);
    flex-shrink: 0;
}
.header-user img {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid #fff;
    background: #fff;
    flex-shrink: 0;
}
.header-user-text { line-height: 1.15; }
.header-user-text .label {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    opacity: 0.75;
    display: block;
}
.header-user-text .name {
    font-size: 0.92rem;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

/* ============================================================ */
/* MENUS                                                        */
/* ============================================================ */
.menus-bar {
    background: var(--primary-dark);
    padding: 12px 20px;
    border-bottom: 5px solid var(--shadow-color);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 26px;
    border-radius: 0 0 18px 18px;
    box-shadow: 0 6px 0 0 var(--shadow-color);
}
.menus-bar .dropdown { display: inline-block; }

.btn-menu {
    background: rgba(255,255,255,0.15);
    color: #fff;
    border: 2px solid var(--shadow-color);
    box-shadow: 3px 3px 0 0 var(--shadow-color);
    padding: 9px 18px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 0.85rem;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}
.btn-menu:hover,
.btn-menu:focus {
    transform: translate(-2px, -2px);
    box-shadow: 5px 5px 0 0 var(--shadow-color);
    background: rgba(255,255,255,0.28);
    color: #fff;
}
.btn-menu:active {
    transform: translate(1px, 1px);
    box-shadow: 1px 1px 0 0 var(--shadow-color);
}
.btn-menu i { font-size: 0.9rem; }

.dropdown-menu {
    background: var(--card-bg);
    border: 3px solid var(--shadow-color);
    box-shadow: 5px 5px 0 0 var(--shadow-color);
    border-radius: 12px;
    padding: 8px;
    margin-top: 6px !important;
    min-width: 260px;
}
.dropdown-item {
    color: var(--text-color);
    font-weight: 600;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.88rem;
    transition: all 0.12s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dropdown-item i { color: var(--primary); width: 18px; text-align: center; }
.dropdown-item:hover {
    background: var(--primary);
    color: #fff;
    transform: translateX(4px);
}
.dropdown-item:hover i { color: #fff; }

/* ============================================================ */
/* CARROSSEL                                                    */
/* ============================================================ */
.carousel-container {
    background: var(--shadow-color);
    border: 4px solid var(--shadow-color);
    border-radius: 18px;
    box-shadow: 6px 6px 0 0 var(--shadow-color);
    overflow: hidden;
    margin-bottom: 26px;
}
.carousel-inner {
    background: #f8f8f8;
    height: 300px;
}
.carousel-inner img {
    max-height: 300px;
    width: auto;
    max-width: 100%;
    object-fit: contain;
    margin: 0 auto;
    display: block;
}
.carousel-control-prev,
.carousel-control-next {
    width: 56px;
    opacity: 1;
    background: var(--shadow-color);
    border-radius: 0;
}
.carousel-control-prev:hover,
.carousel-control-next:hover {
    background: var(--primary);
}
.carousel-control-prev-icon,
.carousel-control-next-icon {
    background-color: #fff;
    border-radius: 50%;
    padding: 18px;
    box-shadow: 0 0 0 3px var(--shadow-color);
}
@media (max-width: 768px) {
    .carousel-inner { height: 200px; }
    .carousel-inner img { max-height: 200px; }
    .carousel-control-prev,
    .carousel-control-next { width: 42px; }
}
@media (max-width: 480px) {
    .carousel-inner { height: 160px; }
    .carousel-inner img { max-height: 160px; }
}

/* ============================================================ */
/* CARD GENÉRICO (seções)                                       */
/* ============================================================ */
.section-card {
    background: var(--card-bg);
    border: 3px solid var(--shadow-color);
    border-radius: 18px;
    box-shadow: 6px 6px 0 0 var(--shadow-color);
    margin-bottom: 26px;
    overflow: hidden;
}
.section-card-header {
    background: var(--primary);
    color: #fff;
    padding: 14px 22px;
    font-weight: 800;
    font-size: 1.05rem;
    letter-spacing: -0.2px;
    border-bottom: 3px solid var(--shadow-color);
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-card-header i {
    background: rgba(0,0,0,0.2);
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}
.section-card-body { padding: 22px; }

/* ============================================================ */
/* FILTROS                                                      */
/* ============================================================ */
.card-filters .filter-section { margin-bottom: 0; }
.card-filters .form-label {
    font-weight: 800;
    font-size: 0.78rem;
    color: var(--text-color);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-filters .form-label i { color: var(--primary); font-size: 0.9rem; }

.card-filters .form-control,
.card-filters .form-select {
    border: 2px solid var(--shadow-color);
    border-radius: 10px;
    padding: 10px 14px;
    background: #fff;
    color: var(--text-color);
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.15s;
    box-shadow: 2px 2px 0 0 var(--shadow-color);
}
.card-filters .form-control:focus,
.card-filters .form-select:focus {
    outline: none;
    box-shadow: 4px 4px 0 0 var(--shadow-color);
    border-color: var(--primary);
}

/* Switch neobrutalista */
.neo-switch {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 2px solid var(--shadow-color);
    border-radius: 10px;
    box-shadow: 3px 3px 0 0 var(--shadow-color);
    padding: 8px 14px;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s;
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--text-color);
}
.neo-switch:hover {
    transform: translate(-1px, -1px);
    box-shadow: 4px 4px 0 0 var(--shadow-color);
}
.neo-switch input { display: none; }
.neo-switch .switch-track {
    width: 40px;
    height: 22px;
    background: #d1d5db;
    border-radius: 999px;
    position: relative;
    transition: background 0.2s;
    flex-shrink: 0;
}
.neo-switch .switch-track::after {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}
.neo-switch input:checked + .switch-track { background: var(--primary); }
.neo-switch input:checked + .switch-track::after { transform: translateX(18px); }
.neo-switch i { color: var(--primary); }

/* Botões de ação */
.btn-primary {
    background: var(--btn-bg);
    color: var(--btn-text);
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0 0 var(--shadow-color);
    border-radius: 10px;
    padding: 11px 26px;
    font-weight: 800;
    font-size: 0.92rem;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0 0 var(--shadow-color);
    background: var(--btn-hover);
    color: #fff;
}
.btn-primary:active {
    transform: translate(1px, 1px);
    box-shadow: 1px 1px 0 0 var(--shadow-color);
}

.btn-secondary {
    background: #fff;
    color: var(--text-color);
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0 0 var(--shadow-color);
    border-radius: 10px;
    padding: 11px 26px;
    font-weight: 800;
    font-size: 0.92rem;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-secondary:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0 0 var(--shadow-color);
    background: #f8f9fb;
    color: var(--text-color);
}
.btn-secondary:active {
    transform: translate(1px, 1px);
    box-shadow: 1px 1px 0 0 var(--shadow-color);
}

.btn-danger {
    background: var(--danger);
    color: #fff;
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0 0 var(--shadow-color);
    border-radius: 10px;
    padding: 11px 26px;
    font-weight: 800;
    font-size: 0.92rem;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-danger:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0 0 var(--shadow-color);
    background: #b02a37;
    color: #fff;
}

/* ============================================================ */
/* CARDS DE IMÓVEIS EM DESTAQUE (Layout da Imagem)              */
/* ============================================================ */
.property-card {
    background: #fff;
    border: 3px solid var(--shadow-color);
    border-radius: 12px;
    box-shadow: 6px 6px 0 0 var(--shadow-color);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: all 0.2s;
}
.property-card:hover {
    transform: translateY(-4px);
    box-shadow: 8px 8px 0 0 var(--shadow-color);
}

/* Header do Card */
.property-card-header {
    background: #8b7355; /* Cor marrom da imagem */
    color: #fff;
    padding: 12px 16px;
    border-bottom: 3px solid var(--shadow-color);
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.property-loc {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    opacity: 0.9;
}
.property-title {
    font-size: 1.15rem;
    font-weight: 900;
    margin: 0;
    line-height: 1.2;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.property-title small {
    font-size: 0.75rem;
    font-weight: 600;
    opacity: 0.8;
    text-transform: uppercase;
}

/* Corpo do Card (Imagem + Info) */
.property-card-body {
    display: flex;
    flex-direction: row;
    flex: 1;
    background: #fff;
}
.property-image {
    width: 60%;
    position: relative;
    background: #e9ecef;
    border-right: 3px solid var(--shadow-color);
    min-height: 280px;
}
.property-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Lista de Informações */
.property-info {
    width: 40%;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
}
.info-row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}
.info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.info-row i {
    color: #7a6550; /* Ícones marrons */
    font-size: 1rem;
    margin-top: 2px;
    width: 16px;
    text-align: center;
    flex-shrink: 0;
}
.info-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.info-label {
    font-size: 0.65rem;
    font-weight: 800;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    line-height: 1.3;
}
.info-value.price {
    color: #a83232; /* Vermelho escuro */
    font-weight: 800;
    font-size: 0.9rem;
}
.info-value a {
    color: #8b7355;
    text-decoration: underline;
    font-weight: 800;
}
.info-value a:hover {
    color: #5c4431;
}

/* Responsividade dos Cards */
@media (max-width: 768px) {
    .property-card-body {
        flex-direction: column;
    }
    .property-image {
        width: 100%;
        height: 220px;
        border-right: none;
        border-bottom: 3px solid var(--shadow-color);
    }
    .property-info {
        width: 100%;
    }
}

/* ============================================================ */
/* ALERTA DE MANUTENÇÃO                                         */
/* ============================================================ */
.alert-manutencao {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    text-align: center;
    padding: 14px;
    font-weight: 800;
    font-size: 0.9rem;
    background: var(--danger);
    color: #fff;
    border-bottom: 4px solid var(--shadow-color);
    box-shadow: 0 4px 0 0 var(--shadow-color);
    animation: pulse 2s infinite;
    letter-spacing: 0.3px;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}

/* ============================================================ */
/* CARD DO DIRETOR                                              */
/* ============================================================ */
.card-director {
    background: var(--card-bg);
    border: 3px solid var(--shadow-color);
    border-radius: 18px;
    box-shadow: 6px 6px 0 0 var(--shadow-color);
    overflow: hidden;
    margin-bottom: 20px;
}
.card-director .card-header {
    background: var(--primary);
    color: #fff;
    padding: 16px 24px;
    font-weight: 800;
    border-bottom: 3px solid var(--shadow-color);
    font-size: 1.05rem;
}
.card-director .card-body {
    padding: 22px;
    background: var(--card-bg);
}
.director-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.director-info-item {
    background: rgba(0,0,0,0.03);
    border: 2px solid var(--shadow-color);
    border-radius: 10px;
    padding: 12px 16px;
    text-align: center;
}
.director-info-item small {
    display: block;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-color);
    opacity: 0.65;
    font-weight: 700;
    margin-bottom: 4px;
}
.director-info-item strong {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-color);
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 28px;
    border-radius: 30px;
    font-weight: 800;
    font-size: 0.92rem;
    letter-spacing: 0.5px;
    border: 2px solid var(--shadow-color);
    box-shadow: 3px 3px 0 0 var(--shadow-color);
}
.status-badge.bg-success { background: var(--success); color: #fff; }
.status-badge.bg-danger  { background: var(--danger);  color: #fff; }

/* ============================================================ */
/* ALERTAS PERSONALIZADOS                                       */
/* ============================================================ */
.alert {
    border: 3px solid var(--shadow-color);
    box-shadow: 4px 4px 0 0 var(--shadow-color);
    border-radius: 14px;
    font-weight: 700;
    padding: 16px 22px;
}
.alert-danger  { background: var(--danger);  color: #fff; }
.alert-warning { background: var(--warning); color: #000; }

/* ============================================================ */
/* RESPONSIVO                                                   */
/* ============================================================ */
@media (max-width: 992px) {
    .header-logo { height: 54px; max-width: 180px; }
    .header-brand-text h1 { font-size: 1.15rem; }
}
@media (max-width: 768px) {
    .main-container { padding: 0 12px; }
    .app-header { margin-top: 12px; border-radius: 14px 14px 0 0; }
    .header-inner { padding: 14px 16px; gap: 14px; }
    .header-brand { gap: 12px; }
    .header-logo { height: 46px; max-width: 140px; padding: 4px 6px; }
    .header-brand-text h1 { font-size: 1rem; }
    .header-brand-text p { font-size: 0.7rem; }
    .header-user { padding: 4px 10px 4px 4px; }
    .header-user img { width: 38px; height: 38px; }
    .header-user-text .name { font-size: 0.85rem; max-width: 120px; }
    .menus-bar { border-radius: 0 0 14px 14px; padding: 10px 14px; gap: 8px; }
    .btn-menu { padding: 8px 14px; font-size: 0.8rem; }
    .section-card-body { padding: 16px; }
    .carousel-control-prev-icon,
    .carousel-control-next-icon { padding: 14px; }
    .director-info-grid { grid-template-columns: 1fr; }
    .btn-primary, .btn-secondary, .btn-danger { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
    .header-user { display: none; }
    .header-brand-text h1 { font-size: 0.92rem; }
}
</style>
</head>
<body>

<?php if ($mostrarAlertaManutencao): ?>
<div class="alert-manutencao">
    <i class="fas fa-exclamation-triangle"></i> ATENÇÃO: SISTEMA EM MANUTENÇÃO — Algumas funcionalidades podem estar indisponíveis
</div>
<?php endif; ?>

<div class="main-container">

    <!-- HEADER -->
    <header class="app-header">
        <div class="header-inner">
            <div class="header-brand">
                <img class="header-logo"
                     src="<?php echo (!empty($_SESSION['Conf_ImgLogo'])) ? $_SESSION['Conf_ImgLogo'] : 'logoSunny66a.JPG'; ?>"
                     alt="Logo Empresa">
                <div class="header-brand-text">
                    <h1>SunnyImob <span class="version-tag"><?php echo isset($_SESSION['conf_Versao']) ? $_SESSION['conf_Versao'] : 'v7.0'; ?></span></h1>
                    <p><i class="fas fa-check-circle"></i> Painel Principal · <?php echo strtoupper($_SESSION['Usuario']); ?></p>
                </div>
            </div>
            <div class="header-user">
                <?php
                $userIdImage = "fotos/" . $_SESSION['UserId'] . ".jpg";
                $userImg = file_exists($userIdImage) ? $userIdImage : "fotos/semfoto.jpg";
                ?>
                <img src="<?= $userImg ?>" alt="Foto do usuário">
                <div class="header-user-text">
                    <span class="label">Logado como</span>
                    <span class="name"><?= strtoupper($_SESSION['Usuario']) ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- MENUS -->
    <nav class="menus-bar">
        <div class="dropdown">
            <button class="btn btn-menu" type="button" onclick="window.open('empreend_mapa_v5vbr.php', '_blank')">
                <i class="fas fa-map"></i> Mapa
            </button>
        </div>

        <?php if (UsuarioPodeEditar()): ?>
        <?php endif; ?>

        <div class="dropdown">
            <button class="btn btn-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-list"></i> Consultas
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="empresa_list_v3b.php" target="_blank"><i class="fas fa-building"></i> Imóveis</a></li>
                <li><a class="dropdown-item" href="gestao_empreend_filtro_local2.php" target="_blank"><i class="fas fa-images"></i> Cards</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <button class="btn btn-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-tools"></i> Ferramentas
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="calcInterativaX7d.php" target="_blank"><i class="fas fa-calculator"></i> Calculadora %/R$</a></li>
                <li><a class="dropdown-item" href="calcInterativaX6a-pre-pos.php" target="_blank"><i class="fas fa-calculator"></i> Calculadora Pré/Pós</a></li>
                <li><a class="dropdown-item" href="calcRandon3.php" target="_blank"><i class="fas fa-calculator"></i> Calc. Inteligente</a></li>

                <?php if (UsuarioPodeEditar()): ?>
                    <li><a class="dropdown-item" href="menu4b.php" target="_blank"><i class="fas fa-pencil-alt"></i> Menu Atualizador</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if (strtoupper($_SESSION['Usuario']) == "BARRETO"): ?>
        <div class="dropdown">
            <button class="btn btn-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-code"></i> Dev
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="usr_painel26_2.php" target="_blank"><i class="fas fa-file-alt"></i> Painel RH 2026 Novo</a></li>
                <li><a class="dropdown-item" href="usr_listar.php" target="_blank"><i class="fas fa-file-alt"></i> RH Cad. USR</a></li>
                <li><a class="dropdown-item" href="usr_dupe1.php" target="_blank"><i class="fas fa-file-alt"></i> RH DUPE. USR</a></li>
                <li><a class="dropdown-item" href="rh_status_list.php" target="_blank"><i class="fas fa-file-alt"></i> RH Licença</a></li>
                <li><a class="dropdown-item" href="mapaip3.php" target="_blank"><i class="fas fa-file-alt"></i> RH Map</a></li>
                <li><a class="dropdown-item" href="diretoria_list.php" target="_blank"><i class="fas fa-file-alt"></i> RH Diretorias</a></li>
                <li><a class="dropdown-item" href="gerencia_list.php" target="_blank"><i class="fas fa-file-alt"></i> RH Gerencias</a></li>
                <li><a class="dropdown-item" href="historico_list.php" target="_blank"><i class="fas fa-cogs"></i> Log Acessos</a></li>
                <li><a class="dropdown-item" href="log_list.php" target="_blank"><i class="fas fa-cogs"></i> Log Páginas</a></li>
                <li><a class="dropdown-item" href="log_conta_paginas.php" target="_blank"><i class="fas fa-cogs"></i> Log Páginas 2</a></li>
                <li><a class="dropdown-item" href="manut_vendas_diretorias.php" target="_blank"><i class="fas fa-cogs"></i> Vendas Diretorias</a></li>
                <li><a class="dropdown-item" href="manutmenu.php" target="_blank"><i class="fas fa-cogs"></i> Manutenção do Sistema</a></li>
                <li><a class="dropdown-item" href="listar_simulacao.php" target="_blank"><i class="fas fa-cogs"></i> M2 Completa</a></li>
                <li><a class="dropdown-item" href="listar_simulacao_simples.php" target="_blank"><i class="fas fa-cogs"></i> M2 Simples</a></li>
                <li><a class="dropdown-item" href="log_atualizar_nome_arquivo.php" target="_blank"><i class="fas fa-cogs"></i> Atualiza Log</a></li>
                <li><a class="dropdown-item" href="empreend_update_pavimentos.php" target="_blank"><i class="fas fa-cogs"></i> Pavimentos</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>

    <!-- CARROSSEL -->
    <div class="carousel-container">
        <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel" data-bs-interval="7000">
            <div class="carousel-inner">
                <?php
                if (count($carouselImages) > 0) {
                    foreach ($carouselImages as $i => $image) {
                        $activeClass = ($i == 0) ? "active" : "";
                        echo "<div class=\"carousel-item $activeClass\">";
                        echo "<img src=\"$image\" alt=\"Imagem do Carrossel\">";
                        echo "</div>";
                    }
                } else {
                    echo "<div class=\"carousel-item active\">";
                    echo "<img src=\"https://via.placeholder.com/1400x300?text=Adicione+imagens+na+pasta+/carrossel\" alt=\"Nenhuma imagem encontrada\">";
                    echo "</div>";
                }
                ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="section-card card-filters">
        <div class="section-card-header">
            <i class="fas fa-filter"></i> Filtros de Empreendimentos
        </div>
        <div class="section-card-body">
            <form method="GET" action="empreend_v3_resultados.php" target="_blank">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12 filter-section">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Localidade</label>
                        <select name="localidade" class="form-select">
                            <option value="">Qualquer</option>
                            <?php foreach ($localidades as $localidade) {
                                $v = htmlspecialchars($localidade['Localidade']);
                                echo "<option value=\"$v\">$v</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6 col-12 filter-section">
                        <label class="form-label"><i class="fas fa-bed"></i> Tipologia</label>
                        <select name="tipologia" class="form-select">
                            <option value="">Qualquer</option>
                            <option value="Bang">Bang</option>
                            <option value="Studio">Studio</option>
                            <option value="Casa">Casa</option>
                            <option value="1q">1 Quarto</option>
                            <option value="2q">2 Quartos</option>
                            <option value="3q">3 Quartos</option>
                            <option value="4q">4 Quartos</option>
                            <option value="5q">5+ Quartos</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6 col-12 filter-section">
                        <label class="form-label"><i class="fas fa-tag"></i> Orçamento (R$)</label>
                        <input type="text" name="orcamento_cliente" class="form-control" placeholder="R$ 0,00" id="orcamentoInput">
                    </div>

                    <div class="col-md-3 col-sm-6 col-12 filter-section">
                        <label class="form-label"><i class="fas fa-calendar-alt"></i> Entrega</label>
                        <select name="data_entrega" class="form-select">
                            <option value="">Qualquer</option>
                            <?php foreach ($anosEntrega as $ano) {
                                $af = $ano['AnoEntrega'];
                                echo "<option value=\"$af\">$af</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6 col-12 filter-section">
                        <label class="form-label"><i class="fas fa-building"></i> Empresa</label>
                        <select name="empresa" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($empresas as $empresa) {
                                $nomeEmpresa = strtoupper($empresa['NomeEmpresa']);
                                $selected = (isset($_GET['empresa']) && strtoupper($_GET['empresa']) == $nomeEmpresa) ? "selected" : "";
                                $e = htmlspecialchars($nomeEmpresa);
                                echo "<option value=\"$e\" $selected>$e</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-4 col-sm-6 col-12">
                        <label class="form-label"><i class="fas fa-sliders-h"></i> Características</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <label class="neo-switch">
                                <input type="checkbox" name="BeiraMar" id="BeiraMar">
                                <span class="switch-track"></span>
                                <span><i class="fas fa-umbrella-beach"></i> Beira Mar</span>
                            </label>
                            <label class="neo-switch">
                                <input type="checkbox" name="Varanda" id="Varanda">
                                <span class="switch-track"></span>
                                <span><i class="fas fa-couch"></i> Varanda</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Limpar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DOIS CARDS DE IMÓVEIS (DESTAQUES) -->
    <div class="row g-4 mb-4">

        <!-- ========== CARD 1: PORTO DE GALINHAS / CUPE / MURO ALTO ========== -->
        <div class="col-lg-6">
            <?php if ($destaquePorto): 
                $img = $destaquePorto['_img_resolvida']; // Caminho card/ID.jpg validado pela função
                
                $precoMin = $destaquePorto['PrecoMinimo'] ?? $destaquePorto['Preco'] ?? null;
                $precoMax = $destaquePorto['PrecoMaximo'] ?? $destaquePorto['Preco'] ?? null;
                $metMin = $destaquePorto['MetragemMinima'] ?? $destaquePorto['Metragem'] ?? null;
                $metMax = $destaquePorto['MetragemMaxima'] ?? $destaquePorto['Metragem'] ?? null;
                $tipo = $destaquePorto['Tipologia'] ?? 'Não informada';
                $pav = $destaquePorto['Pavimentos'] ?? 'Não informado';
                $mes = $destaquePorto['MesEntrega'] ?? $destaquePorto['MesPrevisto'] ?? '';
                $ano = $destaquePorto['AnoEntrega'] ?? $destaquePorto['AnoPrevisto'] ?? '';
                $construtora = $destaquePorto['NomeConstrutora'] ?? $destaquePorto['Construtora'] ?? '';
            ?>
            <div class="property-card">
                <div class="property-card-header">
                    <span class="property-loc"><?= htmlspecialchars($destaquePorto['Localidade']) ?></span>
                    <h3 class="property-title">
                        <?= htmlspecialchars($destaquePorto['NomeEmpreendimento']) ?>
                        <?php if($construtora): ?><small><?= htmlspecialchars($construtora) ?></small><?php endif; ?>
                    </h3>
                </div>
                <div class="property-card-body">
                    <div class="property-image">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($destaquePorto['NomeEmpreendimento']) ?>">
                    </div>
                    <div class="property-info">
                        <div class="info-row">
                            <i class="far fa-calendar-check"></i>
                            <div class="info-content">
                                <span class="info-label">Status de Entrega</span>
                                <span class="info-value"><?= ($mes && $ano) ? "PREVISÃO: " . str_pad($mes, 2, '0', STR_PAD_LEFT) . "/" . $ano : "Consulte" ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-tag"></i>
                            <div class="info-content">
                                <span class="info-label">Faixa de Preço</span>
                                <span class="info-value price">
                                    <?php if ($precoMin && $precoMax): ?>
                                        <?= formataMoeda($precoMin) ?> a <?= formataMoeda($precoMax) ?>
                                    <?php elseif ($precoMin): ?>
                                        A partir de <?= formataMoeda($precoMin) ?>
                                    <?php else: ?>
                                        Consulte
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-ruler-combined"></i>
                            <div class="info-content">
                                <span class="info-label">Faixa de Metragem</span>
                                <span class="info-value">
                                    <?php if ($metMin && $metMax): ?>
                                        <?= formataArea($metMin) ?> a <?= formataArea($metMax) ?>
                                    <?php elseif ($metMin): ?>
                                        <?= formataArea($metMin) ?>
                                    <?php else: ?>
                                        Consulte
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-bed"></i>
                            <div class="info-content">
                                <span class="info-label">Tipologia</span>
                                <span class="info-value"><?= htmlspecialchars($tipo) ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-layer-group"></i>
                            <div class="info-content">
                                <span class="info-label">Pavimentos</span>
                                <span class="info-value"><?= htmlspecialchars($pav) ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="info-content">
                                <span class="info-label">Localização</span>
                                <span class="info-value">
                                    <a href="empreend_mapa_v5vbr.php?id=<?= urlencode($destaquePorto['Empreend_ID']) ?>" target="_blank">Ver no Mapa</a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-warning text-center h-100 d-flex align-items-center justify-content-center">
                    Nenhum imóvel com imagem (card/ID.jpg) encontrado em Porto de Galinhas, Cupe ou Muro Alto.
                </div>
            <?php endif; ?>
        </div>

        <!-- ========== CARD 2: CARNEIROS / MARAGOGI ========== -->
        <div class="col-lg-6">
            <?php if ($destaqueCarneiros): 
                $img = $destaqueCarneiros['_img_resolvida']; // Caminho card/ID.jpg validado pela função
                
                $precoMin = $destaqueCarneiros['PrecoMinimo'] ?? $destaqueCarneiros['Preco'] ?? null;
                $precoMax = $destaqueCarneiros['PrecoMaximo'] ?? $destaqueCarneiros['Preco'] ?? null;
                $metMin = $destaqueCarneiros['MetragemMinima'] ?? $destaqueCarneiros['Metragem'] ?? null;
                $metMax = $destaqueCarneiros['MetragemMaxima'] ?? $destaqueCarneiros['Metragem'] ?? null;
                $tipo = $destaqueCarneiros['Tipologia'] ?? 'Não informada';
                $pav = $destaqueCarneiros['Pavimentos'] ?? 'Não informado';
                $mes = $destaqueCarneiros['MesEntrega'] ?? $destaqueCarneiros['MesPrevisto'] ?? '';
                $ano = $destaqueCarneiros['AnoEntrega'] ?? $destaqueCarneiros['AnoPrevisto'] ?? '';
                $construtora = $destaqueCarneiros['NomeConstrutora'] ?? $destaqueCarneiros['Construtora'] ?? '';
            ?>
            <div class="property-card">
                <div class="property-card-header">
                    <span class="property-loc"><?= htmlspecialchars($destaqueCarneiros['Localidade']) ?></span>
                    <h3 class="property-title">
                        <?= htmlspecialchars($destaqueCarneiros['NomeEmpreendimento']) ?>
                        <?php if($construtora): ?><small><?= htmlspecialchars($construtora) ?></small><?php endif; ?>
                    </h3>
                </div>
                <div class="property-card-body">
                    <div class="property-image">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($destaqueCarneiros['NomeEmpreendimento']) ?>">
                    </div>
                    <div class="property-info">
                        <div class="info-row">
                            <i class="far fa-calendar-check"></i>
                            <div class="info-content">
                                <span class="info-label">Status de Entrega</span>
                                <span class="info-value"><?= ($mes && $ano) ? "PREVISÃO: " . str_pad($mes, 2, '0', STR_PAD_LEFT) . "/" . $ano : "Consulte" ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-tag"></i>
                            <div class="info-content">
                                <span class="info-label">Faixa de Preço</span>
                                <span class="info-value price">
                                    <?php if ($precoMin && $precoMax): ?>
                                        <?= formataMoeda($precoMin) ?> a <?= formataMoeda($precoMax) ?>
                                    <?php elseif ($precoMin): ?>
                                        A partir de <?= formataMoeda($precoMin) ?>
                                    <?php else: ?>
                                        Consulte
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-ruler-combined"></i>
                            <div class="info-content">
                                <span class="info-label">Faixa de Metragem</span>
                                <span class="info-value">
                                    <?php if ($metMin && $metMax): ?>
                                        <?= formataArea($metMin) ?> a <?= formataArea($metMax) ?>
                                    <?php elseif ($metMin): ?>
                                        <?= formataArea($metMin) ?>
                                    <?php else: ?>
                                        Consulte
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-bed"></i>
                            <div class="info-content">
                                <span class="info-label">Tipologia</span>
                                <span class="info-value"><?= htmlspecialchars($tipo) ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-layer-group"></i>
                            <div class="info-content">
                                <span class="info-label">Pavimentos</span>
                                <span class="info-value"><?= htmlspecialchars($pav) ?></span>
                            </div>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="info-content">
                                <span class="info-label">Localização</span>
                                <span class="info-value">
                                    <a href="empreend_mapa_v5vbr.php?id=<?= urlencode($destaqueCarneiros['Empreend_ID']) ?>" target="_blank">Ver no Mapa</a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-warning text-center h-100 d-flex align-items-center justify-content-center">
                    Nenhum imóvel com imagem (card/ID.jpg) encontrado em Carneiros ou Maragogi.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- SEÇÃO DIRETOR -->
    <?php
    $sessaoUserId  = $_SESSION['UserId']  ?? 0;
    $usuarioAtual  = $_SESSION['Usuario'] ?? 'Desconhecido';
    $isBarreto     = (strtoupper($usuarioAtual) === 'BARRETO');

    $isDiretor     = false;
    $nomeDiretoria = 'Não informado';
    $nomeUsuario   = $usuarioAtual;

    if ($isBarreto) {
        $isDiretor = true;
        $nomeExibicao = 'BARRETO (Gestor)';
        $nomeDiretoria = 'Gestão Geral';
        $_SESSION['Dir_DiretoriaID'] = 0;
        $_SESSION['NOME_DIRETORIA']  = 'Gestão Geral';
    } else {
        $stmt = $conn->prepare("
            SELECT DiretoriaId, NomeDiretoria, Nome, Usuario, NomeDiretor
            FROM diretorias
            WHERE UserId = :userid
            LIMIT 1
        ");
        $stmt->execute([':userid' => $sessaoUserId]);
        $diretor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($diretor) {
            $isDiretor     = true;
            $nomeUsuario   = $diretor['Usuario'] ?? $usuarioAtual;
            $nomeDiretoria = $diretor['NomeDiretoria'] ?? 'Não informado';
            $nomeExibicao  = !empty($diretor['NomeDiretoria']) ? $diretor['NomeDiretoria'] : ($diretor['NomeDiretor'] ?? 'Diretoria');
            $_SESSION['Dir_DiretoriaID'] = $diretor['DiretoriaId'];
            $_SESSION['NOME_DIRETORIA']  = $diretor['NomeDiretoria'];
        } else {
            $nomeExibicao = 'Acesso Restrito';
            unset($_SESSION['Dir_DiretoriaID'], $_SESSION['NOME_DIRETORIA']);
        }
    }

    $isBloqueado = DiretorioVendasBloqueado();
    $status      = $isBloqueado ? 'BLOQUEADO' : 'LIBERADO';
    $statusIcon  = $isBloqueado ? 'bi-lock-fill' : 'bi-unlock-fill';
    ?>

    <div class="card-director">
        <div class="card-header text-center">
            <i class="bi bi-person-badge-fill me-2"></i>
            <?= htmlspecialchars($nomeExibicao) ?>
        </div>
        <div class="card-body">
            <div class="director-info-grid">
                <div class="director-info-item">
                    <small>Usuário</small>
                    <strong><?= htmlspecialchars($nomeUsuario) ?></strong>
                </div>
                <div class="director-info-item">
                    <small>ID da Diretoria</small>
                    <strong><?= htmlspecialchars($_SESSION['Dir_DiretoriaID'] ?? '0') ?></strong>
                </div>
            </div>
            <div class="text-center">
                <span class="status-badge <?= $isBloqueado ? 'bg-danger' : 'bg-success' ?>">
                    <i class="bi <?= $statusIcon ?>"></i> <?= $status ?>
                </span>
            </div>
        </div>
    </div>

    <?php if (($isDiretor || $isBarreto) && !$isBloqueado): ?>
    <div class="d-flex gap-2 justify-content-center flex-wrap mb-4">
        <a href="gestao_vendas_diretoria3.php" target="_blank" class="btn btn-primary">
            <i class="bi bi-link-45deg"></i> Vendas da Diretoria
        </a>
    </div>
    <?php elseif ($isBloqueado): ?>
    <div class="alert alert-danger text-center mb-4">
        <strong>ACESSO BLOQUEADO (SISTEMA)</strong>
    </div>
    <?php else: ?>
    <div class="alert alert-warning text-center mb-4">
        <strong>USUÁRIO NÃO AUTORIZADO COMO DIRETOR</strong>
    </div>
    <?php endif; ?>

    <!-- LOGOUT -->
    <div class="d-flex justify-content-center my-4">
        <a href="#" onclick="logout(); return false;" class="btn btn-danger">
            <i class="fas fa-sign-out-alt"></i> Sair do Sistema
        </a>
    </div>

</div><!-- /.main-container -->

<?php include "footer.inc"; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.7/jquery.inputmask.min.js"></script>

<script>
    function logout() {
        if (confirm('Deseja realmente sair do sistema?')) {
            window.location.href = 'logout.php';
        }
    }

    $(document).ready(function() {
        $('#orcamentoInput').inputmask('currency', {
            prefix: 'R$ ',
            radixPoint: ',',
            groupSeparator: '.',
            alias: 'numeric',
            digits: 2,
            autoGroup: true,
            removeMaskOnSubmit: false
        });

        $('form').on('submit', function(e) {
            e.preventDefault();

            var rawValue = $('#orcamentoInput').inputmask('unmaskedvalue');
            rawValue = rawValue.replace(',', '.');

            $('<input>').attr({
                type: 'hidden',
                name: 'orcamento_cliente_raw',
                value: rawValue
            }).appendTo(this);

            $('#orcamentoInput').prop('disabled', true);
            this.submit();
        });
    });
</script>
</body>
</html>
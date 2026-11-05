<?php
session_start();
ob_start();

// Incluir arquivos necessários
require_once "conn.php";
require_once "usr_acoes.php";

// ============== Novo! - criado em 19 02 2026
protegerPagina(true);  
// ===========================================

// Desativar warnings para não poluir o JSON
error_reporting(0);


function FormatarMoeda($valor) {
    if (is_null($valor) || !is_numeric($valor)) {
        return "R$ 0,00";
    }
    return "R$ " . number_format($valor, 2, ',', '.');
}

// Obter parâmetros do formulário
$localidade = $_GET['localidade'] ?? '';
$nomeFiltro = $_GET['nome'] ?? '';
$precoMin = $_GET['precoMin'] ?? '';
$tipoImovel = $_GET['tipoImovel'] ?? '';
$beiramar = $_GET['beiramar'] ?? '';
$varanda = $_GET['varanda'] ?? '';
$rooftop = $_GET['rooftop'] ?? '';
$ano = $_GET['Ano'] ?? '';

// Construir cláusula WHERE e array de parâmetros
$vWhere = " 1=1 ";
$params = [];

if (!empty(trim($localidade))) {
    $localidadeUpper = strtoupper(trim($localidade));
    if ($localidadeUpper == "TAMANDARÉ" || $localidadeUpper == "CARNEIROS") {
        $vWhere .= " AND (localidade = :localidade OR localidade = 'TAMANDARÉ' OR localidade = 'CARNEIROS') ";
        $params[':localidade'] = $localidade;
    } else {
        $vWhere .= " AND localidade = :localidade ";
        $params[':localidade'] = $localidade;
    }
}

if ($varanda == "on") {
    $vWhere .= " AND varanda = 1 ";
}

if ($beiramar == "on") {
    $vWhere .= " AND beiramar = 1 ";
}

if ($rooftop == "on") {
    $vWhere .= " AND rooftop = 1 ";
}

if (!empty($ano)) {
    $vWhere .= " AND AnoEntrega = :ano ";
    $params[':ano'] = intval($ano);
}

if (!empty(trim($precoMin))) {
    $precoMinVal = floatval($precoMin);
    $vWhere .= " AND PrecoInicial >= :precoMin80 AND PrecoInicial <= :precoMin120 ";
    $params[':precoMin80'] = $precoMinVal * 0.8;
    $params[':precoMin120'] = $precoMinVal * 1.2;
}

if (!empty(trim($tipoImovel))) {
    $vWhere .= " AND Tipologia LIKE :tipoImovel ";
    $params[':tipoImovel'] = '%' . $tipoImovel . '%';
}

if (!empty(trim($nomeFiltro))) {
    $vWhere .= " AND NomeEmpreendimento LIKE :nome ";
    $params[':nome'] = '%' . $nomeFiltro . '%';
}

// Query para localidades
try {
    $sqlLocalidades = "SELECT DISTINCT Localidade FROM empreendimento WHERE Localidade <> '' ORDER BY Localidade";
    $stmtLocalidades = $conn->query($sqlLocalidades);
    $localidades = $stmtLocalidades->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $localidades = [];
    error_log("Erro ao buscar localidades: " . $e->getMessage());
}

// Query para preços
try {
    $sqlPrecos = "SELECT DISTINCT PrecoInicial FROM empreendimento 
                  WHERE PrecoInicial IS NOT NULL AND PrecoInicial > 0 
                  ORDER BY PrecoInicial";
    $stmtPrecos = $conn->query($sqlPrecos);
    $precos = $stmtPrecos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $precos = [];
    error_log("Erro ao buscar preços: " . $e->getMessage());
}

// Query para empreendimentos
try {
    $sqlEmpreendimentos = "SELECT * FROM empreendimento 
                          WHERE 1=1 AND Ocultar = 0 AND $vWhere 
                          AND Vendido = 0 AND Localizacao IS NOT NULL AND Localizacao <> ''";
    
    $stmtEmpreendimentos = $conn->prepare($sqlEmpreendimentos);
    
    // Bind dos parâmetros
    foreach ($params as $key => $value) {
        if (strpos($key, ':precoMin') !== false || $key == ':ano') {
            $stmtEmpreendimentos->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmtEmpreendimentos->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    
    $stmtEmpreendimentos->execute();
    $empreendimentos = $stmtEmpreendimentos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $empreendimentos = [];
    error_log("Erro ao buscar empreendimentos: " . $e->getMessage());
}

// Preparar dados para JavaScript como JSON
$empreendimentos_js = [];
foreach ($empreendimentos as $emp) {
    $localizacao = $emp['Localizacao'] ?? '';
    
    if (strpos($localizacao, ',') !== false) {
        $partes = explode(',', $localizacao);
        if (count($partes) >= 2) {
            $lat = trim($partes[0]);
            $lng = trim($partes[1]);
            
            if (is_numeric($lat) && is_numeric($lng)) {
                $nomeLimpo = strtoupper($emp['NomeEmpreendimento'] ?? '');
                $nomeLimpo = preg_replace('/^\s*\d+\s*-\s*/', '', $nomeLimpo);
                
                $nomeLimpoEmpresa = strtoupper($emp['NomeEmpresa'] ?? '');
                $nomeLimpoEmpresa = preg_replace('/^\s*\d+\s*-\s*/', '', $nomeLimpoEmpresa);
                
                $descricao = "Local: <b>" . strtoupper($emp['Localidade'] ?? '') . "</b><br>";
                $descricao .= "-Construtora: " . $nomeLimpoEmpresa . "<br>";
                $descricao .= "Metragem: " . Nz($emp['MetragemMin'] ?? '') . " m² – " . Nz($emp['MetragemMax'] ?? '') . " m²<br>";
                $descricao .= "Valores: " . FormatarMoeda($emp['MenorPreco'] ?? 0) . " – " . FormatarMoeda($emp['MaiorPreco'] ?? 0) . "<br>";
                
                if (!empty($emp['MesEntrega']) || !empty($emp['AnoEntrega'])) {
                    $descricao .= "Entrega: ";
                    if (!empty($emp['MesEntrega'])) $descricao .= $emp['MesEntrega'];
                    if (!empty($emp['AnoEntrega'])) {
                        if (!empty($emp['MesEntrega'])) $descricao .= "/";
                        $descricao .= $emp['AnoEntrega'];
                    }
                    $descricao .= "<br>";
                }
                
                $descricao .= "Garagem: " . Nz($emp['VagasGaragem'] ?? '') . " vaga(s)";
                if (!empty($emp['TipoGaragem'])) {
                    $descricao .= ", " . $emp['TipoGaragem'];
                }
                
                if (!empty($emp['ComissaoVenda'])) {
                    $descricao .= ",<br> C-" . $emp['ComissaoVenda'];
                }
                
                $nomeLimpo .= " - " . ($emp['Tipologia'] ?? '');
                
                $vMar = !empty($emp['BeiraMar']) ? "<span style='color: red'> - BEIRA MAR </span>" : " MAR: " . Nz($emp['ProximoMar'] ?? '') . "m";
                $vVar = !empty($emp['Varanda']) ? "<span style='color: blue'> - VARANDA </span>" : "";
                $vRof = !empty($emp['Rooftop']) ? "<span style='color: green'> - ROOFTOP </span>" : "";
                $vAno = $emp['AnoEntrega'] ?? '';
                
                $linkHtml = "<a href='empreend_ficha1.php?id=" . ($emp['Empreend_ID'] ?? '') . "' target='_blank' class='orange-badge'>Ficha</a> ";
                if (isset($_SESSION["UserId"]) && $_SESSION["UserId"] == 1) {
                    $linkHtml .= " <a href='selecao_salvar.php?id=" . ($emp['Empreend_ID'] ?? '') . "' target='_blank' class='orange-badge'>Selecionar</a> ";
                }
                
                $empreendimentos_js[] = [
                    'nome' => ($vAno ? $vAno . "-" : "") . $nomeLimpo . $vMar . $vVar . $vRof,
                    'preco' => $emp['PrecoInicial'] ?? '',
                    'desc' => $descricao . " " . $linkHtml,
                    'foto' => "card/" . ($emp['Empreend_ID'] ?? '') . ".jpg",
                    'lat' => (float)$lat,
                    'lng' => (float)$lng
                ];
            }
        }
    }
}

// Converter para JSON
$empreendimentos_json = json_encode($empreendimentos_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Verificar se há erros no JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    $empreendimentos_json = '[]';
    error_log("Erro ao converter JSON: " . json_last_error_msg());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SunnyImob - Mapa de Empreendimentos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
   integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
   crossorigin=""/>

<style>
/* ===== RESET E ESTILOS BASE ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    width: 100%;
    overflow: hidden;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ===== VARIÁVEIS GLOBAIS ===== */
:root {
  --primary-color: <?php echo isset($_SESSION["conf_CorPrimaria"]) ? $_SESSION["conf_CorPrimaria"] : '#007bff'; ?>;
  --secondary-color: #343a40;
  --header-height: 50px;
  --button-height: 40px;
  --form-width: 350px;
  --transition-speed: 0.3s;
  --accent-color: #A0522D;
}

/* ===== CABEÇALHO FIXO ===== */
.header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--header-height);
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 15px;
    z-index: 1000;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.header h5 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* ===== BOTÃO TOGGLE MOBILE ===== */
.toggle-btn {
    position: fixed;
    top: var(--header-height);
    left: 0;
    right: 0;
    height: var(--button-height);
    background: var(--secondary-color);
    color: white;
    border: none;
    cursor: pointer;
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    transition: background-color var(--transition-speed);
    font-size: 14px;
}

.toggle-btn:hover {
    background: #495057;
}

.toggle-btn i {
    margin-right: 8px;
}

/* ===== BOTÕES GERAIS ===== */
.toggle-desktop-btn, .close-btn {
    background: var(--secondary-color);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color var(--transition-speed);
    font-size: 14px;
    font-weight: 500;
}

.toggle-desktop-btn:hover, .close-btn:hover {
    background: #495057;
}

.close-btn {
    background: var(--accent-color);
}

.close-btn:hover {
    background: #8B4513;
}

/* ===== LAYOUT PRINCIPAL ===== */
#app-container {
    position: relative;
    width: 100%;
    height: 100%;
    padding-top: var(--header-height);
}

/* ===== FORMULÁRIO DE FILTROS ===== */
.form-container {
    position: fixed;
    top: calc(var(--header-height) + var(--button-height));
    left: 0;
    right: 0;
    max-height: 0;
    overflow: hidden;
    background: white;
    z-index: 800;
    transition: max-height var(--transition-speed) ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    padding: 0;
}

.form-container.show {
    max-height: 70vh;
    overflow-y: auto;
    padding: 15px;
    border-top: 1px solid #ddd;
    border-bottom: 1px solid #ddd;
}

.form-container .card {
    border: none;
    box-shadow: none;
}

.form-container .card-body {
    padding: 15px;
}

/* ===== ESTILOS DO FORMULÁRIO ===== */
.form-label {
    font-weight: 600;
    margin-bottom: 5px;
    display: block;
    font-size: 14px;
    color: #495057;
}

.form-select, .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    margin-bottom: 15px;
    font-size: 14px;
    background-color: white;
}

.form-select:focus, .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    outline: none;
}

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.form-check-input {
    margin-right: 8px;
    cursor: pointer;
}

.form-check-label {
    cursor: pointer;
    font-size: 14px;
}

/* ===== BOTÕES DO FORMULÁRIO ===== */
.btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--transition-speed);
    border: none;
    font-size: 14px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

/* ===== ÁREA DO MAPA ===== */
.map-container {
    position: absolute;
    top: calc(var(--header-height) + var(--button-height));
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    z-index: 1;
    transition: all var(--transition-speed) ease;
}

#map {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: #f0f0f0;
    z-index: 1;
}

/* Quando o formulário está aberto no mobile */
.form-container.show ~ .map-container {
    top: calc(var(--header-height) + var(--button-height) + 70vh);
    height: calc(30vh - var(--button-height));
}

/* ===== ESTILOS DOS MARCADORES ===== */
.leaflet-tooltip.marker-label {
    background-color: rgba(255, 255, 153, 0.9);
    border: 1px solid #ccc;
    border-radius: 12px;
    font-size: 11px;
    padding: 3px 8px;
    margin-top: 15px;
    font-weight: 500;
    white-space: nowrap;
}

.leaflet-popup-content-wrapper {
    border-radius: 8px;
    padding: 8px;
    max-width: 300px;
}

.leaflet-popup-content {
    font-size: 13px;
    line-height: 1.4;
}

.leaflet-popup-content img {
    max-width: 100%;
    height: auto;
    margin-top: 8px;
    border-radius: 4px;
}

/* ===== RESPONSIVIDADE - DESKTOP ===== */
@media (min-width: 769px) {
    #app-container {
        display: flex;
        flex-direction: row;
        padding-top: var(--header-height);
    }

    .header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
    }

    .toggle-btn {
        display: none;
    }

    .form-container {
        position: relative;
        top: 0;
        width: var(--form-width);
        height: calc(100vh - var(--header-height));
        max-height: none;
        padding: 15px;
        box-shadow: none;
        border-right: 1px solid #ddd;
        overflow-y: auto;
        z-index: 2;
    }

    .form-container.hidden {
        display: none;
    }

    .map-container {
        position: relative;
        top: 0;
        left: 0;
        flex: 1;
        height: calc(100vh - var(--header-height));
        transition: width var(--transition-speed) ease;
    }

    .form-container:not(.hidden) ~ .map-container {
        width: calc(100% - var(--form-width));
    }
}

/* ===== RESPONSIVIDADE - MOBILE ===== */
@media (max-width: 768px) {
    .toggle-desktop-btn {
        display: none;
    }

    .form-container {
        top: calc(var(--header-height) + var(--button-height));
    }

    .map-container {
        top: calc(var(--header-height) + var(--button-height));
        height: calc(100vh - var(--header-height) - var(--button-height));
    }

    /* Grid para campos no mobile */
    .row-cols-sm-2 > * {
        flex: 0 0 auto;
        width: 50%;
    }
    
    .form-container .form-select {
        font-size: 13px;
        padding: 6px 8px;
    }
    
    .form-container .form-label {
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}

/* ===== SCROLL PERSONALIZADO ===== */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* ===== ESTILOS PARA MEDIÇÃO ===== */
.measure-marker {
    cursor: pointer;
}

.measure-polygon {
    stroke-dasharray: 5,5;
}

.measure-tooltip {
    background-color: rgba(255, 255, 255, 0.9);
    border: 1px solid #666;
    border-radius: 3px;
    font-size: 11px;
    padding: 2px 5px;
}

/* ===== MODAL CUSTOMIZADO ===== */
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.custom-modal-content {
    background: white;
    padding: 20px;
    border-radius: 8px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.custom-modal-content h3 {
    margin-bottom: 15px;
    color: #333;
    font-size: 18px;
}

.custom-modal-content p {
    margin-bottom: 20px;
    color: #666;
    font-size: 14px;
}

.custom-modal-content button {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.custom-modal-content button:hover {
    background-color: #0056b3;
}

/* ===== UTILITÁRIOS ===== */
.orange-badge {
    display: inline-block;
    background-color: #fd7e14;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-decoration: none;
    margin-right: 5px;
    margin-top: 5px;
}

.orange-badge:hover {
    background-color: #e8590c;
    color: white;
    text-decoration: none;
}

/* ===== AJUSTES ESPECÍFICOS ===== */
.form-select {
    height: 38px;
    cursor: pointer;
}

@media (max-width: 576px) {
    .form-container .row {
        display: flex;
        flex-wrap: wrap;
        margin-left: -5px;
        margin-right: -5px;
    }
    
    .form-container .row > div {
        padding-left: 5px;
        padding-right: 5px;
        flex: 0 0 50%;
        max-width: 50%;
    }
    
    .form-container .form-select {
        font-size: 13px;
        padding: 6px 8px;
        min-height: 36px;
    }
    
    .form-container .form-label {
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .header h5 {
        font-size: 1rem;
    }
    
    .header-actions {
        gap: 5px;
    }
    
    .toggle-desktop-btn, .close-btn {
        padding: 4px 8px;
        font-size: 12px;
    }
}

/* Estilo para quando não há resultados */
.no-results {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-results i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #adb5bd;
}

.no-results h4 {
    margin-bottom: 10px;
    color: #495057;
}

.no-results p {
    font-size: 14px;
}
</style>
</head>
<body>
<div id="app-container">
    <!-- Cabeçalho -->
    <div class="header">
        <h5><i class="fas fa-map-marker-alt"></i> SunnyImob - Mapa</h5>
        <div class="header-actions">
            <button class="toggle-desktop-btn" id="toggleDesktopBtn">
                <i class="fas fa-filter"></i> Ocultar Filtros
            </button>
            <button class="close-btn" onclick="window.close()">
                <i class="fas fa-times"></i> Fechar
            </button>
        </div>
    </div>

    <!-- Botão para mostrar/ocultar filtros (mobile) -->
    <button class="toggle-btn" id="toggleBtn">
        <i class="fas fa-filter"></i> Mostrar Filtros
    </button>

    <!-- Container do formulário -->
    <div class="form-container" id="formContainer">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="fas fa-sliders-h"></i> Filtros</h6>
                <form method="get" action="" id="filterForm">
                    <!-- Filtro de Localidade -->
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-map-marker"></i> Localidade:</label>
                        <select name="localidade" class="form-select">
                            <option value="">Todas as localidades</option>
                            <?php if (!empty($localidades)): ?>
                                <?php foreach ($localidades as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc['Localidade']); ?>"
                                        <?php echo (isset($_GET['localidade']) && $_GET['localidade'] == $loc['Localidade']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['Localidade']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Filtros em linha para mobile -->
                    <div class="row g-2">
                        <!-- Filtro de Preço -->
                        <div class="col-6 col-sm-4 mb-2">
                            <label class="form-label fw-bold">Valor Inicial:</label>
                            <select name="precoMin" class="form-select">
                                <option value="">Todos os valores</option>
                                <?php if (!empty($precos)): ?>
                                    <?php foreach ($precos as $preco): 
                                        if ($preco['PrecoInicial'] > 0): ?>
                                            <option value="<?php echo $preco['PrecoInicial']; ?>"
                                                <?php echo (isset($_GET['precoMin']) && $_GET['precoMin'] == $preco['PrecoInicial']) ? 'selected' : ''; ?>>
                                                R$ <?php echo number_format($preco['PrecoInicial'], 0, ',', '.'); ?>
                                            </option>
                                        <?php endif; 
                                    endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Filtro de Tipologia -->
                        <div class="col-6 col-sm-8 mb-2">
                            <label class="form-label fw-bold">Tipologia:</label>
                            <select id="tipoImovel" name="tipoImovel" class="form-select">
                                <option value="">Selecione a tipologia</option>
                                <?php
                                $tipologias = ['STUDIO', 'FLAT', 'CASA', '1Q', '2Q', '3Q', '4Q', '5Q', '6Q'];
                                $tipoAtual = $_GET['tipoImovel'] ?? '';
                                foreach ($tipologias as $tipologia): ?>
                                    <option value="<?php echo $tipologia; ?>"
                                        <?php echo $tipoAtual == $tipologia ? 'selected' : ''; ?>>
                                        <?php echo $tipologia == '1Q' ? '1 Quarto' : 
                                                ($tipologia == '2Q' ? '2 Quartos' : 
                                                ($tipologia == '3Q' ? '3 Quartos' : 
                                                ($tipologia == '4Q' ? '4 Quartos' : 
                                                ($tipologia == '5Q' ? '5 Quartos' : 
                                                ($tipologia == '6Q' ? '6 Quartos' : $tipologia))))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filtro de Ano -->
                        <div class="col12 col-sm-6 mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Ano Entrega:</label>
                            <select id="ano" name="Ano" class="form-select">
                                <option value="">Qualquer ano</option>
                                <?php
                                $anos = ['2025', '2026', '2027', '2028'];
                                $anoAtual = $_GET['Ano'] ?? '';
                                foreach ($anos as $anoOption): ?>
                                    <option value="<?php echo $anoOption; ?>"
                                        <?php echo $anoAtual == $anoOption ? 'selected' : ''; ?>>
                                        <?php echo $anoOption; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Filtros de Características -->
                    <div class="row mb-3">
                        <div class="col12">
                            <label class="form-label fw-bold mb-2"><i class="fas fa-star"></i> Características:</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="beiramar" id="BeiraMar"
                                        <?php echo (isset($_GET['beiramar']) && $_GET['beiramar'] == 'on') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="BeiraMar">Beira Mar</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="varanda" id="Varanda"
                                        <?php echo (isset($_GET['varanda']) && $_GET['varanda'] == 'on') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="Varanda">Varanda</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="rooftop" id="Rooftop"
                                        <?php echo (isset($_GET['rooftop']) && $_GET['rooftop'] == 'on') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="Rooftop">Rooftop</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro por Nome -->
                    <div class="mb-4">
                        <label class="form-label fw-bold"><i class="fas fa-search"></i> Buscar por nome:</label>
                        <input type="text" name="nome" class="form-control" placeholder="Digite o nome do empreendimento..."
                            value="<?php echo htmlspecialchars($_GET['nome'] ?? ''); ?>">
                    </div>

                    <!-- Botões de Ação -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                        <button type="submit" class="btn btn-primary flex-fill me-md-2">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                        </button>
                        <button type="button" class="btn btn-secondary flex-fill" onclick="clearFilters()">
                            <i class="fas fa-eraser"></i> Limpar Filtros
                        </button>
                    </div>
                </form>
                
                <!-- Contador de resultados -->
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="fas fa-building"></i> 
                        <?php echo count($empreendimentos); ?> empreendimento(s) encontrado(s)
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Container do Mapa -->
    <div class="map-container" id="mapContainer">
        <div id="map"></div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

<script>
// ==================== VARIÁVEIS GLOBAIS ====================
var map = null;
var mapInitialized = false;
var empreendimentos = <?php echo $empreendimentos_json; ?>;
var markers = [];
var measureMode = false;
var measurePoints = [];
var measurePolygon = null;
var measureMarkers = [];
var measureTooltips = [];

// ==================== FUNÇÕES DO MAPA ====================

/**
 * Inicializa o mapa Leaflet
 */
function initMap() {
    console.log('Inicializando mapa...');
    
    try {
        // Verificar se o elemento do mapa existe
        const mapElement = document.getElementById('map');
        if (!mapElement) {
            console.error('Elemento #map não encontrado no DOM');
            return;
        }
        
        // Forçar estilos no elemento do mapa
        mapElement.style.position = 'absolute';
        mapElement.style.top = '0';
        mapElement.style.left = '0';
        mapElement.style.width = '100%';
        mapElement.style.height = '100%';
        mapElement.style.zIndex = '1';
        
        // Criar o mapa
        map = L.map('map', {
            center: [-8.0522, -34.9286], // Coordenadas de Recife
            zoom: 13,
            zoomControl: true,
            attributionControl: true,
            preferCanvas: true
        });
        
        console.log('Mapa Leaflet criado com sucesso');
        
        // Adicionar camada base do OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
            minZoom: 3
        }).addTo(map);
        
        console.log('Camada base adicionada');
        
        // Adicionar controle de camadas
        L.control.layers({
            "Mapa Padrão": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
            "Satélite": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
            })
        }).addTo(map);
        
        // Adicionar marcadores
        addMarkersToMap();
        
        // Adicionar controle de medição
        addMeasureControl();
        
        // Redimensionar mapa após um breve delay
        setTimeout(() => {
            if (map) {
                map.invalidateSize();
                console.log('Mapa redimensionado');
            }
        }, 300);
        
        mapInitialized = true;
        console.log('Mapa inicializado com sucesso!');
        
    } catch (error) {
        console.error('Erro ao inicializar mapa:', error);
        // Mostrar mensagem de erro no mapa
        const mapElement = document.getElementById('map');
        if (mapElement) {
            mapElement.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; color: #dc3545; padding: 20px; text-align: center;">
                    <div>
                        <h4><i class="fas fa-exclamation-triangle"></i> Erro ao carregar o mapa</h4>
                        <p>${error.message}</p>
                        <button onclick="initMap()" class="btn btn-primary mt-3">
                            <i class="fas fa-redo"></i> Tentar novamente
                        </button>
                    </div>
                </div>
            `;
        }
    }
}

/**
 * Adiciona marcadores ao mapa
 */
function addMarkersToMap() {
    if (!map) {
        console.error('Mapa não inicializado');
        return;
    }
    
    console.log(`Adicionando ${empreendimentos.length} marcadores...`);
    
    // Limpar marcadores anteriores
    clearMarkers();
    
    // Ícone personalizado
    const customIcon = L.icon({
        iconUrl: 'icon/iconSunny1.png', // Substitua pelo caminho correto do seu marcador
        iconSize: [32, 45],    // Tamanho do ícone
        iconAnchor: [16, 45],  // Ponto do ícone que corresponde à posição do marcador
        popupAnchor: [0, -40]  // Ponto a partir do qual o popup deve abrir
    });
    
    const bounds = [];
    let validMarkers = 0;
    
    empreendimentos.forEach((emp, index) => {
        // Verificar se as coordenadas são válidas
        if (!emp || emp.lat === undefined || emp.lng === undefined || 
            isNaN(emp.lat) || isNaN(emp.lng) || 
            emp.lat === 0 || emp.lng === 0) {
            console.warn(`Coordenadas inválidas para empreendimento ${index}:`, emp);
            return;
        }
        
        try {
            // Criar marcador
            const marker = L.marker([emp.lat, emp.lng], {
                icon: customIcon,
                title: emp.nome || 'empreendimento'
            });
            
            // Criar conteúdo do popup
            const popupContent = `
                <div style="max-width: 280px; min-width: 240px;">
                    <h6 style="margin: 0 0 10px 0; color: #333; font-weight: bold; font-size: 14px;">
                        ${emp.nome || 'empreendimento'}
                    </h6>
                    <div style="font-size: 12px; line-height: 1.4; color: #555;">
                        ${emp.desc || ''}
                    </div>
                    ${emp.foto ? `
                        <div style="margin-top: 10px;">
                            <img src="${emp.foto}" 
                                 style="max-width: 100%; border-radius: 4px; display: block;"
                                 onerror="this.style.display='none'">
                        </div>
                    ` : ''}
                    <div style="margin-top: 10px; text-align: center;">
                        <a href="#" onclick="event.preventDefault(); map.closePopup();" 
                           style="font-size: 11px; color: #666; text-decoration: none;">
                            <i class="fas fa-times"></i> Fechar
                        </a>
                    </div>
                </div>
            `;
            
            // Adicionar popup
            marker.bindPopup(popupContent, {
                maxWidth: 300,
                minWidth: 250,
                autoClose: false,
                closeOnClick: false
            });
            
            // Adicionar tooltip
            if (emp.nome) {
                marker.bindTooltip(emp.nome, {
                    permanent: true,
                    direction: 'top',
                    offset: [0, -60],
                    className: 'marker-label',
                    opacity: 0.9,
                    interactive: false
                });
            }
            
            // Adicionar ao mapa
            marker.addTo(map);
            markers.push(marker);
            bounds.push([emp.lat, emp.lng]);
            validMarkers++;
            
        } catch (error) {
            console.error(`Erro ao criar marcador ${index}:`, error);
        }
    });
    
    console.log(`${validMarkers} marcadores adicionados com sucesso`);
    
    // Ajustar visualização do mapa para mostrar todos os marcadores
    if (bounds.length > 0) {
        try {
            const latLngBounds = L.latLngBounds(bounds);
            map.fitBounds(latLngBounds, { 
                padding: [50, 50],
                maxZoom: 15
            });
            console.log('Mapa ajustado para mostrar todos os marcadores');
        } catch (error) {
            console.error('Erro ao ajustar bounds:', error);
        }
    } else {
        console.log('Nenhum marcador válido para ajustar o mapa');
    }
}

/**
 * Limpa todos os marcadores do mapa
 */
function clearMarkers() {
    markers.forEach(marker => {
        if (map && marker) {
            map.removeLayer(marker);
        }
    });
    markers = [];
}

/**
 * Adiciona controle de medição ao mapa
 */
function addMeasureControl() {
    if (!map) return;
    
    const MeasureControl = L.Control.extend({
        options: {
            position: 'topright'
        },
        
        onAdd: function(map) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            const button = L.DomUtil.create('a', '', container);
            button.href = '#';
            button.title = 'Ativar/Desativar medição de área';
            button.innerHTML = '<i class="fas fa-ruler-combined"></i>';
            button.style.fontSize = '16px';
            button.style.lineHeight = '30px';
            button.style.width = '30px';
            button.style.textAlign = 'center';
            button.style.display = 'block';
            
            L.DomEvent
                .on(button, 'click', L.DomEvent.stop)
                .on(button, 'click', () => toggleMeasure());
            
            return container;
        }
    });
    
    map.addControl(new MeasureControl());
}

/**
 * Alterna modo de medição
 */
function toggleMeasure() {
    if (measureMode) {
        resetMeasurement();
    } else {
        startMeasurement();
    }
}

/**
 * Inicia modo de medição
 */
function startMeasurement() {
    if (!map) return;
    
    measureMode = true;
    measurePoints = [];
    
    // Adicionar evento de clique
    map.on('click', addMeasurePoint);
    
    // Criar elemento para mostrar área
    if (!document.getElementById('measure-total')) {
        const totalDiv = document.createElement('div');
        totalDiv.id = 'measure-total';
        totalDiv.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            font-family: Arial, sans-serif;
            font-size: 14px;
            border: 1px solid #ddd;
            max-width: 300px;
        `;
        totalDiv.innerHTML = '<strong><i class="fas fa-ruler"></i> Modo Medição</strong><br>Clique no mapa para criar pontos. Duplo clique para finalizar.';
        document.body.appendChild(totalDiv);
    }
    
    // Mostrar mensagem
    showMessage('Modo medição ativado. Clique no mapa para criar pontos.', 'info');
}

/**
 * Adiciona ponto de medição
 */
function addMeasurePoint(e) {
    if (!measureMode || !map) return;
    
    measurePoints.push(e.latlng);
    
    // Adicionar marcador do ponto
    const marker = L.circleMarker(e.latlng, {
        radius: 5,
        color: '#ff0000',
        fillColor: '#ff0000',
        fillOpacity: 0.8,
        weight: 2
    }).addTo(map);
    
    measureMarkers.push(marker);
    
    // Adicionar linha entre pontos
    if (measurePoints.length > 1) {
        // Remover polígono anterior se existir
        if (measurePolygon) {
            map.removeLayer(measurePolygon);
        }
        
        // Criar novo polígono
        measurePolygon = L.polygon(measurePoints, {
            color: '#ff0000',
            weight: 2,
            fillColor: '#ff0000',
            fillOpacity: 0.2,
            dashArray: '5,5'
        }).addTo(map);
        
        // Calcular e mostrar área
        const area = calculateArea(measurePoints);
        const perimeter = calculatePerimeter(measurePoints);
        
        const totalDiv = document.getElementById('measure-total');
        if (totalDiv) {
            totalDiv.innerHTML = `
                <strong><i class="fas fa-ruler"></i> Medição</strong><br>
                Área: ${formatArea(area)}<br>
                Perímetro: ${formatDistance(perimeter)}<br>
                <small>Pontos: ${measurePoints.length}</small>
            `;
        }
    }
    
    // Adicionar evento de duplo clique para finalizar
    map.on('dblclick', endMeasurement);
}

/**
 * Calcula área do polígono
 */
function calculateArea(points) {
    if (points.length < 3) return 0;
    
    let area = 0;
    const n = points.length;
    
    for (let i = 0; i < n; i++) {
        const j = (i + 1) % n;
        area += points[i].lng * points[j].lat;
        area -= points[j].lng * points[i].lat;
    }
    
    return Math.abs(area) / 2;
}

/**
 * Calcula perímetro do polígono
 */
function calculatePerimeter(points) {
    if (points.length < 2) return 0;
    
    let perimeter = 0;
    for (let i = 0; i < points.length; i++) {
        const j = (i + 1) % points.length;
        perimeter += points[i].distanceTo(points[j]);
    }
    
    return perimeter;
}

/**
 * Formata área
 */
function formatArea(area) {
    // Área em metros quadrados
    const areaM2 = area * 111319.9 * 111319.9; // Conversão aproximada
    
    if (areaM2 > 10000) {
        return (areaM2 / 10000).toFixed(2) + ' hectares';
    } else if (areaM2 > 1000000) {
        return (areaM2 / 1000000).toFixed(2) + ' km²';
    } else {
        return Math.round(areaM2) + ' m²';
    }
}

/**
 * Formata distância
 */
function formatDistance(distance) {
    if (distance > 1000) {
        return (distance / 1000).toFixed(2) + ' km';
    } else {
        return Math.round(distance) + ' m';
    }
}

/**
 * Finaliza medição
 */
function endMeasurement() {
    if (!measureMode || !map) return;
    
    map.off('click', addMeasurePoint);
    map.off('dblclick', endMeasurement);
    
    if (measurePoints.length >= 3) {
        const area = calculateArea(measurePoints);
        const perimeter = calculatePerimeter(measurePoints);
        
        showMessage(`Medição finalizada! Área: ${formatArea(area)}, Perímetro: ${formatDistance(perimeter)}`, 'success');
    } else {
        showMessage('Medição cancelada. São necessários pelo menos 3 pontos.', 'warning');
    }
    
    resetMeasurement();
}

/**
 * Reseta medição
 */
function resetMeasurement() {
    measureMode = false;
    measurePoints = [];
    
    // Remover marcadores
    measureMarkers.forEach(marker => {
        if (map && marker) {
            map.removeLayer(marker);
        }
    });
    measureMarkers = [];
    
    // Remover polígono
    if (measurePolygon && map) {
        map.removeLayer(measurePolygon);
        measurePolygon = null;
    }
    
    // Remover tooltips
    measureTooltips.forEach(tooltip => {
        if (map && tooltip) {
            map.removeLayer(tooltip);
        }
    });
    measureTooltips = [];
    
    // Remover elemento de área
    const totalDiv = document.getElementById('measure-total');
    if (totalDiv) {
        totalDiv.remove();
    }
    
    // Remover eventos
    if (map) {
        map.off('click', addMeasurePoint);
        map.off('dblclick', endMeasurement);
    }
}

// ==================== FUNÇÕES DO FORMULÁRIO ====================

/**
 * Alterna formulário no desktop
 */
function toggleDesktopForm() {
    const formContainer = document.getElementById('formContainer');
    const toggleBtn = document.getElementById('toggleDesktopBtn');
    
    if (!formContainer || !toggleBtn) return;
    
    formContainer.classList.toggle('hidden');
    
    const icon = formContainer.classList.contains('hidden') ? 'fa-filter' : 'fa-times';
    const text = formContainer.classList.contains('hidden') ? 'Mostrar Filtros' : 'Ocultar Filtros';
    
    toggleBtn.innerHTML = `<i class="fas ${icon}"></i> ${text}`;
    
    // Redimensionar mapa
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 300);
}

/**
 * Alterna formulário no mobile
 */
function toggleMobileForm() {
    const formContainer = document.getElementById('formContainer');
    const toggleBtn = document.getElementById('toggleBtn');
    
    if (!formContainer || !toggleBtn) return;
    
    formContainer.classList.toggle('show');
    
    const icon = formContainer.classList.contains('show') ? 'fa-times' : 'fa-filter';
    const text = formContainer.classList.contains('show') ? 'Ocultar Filtros' : 'Mostrar Filtros';
    
    toggleBtn.innerHTML = `<i class="fas ${icon}"></i> ${text}`;
    
    // Redimensionar mapa
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 300);
}

/**
 * Verifica tamanho da tela e ajusta layout
 */
function checkScreenSize() {
    const isMobile = window.innerWidth <= 768;
    const formContainer = document.getElementById('formContainer');
    const toggleBtn = document.getElementById('toggleBtn');
    const desktopBtn = document.getElementById('toggleDesktopBtn');
    
    if (isMobile) {
        // Modo mobile
        if (toggleBtn) toggleBtn.style.display = 'flex';
        if (desktopBtn) desktopBtn.style.display = 'none';
        if (formContainer) formContainer.classList.remove('hidden');
    } else {
        // Modo desktop
        if (toggleBtn) toggleBtn.style.display = 'none';
        if (desktopBtn) desktopBtn.style.display = 'block';
        if (formContainer) formContainer.classList.remove('show');
    }
    
    // Redimensionar mapa
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 200);
}

/**
 * Limpa todos os filtros
 */
function clearFilters() {
    window.location.href = window.location.pathname;
}

/**
 * Mostra mensagem temporária
 */
function showMessage(message, type = 'info') {
    // Remover mensagem anterior se existir
    const oldMsg = document.getElementById('temp-message');
    if (oldMsg) oldMsg.remove();
    
    // Criar nova mensagem
    const msgDiv = document.createElement('div');
    msgDiv.id = 'temp-message';
    msgDiv.style.cssText = `
        position: fixed;
        top: 100px;
        left: 50%;
        transform: translateX(-50%);
        padding: 10px 20px;
        background: ${type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#17a2b8'};
        color: white;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 2000;
        font-family: Arial, sans-serif;
        font-size: 14px;
        animation: fadeIn 0.3s;
    `;
    
    // Adicionar estilos de animação
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
    `;
    document.head.appendChild(style);
    
    msgDiv.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i> ${message}`;
    document.body.appendChild(msgDiv);
    
    // Remover após 3 segundos
    setTimeout(() => {
        if (msgDiv.parentNode) {
            msgDiv.style.opacity = '0';
            msgDiv.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (msgDiv.parentNode) {
                    msgDiv.remove();
                }
            }, 300);
        }
    }, 3000);
}

// ==================== INICIALIZAÇÃO ====================

/**
 * Configura event listeners
 */
function setupEventListeners() {
    // Botões do formulário
    const toggleBtn = document.getElementById('toggleBtn');
    const desktopBtn = document.getElementById('toggleDesktopBtn');
    
    if (toggleBtn) toggleBtn.addEventListener('click', toggleMobileForm);
    if (desktopBtn) desktopBtn.addEventListener('click', toggleDesktopForm);
    
    // Redimensionamento da janela
    window.addEventListener('resize', checkScreenSize);
    window.addEventListener('orientationchange', () => {
        setTimeout(checkScreenSize, 100);
    });
    
    // Formulário
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            // Validar formulário antes de enviar
            const localidade = this.querySelector('[name="localidade"]');
            if (localidade && localidade.value === '') {
                showMessage('Selecione uma localidade para filtrar', 'info');
            }
        });
    }
    
    // Prevenir duplo clique no mapa
    document.addEventListener('dblclick', function(e) {
        if (e.target.closest('#map')) {
            e.preventDefault();
        }
    });
}

/**
 * Inicializa a aplicação
 */
function initApp() {
    console.log('Inicializando aplicação...');
    
    // Configurar event listeners
    setupEventListeners();
    
    // Verificar tamanho da tela
    checkScreenSize();
    
    // Inicializar mapa
    initMap();
    
    // Verificar se há empreendimentos
    if (empreendimentos.length === 0) {
        showMessage('Nenhum empreendimento encontrado com os filtros atuais.', 'info');
    }
    
    console.log('Aplicação inicializada com sucesso!');
}

// ==================== EVENTOS ====================

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', initApp);

// Redimensionar mapa quando a página carregar completamente
window.addEventListener('load', function() {
    console.log('Página completamente carregada');
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
            console.log('Mapa redimensionado após load');
        }
    }, 500);
});

// Expor funções para debug
window.debugMap = function() {
    console.log('=== DEBUG DO MAPA ===');
    console.log('Mapa inicializado:', mapInitialized);
    console.log('Objeto mapa:', map);
    console.log('Elemento #map:', document.getElementById('map'));
    console.log('Empreendimentos:', empreendimentos.length);
    console.log('Marcadores:', markers.length);
    console.log('Modo medição:', measureMode);
};
</script>
</body>
</html>
<?php
// Fechar conexão
$conn = null;
ob_end_flush();
?>
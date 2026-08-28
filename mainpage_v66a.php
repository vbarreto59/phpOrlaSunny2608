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

// Incluir arquivos de configuração
require_once "conn.php";
require_once "usr_acoes.php";
require_once "theme_config.php"; // <-- CARREGA O TEMA
AcoesPermitidas();
require_once "manutencao_config.php";
require_once "log_navegacao.php";


// Verificar se o usuário está logado
// removido em 19 02 2026
//if (!isset($_SESSION['Usuario']) || empty($_SESSION['Usuario'])) {
//    header("Location: login_p1.php");
//    exit();
//}

// Novo! - criado em 19 02 2026
protegerPagina(true);  // ou protegerPagina(false) se não quiser exigir licença


if (!registrarNavegacao($conn, $_SESSION)) {
    echo "Erro no registro da navegação.";
    exit;
}


$mostrarAlertaManutencao = EstaEmManutencao('sunny');

$_SESSION['menuPrincipal'] = "mainpage_v66a.php";

// Variaveis para armazenar imagens do carrossel
$carouselImages = [];

// Listar arquivos da pasta "carrossel"
$carouselPath = dirname(__FILE__) . "/carrossel";

if (is_dir($carouselPath)) {
    $files = scandir($carouselPath);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $carouselPath . '/' . $file;
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Filtrar apenas arquivos de imagem
        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $carouselImages[] = "carrossel/" . $file;
        }
    }
}

// ============= 26 04 2026 - Criar as variaveis de sessao da empresa
$idEmp = $_SESSION['IdEmp']; 
CarregarConfiguracoesEmpresa($idEmp);
//  =================================



// =======================================================
// FUNÇÕES PARA CONSULTAS COM PDO
// =======================================================

/**
 * Busca localidades para o filtro
 */
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

/**
 * Busca anos de entrega para o filtro
 */
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

/**
 * Busca empresas para o filtro
 */
function getEmpresas() {
    global $conn;
    $empresas = [];
    
    try {
        $sql = "SELECT NomeEmpresa FROM empresa 
                ORDER BY NomeEmpresa";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar empresas: " . $e->getMessage());
    }
    
    return $empresas;
}

/**
 * Busca premiações ativas
 */
function getPremiacoesAtivas() {
    global $conn;
    $premiacoes = [];
    
    try {
        $sql = "SELECT E.Empreend_ID, E.NomeEmpreendimento, P.PremiacaoID, P.Titulo, P.Descricao, 
                       P.InicioDia, P.InicioMes, P.InicioAno, P.TerminoDia, P.TerminoMes, P.TerminoAno 
                FROM empreendimento AS E 
                INNER JOIN premiacao AS P ON E.Empreend_ID = P.Empreend_ID 
                WHERE P.Ativa = -1 AND 
                ((P.TerminoAno > YEAR(NOW())) 
                OR (P.TerminoAno = YEAR(NOW()) AND P.TerminoMes > MONTH(NOW())) 
                OR (P.TerminoAno = YEAR(NOW()) AND P.TerminoMes = MONTH(NOW()) AND P.TerminoDia >= DAY(NOW()))) 
                ORDER BY E.NomeEmpreendimento, P.PremiacaoID DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $premiacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar premiações: " . $e->getMessage());
    }
    
    return $premiacoes;
}

// =======================================================
// EXECUTAR CONSULTAS
// =======================================================

$localidades = getLocalidades();
$anosEntrega = getAnosEntrega();
$empresas = getEmpresas();
$premiacoes = getPremiacoesAtivas();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
    <title>Filtro de Empreendimentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
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
    
    /* Cores para alertas e estados (fixas, mas podem ser ajustadas) */
    --danger: #dc3545;
    --success: #28a745;
    --warning: #ffc107;
    --info: #17a2b8;
    --dark: #343a40;
}

/* ================================================== */
/* ESTILOS GERAIS (Reset e Base) */
/* ================================================== */
body {
    font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;
    background: var(--bg-body);
    color: var(--text-color);
    min-height: 100vh;
    margin: 0;
    line-height: 1.5;
}

/* Reset para evitar conflitos em componentes críticos */
.carousel-container, .carousel-container *,
.empreendimento-container, .empreendimento-container * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* ================================================== */
/* COMPONENTE: HEADER */
/* ================================================== */
.header {
    background: var(--primary);
    color: #fff;
    padding: 0.8rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 1rem;
    min-height: 80px;
    border-bottom: 4px solid var(--shadow-color);
    box-shadow: 0 4px 0px 0px var(--shadow-color);
}

.header-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 0 0 auto;
    min-width: 200px;
}

.header-logo img {
    height: auto;
    max-height: 60px;
    width: auto;
    max-width: 250px;
    object-fit: contain;
    margin: 0 auto 10px auto;
    border: 2px solid var(--shadow-color);
    border-radius: 8px;
    box-shadow: 3px 3px 0px 0px var(--shadow-color);
    background: #fff;
    padding: 4px;
}

.user-image {
    border-radius: 50%;
    width: 45px;
    height: 45px;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 3px 3px 0px 0px var(--shadow-color);
}

.image-container {
    display: flex;
    gap: 20px;
    align-items: center;
}

.header-logo h6 {
    font-weight: 700;
    margin-top: 4px;
    opacity: 0.9;
}

/* ================================================== */
/* COMPONENTE: NAVEGAÇÃO (Menus) */
/* ================================================== */
.menus-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    background: var(--primary);
    padding: 0.8rem 1.5rem;
    border-bottom: 4px solid var(--shadow-color);
    box-shadow: 0 4px 0px 0px var(--shadow-color);
}

.btn-menu {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    border: 2px solid var(--shadow-color);
    box-shadow: 3px 3px 0px 0px var(--shadow-color);
    padding: 0.5rem 1.2rem;
    border-radius: 10px;
    font-weight: 700;
    transition: all 0.15s;
}
.btn-menu:hover {
    transform: translate(-2px, -2px);
    box-shadow: 5px 5px 0px 0px var(--shadow-color);
    background: rgba(255,255,255,0.25);
    color: #fff;
}
.btn-menu:active {
    transform: translate(2px, 2px);
    box-shadow: 1px 1px 0px 0px var(--shadow-color);
}

.dropdown-menu {
    background: var(--card-bg);
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0px 0px var(--shadow-color);
    border-radius: 12px;
    padding: 0.5rem;
}
.dropdown-item {
    color: var(--text-color);
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.15s;
}
.dropdown-item:hover {
    background: var(--primary);
    color: #fff;
    transform: translateX(4px);
}

/* ================================================== */
/* COMPONENTE: CARROSSEL */
/* ================================================== */
.carousel-container {
    background: var(--primary);
    max-width: 1400px;
    margin: 1.5rem auto;
    border: 4px solid var(--shadow-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 6px 6px 0px 0px var(--shadow-color);
}

.carousel-inner {
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f8f8f8;
    height: 260px;
    width: 100%;
}

.carousel-inner img {
    max-height: 260px;
    max-width: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
}

@media (max-width: 768px) {
    .carousel-inner { height: 180px; }
    .carousel-inner img { max-height: 180px; }
}
@media (max-width: 480px) {
    .carousel-inner { height: 150px; }
    .carousel-inner img { max-height: 150px; }
}

/* ================================================== */
/* COMPONENTE: CARDS DE FILTRO */
/* ================================================== */
.card-filters {
    background: var(--card-bg);
    border: 3px solid var(--shadow-color);
    border-radius: 16px;
    box-shadow: 6px 6px 0px 0px var(--shadow-color);
    margin: 1.5rem 0;
    overflow: hidden;
}

.card-filters .card-header {
    background: var(--primary);
    color: #fff;
    padding: 0.8rem 1.5rem;
    font-weight: 700;
    font-size: 1.1rem;
    border-bottom: 3px solid var(--shadow-color);
}
.card-filters .card-header i {
    margin-right: 8px;
}

.card-filters .card-body {
    padding: 1.5rem;
}

.card-filters .form-label {
    font-weight: 700;
    color: var(--text-color);
}
.card-filters .form-control,
.card-filters .form-select {
    border: 2px solid var(--shadow-color);
    border-radius: 10px;
    padding: 0.6rem 1rem;
    background: #fff;
    color: var(--text-color);
    transition: all 0.2s;
}
.card-filters .form-control:focus,
.card-filters .form-select:focus {
    outline: none;
    box-shadow: 4px 4px 0px 0px var(--shadow-color);
    border-color: var(--primary);
}
.card-filters .form-check-input {
    border: 2px solid var(--shadow-color);
    box-shadow: 2px 2px 0px 0px var(--shadow-color);
}
.card-filters .form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Botões neobrutalistas usando variáveis do tema */
.btn-primary {
    background: var(--btn-bg);
    color: var(--btn-text);
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0px 0px var(--shadow-color);
    border-radius: 10px;
    padding: 0.6rem 1.8rem;
    font-weight: 700;
    transition: all 0.15s;
}
.btn-primary:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0px 0px var(--shadow-color);
    background: var(--btn-hover);
    color: #fff;
}
.btn-primary:active {
    transform: translate(2px, 2px);
    box-shadow: 2px 2px 0px 0px var(--shadow-color);
}

.btn-secondary {
    background: #6c757d;
    color: #fff;
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0px 0px var(--shadow-color);
    border-radius: 10px;
    padding: 0.6rem 1.8rem;
    font-weight: 700;
    transition: all 0.15s;
}
.btn-secondary:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0px 0px var(--shadow-color);
    background: #5a6268;
}
.btn-secondary:active {
    transform: translate(2px, 2px);
    box-shadow: 2px 2px 0px 0px var(--shadow-color);
}

.btn-danger {
    background: var(--danger);
    color: #fff;
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0px 0px var(--shadow-color);
    border-radius: 10px;
    padding: 0.6rem 1.8rem;
    font-weight: 700;
    transition: all 0.15s;
}
.btn-danger:hover {
    transform: translate(-2px, -2px);
    box-shadow: 6px 6px 0px 0px var(--shadow-color);
    background: #b02a37;
}
.btn-danger:active {
    transform: translate(2px, 2px);
    box-shadow: 2px 2px 0px 0px var(--shadow-color);
}

/* ================================================== */
/* COMPONENTE: CARDS DE PRÊMIOS */
/* ================================================== */
.empreendimento-container {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    justify-content: center;
    padding: 20px 10px;
}

.empreendimento-card {
    background: var(--card-bg);
    border: 3px solid var(--shadow-color);
    border-radius: 16px;
    box-shadow: 5px 5px 0px 0px var(--shadow-color);
    padding: 25px;
    max-width: 380px;
    width: 100%;
    transition: all 0.2s;
}
.empreendimento-card:hover {
    transform: translateY(-5px);
    box-shadow: 8px 8px 0px 0px var(--shadow-color);
}

.empreendimento-card-header {
    font-weight: 800;
    font-size: 1.2rem;
    color: var(--primary);
    border-bottom: 3px solid var(--shadow-color);
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.empreendimento-card-header i {
    color: var(--primary);
    margin-right: 8px;
}

.premiacao-item {
    padding: 10px 0;
    border-bottom: 2px solid var(--shadow-color);
    list-style: none;
}
.premiacao-item:last-child {
    border-bottom: none;
}

.premiacao-titulo {
    font-weight: 700;
    color: var(--primary);
}
.premiacao-descricao {
    font-size: 0.9rem;
    color: var(--text-color);
    opacity: 0.8;
    margin: 4px 0;
}
.premiacao-periodo {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-color);
}
.premiacao-list {
    padding-left: 0;
}
.no-premiacoes {
    color: var(--text-color);
    opacity: 0.7;
    font-style: italic;
    padding: 10px 0;
}

/* ================================================== */
/* COMPONENTE: ALERTA DE MANUTENÇÃO */
/* ================================================== */
.alert-manutencao {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    border-radius: 0;
    text-align: center;
    padding: 15px;
    font-weight: 700;
    background: var(--danger);
    color: #fff;
    border-bottom: 4px solid var(--shadow-color);
    box-shadow: 0 4px 0px 0px var(--shadow-color);
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

/* ================================================== */
/* COMPONENTE: CARD DIRETOR */
/* ================================================== */
.card-director {
    background: var(--card-bg);
    border: 3px solid var(--shadow-color);
    border-radius: 16px;
    box-shadow: 6px 6px 0px 0px var(--shadow-color);
    overflow: hidden;
}
.card-director .card-header {
    background: var(--primary);
    color: #fff;
    padding: 0.8rem 1.5rem;
    font-weight: 700;
    border-bottom: 3px solid var(--shadow-color);
}

/* Badge de status */
.badge.bg-success {
    background-color: var(--success) !important;
    color: #fff;
}
.badge.bg-danger {
    background-color: var(--danger) !important;
    color: #fff;
}

/* Alertas personalizados */
.alert-danger {
    background: var(--danger);
    color: #fff;
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0px 0px var(--shadow-color);
}
.alert-warning {
    background: var(--warning);
    color: #000;
    border: 2px solid var(--shadow-color);
    box-shadow: 4px 4px 0px 0px var(--shadow-color);
}

/* ================================================== */
/* MEDIA QUERIES (Responsividade) */
/* ================================================== */
@media (max-width: 992px) {
    .header-logo img { max-height: 50px; }
}
@media (max-width: 768px) {
    .header { flex-direction: column; align-items: center; }
    .header-logo { min-width: auto; }
    .image-container { flex-wrap: wrap; justify-content: center; }
}
@media (max-width: 576px) {
    .header-logo h6 { font-size: 0.9rem; }
    .btn-menu { padding: 0.4rem 0.8rem; font-size: 0.85rem; }
    .card-filters .card-body { padding: 1rem; }
}
</style>

</head>
<body>
    <?php if ($mostrarAlertaManutencao): ?>
    <div class="alert-manutencao">
        <i class="fas fa-exclamation-triangle"></i> ATENÇÃO: SISTEMA EM MANUTENÇÃO - Algumas funcionalidades podem estar indisponíveis
    </div>
    <?php endif; ?>

    <div class="main-container"> 
        <!-- Header -->
        <div class="header">
            <div class="header-logo">
                <div class="image-container">
                    <img src="<?php echo (!empty($_SESSION['Conf_ImgLogo'])) ? $_SESSION['Conf_ImgLogo'] : 'logoSunny66a.JPG'; ?>" 
                         alt="Logo Empresa" 
                         width="288" 
                         height="45" 
                         style="object-fit: contain;">
                    <?php
                    $userIdImage = "fotos/" . $_SESSION['UserId'] . ".jpg";
                    
                    if (file_exists($userIdImage)) {
                        echo "<img class='user-image' src='$userIdImage' alt='foto' width='45' height='45'>";
                    } else {
                        echo "<img class='user-image' src='fotos/semfoto.jpg' alt='Sem Foto' width='45' height='45'>";
                    }
                    ?>
                </div>
                <h6>SunnyImob <?php echo isset($_SESSION['conf_Versao']) ? $_SESSION['conf_Versao'] : 'v7.0'; ?> - <?php echo strtoupper($_SESSION['Usuario']); ?></h6>
            </div>
        </div> 

        <!-- Menus -->
        <div class="menus-container">
            <div class="header-menu">
                <div class="dropdown">
                    <button class="btn btn-menu" type="button" id="mapaDropdown" onclick="window.open('empreend_mapa_v5vbr.php', '_blank')">
                        <i class="fas fa-map"></i> Mapa
                    </button>
                </div>
            </div>

            <?php if (UsuarioPodeEditar()): ?>
            <div class="header-menu">
                <div class="dropdown">
                    <button class="btn btn-menu dropdown-toggle" type="button" id="documentosDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-folder-open"></i> M2
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="documentosDropdown">
                        <li><a class="dropdown-item" href="listar_M2_m2.php" target="_blank"><i class="fas fa-file-archive"></i> M2 Data</a></li>
                        <li><a class="dropdown-item" href="listar_M2_lastDate.php" target="_blank"><i class="fas fa-file-archive"></i> M2 Beira Mar</a></li>
                    </ul>
                </div>
            </div>

            <div class="header-menu">
                <div class="dropdown">
                    <button class="btn btn-menu dropdown-toggle" type="button" id="documentosDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-folder-open"></i> RH
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="documentosDropdown">
                        <li><a class="dropdown-item" href="usr_usuarios_ativos_tocca_onze.php" target="_blank"><i class="fas fa-file-archive"></i> Usuários/Licenças</a></li>    
                        <li><a class="dropdown-item" href="usr_novo_usuario1.php" target="_blank"><i class="fas fa-file-archive"></i> Novo Usuário</a></li>                      
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <div class="header-menu">
                <div class="dropdown">
                    <button class="btn btn-menu dropdown-toggle" type="button" id="consultasDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-list"></i> Consultas
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="consultasDropdown">
                        <li><a class="dropdown-item" href="empresa_list_v3b.php" target="_blank"><i class="fas fa-building"></i> Imóveis</a></li>
                        <li><a class="dropdown-item" href="gestao_empreend_filtro_local2.php" target="_blank"><i class="fas fa-images"></i> Cards</a></li>
                        <li><a class="dropdown-item" href="listagem_simples.php" target="_blank"><i class="fas fa-list"></i> Listagem</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="header-menu">
                <div class="dropdown">
                    <button class="btn btn-menu dropdown-toggle" type="button" id="ferramentasDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-tools"></i> Ferramentas
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="ferramentasDropdown">
                        <li><a class="dropdown-item" href="calcInterativaX7d.php" target="_blank"><i class="fas fa-calculator"></i> Calculadora %/R$</a></li>
                        <li><a class="dropdown-item" href="calcInterativaX6a-pre-pos.php" target="_blank"><i class="fas fa-calculator"></i> Calculadora Pré/Pós</a></li>
                        <li><a class="dropdown-item" href="calcRandon3.php" target="_blank"><i class="fas fa-calculator"></i> Calc. Inteligente</a></li>      
                        <li><a class="dropdown-item" href="usr_trocar_senha.php" target="_blank"><i class="fas fa-calculator"></i> Trocar senha</a></li>                     
                        <?php if (UsuarioPodeEditar()): ?>
                            <li><a class="dropdown-item" href="empreend_v3_imagem_paste.php" target="_blank"><i class="fas fa-pencil"></i> Imagens</a></li>
                            <li><a class="dropdown-item" href="menu4b.php" target="_blank"><i class="fas fa-pencil"></i> Menu Atualizador</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <?php if (strtoupper($_SESSION['Usuario']) == "BARRETO"): ?>
                <div class="header-menu">
                    <div class="dropdown">
                        <button class="btn btn-menu dropdown-toggle" type="button" id="devDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-code"></i> Dev
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="devDropdown">
                            <li><a class="dropdown-item" href="usr_painel26_2.php" target="_blank"><i class="fas fa-file-alt"></i> Painel RH 2026 Novo</a></li>
                            <li><a class="dropdown-item" href="usr_listar.php" target="_blank"><i class="fas fa-file-alt"></i> RH Cad. USR</a></li>
                            <li><a class="dropdown-item" href="usr_dupe1.php" target="_blank"><i class="fas fa-file-alt"></i> RH DUPE. USR</a></li>
                            <li><a class="dropdown-item" href="rh_list_admin.php" target="_blank"><i class="fas fa-file-alt"></i> RH Listagem 1</a></li>
                            <li><a class="dropdown-item" href="rh_status_list.php" target="_blank"><i class="fas fa-file-alt"></i> RH Licença</a></li>                            
                            <li><a class="dropdown-item" href="mapaip3.php" target="_blank"><i class="fas fa-file-alt"></i> RH Map</a></li>
                            <li><a class="dropdown-item" href="diretoria_list.php" target="_blank"><i class="fas fa-file-alt"></i> RH Diretorias</a></li>
                            <li><a class="dropdown-item" href="gerencia_list.php" target="_blank"><i class="fas fa-file-alt"></i> RH Gerências</a></li>
                            <li><a class="dropdown-item" href="historico_list.php" target="_blank"><i class="fas fa-cogs"></i> Log Acessos</a></li>
                            <li><a class="dropdown-item" href="log_list.php" target="_blank"><i class="fas fa-cogs"></i> Log Páginas</a></li>
                            <li><a class="dropdown-item" href="log_conta_paginas.php" target="_blank"><i class="fas fa-cogs"></i> Log Páginas 2</a></li>
                            <li><a class="dropdown-item" href="manut_vendas_diretorias.php" target="_blank"><i class="fas fa-cogs"></i> Vendas Diretorias</a></li>
                            <li><a class="dropdown-item" href="manutmenu.php" target="_blank"><i class="fas fa-cogs"></i> Manutencao do Sistema</a></li>
                            <li><a class="dropdown-item" href="sunny_empresas1.php" target="_blank"><i class="fas fa-cogs"></i> Sunny Empresas</a></li>
                            <li><a class="dropdown-item" href="listar_simulacao.php" target="_blank"><i class="fas fa-cogs"></i> M2 Completa</a></li>
                            <li><a class="dropdown-item" href="listar_simulacao_simples.php" target="_blank"><i class="fas fa-cogs"></i> M2 Simples</a></li>
                            <li><a class="dropdown-item" href="empreend2map3a.php" target="_blank"><i class="fas fa-cogs"></i> Selec. Mapa</a></li>
                            <li><a class="dropdown-item" href="backupEmail.php" target="_blank"><i class="fas fa-cogs"></i> Backup</a></li>
                            <li><a class="dropdown-item" href="log_atualizar_nome_arquivo.php" target="_blank"><i class="fas fa-cogs"></i> Atualiza Log</a></li>
                            <li><a class="dropdown-item" href="pdf_listar.php" target="_blank"><i class="fas fa-cogs"></i> PDF</a></li>
                            <li><a class="dropdown-item" href="empreend_update_pavimentos.php" target="_blank"><i class="fas fa-cogs"></i> Pavimentos</a></li>
                        </ul>
                    </div>
                </div>

                <div class="header-menu">
                    <div class="dropdown">
                        <button class="btn btn-menu dropdown-toggle" type="button" id="devDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-code"></i> Painel VB1
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="devDropdown">
                            <li><a class="dropdown-item" href="menuvb1.php" target="_blank"><i class="fas fa-cogs"></i> Painel</a></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Carrossel -->
        <div class="carousel-container">
            <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel" data-bs-interval="7000">
                <div class="carousel-inner">
                    <?php
                    if (count($carouselImages) > 0) {
                        foreach ($carouselImages as $i => $image) {
                            $activeClass = ($i == 0) ? "active" : "";
                            echo "<div class=\"carousel-item $activeClass\">";
                            echo "<img src=\"$image\" class=\"d-block w-100\" alt=\"Imagem do Carrossel\">";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class=\"carousel-item active\">";
                        echo "<img src=\"https://via.placeholder.com/1400x300?text=Adicione+imagens+na+pasta+/carrossel\" class=\"d-block w-100\" alt=\"Nenhuma imagem encontrada\">";
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

        <!-- Filtros -->
        <div class="card-filters"> 
            <div class="card-header">
                <i class="fas fa-filter"></i> Filtros de Empreendimentos
            </div>
            <div class="card-body">
                <form method="GET" action="empreend_v3_resultados.php" target="_blank">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 col-12 filter-section">
                            <label class="form-label"><i class="fas fa-map-marker-alt feature-icon"></i> Localidade</label>
                            <select name="localidade" class="form-select">
                                <option value="">Qualquer</option>
                                <?php
                                foreach ($localidades as $localidade) {
                                    $localidadeValue = htmlspecialchars($localidade['Localidade']);
                                    echo "<option value=\"$localidadeValue\">$localidadeValue</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 col-sm-6 col-12 filter-section">
                            <label class="form-label"><i class="fas fa-bed feature-icon"></i> Tipologia</label>
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
                            <label class="form-label"><i class="fas fa-tag feature-icon"></i> Orçamento (R$)</label>
                            <input type="text" name="orcamento_cliente" class="form-control" 
                                   placeholder="R$ 0,00" id="orcamentoInput">
                        </div>

                        <div class="col-md-3 col-sm-6 col-12 filter-section">
                            <label class="form-label"><i class="fas fa-calendar-alt feature-icon"></i> Entrega</label>
                            <select name="data_entrega" class="form-select">
                                <option value="">Qualquer</option>
                                <?php
                                foreach ($anosEntrega as $ano) {
                                    $anoFormatado = $ano['AnoEntrega'];
                                    echo "<option value=\"$anoFormatado\">$anoFormatado</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6 col-12 filter-section">
                            <label class="form-label"><i class="fas fa-company feature-icon"></i> Empresa</label>
                            <select name="empresa" class="form-select">
                                <option value="">Todas</option>
                                <?php
                                foreach ($empresas as $empresa) {
                                    $nomeEmpresa = strtoupper($empresa['NomeEmpresa']);
                                    $selected = (isset($_GET['empresa']) && strtoupper($_GET['empresa']) == $nomeEmpresa) ? "selected" : "";
                                    $nomeEmpresaEscaped = htmlspecialchars($nomeEmpresa);
                                    echo "<option value=\"$nomeEmpresaEscaped\" $selected>$nomeEmpresaEscaped</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-auto">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="BeiraMar" id="BeiraMar">
                                <label class="form-check-label" for="BeiraMar"><i class="fas fa-umbrella-beach feature-icon"></i> Beira Mar</label>
                            </div>
                        </div>

                        <div class="col-auto">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="Varanda" id="Varanda">
                                <label class="form-check-label" for="Varanda"><i class="fas fa-couch feature-icon"></i> Varanda</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12 d-flex justify-content-end flex-wrap">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Limpar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Premiações -->
        <div class="card-filters">
            <div class="card-header">
                <i class="fas fa-award"></i> Premiações Ativas
            </div>
            <div class="card-body">
                <div class="empreendimento-container">
                    <?php
                    $currentEmpreendID = "";
                    $hasPremiacoesForCurrentEmpreend = false;

                    if (!empty($premiacoes)) {
                        foreach ($premiacoes as $row) {
                            if ($row['Empreend_ID'] != $currentEmpreendID) {
                                if ($currentEmpreendID != "") {
                                    if (!$hasPremiacoesForCurrentEmpreend) {
                                        echo "<li class=\"no-premiacoes\">Nenhuma premiação ativa no momento para este empreendimento.</li>";
                                    }
                                    echo "</ul>";
                                    echo "</div>";
                                }

                                $currentEmpreendID = $row['Empreend_ID'];
                                echo "<div class=\"empreendimento-card\">";
                                echo "<div class=\"empreendimento-card-header\">";
                                echo "<i class=\"fas fa-building\"></i> " . htmlspecialchars($row['NomeEmpreendimento']);
                                echo "</div>";
                                echo "<ul class=\"premiacao-list\">";
                                $hasPremiacoesForCurrentEmpreend = false;
                            }

                            $periodo = str_pad($row['InicioDia'], 2, '0', STR_PAD_LEFT) . "/" . 
                                     str_pad($row['InicioMes'], 2, '0', STR_PAD_LEFT) . "/" . 
                                     $row['InicioAno'] . " a " . 
                                     str_pad($row['TerminoDia'], 2, '0', STR_PAD_LEFT) . "/" . 
                                     str_pad($row['TerminoMes'], 2, '0', STR_PAD_LEFT) . "/" . 
                                     $row['TerminoAno'];
                            
                            echo "<li class=\"premiacao-item\">";
                            echo "<div class=\"premiacao-titulo\">" . htmlspecialchars($row['Titulo']) . "</div>";
                            echo "<div class=\"premiacao-descricao\">";
                            echo substr(htmlspecialchars($row['Descricao']), 0, 150);
                            if (strlen($row['Descricao']) > 150) echo "...";
                            echo "</div>";
                            echo "<div class=\"premiacao-periodo\">";
                            echo "<strong>Período:</strong> " . $periodo;
                            echo "</div>";
                            echo "</li>";
                            $hasPremiacoesForCurrentEmpreend = true;
                        }

                        if ($currentEmpreendID != "") {
                            if (!$hasPremiacoesForCurrentEmpreend) {
                                echo "<li class=\"no-premiacoes\">Nenhuma premiação ativa no momento para este empreendimento.</li>";
                            }
                            echo "</ul>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class=\"no-premiacoes\" style=\"width: 100%;\">Nenhum empreendimento com premiações ativas encontradas.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Seção Diretor -->
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
        $statusClass = $isBloqueado ? 'danger' : 'success';
        $statusIcon  = $isBloqueado ? 'bi-lock-fill' : 'bi-unlock-fill';
        ?>

        <div class="container mt-4">
            <div class="card-director mb-4">
                <div class="card-header text-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-badge-fill me-2"></i>
                        <?= htmlspecialchars($nomeExibicao) ?>
                    </h5>
                </div>
                <div class="card-body" style="background: var(--card-bg);">
                    <div class="row text-center">
                        <div class="col-6 border-end" style="border-color: var(--shadow-color) !important;">
                            <small class="text-muted">Usuário</small><br>
                            <strong><?= htmlspecialchars($nomeUsuario) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">ID da Diretoria</small><br>
                            <strong><?= htmlspecialchars($_SESSION['Dir_DiretoriaID'] ?? '0') ?></strong>
                        </div>
                    </div>
                    <hr style="border-color: var(--shadow-color);">
                    <div class="text-center">
                        <span class="badge <?= $isBloqueado ? 'bg-danger' : 'bg-success' ?>" style="font-size: 1rem; padding: 0.5rem 1.5rem; border: 2px solid var(--shadow-color); box-shadow: 2px 2px 0px 0px var(--shadow-color);">
                            <i class="bi <?= $statusIcon ?> me-1"></i> <?= $status ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (($isDiretor || $isBarreto) && !$isBloqueado): ?>
        <div class="container">
            <div class="d-flex gap-2 justify-content-center flex-wrap" role="group">
                <a href="gestao_vendas_diretoria3.php" target="_blank" class="btn btn-primary shadow-sm">
                    <i class="bi bi-link-45deg"></i> Vendas da Diretoria
                </a>
            </div>
        </div>
        <?php elseif ($isBloqueado): ?>
            <div class="container mt-3">
                <div class="alert alert-danger text-center shadow-sm" style="border: 2px solid var(--shadow-color); box-shadow: 4px 4px 0px 0px var(--shadow-color);">
                    <strong>ACESSO BLOQUEADO (SISTEMA)</strong>
                </div>
            </div>
        <?php else: ?>
            <div class="container mt-3">
                <div class="alert alert-warning text-center shadow-sm" style="border: 2px solid var(--shadow-color); box-shadow: 4px 4px 0px 0px var(--shadow-color);">
                    <strong>USUÁRIO NÃO AUTORIZADO COMO DIRETOR</strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- Logout -->
        <div class="d-flex justify-content-center my-3">
            <a href="#" onclick="logout()" title="Sair" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>

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
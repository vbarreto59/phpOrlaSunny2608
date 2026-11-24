<?php
session_start();
require_once 'conn.php';
require_once 'usr_acoes.php';
require_once 'manutencao_config.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

// ==================== 26 04 2026 - CONFIGURAÇÃO DINÂMICA ====================
// Garante que as variáveis Conf_ da empresa estejam carregadas na sessão
if (!empty($_SESSION['IdEmp'])) {
    CarregarConfiguracoesEmpresa($_SESSION['IdEmp']);
}

// Definição de fallback para nomes (as cores vêm do tema)
$nomeEmpresa = $_SESSION['EmpresaNome'] ?? 'SunnyImob';
$logoEmpresa = !empty($_SESSION['Conf_ImgLogo']) ? $_SESSION['Conf_ImgLogo'] : 'logoSunny66a.JPG';

// ==================== RECURSO DE ABERTURA DE ABA (_blank / _self) ====================
$linkTarget   = $_SESSION['link_target'] ?? '_self';
$abrirNovaAba = !empty($_SESSION['abrir_nova_aba']) || ($linkTarget === '_blank');
$targetAttr   = $abrirNovaAba ? '_blank' : '_self';
$modoParam    = $abrirNovaAba ? 'blank' : 'self';

function urlComModo(string $url, string $modo): string {
    $separador = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $separador . 'modo=' . urlencode($modo);
}

header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['Usuario'])) {
    header('Location: login_p1.php');
    exit();
}

if (!UsuarioPodeEditar()) {
    header('Location: login_v3a.php');
    exit();
}

$usuarioLogado = $_SESSION['Usuario'];

$itensMenu = [
    [
        'href'  => 'gestao_empresa_list2.php',
        'icone' => 'fas fa-building',
        'titulo'=> 'Empresas',
        'desc'  => 'Rede e Unidades',
    ],
    [
        'href'  => 'gestao_empreend_list_card.php',
        'icone' => 'fas fa-city',
        'titulo'=> 'Empreendimentos',
        'desc'  => 'Lista de Produtos',
    ],
    [
        'href'  => 'empreend_v3_imagem_paste.php',
        'icone' => 'fas fa-images',
        'titulo'=> 'Imagens',
        'desc'  => 'Cards dos Empreendimentos',
    ],
    [
        'href'  => 'empreend_update_nomeempreendimento.php',
        'icone' => 'fas fa-pen-square',
        'titulo'=> 'Nomes',
        'desc'  => 'Títulos Comerciais',
    ],
    [
        'href'  => 'empreend_update_tipologia.php',
        'icone' => 'fas fa-layer-group',
        'titulo'=> 'Tipologias',
        'desc'  => 'Plantas e Unidades',
    ],
    [
        'href'  => 'empreend_update_pavimentos.php',
        'icone' => 'fas fa-layer-group',
        'titulo'=> 'Pavimentos',
        'desc'  => 'QTD de Pavimentos',
    ],
    [
        'href'  => 'empreend_update_localizacao.php',
        'icone' => 'fas fa-map-marked-alt',
        'titulo'=> 'Localização',
        'desc'  => 'Endereços e Mapas',
    ],
    [
        'href'  => 'empreend_update_distancia_mar.php',
        'icone' => 'fas fa-map-marked-alt',
        'titulo'=> 'Distância Mar',
        'desc'  => 'Distância até o mar',
    ],
    [
        'href'  => 'empreend_update_comissao.php',
        'icone' => 'fas fa-hand-holding-usd',
        'titulo'=> 'Comissões',
        'desc'  => 'Regras e Valores',
    ],
    [
        'href'  => 'empreend_verificacao2.php',
        'icone' => 'fas fa-clipboard-check',
        'titulo'=> 'Auditoria',
        'desc'  => 'Dados Incompletos',
    ],
    [
        'href'  => 'usr_usuarios_ativos_sunny.php',
        'icone' => 'fas fa-user-shield',
        'titulo'=> 'Usuários',
        'desc'  => 'Equipe e Acessos',
    ],
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="900"> 
    <title>Menu Principal - <?= htmlspecialchars($nomeEmpresa, ENT_QUOTES) ?></title>
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    
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
        }

        body {
            background: var(--bg-body);
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        /* Container principal neobrutalista */
        .main-container {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--card-bg);
            border: 4px solid var(--shadow-color);
            border-radius: 20px;
            box-shadow: 8px 8px 0px 0px var(--shadow-color);
            overflow: hidden;
        }

        /* Header neobrutalista */
        header {
            background: var(--primary);
            color: #fff;
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            position: relative;
        }

        header .logo-box {
            background: #fff;
            display: inline-block;
            padding: 10px 15px;
            border-radius: 12px;
            border: 3px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            margin-bottom: 0.5rem;
        }

        header .logo-box img {
            max-height: 70px;
            width: auto;
            display: block;
        }

        header h2 {
            font-weight: 800;
            font-size: 2rem;
            margin: 0.5rem 0 0.2rem;
            letter-spacing: -0.5px;
        }

        header h4 {
            font-weight: 600;
            opacity: 0.9;
            font-size: 1.1rem;
            margin: 0;
        }

        .user-tag {
            position: absolute;
            top: 15px;
            right: 20px;
            background: rgba(255,255,255,0.15);
            border: 2px solid var(--shadow-color);
            border-radius: 30px;
            padding: 0.3rem 1.2rem;
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .user-tag a {
            color: #fff;
            transition: all 0.15s;
        }
        .user-tag a:hover {
            opacity: 0.8;
            transform: translateY(-2px);
        }

        /* Título do grupo */
        .group-title {
            display: block;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            margin: 2rem 0 1.5rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 4px solid var(--shadow-color);
            position: relative;
        }
        .group-title::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 80px;
            height: 4px;
            background: var(--primary);
        }

        /* Cards neobrutalistas */
        .menu-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            padding: 1.5rem 1rem;
            text-align: center;
            text-decoration: none !important;
            transition: all 0.15s;
            display: flex;
            flex-direction: column;
            height: 100%;
            color: var(--text-color);
        }
        .menu-card:hover {
            transform: translateY(-6px);
            box-shadow: 8px 8px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .menu-card:active {
            transform: translate(2px, 2px);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }

        .menu-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.8rem;
        }

        .menu-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.3rem;
        }

        .menu-card p {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.7;
            margin: 0;
            line-height: 1.3;
        }

        /* Footer */
        footer {
            padding: 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            border-top: 4px solid var(--shadow-color);
            color: var(--text-color);
            opacity: 0.7;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .main-container { border-radius: 16px; }
            header { padding: 1.5rem 1rem; }
            header h2 { font-size: 1.6rem; }
            .user-tag {
                position: static;
                display: inline-flex;
                justify-content: center;
                margin-top: 0.5rem;
            }
            .menu-card { padding: 1rem; }
            .menu-card i { font-size: 2rem; }
        }
    </style>
</head>
<body>

<!-- ============================================================ -->
<!-- HEADER                                                       -->
<!-- ============================================================ -->
<?php
$pageTitle    = 'Menu Atualizador';           // OBRIGATÓRIO
$pageSubtitle = 'SunnyImob';  // opcional
$pageIcon     = 'fa-city';                   // opcional (default: fa-building)
include 'header.php';
?>


<div class="main-container">


    <div class="p-4">
        <span class="group-title">Menu Atualizador</span>
        
        <div class="row">
            <?php foreach ($itensMenu as $item): ?>
                <div class="col-6 col-md-4 mb-4">
                    <a href="<?= htmlspecialchars(urlComModo($item['href'], $modoParam), ENT_QUOTES) ?>"
                       class="menu-card"
                       target="<?= htmlspecialchars($targetAttr, ENT_QUOTES) ?>"
                       <?= $abrirNovaAba ? 'rel="noopener"' : '' ?>>
                        <i class="<?= htmlspecialchars($item['icone'], ENT_QUOTES) ?>"></i>
                        <h3><?= htmlspecialchars($item['titulo'], ENT_QUOTES) ?></h3>
                        <p><?= htmlspecialchars($item['desc'], ENT_QUOTES) ?></p>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer>
        <?= htmlspecialchars($nomeEmpresa, ENT_QUOTES) ?> &copy; <?= date('Y') ?> | Sistema SunnyImob
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>
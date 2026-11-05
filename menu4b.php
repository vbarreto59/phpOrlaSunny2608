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
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="900"> 
    <title>Menu Principal - <?= $nomeEmpresa ?></title>
    
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

<div class="main-container">
    <header>
        <div class="user-tag">
            <i class="fas fa-user-circle mr-1"></i>
            <?= htmlspecialchars(strtoupper($usuarioLogado)) ?> 
            <a href="logout.php" onclick="return confirm('Sair?')"><i class="fas fa-sign-out-alt"></i></a>
        </div>

        <div class="logo-box">
            <img src="<?= $logoEmpresa ?>" alt="Logo">
        </div>
        
        <h2><?= $nomeEmpresa ?></h2>
        <h4>Gestão de Empreendimentos Imobiliários</h4>
    </header>

    <div class="p-4">
        <span class="group-title">Painel Administrativo</span>
        
        <div class="row">
            <div class="col-6 col-md-4 mb-4">
                <a href="gestao_empresa_list2.php" class="menu-card" target="_blank">
                    <i class="fas fa-building"></i>
                    <h3>Empresas</h3>
                    <p>Rede e Unidades</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="gestao_empreend_list_card.php" class="menu-card" target="_blank">
                    <i class="fas fa-city"></i>
                    <h3>Empreendimentos</h3>
                    <p>Lista de Produtos</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="empreend_v3_imagem_paste.php" class="menu-card" target="_blank">
                    <i class="fas fa-images"></i>
                    <h3>Imagens</h3>
                    <p>Cards dos Empreendimentos</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="empreend_update_nomeempreendimento.php" class="menu-card" target="_blank">
                    <i class="fas fa-pen-square"></i>
                    <h3>Nomes</h3>
                    <p>Títulos Comerciais</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="empreend_update_tipologia.php" class="menu-card" target="_blank">
                    <i class="fas fa-layer-group"></i>
                    <h3>Tipologias</h3>
                    <p>Plantas e Unidades</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="empreend_update_localizacao.php" class="menu-card" target="_blank">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>Localização</h3>
                    <p>Endereços e Mapas</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="empreend_update_comissao.php" class="menu-card" target="_blank">
                    <i class="fas fa-hand-holding-usd"></i>
                    <h3>Comissões</h3>
                    <p>Regras e Valores</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="empreend_verificacao2.php" class="menu-card" target="_blank">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>Auditoria</h3>
                    <p>Dados Incompletos</p>
                </a>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <a href="usr_usuarios_ativos_sunny.php" class="menu-card" target="_blank">
                    <i class="fas fa-user-shield"></i>
                    <h3>Usuários</h3>
                    <p>Equipe e Acessos</p>
                </a>
            </div>
        </div>
    </div>

    <footer>
        <?= $nomeEmpresa ?> &copy; <?= date('Y') ?> | Sistema SunnyImob
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>
<?php
session_start();

// Incluir arquivos necessários
require_once 'conn.php';
require_once 'usr_acoes.php';
require_once 'theme_config.php';

// Verificar se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// Envio de email de login
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if ($remoteAddr !== "127.0.0.1" && $remoteAddr !== "::1") {
    $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
    $subject = "SI-EMPREEND-" . strtoupper($_SESSION['Usuario']) . " - " . $remoteAddr . " - " . date('Y-m-d') . " - " . date('H:i:s');
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    // mail($to, $subject, "Acesso registrado", $headers);
}

// Função para remover números
function RemoverNumeros($texto) {
    if (empty($texto)) return '';
    $texto = str_replace("-", "", $texto);
    return preg_replace('/[0-9]/', '', $texto);
}

// Obter empreendimentos
$empreendimentos = [];

try {
    $sql = "SELECT empreendimento.*, empresa.NomeEmpresa, empresa.LinkDrive1, 
                   empresa.LinkDrive2, empresa.LinkSite1, empresa.LinkSite2, empresa.Mapa1 
            FROM empresa 
            INNER JOIN empreendimento ON empresa.Empresa_Id = empreendimento.Empresa_Id 
            WHERE Ocultar = 0 
            ORDER BY empreendimento.NomeEmpreendimento";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Empreendimentos</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            --success: #28a745;
            --danger: #dc3545;
            --info: #17a2b8;
            --warning: #fd7e14;
            --edit-color: #b8860b;
        }

        /* Reset e base */
        * { font-family: 'Inter', 'Segoe UI', sans-serif; }

        body {
            background: var(--bg-body);
            color: var(--text-color);
            padding: 1rem;
            min-height: 100vh;
        }

        /* Header */
        .header-section {
            background: var(--primary);
            color: #fff;
            padding: 0.8rem 1.5rem;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            margin-bottom: 2rem;
            border-radius: 0;
        }

        .header-section .container-fluid {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }

        .header-logo img {
            height: 50px;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            background: #fff;
            padding: 4px;
            margin-right: 15px;
        }

        .header-title h6 {
            font-weight: 800;
            font-size: 1.2rem;
            margin: 0;
        }
        .header-title p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .btn-neo-close {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.2rem;
            font-weight: 700;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #b02a37;
        }

        /* Card container */
        .card-shadow {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            overflow: hidden;
            padding: 0;
        }
        .card-shadow .card-body {
            padding: 1.5rem;
        }

        /* Estilo dos itens (cada empreendimento) */
        .empreendimento-item {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s;
        }
        .empreendimento-item:hover {
            transform: translateY(-4px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
        }

        .empreendimento-title {
            color: var(--primary);
            font-weight: 800;
            font-size: 1.4rem;
            margin-bottom: 0.2rem;
        }

        .company-name {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1rem;
        }
        .company-name i {
            margin-right: 8px;
        }

        .vendido-tag {
            background: var(--danger);
            color: #fff;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            margin-left: 10px;
            vertical-align: middle;
        }

        /* Imagens padronizadas */
        .empreendimento-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            cursor: pointer;
            transition: all 0.2s;
        }
        .empreendimento-img:hover {
            transform: scale(1.02);
        }

        .placeholder-img {
            margin-bottom: 15px;
            width: 100%;
            height: 150px;
            background: var(--secondary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            font-size: 2.5rem;
        }

        /* Badges de informação */
        .info-badge {
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.3rem 0.9rem;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        .info-badge i {
            margin-right: 6px;
            color: #fff;
        }

        /* Badges de comodidades */
        .feature-badge {
            background: var(--secondary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.2rem 0.8rem;
            margin-right: 6px;
            margin-bottom: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        .feature-badge i {
            margin-right: 4px;
            color: #fff;
        }

        /* Botões de ação */
        .btn-ficha {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.15s;
            display: inline-block;
            margin: 0.2rem 0.3rem 0.2rem 0;
            text-decoration: none;
        }
        .btn-ficha:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }

        .btn-editar {
            background: var(--edit-color);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.15s;
            display: inline-block;
            margin: 0.2rem 0.3rem 0.2rem 0;
            text-decoration: none;
        }
        .btn-editar:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #a67c00;
            color: #fff;
        }

        #buttons-links {
            background: var(--card-bg);
            padding: 0.5rem 0;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 0.2rem;
        }

        /* DataTables override */
        #tabelaEmpreendimentos_wrapper .dataTables_filter {
            text-align: right;
            padding-bottom: 15px;
        }
        #tabelaEmpreendimentos_wrapper .dataTables_filter input {
            border: 2px solid var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.2rem;
            background: #fff;
            color: var(--text-color);
            transition: all 0.2s;
        }
        #tabelaEmpreendimentos_wrapper .dataTables_filter input:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        #tabelaEmpreendimentos thead {
            display: none;
        }
        #tabelaEmpreendimentos tbody td {
            padding: 0 !important;
            border: none;
            vertical-align: top;
        }

        /* Modal imagem */
        .modal-image {
            display: none;
            position: fixed;
            z-index: 9999;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
        }
        .modal-image-content {
            display: block;
            margin: 0 auto;
            max-width: 90%;
            max-height: 80vh;
            border: 4px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 8px 8px 0px 0px var(--shadow-color);
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        .close-modal:hover {
            color: #bbb;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-section .container-fluid {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .header-logo img { margin-right: 0; margin-bottom: 10px; }
            .empreendimento-title { font-size: 1.2rem; }
            #tabelaEmpreendimentos_wrapper .dataTables_filter { text-align: left; }
            .empreendimento-img, .placeholder-img {
                height: 120px;
            }
        }
    </style>
</head>

<body>
    <!-- HEADER -->
    <header class="header-section">
        <div class="container-fluid">
            <div class="d-flex align-items-center flex-wrap">
                <img src="<?= htmlspecialchars($_SESSION['Conf_ImgLogo'] ?? '') ?>" alt="Logo" class="header-logo">
                <div class="header-title">
                    <h6><i class="fas fa-building me-2"></i>Empreendimentos</h6>
                    <p>SunnyImob</p>
                </div>
            </div>
            <div>
                <button class="btn-neo-close" onclick="window.close()">
                    <i class="fas fa-times me-1"></i> Fechar
                </button>
            </div>
        </div>
    </header>

    <!-- CONTEÚDO -->
    <div class="container-fluid">
        <div class="card-shadow">
            <div class="card-body">
                <table id="tabelaEmpreendimentos" class="table table-borderless" style="width:100%">
                    <thead>
                        <tr><th>Empreendimentos</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($empreendimentos)): ?>
                        <?php foreach ($empreendimentos as $empreendimento): ?>
                        <tr>
                            <td>
                                <div class="empreendimento-item">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <?php 
                                            $imagePath = "card/" . $empreendimento['Empreend_ID'] . ".jpg";
                                            if (file_exists($imagePath)): ?>
                                                <img src="<?= $imagePath ?>" 
                                                     alt="<?= htmlspecialchars($empreendimento['NomeEmpreendimento']) ?>" 
                                                     class="empreendimento-img img-fluid"
                                                     onclick="openImageModal(this.src, this.alt)">
                                            <?php else: ?>
                                                <div class="placeholder-img">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-6">
                                            <h3 class="empreendimento-title">
                                                <?= strtoupper(htmlspecialchars($empreendimento['Localidade'])) . "-" . 
                                                    RemoverNumeros(strtoupper(htmlspecialchars($empreendimento['NomeEmpreendimento']))) ?>
                                                <?php if (!empty($empreendimento['Vendido']) && $empreendimento['Vendido']): ?>
                                                    <span class="vendido-tag">VENDIDO</span>
                                                <?php endif; ?>
                                            </h3>
                                            <h4 class="company-name">
                                                <i class="fas fa-building"></i>
                                                <?= RemoverNumeros(strtoupper(htmlspecialchars($empreendimento['NomeEmpresa']))) ?>
                                            </h4>
                                        </div>
                                        <div class="col-md-4 last-update"></div>
                                    </div>

                                    <!-- Badges de informações -->
                                    <div class="d-flex flex-wrap mb-3">
                                        <?php
                                        $mesEntrega = trim($empreendimento['MesEntrega'] ?? '0');
                                        $anoEntrega = trim($empreendimento['AnoEntrega'] ?? '0');
                                        $entregaTexto = "00/0000";
                                        if (is_numeric($mesEntrega) && is_numeric($anoEntrega)) {
                                            $mesEntrega = (int)$mesEntrega;
                                            $anoEntrega = (int)$anoEntrega;
                                            if ($mesEntrega >= 1 && $mesEntrega <= 12 && $anoEntrega >= 2025 && $anoEntrega <= 2035) {
                                                $entregaTexto = str_pad($mesEntrega, 2, '0', STR_PAD_LEFT) . "/" . $anoEntrega;
                                            } elseif ($mesEntrega == 0 && $anoEntrega == 0) {
                                                $entregaTexto = "00/0000";
                                            } elseif ($mesEntrega == 0) {
                                                $entregaTexto = "00/" . $anoEntrega;
                                            } elseif ($anoEntrega == 0) {
                                                $entregaTexto = str_pad($mesEntrega, 2, '0', STR_PAD_LEFT) . "/0000";
                                            }
                                        }
                                        ?>
                                        <!-- Metragem -->
                                        <?php if (!empty($empreendimento['MetragemMin']) && !empty($empreendimento['MetragemMax'])): ?>
                                            <?php if ($empreendimento['MetragemMin'] > 0 && $empreendimento['MetragemMax'] > 0): ?>
                                                <span class="info-badge" title="Metragem">
                                                    <i class="fas fa-ruler-combined"></i> 
                                                    <?= $empreendimento['MetragemMin'] ?>m² a <?= $empreendimento['MetragemMax'] ?>m²
                                                </span>
                                            <?php elseif ($empreendimento['MetragemMin'] > 0): ?>
                                                <span class="info-badge" title="Metragem">
                                                    <i class="fas fa-ruler-combined"></i> 
                                                    <?= $empreendimento['MetragemMin'] ?>m²
                                                </span>
                                            <?php elseif ($empreendimento['MetragemMax'] > 0): ?>
                                                <span class="info-badge" title="Metragem">
                                                    <i class="fas fa-ruler-combined"></i> 
                                                    <?= $empreendimento['MetragemMax'] ?>m²
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <!-- Preço -->
                                        <?php if (!empty($empreendimento['MenorPreco']) && !empty($empreendimento['MaiorPreco'])): ?>
                                            <?php 
                                            $menorPreco = number_format($empreendimento['MenorPreco'], 2, ',', '.');
                                            $maiorPreco = number_format($empreendimento['MaiorPreco'], 2, ',', '.');
                                            ?>
                                            <?php if ($empreendimento['MenorPreco'] > 0 && $empreendimento['MaiorPreco'] > 0): ?>
                                                <span class="info-badge" title="Preço">
                                                    <i class="fas fa-tag"></i> 
                                                    R$ <?= $menorPreco ?> a R$ <?= $maiorPreco ?>
                                                </span>
                                            <?php elseif ($empreendimento['MenorPreco'] > 0): ?>
                                                <span class="info-badge" title="Preço">
                                                    <i class="fas fa-tag"></i> 
                                                    R$ <?= $menorPreco ?>
                                                </span>
                                            <?php elseif ($empreendimento['MaiorPreco'] > 0): ?>
                                                <span class="info-badge" title="Preço">
                                                    <i class="fas fa-tag"></i> 
                                                    R$ <?= $maiorPreco ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <!-- Vagas -->
                                        <?php if (!empty($empreendimento['VagasGaragem']) && $empreendimento['VagasGaragem'] > 0): ?>
                                            <span class="info-badge" title="Garagem">
                                                <i class="fas fa-car"></i> 
                                                <?= $empreendimento['VagasGaragem'] ?> vaga(s) <?= htmlspecialchars($empreendimento['TipoGaragem'] ?? '') ?>
                                            </span>
                                        <?php endif; ?>
                                        <!-- Tipologia -->
                                        <?php if (!empty($empreendimento['Tipologia'])): ?>
                                            <span class="info-badge" title="Tipologia">
                                                <i class="fas fa-bed"></i> 
                                                <?= htmlspecialchars($empreendimento['Tipologia']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <!-- Entrega -->
                                        <?php if (!empty($entregaTexto) && $entregaTexto !== "00/0000"): ?>
                                            <span class="info-badge" title="Entrega">
                                                <i class="fas fa-calendar"></i> 
                                                <?= $entregaTexto ?>
                                            </span>
                                        <?php endif; ?>
                                        <!-- Unidades -->
                                        <?php if (!empty($empreendimento['QTD_Unidades'])): ?>
                                            <span class="info-badge" title="Unidades">
                                                <i class="fas fa-home"></i> 
                                                <?= $empreendimento['QTD_Unidades'] ?> unidade(s)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Comodidades -->
                                    <div class="mb-3">
                                        <?php 
                                        $comodidades = [
                                            'Academia' => 'fas fa-dumbbell',
                                            'BeiraMar' => 'fas fa-umbrella-beach',
                                            'Bicicletario' => 'fas fa-bicycle',
                                            'Brinquedoteca' => 'fas fa-child',
                                            'Coworking' => 'fas fa-laptop',
                                            'Emporio' => 'fas fa-store',
                                            'EspacoGourmet' => 'fas fa-utensils',
                                            'FechaduraEletronica' => 'fas fa-key',
                                            'GasEncanado' => 'fas fa-fire',
                                            'Hidromassagem' => 'fas fa-hot-tub',
                                            'Lavanderia' => 'fas fa-tshirt',
                                            'LoungeBar' => 'fas fa-glass-martini-alt',
                                            'Petplace' => 'fas fa-paw',
                                            'Piscina2' => 'fas fa-swimming-pool',
                                            'PiscinaPrivativa' => 'fas fa-swimmer',
                                            'QuadraTenis' => 'fas fa-table-tennis',
                                            'Recepcao24h' => 'fas fa-concierge-bell',
                                            'Restaurante' => 'fas fa-utensils',
                                            'Rooftop' => 'fas fa-building',
                                            'SalaoJogos' => 'fas fa-gamepad',
                                            'Sauna' => 'fas fa-hot-tub',
                                            'Spa' => 'fas fa-spa',
                                            'Varanda' => 'fas fa-door-open'
                                        ];
                                        foreach ($comodidades as $campo => $icone) {
                                            if (!empty($empreendimento[$campo]) && $empreendimento[$campo]) {
                                                $nome = str_replace('_', ' ', $campo);
                                                echo '<span class="feature-badge"><i class="' . $icone . '"></i> ' . $nome . '</span>';
                                            }
                                        }
                                        ?>
                                    </div>

                                    <h5>Comissão: <?= $empreendimento['ComissaoVenda']/1 ?>%</h5>

                                    <!-- Fluxo de Pagamento (resumido) -->
                                    <?php
                                    $fluxoResumo = "";
                                    $totalPercentual = 0;
                                    try {
                                        $sqlFluxo = "SELECT Posicao, Etapa, Percentual 
                                                    FROM fluxo_Etapas 
                                                    WHERE Fluxo_ID = :empreend_id 
                                                    ORDER BY Posicao ASC";
                                        $stmtFluxo = $conn->prepare($sqlFluxo);
                                        $stmtFluxo->bindValue(':empreend_id', $empreendimento['Empreend_ID'], PDO::PARAM_INT);
                                        $stmtFluxo->execute();
                                        $fluxos = $stmtFluxo->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($fluxos as $fluxo) {
                                            $fluxoResumo .= $fluxo['Posicao'] . ") " . $fluxo['Etapa'] . " (" . $fluxo['Percentual'] . "%), ";
                                            $totalPercentual += $fluxo['Percentual'];
                                        }
                                        if (trim($fluxoResumo) == "") $fluxoResumo = "***";
                                        else $fluxoResumo = rtrim($fluxoResumo, ", ");
                                    } catch (PDOException $e) {
                                        $fluxoResumo = "Erro ao carregar fluxo";
                                    }
                                    ?>

                                    <!-- Links -->
                                    <br>
                                    <div id="buttons-links">
                                        <?php if (!empty($empreendimento['LinkMapa'])): ?>
                                            <a href="<?= htmlspecialchars($empreendimento['LinkMapa']) ?>" target="_blank" class="btn-ficha" title="Mapa">
                                                <i class="fas fa-map-marked-alt"></i> Mapa
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($empreendimento['LinkSite'])): ?>
                                            <a href="<?= htmlspecialchars($empreendimento['LinkSite']) ?>" target="_blank" class="btn-ficha" title="Site">
                                                <i class="fas fa-globe"></i> Site
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($empreendimento['LinkVideo'])): ?>
                                            <a href="<?= htmlspecialchars($empreendimento['LinkVideo']) ?>" target="_blank" class="btn-ficha" title="Vídeo">
                                                <i class="fas fa-video"></i> Vídeo
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($empreendimento['LinkTocca'])): ?>
                                            <a href="<?= htmlspecialchars($empreendimento['LinkTocca']) ?>" target="_blank" class="btn-ficha" title="Tocca">
                                                <i class="fas fa-building"></i> Tocca
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($empreendimento['LinkConstrutora'])): ?>
                                            <a href="<?= htmlspecialchars($empreendimento['LinkConstrutora']) ?>" target="_blank" class="btn-ficha" title="Construtora">
                                                <i class="fas fa-hammer"></i> Construtora
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($empreendimento['LinkTabela'])): ?>
                                            <a href="<?= htmlspecialchars($empreendimento['LinkTabela']) ?>" target="_blank" class="btn-ficha" title="Tabela">
                                                <i class="fas fa-dollar-sign"></i> Tabela
                                            </a>
                                        <?php endif; ?>
                                        <a href="empreend_ficha1.php?id=<?= $empreendimento['Empreend_ID'] ?>" target="_blank" class="btn-ficha" title="Relatório do Empreendimento">
                                            <i class="fas fa-list"></i> Ficha
                                        </a>
                                        <?php if (UsuarioPodeEditar()): ?>
                                            <a href="gestao_empreend_links_update.php?id=<?= $empreendimento['Empreend_ID'] ?>" target="_blank" class="btn-editar" title="Editar Links">
                                                <i class="fas fa-link"></i> Links
                                            </a>
                                            <a href="gestao_empreend_caract_update.php?id=<?= $empreendimento['Empreend_ID'] ?>" target="_blank" class="btn-editar" title="Editar Características">
                                                <i class="fas fa-list"></i> Caract.
                                            </a>
                                            <a href="fluxo_listar.php?Empreend_id=<?= $empreendimento['Empreend_ID'] ?>" target="_blank" class="btn-editar" title="Fluxo de Pagamento">
                                                <i class="fas fa-list"></i> Fluxo
                                            </a>
                                        <?php endif; ?>
                                        <?php if (UsuarioGestor() || UsuarioAdmin()): ?>
                                            <a href="gestao_painel.php" target="_blank" class="btn-editar" title="Gestão do Sistema">
                                                <i class="fas fa-list"></i> Gestão
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td class="text-center py-4">
                                <i class="fas fa-building fa-2x text-muted mb-3"></i><br>
                                Nenhum empreendimento encontrado.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para imagem ampliada -->
    <div id="imageModal" class="modal-image">
        <span class="close-modal">&times;</span>
        <img class="modal-image-content" id="modalImage">
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tabelaEmpreendimentos').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json",
                    "search": "Pesquisar:",
                    "lengthMenu": "Mostrar _MENU_ registros por página",
                    "zeroRecords": "Nenhum registro encontrado",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    "infoEmpty": "Nenhum registro disponível",
                    "infoFiltered": "(filtrado de _MAX_ registros totais)",
                    "paginate": {
                        "first": "Primeira",
                        "last": "Última",
                        "next": "Próxima",
                        "previous": "Anterior"
                    }
                },
                "paging": true,
                "lengthChange": false,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "pageLength": 100,
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>'
            });

            // Modal de imagem
            window.openImageModal = function(src, alt) {
                var modal = document.getElementById("imageModal");
                var modalImg = document.getElementById("modalImage");
                modal.style.display = "block";
                modalImg.src = src;
                modalImg.alt = alt || '';
            };

            $('.close-modal').click(function() {
                document.getElementById("imageModal").style.display = "none";
            });

            $(window).click(function(event) {
                if (event.target == document.getElementById("imageModal")) {
                    document.getElementById("imageModal").style.display = "none";
                }
            });
        });
    </script>

</body>
</html>
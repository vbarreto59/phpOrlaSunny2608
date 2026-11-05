<?php
// empreend_v3_resultados.php - Versão com tema neobrutalista
session_start();

// Incluir arquivos necessários
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

// Verificar se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// =============================================
// FUNÇÕES AUXILIARES
// =============================================

function removeNumeros($texto) {
    if (empty($texto)) return '';
    $texto = str_replace("-", "", $texto);
    return preg_replace('/[0-9]/', '', $texto);
}

function getCoordinates($localizacao) {
    if (empty(trim($localizacao))) return [null, null];
    $partes = explode(",", $localizacao);
    if (count($partes) >= 2) {
        $lat = trim($partes[0]);
        $lng = trim($partes[1]);
        if (is_numeric($lat) && is_numeric($lng)) {
            return [(float)$lat, (float)$lng];
        }
    }
    return [null, null];
}

function isValidGoogleMapsLink($link) {
    if (empty(trim($link))) return false;
    $lowerLink = strtolower($link);
    return (strpos($lowerLink, "https://www.google.com/maps") === 0 ||
            strpos($lowerLink, "https://maps.google.com") === 0 ||
            strpos($lowerLink, "https://goo.gl/maps") === 0);
}

// =============================================
// PROCESSAMENTO DA CONSULTA COM PDO
// =============================================

$conditions = ["Ocultar = 0"];
$params = [];

if (!empty($_GET['localidade'])) {
    $localidade = trim($_GET['localidade']);
    $conditions[] = "Localidade LIKE :localidade";
    $params[':localidade'] = "%" . $localidade . "%";
}

if (!empty($_GET['tipologia'])) {
    $tipologia = $_GET['tipologia'];
    $tipologiaMap = [
        'Bang' => 'Bangalô', 'Studio' => 'Studio', 'Casa' => 'Casa',
        '1q' => '1Q', '2q' => '2Q', '3q' => '3Q', '4q' => '4Q', '5q' => '5Q'
    ];
    $tipologiaValue = $tipologiaMap[$tipologia] ?? $tipologia;
    $conditions[] = "Tipologia LIKE :tipologia";
    $params[':tipologia'] = "%" . $tipologiaValue . "%";
}

if (!empty($_GET['empresa'])) {
    $conditions[] = "NomeEmpresa = :empresa";
    $params[':empresa'] = trim($_GET['empresa']);
}

if (!empty($_GET['data_entrega'])) {
    $conditions[] = "AnoEntrega = :ano_entrega";
    $params[':ano_entrega'] = (int)$_GET['data_entrega'];
}

if (isset($_GET['BeiraMar']) && $_GET['BeiraMar'] === 'on') {
    $conditions[] = "(BeiraMar = 1 OR BeiraMar = -1)";
}

if (isset($_GET['Varanda']) && $_GET['Varanda'] === 'on') {
    $conditions[] = "(Varanda = 1 OR Varanda = -1)";
}

if (!empty($_GET['orcamento_cliente_raw'])) {
    $conditions[] = ":orcamento_cliente BETWEEN MenorPreco AND MaiorPreco";
    $params[':orcamento_cliente'] = (float)$_GET['orcamento_cliente_raw'];
}

$sql = "SELECT * FROM empreendimento WHERE " . implode(" AND ", $conditions);
$sql .= " ORDER BY Localidade, AnoEntrega, NomeEmpreendimento";

try {
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } elseif (is_float($value)) {
            $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $qtdRegistros = count($empreendimentos);
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Erro na consulta: " . htmlspecialchars($e->getMessage()) . "<br><br><strong>SQL:</strong><br><code>" . htmlspecialchars($sql) . "</code></div>");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados de Empreendimentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

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
        }

        body {
            font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;
            background: var(--bg-body);
            color: var(--text-color);
            min-height: 100vh;
            padding: 1rem;
        }

        /* Container principal */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            overflow: hidden;
        }

        /* Header */
        .header {
            background: var(--primary);
            color: #fff;
            padding: 1.5rem;
            text-align: center;
            position: relative;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
        }
        .header h1 img {
            max-height: 60px;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            background: #fff;
            padding: 4px;
        }
        .header h3 {
            font-weight: 800;
            margin: 0.5rem 0 0;
        }

        .user-info {
            position: absolute;
            top: 0.8rem;
            right: 1.2rem;
            background: rgba(255,255,255,0.15);
            border: 2px solid var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.2rem;
            font-weight: 600;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-info a {
            color: #fff;
            transition: all 0.15s;
        }
        .user-info a:hover {
            opacity: 0.8;
            transform: translateY(-2px);
        }

        /* Conteúdo */
        .content {
            padding: 1.5rem;
        }

        /* Card de resultados */
        .card-resultados {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            overflow: hidden;
        }
        .card-resultados .card-header {
            background: var(--primary);
            color: #fff;
            padding: 0.8rem 1.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            border-bottom: 3px solid var(--shadow-color);
        }
        .card-resultados .card-header .badge {
            background: var(--card-bg);
            color: var(--text-color);
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .card-resultados .card-body {
            padding: 1.5rem;
        }

        /* Alertas neobrutalistas */
        .alert-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            padding: 1rem;
            font-weight: 600;
        }
        .alert-info-neo {
            background: var(--info);
            color: #fff;
        }

        /* Botões */
        .btn-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo:active {
            transform: translate(2px, 2px);
            box-shadow: 1px 1px 0px 0px var(--shadow-color);
        }
        .btn-neo-secondary {
            background: #6c757d;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        .btn-neo-secondary:hover {
            background: #5a6268;
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
        }

        .btn-ficha {
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-weight: 700;
            transition: all 0.15s;
            display: inline-block;
            margin: 0.2rem 0.2rem 0.2rem 0;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .btn-ficha:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--primary-dark);
            color: #fff;
        }

        /* Tabela */
        .table-neo {
            border-collapse: collapse;
            width: 100%;
        }
        .table-neo th {
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            font-weight: 700;
            padding: 0.8rem;
        }
        .table-neo td {
            border: 1px solid var(--shadow-color);
            padding: 0.8rem;
            vertical-align: top;
        }
        .table-neo tbody tr:hover {
            background: rgba(0,0,0,0.02);
        }

        /* Badges */
        .badge-neo {
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-weight: 700;
        }
        .badge-primary-neo {
            background: var(--primary);
            color: #fff;
        }
        .badge-success-neo {
            background: var(--success);
            color: #fff;
        }

        /* Mapa Leaflet */
        .leaflet-map {
            height: 300px;
            width: 100%;
            margin-top: 10px;
            border-radius: 12px;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }

        /* Modal de mapa flutuante */
        #leaflet-map-container {
            z-index: 1050;
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 8px 8px 0px 0px var(--shadow-color);
            padding: 1.5rem;
        }
        #leaflet-map-container .btn-danger {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        #leaflet-map-container .btn-danger:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
        }

        /* Modal de imagem (Bootstrap) */
        .modal-content {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 8px 8px 0px 0px var(--shadow-color);
        }
        .modal-header {
            border-bottom: 2px solid var(--shadow-color);
        }
        .modal-footer {
            border-top: 2px solid var(--shadow-color);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding: 0.5rem; }
            .main-container { border-radius: 12px; }
            .user-info {
                position: static;
                justify-content: center;
                margin-top: 0.5rem;
                display: inline-flex;
            }
            .header { padding: 1rem; }
            .content { padding: 1rem; }
            .card-resultados .card-body { padding: 1rem; }
            .table-neo td, .table-neo th { padding: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <h1><img src="<?= htmlspecialchars($_SESSION['Conf_ImgLogo'] ?? '') ?>" alt="Logo"></h1>
            <h3>SunnyImob</h3>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['UserId'] ?? '') ?> - <?= htmlspecialchars(strtoupper($_SESSION['Usuario'] ?? '')) ?></span>
                <a href="usuario_trocar_senha.php" title="Trocar Senha"><i class="fas fa-key"></i></a>
                <a href="logout.php" title="Sair"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
       
        <div class="content">
            <!-- Botão Voltar -->
            <div class="mb-3">
                <a class="btn btn-neo-secondary" href="javascript:window.close()">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
            
            <!-- Card Resultados -->
            <div class="card-resultados">
                <div class="card-header">
                    <i class="fas fa-list"></i> Resultados
                    <?php if ($qtdRegistros > 0): ?>
                    <span class="badge ms-2"><?= $qtdRegistros ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($qtdRegistros > 0): ?>
                    <div class="alert alert-neo alert-info-neo mb-4">
                        <i class="fas fa-check-circle"></i> Encontrados: <strong><?= $qtdRegistros ?> empreendimentos</strong>
                    </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table id="tabelaResultados" class="table-neo table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Empreendimento</th>
                                    <th>Localidade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($empreendimentos)): ?>
                                <tr>
                                    <td colspan="2" class="text-center py-5">
                                        <i class="fas fa-search fa-2x text-muted mb-3"></i><br>
                                        Nenhum resultado encontrado
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($empreendimentos as $empreend): ?>
                                        <?php
                                        $dataEntrega = '';
                                        if (!empty($empreend['MesEntrega']) && !empty($empreend['AnoEntrega'])) {
                                            $dataEntrega = str_pad($empreend['MesEntrega'], 2, '0', STR_PAD_LEFT) . "/" . $empreend['AnoEntrega'];
                                        } else {
                                            $dataEntrega = "Não definida";
                                        }
                                        
                                        $menorPreco = !empty($empreend['MenorPreco']) && is_numeric($empreend['MenorPreco']) 
                                            ? number_format($empreend['MenorPreco'], 2, ',', '.') 
                                            : null;
                                        
                                        $maiorPreco = !empty($empreend['MaiorPreco']) && is_numeric($empreend['MaiorPreco']) 
                                            ? number_format($empreend['MaiorPreco'], 2, ',', '.') 
                                            : null;
                                        
                                        $coords = getCoordinates($empreend['Localizacao'] ?? '');
                                        ?>
                                        <tr>
                                            <td>
                                                <!--  -->
<?php 
$imagem_path   = "card/" . $empreend['Empreend_ID'] . ".jpg";
$imagem_generica = "card/sem-imagem.jpg"; // ajuste o caminho se necessário
$imagem_final  = file_exists($imagem_path) ? $imagem_path : $imagem_generica;
?>
<img src="<?= htmlspecialchars($imagem_final) ?>" 
     class="img-fluid rounded mb-2" 
     style="max-height: 200px; width: auto; cursor: pointer; border: 2px solid var(--shadow-color); box-shadow: 3px 3px 0px 0px var(--shadow-color); object-fit: cover;"
     onclick="openImageModal(this)"
     onerror="this.onerror=null; this.src='<?= htmlspecialchars($imagem_generica) ?>';"
     alt="<?= htmlspecialchars($empreend['NomeEmpreendimento']) ?>">
                                                <!--  -->
                                                
                                                <div class='mt-2'>
                                                    <h5><?= htmlspecialchars(strtoupper(removeNumeros($empreend['NomeEmpreendimento']))) ?></h5>
                                                    <small class="text-muted"><?= htmlspecialchars(removeNumeros($empreend['NomeEmpresa'])) ?></small><br>
                                                    
                                                    <?php if ($menorPreco): ?>
                                                    <div class="mt-2">
                                                        <i class='fas fa-tag text-success'></i> 
                                                        <strong>A partir de R$ <?= $menorPreco ?></strong>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($maiorPreco && $maiorPreco > $menorPreco): ?>
                                                    <div>
                                                        <i class='fas fa-tags text-info'></i> 
                                                        <span>Até R$ <?= $maiorPreco ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($empreend['BeiraMar']) && ($empreend['BeiraMar'] == 1 || $empreend['BeiraMar'] == -1)): ?>
                                                    <span class="badge badge-neo badge-primary-neo mt-1">BEIRA MAR</span>
                                                    <?php endif; ?>
                                                    
                                                    <span class="badge badge-neo badge-success-neo mt-1">Comissão: <?= $empreend['ComissaoVenda']/1 ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <h5><i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($empreend['Localidade'] ?? '') ?></h5>
                                                <div class="mb-2">
                                                    <i class="fas fa-home text-primary"></i> 
                                                    <?= htmlspecialchars($empreend['Tipologia'] ?? '') ?>
                                                    
                                                    <?php if (!empty($empreend['Varanda']) && ($empreend['Varanda'] == 1 || $empreend['Varanda'] == -1)): ?>
                                                    <br><i class="fas fa-couch text-warning"></i> VARANDA
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <i class="fas fa-calendar-alt text-info"></i> 
                                                    <strong>Entrega: <?= $dataEntrega ?></strong>
                                                </div>
                                                
                                                <div class="btn-group-mobile">
                                                    <a href='empreend_ficha1.php?id=<?= $empreend['Empreend_ID'] ?>' 
                                                       class='btn-ficha mr-2 mb-2' 
                                                       target='_blank'>
                                                        <i class='fas fa-file-alt'></i> Ficha
                                                    </a>
                                                    
                                                    <?php if (!empty($empreend['LinkMapa'])): ?>
                                                        <?php if (isValidGoogleMapsLink($empreend['LinkMapa'])): ?>
                                                            <a href='<?= htmlspecialchars($empreend['LinkMapa']) ?>' 
                                                               target='_blank' 
                                                               class='btn-ficha mr-2 mb-2'>
                                                                <i class='fab fa-google'></i> Maps
                                                            </a>
                                                        <?php elseif (!empty($coords[0]) && !empty($coords[1])): ?>
                                                            <button type='button' 
                                                                    class='btn-ficha mr-2 mb-2 show-map-btn'
                                                                    data-lat='<?= $coords[0] ?>'
                                                                    data-lng='<?= $coords[1] ?>'
                                                                    data-title='<?= htmlspecialchars($empreend['NomeEmpreendimento']) ?>'>
                                                                <i class='fas fa-map-marked-alt'></i> Mapa
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($empreend['LinkConstrutora'])): ?>
                                                    <a href='<?= htmlspecialchars($empreend['LinkConstrutora']) ?>' 
                                                       target='_blank' 
                                                       class='btn-ficha mr-2 mb-2'>
                                                        <i class='fas fa-hammer'></i> Const.
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($empreend['LinkTabela'])): ?>
                                                    <a href='<?= htmlspecialchars($empreend['LinkTabela']) ?>' 
                                                       target='_blank' 
                                                       class='btn-ficha mr-2 mb-2'>
                                                        <i class='fas fa-dollar-sign'></i> Tabela
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mapa único (Leaflet) -->
    <div id="leaflet-map-container" class="d-none position-fixed top-0 start-0 w-100 h-100 p-3" style="z-index: 1050;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 style="color: var(--text-color); font-weight: 800;">Localização</h5>
            <button id="close-map-btn" class="btn btn-danger btn-sm">Fechar</button>
        </div>
        <div id="single-leaflet-map" class="leaflet-map"></div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tabelaResultados').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json"
                },
                "pageLength": 25,
                "responsive": true
            });
        });

        // Mapa Leaflet
        var currentMap = null;

        $(document).on('click', '.show-map-btn', function() {
            var lat = parseFloat($(this).data('lat'));
            var lng = parseFloat($(this).data('lng'));
            var title = $(this).data('title');
            
            if (currentMap) {
                currentMap.remove();
            }
            
            $('#leaflet-map-container').removeClass('d-none');
            
            currentMap = L.map('single-leaflet-map').setView([lat, lng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(currentMap);
            
            L.marker([lat, lng]).addTo(currentMap)
                .bindPopup(title)
                .openPopup();
        });

        $('#close-map-btn').click(function() {
            if (currentMap) {
                currentMap.remove();
                currentMap = null;
            }
            $('#leaflet-map-container').addClass('d-none');
        });

        // Função para modal de imagem
        function openImageModal(img) {
            var modal = $('<div class="modal fade" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">' + img.alt + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><img src="' + img.src + '" class="img-fluid"></div></div></div></div>');
            $('body').append(modal);
            var bsModal = new bootstrap.Modal(modal[0]);
            bsModal.show();
        }
    </script>
</body>
</html>
<!--  -->
<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php';

// ============================================================
// CONEXÃO — fallback robusto
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

$themeDefaults = [
    'primary' => '#800020', 'primary_dark' => '#4B0C1D',
    'bg_body' => '#f8f9fa', 'text_color' => '#1a1a2e',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ============================================================
// BUSCA O IMÓVEL
// ============================================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("<h3 class='text-center mt-5'>Imóvel não encontrado.</h3><div class='text-center mt-3'><a href='vitrine_captacoes.php'>Voltar para a vitrine</a></div>");
}

$stmt = $db->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = ? AND Origem = 'CAPTACAO'");
$stmt->execute([$id]);
$imovel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$imovel) {
    die("<h3 class='text-center mt-5'>Imóvel não encontrado ou indisponível.</h3><div class='text-center mt-3'><a href='vitrine_captacoes.php'>Voltar para a vitrine</a></div>");
}

// Processa as fotos
$fotos = [];
if (!empty($imovel['LinkFicha'])) {
    $jsonFotos = json_decode($imovel['LinkFicha'], true);
    if (is_array($jsonFotos)) {
        $fotos = $jsonFotos;
    }
}
$fotoCapa = count($fotos) > 0 ? $fotos[0] : 'https://placehold.co/800x600/e2e8f0/64748b?text=Sem+Foto';

// Formatação de valores
function formatarMoeda($valor) {
    if (!$valor) return "Sob Consulta";
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($imovel['NomeEmpreendimento'] ?? 'Detalhes do Imóvel') ?> | ORLA CAPTAÇÕES</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --primary-dark: <?= $themeSafe['primary_dark'] ?>;
        }

        body {
            background-color: var(--bg_body);
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            padding-bottom: 3rem;
        }

        /* Navbar */
        .navbar-custom {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .navbar-brand { font-weight: 800; font-size: 1.4rem; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 10px; }
        
        /* Galeria de Fotos */
        .gallery-container {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        .main-photo {
            width: 100%;
            height: 500px;
            object-fit: cover;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .thumbnail-container {
            display: flex;
            gap: 10px;
            padding: 10px;
            overflow-x: auto;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .thumbnail-container::-webkit-scrollbar { height: 6px; }
        .thumbnail-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        
        .thumb-img {
            width: 100px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            opacity: 0.6;
            transition: all 0.2s;
            border: 2px solid transparent;
            flex-shrink: 0;
        }
        .thumb-img:hover, .thumb-img.active {
            opacity: 1;
            border-color: var(--primary);
        }

        /* Bloco de Informações */
        .info-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        .property-type { font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .property-title { font-size: 1.6rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem; line-height: 1.3; }
        .property-location { font-size: 1rem; color: #64748b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }
        
        .price-box { background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; }
        .price-label { font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .price-value { font-size: 2rem; font-weight: 800; color: var(--primary); line-height: 1; margin-top: 4px; }
        .price-market { font-size: 0.85rem; color: #059669; font-weight: 600; margin-top: 5px; display: block; }

        /* Características em Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #334155;
            background: #f1f5f9;
            padding: 10px;
            border-radius: 8px;
        }
        .feature-item i { color: var(--primary); font-size: 1.1rem; width: 20px; text-align: center; }

        /* Lista de Custos */
        .cost-list { list-style: none; padding: 0; margin: 0; }
        .cost-list li {
            display: flex; justify-content: space-between;
            padding: 0.75rem 0; border-bottom: 1px dashed #e2e8f0;
            font-size: 0.95rem; color: #475569;
        }
        .cost-list li:last-child { border-bottom: none; }
        .cost-list li span:last-child { font-weight: 700; color: #1e293b; }

        /* Contato */
        .contact-box {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .contact-box h5 { font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; }
        .contact-btn {
            background: #fff; color: var(--primary);
            border: none; border-radius: 8px;
            padding: 0.8rem; font-weight: 700; width: 100%;
            margin-bottom: 0.5rem; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            text-decoration: none;
        }
        .contact-btn:hover { background: #f1f5f9; color: var(--primary-dark); transform: translateY(-2px); }

        /* Detalhes e Descrição */
        .desc-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px; }
        .desc-text { font-size: 0.95rem; color: #475569; line-height: 1.7; white-space: pre-wrap; }
        
        /* Badge de Estado */
        .badge-custom { background: var(--primary); color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    </style>
</head>
<body>

<nav class="navbar navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="vitrine_captacoes.php">
            <i class="fas fa-arrow-left"></i> Voltar para a Vitrine
        </a>
        <div class="d-none d-md-block fw-bold text-muted">
            <i class="fas fa-building me-2"></i> ORLA CAPTAÇÕES
        </div>
    </div>
</nav>

<div class="container">
    <div class="row g-4">
        
        <!-- COLUNA ESQUERDA: FOTOS E DESCRIÇÃO -->
        <div class="col-lg-8">
            <!-- Galeria -->
            <div class="gallery-container">
                <!-- Imagem Principal -->
                <img src="<?= htmlspecialchars($fotoCapa) ?>" alt="Foto Principal" class="main-photo" id="mainImage" data-bs-toggle="modal" data-bs-target="#modalGaleria">
                
                <!-- Miniaturas -->
                <?php if (count($fotos) > 1): ?>
                <div class="thumbnail-container">
                    <?php foreach ($fotos as $index => $foto): ?>
                        <img src="<?= htmlspecialchars($foto) ?>" 
                             class="thumb-img <?= $index === 0 ? 'active' : '' ?>" 
                             onclick="trocarFoto(this, '<?= htmlspecialchars($foto) ?>')"
                             alt="Miniatura <?= $index + 1 ?>">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Descrição e Análise -->
            <div class="info-card">
                <h3 class="desc-title"><i class="fas fa-align-left"></i> Sobre o Imóvel</h3>
                <div class="desc-text">
                    <?= nl2br(htmlspecialchars($imovel['Descricao'] ?? 'Nenhuma descrição detalhada fornecida.')) ?>
                </div>

                <?php if (!empty($imovel['PontoForte']) || !empty($imovel['PontoFraco'])): ?>
                <hr class="my-4">
                <div class="row g-3">
                    <?php if (!empty($imovel['PontoForte'])): ?>
                    <div class="col-md-6">
                        <div class="p-3 bg-success bg-opacity-10 rounded border border-success border-opacity-25 h-100">
                            <h6 class="fw-bold text-success mb-2"><i class="fas fa-thumbs-up me-1"></i> Ponto Forte</h6>
                            <p class="mb-0 text-success small"><?= nl2br(htmlspecialchars($imovel['PontoForte'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($imovel['PontoFraco'])): ?>
                    <div class="col-md-6">
                        <div class="p-3 bg-danger bg-opacity-10 rounded border border-danger border-opacity-25 h-100">
                            <h6 class="fw-bold text-danger mb-2"><i class="fas fa-thumbs-down me-1"></i> Ponto Fraco</h6>
                            <p class="mb-0 text-danger small"><?= nl2br(htmlspecialchars($imovel['PontoFraco'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Detalhes Adicionais / Custos -->
            <div class="info-card">
                <h3 class="desc-title"><i class="fas fa-file-invoice-dollar"></i> Custos Mensais</h3>
                <ul class="cost-list">
                    <li><span>Condomínio</span> <span><?= formatarMoeda($imovel['ValorCond']) ?></span></li>
                    <li><span>IPTU</span> <span><?= formatarMoeda($imovel['ValorIPTU']) ?></span></li>
                    <li><span>Água (Média)</span> <span><?= formatarMoeda($imovel['ValorAgua']) ?></span></li>
                    <li><span>Energia (Média)</span> <span><?= formatarMoeda($imovel['ValorEnergia']) ?></span></li>
                    <?php if (!empty($imovel['TaxaExtra'])): ?>
                        <li><span>Taxa Extra</span> <span><?= htmlspecialchars($imovel['TaxaExtra']) ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($imovel['EmpresaGestora'])): ?>
                        <li><span>Empresa Gestora</span> <span><?= htmlspecialchars($imovel['EmpresaGestora']) ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- COLUNA DIREITA: RESUMO, CARACTERÍSTICAS E CONTATO -->
        <div class="col-lg-4">
            <!-- Box de Resumo Financeiro -->
            <div class="info-card">
                <div class="property-type">
                    <span class="badge-custom me-2"><?= htmlspecialchars($imovel['CondicaoImovel'] ?? 'Usado') ?></span>
                    <?= htmlspecialchars($imovel['TipoImovelCaptacao'] ?? 'Imóvel') ?>
                </div>
                <h1 class="property-title"><?= htmlspecialchars($imovel['NomeEmpreendimento'] ?? 'Sem título') ?></h1>
                <div class="property-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($imovel['Localidade'] ?? 'Localização não informada') ?>
                </div>

                <div class="price-box">
                    <div class="price-label">Valor Desejado</div>
                    <div class="price-value"><?= formatarMoeda($imovel['ValorDesejado'] ?? $imovel['ValorMercado']) ?></div>
                    <?php if (!empty($imovel['ValorMercado']) && $imovel['ValorMercado'] != $imovel['ValorDesejado']): ?>
                        <span class="price-market"><i class="fas fa-check-circle me-1"></i> Avaliação de Mercado: <?= formatarMoeda($imovel['ValorMercado']) ?></span>
                    <?php endif; ?>
                </div>

                <h5 class="fw-bold mb-3 border-bottom pb-2">Características</h5>
                <div class="features-grid">
                    <?php if (!empty($imovel['MetragemTotal'])): ?>
                        <div class="feature-item"><i class="fas fa-ruler-combined"></i> <?= number_format($imovel['MetragemTotal'], 0, ',', '.') ?> m²</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($imovel['Quartos'])): ?>
                        <div class="feature-item"><i class="fas fa-bed"></i> <?= htmlspecialchars($imovel['Quartos']) ?> Quartos</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($imovel['Suites'])): ?>
                        <div class="feature-item"><i class="fas fa-bed"></i> <?= htmlspecialchars($imovel['Suites']) ?> Suítes</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($imovel['WCTotal'])): ?>
                        <div class="feature-item"><i class="fas fa-bath"></i> <?= htmlspecialchars($imovel['WCTotal']) ?> Banheiros</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($imovel['VagasGaragem'])): ?>
                        <div class="feature-item"><i class="fas fa-car"></i> <?= htmlspecialchars($imovel['VagasGaragem']) ?> Vagas</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($imovel['Andar'])): ?>
                        <div class="feature-item"><i class="fas fa-building"></i> Andar: <?= htmlspecialchars($imovel['Andar']) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($imovel['PossuiElevador']) && $imovel['PossuiElevador'] == 1): ?>
                        <div class="feature-item"><i class="fas fa-arrows-alt-v"></i> Elevador</div>
                    <?php endif; ?>

                    <?php if (!empty($imovel['EstadoConservacao'])): ?>
                        <div class="feature-item"><i class="fas fa-star"></i> Estado: <?= htmlspecialchars($imovel['EstadoConservacao']) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($imovel['AnoEntrega'])): ?>
                        <div class="feature-item"><i class="fas fa-calendar-alt"></i> Ano: <?= htmlspecialchars($imovel['AnoEntrega']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Box de Contato -->
            <div class="contact-box">
                <h5><i class="fas fa-user-tie"></i> Fale com o Corretor</h5>
                <div class="mb-3">
                    <div class="fw-bold fs-5"><?= htmlspecialchars($imovel['CaptadorNome'] ?? 'ORLA CAPTAÇÕES') ?></div>
                    <?php if (!empty($imovel['CaptadorTelefone'])): ?>
                        <div class="opacity-75 small mt-1"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($imovel['CaptadorTelefone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($imovel['Exclusividade']) && $imovel['Exclusividade'] == 1): ?>
                        <div class="mt-2 text-warning fw-bold small"><i class="fas fa-medal me-1"></i> Imóvel com Exclusividade</div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($imovel['CaptadorTelefone'])): 
                    // Limpa o telefone para o link do WhatsApp
                    $telefoneLimpo = preg_replace('/[^0-9]/', '', $imovel['CaptadorTelefone']);
                    $msgWhats = urlencode("Olá, tenho interesse no imóvel: " . ($imovel['NomeEmpreendimento'] ?? 'ID ' . $imovel['Empreend_ID']));
                ?>
                    <a href="https://wa.me/55<?= $telefoneLimpo ?>?text=<?= $msgWhats ?>" target="_blank" class="contact-btn">
                        <i class="fab fa-whatsapp fs-5"></i> Chamar no WhatsApp
                    </a>
                    <a href="tel:<?= $telefoneLimpo ?>" class="contact-btn" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.3);">
                        <i class="fas fa-phone"></i> Ligar Agora
                    </a>
                <?php endif; ?>

                <!-- Botão opcional para ver dados do proprietário (uso interno, se logado) -->
                <?php if (isset($_SESSION['Usuario'])): ?>
                <div class="mt-3 pt-3 border-top border-light border-opacity-25">
                    <small class="opacity-50">Uso interno (Logado)</small>
                    <div class="small mt-1"><i class="fas fa-user me-1"></i> Prop: <?= htmlspecialchars($imovel['ProprietarioNome'] ?? '-') ?></div>
                    <?php if (!empty($imovel['ProprietarioTelefone'])): ?>
                        <div class="small"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($imovel['ProprietarioTelefone']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE FOTO EXPANDIDA -->
<div class="modal fade" id="modalGaleria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close" style="z-index: 10; filter: invert(1);"></button>
                <img src="<?= htmlspecialchars($fotoCapa) ?>" id="modalImage" class="img-fluid rounded shadow-lg" style="max-height: 90vh;" alt="Foto Expandida">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Função para trocar a foto principal e a foto do modal ao clicar na miniatura
    function trocarFoto(element, src) {
        // Remove a classe ativa de todas as miniaturas
        document.querySelectorAll('.thumb-img').forEach(img => img.classList.remove('active'));
        // Adiciona a classe ativa na miniatura clicada
        element.classList.add('active');
        
        // Troca a imagem principal
        document.getElementById('mainImage').src = src;
        // Troca a imagem do modal
        document.getElementById('modalImage').src = src;
    }
</script>

</body>
</html>
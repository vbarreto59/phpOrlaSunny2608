<?php 
session_start();
require_once '../../includes/auth.php'; 
require_once '../../conn_cap.php'; 
require_once '../../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Busca os dados do imóvel e do proprietário (opcional, caso queira o nome do dono também)
$stmt = $conn->prepare("SELECT * FROM imoveis WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$im = $stmt->fetch();

if (!$im) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Imóvel não encontrado.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

$stmt_fotos = $conn->prepare("SELECT caminho FROM fotos_imoveis WHERE imovel_id = ?");
$stmt_fotos->execute([$id]);
$fotos = $stmt_fotos->fetchAll();
?>

<style>
    #map { height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; }
    
    /* RODAPÉ EXCLUSIVO PARA O CORRETOR */
    .footer-corretor {
        border-top: 2px solid #007bff;
        padding: 20px 0;
        margin-top: 50px;
        text-align: center;
        background-color: #f8f9fa;
    }

    @media print {
        .btn, .navbar, footer, .d-print-none { display: none !important; }
        body { background-color: white !important; -webkit-print-color-adjust: exact; }
        
        /* Força o rodapé a aparecer no final de cada página impressa */
        .footer-corretor {
            position: fixed;
            bottom: 0;
            width: 100%;
            border-top: 1px solid #333;
            background-color: white !important;
            padding: 10px 0;
            font-size: 10pt;
        }

        .secao-fotos { page-break-before: always; margin-bottom: 100px; }
        .foto-pdf { max-width: 100%; height: auto; margin-bottom: 20px; page-break-inside: avoid; }
        
        /* Ajuste de margem para o rodapé não cobrir conteúdo */
        .container { margin-bottom: 80px !important; }
    }

    .foto-lista { width: 100%; max-height: 600px; object-fit: contain; margin-bottom: 20px; border-radius: 8px; border: 1px solid #eee; }
</style>

<div class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <h2 class="text-primary fw-bold">Ficha de Captação</h2>
        <div>
            <button onclick="window.print()" class="btn btn-danger shadow">
                <i class="bi bi-file-earmark-pdf me-2"></i>Gerar PDF Profissional
            </button>
            <a href="list.php" class="btn btn-outline-secondary ms-2">Voltar</a>
        </div>
    </div>

    <div class="ficha-imovel">
        <div class="mb-4">
            <h1 class="fw-bold"><?= htmlspecialchars($im['titulo']) ?></h1>
            <h2 class="text-success fw-bold">R$ <?= number_format($im['valor'] ?? $im['preco'] ?? 0, 2, ',', '.') ?></h2>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <h5 class="fw-bold text-primary border-bottom pb-2">Endereço</h5>
                <p class="fs-5"><?= htmlspecialchars($im['endereco']) ?></p>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold text-primary border-bottom pb-2">Características</h5>
                <p><?= nl2br(htmlspecialchars($im['descricao'])) ?></p>
            </div>
        </div>

        <div class="mb-4">
            <h5 class="fw-bold text-primary mb-3">Localização Geográfica</h5>
            <div id="map"></div>
        </div>

        <div class="secao-fotos">
            <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">Galeria de Fotos do Imóvel</h5>
            <div class="row">
                <?php if (!empty($fotos)): ?>
                    <?php foreach ($fotos as $f): ?>
                        <div class="col-12 text-center">
                            <img src="<?= htmlspecialchars($f['caminho']) ?>" class="foto-lista foto-pdf">
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer-corretor">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12">
                    <p class="mb-0 fw-bold" style="font-size: 1.1rem;">
                        Corretor Valter Barreto | CRECI-PE: 22003
                    </p>
                    <p class="mb-0 text-muted">
                        <i class="bi bi-whatsapp me-1"></i> Contato: 81 98842-1455
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.onload = function() {
    var lat = <?= $im['latitude'] ?? 0 ?>;
    var lng = <?= $im['longitude'] ?? 0 ?>;

    if (lat !== 0 && lng !== 0) {
        var map = L.map('map').setView([lat, lng], 17);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([lat, lng]).addTo(map);
        
        // Resolve problema de renderização do mapa
        setTimeout(function(){ map.invalidateSize(); }, 400);
    } else {
        document.getElementById('map').style.display = 'none';
    }
};
</script>

<?php require_once '../../includes/footer.php'; ?>
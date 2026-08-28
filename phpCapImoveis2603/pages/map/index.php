<?php session_start();
require_once '../../includes/auth.php'; 
require_once '../../includes/header.php'; ?>
<h2>Mapa de Todos os Imóveis</h2>
<div id="map" style="height:600px"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var map = L.map('map').setView([-8.05, -34.9], 13); // Recife
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

<?php
$stmt = $conn->query("SELECT * FROM imoveis WHERE deleted_at IS NULL");
while ($i = $stmt->fetch()) {
    echo "L.marker([{$i['latitude']}, {$i['longitude']}]).addTo(map)
        .bindPopup('<b>{$i['titulo']}</b><br>R$ ".number_format($i['preco'],2)."<br><a href=\"../imoveis/view.php?id={$i['id']}\">Ver detalhes</a>');";
}
?>
</script>
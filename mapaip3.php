<?php
// mapaip3.php
session_start();
require_once 'conn.php';
require_once 'usr_acoes.php';

header('Content-Type: text/html; charset=utf-8');

// Configuração de Filtros
$filtroIP      = $_GET['filtroIP']      ?? '';
$filtroData    = $_GET['filtroData']    ?? '';
$filtroUsuario = $_GET['filtroUsuario'] ?? '';
$filtroIPLike  = $filtroIP ? $filtroIP . '%' : '';

// Calcular data de 30 dias atrás
$dataLimite = date('Y-m-d', strtotime('-30 days'));

// Buscar datas dos últimos 30 dias
$sqlDatas = "SELECT DISTINCT DATE(Data_Hora_Visita) as data_visita 
             FROM historico_navegacao 
             WHERE Data_Hora_Visita >= ? 
             ORDER BY data_visita DESC";
$stmtDatas = $conn->prepare($sqlDatas);
$stmtDatas->execute([$dataLimite]);
$dates = $stmtDatas->fetchAll(PDO::FETCH_ASSOC);

// Buscar usuários únicos dos últimos 30 dias
$sqlUsuarios = "SELECT DISTINCT Usuario 
                FROM historico_navegacao 
                WHERE Usuario IS NOT NULL 
                  AND Usuario <> '' 
                  AND Data_Hora_Visita >= ?
                ORDER BY Usuario";
$stmtUsuarios = $conn->prepare($sqlUsuarios);
$stmtUsuarios->execute([$dataLimite]);
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Busca IPs únicos (não repetir mesmo usuário+IP)
$where = "Data_Hora_Visita >= ? 
          AND IP_Usuario NOT IN ('127.0.0.1','::1','0.0.0.0','localhost') 
          AND IP_Usuario NOT LIKE '192.168.%' 
          AND IP_Usuario NOT LIKE '10.%' 
          AND IP_Usuario NOT LIKE '172.%'";
$params = [$dataLimite];

if ($filtroIP) { 
    $where .= " AND IP_Usuario LIKE ?"; 
    $params[] = $filtroIPLike; 
}
if ($filtroData) { 
    $where .= " AND DATE(Data_Hora_Visita) = ?"; 
    $params[] = $filtroData; 
}
if ($filtroUsuario) { 
    $where .= " AND Usuario = ?"; 
    $params[] = $filtroUsuario; 
}

// Busca o registro mais recente de cada combinação Usuario+IP
$sql = "SELECT h1.IP_Usuario, h1.Usuario, MAX(h1.Data_Hora_Visita) as ultimo_acesso
        FROM historico_navegacao h1
        WHERE $where
          AND NOT EXISTS (
            SELECT 1 
            FROM historico_navegacao h2 
            WHERE h2.Usuario = h1.Usuario 
              AND h2.IP_Usuario = h1.IP_Usuario 
              AND h2.Data_Hora_Visita > h1.Data_Hora_Visita
              AND h2.Data_Hora_Visita >= ?
          )
        GROUP BY h1.IP_Usuario, h1.Usuario
        ORDER BY ultimo_acesso DESC";
$params[] = $dataLimite;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$dadosBD = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contador de IPs encontrados
$totalIPsEncontrados = count($dadosBD);

// Preparar dados para o JavaScript
$dadosParaMapa = [];
foreach ($dadosBD as $row) {
    $dadosParaMapa[$row['IP_Usuario']] = [
        'usuario' => $row['Usuario'],
        'ultimo_acesso' => $row['ultimo_acesso']
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Mapa de Acessos - Últimos 30 Dias</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .container-fluid { 
            max-width: 1400px; 
        }
        
        .main-card {
            border: none;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            background: white;
            margin-bottom: 24px;
        }
        
        .card-header-custom {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px 25px;
            border-bottom: none;
        }
        
        .card-header-custom h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.4rem;
        }
        
        #map { 
            height: 680px; 
            width: 100%; 
            border-radius: 12px; 
            border: none;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border-left: 5px solid var(--primary-color);
        }
        
        .stats-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--secondary-color);
            line-height: 1;
        }
        
        .stats-label {
            font-size: 0.95rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .loading-badge { 
            position: absolute; 
            top: 20px; 
            right: 20px; 
            z-index: 1000; 
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .progress-container {
            position: absolute;
            top: 70px;
            right: 20px;
            z-index: 1000;
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
            border: 1px solid rgba(13, 110, 253, 0.2);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-outline-secondary {
            border-radius: 10px;
            padding: 12px 20px;
            border: 2px solid #dee2e6;
        }
        
        .map-container {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .legend {
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-size: 0.9rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .marker-cluster {
            background-color: rgba(67, 97, 238, 0.6);
            color: white;
            font-weight: bold;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            #map {
                height: 500px;
            }
            
            .stats-card {
                padding: 15px;
            }
            
            .stats-number {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <!-- Cabeçalho com Título -->
    <div class="main-card">
        <div class="card-header-custom">
            <div class="d-flex justify-content-between align-items-center">
                <h3><i class="bi bi-geo-alt-fill me-2"></i> Mapa de Acessos - Últimos 30 Dias</h3>
                <div class="text-end">
                    <div class="text-white-50 small">Monitoramento em tempo real</div>
                </div>
            </div>
        </div>
        
        <!-- Cards de Estatísticas -->
        <div class="row p-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= $totalIPsEncontrados ?></div>
                    <div class="stats-label">IPs Únicos Encontrados</div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Últimos 30 dias
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= count($usuarios) ?></div>
                    <div class="stats-label">Usuários Ativos</div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-people me-1"></i>
                        Com acesso recente
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= count($dates) ?></div>
                    <div class="stats-label">Dias com Acesso</div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-calendar3 me-1"></i>
                        Período analisado
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= date('d/m/Y') ?></div>
                    <div class="stats-label">Data Atual</div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-clock me-1"></i>
                        Última atualização
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="px-4 pb-3">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-medium">IP Inicial</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-globe"></i></span>
                        <input type="text" name="filtroIP" class="form-control" value="<?= htmlspecialchars($filtroIP) ?>" placeholder="Ex: 177.200.xxx">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-medium">Data Específica</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-calendar"></i></span>
                        <select name="filtroData" class="form-select">
                            <option value="">Todas as datas</option>
                            <?php foreach($dates as $d): ?>
                                <option value="<?= $d['data_visita'] ?>" <?= $filtroData == $d['data_visita'] ? 'selected' : '' ?>>
                                    <?= date('d/m/Y', strtotime($d['data_visita'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-medium">Usuário</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                        <select name="filtroUsuario" class="form-select">
                            <option value="">Todos os usuários</option>
                            <?php foreach($usuarios as $u): ?>
                                <option value="<?= $u['Usuario'] ?>" <?= $filtroUsuario == $u['Usuario'] ? 'selected' : '' ?>><?= $u['Usuario'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-2"></i>Aplicar Filtros
                        </button>
                        <?php if($filtroIP || $filtroData || $filtroUsuario): ?>
                            <a href="mapaip3.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Limpar Filtros
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Container do Mapa -->
    <div class="map-container">
        <div id="status-api" class="loading-badge">
            <i class="bi bi-hourglass-split me-2"></i>
            <span id="status-text">Aguardando processamento...</span>
        </div>
        
        <div class="progress-container" id="progress-container">
            <div class="d-flex justify-content-between mb-2">
                <span class="small fw-medium">Progresso:</span>
                <span class="small fw-medium" id="progress-percent">0%</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                     id="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <small class="text-muted" id="progress-count">0/0 processados</small>
                <small class="text-muted" id="success-count">0 sucessos</small>
            </div>
        </div>
        
        <!-- Legenda -->
        <div class="legend">
            <div class="fw-medium mb-2"><i class="bi bi-key me-1"></i> Legenda</div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #4361ee;"></div>
                <span>IP Único</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #4cc9f0;"></div>
                <span>Múltiplos IPs</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ff6b6b;"></div>
                <span>Erro na API</span>
            </div>
        </div>
        
        <div id="map"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css">

<script>
$(document).ready(function() {
    // 1. Configurações Iniciais
    const centroMundo = [20, 0];
    const map = L.map('map').setView(centroMundo, 2);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);
    
    // 2. Camadas para agrupamento
    const markersLayer = L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 50,
        iconCreateFunction: function(cluster) {
            return L.divIcon({
                html: '<div class="marker-cluster" style="width: 40px; height: 40px; line-height: 40px;">' + 
                      cluster.getChildCount() + '</div>',
                className: 'marker-cluster',
                iconSize: L.point(40, 40)
            });
        }
    });
    
    map.addLayer(markersLayer);
    
    // 3. Dados vindos do PHP
    const listaIPs = <?= json_encode($dadosParaMapa) ?>;
    const totalIPs = Object.keys(listaIPs).length;
    
    // 4. Atualizar elementos de status
    function atualizarStatus(mensagem, tipo = 'warning', icon = 'hourglass-split') {
        const badge = $('#status-api');
        const iconClass = tipo === 'success' ? 'check-circle' : 
                         tipo === 'error' ? 'exclamation-triangle' : 
                         tipo === 'info' ? 'info-circle' : 'hourglass-split';
        
        badge.find('i').attr('class', 'bi bi-' + iconClass + ' me-2');
        badge.find('#status-text').text(mensagem);
        
        badge.removeClass('bg-warning bg-success bg-danger bg-info')
             .addClass('bg-' + tipo)
             .show();
    }
    
    // 5. Atualizar progresso
    function atualizarProgresso(processados, sucessos) {
        const percent = totalIPs > 0 ? Math.round((processados / totalIPs) * 100) : 0;
        
        $('#progress-percent').text(percent + '%');
        $('#progress-bar').css('width', percent + '%');
        $('#progress-count').text(`${processados}/${totalIPs} processados`);
        $('#success-count').text(`${sucessos} sucessos`);
        
        if (processados > 0 && !$('#progress-container').is(':visible')) {
            $('#progress-container').slideDown();
        }
    }
    
    // 6. Função para buscar geolocalização com fallback
    async function buscarGeolocalizacao(ip) {
        const apis = [
            `https://ipapi.co/${ip}/json/`,
            `https://ipwho.is/${ip}`,
            `https://freeipapi.com/api/json/${ip}`
        ];
        
        for (let apiUrl of apis) {
            try {
                const response = await fetch(apiUrl, { 
                    mode: 'cors',
                    headers: { 'Accept': 'application/json' }
                });
                
                if (!response.ok) continue;
                
                const data = await response.json();
                
                // Verifica se tem coordenadas válidas
                let lat, lon, city, region, country, isp;
                
                if (apiUrl.includes('ipapi.co')) {
                    if (data.latitude && data.longitude) {
                        return {
                            lat: data.latitude,
                            lon: data.longitude,
                            city: data.city,
                            region: data.region,
                            country: data.country_name,
                            isp: data.org || data.asn || 'N/A'
                        };
                    }
                } else if (apiUrl.includes('ipwho.is')) {
                    if (data.latitude && data.longitude) {
                        return {
                            lat: data.latitude,
                            lon: data.longitude,
                            city: data.city,
                            region: data.region,
                            country: data.country,
                            isp: data.connection?.isp || 'N/A'
                        };
                    }
                } else if (apiUrl.includes('freeipapi.com')) {
                    if (data.latitude && data.longitude) {
                        return {
                            lat: data.latitude,
                            lon: data.longitude,
                            city: data.cityName,
                            region: data.regionName,
                            country: data.countryName,
                            isp: data.isp || 'N/A'
                        };
                    }
                }
            } catch (error) {
                console.log(`API ${apiUrl} falhou para IP ${ip}:`, error);
                continue;
            }
        }
        
        return null; // Todas as APIs falharam
    }
    
    // 7. Função para processar IPs em lote
    async function processarIPsEmLote() {
        if (totalIPs === 0) {
            atualizarStatus('Nenhum IP encontrado para os filtros selecionados.', 'info', 'info-circle');
            return;
        }
        
        atualizarStatus(`Iniciando processamento de ${totalIPs} IPs únicos...`, 'warning', 'hourglass-split');
        $('#progress-container').show();
        
        markersLayer.clearLayers();
        let processados = 0;
        let sucessos = 0;
        
        const tamanhoLote = 3; // Pequeno para evitar bloqueio
        const delayEntreLotes = 1500; // 1.5 segundos entre lotes
        const ipsArray = Object.keys(listaIPs);
        
        for (let i = 0; i < totalIPs; i += tamanhoLote) {
            const lote = ipsArray.slice(i, i + tamanhoLote);
            const promises = [];
            
            // Processa cada IP no lote
            for (const ip of lote) {
                promises.push(
                    buscarGeolocalizacao(ip).then(data => {
                        processados++;
                        
                        if (data) {
                            sucessos++;
                            
                            // Formata data do último acesso
                            const dataAcesso = new Date(listaIPs[ip].ultimo_acesso);
                            const dataFormatada = dataAcesso.toLocaleDateString('pt-BR') + ' ' + 
                                                dataAcesso.toLocaleTimeString('pt-BR', { 
                                                    hour: '2-digit', 
                                                    minute: '2-digit' 
                                                });
                            
                            // Cria marcador personalizado
                            const marker = L.marker([data.lat, data.lon], {
                                title: `IP: ${ip} | Usuário: ${listaIPs[ip].usuario}`
                            });
                            
                            // Popup com informações
                            const popupContent = `
                                <div style="min-width: 250px;">
                                    <h6 style="margin-top: 0; color: #4361ee;">
                                        <i class="bi bi-geo-alt"></i> Localização do IP
                                    </h6>
                                    <div style="margin-bottom: 8px;">
                                        <strong>Usuário:</strong> ${listaIPs[ip].usuario}<br>
                                        <strong>IP:</strong> <code>${ip}</code><br>
                                        <strong>Último acesso:</strong> ${dataFormatada}
                                    </div>
                                    <hr style="margin: 8px 0;">
                                    <div style="margin-bottom: 8px;">
                                        <strong>Cidade:</strong> ${data.city || 'N/A'}<br>
                                        <strong>Região:</strong> ${data.region || 'N/A'}<br>
                                        <strong>País:</strong> ${data.country || 'N/A'}<br>
                                        <strong>Provedor:</strong> ${data.isp || 'N/A'}
                                    </div>
                                    <div class="text-end small text-muted">
                                        <i class="bi bi-clock-history"></i> Processado agora
                                    </div>
                                </div>
                            `;
                            
                            marker.bindPopup(popupContent);
                            markersLayer.addLayer(marker);
                        }
                        
                        atualizarProgresso(processados, sucessos);
                        atualizarStatus(`Processando: ${processados}/${totalIPs} IPs`, 'warning', 'arrow-repeat');
                    }).catch(error => {
                        console.error(`Erro ao processar IP ${ip}:`, error);
                        processados++;
                        atualizarProgresso(processados, sucessos);
                    })
                );
            }
            
            // Aguarda o lote atual terminar
            await Promise.all(promises);
            
            // Aguarda antes do próximo lote (para respeitar limites da API)
            if (i + tamanhoLote < totalIPs) {
                await new Promise(resolve => setTimeout(resolve, delayEntreLotes));
            }
        }
        
        // Processamento concluído
        const tempoEspera = Math.round(totalIPs * 0.5); // Estimativa em segundos
        atualizarStatus(`Concluído! ${sucessos}/${totalIPs} IPs geolocalizados.`, 'success', 'check-circle');
        
        // Ajusta o zoom para mostrar todos os marcadores
        if (sucessos > 0) {
            setTimeout(() => {
                const bounds = markersLayer.getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds, { 
                        padding: [50, 50],
                        maxZoom: 10
                    });
                }
            }, 1000);
        }
        
        // Oculta a barra de progresso após alguns segundos
        setTimeout(() => {
            $('#progress-container').slideUp();
        }, 3000);
        
        // Adiciona contador ao mapa
        if (sucessos > 0) {
            L.control.textbox(`IPs plotados: ${sucessos}/${totalIPs}`).addTo(map);
        }
    }
    
    // 8. Inicia o processamento
    setTimeout(processarIPsEmLote, 500);
    
    // 9. Ajusta o tamanho do mapa após carregamento
    setTimeout(() => {
        map.invalidateSize();
    }, 100);
    
    // 10. TextBox Control para Leaflet
    L.Control.Textbox = L.Control.extend({
        onAdd: function(map) {
            var container = L.DomUtil.create('div', 'leaflet-control-attribution');
            container.style.backgroundColor = 'rgba(255,255,255,0.8)';
            container.style.padding = '5px 10px';
            container.style.borderRadius = '4px';
            container.style.fontSize = '12px';
            container.style.fontWeight = 'bold';
            container.innerHTML = this.options.text;
            return container;
        }
    });
    
    L.control.textbox = function(options) {
        return new L.Control.Textbox(options);
    };
});
</script>
</body>
</html>
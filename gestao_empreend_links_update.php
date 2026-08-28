<?php
session_start();

// Inclui a conexão PDO
require_once 'conn.php';


// Funções auxiliares
function usuarioPodeEditar() {
    // Implemente sua lógica de permissões aqui
    return isset($_SESSION['Usuario']) && !empty($_SESSION['Usuario']);
}

function removerNumeros($texto) {
    return preg_replace('/[0-9*-]/', '', $texto);
}

// Função para registrar histórico (implemente conforme necessário)
function registrarHistorico($idEmpreend, $idEmpresa, $acao) {
    global $pdo;
    
    try {
        $userId = isset($_SESSION['UserId']) ? $_SESSION['UserId'] : 0;
        
        $sql = "INSERT INTO histatulog (IdEmpresa, IdEmpreend, IdUser, Usuario, Historico) 
                VALUES (:idEmpresa, :idEmpreend, :idUser, :usuario, :historico)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':idEmpresa' => $idEmpresa,
            ':idEmpreend' => $idEmpreend,
            ':idUser' => $userId,
            ':usuario' => $_SESSION['Usuario'],
            ':historico' => $acao
        ]);
        
        echo "<script>console.log('Histórico registrado com sucesso');</script>";
        
    } catch(PDOException $e) {
        echo "<script>console.error('Erro ao registrar histórico: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Verificação de login
if (empty($_SESSION['Usuario'])) {
    header("Location: login_v3a.php");
    exit();
}

// Obter ID do empreendimento
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    die("ID do empreendimento não fornecido");
}

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar inputs
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $localizacao = isset($_POST['Localizacao']) ? trim($_POST['Localizacao']) : '';
    $linkMapa = isset($_POST['LinkMapa']) ? trim($_POST['LinkMapa']) : '';
    $linkFicha = isset($_POST['LinkFicha']) ? trim($_POST['LinkFicha']) : '';
    $linkSite = isset($_POST['LinkSite']) ? trim($_POST['LinkSite']) : '';
    $linkVideo = isset($_POST['LinkVideo']) ? trim($_POST['LinkVideo']) : '';
    $linkTocca = isset($_POST['LinkTocca']) ? trim($_POST['LinkTocca']) : '';
    $linkConstrutora = isset($_POST['LinkConstrutora']) ? trim($_POST['LinkConstrutora']) : '';
    $linkTabela = isset($_POST['LinkTabela']) ? trim($_POST['LinkTabela']) : '';
    $linkCard = isset($_POST['LinkCard']) ? trim($_POST['LinkCard']) : '';
    $idEmpresa = isset($_POST['idEmpresa']) ? intval($_POST['idEmpresa']) : 0;
    $nomeEmpreend = isset($_POST['NomeEmpreend']) ? trim($_POST['NomeEmpreend']) : '';
    
    if ($id === 0) {
        $_SESSION['mensagem'] = "Erro: ID do empreendimento não fornecido.";
        $_SESSION['tipo_mensagem'] = "danger";
    } else {
        try {
            // Query SQL para atualizar os links
            $sql = "UPDATE Empreendimento SET 
                    Localizacao = :localizacao,
                    LinkMapa = :linkMapa,
                    LinkFicha = :linkFicha,
                    LinkSite = :linkSite,
                    LinkVideo = :linkVideo,
                    LinkTocca = :linkTocca,
                    LinkConstrutora = :linkConstrutora,
                    LinkTabela = :linkTabela,
                    LinkCard = :linkCard,
                    DataAtualizacao = NOW(),
                    UsuarioAtu = :usuarioAtu
                    WHERE Empreend_ID = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':localizacao' => $localizacao,
                ':linkMapa' => $linkMapa,
                ':linkFicha' => $linkFicha,
                ':linkSite' => $linkSite,
                ':linkVideo' => $linkVideo,
                ':linkTocca' => $linkTocca,
                ':linkConstrutora' => $linkConstrutora,
                ':linkTabela' => $linkTabela,
                ':linkCard' => $linkCard,
                ':usuarioAtu' => $_SESSION['Usuario'],
                ':id' => $id
            ]);
            
            // Registrar histórico
            registrarHistorico($id, $idEmpresa, "Atualização de Links");
            
            // Enviar email de log (implemente conforme necessário)
            // sendMail($nomeEmpreend . " - " . $sql);
            
            $_SESSION['mensagem'] = "Links atualizados com sucesso!";
            $_SESSION['tipo_mensagem'] = "success";
            
        } catch(PDOException $e) {
            $_SESSION['mensagem'] = "Erro ao atualizar: " . $e->getMessage();
            $_SESSION['tipo_mensagem'] = "danger";
        }
    }
}

// Buscar dados do empreendimento
try {
    $stmt = $pdo->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = :id");
    $stmt->execute([':id' => $id]);
    $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empreendimento) {
        die("Empreendimento não encontrado");
    }
} catch(PDOException $e) {
    die("Erro ao buscar empreendimento: " . $e->getMessage());
}

// Função para escapar dados para exibição HTML
function escape($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// Configuração de tempo de sessão para JavaScript
$sessionTimeout = ini_get('session.gc_maxlifetime') ?: 1440;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="600">
    <title>SunnyImob - Links - Empreendimento</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .link-group {
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .link-group label {
            font-weight: 500;
        }
        .img-loading {
            opacity: 0.3;
        }
        .img-fallback {
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        
        <!-- Contador de sessão -->
        <audio id="alertSound" src="notification.mp3" preload="auto"></audio>
        
        <div id="sessionTimer" style="position:fixed; bottom:20px; left:20px; background:#f8f9fa; padding:10px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.1); z-index:1000; border:1px solid #ddd;">
            <i class="bi bi-clock" style="margin-right:5px;"></i>
            Tempo restante: <span id="time" style="font-weight:bold;">--:--</span>
        </div>
        
        <div id="sessionWarning" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1050;">
            <div style="background:white; width:400px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 20px rgba(0,0,0,0.2);">
                <h3 style="color:#dc3545;"><i class="bi bi-exclamation-triangle-fill"></i> Sessão Prestes a Expirar</h3>
                <p>Sua sessão expirará em <span id="warningTime">5</span> minutos.</p>
                <p>Os dados foram salvos automaticamente.</p>
                <div style="text-align:right; margin-top:20px;">
                    <button onclick="logout()" class="btn btn-secondary me-2">Sair</button>
                    <button onclick="extendSession()" class="btn btn-primary">Continuar</button>
                </div>
            </div>
        </div>
        
        <header class="container-fluid py-3 mb-4 border-bottom" style="background-color: #800000; color: white;">
            <div class="container">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-2 mb-md-0 d-flex align-items-center">
                        <img src="img/logotocca.jpg" alt="Logotipo Tocca" style="height: 50px; margin-right: 15px;">
                        <div>
                            <h1 class="mb-0 fs-4">
                                <i class="fas fa-building me-2"></i> Empreendimentos Links
                            </h1>
                            <h3 class="mb-0 fs-4">
                                <i class="fas fa-building me-2"></i> <?php echo escape($empreendimento['NomeEmpreendimento']); ?>
                            </h3>
                            <h5 class="mb-0 fs-4">
                                <i class="fas fa-building me-2"></i> <?php echo escape($empreendimento['NomeEmpresa']); ?>
                            </h5>
                            <p class="mb-0 fs-6">SunnyImob</p>
                        </div>
                    </div>
                    <div>
                        <a href="#" class="btn btn-secondary" onclick="window.close()" style="background-color: #A0522D; border-color: #A0522D; color: white;">
                            <i class="fas fa-times me-1"></i> Fechar
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-<?php echo $_SESSION['tipo_mensagem']; ?> alert-dismissible fade show">
                <?php echo $_SESSION['mensagem']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php 
            unset($_SESSION['mensagem']);
            unset($_SESSION['tipo_mensagem']);
            ?>
        <?php endif; ?>
        
        <div class="d-flex justify-content-center">
            <div class="card" style="width: 18rem;">
                <img src="card/<?php echo $empreendimento['Empreend_ID']; ?>.jpg"
                    class="card-img-top ficha-imagem img-loading"
                    alt="<?php echo escape($empreendimento['NomeEmpreendimento']); ?>"
                    onload="this.classList.remove('img-loading')"
                    onerror="this.onerror=null;this.src='card/default.jpg';this.classList.add('img-fallback');this.classList.remove('img-loading')">
                <div class="card-body">
                    <h5 class="card-title"><?php echo escape($empreendimento['NomeEmpreendimento']); ?></h5>
                </div>
            </div>
        </div>
        
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="idEmpresa" value="<?php echo $empreendimento['Empresa_Id']; ?>">
            <input type="hidden" name="NomeEmpreend" value="<?php echo escape($empreendimento['NomeEmpreendimento']); ?>">
            
            <!-- Grupo 1: Links Principais -->
            <div class="link-group">
                <label class="form-label">1.x Latitude e Longitude (Copie a Latitude e Longitude no Google Maps e cole aqui.)</label>
                <input type="text" class="form-control mb-2" name="Localizacao"
                       id="localizacaoInput" value="<?php echo escape($empreendimento['Localizacao']); ?>"
                       maxlength="255"
                       style="background-color: #ffe6f0;">
                <button type="button" class="btn btn-primary" onclick="openGoogleMaps()">Abrir no Google Maps</button>
            </div>
            
            <div class="link-group">
                <label class="form-label">2. Link do Mapa (Google Maps ou similar)</label>
                <input type="text" class="form-control mb-2" name="LinkMapa" 
                       value="<?php echo escape($empreendimento['LinkMapa']); ?>" 
                       maxlength="255"
                       style="background-color: #ffe6f0;">
            </div>
            
            <div class="link-group">
                <label class="form-label">3. Link da Ficha Técnica</label>
                <input type="text" class="form-control mb-2" name="LinkFicha" 
                       value="<?php echo escape($empreendimento['LinkFicha']); ?>" 
                       maxlength="255" style="background-color: #ffe6f0;">
            </div>
            
            <div class="link-group">
                <label class="form-label">4. Link do Site Oficial do Empreendimento</label>
                <input type="text" class="form-control mb-2" name="LinkSite" 
                       value="<?php echo escape($empreendimento['LinkSite']); ?>" 
                       maxlength="255" style="background-color: #ffe6f0;">
            </div>
            
            <div class="link-group">
                <label class="form-label">5. Link do Vídeo</label>
                <input type="text" class="form-control mb-2" name="LinkVideo" 
                       value="<?php echo escape($empreendimento['LinkVideo']); ?>" 
                       maxlength="255" style="background-color: #ffe6f0;">
            </div>
            
            <!-- Grupo 2: Links Específicos -->
            <div class="link-group">
                <label class="form-label">6. Link do Book</label>
                <input type="text" class="form-control mb-2" name="LinkTocca" 
                       value="<?php echo escape($empreendimento['LinkTocca']); ?>" 
                       maxlength="255" style="background-color: #ffe6f0;">
            </div>
            
            <div class="link-group">
                <label class="form-label">7. Link do Drive da Construtora</label>
                <input type="text" class="form-control mb-2" name="LinkConstrutora" 
                       value="<?php echo escape($empreendimento['LinkConstrutora']); ?>" 
                       maxlength="255" style="background-color: #ffe6f0;">
            </div>
            
            <div class="link-group">
                <label class="form-label">8. Link da Tabela de Valores</label>
                <input type="text" class="form-control mb-2" name="LinkTabela" 
                       value="<?php echo escape($empreendimento['LinkTabela']); ?>" 
                       maxlength="255" style="background-color: #ffe6f0;">
            </div>
            
            <div class="link-group">
                <label class="form-label">9. Link do Card (Imagem)</label>
                <input type="text" class="form-control mb-2" name="LinkCard" 
                       id="linkCardInput"
                       value="<?php echo escape($empreendimento['LinkCard']); ?>" 
                       maxlength="255" style="background-color: #ffe6f0;" oninput="atualizarImagemCard()">
                <div id="previewCard" class="mt-2">
                    <img id="previewImagemCard" src="" alt="Preview do Card" style="max-width: 200px; display: none;" class="img-thumbnail">
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <?php if (usuarioPodeEditar()): ?>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle"></i> Atualizar Links
                    </button>
                <?php endif; ?>
                <a href="javascript:window.close()" class="btn btn-secondary">Voltar</a>
            </div>
        </form>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Script do contador de sessão
    var sessionTimeout = <?php echo $sessionTimeout * 60; ?>;
    var warningTime = 5 * 60;
    var warningShown = false;
    var autoRefreshDone = false;
    var alertSound = document.getElementById('alertSound');
    
    function updateTimer() {
        var minutes = Math.floor(sessionTimeout / 60);
        var seconds = sessionTimeout % 60;
        var timeString = minutes.toString().padStart(2, '0') + ":" + seconds.toString().padStart(2, '0');
        document.getElementById('time').textContent = timeString;
        
        var timerElement = document.getElementById('sessionTimer');
        if(sessionTimeout <= warningTime) {
            timerElement.style.backgroundColor = '#fff3cd';
            timerElement.style.borderColor = '#ffc107';
        } else {
            timerElement.style.backgroundColor = '#f8f9fa';
            timerElement.style.borderColor = '#ddd';
        }
        
        checkSession();
        sessionTimeout--;
        
        if(sessionTimeout <= 0) {
            sessionExpired();
        } else {
            setTimeout(updateTimer, 1000);
        }
    }
    
    function checkSession() {
        if(sessionTimeout <= warningTime && !warningShown) {
            document.getElementById('warningTime').textContent = Math.ceil(sessionTimeout / 60);
            document.getElementById('sessionWarning').style.display = 'block';
            warningShown = true;
        }
        
        if(sessionTimeout <= warningTime && !autoRefreshDone) {
            clickUpdateButton();
            autoRefreshDone = true;
        }
    }
    
    function clickUpdateButton() {
        playAlertSound();
        var buttons = document.querySelectorAll('button.btn-primary');
        var updateButton = null;
        
        buttons.forEach(function(button) {
            if(button.textContent.trim() === "Atualizar Links") {
                updateButton = button;
            }
        });
        
        if(updateButton) {
            console.log('Salvando dados automaticamente...');
            var originalText = updateButton.innerHTML;
            updateButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvando...';
            updateButton.disabled = true;
            
            if(updateButton.form) {
                updateButton.form.submit();
            } else {
                updateButton.click();
            }
            
            setTimeout(function() {
                if(updateButton) {
                    updateButton.innerHTML = originalText;
                    updateButton.disabled = false;
                }
            }, 3000);
        } else {
            console.warn('Botão não encontrado.');
            backupSaveMethod();
        }
    }
    
    function playAlertSound() {
        try {
            alertSound.currentTime = 0;
            alertSound.play().catch(e => console.log('Não foi possível tocar o som:', e));
        } catch(e) {
            console.log('Erro ao reproduzir som:', e);
        }
    }
    
    function backupSaveMethod() {
        fetch('keepalive.php?autosave=1', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=autosave'
        }).then(response => {
            if(response.ok) {
                console.log('Dados salvos via método alternativo');
            }
        });
    }
    
    function extendSession() {
        playAlertSound();
        fetch('keepalive.php').then(response => {
            if(response.ok) {
                sessionTimeout = <?php echo $sessionTimeout * 60; ?>;
                warningShown = false;
                autoRefreshDone = false;
                document.getElementById('sessionWarning').style.display = 'none';
                console.log('Sessão renovada');
            }
        });
    }
    
    function sessionExpired() {
        playAlertSound();
        alert("Sua sessão expirou. Você será redirecionado.");
        window.location.href = "login_v3a.php?timeout=1";
    }
    
    function logout() {
        playAlertSound();
        if(confirm('Deseja realmente sair do sistema?')) {
            window.location.href = 'logout.php';
        }
    }
    
    // Iniciar o timer quando a página carregar
    document.addEventListener('DOMContentLoaded', function() {
        updateTimer();
        atualizarImagemCard(); // Inicializar preview da imagem
    });
    
    // Renovar sessão a cada interação do usuário
    document.addEventListener('click', function() {
        sessionTimeout = <?php echo $sessionTimeout * 60; ?>;
    });
    
    // Função para abrir Google Maps
    function openGoogleMaps() {
        var localizacao = document.getElementById('localizacaoInput').value;
        if (localizacao) {
            if (localizacao.startsWith("http")) {
                window.open(localizacao, '_blank');
            } else {
                window.open("https://www.google.com/maps/search/?api=1&query=" + localizacao, '_blank');
            }
        } else {
            alert("Por favor, insira a Latitude e Longitude.");
        }
    }
    
    // Função para atualizar preview da imagem do card
    function atualizarImagemCard() {
        var linkInput = document.getElementById('linkCardInput');
        var imagemPreview = document.getElementById('previewImagemCard');
        var novoLink = linkInput.value.trim();
        
        if (novoLink !== "") {
            imagemPreview.src = novoLink;
            imagemPreview.style.display = "block";
        } else {
            imagemPreview.src = "";
            imagemPreview.style.display = "none";
        }
    }
    </script>
</body>
</html>
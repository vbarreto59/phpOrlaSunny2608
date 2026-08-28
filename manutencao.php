<?php
// manutencao.php

// Iniciar sessão
session_start();

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');

// Função para verificar se está em manutenção
function estaEmManutencao() {
    $arquivoManutencao = dirname(__FILE__) . '/manut.txt';
    return file_exists($arquivoManutencao);
}

// Função para verificar se é o usuário admin
function eAdmin() {
    return isset($_SESSION['Usuario']) && strtolower($_SESSION['Usuario']) === 'barreto';
}

// Verificar se realmente está em manutenção
// (Opcional: redirecionar se não estiver em manutenção)
if (!estaEmManutencao() && !eAdmin()) {
    // Se não está em manutenção e não é admin, redirecionar para página principal
    header('Location: index.php');
    exit();
}

// Obter usuário logado para exibição
$usuarioLogado = isset($_SESSION['Usuario']) ? $_SESSION['Usuario'] : 'Visitante';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site em Manutenção</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            text-align: center; 
            padding: 50px; 
            background-color: #f8f9fa;
            margin: 0;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #d9534f; 
            margin-bottom: 20px;
            font-size: 2.5em;
        }
        p {
            color: #666;
            font-size: 1.1em;
            line-height: 1.6;
            margin: 15px 0;
        }
        .status-img {
            max-width: 200px;
            margin: 30px auto;
            filter: drop-shadow(0 3px 6px rgba(0,0,0,0.1));
        }
        .admin-panel { 
            margin-top: 40px; 
            padding: 25px; 
            background: #f8f9fa; 
            border-radius: 8px;
            border-left: 4px solid #007bff;
            text-align: left;
        }
        .admin-panel h3 {
            color: #007bff;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 10px 5px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-success { background-color: #28a745; }
        .btn-success:hover { background-color: #218838; }
        .btn-info { background-color: #17a2b8; }
        .btn-info:hover { background-color: #138496; }
        
        .user-info {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 12px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        
        .countdown {
            margin: 30px 0;
            padding: 20px;
            background: #f0f7ff;
            border-radius: 8px;
            border: 2px dashed #007bff;
        }
        .countdown-text {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        #timer {
            font-size: 2em;
            font-weight: bold;
            color: #d9534f;
            font-family: monospace;
        }
        
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
        }
        .contact-info a {
            color: #007bff;
            text-decoration: none;
        }
        .contact-info a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="user-info">
        Usuário: <strong><?php echo htmlspecialchars(strtoupper($usuarioLogado)); ?></strong>
        <?php if ($usuarioLogado !== 'Visitante'): ?>
        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i> Sair
        </button>
        <?php endif; ?>
    </div>
    
    <div class="container">
        <i class="fas fa-tools fa-5x" style="color: #d9534f; margin-bottom: 20px;"></i>
        <h1>Site em Manutenção</h1>
        
        <p>Nosso site está passando por manutenção programada.</p>
        <p>Estamos trabalhando para melhorar sua experiência.</p>
        <p>Por favor, volte mais tarde. Agradecemos sua compreensão.</p>
        
        <!-- Contador regressivo (opcional) -->
        <div class="countdown">
            <div class="countdown-text">Previsão de retorno:</div>
            <div id="timer">--:--:--</div>
        </div>
        
        <?php if (eAdmin()): ?>
        <div class="admin-panel">
            <h3><i class="fas fa-user-shield"></i> Painel de Administrador</h3>
            <p>Você está visualizando esta página como administrador.</p>
            <p><strong>Usuários comuns</strong> estão vendo apenas a mensagem de manutenção.</p>
            
            <div style="margin-top: 20px;">
                <a href="ManutencaoOff.php" class="btn btn-success" onclick="return confirm('Deseja desativar o modo manutenção?\\n\\nIsso permitirá que todos os usuários acessem o sistema normalmente.')">
                    <i class="fas fa-power-off"></i> Desativar Manutenção
                </a>
                
                <a href="dashboard.php" class="btn btn-info">
                    <i class="fas fa-tachometer-alt"></i> Painel de Controle
                </a>
                
                <a href="manutmenu.php" class="btn">
                    <i class="fas fa-cog"></i> Configurações
                </a>
            </div>
            
            <div style="margin-top: 15px; font-size: 12px; color: #666;">
                <p><strong>Informações:</strong></p>
                <ul style="text-align: left; margin: 10px 0; padding-left: 20px;">
                    <li>Arquivo de controle: <code>manut.txt</code></li>
                    <li>Status atual: <strong>EM MANUTENÇÃO</strong></li>
                    <li>IP de acesso: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></li>
                    <li>Data/hora: <?php echo date('d/m/Y H:i:s'); ?></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="contact-info">
            <p>Em caso de urgência, entre em contato:</p>
            <p><i class="fas fa-envelope"></i> suporte@empresa.com</p>
            <p><i class="fas fa-phone"></i> (11) 99999-9999</p>
        </div>
    </div>

    <script>
        // Função de logout
        function logout() {
            if (confirm('Deseja realmente sair?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Contador regressivo (exemplo - 2 horas)
        function startCountdown() {
            const targetTime = new Date();
            targetTime.setHours(targetTime.getHours() + 2); // +2 horas
            
            function updateTimer() {
                const now = new Date();
                const diff = targetTime - now;
                
                if (diff <= 0) {
                    document.getElementById('timer').textContent = '00:00:00';
                    clearInterval(timerInterval);
                    return;
                }
                
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                document.getElementById('timer').textContent = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
            
            updateTimer();
            const timerInterval = setInterval(updateTimer, 1000);
        }
        
        // Iniciar contador quando a página carregar
        document.addEventListener('DOMContentLoaded', startCountdown);
        
        // Auto-refresh para admin verificar status (a cada 30 segundos)
        <?php if (eAdmin()): ?>
        setInterval(function() {
            // Verificar se ainda está em manutenção
            fetch('check_maintenance.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.inMaintenance) {
                        if (confirm('O modo manutenção foi desativado. Deseja recarregar a página?')) {
                            window.location.reload();
                        }
                    }
                });
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// controle_manutencao.php

// Iniciar sessão
session_start();

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');

// Incluir arquivo de configuração
require_once 'manutencao_config.php';

// Verificar se é admin para acessar este menu
if (!eAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo "Acesso negado - Somente administradores";
    exit();
}

// Verificar se o sistema está em manutenção
$emManutencao = estaEmManutencao();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Manutenção</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            text-align: center; 
            padding: 20px; 
            background-color: #f8f9fa;
        }
        .container { 
            max-width: 500px; 
            margin: 0 auto; 
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-panel { 
            padding: 20px; 
            margin: 20px 0; 
            border-radius: 5px; 
        }
        .manutencao-on { 
            background-color: #f8d7da; 
            border: 1px solid #f5c6cb; 
            color: #721c24;
        }
        .manutencao-off { 
            background-color: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724;
        }
        .btn { 
            padding: 12px 20px; 
            margin: 10px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-on { 
            background-color: #dc3545; 
            color: white; 
        }
        .btn-off { 
            background-color: #28a745; 
            color: white; 
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .status-img { 
            max-width: 120px; 
            margin: 20px auto; 
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        h2 {
            color: #343a40;
            margin-bottom: 25px;
        }
        .footer-info {
            margin-top: 30px; 
            font-size: 13px; 
            color: #6c757d;
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-tools"></i> Controle de Manutenção do Sistema</h2>
        
        <div class="status-panel <?php echo $emManutencao ? 'manutencao-on' : 'manutencao-off'; ?>">
            <?php if ($emManutencao): ?>
                <!-- Use ícone do Font Awesome -->
                <i class="fas fa-exclamation-triangle fa-5x" style="color: #dc3545; margin-bottom: 20px;"></i>
                <h3 style="color: #dc3545;">SISTEMA EM MANUTENÇÃO</h3>
                <p>Os usuários comuns estão sendo redirecionados para a página de manutenção.</p>
            <?php else: ?>
                <!-- Use ícone do Font Awesome -->
                <i class="fas fa-check-circle fa-5x" style="color: #28a745; margin-bottom: 20px;"></i>
                <h3 style="color: #28a745;">SISTEMA OPERACIONAL</h3>
                <p>Todos os usuários podem acessar o sistema normalmente.</p>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="ManutencaoOn.php" class="btn btn-on" onclick="return confirm('Tem certeza que deseja ativar o modo manutenção?\\n\\nOs usuários comuns não poderão acessar o sistema.')">
                <i class="fas fa-power-off"></i> Ativar Modo Manutenção
            </a>
            
            <a href="ManutencaoOff.php" class="btn btn-off" onclick="return confirm('Tem certeza que deseja desativar o modo manutenção?\\n\\nTodos os usuários poderão acessar o sistema normalmente.')">
                <i class="fas fa-check-circle"></i> Desativar Modo Manutenção
            </a>
            
            <br><br>
            <a href="menu.php" class="btn btn-warning">
                <i class="fas fa-arrow-left"></i> Voltar ao Menu
            </a>
        </div>
        
        <div class="footer-info">
            <p>Usuário logado: <strong><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Desconhecido'); ?></strong></p>
            <p>IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></p>
            <p>Última verificação: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Confirmar ações
        document.addEventListener('DOMContentLoaded', function() {
            var btnOn = document.querySelector('.btn-on');
            var btnOff = document.querySelector('.btn-off');
            
            if (btnOn) {
                btnOn.addEventListener('click', function(e) {
                    if (!confirm('ATENÇÃO: Ativar o modo manutenção irá bloquear o acesso de todos os usuários comuns.\n\nContinuar?')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (btnOff) {
                btnOff.addEventListener('click', function(e) {
                    if (!confirm('Deseja desativar o modo manutenção?\n\nIsso permitirá que todos os usuários acessem o sistema normalmente.\n\nContinuar?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php
/**
 * CONFIGURAÇÕES DO SISTEMA EM MANUTENÇÃO
 */
define('NOME_SISTEMA', 'SunnyImob'); // Nome que aparecerá no título
define('URL_LOGIN', 'login_p1.php');      // Caminho para a página de login
define('SISTEMA_ID', 'sunny');        // ID usado no arquivo .txt (ex: gabnet, todolist)

// Caminho para verificar se a manutenção ainda está ativa
$arquivo_manut = $_SERVER['DOCUMENT_ROOT'] . "/manut/manut_" . SISTEMA_ID . ".txt";
$em_manutencao = file_exists($arquivo_manut);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenção - <?php echo NOME_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .maint-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 500px;
            text-align: center;
            border-top: 5px solid #ff9800;
        }
        .icon-box {
            font-size: 4rem;
            color: #ff9800;
            margin-bottom: 20px;
            animation: gear-rotate 4s linear infinite;
        }
        @keyframes gear-rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .system-name {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 15px;
            border-radius: 50px;
            background: #fff3e0;
            color: #e65100;
            font-weight: 600;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="maint-card">
    <div class="icon-box">
        <i class="bi bi-gear-fill"></i>
    </div>
    
    <span class="status-badge">Acesso Restrito</span>
    <h2 class="system-name mt-3"><?php echo NOME_SISTEMA; ?></h2>
    
    <div class="my-4">
        <h4 class="text-dark">Estamos em Manutenção</h4>
        <p class="text-muted">
            Desculpe o transtorno! Estamos realizando atualizações importantes para melhorar sua experiência. Voltaremos em breve.
        </p>
    </div>

    <hr>

    <?php if ($em_manutencao): ?>
        <p class="small text-muted mb-4">
            <i class="bi bi-clock-history"></i> Status: Aguardando liberação do administrador.
        </p>

    <?php else: ?>
        <div class="alert alert-success border-0 small">
            <i class="bi bi-check-circle-fill"></i> O sistema já está online novamente!
        </div>
        <a href="<?php echo URL_LOGIN; ?>" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
            Ir para o Login <i class="bi bi-arrow-right"></i>
        </a>
    <?php endif; ?>

    <div class="mt-4">
        <a href="login_p1.php" class="text-decoration-none small text-secondary">
            <i class="bi bi-house"></i> Voltar ao Portal
        </a>
    </div>
</div>

</body>
</html>
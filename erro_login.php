<?php
// erro_login.php
// Página de erro para conta inativa - com logo adicionado
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conta Inativa - SunnyImob</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8d7da 0%, #ffebee 100%);
            color: #721c24;
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .card {
            max-width: 500px;
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(220,53,69,0.25);
        }
        .card-header {
            background: #dc3545;
            color: white;
            padding: 2rem 1.5rem;
        }
        .logo-container {
            text-align: center;
            padding: 2rem 1rem 1rem;
            background: white;
        }
        .logo-error {
            max-width: 70%;
            max-height: 140px;
            height: auto;
        }
        .card-body {
            padding: 2rem;
            background: white;
        }
        .btn-outline-danger {
            padding: 0.75rem 2rem;
            font-weight: 500;
        }
        .icon-big {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            color: #dc3545;
        }
    </style>
</head>
<body>

    <div class="card shadow-lg">
        <!-- Logo no topo -->
        <div class="logo-container">
            <img src="img/logotocca.jpg" alt="Logo SunnyImob" class="logo-error">
        </div>

        <div class="card-header">
            <h3>Erro de Acesso</h3>
        </div>

        <div class="card-body">
            <i class="fas fa-exclamation-triangle icon-big"></i>
            
            <p class="lead mb-4">
                Seu usuário está sem acesso no momento.
            </p>
            
            <p>
                Por favor, entre em contato com a administração da Tocca Onze.
            </p>

            <hr class="my-4">

            <a href="login_p1.php" class="btn btn-outline-danger btn-lg">
                <i class="fas fa-arrow-left me-2"></i> Voltar ao Login
            </a>
        </div>

        <div class="card-footer text-muted small text-center py-3">
            SunnyImob • 2026
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SunnyImob2026 | Bem-vindo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .welcome-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .btn-login {
            background-color: #007bff;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: scale(1.05);
            background-color: #0056b3;
        }
        .logo-placeholder {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

    <div class="welcome-card">
        <div class="logo-placeholder">
            ☀️ SunnyImob
        </div>
        <h2 class="mb-3">Bem-vindo</h2>
        <p class="text-muted mb-4">Acesse o portal imobiliário para gerenciar suas vendas e empreendimentos.</p>
        
        <a href="login_p1.php" class="btn btn-primary btn-login w-100">
            Ir para o Login
        </a>
        
        <div class="mt-4 small text-muted">
            &copy; 2026 SunnyImob - Sistema de Gestão
        </div>
    </div>

</body>
</html>
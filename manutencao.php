<?php
// manutencao.php

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema em Manutenção</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background-color: #f8f9fa;
        }
        .maintenance-card {
            max-width: 500px;
            width: 100%;
            background-color: white;
            padding: 40px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100 m-0">
    <div class="maintenance-card text-center">
        <div class="mb-4">
            <i class="fas fa-tools fa-4x text-warning"></i>
        </div>
        
        <h1 class="h3 text-dark fw-bold mb-3">Sistema em Manutenção</h1>
        
        <p class="text-muted mb-4">
            No momento, nosso sistema está passando por atualizações e melhorias estruturais para garantir um melhor atendimento. Por favor, tente novamente mais tarde.
        </p>
        
        <hr class="my-4">
        

    </div>
</body>
</html>
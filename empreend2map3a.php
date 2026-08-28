<?php
// empreend2map.php - Processamento dos empreendimentos selecionados
session_start();

// Verificar login
if (empty($_SESSION['Usuario'])) {
    header('Location: login2.php');
    exit();
}

require_once 'conn.php';

// Obter IDs dos empreendimentos selecionados
$empreendimentosSelecionados = $_POST['empreendimentos_selecionados'] ?? '';

if (empty($empreendimentosSelecionados)) {
    die('Nenhum empreendimento selecionado.');
}

// Converter string de IDs em array
$idsArray = explode(',', $empreendimentosSelecionados);
$idsArray = array_filter($idsArray, 'is_numeric'); // Filtrar apenas números

if (empty($idsArray)) {
    die('IDs inválidos.');
}

// Criar placeholders para a query
$placeholders = str_repeat('?,', count($idsArray) - 1) . '?';

try {
    $conn = GetOpenConnectionLog();
    
    // Buscar detalhes dos empreendimentos selecionados
    $sql = "SELECT e.*, emp.NomeEmpresa, emp.LinkDrive1, emp.LinkDrive2, 
                   emp.LinkSite1, emp.LinkSite2, emp.Mapa1 
            FROM empreendimento e 
            INNER JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
            WHERE e.Empreend_ID IN ($placeholders) 
            ORDER BY e.Localidade, e.NomeEmpreendimento";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($idsArray);
    $empreendimentos = $stmt->fetchAll();
    
    if (empty($empreendimentos)) {
        die('Nenhum empreendimento encontrado com os IDs fornecidos.');
    }
    
    // Aqui você pode gerar o PDF ou mapa
    // Por enquanto, vamos apenas mostrar os dados
    header('Content-Type: text/html; charset=UTF-8');
    
} catch (PDOException $e) {
    die("Erro ao processar empreendimentos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Empreendimentos Selecionados</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        h2 { color: #2c3e50; }
        .empreend-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-list"></i> Empreendimentos Selecionados (<?php echo count($empreendimentos); ?>)</h2>
        <p>Empreendimentos selecionados para geração do mapa/relatório.</p>
        <hr>
        
        <?php foreach ($empreendimentos as $emp): ?>
        <div class="empreend-card">
            <h4><?php echo htmlspecialchars($emp['NomeEmpreendimento']); ?></h4>
            <p><strong>Localidade:</strong> <?php echo htmlspecialchars($emp['Localidade']); ?></p>
            <p><strong>Empresa:</strong> <?php echo htmlspecialchars($emp['NomeEmpresa']); ?></p>
            <p><strong>Metragem:</strong> 
                <?php if ($emp['MetragemMin'] > 0): ?>
                    <?php echo $emp['MetragemMin']; ?>m²
                    <?php if ($emp['MetragemMax'] > 0): ?>
                        a <?php echo $emp['MetragemMax']; ?>m²
                    <?php endif; ?>
                <?php endif; ?>
            </p>
            <p><strong>Preço:</strong> 
                <?php if ($emp['MenorPreco'] > 0): ?>
                    R$ <?php echo number_format($emp['MenorPreco'], 2, ',', '.'); ?>
                    <?php if ($emp['MaiorPreco'] > 0): ?>
                        a R$ <?php echo number_format($emp['MaiorPreco'], 2, ',', '.'); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <?php endforeach; ?>
        
        <div class="mt-4">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <a href="selecao_empreendimentos.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</body>
</html>
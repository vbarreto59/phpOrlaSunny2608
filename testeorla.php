<?php
require_once 'conn.php';

try {
    $sql = "SELECT Id, NomeEmpreendimento, NomeCliente, Unidade, DataVenda FROM vendas ORDER BY Id DESC LIMIT 10";
    $stmt = $pdo->query($sql);
    $vendas = $stmt->fetchAll();

    echo "<h2 style='color: green;'>✔ Conexão e consulta realizadas com sucesso!</h2>";
    echo "<h3>Últimas 10 Vendas:</h3>";
    
    if (count($vendas) > 0) {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; font-family: Arial;'>";
        echo "<tr style='background-color: #f2f2f2;'>
                <th>ID</th>
                <th>Empreendimento</th>
                <th>Cliente</th>
                <th>Unidade</th>
                <th>Data da Venda</th>
              </tr>";
        
        foreach ($vendas as $v) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($v['Id']) . "</td>";
            echo "<td>" . htmlspecialchars($v['NomeEmpreendimento']) . "</td>";
            echo "<td>" . htmlspecialchars($v['NomeCliente']) . "</td>";
            echo "<td>" . htmlspecialchars($v['Unidade']) . "</td>";
            echo "<td>" . htmlspecialchars($v['DataVenda']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum registro encontrado na tabela vendas.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro na consulta: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
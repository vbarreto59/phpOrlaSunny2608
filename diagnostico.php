<?php
// diagnostico_distanciamar.php — acesse pelo navegador
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== DIAGNÓSTICO DISTÂNCIA DO MAR ===\n\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Dir: " . __DIR__ . "\n\n";

// conn.php
echo "--- conn.php ---\n";
if (!file_exists(__DIR__ . '/conn.php')) {
    echo "❌ NÃO ENCONTRADO\n";
    exit;
}
echo "✓ Existe\n";
require_once __DIR__ . '/conn.php';

$pdoCon = null;
if (isset($conn) && $conn instanceof PDO)     { $pdoCon = $conn; echo "✓ \$conn é PDO\n"; }
elseif (isset($pdo) && $pdo instanceof PDO)   { $pdoCon = $pdo;  echo "✓ \$pdo é PDO\n"; }
elseif (isset($db) && $db instanceof PDO)     { $pdoCon = $db;   echo "✓ \$db é PDO\n"; }
else {
    echo "❌ Nenhum PDO. Variáveis definidas: ";
    echo implode(', ', array_keys(get_defined_vars())) . "\n";
    exit;
}

// Colunas da tabela
echo "\n--- Colunas de empreendimento ---\n";
try {
    foreach ($pdoCon->query("SHOW COLUMNS FROM empreendimento") as $c) {
        printf("  %-30s %s\n", $c['Field'], $c['Type']);
    }
} catch (Throwable $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

// Colunas necessárias
echo "\n--- Verificação de colunas ---\n";
try {
    $cols = [];
    foreach ($pdoCon->query("SHOW COLUMNS FROM empreendimento") as $r) {
        $cols[] = strtolower($r['Field']);
    }
    foreach (['distanciamar', 'beiramar', 'proximomardetalhe'] as $need) {
        echo (in_array($need, $cols, true) ? "✓ " : "❌ ") . "$need\n";
    }
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// Log de erros do endpoint
echo "\n--- erros_distanciamar.log ---\n";
$log = __DIR__ . '/erros_distanciamar.log';
echo file_exists($log) ? file_get_contents($log) : "(vazio/não existe)\n";
<?php
// teste_moeda.php
require_once 'conn.php';

// ID para teste (ajuste se necessário)
$id = isset($_GET['id']) ? intval($_GET['id']) : 1; 

$sqlExecutado = null;
$parametrosExecutados = null;
$msg = '';

// =========================================================================
// FUNÇÃO DE PARSING BLINDADA (A MESMA DO SISTEMA)
// =========================================================================
function parseValorMonetario($valorRecebido, $valorAntigo) {
    if ($valorRecebido === null || trim((string)$valorRecebido) === '') {
        return $valorAntigo;
    }
    $valor = trim((string)$valorRecebido);

    // 1. O ideal: jquery-maskMoney envia "500000.00"
    if (is_numeric($valor)) {
        return (float)$valor;
    }

    // 2. Fallback: Formato BR "500.000,00"
    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        if (is_numeric($valor)) return (float)$valor;
    }

    // 3. Fallback: "500.000" (milhar BR sem centavos)
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
        $valor = str_replace('.', '', $valor);
        if (is_numeric($valor)) return (float)$valor;
    }

    // 4. Se falhar, mantém o valor antigo para não corromper o banco
    return $valorAntigo;
}

// =========================================================================
// BUSCAR DADOS ATUAIS
// =========================================================================
try {
    $stmt = $pdo->prepare("SELECT Empreend_ID, NomeEmpreendimento, ValorCond, ValorIPTU, MenorPreco, MaiorPreco FROM empreendimento WHERE Empreend_ID = :id");
    $stmt->execute([':id' => $id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        die("Empreendimento ID $id não encontrado.");
    }
} catch(PDOException $e) {
    die("Erro: " . $e->getMessage());
}

// =========================================================================
// PROCESSAR POST (SALVAR)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cond  = parseValorMonetario($_POST['ValorCond'] ?? '', $dados['ValorCond']);
    $iptu  = parseValorMonetario($_POST['ValorIPTU'] ?? '', $dados['ValorIPTU']);
    $menor = parseValorMonetario($_POST['MenorPreco'] ?? '', $dados['MenorPreco']);
    $maior = parseValorMonetario($_POST['MaiorPreco'] ?? '', $dados['MaiorPreco']);

    $sql = "UPDATE empreendimento SET ValorCond = :cond, ValorIPTU = :iptu, MenorPreco = :menor, MaiorPreco = :maior WHERE Empreend_ID = :id";
    $params = [
        ':cond'  => $cond,
        ':iptu'  => $iptu,
        ':menor' => $menor,
        ':maior' => $maior,
        ':id'    => $id
    ];

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $sqlExecutado = $sql;
        $parametrosExecutados = $params;
        $msg = "✅ Dados salvos com sucesso!";

        // Recarrega os dados para mostrar na tabela abaixo
        $stmt = $pdo->prepare("SELECT Empreend_ID, NomeEmpreendimento, ValorCond, ValorIPTU, MenorPreco, MaiorPreco FROM empreendimento WHERE Empreend_ID = :id");
        $stmt->execute([':id' => $id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $msg = "❌ Erro ao salvar: " . $e->getMessage();
    }
}

// Formatação para exibição inicial no input
function formatarInput($valor) {
    if (empty($valor) || !is_numeric($valor)) return '0,00';
    return number_format((float)$valor, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Teste de Valores Monetários</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light py-5">

<div class="container">
    <h2 class="mb-4">Teste de Valores Monetários (ID: <?= $id ?>)</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info fw-bold fs-5"><?= $msg ?></div>
    <?php endif; ?>

    <!-- ============================= -->
    <!-- FORMULÁRIO DE TESTE -->
    <!-- ============================= -->
    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <h5 class="card-title text-muted mb-4">Insira os valores abaixo:</h5>
            <form method="post">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Valor Condomínio</label>
                        <input type="text" class="form-control money" name="ValorCond" value="<?= formatarInput($dados['ValorCond']) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Valor IPTU</label>
                        <input type="text" class="form-control money" name="ValorIPTU" value="<?= formatarInput($dados['ValorIPTU']) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Menor Preço</label>
                        <input type="text" class="form-control money" name="MenorPreco" value="<?= formatarInput($dados['MenorPreco']) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Maior Preço</label>
                        <input type="text" class="form-control money" name="MaiorPreco" value="<?= formatarInput($dados['MaiorPreco']) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary px-5">Salvar e Ver SQL</button>
            </form>
        </div>
    </div>

    <!-- ============================= -->
    <!-- RETORNO DO SQL -->
    <!-- ============================= -->
    <?php if ($sqlExecutado): ?>
        <div class="card shadow-sm mb-5 border-success">
            <div class="card-header bg-success text-white fw-bold">
                O que foi enviado ao banco de dados
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Query preparada:</strong></p>
                <pre class="bg-dark text-light p-3 rounded"><code><?= htmlspecialchars($sqlExecutado) ?></code></pre>
                
                <p class="mb-1 mt-3"><strong>Parâmetros (Bind Values):</strong></p>
                <pre class="bg-dark text-light p-3 rounded"><?php print_r($parametrosExecutados); ?></pre>
                <small class="text-muted">Nota: Os parâmetros acima são exatamente os valores que o PHP enviou para o MySQL, no formato numérico. Se estiverem <code>500000</code>, o banco recebeu 500 mil corretamente.</small>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================= -->
    <!-- LEITURA DO BANCO -->
    <!-- ============================= -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">
            O que está gravado no banco de dados agora
        </div>
        <div class="card-body">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Campo</th>
                        <th>Valor bruto no banco (string retornada pelo MySQL)</th>
                        <th>Formatado em Reais (R$)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>ValorCond</td>
                        <td><code><?= htmlspecialchars((string)$dados['ValorCond']) ?></code></td>
                        <td>R$ <?= formatarInput($dados['ValorCond']) ?></td>
                    </tr>
                    <tr>
                        <td>ValorIPTU</td>
                        <td><code><?= htmlspecialchars((string)$dados['ValorIPTU']) ?></code></td>
                        <td>R$ <?= formatarInput($dados['ValorIPTU']) ?></td>
                    </tr>
                    <tr>
                        <td>MenorPreco</td>
                        <td><code><?= htmlspecialchars((string)$dados['MenorPreco']) ?></code></td>
                        <td>R$ <?= formatarInput($dados['MenorPreco']) ?></td>
                    </tr>
                    <tr>
                        <td>MaiorPreco</td>
                        <td><code><?= htmlspecialchars((string)$dados['MaiorPreco']) ?></code></td>
                        <td>R$ <?= formatarInput($dados['MaiorPreco']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<script>
$(document).ready(function() {
    // Aplica a máscara
    $('.money').maskMoney({
        thousands: '.', 
        decimal: ',', 
        precision: 2, 
        allowZero: true
    });

    // No envio, extrai o float puro usando a API oficial (unmasked)
    $('form').on('submit', function() {
        $('.money').each(function() {
            var unmasked = $(this).maskMoney('unmasked')[0];
            var finalValue = isNaN(unmasked) ? '' : unmasked.toFixed(2);
            $(this).val(finalValue); // Ex: envia "500000.00"
            console.log('Valor enviado no POST:', finalValue);
        });
    });
});
</script>
</body>
</html>
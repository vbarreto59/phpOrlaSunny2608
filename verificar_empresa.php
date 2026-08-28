<?php
// ######################################
// DIAGNÓSTICO: Vendas com NomeEmpresa em branco
// Inclui o nome do empreendimento na listagem
// ######################################

session_start();

if (!isset($_SESSION['Usuario']) || empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit;
}

require_once "conn.php";

try {
    $conn = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta principal com o nome do empreendimento mapeado corretamente
    $sql = "
        SELECT 
            v.*,
            emp.Empreend_ID,
            emp.Empresa_ID          AS Empresa_ID_do_Empreendimento,
            emp.NomeEmpresa         AS NomeEmpresa_do_Empreendimento,
            emp.NomeEmpreendimento  AS NomeEmpreendimento_Tabela,
            e.NomeEmpresa           AS NomeEmpresa_Tabela_Empresa
        FROM vendas v
        LEFT JOIN empreendimento emp ON (
            emp.NomeEmpreendimento = v.NomeEmpreendimento
            OR emp.Empreend_ID = v.Empreend_Id
        )
        LEFT JOIN empresa e ON (
            e.Empresa_ID = emp.Empresa_ID 
            OR e.Empresa_ID = v.Empresa_Id
        )
        WHERE v.Excluido = 0
          AND (v.NomeEmpresa IS NULL OR TRIM(v.NomeEmpresa) = '')
        ORDER BY v.DataVenda DESC
        LIMIT 500
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<h3>Erro na consulta:</h3><pre>" . $e->getMessage() . "</pre>");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico - Empresas em Branco</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6fa; padding: 25px; font-size: 13.5px; }
        .table th { background-color: #2c3e50; color: white; white-space: nowrap; }
        .badge-vazio { background-color: #e74c3c; }
        .badge-ok { background-color: #27ae60; }
        .table td { vertical-align: middle; }
        .destaque { background-color: #fff3cd !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">Vendas com <span class="text-danger">NomeEmpresa</span> em branco</h4>
                <small class="text-muted">Relacionamento via vendas.Empreend_Id e vendas.Empresa_Id</small>
            </div>
            <a href="gestao_vendas_diretoria3.php" class="btn btn-secondary btn-sm">← Voltar</a>
        </div>

        <?php if (empty($vendas)) : ?>
            <div class="alert alert-success">Nenhuma venda com NomeEmpresa em branco encontrada.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>ID Venda</th>
                            <th>vendas.Empresa_Id</th>
                            <th>vendas.Empreend_Id</th>
                            <th>Nome do Empreendimento</th>
                            <th>Empresa Sugerida (Nome)</th>
                            <th>Empresa_ID (Localizado)</th>
                            <th>Data Venda</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendas as $v) : 
                            $idVenda = $v['Id'] ?? $v['ID'] ?? $v['id'] ?? '—';
                            $empresaSugerida = $v['NomeEmpresa_Tabela_Empresa'] ?? $v['NomeEmpresa_do_Empreendimento'] ?? null;
                            $empresaIdLocalizado = $v['Empresa_ID_do_Empreendimento'] ?? $v['Empresa_Id'] ?? null;
                            $nomeEmpreendimento = $v['NomeEmpreendimento'] ?? $v['NomeEmpreendimento_Tabela'] ?? '—';
                        ?>
                            <tr class="<?= $empresaSugerida ? 'destaque' : '' ?>">
                                <td><strong><?= htmlspecialchars($idVenda) ?></strong></td>
                                <td><?= htmlspecialchars($v['Empresa_Id'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($v['Empreend_Id'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($nomeEmpreendimento) ?></td>
                                <td>
                                    <?php if ($empresaSugerida) : ?>
                                        <span class="badge badge-ok"><?= htmlspecialchars($empresaSugerida) ?></span>
                                    <?php else : ?>
                                        <span class="text-muted">Não encontrado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($empresaIdLocalizado ?? '—') ?></td>
                                <td><?= htmlspecialchars($v['DataVenda'] ?? '') ?></td>
                                <td>R$ <?= number_format(floatval($v['ValorUnidade'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
require_once 'conn.php';

try {
    $sql = "SELECT 
                u.UserId, 
                u.Nome AS NomeUsuario, 
                u.Acoes,
                d.NomeDiretoria, 
                g.NomeGerencia
            FROM usuarios u
            LEFT JOIN diretorias d ON u.UserId = d.UserId
            LEFT JOIN gerencias g ON u.UserId = g.UserId
            WHERE u.Ativo = 1 
              AND u.Grupo = 'TOCCA_ONZE'
            ORDER BY u.Nome ASC";

    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Array de controle para garantir que o ID seja usado apenas UMA VEZ no relatório todo
    $controleIdsGeral = array_unique(array_column($usuarios, 'UserId'));

    $listagem = [
        'Gestores'    => [],
        'Diretores'   => [],
        'Gerentes'    => [],
        'Corretores'  => []
    ];

    foreach ($usuarios as $row) {
        $idAtual = $row['UserId'];

        // Se o ID já foi removido do controle, ignora este registro completamente
        if (!in_array($idAtual, $controleIdsGeral)) {
            continue; 
        }

        $nomeExibicao = mb_strtoupper($row['NomeUsuario']) . " (" . $idAtual . ")";
        $alocado = false;

        // --- DEFINIÇÃO DE PRIORIDADE ---
        // Se o Maik deve aparecer como DIRETOR em vez de GESTOR, 
        // a verificação de Diretores deve vir antes.

        // 1. PRIORIDADE MÁXIMA: DIRETORES
        if (!empty($row['NomeDiretoria'])) {
            $listagem['Diretores'][] = $nomeExibicao . " - " . $row['NomeDiretoria'];
            $alocado = true;
        }

        // 2. SEGUNDA PRIORIDADE: GERENTES (Só entra se não for Diretor)
        if (!$alocado && !empty($row['NomeGerencia'])) {
            $listagem['Gerentes'][] = $nomeExibicao . " - " . $row['NomeGerencia'];
            $alocado = true;
        }

        // 3. TERCEIRA PRIORIDADE: GESTORES (Baseado no campo Acoes - Só entra se não for Diretor/Gerente)
        if (!$alocado && stripos($row['Acoes'], 'GESTOR') !== false) {
            $listagem['Gestores'][] = $nomeExibicao;
            $alocado = true;
        }

        // 4. ÚLTIMA OPÇÃO: CORRETORES
        if (!$alocado) {
            $listagem['Corretores'][] = $nomeExibicao;
            $alocado = true;
        }

        // UMA VEZ LISTADO, REMOVE DO CONTROLE PARA NÃO REPETIR EM NENHUMA OUTRA COLUNA
        if ($alocado) {
            $chave = array_search($idAtual, $controleIdsGeral);
            if ($chave !== false) unset($controleIdsGeral[$chave]);
        }
    }

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Listagem de Usuários com Licença</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 8px; }
        .card-header { background: #fff; font-weight: bold; border-bottom: 1px solid #f0f0f0; }
        .badge-count { background: #0d6efd; float: right; }
    </style>
</head>
<body>

<div class="container py-5">
    <h4 class="mb-4 fw-bold">Estrutura de Equipe Única (Sem Repetições)</h4>
    
    <div class="row">
        <?php 
        $somaLideranca = 0;
        foreach ($listagem as $cargo => $pessoas): 
            $qtd = count($pessoas);
            if ($cargo !== 'Corretores') $somaLideranca += $qtd;
        ?>
            <div class="col-md-3 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <?= strtoupper($cargo) ?>
                        <span class="badge rounded-pill badge-count"><?= $qtd ?></span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($pessoas as $p): ?>
                            <li class="list-group-item"><?= $p ?></li>
                        <?php endforeach; ?>
                        <?php if ($qtd == 0): ?>
                            <li class="list-group-item text-muted small">Nenhum registro</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card bg-primary text-white p-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <span>TOTAL LIDERANÇA</span>
                    <span class="h3 mb-0 fw-bold"><?= $somaLideranca ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-dark text-white p-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <span>TOTAL GERAL (PESSOAS)</span>
                    <span class="h3 mb-0 fw-bold text-success"><?= count($usuarios) - count($controleIdsGeral) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
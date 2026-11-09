<?php
require_once 'conn.php';

try {
    // Função auxiliar para normalizar nome (evitar duplicatas por nome)
    function normalizarNome($nome) {
        $nome = mb_strtoupper($nome, 'UTF-8');
        $nome = preg_replace('/\s+/', ' ', trim($nome));
        $nome = iconv('UTF-8', 'ASCII//TRANSLIT', $nome);
        return $nome;
    }

    // 1. Busca usuários
    $sql_usuarios = "
        SELECT
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
        ORDER BY u.Nome ASC
    ";
    $stmt = $pdo->query($sql_usuarios);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Desempenho total por usuário
    $sql_vendas = "
        SELECT
            u.UserId,
            COUNT(v.id) AS qtd_unidades,
            COALESCE(SUM(v.ValorUnidade), 0) AS vgv
        FROM usuarios u
        LEFT JOIN vendas v ON 
            v.UserIdDiretoria = u.UserId OR 
            v.UserIdGerencia  = u.UserId OR 
            v.CorretorId      = u.UserId
        WHERE u.Ativo = 1
          AND u.Grupo = 'TOCCA_ONZE'
          AND v.ValorUnidade IS NOT NULL
        GROUP BY u.UserId
    ";
    $stmt_vendas = $pdo->query($sql_vendas);
    $rows_vendas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);

    $dados_vendas = [];
    foreach ($rows_vendas as $row) {
        $uid = $row['UserId'];
        $dados_vendas[$uid] = [
            'qtd_unidades' => (int)$row['qtd_unidades'],
            'vgv'          => (float)$row['vgv']
        ];
    }

    // 3. Controles de duplicidade
    $controleIdsGeral = array_unique(array_column($usuarios, 'UserId'));
    $nomesJaUsados = [];

    $listagem = [
        'Diretores'  => [],
        'Gerentes'   => [],
        'Gestores'   => [],
        'Corretores' => [],
        'CorretoresSemVendas' => []  // Novo: lista separada para zerados
    ];

    foreach ($usuarios as $row) {
        $userId = $row['UserId'];
        $nomeNormalizado = normalizarNome($row['NomeUsuario']);

        if (!in_array($userId, $controleIdsGeral) || in_array($nomeNormalizado, $nomesJaUsados)) {
            continue;
        }

        $nomeExibicao = mb_strtoupper($row['NomeUsuario']) . " (ID: $userId)";

        $qtd = $dados_vendas[$userId]['qtd_unidades'] ?? 0;
        $vgv = $dados_vendas[$userId]['vgv']         ?? 0;
        $vgv_formatado = number_format($vgv, 2, ',', '.');

        $vendas_str = $qtd . " unid. | R$ " . $vgv_formatado;

        $alocado = false;

        // Prioridade: Diretor > Gerente > Gestor > Corretor
        if (!empty($row['NomeDiretoria'])) {
            $listagem['Diretores'][] = [
                'nome' => $nomeExibicao . " - " . $row['NomeDiretoria'],
                'vendas' => $vendas_str
            ];
            $alocado = true;
        }

        if (!$alocado && !empty($row['NomeGerencia'])) {
            $listagem['Gerentes'][] = [
                'nome' => $nomeExibicao . " - " . $row['NomeGerencia'],
                'vendas' => $vendas_str
            ];
            $alocado = true;
        }

        if (!$alocado && stripos($row['Acoes'], 'GESTOR') !== false) {
            $listagem['Gestores'][] = [
                'nome' => $nomeExibicao,
                'vendas' => $vendas_str
            ];
            $alocado = true;
        }

        if (!$alocado) {
            // Corretor: se tiver vendas → lista normal; se zerado → lista separada
            if ($qtd > 0 || $vgv > 0) {
                $listagem['Corretores'][] = [
                    'nome' => $nomeExibicao,
                    'vendas' => $vendas_str
                ];
            } else {
                $listagem['CorretoresSemVendas'][] = [
                    'nome' => $nomeExibicao,
                    'vendas' => $vendas_str
                ];
            }
            $alocado = true;
        }

        // Marca como usado
        if ($alocado) {
            $chave = array_search($userId, $controleIdsGeral);
            if ($chave !== false) unset($controleIdsGeral[$chave]);
            $nomesJaUsados[] = $nomeNormalizado;
        }
    }

} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrutura de Equipe + Desempenho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .card { border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 10px; }
        .card-header { background: #ffffff; font-weight: 600; border-bottom: 1px solid #e9ecef; }
        .badge-count { background: #0d6efd; float: right; }
        .list-group-item { padding: 0.75rem 1.25rem; }
        .nome-linha { font-weight: 500; margin-bottom: 0.25rem; display: block; line-height: 1.3; }
        .vendas-info { 
            font-size: 0.82rem; 
            color: #0d6efd; 
            font-weight: 600; 
            text-align: right; 
            display: block;
        }
        .zero-vendas .vendas-info { color: #6c757d; font-style: italic; }
        .sem-vendas-header {
            background-color: #f8d7da;
            color: #721c24;
            font-weight: bold;
            padding: 0.5rem;
            text-align: center;
            margin-top: 1rem;
            border-top: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <h4 class="mb-4 fw-bold text-center">Estrutura de Equipe ORLA IMOB + Desempenho (VGV e Unidades)</h4>

    <div class="row g-4">
        <?php
        $somaLideranca = 0;
        foreach ($listagem as $cargo => $pessoas):
            // Pula a seção de sem vendas aqui (vai ser renderizada dentro de Corretores)
            if ($cargo === 'CorretoresSemVendas') continue;

            $qtd = count($pessoas);
            if ($cargo !== 'Corretores') $somaLideranca += $qtd;
        ?>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <?= strtoupper($cargo) ?>
                        <span class="badge rounded-pill badge-count"><?= $qtd ?></span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($pessoas as $p): 
                            $classe_zero = (strpos($p['vendas'], '0 unid.') !== false) ? 'zero-vendas' : '';
                        ?>
                            <li class="list-group-item <?= $classe_zero ?>">
                                <div class="nome-linha"><?= htmlspecialchars($p['nome']) ?></div>
                                <div class="vendas-info"><?= htmlspecialchars($p['vendas']) ?></div>
                            </li>
                        <?php endforeach; ?>

                        <?php if ($cargo === 'Corretores'): ?>
                            <?php if (!empty($listagem['CorretoresSemVendas'])): ?>
                                <li class="sem-vendas-header">Corretores sem vendas registradas</li>
                                <?php foreach ($listagem['CorretoresSemVendas'] as $p_sem): ?>
                                    <li class="list-group-item zero-vendas">
                                        <div class="nome-linha"><?= htmlspecialchars($p_sem['nome']) ?></div>
                                        <div class="vendas-info"><?= htmlspecialchars($p_sem['vendas']) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($qtd === 0 && $cargo !== 'Corretores'): ?>
                            <li class="list-group-item text-muted small text-center">Nenhum registro</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row mt-5">
        <div class="col-md-6 mb-3">
            <div class="card bg-primary text-white p-4 shadow">
                <div class="d-flex justify-content-between align-items-center">
                    <span>TOTAL LIDERANÇA</span>
                    <span class="h2 mb-0 fw-bold"><?= $somaLideranca ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card bg-dark text-white p-4 shadow">
                <div class="d-flex justify-content-between align-items-center">
                    <span>TOTAL PESSOAS ÚNICAS</span>
                    <span class="h2 mb-0 fw-bold text-success"><?= count($usuarios) - count($controleIdsGeral) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
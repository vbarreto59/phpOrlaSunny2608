<?php
require_once 'conn.php';
require_once "usr_acoes.php";

// ============== Novo! - criado em 19 02 2026
protegerPagina(true);
// ===========================================

try {
    // Função auxiliar para normalizar nome (evitar duplicatas por nome)
    function normalizarNome($nome) {
        $nome = mb_strtoupper($nome, 'UTF-8');
        $nome = preg_replace('/\s+/', ' ', trim($nome));
        $nome = iconv('UTF-8', 'ASCII//TRANSLIT', $nome);
        return $nome;
    }

    // 1. Busca todos os usuários do grupo + campo Licenca
    $sql_usuarios = "
        SELECT
            u.UserId,
            u.Nome AS NomeUsuario,
            u.Acoes,
            u.Ativo,
            u.Licenca,
            d.NomeDiretoria,
            g.NomeGerencia
        FROM usuarios u
        LEFT JOIN diretorias d ON u.UserId = d.UserId
        LEFT JOIN gerencias g ON u.UserId = g.UserId
        WHERE u.Grupo = 'TOCCA_ONZE'
        ORDER BY u.Nome ASC
    ";
    $stmt = $pdo->query($sql_usuarios);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Desempenho (apenas vendas NÃO excluídas)
    $sql_vendas = "
        SELECT
            u.UserId,
            COUNT(v.id) AS qtd_unidades,
            COALESCE(SUM(v.ValorUnidade), 0) AS vgv
        FROM usuarios u
        LEFT JOIN vendas v ON
            v.UserIdDiretoria = u.UserId OR
            v.UserIdGerencia = u.UserId OR
            v.CorretorId = u.UserId
        WHERE u.Grupo = 'TOCCA_ONZE'
          AND v.ValorUnidade IS NOT NULL
          AND v.Excluido = 0
        GROUP BY u.UserId
    ";
    $stmt_vendas = $pdo->query($sql_vendas);
    $rows_vendas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
    $dados_vendas = [];
    foreach ($rows_vendas as $row) {
        $dados_vendas[$row['UserId']] = [
            'qtd_unidades' => (int)$row['qtd_unidades'],
            'vgv' => (float)$row['vgv']
        ];
    }

    // 3. Controles para evitar duplicatas
    $controleIdsGeral = array_unique(array_column($usuarios, 'UserId'));
    $nomesJaUsados = [];
    $listagem = [
        'Diretores' => [],
        'Gerentes' => [],
        'Gestores' => [],
        'Corretores' => [],
        'CorretoresSemVendas' => []
    ];

    foreach ($usuarios as $row) {
        $userId = $row['UserId'];
        $nomeNormalizado = normalizarNome($row['NomeUsuario']);

        if (!in_array($userId, $controleIdsGeral) || in_array($nomeNormalizado, $nomesJaUsados)) {
            continue;
        }

        $nomeExibicao = htmlspecialchars(mb_strtoupper($row['NomeUsuario']) . " (ID: $userId)", ENT_QUOTES, 'UTF-8');
        $qtd = $dados_vendas[$userId]['qtd_unidades'] ?? 0;
        $vgv = $dados_vendas[$userId]['vgv'] ?? 0;
        $vgv_formatado = number_format($vgv, 2, ',', '.');
        $vendas_str = $qtd . " unid. | R$ " . $vgv_formatado;

        $alocado = false;
        $entrada = [
            'nome' => $nomeExibicao,
            'funcao' => '',
            'vendas' => $vendas_str,
            'ativo' => (bool)$row['Ativo'],
            'licenca' => (bool)$row['Licenca'],
            'userId' => $userId
        ];

        if (!empty($row['NomeDiretoria'])) {
            $entrada['funcao'] = " - " . htmlspecialchars($row['NomeDiretoria'], ENT_QUOTES, 'UTF-8');
            $listagem['Diretores'][] = $entrada;
            $alocado = true;
        }

        if (!$alocado && !empty($row['NomeGerencia'])) {
            $entrada['funcao'] = " - " . htmlspecialchars($row['NomeGerencia'], ENT_QUOTES, 'UTF-8');
            $listagem['Gerentes'][] = $entrada;
            $alocado = true;
        }

        if (!$alocado && stripos($row['Acoes'], 'GESTOR') !== false) {
            $listagem['Gestores'][] = $entrada;
            $alocado = true;
        }

        if (!$alocado) {
            if ($qtd > 0 || $vgv > 0) {
                $listagem['Corretores'][] = $entrada;
            } else {
                $listagem['CorretoresSemVendas'][] = $entrada;
            }
            $alocado = true;
        }

        if ($alocado) {
            $chave = array_search($userId, $controleIdsGeral);
            if ($chave !== false) unset($controleIdsGeral[$chave]);
            $nomesJaUsados[] = $nomeNormalizado;
        }
    }

    // 4. Contagem de ATIVOS por coluna + total geral
    $contagemAtivos = [
        'Diretores' => 0,
        'Gerentes' => 0,
        'Gestores' => 0,
        'Corretores' => 0,
        'CorretoresSemVendas' => 0
    ];

    foreach ($listagem as $cargo => $pessoas) {
        foreach ($pessoas as $p) {
            if ($p['ativo']) {
                $contagemAtivos[$cargo]++;
            }
        }
    }

    $totalAtivos = array_sum($contagemAtivos);
    $somaLideranca = $contagemAtivos['Diretores'] + $contagemAtivos['Gerentes'] + $contagemAtivos['Gestores'];

} catch (PDOException $e) {
    die("Erro no banco de dados: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrutura de Equipe ORLA IMOB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .card { border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 10px; background: white; }
        .card-header { background: #ffffff; font-weight: 600; border-bottom: 1px solid #e9ecef; }
        .badge-count { float: right; }
        .list-group-item.ativo { background-color: #e6ffe6 !important; }
        .list-group-item.inativo { background-color: #ffe6e6 !important; }
        .list-group-item { padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 12px; }
        .nome-linha { font-weight: 500; flex-grow: 1; line-height: 1.3; display: flex; align-items: center; gap: 8px; }
        .vendas-info {
            font-size: 0.82rem;
            color: #0d6efd;
            font-weight: 600;
            white-space: nowrap;
            text-align: right;
            min-width: 140px;
        }
        .zero-vendas .vendas-info { color: #6c757d; font-style: italic; }
        .sem-vendas-header {
            background-color: #f8d7da;
            color: #721c24;
            font-weight: bold;
            padding: 0.5rem;
            text-align: center;
            margin: 1rem 0 0.5rem;
            border-top: 1px solid #f5c6cb;
        }
        .form-check-input:checked { background-color: #198754; border-color: #198754; }
        .contagem-ativos {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 1.5rem;
            margin-top: 3rem;
            border-radius: 8px;
        }
        .contagem-total {
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        .licenca-ok {
            color: #0d6efd;
            font-size: 0.7rem;
            font-weight: bold;
            line-height: 1;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <h4 class="mb-3 fw-bold text-center">Estrutura de Equipe ORLA IMOB</h4>

    <!-- Cards de contagem -->
    <div class="row g-3 justify-content-center mb-5">
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card text-center shadow-sm border-success">
                <div class="card-body py-3 px-2">
                    <h6 class="card-title mb-1 text-muted">Diretores</h6>
                    <p class="fs-3 fw-bold text-success mb-0"><?= $contagemAtivos['Diretores'] ?></p>
                </div>
            </div>
        </div>
        <!-- ... (demais cards de contagem mantidos iguais) ... -->
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card text-center shadow-sm border-primary bg-white">
                <div class="card-body py-3 px-2">
                    <h6 class="card-title mb-1 text-muted">Total Ativos</h6>
                    <p class="fs-3 fw-bold text-primary mb-0"><?= $totalAtivos ?></p>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="row g-4">
        <?php foreach (['Diretores', 'Gerentes', 'Gestores', 'Corretores'] as $cargo):
            $pessoas = $listagem[$cargo] ?? [];
            $qtd = count($pessoas);
        ?>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <?= strtoupper($cargo) ?>
                        <span class="badge rounded-pill bg-primary badge-count"><?= $qtd ?></span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($pessoas as $p):
                            $classe_status = $p['ativo'] ? 'ativo' : 'inativo';
                            $classe_zero = (strpos($p['vendas'], '0 unid.') !== false) ? 'zero-vendas' : '';
                        ?>
                            <li class="list-group-item <?= $classe_status ?> <?= $classe_zero ?>">
                                <div class="form-check">
                                    <input class="form-check-input toggle-ativo"
                                           type="checkbox"
                                           id="ativo_<?= $p['userId'] ?>"
                                           <?= $p['ativo'] ? 'checked' : '' ?>
                                           data-userid="<?= $p['userId'] ?>">
                                </div>
                                <div class="nome-linha">
                                    <?php if ($p['licenca']): ?>
                                        <span class="licenca-ok" title="Licença válida">🔵</span>
                                    <?php endif; ?>
                                    <?= $p['nome'] . $p['funcao'] ?>
                                </div>
                                <div class="vendas-info"><?= htmlspecialchars($p['vendas'], ENT_QUOTES, 'UTF-8') ?></div>
                            </li>
                        <?php endforeach; ?>

                        <?php if ($cargo === 'Corretores' && !empty($listagem['CorretoresSemVendas'])): ?>
                            <li class="sem-vendas-header">Corretores sem vendas registradas</li>
                            <?php foreach ($listagem['CorretoresSemVendas'] as $p_sem):
                                $classe_status = $p_sem['ativo'] ? 'ativo' : 'inativo';
                                $classe_zero = 'zero-vendas';
                            ?>
                                <li class="list-group-item <?= $classe_status ?> <?= $classe_zero ?>">
                                    <div class="form-check">
                                        <input class="form-check-input toggle-ativo"
                                               type="checkbox"
                                               id="ativo_<?= $p_sem['userId'] ?>"
                                               <?= $p_sem['ativo'] ? 'checked' : '' ?>
                                               data-userid="<?= $p_sem['userId'] ?>">
                                    </div>
                                    <div class="nome-linha">
                                        <?php if ($p_sem['licenca']): ?>
                                            <span class="licenca-ok" title="Licença válida">✔</span>
                                        <?php endif; ?>
                                        <?= $p_sem['nome'] ?>
                                    </div>
                                    <div class="vendas-info"><?= htmlspecialchars($p_sem['vendas'], ENT_QUOTES, 'UTF-8') ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($qtd === 0 && $cargo !== 'Corretores'): ?>
                            <li class="list-group-item text-muted small text-center">Nenhum registro</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php require_once 'footer.inc'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Script de toggle ativo - incluído exatamente como solicitado
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-ativo').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const userId = this.dataset.userid;
            const novoStatus = this.checked ? 1 : 0;
            const mensagem = this.checked
                ? "Deseja ATIVAR este usuário?"
                : "Deseja INATIVAR este usuário?";
            if (!confirm(mensagem)) {
                this.checked = !this.checked;
                return;
            }
            fetch('usr_atualiza_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `userId=${userId}&ativo=${novoStatus}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    alert("Erro ao atualizar: " + (data.error || "Resposta inválida"));
                    this.checked = !this.checked;
                } else {
                    const linha = this.closest('li');
                    if (novoStatus) {
                        linha.classList.remove('inativo');
                        linha.classList.add('ativo');
                    } else {
                        linha.classList.remove('ativo');
                        linha.classList.add('inativo');
                    }
                    console.log('Atualização OK para UserID:', userId);
                }
            })
            .catch(error => {
                console.error('Erro no fetch:', error);
                alert("Erro na comunicação com o servidor: " + error.message);
                this.checked = !this.checked;
            });
        });
    });
});
</script>
</body>
</html>
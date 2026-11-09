<?php
require_once 'conn.php';
require_once "usr_acoes.php";

// Proteção da página
protegerPagina(true);

// Número de licenças permitidas (os primeiros X nomes ativos ficam em azul)
$numeroLicencas = 20;

// Inicializa variáveis
$listagem = [
    'Diretores' => [], 'Gerentes' => [], 'Gestores' => [], 'Corretores' => []
];
$contagemAtivos = [
    'Diretores' => 0, 'Gerentes' => 0, 'Gestores' => 0, 'Corretores' => 0
];
$totalAtivos = 0;
$erro = null;

try {
    // Função auxiliar para normalizar nome
    function normalizarNome($nome) {
        $nome = mb_strtoupper($nome, 'UTF-8');
        $nome = preg_replace('/\s+/', ' ', trim($nome));
        $nome = iconv('UTF-8', 'ASCII//TRANSLIT', $nome);
        return $nome;
    }

    // 1. Busca todos os usuários
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

    // 2. Busca Desempenho
    $sql_vendas = "
        SELECT
            u.UserId,
            COUNT(v.id) AS qtd_unidades,
            COALESCE(SUM(v.ValorUnidade), 0) AS vgv
        FROM usuarios u
        LEFT JOIN vendas v ON
            (v.UserIdDiretoria = u.UserId OR v.UserIdGerencia = u.UserId OR v.CorretorId = u.UserId)
        WHERE u.Grupo = 'TOCCA_ONZE'
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

    // 3. Organização por Cargo
    $nomesJaUsados = [];
    foreach ($usuarios as $row) {
        $userId = $row['UserId'];
        $nomeNormalizado = normalizarNome($row['NomeUsuario']);
        if (in_array($nomeNormalizado, $nomesJaUsados)) continue;

        $qtd = $dados_vendas[$userId]['qtd_unidades'] ?? 0;
        $vgv = $dados_vendas[$userId]['vgv'] ?? 0;
        $vendas_str = $qtd . " unid. | R$ " . number_format($vgv, 2, ',', '.');

        $cargoAtual = 'Corretores';
        $funcao = '';
        if (!empty($row['NomeDiretoria'])) {
            $cargoAtual = 'Diretores';
            $funcao = " - " . htmlspecialchars($row['NomeDiretoria'], ENT_QUOTES, 'UTF-8');
        } elseif (!empty($row['NomeGerencia'])) {
            $cargoAtual = 'Gerentes';
            $funcao = " - " . htmlspecialchars($row['NomeGerencia'], ENT_QUOTES, 'UTF-8');
        } elseif (stripos($row['Acoes'] ?? '', 'GESTOR') !== false) {
            $cargoAtual = 'Gestores';
        }

        $listagem[$cargoAtual][] = [
            'userId' => $userId,
            'nome'   => htmlspecialchars(mb_strtoupper($row['NomeUsuario']), ENT_QUOTES, 'UTF-8') . " (ID: $userId)" . $funcao,
            'vendas' => $vendas_str,
            'qtd'    => $qtd,
            'ativo'  => (bool)$row['Ativo'],
            'licenca'=> (bool)$row['Licenca'],
            'numero' => null
        ];

        $nomesJaUsados[] = $nomeNormalizado;
    }

    // 4. Numeração sequencial + marcação das primeiras $numeroLicencas posições
    $contadorGlobalAtivo = 0;
    $ordemCargos = ['Diretores', 'Gerentes', 'Gestores', 'Corretores'];
    foreach ($ordemCargos as $cargo) {
        foreach ($listagem[$cargo] as &$p) {
            if ($p['ativo']) {
                $contadorGlobalAtivo++;
                $p['numero'] = $contadorGlobalAtivo;
                $contagemAtivos[$cargo]++;

                // Marca se o nome deve ficar em azul (dentro do limite de licenças)
                $p['nomeEmAzul'] = ($contadorGlobalAtivo <= $numeroLicencas);
            }
        }
    }
    unset($p);

    $totalAtivos = array_sum($contagemAtivos);

} catch (PDOException $e) {
    $erro = "Erro no banco de dados: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrutura de Equipe • ORLA IMOB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #0d6efd;
            --teal: #20c997;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
            --dark: #212529;
        }
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f0fa 100%);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #2d3748;
            min-height: 100vh;
        }
        .container-fluid { max-width: 1600px; }
        .card { border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.07); transition: all 0.2s; }
        .card:hover { box-shadow: 0 12px 36px rgba(0,0,0,0.12); transform: translateY(-2px); }
        .card-header { background: linear-gradient(90deg, var(--primary), #0b5ed7); color: white; font-weight: 600; padding: 1rem 1.25rem; border: none; }
        .list-group-item { padding: 0.85rem 1.25rem; border: none; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; gap: 12px; transition: background 0.15s; }
        .list-group-item:hover { background: rgba(13,110,253,0.06); }
        .list-group-item.ativo { background: rgba(32,201,151,0.08); }
        .list-group-item.inativo { background: rgba(253,126,20,0.06); opacity: 0.82; }
        .numero-linha { min-width: 38px; font-weight: 700; color: var(--gray-600); text-align: right; }
        .nome-linha { flex: 1; font-weight: 500; color: #1a202c; }
        .nome-linha.azul-licenca { color: var(--primary) !important; font-weight: 700; }
        .vendas-info { font-size: 0.78rem; font-weight: 600; color: var(--primary); background: rgba(13,110,253,0.08); padding: 3px 8px; border-radius: 6px; white-space: nowrap; }
        .zero-vendas .vendas-info { color: var(--gray-600); background: var(--gray-200); font-weight: 500; }
        .licenca-ok { color: var(--teal); font-size: 1.1rem; margin-right: 6px; }
        .form-check-input { width: 1.2em; height: 1.2em; margin-top: 0.2em; cursor: pointer; }
        .form-check-input:checked { background-color: var(--teal); border-color: var(--teal); }
        .badge-count { background-color: rgba(255,255,255,0.25); color: white; font-weight: 500; }
    </style>
</head>
<body>

<div class="container-fluid py-5 px-3 px-md-4 px-xxl-5">

    <header class="text-center mb-5">
        <h4 class="display-6 fw-bold mb-1">Estrutura de Equipe</h4>
        <p class="text-muted">ORLA IMOB • Atualização em tempo real</p>
        <?php if ($numeroLicencas > 0): ?>
            <p class="text-primary small">Primeiros <?= $numeroLicencas ?> nomes ativos destacados em azul</p>
        <?php endif; ?>
    </header>

    <?php if ($erro): ?>
        <div class="alert alert-danger mb-4"><?= $erro ?></div>
    <?php endif; ?>

    <!-- Cards de resumo -->
    <div class="row g-3 justify-content-center mb-5">
        <?php foreach ($contagemAtivos as $cargo => $qtd): ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card text-center py-3">
                <div class="small text-uppercase fw-semibold text-muted mb-1"><?= htmlspecialchars($cargo) ?></div>
                <div class="fs-3 fw-bold <?= $qtd > 0 ? 'text-teal' : 'text-secondary' ?>"><?= $qtd ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card border border-teal text-center py-3">
                <div class="small text-uppercase fw-semibold text-muted mb-1">TOTAL ATIVOS</div>
                <div class="fs-3 fw-bold text-teal"><?= $totalAtivos ?></div>
            </div>
        </div>
    </div>

    <!-- Colunas de cargos -->
    <div class="row g-4">
        <?php foreach (['Diretores', 'Gerentes', 'Gestores', 'Corretores'] as $cargo):
            $pessoas = $listagem[$cargo] ?? [];
            if (empty($pessoas)) continue;
        ?>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><?= strtoupper($cargo) ?></span>
                        <span class="badge badge-count"><?= count($pessoas) ?></span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($pessoas as $p):
                            $status_class = $p['ativo'] ? 'ativo' : 'inativo';
                            $venda_class  = ($p['qtd'] == 0) ? 'zero-vendas' : '';
                            $nome_class   = (!empty($p['nomeEmAzul'])) ? 'azul-licenca' : '';
                        ?>
                            <li class="list-group-item <?= $status_class ?> <?= $venda_class ?>">
                                <div class="form-check">
                                    <input class="form-check-input toggle-ativo" type="checkbox"
                                           <?= $p['ativo'] ? 'checked' : '' ?>
                                           data-userid="<?= $p['userId'] ?>">
                                </div>
                                <span class="numero-linha">
                                    <?= $p['ativo'] ? $p['numero'] . '.' : '—' ?>
                                </span>
                                <div class="nome-linha <?= $nome_class ?>">
                                    <?php if ($p['licenca']): ?>
                                        <i class="bi bi-check-circle-fill licenca-ok" title="Licença ativa"></i>
                                    <?php endif; ?>
                                    <?= $p['nome'] ?>
                                    <br>
                                    <small><?= $p['vendas'] ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-ativo').forEach(checkbox => {
        checkbox.addEventListener('change', function(e) {
            const userId = this.dataset.userid;
            const novoStatus = this.checked ? 1 : 0;
            if (!confirm(`Deseja realmente ${this.checked ? 'ATIVAR' : 'DESATIVAR'} este usuário?`)) {
                this.checked = !this.checked;
                return;
            }
            fetch('usr_atualiza_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `userId=${userId}&ativo=${novoStatus}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("Erro ao atualizar status.");
                    this.checked = !this.checked;
                }
            })
            .catch(() => {
                alert("Erro de conexão.");
                this.checked = !this.checked;
            });
        });
    });
});
</script>
</body>
</html>
<?php
require_once 'conn.php';
require_once "usr_acoes.php";

// Proteção da página
protegerPagina(true);

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

    // 3. Estrutura da listagem (Organização por Cargo)
    $listagem = [
        'Diretores' => [],
        'Gerentes' => [],
        'Gestores' => [],
        'Corretores' => []
    ];

    $nomesJaUsados = [];
    $contagemAtivos = ['Diretores' => 0, 'Gerentes' => 0, 'Gestores' => 0, 'Corretores' => 0];

    // PASSO 1: Distribuir os usuários nos arrays de cargos (Mantendo ordem alfabética da query)
    foreach ($usuarios as $row) {
        $userId = $row['UserId'];
        $nomeNormalizado = normalizarNome($row['NomeUsuario']);

        // Evita duplicados por nome/ID
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
            'userId'  => $userId,
            'nome'    => htmlspecialchars(mb_strtoupper($row['NomeUsuario']), ENT_QUOTES, 'UTF-8') . " (ID: $userId)" . $funcao,
            'vendas'  => $vendas_str,
            'qtd'     => $qtd,
            'ativo'   => (bool)$row['Ativo'],
            'licenca' => (bool)$row['Licenca'],
            'numero'  => null // Será preenchido no Passo 2
        ];
        $nomesJaUsados[] = $nomeNormalizado;
    }

    // PASSO 2: Aplicar a contagem sequencial seguindo a hierarquia das colunas
    $contadorGlobalAtivo = 0;
    $ordemCargos = ['Diretores', 'Gerentes', 'Gestores', 'Corretores'];

    foreach ($ordemCargos as $cargo) {
        foreach ($listagem[$cargo] as &$p) {
            if ($p['ativo']) {
                $p['numero'] = ++$contadorGlobalAtivo;
                $contagemAtivos[$cargo]++;
            }
        }
    }
    unset($p); // Limpa referência

    $totalAtivos = array_sum($contagemAtivos);

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
        body { background-color: #f8f9fa; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 10px; }
        .card-header { background: #fff; font-weight: 700; border-bottom: 1px solid #eee; color: #333; }
        .list-group-item { padding: 0.6rem 0.8rem; display: flex; align-items: center; gap: 8px; border-left: none; border-right: none; }
        .list-group-item.ativo { background-color: #f0fff0 !important; }
        .list-group-item.inativo { background-color: #fff5f5 !important; opacity: 0.8; }
        .numero-linha { width: 30px; font-weight: bold; color: #6c757d; text-align: center; }
        .nome-linha { flex-grow: 1; font-weight: 500; }
        .vendas-info { font-size: 0.75rem; font-weight: 600; color: #0d6efd; white-space: nowrap; }
        .zero-vendas .vendas-info { color: #adb5bd; font-weight: 400; }
        .licenca-ok { color: #0d6efd; font-size: 0.8rem; margin-right: 4px; }
        .badge-count { font-size: 0.75rem; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <h4 class="mb-4 fw-bold text-center">Estrutura de Equipe ORLA IMOB</h4>

    <div class="row g-3 justify-content-center mb-4">
        <?php foreach ($contagemAtivos as $cargo => $qtd): ?>
        <div class="col-6 col-md-2">
            <div class="card text-center py-2">
                <div class="small text-muted mb-0"><?= strtoupper($cargo) ?></div>
                <div class="fs-4 fw-bold <?= $qtd > 0 ? 'text-success' : 'text-secondary' ?>"><?= $qtd ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-6 col-md-2">
            <div class="card text-center py-2 border-success">
                <div class="small text-muted mb-0">TOTAL ATIVOS</div>
                <div class="fs-4 fw-bold text-success"><?= $totalAtivos ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($ordemCargos as $cargo): 
            $pessoas = $listagem[$cargo];
            if (empty($pessoas)) continue;
        ?>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <?= strtoupper($cargo) ?>
                        <span class="badge bg-dark badge-count"><?= count($pessoas) ?></span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php 
                        // Separar para exibir ativos primeiro ou manter ordem alfabética? 
                        // Aqui mantemos a ordem alfabética da query original para facilitar localização
                        foreach ($pessoas as $p): 
                            $status_class = $p['ativo'] ? 'ativo' : 'inativo';
                            $venda_class = ($p['qtd'] == 0) ? 'zero-vendas' : '';
                        ?>
                            <li class="list-group-item <?= $status_class ?> <?= $venda_class ?>">
                                <input class="form-check-input toggle-ativo" type="checkbox" 
                                       <?= $p['ativo'] ? 'checked' : '' ?> data-userid="<?= $p['userId'] ?>">
                                
                                <span class="numero-linha">
                                    <?= $p['ativo'] ? $p['numero'] . '.' : '-' ?>
                                </span>

                                <div class="nome-linha">
                                    <?php if ($p['licenca']): ?><span class="licenca-ok" title="Licença">🔵</span><?php endif; ?>
                                    <?= $p['nome'] ?>
                                </div>
                                
                                <div class="vendas-info"><?= $p['vendas'] ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-ativo').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const userId = this.dataset.userid;
            const novoStatus = this.checked ? 1 : 0;
            if (!confirm("Confirmar alteração de status?")) {
                this.checked = !this.checked;
                return;
            }
            
            fetch('usr_atualiza_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `userId=${userId}&ativo=${novoStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Recarrega para atualizar a numeração sequencial
                } else {
                    alert("Erro ao atualizar");
                    this.checked = !this.checked;
                }
            })
            .catch(err => {
                console.error(err);
                this.checked = !this.checked;
            });
        });
    });
});
</script>
</body>
</html>
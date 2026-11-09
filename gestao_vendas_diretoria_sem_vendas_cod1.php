<?php
// #####################################################
// Relatório Simplificado de Corretores - Versão PDO/PHP
// Conversão do ASP clássico
// #####################################################

session_start();

// Evitar cache
header("Expires: -1");
header("Pragma: no-cache");
header("Cache-Control: no-store, must-revalidate");

// Incluir conexão única (já que tudo está em conn.php)
require_once "conn.php";       // contém $host, $usuario, $senha, $banco
require_once "usr_acoes.php";  // suas funções de autenticação (se existir)

// Enviar e-mail de acesso (fora do localhost)
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    $to = "valterpb@gmail.com, valterpb@hotmail.com";
    $subject = "SV-DIR-CORRET2-" . strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO') .
               " - " . $_SERVER['REMOTE_ADDR'] . " - " . date('d/m/Y') . " - " . date('H:i:s');
    $message = "Página Corretores. " . strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO');
    $headers = "From: sendmail@sunnyimob.com.br\r\n";
    @mail($to, $subject, $message, $headers);
}

// ===============================================================
$paginaRedirecionamento1 = "login_p1.php";
$paginaRedirecionamento2 = "login_p1.php";
// Controle de acesso
$isBarreto = (strtoupper($_SESSION['Usuario'] ?? '') === 'BARRETO');
$diretoriaID = $_SESSION['Dir_DiretoriaID'] ?? '';

if ($diretoriaID == '' && !$isBarreto) {

    echo "Erro de processamento! (100)";
    
    if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
        header("Location: " . $paginaRedirecionamento1);
    } else {
        header("Location: " . $paginaRedirecionamento2);
    }
    exit();
}

// ===============================================================


// Conexão PDO única
try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . htmlspecialchars($e->getMessage()));
}

// Data atual
$anoAtual  = date('Y');
$mesAtual  = date('n');
$diaAtual  = date('j');

// Meses fixos: 2025 e 2026 (24 colunas)
$meses = [];
$mesesLabels = [];
$cont = 0;

for ($ano = 2025; $ano <= 2026; $ano++) {
    for ($mes = 1; $mes <= 12; $mes++) {
        $meses[$cont]       = "$ano-" . str_pad($mes, 2, '0', STR_PAD_LEFT);
        $mesesLabels[$cont] = date('M', mktime(0,0,0,$mes,1)) . "/" . substr($ano, -2);
        $cont++;
    }
}

// 1. Usuários ativos
$usuariosAtivos = [];
$sql = "SELECT UserId, Nome FROM usuarios WHERE Ativo = 1";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $usuariosAtivos[$row['UserId']] = $row['Nome'];
}

$idsAtivos = !empty($usuariosAtivos) ? implode(',', array_keys($usuariosAtivos)) : '0';

// Arrays de dados
$corretoresDados = [];
$gerenciasUnicas = [];

// 2. Buscar corretores ativos com vendas
if ($idsAtivos !== '0') {
    $sql = "SELECT DISTINCT Corretor, CorretorId 
            FROM vendas 
            WHERE Excluido = 0 
              AND CorretorId IN ($idsAtivos)
              AND DiretoriaId = :dir 
              AND Corretor IS NOT NULL 
              AND TRIM(Corretor) <> ''
            ORDER BY Corretor";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':dir' => $diretoriaID]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nome = trim($row['Corretor']);
        $id   = $row['CorretorId'];

        if (empty($nome) || !isset($usuariosAtivos[$id])) {
            continue;
        }

        // Última venda (ano, mês, dia)
        $sqlUlt = "SELECT AnoVenda, MesVenda, DiaVenda 
                   FROM vendas 
                   WHERE Excluido = 0 AND CorretorId = :id 
                   ORDER BY AnoVenda DESC, MesVenda DESC, DiaVenda DESC 
                   LIMIT 1";
        $stmtUlt = $pdo->prepare($sqlUlt);
        $stmtUlt->execute([':id' => $id]);
        $ult = $stmtUlt->fetch(PDO::FETCH_ASSOC);

        $ultAno = $ult ? (int)$ult['AnoVenda'] : 0;
        $ultMes = $ult ? (int)$ult['MesVenda'] : 0;
        $ultDia = $ult ? (int)$ult['DiaVenda'] : 0;

        // Dias sem vender
        $diasSem = 9999;
        if ($ultAno > 0 && $ultMes > 0 && $ultDia > 0) {
            $dataUlt = mktime(0,0,0, $ultMes, $ultDia, $ultAno);
            $dataHoje = mktime(0,0,0, $mesAtual, $diaAtual, $anoAtual);
            $diff = ($dataHoje - $dataUlt) / 86400;
            $diasSem = max(0, (int)$diff);
        }

        // Meses sem vender
        $mesesSem = ($ultAno > 0 && $ultMes > 0) 
            ? (($anoAtual - $ultAno) * 12) + ($mesAtual - $ultMes) 
            : 999;
        if ($mesesSem < 0) $mesesSem = 0;

        // Gerência atual
        $sqlGer = "SELECT Gerencia 
                   FROM vendas 
                   WHERE Excluido = 0 AND CorretorId = :id 
                   ORDER BY AnoVenda DESC, MesVenda DESC 
                   LIMIT 1";
        $stmtGer = $pdo->prepare($sqlGer);
        $stmtGer->execute([':id' => $id]);
        $gerencia = trim($stmtGer->fetchColumn() ?: '');

        if ($gerencia !== '') {
            $gerenciasUnicas[$gerencia] = ($gerenciasUnicas[$gerencia] ?? 0) + 1;
        }

        // Vendas por mês (2025-2026)
        $vendasMes = array_fill(0, 24, 0);

        $sqlVendas = "SELECT AnoVenda, MesVenda, COUNT(*) as qtd 
                      FROM vendas 
                      WHERE Excluido = 0 AND CorretorId = :id 
                      GROUP BY AnoVenda, MesVenda";
        $stmtV = $pdo->prepare($sqlVendas);
        $stmtV->execute([':id' => $id]);

        while ($vm = $stmtV->fetch(PDO::FETCH_ASSOC)) {
            $key = $vm['AnoVenda'] . "-" . str_pad($vm['MesVenda'], 2, '0', STR_PAD_LEFT);
            $idx = array_search($key, $meses);
            if ($idx !== false) {
                $vendasMes[$idx] = (int)$vm['qtd'];
            }
        }

        // Armazenar tudo
        $corretoresDados[] = [
            'nome'          => strtoupper($nome),
            'gerencia'      => $gerencia,
            'diasSem'       => $diasSem,
            'mesesSem'      => $mesesSem,
            'ultAno'        => $ultAno,
            'ultMes'        => $ultMes,
            'ultDia'        => $ultDia,
            'vendasMes'     => $vendasMes,
        ];
    }
}

// Ordenar por dias sem vender (decrescente)
usort($corretoresDados, function($a, $b) {
    return $b['diasSem'] <=> $a['diasSem'];
});

$count = count($corretoresDados);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Simplificado de Corretores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Seu CSS original mantido aqui */
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; font-size: 14px; }
        .table th { background-color: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .badge-venda { font-size: 0.75rem; min-width: 25px; height: 25px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; }
        .header-section { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 15px; border-radius: 8px; margin-bottom: 20px; color: white; }
        .mes-header { background-color: #e9ecef; font-weight: bold; text-align: center; padding: 8px 5px; font-size: 0.8rem; writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; height: 150px; width: 25px; }
        .dias-sem-vender { font-weight: bold; padding: 5px 10px; border-radius: 4px; display: inline-block; min-width: 70px; text-align: center; }
        .dias-vermelho { background-color: #dc3545; color: white; }
        .dias-laranja  { background-color: #fd7e14; color: white; }
        .dias-amarelo  { background-color: #ffc107; color: black; }
        .dias-verde    { background-color: #28a745; color: white; }
        .fixed-column   { position: sticky; left: 0; background: white; z-index: 5; }
        .fixed-column-2 { position: sticky; left: 100px; background: white; z-index: 5; }

        .meses-2025 { background-color: #e3f2fd; }
        .meses-2026 { background-color: #f3e5f5; }
        .filter-badge.active { background-color: #0d6efd !important; color: white !important; }
        .table-container { overflow-x: auto; }
    </style>
    
    <style>
        /* Adicione ao seu CSS existente */
        .celula-2025 {
            background-color: #f8f9fa !important; /* Cinza bem clarinho */
        }

        .celula-2026 {
            background-color: white !important; /* Ou remova para fundo padrão */
        }        

    </style>

    <style>
        /* Adicione ou modifique no seu <style> */
.table thead tr:first-child th {
    background-color: #B3D5F7 !important;
    text-align: center !important;          /* centraliza horizontalmente */
    vertical-align: middle !important;      /* centraliza verticalmente */
    height: 60px;                           /* dá mais espaço vertical se necessário */
    font-weight: bold;
    font-size: 1.1rem;
}

.ano-header {
    background-color: #B3D5F7 !important;
    color: white;
    text-align: center;
    font-weight: bold;
    padding: 5px;
    font-size: 0.9rem;
    white-space: nowrap;
    height: 150px;
    width: 25px;
}


.meses-sem-vender {
    font-weight: bold;
    display: inline-flex;           /* melhor que inline-block para centralizar */
    align-items: center;            /* centraliza verticalmente */
    justify-content: center;        /* centraliza horizontalmente */
    min-width: 38px;                /* largura mínima igual à altura */
    height: 38px;                   /* altura fixa = largura → círculo perfeito */
    line-height: 1;                 /* evita espaçamento extra */
    padding: 0;                     /* remove padding para ficar exato */
    border-radius: 50% !important;  /* 50% = círculo completo */
    text-align: center;
    font-size: 0.95rem;             /* tamanho do número dentro do círculo */
    box-shadow: 0 2px 5px rgba(0,0,0,0.15); /* opcional: sombra suave */
}

/* Cores de fundo (mantém as mesmas que você já usa) */
.meses-sem-vender.bg-success { background-color: #198754 !important; color: white; }
.meses-sem-vender.bg-info    { background-color: #0dcaf0 !important; color: white; }
.meses-sem-vender.bg-warning { background-color: #ffc107 !important; color: black; }
.meses-sem-vender.bg-danger  { background-color: #dc3545 !important; color: white; }
.meses-sem-vender.bg-secondary { background-color: #6c757d !important; color: white; }
    </style>
</head>
<body>

<div class="container-fluid mt-3">

    <!-- Header -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1"><i class="fas fa-users me-2"></i>Relatório Simplificado de Corretores</h4>
                <p class="mb-0">Diretoria: <strong><?= htmlspecialchars($_SESSION['NOME_DIRETORIA']  ?? 'N/D') ?></strong> | Total: <strong><?= $count ?></strong> corretores ativos</p>
                <p class="mb-0"><small><i class="fas fa-sort-amount-down me-1"></i>Ordenado por: <strong>Dias sem vender (decrescente)</strong></small></p>
            </div>
            <div class="col-md-4 text-end">
                <small>Data: <?= date('d/m/Y') ?></small>
            </div>
        </div>
    </div>

    <!-- Filtro por Gerência -->
    <div class="filter-section">
        <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filtrar por Gerência</h6>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button class="btn btn-sm btn-outline-primary filter-badge active" onclick="filtrarPorGerencia('')" id="filtroTodas">
                <i class="fas fa-eye me-1"></i>Todas Gerências (<?= $count ?>)
            </button>

            <?php foreach ($gerenciasUnicas as $ger => $qtd): ?>
                <button class="btn btn-sm btn-outline-<?= ['primary','success','warning','danger','info'][array_rand([0,1,2,3,4])] ?> filter-badge" 
                        onclick="filtrarPorGerencia('<?= htmlspecialchars($ger) ?>')" 
                        id="filtro<?= str_replace(' ', '', $ger) ?>">
                    <i class="fas fa-building me-1"></i><?= htmlspecialchars($ger) ?> (<?= $qtd ?>)
                </button>
            <?php endforeach; ?>

            <?php
            $semGerencia = 0;
            foreach ($corretoresDados as $c) {
                if ($c['gerencia'] === '') $semGerencia++;
            }
            if ($semGerencia > 0):
            ?>
                <button class="btn btn-sm btn-outline-secondary filter-badge" onclick="filtrarPorGerencia('sem_gerencia')" id="filtroSemGerencia">
                    <i class="fas fa-question-circle me-1"></i>Sem Gerência (<?= $semGerencia ?>)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($count > 0): ?>
    <!-- Tabela principal -->
    <div class="card">
        <div class="card-header text-white d-flex justify-content-between" style="background:#f39c12;">
            <div>
                <i class="fas fa-table me-2"></i>Quantidade de Vendas por Mês (2025-2026)
                <span class="badge bg-light text-dark ms-2" id="contadorTabela">Mostrando <?= $count ?> corretores</span>
                <span class="badge bg-info text-white ms-2" id="filtroAtivo" style="display:none;"></span>
            </div>
            <small>Legenda: <span class="badge bg-success">0-30 dias</span> <span class="badge bg-warning">31-90 dias</span> <span class="badge bg-danger">+90 dias</span></small>
        </div>
        <div class="card-body p-0">
            <div class="table-container">
                <table class="table table-bordered table-hover mb-0" id="tabelaCorretores">
                    <thead>
                        <tr>
                            <th class="fixed-column">#</th>
                            <th class="fixed-column-2">Corretor</th>
                            <th>Gerência</th>
                            <th class="text-center">Dias sem Vender</th>
                            <th class="text-center">Meses sem Vender</th>
                            <th colspan="12" class="ano-header meses-2025">2025</th>
                            <th colspan="12" class="ano-header meses-2026">2026</th>
                        </tr>
                        <tr>
                            <th colspan="5"></th>
                            <?php for ($i = 0; $i < 24; $i++): ?>
                                <th class="mes-header <?= $i < 12 ? 'meses-2025' : 'meses-2026' ?>">
                                    <?= $mesesLabels[$i] ?>
                                </th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody id="tbodyCorretores">
                    <?php foreach ($corretoresDados as $idx => $c): ?>
                        <?php
                        $diasClass = $c['diasSem'] === 9999 ? 'bg-secondary text-white' :
                                     ($c['diasSem'] >= 90 ? 'dias-vermelho' :
                                      ($c['diasSem'] >= 30 ? 'dias-laranja' :
                                       ($c['diasSem'] >= 7 ? 'dias-amarelo' : 'dias-verde')));

                        $mesesClass = $c['mesesSem'] === 999 ? 'bg-secondary text-white' :
                                      ($c['mesesSem'] >= 12 ? 'bg-danger text-white' :
                                       ($c['mesesSem'] >= 6 ? 'bg-warning text-white' :
                                        ($c['mesesSem'] >= 3 ? 'bg-info text-white' : 'bg-success text-white')));

                        $gerId = $c['gerencia'] === '' ? 'sem_gerencia' : $c['gerencia'];
                        ?>
                        <tr data-gerencia="<?= htmlspecialchars($gerId) ?>">
                            <td class="fixed-column fw-bold"><?= $idx + 1 ?></td>
                            <td class="fixed-column-2">
                                <strong><?= htmlspecialchars($c['nome']) ?></strong>
                                <?php if ($c['ultAno'] > 0): ?>
                                    <br><small class="text-muted">Última: <?= $c['ultDia'] ?>/<?= $c['ultMes'] ?>/<?= $c['ultAno'] ?></small>
                                <?php else: ?>
                                    <br><small class="text-muted">Nunca vendeu</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $c['gerencia'] ? htmlspecialchars($c['gerencia']) : '<span class="badge bg-secondary">Sem gerência</span>' ?>
                            </td>
                            <td class="text-center">
                                <span class="dias-sem-vender <?= $diasClass ?>">
                                    <?= $c['diasSem'] === 9999 ? 'N/A' : $c['diasSem'] . ' dias' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="meses-sem-vender <?= $mesesClass ?>">
                                    <?= $c['mesesSem'] === 999 ? 'N/A' : $c['mesesSem'] ?>
                                </span>
                            </td>
                            <!-- ========================== -->
<?php for ($j = 0; $j < 24; $j++): 
    $qtd = $c['vendasMes'][$j];
    // Determine se é 2025 ou 2026
    $anoClass = ($j < 12) ? 'celula-2025' : 'celula-2026';
?>
    <td class="text-center <?= $anoClass ?>">
        <?php if ($qtd > 0): ?>
            <span class="badge-venda bg-info" title="<?= $mesesLabels[$j] ?>: <?= $qtd ?> vendas">
                <?= $qtd ?>
            </span>
        <?php else: ?>
            <span class="badge-venda bg-light text-muted" title="<?= $mesesLabels[$j] ?>: 0 vendas">
                0
            </span>
        <?php endif; ?>
    </td>
<?php endfor; ?>
                            <!-- ========================== -->
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Cards de resumo -->
    <div class="row mt-4">
        <?php
        $ativosRecentes = $atencao = $inativos = $semVendas = 0;
        foreach ($corretoresDados as $c) {
            if ($c['diasSem'] === 9999) $semVendas++;
            elseif ($c['diasSem'] <= 30) $ativosRecentes++;
            elseif ($c['diasSem'] <= 90) $atencao++;
            else $inativos++;
        }
        ?>
        <div class="col-md-3"><div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-success"><i class="fas fa-check-circle"></i> Ativos Recentes</h6>
                <h3 class="text-success"><?= $ativosRecentes ?></h3>
                <small>≤ 30 dias</small>
            </div>
        </div></div>
        <div class="col-md-3"><div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-warning"><i class="fas fa-exclamation-triangle"></i> Atenção</h6>
                <h3 class="text-warning"><?= $atencao ?></h3>
                <small>31-90 dias</small>
            </div>
        </div></div>
        <div class="col-md-3"><div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-danger"><i class="fas fa-user-clock"></i> Inativos</h6>
                <h3 class="text-danger"><?= $inativos ?></h3>
                <small>> 90 dias</small>
            </div>
        </div></div>
        <div class="col-md-3"><div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-secondary"><i class="fas fa-question-circle"></i> Sem Vendas</h6>
                <h3 class="text-secondary"><?= $semVendas ?></h3>
                <small>Nenhuma venda</small>
            </div>
        </div></div>
    </div>

    <?php else: ?>
    <div class="alert alert-warning text-center mt-4">
        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
        <h5>Nenhum corretor ativo encontrado</h5>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Seu JavaScript de filtro (já adaptado anteriormente)
let filtroGerenciaAtiva = '';

function filtrarPorGerencia(gerencia) {
    filtroGerenciaAtiva = gerencia;

    document.querySelectorAll('.filter-badge').forEach(btn => btn.classList.remove('active'));

    if (gerencia === '') {
        document.getElementById('filtroTodas')?.classList.add('active');
    } else if (gerencia === 'sem_gerencia') {
        document.getElementById('filtroSemGerencia')?.classList.add('active');
    } else {
        const id = 'filtro' + gerencia.replace(/ /g, '');
        document.getElementById(id)?.classList.add('active');
    }

    const rows = document.querySelectorAll('#tbodyCorretores tr');
    let visible = 0;

    rows.forEach(row => {
        const g = row.getAttribute('data-gerencia');
        const match = !gerencia || 
                      (gerencia === 'sem_gerencia' && g === 'sem_gerencia') || 
                      g === gerencia;

        if (match) {
            row.style.display = '';
            visible++;
            row.querySelector('td:first-child').textContent = visible;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('contadorTabela').textContent = `Mostrando ${visible} corretores`;

    const badge = document.getElementById('filtroAtivo');
    if (gerencia) {
        badge.textContent = gerencia === 'sem_gerencia' ? 'Sem Gerência' : gerencia;
        badge.style.display = 'inline';
    } else {
        badge.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Destacar badges com vendas
    document.querySelectorAll('.badge-venda.bg-success').forEach(b => {
        if (parseInt(b.textContent) > 0) {
            b.style.boxShadow = '0 0 5px rgba(0,128,0,0.5)';
        }
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') filtrarPorGerencia('');
});
</script>

</body>
</html>
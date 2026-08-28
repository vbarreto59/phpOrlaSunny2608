<?php
// ######################################
// SISTEMA: SGVENDAS - Relatório de Corretores Ativos
// Conversão de ASP → PHP
// Autor original: Valter Barreto
// ######################################

session_start();

// Evitar cache
header("Expires: -1");
header("Pragma: no-cache");
header("Cache-Control: no-store, must-revalidate");

// Incluir conexões (ajuste os nomes dos arquivos conforme seu projeto)
require_once "conn.php";           // conexão principal (vendas)
require_once "usr_acoes.php";      // suas funções de autenticação/autorização

// Enviar e-mail de acesso (fora do localhost)
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    $to = "valterpb@gmail.com, valterpb@hotmail.com";
    $subject = "SV-DIR-CORRET1-" . strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO') .
               " - " . $_SERVER['REMOTE_ADDR'] . " - " . date('d/m/Y') . " - " . date('H:i:s');
    $message = "Página Corretores. " . strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO');
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    @mail($to, $subject, $message, $headers);
}

// ========================
// CONTROLE DE ACESSO
// ========================
$diretoriaID = $_SESSION['Dir_DiretoriaID'] ?? '';
$paginaRedirecionamento1 = "login_p1.php";
$paginaRedirecionamento2 = "login_v66a.php";

$isBarreto = (strtoupper($_SESSION['Usuario'] ?? '') === 'BARRETO');
$diretoriaID = $_SESSION['Dir_DiretoriaID'] ?? '';

if ($diretoriaID == '' && !$isBarreto) {
    echo "Erro de processamento! (100)";
    if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
        header("Location: $paginaRedirecionamento1");
    } else {
        header("Location: $paginaRedirecionamento2");
    }
    exit;
}

// ========================
// CONEXÕES
// ========================
try {
    $pdoVendas = new PDO("mysql:host=$host;dbname=$banco", $usuario, $senha);
    $pdoVendas->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdoUsuarios = new PDO("mysql:host=$host;dbname=$banco", $usuario, $senha);
    $pdoUsuarios->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . htmlspecialchars($e->getMessage()));
}



// Data atual
$anoAtual = date('Y');
$mesAtual = date('n');

// ========================
// 1. Usuários ativos (dicionário)
// ========================
$usuariosAtivos = [];
$sql = "SELECT UserId, Nome FROM usuarios WHERE Ativo = 1";
$stmt = $pdoUsuarios->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $usuariosAtivos[$row['UserId']] = $row['Nome'];
}

// IDs dos usuários ativos (string para IN)
$idsAtivos = !empty($usuariosAtivos) ? implode(',', array_keys($usuariosAtivos)) : '0';



// ========================
// 2. Gerências únicas (para filtro)
// ========================
$gerenciasUnicas = [];
if ($idsAtivos !== '0') {
    $sql = "SELECT DISTINCT Gerencia 
            FROM vendas 
            WHERE Excluido = 0 
              AND CorretorId IN ($idsAtivos)
              AND DiretoriaId = :dir 
              AND Gerencia IS NOT NULL 
              AND TRIM(Gerencia) <> ''
            ORDER BY Gerencia";
    $stmt = $pdoVendas->prepare($sql);
    $stmt->execute([':dir' => $diretoriaID]);
    $gerenciasUnicas = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ========================
// 3. Coleta de dados dos corretores
// ========================
$corretoresDados = [];

if ($idsAtivos !== '0') {
    $sql = "SELECT DISTINCT Corretor, CorretorId 
            FROM vendas 
            WHERE Excluido = 0 
              AND CorretorId IN ($idsAtivos)
              AND DiretoriaId = :dir 
              AND Corretor IS NOT NULL 
              AND TRIM(Corretor) <> ''
            ORDER BY Corretor";
    $stmt = $pdoVendas->prepare($sql);
    $stmt->execute([':dir' => $diretoriaID]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nome = trim($row['Corretor']);
        $id   = $row['CorretorId'];

        if (empty($nome) || !isset($usuariosAtivos[$id])) {
            continue; // só corretores ativos
        }

        // Última venda
        $sqlUltima = "SELECT AnoVenda, MesVenda 
                      FROM vendas 
                      WHERE Excluido = 0 
                        AND Corretor = :nome 
                        AND CorretorId IN ($idsAtivos)
                        AND DiretoriaId = :dir 
                      ORDER BY AnoVenda DESC, MesVenda DESC 
                      LIMIT 1";
        $stmtUlt = $pdoVendas->prepare($sqlUltima);
        $stmtUlt->execute([':nome' => $nome, ':dir' => $diretoriaID]);
        $ultima = $stmtUlt->fetch(PDO::FETCH_ASSOC);

        if ($ultima) {
            $ultAno = (int)$ultima['AnoVenda'];
            $ultMes = (int)$ultima['MesVenda'];

            // Total vendas e VGV
            $sqlTotal = "SELECT COUNT(*) as qtd, COALESCE(SUM(ValorUnidade), 0) as vgv 
                         FROM vendas 
                         WHERE Excluido = 0 
                           AND Corretor = :nome 
                           AND CorretorId IN ($idsAtivos)
                           AND DiretoriaId = :dir";
            $stmtTot = $pdoVendas->prepare($sqlTotal);
            $stmtTot->execute([':nome' => $nome, ':dir' => $diretoriaID]);
            $tot = $stmtTot->fetch(PDO::FETCH_ASSOC);

            $totalVendas = (int)$tot['qtd'];
            $totalVGV    = (float)$tot['vgv'];

            // Meses sem vender
            $mesesSem = ($anoAtual - $ultAno) * 12 + ($mesAtual - $ultMes);
            if ($mesesSem < 0) $mesesSem = 0;
        } else {
            $ultAno = 0;
            $ultMes = 0;
            $totalVendas = 0;
            $totalVGV = 0;
            $mesesSem = 999;
        }

        // Última gerência
        $sqlGer = "SELECT Gerencia 
                   FROM vendas 
                   WHERE Excluido = 0 
                     AND Corretor = :nome 
                     AND CorretorId IN ($idsAtivos)
                     AND DiretoriaId = :dir 
                   ORDER BY AnoVenda DESC, MesVenda DESC 
                   LIMIT 1";
        $stmtGer = $pdoVendas->prepare($sqlGer);
        $stmtGer->execute([':nome' => $nome, ':dir' => $diretoriaID]);
        $ger = $stmtGer->fetchColumn();
        $gerenciaAtual = $ger ? trim($ger) : '';

        // Status
        if ($mesesSem === 999) {
            $statusTexto = "Sem Vendas";
            $statusClass = "badge-secondary";
        } elseif ($mesesSem >= 12) {
            $statusTexto = "Muito Inativo";
            $statusClass = "badge-vermelho";
        } elseif ($mesesSem >= 6) {
            $statusTexto = "Inativo";
            $statusClass = "badge-laranja";
        } elseif ($mesesSem >= 3) {
            $statusTexto = "Atenção";
            $statusClass = "badge-amarelo";
        } else {
            $statusTexto = "Ativo";
            $statusClass = "badge-verde";
        }

        $corretoresDados[] = [
            'nome'          => strtoupper($nome),
            'ultAno'        => $ultAno,
            'ultMes'        => $ultMes,
            'totalVendas'   => $totalVendas,
            'totalVGV'      => $totalVGV,
            'mesesSem'      => $mesesSem,
            'statusTexto'   => $statusTexto,
            'statusClass'   => $statusClass,
            'gerencia'      => $gerenciaAtual,
        ];
    }
}

// ========================
// Ordenar por meses sem vender (decrescente)
// ========================
usort($corretoresDados, function($a, $b) {
    return $b['mesesSem'] <=> $a['mesesSem'];
});

// ========================
// Cálculos de totais
// ========================
$totalCorretores     = count($corretoresDados);
$corretoresAtivos    = 0;
$corretoresInativos  = 0;
$corretoresAtencao   = 0;
$corretoresMuitoInat = 0;
$corretoresSemVendas = 0;

foreach ($corretoresDados as $c) {
    switch ($c['statusTexto']) {
        case 'Ativo':           $corretoresAtivos++;    break;
        case 'Atenção':         $corretoresAtencao++; $corretoresInativos++; break;
        case 'Inativo':         $corretoresInativos++;  break;
        case 'Muito Inativo':   $corretoresMuitoInat++; $corretoresInativos++; break;
        case 'Sem Vendas':      $corretoresSemVendas++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Corretores - Diretoria <?= htmlspecialchars($_SESSION['NOME_DIRETORIA']  ?? 'N/D') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Manter os mesmos estilos que você tinha no ASP */
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .card { margin-bottom: 20px; border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .card-header { border-radius: 10px 10px 0 0; font-weight: 600; padding: 15px 20px; }
        .badge-status { font-size: 0.8rem; padding: 4px 8px; border-radius: 12px; }
        .badge-vermelho   { background-color: #dc3545; color: white; }
        .badge-laranja    { background-color: #fd7e14; color: white; }
        .badge-amarelo    { background-color: #ffc107; color: black; }
        .badge-verde      { background-color: #28a745; color: white; }
        .badge-secondary  { background-color: #6c757d; color: white; }
        .corretor-muito-inativo { background-color: #fff5f5 !important; }
        .corretor-inativo       { background-color: #fff9e6 !important; }
        .meses-indicator { height: 6px; border-radius: 3px; margin-top: 5px; background: #e9ecef; }
        .header-section { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; color: white; }
        .metric-card { padding: 15px; border-radius: 8px; color: white; text-align: center; margin-bottom: 15px; }
        .metric-value { font-size: 1.8rem; font-weight: bold; margin: 5px 0; }
        .metric-label { font-size: 0.9rem; opacity: 0.9; }
        /* ... mantenha os outros estilos que você tinha ... */
    </style>
</head>
<body>

<div class="container mt-4">

    <!-- Header -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2"><i class="fas fa-users me-2"></i>Relatório de Corretores Ativos</h1>
                <p class="mb-0">Diretoria: <strong><?= htmlspecialchars($_SESSION['NOME_DIRETORIA']  ?? 'N/D') ?></strong></p>
                <p class="mb-0 mt-2">
                    <small><i class="fas fa-info-circle me-1"></i>Lista de corretores ativos com histórico de vendas</small>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="gestao_vendas_diretoria2.php" class="btn btn-light">
                    <i class="fas fa-chart-bar me-2"></i>Voltar ao Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Métricas -->
    <div class="row mb-4">
        <div class="col-md-3"><div class="metric-card" style="background:#3498db;">
            <div class="metric-label"><i class="fas fa-users me-2"></i>Ativos</div>
            <div class="metric-value"><?= $totalCorretores ?></div>
            <small>Corretores</small>
        </div></div>
        <div class="col-md-3"><div class="metric-card" style="background:#2ecc71;">
            <div class="metric-label"><i class="fas fa-check-circle me-2"></i>Vendendo</div>
            <div class="metric-value"><?= $corretoresAtivos ?></div>
            <small>0-2 meses sem vender</small>
        </div></div>
        <div class="col-md-3"><div class="metric-card" style="background:#e74c3c;">
            <div class="metric-label"><i class="fas fa-user-clock me-2"></i>Inativos/Atenção</div>
            <div class="metric-value"><?= $totalCorretores - $corretoresAtivos ?></div>
            <small>3+ meses sem vender</small>
        </div></div>
        <div class="col-md-3"><div class="metric-card" style="background:#6c757d;">
            <div class="metric-label"><i class="fas fa-question-circle me-2"></i>Sem Vendas</div>
            <div class="metric-value"><?= $corretoresSemVendas ?></div>
            <small>Nenhuma venda registrada</small>
        </div></div>
    </div>

    <!-- Aqui continua o restante do HTML com filtros, tabela, resumo por gerência, etc. -->
    <!-- Recomendo copiar o HTML que você já tem e substituir as partes dinâmicas ASP pelas variáveis PHP -->

    <!-- Exemplo da tabela (continuação) -->
    <?php if ($totalCorretores > 0): ?>
    <div class="card">
        <div class="card-header text-white" style="background:#f39c12;">
            <i class="fas fa-table me-2"></i>Lista de Corretores Ativos
            <span class="badge bg-light text-dark ms-2">Mostrando <?= $totalCorretores ?> corretores</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaCorretores">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Corretor</th>
                            <th class="text-center">Meses sem Vender</th>
                            <th class="text-center">Gerência Atual</th>
                            <th class="text-end">Total VGV</th>
                            <th class="text-end">Total Vendas</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($corretoresDados as $i => $c): ?>
                        <?php
                        $rowClass = $c['mesesSem'] >= 12 ? 'corretor-muito-inativo' : ($c['mesesSem'] >= 6 ? 'corretor-inativo' : '');
                        $vgvFmt = $c['totalVGV'] >= 1000000 ? 'R$ ' . number_format($c['totalVGV']/1000000, 2, ',', '.') . ' M' :
                                 ($c['totalVGV'] >= 1000 ? 'R$ ' . number_format($c['totalVGV']/1000, 0, ',', '.') . ' mil' :
                                  'R$ ' . number_format($c['totalVGV'], 0, ',', '.'));
                        ?>
                        <tr class="<?= $rowClass ?>" data-status="<?= $c['statusTexto'] ?>" data-gerencia="<?= htmlspecialchars($c['gerencia']) ?>">
                            <td class="fw-bold"><?= $i+1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c['nome']) ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-building me-1"></i>
                                    <?= $c['gerencia'] ? htmlspecialchars($c['gerencia']) : 'Gerência não informada' ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <?php if ($c['mesesSem'] !== 999): ?>
                                    <div class="fw-bold <?= $c['statusClass'] ?> p-2 rounded">
                                        <?= $c['mesesSem'] === 0 ? 'Este mês' : $c['mesesSem'] . ' meses' ?>
                                    </div>
                                <?php else: ?>
                                    <div class="fw-bold badge-secondary p-2 rounded">Sem vendas</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?= $c['gerencia'] ? '<span class="badge bg-info text-white">' . htmlspecialchars($c['gerencia']) . '</span>' : '<span class="text-muted">-</span>' ?>
                                <br><small><span class="badge <?= $c['statusClass'] ?> badge-status"><?= $c['statusTexto'] ?></span></small>
                            </td>
                            <td class="text-end">
                                <div class="fw-bold"><?= $vgvFmt ?></div>
                            </td>
                            <td class="text-end">
                                <div class="fw-bold"><?= number_format($c['totalVendas'], 0, ',', '.') ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning text-center">
        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
        <h5>Nenhum corretor ativo encontrado</h5>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Coloque aqui o JavaScript de filtro/busca que você já tinha no ASP (adaptado para JS moderno) -->


<script>
// Variáveis globais para controle dos filtros
let filtroStatusAtivo = '';
let filtroGerenciaAtiva = '';

// Função para filtrar tabela por status
function filtrarTabela(status) {
    filtroStatusAtivo = status;
    aplicarFiltros();
}

// Função para filtrar tabela por gerência
function filtrarPorGerencia(gerencia) {
    filtroGerenciaAtiva = gerencia;
    aplicarFiltros();
}

// Função principal que aplica os filtros combinados
function aplicarFiltros() {
    const rows = document.querySelectorAll('#tabelaCorretores tbody tr');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const rowStatus = row.getAttribute('data-status');
        const rowGerencia = row.getAttribute('data-gerencia') || '';

        let statusMatch = true;
        let gerenciaMatch = true;

        // Filtro de status
        if (filtroStatusAtivo && filtroStatusAtivo !== 'todos' && filtroStatusAtivo !== '') {
            if (filtroStatusAtivo === 'Inativo') {
                // "Inativo" agrupa Atenção + Inativo + Muito Inativo
                statusMatch = rowStatus === 'Atenção' || 
                              rowStatus === 'Inativo' || 
                              rowStatus === 'Muito Inativo';
            } else {
                statusMatch = rowStatus === filtroStatusAtivo;
            }
        }

        // Filtro de gerência
        if (filtroGerenciaAtiva && filtroGerenciaAtiva !== 'todas' && filtroGerenciaAtiva !== '') {
            gerenciaMatch = rowGerencia === filtroGerenciaAtiva;
        }

        if (statusMatch && gerenciaMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Atualizar contador principal
    const contadorEl = document.getElementById('contador');
    if (contadorEl) {
        contadorEl.textContent = `Mostrando ${visibleCount} corretores`;
    }

    // Atualizar badge de filtro ativo
    const filtroAtivoBadge = document.getElementById('filtroAtivo');
    let filtroTexto = '';

    if (filtroStatusAtivo && filtroStatusAtivo !== 'todos' && filtroStatusAtivo !== '') {
        filtroTexto += `Status: ${filtroStatusAtivo} `;
    }

    if (filtroGerenciaAtiva && filtroGerenciaAtiva !== 'todas' && filtroGerenciaAtiva !== '') {
        filtroTexto += `Gerência: ${filtroGerenciaAtiva}`;
    }

    if (filtroAtivoBadge) {
        if (filtroTexto.trim()) {
            filtroAtivoBadge.textContent = filtroTexto.trim();
            filtroAtivoBadge.style.display = 'inline';
        } else {
            filtroAtivoBadge.style.display = 'none';
        }
    }

    // Renumerar as linhas visíveis
    updateRowNumbers();
}

// Limpar todos os filtros
function limparFiltros() {
    filtroStatusAtivo = '';
    filtroGerenciaAtiva = '';
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';

    const rows = document.querySelectorAll('#tabelaCorretores tbody tr');
    rows.forEach(row => row.style.display = '');

    const contadorEl = document.getElementById('contador');
    if (contadorEl) {
        contadorEl.textContent = `Mostrando <?php echo $totalCorretores; ?> corretores`;
    }

    const filtroAtivoBadge = document.getElementById('filtroAtivo');
    if (filtroAtivoBadge) filtroAtivoBadge.style.display = 'none';

    updateRowNumbers();
}

// Renumera a coluna # das linhas visíveis
function updateRowNumbers() {
    const visibleRows = document.querySelectorAll('#tabelaCorretores tbody tr:not([style*="display: none"])');
    let count = 1;

    visibleRows.forEach(row => {
        const firstTd = row.querySelector('td:first-child');
        if (firstTd) {
            firstTd.textContent = count;
            count++;
        }
    });
}

// Busca em tempo real (keyup)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#tabelaCorretores tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowGerencia = row.getAttribute('data-gerencia') || '';

                let statusMatch = true;
                let gerenciaMatch = true;

                // Manter os filtros ativos
                if (filtroStatusAtivo && filtroStatusAtivo !== 'todos') {
                    if (filtroStatusAtivo === 'Inativo') {
                        statusMatch = ['Atenção', 'Inativo', 'Muito Inativo'].includes(rowStatus);
                    } else {
                        statusMatch = rowStatus === filtroStatusAtivo;
                    }
                }

                if (filtroGerenciaAtiva && filtroGerenciaAtiva !== 'todas') {
                    gerenciaMatch = rowGerencia === filtroGerenciaAtiva;
                }

                // Filtro de texto
                const text = row.textContent.toLowerCase();
                const searchMatch = text.includes(filter);

                if (statusMatch && gerenciaMatch && searchMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const contadorEl = document.getElementById('contador');
            if (contadorEl) {
                contadorEl.textContent = `Mostrando ${visibleCount} corretores`;
            }

            updateRowNumbers();
        });
    }

    // Inicializa numeração
    updateRowNumbers();
});

// Exportar para Excel (client-side)
function exportarParaExcel() {
    let tableHTML = '<table border="1">';

    // Cabeçalho
    tableHTML += '<tr>';
    document.querySelectorAll('#tabelaCorretores thead th').forEach(th => {
        tableHTML += `<th>${th.textContent}</th>`;
    });
    tableHTML += '</tr>';

    // Apenas linhas visíveis
    document.querySelectorAll('#tabelaCorretores tbody tr:not([style*="display: none"])').forEach(row => {
        tableHTML += '<tr>';
        row.querySelectorAll('td').forEach(td => {
            let content = td.innerHTML
                .replace(/<[^>]*>/g, '')     // remove tags HTML
                .replace(/&nbsp;/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            tableHTML += `<td>${content}</td>`;
        });
        tableHTML += '</tr>';
    });

    tableHTML += '</table>';

    const blob = new Blob([tableHTML], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Corretores_Ativos_${new Date().toISOString().slice(0,10)}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Imprimir página
function imprimirRelatorio() {
    window.print();
}
</script>
</body>
</html>
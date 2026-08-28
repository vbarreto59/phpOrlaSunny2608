<?php
// #####################################################
// Relatório de Comissões - Versão PDO/PHP
// Conversão do ASP clássico
// #####################################################

session_start();

// Evitar cache
header("Expires: -1");
header("Pragma: no-cache");
header("Cache-Control: no-store, must-revalidate");

// Incluir conexão e funções
require_once "conn.php";       // contém $host, $usuario, $senha, $banco
require_once "usr_acoes.php";  // autenticação (se existir)

// Enviar e-mail de acesso (fora do localhost)
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    $to = "valterpb@gmail.com, valterpb@hotmail.com";
    $subject = "SV-DIR-COMISSAO-" . strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO') .
               " - " . $_SERVER['REMOTE_ADDR'] . " - " . date('d/m/Y') . " - " . date('H:i:s');
    $message = "Desempenho dos Corretores. " . strtoupper($_SESSION['Usuario'] ?? 'ANÔNIMO');
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    @mail($to, $subject, $message, $headers);
}

// =======================================================
// Diretoria da sessão
$diretoriaSessao = $_SESSION['Dir_NomeDiretoria'] ?? '';


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


// Filtros via GET
$filtroAno      = $_GET['filtroAno'] ?? '';
$filtroMes      = $_GET['filtroMes'] ?? '';
$filtroGerencia = $_GET['filtroGerencia'] ?? '';
$filtroCorretor = $_GET['filtroCorretor'] ?? '';

// Conexão PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>ERRO DE CONEXÃO: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Meses em português
$mesesNomes = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// Função auxiliar (SafeCDbl)
function safeCdbl($value) {
    return is_numeric($value) ? (float)$value : 0.0;
}

// Consulta principal
$sql = "
    SELECT 
        VT.ID_Venda,
        VT.UserID,
        VT.Nome,
        VT.Diretoria,
        VT.Gerencia,
        V.NomeEmpreendimento,
        V.AnoVenda,
        V.MesVenda,
        V.Gerencia AS GerenciaVenda,
        V.Corretor,
        SUM(VT.VTotal) AS TotalComissaoDevida,
        SUM(VT.VBruto) AS VBrutoConsolidado,
        COALESCE((
            SELECT SUM(ValorPago) 
            FROM pagamentos_comissoes 
            WHERE UsuariosUserId = VT.UserID 
              AND ID_Venda = VT.ID_Venda
        ), 0) AS ValorPagoPorVenda
    FROM venda_temp VT
    LEFT JOIN vendas V ON VT.ID_Venda = V.ID
    WHERE VT.Diretoria = :diretoria
";

$params = [':diretoria' => $diretoriaSessao];

// Filtros
if ($filtroAno !== '' && is_numeric($filtroAno)) {
    $sql .= " AND V.AnoVenda = :ano";
    $params[':ano'] = $filtroAno;
}
if ($filtroMes !== '' && is_numeric($filtroMes)) {
    $sql .= " AND V.MesVenda = :mes";
    $params[':mes'] = $filtroMes;
}
if ($filtroGerencia !== '' && $filtroGerencia !== 'TODOS') {
    $sql .= " AND VT.Gerencia = :gerencia";
    $params[':gerencia'] = $filtroGerencia;
}
if ($filtroCorretor !== '' && $filtroCorretor !== 'TODOS') {
    $sql .= " AND VT.Nome = :corretor";
    $params[':corretor'] = $filtroCorretor;
}

$sql .= "
    GROUP BY 
        VT.ID_Venda, VT.UserID, VT.Nome, VT.Diretoria, VT.Gerencia,
        V.NomeEmpreendimento, V.AnoVenda, V.MesVenda, V.Gerencia, V.Corretor
    ORDER BY 
        VT.Gerencia ASC, VT.Nome ASC, VT.ID_Venda DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasRecords = !empty($rows);

// Totais
$totalComissoesGeral = $totalPagoGeral = $totalVBrutoGeral = $totalSaldoGeral = 0;
$contadorVendas = 0;

if ($hasRecords) {
    foreach ($rows as $row) {
        $vComissao = safeCdbl($row['TotalComissaoDevida']);
        $vPago     = safeCdbl($row['ValorPagoPorVenda']);
        $vBruto    = safeCdbl($row['VBrutoConsolidado']);
        
        $totalComissoesGeral += $vComissao;
        $totalPagoGeral      += $vPago;
        $totalVBrutoGeral    += $vBruto;
        $contadorVendas++;
    }
    $totalSaldoGeral = $totalComissoesGeral - $totalPagoGeral;
}

// Filtros disponíveis (anos, meses, gerências, corretores)
$anos       = [];
$meses      = [];
$gerencias  = [];
$corretores = [];

// Anos
$sqlAnos = "
    SELECT DISTINCT V.AnoVenda 
    FROM venda_temp VT 
    LEFT JOIN vendas V ON VT.ID_Venda = V.ID 
    WHERE VT.Diretoria = :dir 
      AND V.AnoVenda IS NOT NULL 
    ORDER BY V.AnoVenda DESC
";
$stmtAnos = $pdo->prepare($sqlAnos);
$stmtAnos->execute([':dir' => $diretoriaSessao]);
$anos = $stmtAnos->fetchAll(PDO::FETCH_COLUMN);

// Meses
$sqlMeses = "
    SELECT DISTINCT V.MesVenda 
    FROM venda_temp VT 
    LEFT JOIN vendas V ON VT.ID_Venda = V.ID 
    WHERE VT.Diretoria = :dir 
      AND V.MesVenda IS NOT NULL 
    ORDER BY V.MesVenda
";
$stmtMeses = $pdo->prepare($sqlMeses);
$stmtMeses->execute([':dir' => $diretoriaSessao]);
$meses = $stmtMeses->fetchAll(PDO::FETCH_COLUMN);

// Gerências
$sqlGer = "SELECT DISTINCT Gerencia FROM venda_temp WHERE Diretoria = :dir AND Gerencia IS NOT NULL ORDER BY Gerencia ASC";
$stmtGer = $pdo->prepare($sqlGer);
$stmtGer->execute([':dir' => $diretoriaSessao]);
$gerencias = $stmtGer->fetchAll(PDO::FETCH_COLUMN);

// Corretores
$sqlCor = "SELECT DISTINCT Nome FROM venda_temp WHERE Diretoria = :dir AND Nome IS NOT NULL ORDER BY Nome ASC";
$stmtCor = $pdo->prepare($sqlCor);
$stmtCor->execute([':dir' => $diretoriaSessao]);
$corretores = $stmtCor->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Comissões - <?= htmlspecialchars($diretoriaSessao) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Seu CSS completo aqui (copie do original) -->
    <style>
        /* Cole aqui todo o seu <style> do código ASP, mantendo exatamente igual */
        body { font-size: 14px; font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }

    </style>
    <style>
/* KPI Cards - Versão Compacta */
.kpi-card {
    border: none;
    border-radius: 16px !important;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0,0,0,0.10);
    transition: all 0.25s ease;
    height: 90%;
}

.kpi-card:hover {
    transform: translateY(-5px);           /* menos elevação */
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.card-content {
    padding: 1.3rem 1.1rem;                /* REDUZIDO: menos padding interno */
    text-align: center;
}

.icon-wrapper {
    width: 30px;                           /* REDUZIDO: círculo menor */
    height: 30px;
    background: rgba(255,255,255,0.20);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.8rem;
    font-size: 1.7rem;                     /* ícone menor */
    color: white;
}

.metric-value {
    font-size: 1.0rem;                    /* REDUZIDO: valor principal menor */
    font-weight: 800;
    color: white;
    line-height: 1;
    margin: 0.3rem 0 0.2rem;
    letter-spacing: -0.5px;
}

.metric-label {
    font-size: 0.65rem;                    /* label um pouco menor */
    font-weight: 200;
    color: rgba(255,255,255,0.95);
    margin-bottom: 0.1rem;
}

.metric-subtitle {
    font-size: 0.85rem;                    /* subtitle menor */
    color: rgba(255,255,255,0.80);
    font-weight: 200;
}

/* Progress bar mais fina e compacta */
.progress {
    height: 4px !important;                /* progress bar mais fina */
    margin-top: 0.6rem;
    border-radius: 10px;
    background: rgba(255,255,255,0.25);
}

.progress-bar {
    background: linear-gradient(90deg, #ffffff, rgba(255,255,255,0.7)) !important;
}

/* Gradientes mantidos */
.gradient-green   { background: linear-gradient(135deg, #28a745 0%, #2ecc71 100%); }
.gradient-blue    { background: linear-gradient(135deg, #0d6efd 0%, #00c4ff 100%); }
.gradient-red     { background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%); }
.gradient-gray    { background: linear-gradient(135deg, #6c757d 0%, #7f8c8d 100%); }
.gradient-purple  { background: linear-gradient(135deg, #6f42c1 0%, #9b59b6 100%); }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Header -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2"><i class="fas fa-money-bill-wave me-2"></i>Relatório de Comissões</h1>
                <p class="mb-0"><i class="fas fa-building me-1"></i><strong>Diretoria:</strong> <?= htmlspecialchars($_SESSION['NOME_DIRETORIA'] ) ?></p>
                <p class="mb-0 mt-1 small opacity-75"><i class="fas fa-info-circle me-1"></i>Filtrado automaticamente pela sua diretoria</p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-light btn-sm me-2" onclick="window.print()" title="Imprimir relatório">
                    <i class="fas fa-print me-1"></i>Imprimir
                </button>
                <button class="btn btn-light btn-sm me-2" onclick="exportarParaExcel()" title="Exportar para Excel">
                    <i class="fas fa-file-excel me-1"></i>Excel
                </button>
                <button class="btn btn-light btn-sm" onclick="location.reload()" title="Atualizar dados">
                    <i class="fas fa-sync-alt me-1"></i>Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-card">
        <div class="filter-title"><i class="fas fa-filter me-2"></i>Filtros do Relatório</div>
        <form method="get" action="" id="filtroForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="filtroAno" class="form-label">Ano</label>
                    <select class="form-select form-select-sm" name="filtroAno" id="filtroAno">
                        <option value="">Todos os Anos</option>
                        <?php foreach ($anos as $ano): ?>
                            <option value="<?= $ano ?>" <?= $filtroAno == $ano ? 'selected' : '' ?>><?= $ano ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtroMes" class="form-label">Mês</label>
                    <select class="form-select form-select-sm" name="filtroMes" id="filtroMes">
                        <option value="">Todos os Meses</option>
                        <?php foreach ($meses as $mes): ?>
                            <option value="<?= $mes ?>" <?= $filtroMes == $mes ? 'selected' : '' ?>>
                                <?= $mesesNomes[$mes] ?? 'N/A' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtroGerencia" class="form-label">Gerência</label>
                    <select class="form-select form-select-sm" name="filtroGerencia" id="filtroGerencia">
                        <option value="">Todas as Gerências</option>
                        <?php foreach ($gerencias as $ger): ?>
                            <option value="<?= htmlspecialchars($ger) ?>" <?= $filtroGerencia == $ger ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ger) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtroCorretor" class="form-label">Corretor</label>
                    <select class="form-select form-select-sm" name="filtroCorretor" id="filtroCorretor">
                        <option value="">Todos os Corretores</option>
                        <?php foreach ($corretores as $cor): ?>
                            <option value="<?= htmlspecialchars($cor) ?>" <?= $filtroCorretor == $cor ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cor) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Aplicar Filtros</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="limparFiltros()">
                    <i class="fas fa-times me-1"></i>Limpar Filtros
                </button>
            </div>
        </form>
    </div>

    <!-- Informações de filtro ativo -->
    <div class="info-badge">
        <i class="fas fa-filter text-primary me-2"></i>
        <strong>Filtro ativo:</strong> Diretoria <span class="badge bg-primary"><?= htmlspecialchars($diretoriaSessao) ?></span>
        <?php if ($filtroAno): ?> | Ano <span class="badge bg-secondary"><?= $filtroAno ?></span><?php endif; ?>
        <?php if ($filtroMes): ?> | Mês <span class="badge bg-secondary"><?= $filtroMes ?></span><?php endif; ?>
        <?php if ($filtroGerencia): ?> | Gerência <span class="badge bg-secondary"><?= htmlspecialchars($filtroGerencia) ?></span><?php endif; ?>
        <?php if ($filtroCorretor): ?> | Corretor <span class="badge bg-secondary"><?= htmlspecialchars($filtroCorretor) ?></span><?php endif; ?>
        

    </div>
    <br>
<!-- KPI Cards - Versão Premium / Moderna -->
<!-- KPI Cards Compactos -->
<div class="row g-3 mb-4">  <!-- g-3 = espaçamento menor entre cards -->
    <!-- Card 1 -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <div class="kpi-card gradient-green">
            <div class="card-content">
                <div class="icon-wrapper">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="metric-value">R$ <?= number_format($totalComissoesGeral, 2, ',', '.') ?></div>
                <div class="metric-label">Comissões Devidas</div>
                <div class="metric-subtitle">Total a pagar</div>
            </div>
        </div>
    </div>

    <!-- Card 2 -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <div class="kpi-card gradient-blue">
            <div class="card-content">
                <div class="icon-wrapper">
                    <i class="fas fa-money-check-alt"></i>
                </div>
                <div class="metric-value">R$ <?= number_format($totalPagoGeral, 2, ',', '.') ?></div>
                <div class="metric-label">Valor Pago</div>
                <div class="metric-subtitle">Já liquidado</div>
            </div>
        </div>
    </div>

    <!-- Card 3: Saldo -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <?php
        $saldoClass = $totalSaldoGeral > 0 ? 'gradient-red' :
                      ($totalSaldoGeral < 0 ? 'gradient-gray' : 'gradient-gray');
        $saldoTexto = $totalSaldoGeral > 0 ? 'A PAGAR' :
                      ($totalSaldoGeral < 0 ? 'EXCEDENTE' : 'QUITADO');
        $badgeClass = $totalSaldoGeral > 0 ? 'bg-danger' :
                      ($totalSaldoGeral < 0 ? 'bg-success' : 'bg-secondary');
        ?>
        <div class="kpi-card <?= $saldoClass ?>">
            <div class="card-content">
                <div class="icon-wrapper">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div class="metric-value">R$ <?= number_format($totalSaldoGeral, 2, ',', '.') ?></div>
                <div class="metric-label">Saldo Pendente</div>
                <div class="metric-subtitle">
                    <span class="badge <?= $badgeClass ?>"><?= $saldoTexto ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: % Pago -->
    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
        <?php 
        $percent = $totalComissoesGeral > 0 ? ($totalPagoGeral / $totalComissoesGeral) * 100 : 0;
        ?>
        <div class="kpi-card gradient-purple">
            <div class="card-content">
                <div class="icon-wrapper">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="metric-value"><?= number_format($percent, 1) ?>%</div>
                <div class="metric-label">% Pago</div>
                <div class="progress mt-2">
                    <div class="progress-bar" style="width: <?= $percent ?>%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- final KPIs -->
    <?php if ($hasRecords): ?>
    <!-- Tabela principal -->
    <div class="table-responsive">
        <table id="relatorioTable" class="table table-sm table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">ID Venda</th>
                    <th>Colaborador</th>
                    <th>Gerência</th>
                    <th>Empreendimento</th>
                    <th class="text-center">Ano/Mês</th>
                    <th class="text-end">V. Bruto</th>
                    <th class="text-end">Comissão</th>
                    <th class="text-end">Pago</th>
                    <th class="text-end">Saldo</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $contadorVendas = 0;
            $totalVBrutoFiltro = $totalComissaoFiltro = $totalPagoFiltro = 0;

            foreach ($rows as $row):
                $contadorVendas++;
                $vBruto    = safeCdbl($row['VBrutoConsolidado']);
                $vComissao = safeCdbl($row['TotalComissaoDevida']);
                $vPago     = safeCdbl($row['ValorPagoPorVenda']);
                $vSaldo    = $vComissao - $vPago;

                $totalVBrutoFiltro    += $vBruto;
                $totalComissaoFiltro  += $vComissao;
                $totalPagoFiltro      += $vPago;

                $statusPagamento = $vComissao > 0 && $vPago == 0 ? 'Pendente' :
                                   ($vSaldo <= 0 ? ($vSaldo < 0 ? 'Pago (Exc.)' : 'Pago') : 'Parcial');

                $badgeClass = $vSaldo > 0 ? 'bg-danger' :
                              ($vSaldo < 0 ? 'bg-success' : 'bg-secondary');

                $statusClass = $vComissao > 0 && $vPago == 0 ? 'bg-pendente' :
                               ($vSaldo <= 0 ? 'bg-pago' : 'bg-parcial');
            ?>
                <tr class="<?= $statusClass ?>">
                    <td class="text-center">
                        <span class="badge bg-dark badge-custom"><i class="fas fa-hashtag me-1"></i><?= $row['ID_Venda'] ?></span>
                    </td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($row['Nome']) ?></div>
                        <small class="text-muted"><i class="fas fa-user me-1"></i>ID: <?= $row['UserID'] ?></small>
                    </td>
                    <td>
                        <span class="badge bg-info badge-custom"><i class="fas fa-sitemap me-1"></i><?= htmlspecialchars($row['Gerencia']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($row['NomeEmpreendimento'] ?: 'N/A') ?></td>
                    <td class="text-center">
                        <?php if ($row['AnoVenda'] && $row['MesVenda']): ?>
                            <span class="badge bg-secondary badge-custom"><?= $row['AnoVenda'] ?>/<?= str_pad($row['MesVenda'], 2, '0', STR_PAD_LEFT) ?></span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark badge-custom">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td class="valor-numero text-end"><strong>R$ <?= number_format($vBruto, 2, ',', '.') ?></strong></td>
                    <td class="valor-numero text-end text-primary fw-bold">R$ <?= number_format($vComissao, 2, ',', '.') ?></td>
                    <td class="valor-numero text-end <?= $vPago > 0 ? 'text-success' : 'text-muted' ?> fw-bold">
                        R$ <?= number_format($vPago, 2, ',', '.') ?>
                    </td>
                    <td class="valor-numero text-end <?= $vSaldo > 0 ? 'text-danger' : ($vSaldo < 0 ? 'text-success' : 'text-muted') ?> fw-bold">
                        R$ <?= number_format($vSaldo, 2, ',', '.') ?>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $badgeClass ?> badge-custom"><?= $statusPagamento ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-dark">
                <tr>
                    <td colspan="5" class="text-end"><strong>TOTAIS (<?= $contadorVendas ?> vendas):</strong></td>
                    <td class="valor-numero text-end"><strong>R$ <?= number_format($totalVBrutoFiltro, 2, ',', '.') ?></strong></td>
                    <td class="valor-numero text-end"><strong>R$ <?= number_format($totalComissaoFiltro, 2, ',', '.') ?></strong></td>
                    <td class="valor-numero text-end"><strong>R$ <?= number_format($totalPagoFiltro, 2, ',', '.') ?></strong></td>
                    <td class="valor-numero text-end">
                        <?php
                        $totalSaldoClass = $totalSaldoGeral > 0 ? 'text-warning' : ($totalSaldoGeral < 0 ? 'text-success' : 'text-white');
                        ?>
                        <strong class="<?= $totalSaldoClass ?>">R$ <?= number_format($totalSaldoGeral, 2, ',', '.') ?></strong>
                    </td>
                    <td class="text-center">
                        <?php
                        $totalStatusBadge = $totalSaldoGeral > 0 ? 'bg-warning text-dark' :
                                            ($totalSaldoGeral < 0 ? 'bg-success' : 'bg-secondary');
                        $totalStatusText  = $totalSaldoGeral > 0 ? 'PENDENTE' :
                                            ($totalSaldoGeral < 0 ? 'SUPERAVIT' : 'QUITADO');
                        ?>
                        <span class="badge <?= $totalStatusBadge ?> badge-custom"><?= $totalStatusText ?></span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php else: ?>
    <div class="alert alert-info text-center py-5">
        <i class="fas fa-database fa-3x mb-4 text-info"></i>
        <h4 class="fw-bold text-info">Nenhuma comissão encontrada</h4>
        <p>Não há registros para os filtros aplicados na diretoria <strong><?= htmlspecialchars($diretoriaSessao) ?></strong>.</p>
    </div>
    <?php endif; ?>

    <!-- Rodapé -->
    <div class="footer-info">
        <div class="row">
            <div class="col-md-6">© Relatório gerado automaticamente pelo sistema</div>
            <div class="col-md-6 text-end">
                Diretoria: <?= htmlspecialchars($diretoriaSessao) ?> | <?= date('d/m/Y H:i') ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Limpar filtros
function limparFiltros() {
    document.querySelectorAll('#filtroForm select').forEach(sel => sel.value = '');
    document.getElementById('filtroForm').submit();
}

// Exportar para Excel (adaptado)
function exportarParaExcel() {
    let html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    html += '<head><meta charset="UTF-8"><style>td {mso-number-format:"\\@";} .valor {mso-number-format:"R\\$ #,##0.00";}</style></head><body>';
    html += '<table border="1">';
    html += '<tr><th colspan="10" style="background:#2c3e50;color:white;">Relatório de Comissões - <?= addslashes($diretoriaSessao) ?></th></tr>';
    html += '<tr><td colspan="10">Gerado em: <?= date('d/m/Y H:i') ?></td></tr>';
    // ... (adapte o resto conforme seu JS original)
    // Você pode expandir para incluir os dados da tabela como no original
    html += '</table></body></html>';

    const blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'Comissoes_<?= str_replace(' ', '_', addslashes($diretoriaSessao)) ?>_<?= date('Ymd') ?>.xls';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>
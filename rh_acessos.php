<?php
// relatorio_acessos.php
session_start();
require_once 'conn.php';

// ------------envio de email de login'
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if ($remoteAddr !== "127.0.0.1" && $remoteAddr !== "::1") {
    $to = "sendmail@sunnyimob.com.br, valterpb@hotmail.com";
    $subject = "SI-RH-Acessos-" . strtoupper($_SESSION['Usuario'] ?? '') . " - " . $remoteAddr . " - " . date('Y-m-d') . " - " . date('H:i:s');
    $message = "Verificação de logins";
    $headers = "From: sendmail@sunnyimob.com.br\r\n";
    // mail($to, $subject, $message, $headers);
}

// Verificar login
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// Atualização dos campos
try {
    $sqlAtualiza = "UPDATE histatulog
                    INNER JOIN empreendimento ON
                        histatulog.IdEmpresa = empreendimento.Empresa_Id AND
                        histatulog.IdEmpreend = empreendimento.Empreend_ID
                    SET
                        histatulog.Empreendimento = empreendimento.NomeEmpreendimento,
                        histatulog.Empresa = empreendimento.NomeEmpresa";
    $stmtAtualiza = $conn->prepare($sqlAtualiza);
    $stmtAtualiza->execute();
} catch(PDOException $e) {
    error_log("Erro na atualização: " . $e->getMessage());
}

// Array de meses
$meses_br = [
    1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
    7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'
];

// 1. Acessos por Ano/Mês/Usuário
try {
    $sqlAcessosPorUsuarioMes = "SELECT YEAR(T1.DataHora) AS Ano,
                                       MONTH(T1.DataHora) AS Mes,
                                       T1.Usuario,
                                       COUNT(*) AS TotalAcessos
                                FROM histatulog T1
                                INNER JOIN usuarios T2 ON T1.IdUser = T2.UserId
                                WHERE T1.Usuario IS NOT NULL
                                  AND T1.Usuario <> ''
                                  AND T2.IdEmp = 2
                                  AND T2.Ativo = 1
                                GROUP BY Ano, Mes, T1.Usuario
                                ORDER BY Ano DESC, Mes DESC, T1.Usuario ASC";
    $stmtAcessos = $conn->prepare($sqlAcessosPorUsuarioMes);
    $stmtAcessos->execute();
    $acessosPorUsuarioMes = $stmtAcessos->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("<div class='alert alert-danger'>Erro na consulta de acessos: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Total Geral + Hoje
$totalGeral = !empty($acessosPorUsuarioMes) ? array_sum(array_column($acessosPorUsuarioMes, 'TotalAcessos')) : 0;

$totalHoje = 0;
try {
    $sqlTotalHoje = "SELECT COUNT(*) AS total_hoje
                     FROM histatulog T1
                     INNER JOIN usuarios T2 ON T1.IdUser = T2.UserId
                     WHERE T1.Usuario IS NOT NULL
                       AND T1.Usuario <> ''
                       AND T2.IdEmp = 2
                       AND T2.Ativo = 1
                       AND DATE(T1.DataHora) = CURDATE()";
    $stmtHoje = $conn->prepare($sqlTotalHoje);
    $stmtHoje->execute();
    $totalHoje = $stmtHoje->fetch(PDO::FETCH_ASSOC)['total_hoje'] ?? 0;
} catch(PDOException $e) {
    error_log("Erro total hoje: " . $e->getMessage());
}

// NOVA TABELA: Total por dia - últimos 30 dias (ordenado decrescente por data)
$acessosUltimos30Dias = [];
try {
    $sqlUltimos30Dias = "SELECT DATE(T1.DataHora) AS DataAcesso,
                                COUNT(*) AS TotalDia
                         FROM histatulog T1
                         INNER JOIN usuarios T2 ON T1.IdUser = T2.UserId
                         WHERE T1.Usuario IS NOT NULL
                           AND T1.Usuario <> ''
                           AND T2.IdEmp = 2
                           AND T2.Ativo = 1
                           AND T1.DataHora >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         GROUP BY DataAcesso
                         ORDER BY DataAcesso DESC";   // ← ORDEM DECRESCENTE DE DATA
    $stmt30 = $conn->prepare($sqlUltimos30Dias);
    $stmt30->execute();
    $acessosUltimos30Dias = $stmt30->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erro consulta últimos 30 dias: " . $e->getMessage());
}

// Usuários sem acesso
$usuariosSemAcesso = [];
try {
    $sqlUsuariosSemAcesso = "SELECT DISTINCT T1.UserId, T1.Usuario, T1.Gerencia, T1.Nome 
                             FROM usuarios T1
                             LEFT JOIN histatulog T2 ON T1.UserId = T2.IdUser
                             WHERE T2.Usuario IS NULL
                               AND T1.IdEmp = 2
                               AND T1.Ativo = 1
                             ORDER BY T1.Usuario ASC";
    $stmtUsuariosSemAcesso = $conn->prepare($sqlUsuariosSemAcesso);
    $stmtUsuariosSemAcesso->execute();
    $usuariosSemAcesso = $stmtUsuariosSemAcesso->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erro consulta sem acesso: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Relatório de Acessos e Ausentes</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body { padding: 20px; background-color: #f8f9fa; }
    .table-container { background-color: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
    h2 { margin-bottom: 20px; color: #343a40; }
    .btn-voltar { margin-bottom: 20px; }
  </style>
</head>
<body>
  <div class="container">

    <a href="javascript:history.back()" class="btn btn-secondary btn-voltar">
      <i class="fas fa-arrow-left"></i> Voltar
    </a>

    <!-- Tabela 1: Acessos por Ano/Mês/Usuário -->
    <div class="table-container">
      <h2><i class="fas fa-chart-line text-primary"></i> Acessos por Ano, Mês e Usuário</h2>
      <table id="tabelaAcessosPorUsuarioMes" class="table table-striped table-hover" style="width:100%;">
        <thead class="table-dark">
          <tr>
            <th class="text-center">Ano</th>
            <th class="text-center">Mês</th>
            <th>Usuário</th>
            <th class="text-center">Total de Acessos</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($acessosPorUsuarioMes)): ?>
            <?php foreach ($acessosPorUsuarioMes as $acesso): ?>
              <tr>
                <td class="text-center"><?= htmlspecialchars($acesso['Ano']) ?></td>
                <td class="text-center">
                  <?= htmlspecialchars($acesso['Mes']) ?>
                  <small class="text-muted">(<?= $meses_br[(int)$acesso['Mes']] ?? '---' ?>)</small>
                </td>
                <td><?= htmlspecialchars(strtoupper($acesso['Usuario'])) ?></td>
                <td class="text-center">
                  <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($acesso['TotalAcessos']) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot class="table-dark">
          <tr>
            <td colspan="3" class="text-end fw-bold">Total Geral:</td>
            <td class="text-center"><span class="badge bg-success fs-6"><?= number_format($totalGeral, 0, ',', '.') ?></span></td>
          </tr>
          <tr>
            <td colspan="3" class="text-end fw-bold">Acessos hoje (<?= date('d/m/Y') ?>):</td>
            <td class="text-center"><span class="badge bg-info fs-6"><?= number_format($totalHoje, 0, ',', '.') ?></span></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Tabela do meio: Acessos por dia - últimos 30 dias (já ordenado decrescente) -->
    <?php if (isset($_SESSION['Usuario']) && strtolower($_SESSION['Usuario']) === 'barreto'): ?>
    <div class="table-container">
      <h2><i class="fas fa-calendar-day text-warning"></i> Total de Acessos por Dia (últimos 30 dias)</h2>
      <p class="text-muted">Ordenado do dia mais recente para o mais antigo</p>
      <table id="tabelaUltimos30Dias" class="table table-striped table-hover" style="width:100%;">
        <thead class="table-dark">
          <tr>
            <th class="text-center">Data (yyyy-mm-dd)</th>
            <th class="text-center">Total de Acessos no Dia</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($acessosUltimos30Dias)): ?>
            <?php foreach ($acessosUltimos30Dias as $dia): ?>
              <tr>
                <td class="text-center"><?= htmlspecialchars($dia['DataAcesso']) ?></td>
                <td class="text-center">
                  <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                    <?= number_format($dia['TotalDia'], 0, ',', '.') ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="2" class="text-center text-muted py-4">
                <i class="fas fa-calendar-times fa-2x mb-2"></i><br>
                Nenhum acesso registrado nos últimos 30 dias.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Tabela 3: Usuários sem acesso -->
    <?php if (isset($_SESSION['Usuario']) && strtolower($_SESSION['Usuario']) === 'barreto'): ?>
    <div class="table-container">
      <h2><i class="fas fa-user-slash text-danger"></i> Usuários Sem Registros de Acesso</h2>
      <table id="tabelaUsuariosSemAcesso" class="table table-striped table-hover" style="width:100%;">
        <thead class="table-dark">
          <tr>
            <th>Usuário</th>
            <th>Nome</th>
            <th>Gerência</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($usuariosSemAcesso)): ?>
            <?php foreach ($usuariosSemAcesso as $usuario): ?>
              <tr>
                <td><?= htmlspecialchars(strtoupper($usuario['Usuario'])) ?></td>
                <td><?= htmlspecialchars(strtoupper($usuario['Nome'])) ?></td>
                <td>
                  <?php if (!empty(trim($usuario['Gerencia'] ?? ''))): ?>
                    <span class="badge bg-info"><?= htmlspecialchars(strtoupper($usuario['Gerencia'])) ?></span>
                  <?php else: ?>
                    <span class="text-muted">Não informada</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="3" class="text-center text-success py-4">Todos os usuários têm registros de acesso.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>

  <script>
    $(document).ready(function() {
      $('#tabelaAcessosPorUsuarioMes').DataTable({
        language: { url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json" },
        responsive: true,
        pageLength: 50,
        order: [[0, 'desc'], [1, 'desc']],
        buttons: ['excel', 'colvis']
      });

      $('#tabelaUltimos30Dias').DataTable({
        language: { url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json" },
        responsive: true,
        pageLength: 31,
        order: [[0, 'desc']],  // garante ordem decrescente por data (coluna 0)
        buttons: ['excel', 'colvis']
      });

      $('#tabelaUsuariosSemAcesso').DataTable({
        language: { url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json" },
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        buttons: ['excel', 'colvis']
      });
    });
  </script>
</body>
</html>

<?php
// Limpeza
$stmtAtualiza = $stmtAcessos = $stmtHoje = $stmt30 = $stmtUsuariosSemAcesso = null;
?>
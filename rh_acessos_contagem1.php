<?php
// relatorio_acessos.php
session_start();
require_once 'conn.php';

// ------------envio de email de login
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

// Atualização dos campos (mantida original)
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

// 1. Acessos por Ano/Mês/Usuário (mantida original)
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

// Total Geral + Hoje (mantido original)
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

// NOVA CONSULTA: Total de acessos com "login" no Historico, agrupado por Ano e Mês
try {
    $sqlAcessosLoginPorAnoMes = "
        SELECT 
            YEAR(DataHora) AS Ano,
            MONTH(DataHora) AS Mes,
            COUNT(*) AS TotalAcessosLogin
        FROM histatulog
        WHERE Historico IS NOT NULL 
          AND LOWER(Historico) LIKE '%login%'
        GROUP BY Ano, Mes
        ORDER BY Ano DESC, Mes DESC
    ";
    $stmtLogin = $conn->prepare($sqlAcessosLoginPorAnoMes);
    $stmtLogin->execute();
    $acessosLoginPorAnoMes = $stmtLogin->fetchAll(PDO::FETCH_ASSOC);

    // Total geral de acessos com "login"
    $totalLoginGeral = !empty($acessosLoginPorAnoMes) ? array_sum(array_column($acessosLoginPorAnoMes, 'TotalAcessosLogin')) : 0;

} catch(PDOException $e) {
    error_log("Erro na consulta de acessos com 'login': " . $e->getMessage());
    $acessosLoginPorAnoMes = [];
    $totalLoginGeral = 0;
}

// ... (o restante das consultas: últimos 30 dias e usuários sem acesso, mantidas iguais)
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
                         ORDER BY DataAcesso DESC";
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
    .table-success { background-color: #d4edda !important; }
  </style>
</head>
<body>
  <div class="container">
    <a href="javascript:history.back()" class="btn btn-secondary btn-voltar">
      <i class="fas fa-arrow-left"></i> Voltar
    </a>

    <!-- Tabela 1: Acessos por Ano/Mês/Usuário (mantida original) -->
    <div class="table-container">
      <h2><i class="fas fa-chart-line text-primary"></i> Acessos por Ano, Mês e Usuário</h2>
      <table id="tabelaAcessosPorUsuarioMes" class="table table-striped table-hover" style="width:100%;">
        <!-- ... conteúdo igual ao original ... -->
      </table>
    </div>

    <!-- NOVA TABELA: Contagem total de acessos com "login" no Historico por Ano e Mês -->
    <div class="table-container">
      <h2><i class="fas fa-key text-success"></i> Total de Acessos com "login" no Histórico (por Ano/Mês)</h2>
      <table id="tabelaAcessosLoginAnoMes" class="table table-striped table-hover table-success" style="width:100%;">
        <thead class="table-dark">
          <tr>
            <th class="text-center">Ano</th>
            <th class="text-center">Mês</th>
            <th class="text-center">Nome do Mês</th>
            <th class="text-center">Total de Acessos com "login"</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($acessosLoginPorAnoMes)): ?>
            <?php foreach ($acessosLoginPorAnoMes as $acesso): ?>
              <tr>
                <td class="text-center"><?= htmlspecialchars($acesso['Ano']) ?></td>
                <td class="text-center"><?= htmlspecialchars($acesso['Mes']) ?></td>
                <td><?= $meses_br[(int)$acesso['Mes']] ?? '---' ?></td>
                <td class="text-center">
                  <span class="badge bg-success rounded-pill fs-6 px-3 py-2">
                    <?= number_format($acesso['TotalAcessosLogin'], 0, ',', '.') ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-4">
                Nenhum registro com a palavra "login" encontrado no campo Histórico.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
        <tfoot class="table-dark">
          <tr>
            <td colspan="3" class="text-end fw-bold">Total Geral com "login":</td>
            <td class="text-center">
              <span class="badge bg-success fs-6"><?= number_format($totalLoginGeral, 0, ',', '.') ?></span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Tabelas restantes mantidas iguais -->
    <!-- ... Tabela últimos 30 dias ... -->
    <!-- ... Tabela usuários sem acesso ... -->

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

      $('#tabelaAcessosLoginAnoMes').DataTable({
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
        order: [[0, 'desc']],
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
$stmtAtualiza = $stmtAcessos = $stmtHoje = $stmt30 = $stmtUsuariosSemAcesso = $stmtLogin = null;
?>
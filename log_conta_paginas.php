<?php
// Configurações de cabeçalho PHP
header('Content-Type: text/html; charset=UTF-8');
require_once 'conn.php';

// Função auxiliar para limpar a URL (equivalente ao CleanUrlPath do ASP)
function cleanUrlPath($url) {
    return parse_url($url, PHP_URL_PATH);
}

// Pegar filtros via GET
$anoSelecionado = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mensagem = filter_input(INPUT_GET, 'msg', FILTER_SANITIZE_SPECIAL_CHARS);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatísticas de Acesso por Mês</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; background-color: #f8f9fa; }
        .container { max-width: 1400px; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); }
        h1 { color: #0d6efd; font-weight: 600; margin-bottom: 30px; }
        .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: #f2f7ff; }
        .badge { font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Estatísticas de Acesso por Mês</h1>
        
        <div class="d-flex justify-content-between mb-4">
            <a href="historico_navegacao.php" class="btn btn-outline-primary">Ver Histórico Completos</a>
            <form method="get" class="d-flex">
                <select name="ano" class="form-select me-2" onchange="this.form.submit()">
                    <option value="">Todos os anos</option>
                    <?php
                    try {
                        $sqlAnos = "SELECT DISTINCT YEAR(Data_Hora_Visita) as Ano FROM historico_navegacao ORDER BY Ano DESC";
                        $stmtAnos = $pdo->query($sqlAnos);
                        while ($rowAno = $stmtAnos->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($rowAno['Ano'] == $anoSelecionado) ? "selected" : "";
                            echo "<option value='{$rowAno['Ano']}' $selected>{$rowAno['Ano']}</option>";
                        }
                    } catch (PDOException $e) {
                        // Silencioso ou log de erro
                    }
                    ?>
                </select>
            </form>
        </div>

        <?php
        // Exibição de Mensagens
        if ($mensagem) {
            $prefix = substr($mensagem, 0, 3);
            $displayMessage = substr($mensagem, 3);
            $alertClass = match($prefix) {
                'suc' => 'alert-success',
                'err' => 'alert-danger',
                default => 'alert-info'
            };
            echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>" . 
                 htmlspecialchars($displayMessage) . 
                 "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
        ?>

        <div class="table-responsive">
            <table id="tabelaEstatisticas" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead class="table-primary">
                    <tr>
                        <th>Ano-Mês</th>
                        <th>Página</th>
                        <th>Total de Acessos</th>
                        <th>Usuários Únicos</th>
                        <th>IPs Únicos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $whereClause = "";
                        $params = [];
                        
                        if ($anoSelecionado) {
                            $whereClause = " WHERE YEAR(Data_Hora_Visita) = :ano ";
                            $params[':ano'] = $anoSelecionado;
                        }

                        // No MySQL usamos DATE_FORMAT para formatar Ano-Mês
                        $sql = "SELECT 
                                    DATE_FORMAT(Data_Hora_Visita, '%Y-%m') as AnoMes, 
                                    Caminho_Pagina as Pagina, 
                                    COUNT(*) as TotalAcessos, 
                                    COUNT(DISTINCT UserID) as UsuariosUnicos, 
                                    COUNT(DISTINCT IP_Usuario) as IPsUnicos 
                                FROM historico_navegacao 
                                $whereClause
                                GROUP BY YEAR(Data_Hora_Visita), MONTH(Data_Hora_Visita), Caminho_Pagina 
                                ORDER BY AnoMes DESC, TotalAcessos DESC";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        if ($stmt->rowCount() == 0) {
                            echo "<tr><td colspan='5' class='text-muted text-center'>Nenhum dado estatístico encontrado.</td></tr>";
                        } else {
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $paginaLimpa = htmlspecialchars(cleanUrlPath($row['Pagina']));
                                ?>
                                <tr>
                                    <td><?= $row['AnoMes'] ?></td>
                                    <td><?= $paginaLimpa ?></td>
                                    <td><span class="badge bg-primary rounded-pill"><?= $row['TotalAcessos'] ?></span></td>
                                    <td><span class="badge bg-success rounded-pill"><?= $row['UsuariosUnicos'] ?></span></td>
                                    <td><span class="badge bg-info rounded-pill"><?= $row['IPsUnicos'] ?></span></td>
                                </tr>
                                <?php
                            }
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='5' class='text-danger text-center'>Erro ao carregar estatísticas: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-center">
            <a href="javascript:history.back()" class="btn btn-secondary btn-lg">Voltar</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tabelaEstatisticas').DataTable({
                "order": [[0, "desc"], [2, "desc"]],
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/2.0.7/i18n/pt-BR.json"
                },
                "pageLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                "responsive": true,
                "columnDefs": [
                    { "type": "num", "targets": [2, 3, 4] }
                ]
            });
        });
    </script>
</body>
</html>
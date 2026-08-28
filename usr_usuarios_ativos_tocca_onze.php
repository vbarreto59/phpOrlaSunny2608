<?php
session_start();
require_once 'conn.php';
require_once 'send_email.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}
$loggedUser = $_SESSION['Usuario'];

try {
    // 1. Identificar IDs de Diretores e Gerentes para a ordenação prioritária
    $idsDiretores = $conn->query("SELECT DISTINCT UserId FROM diretorias WHERE UserId IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $idsGerentes = $conn->query("SELECT DISTINCT UserId FROM gerencias WHERE UserId IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Buscar todos os usuários ATIVOS
    $todosAtivos = $conn->query("SELECT * FROM usuarios WHERE IdEmp = 2 AND Ativo = 1 ORDER BY Usuario ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Mapear Grupos
    $mapaGrupos = $conn->query("SELECT ID_Grupo, Nome_Grupo FROM grupo")->fetchAll(PDO::FETCH_KEY_PAIR);
    $toccaGroupId = array_search('TOCCA_ONZE', $mapaGrupos);

    // 4. Lógica de Separação: Liderança no topo, depois os demais
    $lideranca = [];
    $colaboradores = [];
    $ativosTocca = 0;

    foreach ($todosAtivos as $u) {
        $uid = $u['UserId'] ?? $u['UserID'];
        $isLider = (in_array($uid, $idsDiretores) || in_array($uid, $idsGerentes));
        
        if ($isLider) {
            $lideranca[] = $u;
        } else {
            $colaboradores[] = $u;
        }

        if ($toccaGroupId !== false && $u['GrupoId'] == $toccaGroupId) {
            $ativosTocca++;
        }
    }

    $usuariosFinal = array_merge($lideranca, $colaboradores);
    $totalGeralAtivos = count($usuariosFinal);

    // --- ENVIO DE EMAIL DE NOTIFICAÇÃO ---
    if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
        $assunto = "Usuarios Ativos Tocca Onze - " . strtoupper($loggedUser) . " - " . date('d/m/Y H:i:s');
        $mensagemEmail = "Acesso à página de Contagem de Ativos\n\n";
        $mensagemEmail .= "Título: Usuarios Ativos Tocca Onze\n";
        $mensagemEmail .= "Usuário Logado: " . strtoupper($loggedUser) . "\n";
        $mensagemEmail .= "Total de Usuários Ativos: " . $totalGeralAtivos . "\n";
        $mensagemEmail .= "Usuários no Grupo Tocca Onze: " . $ativosTocca . "\n";
        $mensagemEmail .= "IP de Acesso: " . $_SERVER['REMOTE_ADDR'];

        enviarEmailSGVendas("sendmail@gabnetweb.com.br, valterpb@hotmail.com", $assunto, $mensagemEmail);
    }

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SGVendas | Ativos Grupo TOCCA_ONZE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA (via variáveis CSS)                  */
        /* ============================================================ */
        :root {
            --primary: <?php echo $theme['primary']; ?>;
            --primary-dark: <?php echo $theme['primary_dark']; ?>;
            --secondary: <?php echo $theme['secondary']; ?>;
            --accent: <?php echo $theme['accent']; ?>;
            --bg-body: <?php echo $theme['bg_body']; ?>;
            --text-color: <?php echo $theme['text_color']; ?>;
            --card-bg: <?php echo $theme['card_bg']; ?>;
            --shadow-color: <?php echo $theme['shadow_color']; ?>;
            --btn-bg: <?php echo $theme['btn_bg']; ?>;
            --btn-hover: <?php echo $theme['btn_hover']; ?>;
            --btn-text: <?php echo $theme['btn_text']; ?>;
            --success: #28a745;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        * { font-family: 'Inter', sans-serif; }
        
        body {
            background: var(--bg-body);
            color: var(--text-color);
            padding: 1rem;
            min-height: 100vh;
        }

        /* Cards de estatísticas */
        .stat-card {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            padding: 1.2rem;
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
        }
        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-color);
            opacity: 0.7;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
        }

        /* Container desktop (tabela) */
        .desktop-container {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            padding: 1.5rem;
        }

        /* Tabela */
        .table {
            border-collapse: collapse;
        }
        .table th {
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            font-weight: 700;
            padding: 0.8rem;
        }
        .table td {
            border: 1px solid var(--shadow-color);
            padding: 0.6rem;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background: rgba(0,0,0,0.03);
        }
        .row-lideranca {
            background: rgba(59, 130, 246, 0.08) !important;
        }
        .row-lideranca td {
            border-color: var(--shadow-color);
        }

        .badge-ativo {
            background: #dcfce7;
            color: #166534;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            text-transform: uppercase;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }

        /* Badges de hierarquia */
        .badge.bg-primary {
            background: var(--primary) !important;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .badge.bg-success {
            background: var(--success) !important;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .badge.bg-secondary {
            background: #6c757d !important;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .badge.bg-light {
            background: var(--card-bg) !important;
            color: var(--text-color);
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }

        /* DataTables personalizado */
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--shadow-color);
            border-radius: 30px;
            padding: 0.3rem 1.2rem;
            background: #fff;
            color: var(--text-color);
            transition: all 0.2s;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem;
            background: #fff;
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-color) !important;
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 0.3rem 0.8rem !important;
            margin: 0 2px !important;
            font-weight: 600 !important;
            transition: all 0.15s !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary) !important;
            color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--shadow-color) !important;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }

        /* Cards mobile */
        .user-card {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            padding: 1.2rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            position: relative;
        }
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
        }
        .lider-mark {
            border-left: 6px solid var(--primary);
        }
        .card-index {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--text-color);
            opacity: 0.6;
            background: var(--bg-body);
            padding: 2px 10px;
            border-radius: 20px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .m-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            text-transform: uppercase;
            margin-right: 4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .m-badge-dir {
            background: var(--primary);
            color: #fff;
        }
        .m-badge-ger {
            background: var(--success);
            color: #fff;
        }
        .m-badge-gestao {
            background: #6c757d;
            color: #fff;
        }

        .user-card .fa-user-circle {
            color: var(--primary) !important;
        }

        /* Filtro mobile */
        #filtroMobile {
            border: 2px solid var(--shadow-color);
            border-radius: 30px;
            padding: 0.6rem 1.2rem;
            background: var(--card-bg);
            color: var(--text-color);
            transition: all 0.2s;
        }
        #filtroMobile:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }

        /* Footer */
        .custom-footer {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            border-top: 3px solid var(--shadow-color);
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
            text-align: center;
            margin-top: auto;
        }
        .custom-footer strong {
            color: var(--primary);
        }
        .custom-footer small {
            font-style: italic;
            opacity: 0.7;
        }

        /* Título */
        .page-title {
            color: var(--primary);
            font-weight: 800;
        }
        .page-title small {
            color: var(--text-color);
            opacity: 0.7;
        }

        /* Responsivo */
        @media (max-width: 767px) {
            .desktop-container { display: none; }
            .mobile-container { display: block; }
        }
        @media (min-width: 768px) {
            .mobile-container { display: none; }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="mb-4">
        <h2 class="fw-800 mb-0 page-title">Gestão de Ativos</h2>
        <p class="text-muted">Grupo: <strong>TOCCA_ONZE</strong></p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-label">Ativos:</div>
                <div class="stat-value"><?= $ativosTocca ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <!-- Espaço reservado para futuros KPIs -->
        </div>
    </div>

    <!-- Versão Desktop com DataTable -->
    <div class="desktop-container">
        <table id="tabelaAtivos" class="table table-hover align-middle w-100">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>COLABORADOR</th>
                    <th>HIERARQUIA</th>
                    <th>GRUPO</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; foreach ($usuariosFinal as $u): 
                    $uid = $u['UserId'] ?? $u['UserID'];
                    $eDir = in_array($uid, $idsDiretores);
                    $eGer = in_array($uid, $idsGerentes);
                ?>
                <tr class="<?= ($eDir || $eGer) ? 'row-lideranca' : '' ?>">
                    <td class="fw-bold text-muted"><?= $counter++ ?>º</td>
                    <td><span class="badge bg-light text-dark border">#<?= str_pad($uid, 3, '0', STR_PAD_LEFT) ?></span></td>
                    <td>
                        <div class="fw-bold text-dark"><?= strtoupper($u['Usuario']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($u['Nome']) ?></div>
                    </td>
                    <td>
                        <?php if($eDir): ?><span class="badge bg-primary">DIRETORIA</span><?php endif; ?>
                        <?php if($eGer): ?><span class="badge bg-success ms-1">GERÊNCIA</span><?php endif; ?>
                        <?php if(!$eDir && !$eGer): ?><span class="badge bg-secondary">GESTÃO</span><?php endif; ?>
                    </td>
                    <td><?= $mapaGrupos[$u['GrupoId']] ?? '-' ?></td>
                    <td><span class="badge-ativo">Ativo</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Versão Mobile (Cards) -->
    <div class="mobile-container">
        <input type="text" id="filtroMobile" class="form-control rounded-pill mb-3" placeholder="🔍 Pesquisar colaborador...">
        <div id="listaCards">
            <?php $counterM = 1; foreach ($usuariosFinal as $u): 
                $uid = $u['UserId'] ?? $u['UserID'];
                $eDir = in_array($uid, $idsDiretores);
                $eGer = in_array($uid, $idsGerentes);
                $isLider = ($eDir || $eGer);
            ?>
            <div class="user-card <?= $isLider ? 'lider-mark' : '' ?>" data-nome="<?= strtolower($u['Nome'].' '.$u['Usuario']) ?>">
                <span class="card-index"><?= $counterM++ ?>º registro</span>
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-light rounded-circle p-2 text-primary"><i class="fas fa-user-circle fa-2x"></i></div>
                    <div>
                        <div class="fw-bold text-dark"><?= strtoupper($u['Usuario']) ?></div>
                        <div class="d-flex flex-wrap mt-1 gap-1">
                            <?php if($eDir): ?><span class="m-badge m-badge-dir">Diretor</span><?php endif; ?>
                            <?php if($eGer): ?><span class="m-badge m-badge-ger">Gerente</span><?php endif; ?>
                            <?php if(!$eDir && !$eGer): ?><span class="m-badge m-badge-gestao">Gestão</span><?php endif; ?>
                            <span class="badge-ativo" style="font-size: 0.6rem;">Ativo</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top small">
                    <div class="text-muted mb-1">Nome: <strong><?= htmlspecialchars($u['Nome']) ?></strong></div>
                    <div class="d-flex justify-content-between">
                        <span>ID: #<?= $uid ?></span>
                        <span class="fw-bold" style="color: var(--primary);"><?= $mapaGrupos[$u['GrupoId']] ?? 'Sem Grupo' ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="custom-footer">
    <div style="margin-bottom: 5px;">
        <strong>Sistema SunnyImob</strong> — Valter Barreto
    </div>
    <div>
        <?php 
            $val_licencas = ($ativosTocca - 10) * 30;
            $val_total = $val_licencas + 350; 
            $referencia = ($ativosTocca - 10) . "-" . $val_licencas . "-350-" . $val_total;
            $data_hora = date("d/m/Y H:i");

            echo "<small style='font-style: italic; opacity: 0.7;'>";
            echo "Cód.: <strong>" . $referencia . "</strong>"; 
            echo " • " . $data_hora; 
            echo "</small>"; 
        ?>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    if (window.innerWidth >= 768) {
        $('#tabelaAtivos').DataTable({ 
            language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json" }, 
            pageLength: 50, 
            ordering: false 
        });
    }
    $('#filtroMobile').on('keyup', function() {
        let busca = $(this).val().toLowerCase();
        $('.user-card').each(function() { 
            $(this).toggle($(this).data('nome').includes(busca)); 
        });
    });
});
</script>
</body>
</html>
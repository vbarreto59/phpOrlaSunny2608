<?php
// session_start() PRIMEIRO — antes de qualquer output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';

// Verificação de login
if (empty($_SESSION['Usuario'])) {
    header("Location: login_v3a.php");
    exit();
}

// --- Conexão ---
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
elseif (isset($db) && $db instanceof PDO)      { /* ok */ }
else die("Erro: conexão com o banco não encontrada.");

// --- Tema seguro ---
$themeDefaults = [
    'primary'       => '#800020',
    'primary_dark'  => '#4B0C1D',
    'secondary'     => '#6c757d',
    'accent'        => '#ffc107',
    'bg_body'       => '#f0f2f5',
    'text_color'    => '#1a1a2e',
    'card_bg'       => '#ffffff',
    'shadow_color'  => '#1a1a2e',
    'btn_bg'        => '#800020',
    'btn_hover'     => '#4B0C1D',
    'btn_text'      => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// --- Helper ---
function isCampoVazio($valor) {
    return $valor === null || $valor === '' || $valor === 0 || $valor === '0' || (is_string($valor) && trim($valor) === '');
}

// --- Retorna os campos faltantes, agrupados por categoria ---
function camposFaltantes($emp) {
    $grupos = [];
    $add = function ($grupo, $label, $valor) use (&$grupos) {
        if (isCampoVazio($valor)) {
            $grupos[$grupo][] = $label;
        }
    };

    // Localização
    $add('Localização', 'Localidade', $emp['Localidade']);
    if (!(int)$emp['BeiraMar']) {
        $add('Localização', 'Distância do Mar (m)', $emp['ProximoMar']);
    }

    // Entrega (Removida a checagem de DataEntrega, agora foca em Mês e Ano)
    $add('Entrega', 'Mês de Entrega', $emp['MesEntrega']);
    $add('Entrega', 'Ano de Entrega', $emp['AnoEntrega']);

    // Características
    $add('Características', 'Tipologia',          $emp['Tipologia']);
    $add('Características', 'Vagas de Garagem',   $emp['VagasGaragem']);
    $add('Características', 'Tipo de Garagem',    $emp['TipoGaragem']);
    $add('Características', 'Qtd. de Unidades',   $emp['QTD_Unidades']);

    // Áreas
    $add('Áreas', 'Metragem Mínima', $emp['MetragemMin']);
    $add('Áreas', 'Metragem Máxima', $emp['MetragemMax']);

    // Comercial
    $add('Comercial', 'Comissão de Venda', $emp['ComissaoVenda']);

    // Conteúdo
    $add('Conteúdo', 'Descrição', $emp['Descricao']);

    return $grupos;
}

// --- Carrega e enriquece os empreendimentos ---
$empreendimentos = [];
$error_message   = null;
$totalVerificadosPadrao = 11; // <-- Ajustado de 12 para 11 (removido Data de Entrega)

// Array de meses para formatar a exibição
$mesesAbrev = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];

try {
    $sql = "SELECT e.Empreend_ID, e.NomeEmpreendimento, e.Empresa_Id, e.AnoEntrega, e.MesEntrega,
                   e.BeiraMar, e.Localidade, e.RespCadastro, e.DataEntrega, e.VagasGaragem,
                   e.TipoGaragem, e.MetragemMin, e.MetragemMax, e.Tipologia, e.Descricao,
                   e.ProximoMar, e.QTD_Blocos, e.QTD_Unidades, e.ComissaoVenda,
                   COALESCE(emp.NomeEmpresa, '—') AS NomeEmpresa
            FROM empreendimento e
            LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
            WHERE e.Vendido = 0
            ORDER BY e.RespCadastro, e.NomeEmpreendimento";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $faltantes = camposFaltantes($r);
        $totalCampos = $totalVerificadosPadrao + ((int)$r['BeiraMar'] ? 0 : 1);
        $qtdFaltantes = 0;
        foreach ($faltantes as $g) $qtdFaltantes += count($g);
        $percentual = $totalCampos > 0
            ? round((($totalCampos - $qtdFaltantes) / $totalCampos) * 100)
            : 100;

        // Formatação da data de entrega (Mês/Ano)
        $mesNum = (int)($r['MesEntrega'] ?? 0);
        $anoNum = (int)($r['AnoEntrega'] ?? 0);
        $mesStr = $mesesAbrev[$mesNum] ?? '';
        $anoStr = $anoNum > 0 ? (string)$anoNum : '';
        
        $entregaStr = '';
        if ($mesStr && $anoStr) {
            $entregaStr = "{$mesStr}/{$anoStr}";
        } elseif ($mesStr) {
            $entregaStr = $mesStr;
        } elseif ($anoStr) {
            $entregaStr = $anoStr;
        }

        $empreendimentos[] = [
            'id'              => $r['Empreend_ID'],
            'nome'            => $r['NomeEmpreendimento'],
            'empresa'         => $r['NomeEmpresa'],
            'localidade'      => $r['Localidade'] ?? '',
            'resp'            => trim((string)($r['RespCadastro'] ?? '')) !== '' ? $r['RespCadastro'] : '(sem responsável)',
            'beiraMar'        => (int)$r['BeiraMar'],
            'entrega'         => $entregaStr,
            'faltantes'       => $faltantes,
            'qtdFaltantes'    => $qtdFaltantes,
            'totalCampos'     => $totalCampos,
            'percentual'      => $percentual,
        ];
    }
} catch (PDOException $e) {
    $error_message = "Erro na consulta: " . $e->getMessage();
    error_log("[DADOS EM BRANCO] " . $e->getMessage());
}

// --- Estatísticas gerais ---
$totalEmp        = count($empreendimentos);
$comPendencias   = 0;
$semPendencias   = 0;
$totalFaltantes  = 0;
$somaPercentual  = 0;
foreach ($empreendimentos as $e) {
    if ($e['qtdFaltantes'] > 0) {
        $comPendencias++;
        $totalFaltantes += $e['qtdFaltantes'];
    } else {
        $semPendencias++;
    }
    $somaPercentual += $e['percentual'];
}
$percentualGeral = $totalEmp > 0 ? round($somaPercentual / $totalEmp) : 100;

// --- Agrupar por responsável ---
$porResponsavel = [];
foreach ($empreendimentos as $e) {
    $porResponsavel[$e['resp']][] = $e;
}
ksort($porResponsavel, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Dados em Branco</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?php echo $themeSafe['primary']; ?>;
            --primary-dark: <?php echo $themeSafe['primary_dark']; ?>;
            --secondary: <?php echo $themeSafe['secondary']; ?>;
            --accent: <?php echo $themeSafe['accent']; ?>;
            --bg-body: <?php echo $themeSafe['bg_body']; ?>;
            --text-color: <?php echo $themeSafe['text_color']; ?>;
            --card-bg: <?php echo $themeSafe['card_bg']; ?>;
            --shadow-color: <?php echo $themeSafe['shadow_color']; ?>;
            --btn-bg: <?php echo $themeSafe['btn_bg']; ?>;
            --btn-hover: <?php echo $themeSafe['btn_hover']; ?>;
            --btn-text: <?php echo $themeSafe['btn_text']; ?>;

            --success: #28a745;
            --danger:  #dc3545;
            --warning: #fd7e14;
            --info:    #17a2b8;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            padding: 0;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================ */
        /* HEADER                                                       */
        /* ============================================================ */
        .header-neo {
            background: var(--primary);
            color: #fff;
            padding: 14px 0;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            margin-bottom: 26px;
            position: relative;
        }
        .header-neo::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .header-brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .header-neo .logo-img {
            height: 62px;
            width: auto;
            max-width: 180px;
            min-width: 62px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 5px 8px;
            flex-shrink: 0;
        }
        .header-brand-text h1 {
            font-weight: 900;
            font-size: 1.35rem;
            margin: 0;
            line-height: 1.15;
            letter-spacing: -0.4px;
        }
        .header-brand-text p {
            margin: 4px 0 0;
            font-size: 0.78rem;
            opacity: 0.9;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .header-stats { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
        .stat-badge {
            background: rgba(0,0,0,0.18);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 8px 16px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 82px;
        }
        .stat-badge .num { font-size: 1.3rem; font-weight: 900; line-height: 1; letter-spacing: -0.5px; }
        .stat-badge .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
            font-weight: 700;
            opacity: 0.9;
        }
        .stat-badge.is-success { background: var(--success); }
        .stat-badge.is-warning { background: var(--warning); }
        .stat-badge.is-danger  { background: var(--danger);  }
        .stat-badge.is-info    { background: var(--info);    }

        .btn-neo-close {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 800;
            font-size: 0.88rem;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37;
            color: #fff;
            text-decoration: none;
        }

        /* ============================================================ */
        /* CONTAINER                                                    */
        /* ============================================================ */
        .container-neo { max-width: 1300px; margin: 0 auto; padding: 0 20px 40px; }

        /* ============================================================ */
        /* BARRA DE PROGRESSO GERAL                                     */
        /* ============================================================ */
        .progress-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            padding: 18px 22px;
            margin-bottom: 22px;
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .progress-header strong { font-size: 0.95rem; font-weight: 800; }
        .progress-header .big-num {
            font-size: 1.6rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1;
        }
        .progress-bar-neo {
            height: 18px;
            background: rgba(0,0,0,0.08);
            border: 2px solid var(--shadow-color);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        .progress-bar-neo .fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success), #34ce57);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            font-size: 0.72rem;
            font-weight: 800;
            color: #fff;
            min-width: 40px;
        }
        .progress-bar-neo .fill.warn  { background: linear-gradient(90deg, var(--warning), #ffb020); }
        .progress-bar-neo .fill.dang  { background: linear-gradient(90deg, var(--danger),  #e4606d); }

        /* ============================================================ */
        /* TOOLBAR                                                      */
        /* ============================================================ */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1 1 320px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            flex: 1 1 320px;
            max-width: 480px;
        }
        .search-box input {
            width: 100%;
            background: var(--card-bg);
            color: var(--text-color);
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 11px 46px 11px 46px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .search-box input:focus {
            outline: none;
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .search-box input::placeholder { color: var(--text-color); opacity: 0.5; font-weight: 500; }
        .search-box .icon {
            position: absolute;
            left: 16px;
            color: var(--text-color);
            opacity: 0.6;
            pointer-events: none;
            font-size: 14px;
        }
        .search-box:focus-within .icon { color: var(--primary); opacity: 1; }
        .search-box .clear {
            position: absolute;
            right: 10px;
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 50%;
            width: 26px;
            height: 26px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            padding: 0;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            transition: all 0.12s;
        }
        .search-box .clear.visible { display: flex; }
        .search-box .clear:hover { transform: scale(1.12); background: #b02a37; }

        .neo-switch {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 9px 14px;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--text-color);
            white-space: nowrap;
        }
        .neo-switch:hover {
            transform: translate(-1px, -1px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
        }
        .neo-switch input { display: none; }
        .neo-switch .switch-track {
            width: 40px;
            height: 22px;
            background: #d1d5db;
            border-radius: 999px;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .neo-switch .switch-track::after {
            content: "";
            position: absolute;
            width: 18px;
            height: 18px;
            background: #fff;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .neo-switch input:checked + .switch-track { background: var(--success); }
        .neo-switch input:checked + .switch-track::after { transform: translateX(18px); }
        .neo-switch i { color: var(--success); }

        /* ============================================================ */
        /* SEÇÕES (cabeçalhos de grupo)                                 */
        /* ============================================================ */
        .resp-group {
            margin-bottom: 26px;
        }
        .resp-header {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--primary);
            color: #fff;
            padding: 10px 22px;
            border-radius: 14px;
            border: 3px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            font-weight: 900;
            font-size: 1rem;
            margin-bottom: 16px;
            letter-spacing: -0.2px;
        }
        .resp-header .count {
            background: rgba(255,255,255,0.25);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 800;
            margin-left: 6px;
        }
        .resp-header .pending-tag {
            background: var(--warning);
            color: #000;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            margin-left: 4px;
        }

        /* ============================================================ */
        /* CARDS DE EMPREENDIMENTO                                      */
        /* ============================================================ */
        .emp-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            padding: 16px 20px;
            margin-bottom: 14px;
            transition: all 0.15s;
        }
        .emp-card:hover {
            transform: translateY(-2px);
            box-shadow: 7px 7px 0 0 var(--shadow-color);
        }
        .emp-card.is-complete {
            border-left: 8px solid var(--success);
        }
        .emp-card.is-pending {
            border-left: 8px solid var(--warning);
        }

        .emp-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .emp-info { min-width: 0; flex: 1 1 300px; }
        .emp-nome {
            font-weight: 900;
            font-size: 1.05rem;
            color: var(--text-color);
            line-height: 1.25;
            margin: 0 0 6px 0;
            word-break: break-word;
        }
        .emp-nome .id {
            display: inline-block;
            background: var(--shadow-color);
            color: #fff;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 800;
            margin-right: 8px;
            vertical-align: middle;
        }
        .emp-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 0.78rem;
        }
        .emp-meta .chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(0,0,0,0.06);
            color: var(--text-color);
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .emp-meta .chip i { font-size: 0.7rem; opacity: 0.7; }
        .emp-meta .chip.bm { background: rgba(23,162,184,0.15); color: var(--info); }
        .emp-meta .chip.empresa { background: var(--shadow-color); color: #fff; text-transform: uppercase; }
        
        /* Chip de Entrega */
        .emp-meta .chip.entrega { background: rgba(40,167,69,0.15); color: var(--success); }
        .emp-meta .chip.entrega-empty { background: rgba(220,53,69,0.1); color: var(--danger); font-style: italic; }

        .emp-percentual {
            flex-shrink: 0;
            text-align: right;
            min-width: 90px;
        }
        .emp-percentual .value {
            font-size: 1.6rem;
            font-weight: 900;
            line-height: 1;
            color: var(--primary);
        }
        .emp-percentual .value.ok   { color: var(--success); }
        .emp-percentual .value.warn { color: var(--warning); }
        .emp-percentual .value.bad  { color: var(--danger);  }
        .emp-percentual .lbl {
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            opacity: 0.6;
        }

        /* Barra fina de progresso no card */
        .emp-progress {
            height: 8px;
            background: rgba(0,0,0,0.08);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 12px;
        }
        .emp-progress .fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.4s ease;
        }
        .emp-progress .fill.ok   { background: var(--success); }
        .emp-progress .fill.warn { background: var(--warning); }
        .emp-progress .fill.bad  { background: var(--danger);  }

        /* Grupos de campos faltantes */
        .faltantes-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        .faltantes-group {
            flex: 1 1 200px;
            min-width: 180px;
        }
        .faltantes-group .titulo {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 800;
            color: var(--text-color);
            opacity: 0.65;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .faltantes-group .titulo i { color: var(--primary); }
        .badge-vazio {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--danger);
            color: #fff;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            font-size: 0.74rem;
            margin: 0 4px 4px 0;
            letter-spacing: 0.2px;
        }

        /* Estado completo */
        .emp-complete-msg {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(40,167,69,0.1);
            border: 2px solid var(--success);
            border-radius: 10px;
            padding: 8px 14px;
            color: var(--success);
            font-weight: 800;
            font-size: 0.85rem;
        }

        /* Ações */
        .emp-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed rgba(0,0,0,0.12);
        }
        .btn-neo {
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 7px 14px;
            font-weight: 800;
            font-size: 0.78rem;
            transition: all 0.15s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-neo-edit { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo-edit:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
            text-decoration: none;
        }
        .btn-neo-caract { background: var(--info); color: #fff; }
        .btn-neo-caract:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: #138496;
            color: #fff;
            text-decoration: none;
        }

        /* Vazio */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
        }
        .empty-state i { font-size: 3rem; color: var(--success); margin-bottom: 12px; display: block; }
        .empty-state h3 { font-weight: 900; color: var(--text-color); margin: 0 0 6px; }
        .empty-state p { color: var(--text-color); opacity: 0.65; font-weight: 500; margin: 0; }

        .alert-neo {
            border: 3px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 14px;
            font-weight: 700;
            padding: 16px 22px;
            background: var(--danger);
            color: #fff;
            margin-bottom: 20px;
        }

        /* Hidden helper */
        .hidden-by-filter { display: none !important; }

        /* ============================================================ */
        /* RESPONSIVO                                                   */
        /* ============================================================ */
        @media (max-width: 992px) {
            .header-brand-text h1 { font-size: 1.15rem; }
            .header-neo .logo-img { height: 52px; max-width: 140px; }
        }
        @media (max-width: 768px) {
            .header-brand-text h1 { font-size: 1rem; }
            .header-brand-text p { font-size: 0.7rem; }
            .header-neo .logo-img { height: 44px; max-width: 110px; padding: 3px 6px; }
            .header-stats { width: 100%; }
            .stat-badge { flex: 1; min-width: 0; }
            .btn-neo-close { width: 100%; justify-content: center; }
            .container-neo { padding: 0 12px 30px; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .toolbar-left { flex-basis: 100%; }
            .emp-percentual { text-align: left; min-width: 0; }
            .emp-percentual .value { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<!-- ============================================================ -->
<!-- HEADER                                                       -->
<!-- ============================================================ -->
<header class="header-neo">
    <div class="header-inner">

        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logotipo" class="logo-img">
            <div class="header-brand-text">
                <h1><i class="fas fa-clipboard-check mr-2"></i> Dados em Branco</h1>
                <p>SunnyImob · Auditoria de cadastros</p>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-badge">
                <span class="num"><?php echo $totalEmp; ?></span>
                <span class="label">Total</span>
            </div>
            <div class="stat-badge is-danger">
                <span class="num"><?php echo $comPendencias; ?></span>
                <span class="label">Com pendências</span>
            </div>
            <div class="stat-badge is-success">
                <span class="num"><?php echo $semPendencias; ?></span>
                <span class="label">Completos</span>
            </div>
            <div class="stat-badge is-warning">
                <span class="num"><?php echo $totalFaltantes; ?></span>
                <span class="label">Campos faltando</span>
            </div>
        </div>

        <a href="javascript:window.close()" class="btn-neo-close">
            <i class="fas fa-times"></i> Fechar
        </a>

    </div>
</header>

<!-- ============================================================ -->
<!-- CONTEÚDO                                                     -->
<!-- ============================================================ -->
<div class="container-neo">

    <?php if ($error_message): ?>
        <div class="alert-neo">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($totalEmp === 0 && !$error_message): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h3>Nada para mostrar</h3>
            <p>Não há empreendimentos não vendidos para auditar.</p>
        </div>
    <?php else: ?>

    <!-- Barra de progresso geral -->
    <div class="progress-card">
        <div class="progress-header">
            <div>
                <strong><i class="fas fa-chart-line mr-2" style="color:var(--primary)"></i> Progresso geral de preenchimento</strong>
                <div style="font-size:0.78rem; opacity:0.65; font-weight:600; margin-top:2px;">
                    <?php echo $semPendencias; ?> de <?php echo $totalEmp; ?> empreendimentos com todos os campos preenchidos
                </div>
            </div>
            <div class="big-num"><?php echo $percentualGeral; ?>%</div>
        </div>
        <div class="progress-bar-neo">
            <?php
                $cls = $percentualGeral >= 80 ? '' : ($percentualGeral >= 50 ? 'warn' : 'dang');
            ?>
            <div class="fill <?php echo $cls; ?>" style="width: <?php echo max($percentualGeral, 3); ?>%">
                <?php if ($percentualGeral >= 10): ?><?php echo $percentualGeral; ?>%<?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar por nome, empresa, localidade ou ano de entrega…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <label class="neo-switch" title="Mostrar apenas empreendimentos com campos faltando">
                <input type="checkbox" id="filterOnlyPending" checked>
                <span class="switch-track"></span>
                <span><i class="fas fa-filter"></i> Só com pendências</span>
            </label>
        </div>
    </div>

    <!-- Lista por responsável -->
    <div id="listaContainer">
    <?php foreach ($porResponsavel as $resp => $emps): ?>

        <?php
            $pendingCount = 0;
            foreach ($emps as $e) if ($e['qtdFaltantes'] > 0) $pendingCount++;
        ?>
        <div class="resp-group" data-resp-group="<?php echo htmlspecialchars($resp); ?>">

            <div class="resp-header">
                <i class="fas fa-user-tie"></i>
                <?php echo htmlspecialchars($resp); ?>
                <span class="count"><?php echo count($emps); ?></span>
                <?php if ($pendingCount > 0): ?>
                    <span class="pending-tag"><?php echo $pendingCount; ?> com pendências</span>
                <?php endif; ?>
            </div>

            <?php foreach ($emps as $e): ?>
            <div class="emp-card <?php echo $e['qtdFaltantes'] > 0 ? 'is-pending' : 'is-complete'; ?>"
                 data-pendente="<?php echo $e['qtdFaltantes'] > 0 ? '1' : '0'; ?>"
                 data-search="<?php echo htmlspecialchars(strtolower($e['nome'] . ' ' . $e['empresa'] . ' ' . $e['localidade'] . ' ' . $e['resp'] . ' ' . $e['id'] . ' ' . $e['entrega'])); ?>">

                <div class="emp-top">
                    <div class="emp-info">
                        <h3 class="emp-nome">
                            <span class="id">#<?php echo htmlspecialchars($e['id']); ?></span>
                            <?php echo htmlspecialchars($e['nome']); ?>
                        </h3>
                        <div class="emp-meta">
                            <span class="chip empresa"><i class="fas fa-building"></i> <?php echo htmlspecialchars($e['empresa']); ?></span>
                            
                            <?php if ($e['localidade']): ?>
                                <span class="chip"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($e['localidade']); ?></span>
                            <?php endif; ?>
                            
                            <!-- Exibição do Mês/Ano de Entrega -->
                            <?php if ($e['entrega']): ?>
                                <span class="chip entrega" title="Previsão de Entrega"><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars($e['entrega']); ?></span>
                            <?php else: ?>
                                <span class="chip entrega-empty" title="Sem data de entrega"><i class="fas fa-calendar-times"></i> Sem data</span>
                            <?php endif; ?>

                            <?php if ($e['beiraMar']): ?>
                                <span class="chip bm"><i class="fas fa-umbrella-beach"></i> Beira Mar</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                        $pct = $e['percentual'];
                        $pctCls = $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'bad');
                        $barCls = $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'bad');
                    ?>
                    <div class="emp-percentual">
                        <div class="value <?php echo $pctCls; ?>"><?php echo $pct; ?>%</div>
                        <div class="lbl">Completo</div>
                    </div>
                </div>

                <div class="emp-progress">
                    <div class="fill <?php echo $barCls; ?>" style="width: <?php echo max($pct, 2); ?>%"></div>
                </div>

                <?php if ($e['qtdFaltantes'] > 0): ?>
                    <div class="faltantes-grid">
                        <?php foreach ($e['faltantes'] as $grupo => $labels): ?>
                            <div class="faltantes-group">
                                <div class="titulo">
                                    <?php
                                        $iconGrupo = [
                                            'Localização'     => 'fa-map-marker-alt',
                                            'Entrega'         => 'fa-calendar-alt',
                                            'Características' => 'fa-cogs',
                                            'Áreas'           => 'fa-ruler-combined',
                                            'Comercial'       => 'fa-percentage',
                                            'Conteúdo'        => 'fa-align-left',
                                        ][$grupo] ?? 'fa-tag';
                                    ?>
                                    <i class="fas <?php echo $iconGrupo; ?>"></i> <?php echo $grupo; ?>
                                </div>
                                <?php foreach ($labels as $label): ?>
                                    <span class="badge-vazio"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($label); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="emp-complete-msg">
                        <i class="fas fa-check-circle"></i>
                        Todos os campos estão preenchidos.
                    </div>
                <?php endif; ?>

                <div class="emp-actions">
                    <a href="gestao_empreend_update.php?id=<?php echo urlencode($e['id']); ?>"
                       target="_blank" class="btn-neo btn-neo-edit">
                        <i class="fas fa-edit"></i> Editar Cadastro
                    </a>
                    <a href="gestao_empreend_caract_update2.php?id=<?php echo urlencode($e['id']); ?>"
                       target="_blank" class="btn-neo btn-neo-caract">
                        <i class="fas fa-list-ul"></i> Características
                    </a>
                </div>

            </div>
            <?php endforeach; ?>

        </div>
    <?php endforeach; ?>
    </div>

    <!-- Estado "nada encontrado" via filtro -->
    <div id="emptyFilter" class="empty-state" style="display:none;">
        <i class="fas fa-search"></i>
        <h3>Nenhum resultado</h3>
        <p>Nenhum empreendimento corresponde ao filtro atual.</p>
    </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    var $search      = document.getElementById('searchInput');
    var $clear       = document.getElementById('searchClear');
    var $filterOnly  = document.getElementById('filterOnlyPending');
    var $container   = document.getElementById('listaContainer');
    var $emptyFilter = document.getElementById('emptyFilter');

    if (!$search || !$filterOnly || !$container) return;

    function aplicarFiltros() {
        var termo = ($search.value || '').toLowerCase().trim();
        var onlyPending = $filterOnly.checked;

        var cards = $container.querySelectorAll('.emp-card');
        var grupos = $container.querySelectorAll('.resp-group');
        var totalVisiveis = 0;

        cards.forEach(function (card) {
            var matchTexto = !termo || (card.dataset.search || '').indexOf(termo) !== -1;
            var matchPending = !onlyPending || card.dataset.pendente === '1';
            var visivel = matchTexto && matchPending;

            card.classList.toggle('hidden-by-filter', !visivel);
            if (visivel) totalVisiveis++;
        });

        // Esconde grupos sem cards visíveis
        grupos.forEach(function (grupo) {
            var visiveis = grupo.querySelectorAll('.emp-card:not(.hidden-by-filter)').length;
            grupo.classList.toggle('hidden-by-filter', visiveis === 0);
        });

        if ($emptyFilter) {
            $emptyFilter.style.display = (totalVisiveis === 0 && cards.length > 0) ? 'block' : 'none';
        }
    }

    $search.addEventListener('input', function () {
        $clear.classList.toggle('visible', this.value.length > 0);
        aplicarFiltros();
    });

    $search.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            this.value = '';
            $clear.classList.remove('visible');
            aplicarFiltros();
        }
    });

    $clear.addEventListener('click', function () {
        $search.value = '';
        $clear.classList.remove('visible');
        $search.focus();
        aplicarFiltros();
    });

    $filterOnly.addEventListener('change', aplicarFiltros);

    // Aplica o filtro inicial (só pendentes)
    aplicarFiltros();
})();
</script>
</body>
</html>
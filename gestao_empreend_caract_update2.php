<?php
// gestao_empreend_caract_update2.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';

// Login
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// Conexão
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// Tema seguro
$themeDefaults = [
    'primary' => '#800000', 'primary_dark' => '#4B0C1D',
    'secondary' => '#6c757d', 'accent' => '#ffc107',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800000', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ==================== FUNÇÕES AUXILIARES ====================
function usuarioPodeEditar() {
    return isset($_SESSION['Usuario']) && !empty($_SESSION['Usuario']);
}
function getCheckboxValue($valor) { return ($valor == "on" || $valor === true || $valor === 1) ? 1 : 0; }
function formatarMoedaExibicao($valor) {
    if ($valor === null || $valor === '' || !is_numeric($valor)) return '0,00';
    return number_format((float)$valor, 2, ',', '.');
}
function formatarBytes($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    return $bytes . ' B';
}
function formatarValorResumo($label, $valor) {
    if ($valor === null || $valor === '' || $valor === '(vazio)') return '(vazio)';
    if (is_numeric($valor)) {
        if (in_array($label, ['Valor Cond.', 'Valor IPTU', 'Menor Preço', 'Maior Preço'])) {
            return 'R$ ' . number_format((float)$valor, 2, ',', '.');
        }
        if (in_array($label, ['Metragem Mín.', 'Metragem Máx.'])) {
            return number_format((float)$valor, 2, ',', '.') . ' m²';
        }
    }
    return htmlspecialchars((string)$valor);
}

// ==================== CSRF ====================
if (empty($_SESSION['csrf_carac'])) {
    $_SESSION['csrf_carac'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_carac'];

// ==================== EMAIL ====================
function enviarEmailAtualizacao($empreendimentoId, $nomeEmp, $empresa, $usuario, $alteracoes) {
    $to = "valterpb@gmail.com, valterpb@hotmail.com";
    $subject = "[SI-CARAC] " . $nomeEmp . " - ID: " . $empreendimentoId;

    $headers  = "From: sendmail@sunnyimob.com.br\r\n";
    $headers .= "Reply-To: sendmail@sunnyimob.com.br\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $rows = '';
    foreach ($alteracoes as $campo => $v) {
        $rows .= '<tr><td style="padding:8px;border:1px solid #ddd;"><b>' . htmlspecialchars($campo) . '</b></td>'
              .  '<td style="padding:8px;border:1px solid #ddd;background:#f8d7da;">' . htmlspecialchars($v['antigo']) . '</td>'
              .  '<td style="padding:8px;border:1px solid #ddd;background:#d4edda;">' . htmlspecialchars($v['novo']) . '</td></tr>';
    }

    $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;background:#f4f4f4;padding:20px;}
        .container{background:white;padding:25px;border-radius:8px;max-width:900px;margin:auto;}
        h1{background:#800000;color:white;padding:15px;border-radius:5px;font-size:18px;margin:0 0 20px;}
        table{width:100%;border-collapse:collapse;font-size:13px;}
        th{background:#800000;color:white;padding:10px;text-align:left;}
        .info{background:#f8f9fa;border-left:4px solid #007bff;padding:12px;margin:15px 0;}
    </style></head><body><div class="container">
        <h1>📋 Atualização de Características</h1>
        <div class="info">
            <p><b>ID:</b> ' . $empreendimentoId . ' &nbsp;|&nbsp; <b>Nome:</b> ' . htmlspecialchars($nomeEmp) . '</p>
            <p><b>Empresa:</b> ' . htmlspecialchars($empresa) . ' &nbsp;|&nbsp; <b>Usuário:</b> ' . htmlspecialchars($usuario) . '</p>
            <p><b>IP:</b> ' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . ' &nbsp;|&nbsp; <b>Data:</b> ' . date('d/m/Y H:i:s') . '</p>
        </div>
        <h3>🔍 ' . count($alteracoes) . ' campo(s) alterado(s)</h3>
        <table><thead><tr><th>Campo</th><th>Antigo</th><th>Novo</th></tr></thead><tbody>' . $rows . '</tbody></table>
        <p style="color:#999;font-size:12px;margin-top:20px;text-align:center;">SunnyImob &copy; ' . date('Y') . '</p>
    </div></body></html>';

    return @mail($to, $subject, $body, $headers);
}

// ==================== ID ====================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("ID inválido.");

// ==================== BUSCAR EMPREENDIMENTO (COM CORREÇÃO) ====================
function buscarEmpreendimento($db, $id) {
    $stmt = $db->prepare("
        SELECT e.*, COALESCE(emp.NomeEmpresa, '—') AS NomeEmpresa
        FROM empreendimento e
        LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
        WHERE e.Empreend_ID = :id
    ");
    $stmt->execute([':id' => $id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dados) {
        // Normalização CRÍTICA: garante que chaves com case diferente existam no array
        if (!isset($dados['QTD_Blocos']) && isset($dados['Qtd_Blocos'])) {
            $dados['QTD_Blocos'] = $dados['Qtd_Blocos'];
        }
        if (!isset($dados['QTD_Unidades']) && isset($dados['Qtd_Unidades'])) {
            $dados['QTD_Unidades'] = $dados['Qtd_Unidades'];
        }
        if (!isset($dados['Empresa_ID']) && isset($dados['Empresa_Id'])) {
            $dados['Empresa_ID'] = $dados['Empresa_Id'];
        }
    }
    
    return $dados;
}

try {
    $dadosAntigos = buscarEmpreendimento($db, $id);
    if (!$dadosAntigos) die("Empreendimento não encontrado.");
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

// ==================== PROCESSAR POST ====================
$mensagem = null; $tipoMensagem = null; $emailEnviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (!hash_equals($_SESSION['csrf_carac'] ?? '', $_POST['csrf_token'] ?? '')) {
        $mensagem = "❌ Token de segurança inválido. Recarregue a página.";
        $tipoMensagem = "danger";
    } else {
        $alteracoes = [];
        $setClauses = [];
        $params = [];

        $addCampo = function($campo, $valor, $label) use (&$alteracoes, &$setClauses, &$params, $dadosAntigos) {
            $antigo = $dadosAntigos[$campo] ?? null;
            if ($antigo != $valor) {
                $alteracoes[$label] = ['antigo' => $antigo ?? '(vazio)', 'novo' => $valor ?? '(vazio)'];
            }
            $setClauses[] = "$campo = :$campo";
            $params[":$campo"] = $valor;
        };

        // CAMPOS DE TEXTO
        $localidade  = strtoupper(trim($_POST['Localidade'] ?? ''));
        $tipologia   = strtoupper(trim($_POST['Tipologia'] ?? ''));
        $descricao   = strtoupper(trim($_POST['Descricao'] ?? ''));
        $tipoGaragem = trim($_POST['TipoGaragem'] ?? '');

        if (!empty($localidade) && $localidade != 'X') $addCampo('Localidade', $localidade, 'Localidade');
        if (!empty($tipologia))                        $addCampo('Tipologia', $tipologia, 'Tipologia');
        if (!empty($descricao))                        $addCampo('Descricao', $descricao, 'Descrição');
        if (!empty($tipoGaragem))                      $addCampo('TipoGaragem', $tipoGaragem, 'Tipo Garagem');

        // NUMÉRICOS
        $numericos = [
            'Suites' => 'Suítes', 'WCTotal' => 'Banheiros',
            'VagasGaragem' => 'Vagas Garagem', 'ProximoMar' => 'Dist. Mar (m)',
            'Pavimentos' => 'Pavimentos', 'QTD_Blocos' => 'Qtd. Blocos',
            'QTD_Unidades' => 'Qtd. Unidades', 'MesEntrega' => 'Mês Entrega',
            'AnoEntrega' => 'Ano Entrega',
        ];
        foreach ($numericos as $campo => $label) {
            if (isset($_POST[$campo]) && $_POST[$campo] !== '') {
                $addCampo($campo, intval($_POST[$campo]), $label);
            }
        }

        // MOEDAS E METRAGENS
        $moedas = [
            'ValorCond' => 'Valor Cond.', 'ValorIPTU' => 'Valor IPTU',
            'MenorPreco' => 'Menor Preço', 'MaiorPreco' => 'Maior Preço',
            'MetragemMin' => 'Metragem Mín.', 'MetragemMax' => 'Metragem Máx.',
        ];
        foreach ($moedas as $campo => $label) {
            if (isset($_POST[$campo])) {
                $raw = trim((string)$_POST[$campo]);
                if ($raw === '') {
                    $v = 0;
                } elseif (is_numeric($raw)) {
                    $v = (float)$raw; // JS enviou "500000.00"
                } else {
                    $temp = str_replace('.', '', $raw);
                    $temp = str_replace(',', '.', $temp);
                    $v = is_numeric($temp) ? (float)$temp : 0;
                }
                $addCampo($campo, $v, $label);
            }
        }

        if (!empty($_POST['DataEntrega']) && $_POST['DataEntrega'] != '2035-01-01') {
            $addCampo('DataEntrega', $_POST['DataEntrega'], 'Data Entrega');
        }

        // BOOLEANOS
        $camposBooleanos = [
            'BeiraMar' => 'Beira Mar', 'RoofTop' => 'RoofTop', 'Academia' => 'Academia',
            'Sauna' => 'Sauna', 'PetPlace' => 'Pet Place', 'EspacoGourmet' => 'Espaço Gourmet',
            'Restaurante' => 'Restaurante', 'Coworking' => 'Coworking', 'QuadraTenis' => 'Quadra Tênis',
            'Emporio' => 'Empório', 'Piscina2' => 'Piscina', 'Spa' => 'Spa',
            'Recepcao24h' => 'Recepção 24h', 'LoungeBar' => 'Lounge Bar',
            'Brinquedoteca' => 'Brinquedoteca', 'Varanda' => 'Varanda',
            'VarandaGourmet' => 'Varanda Gourmet', 'Salaojogos' => 'Sala de Jogos',
            'Entregue' => 'Entregue', 'Vendido' => 'Vendido', 'Ocultar' => 'Ocultar',
            'GestoraAluguel' => 'Gestora Aluguel', 'Lavanderia' => 'Lavanderia',
            'Bicicletario' => 'Bicicletário', 'Hidromassagem' => 'Hidromassagem',
            'GasEncanado' => 'Gás Encanado', 'PiscinaPrivativa' => 'Piscina Privativa',
            'FechaduraEletronica' => 'Fechadura Eletrônica',
        ];
        foreach ($camposBooleanos as $campo => $label) {
            $valor = isset($_POST[$campo]) ? getCheckboxValue($_POST[$campo]) : 0;
            $antigo = (int)($dadosAntigos[$campo] ?? 0);
            if ($valor != $antigo) {
                $alteracoes[$label] = ['antigo' => $antigo ? 'Sim' : 'Não', 'novo' => $valor ? 'Sim' : 'Não'];
            }
            $setClauses[] = "$campo = :$campo";
            $params[":$campo"] = $valor;
        }

        // UPLOAD DO CARD
        $cardDir  = __DIR__ . '/card';
        $cardFile = $cardDir . '/' . $id . '.jpg';

        if (!is_dir($cardDir)) @mkdir($cardDir, 0755, true);

        if (!empty($_POST['remover_card']) && $_POST['remover_card'] === '1') {
            if (file_exists($cardFile)) {
                if (@unlink($cardFile)) {
                    $alteracoes['Imagem do Card'] = ['antigo' => 'existia', 'novo' => 'removida'];
                } else {
                    $mensagem = "❌ Não foi possível remover a imagem (verifique permissões em /card).";
                    $tipoMensagem = "danger";
                }
            }
        }

        if (!empty($_FILES['card_image']['name']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['card_image'];
            $maxBytes = 5 * 1024 * 1024;

            if ($file['size'] > $maxBytes) {
                $mensagem = "❌ Imagem muito grande (" . formatarBytes($file['size']) . "). Máx 5 MB.";
                $tipoMensagem = "danger";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowed = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($mime, $allowed)) {
                    $mensagem = "❌ Formato inválido. Use JPG, PNG ou WebP.";
                    $tipoMensagem = "danger";
                } else {
                    $info = @getimagesize($file['tmp_name']);
                    if (!$info) {
                        $mensagem = "❌ Arquivo não é uma imagem válida.";
                        $tipoMensagem = "danger";
                    } else {
                        $ok = false;
                        $maxDim = 1200;
                        $w = $info[0]; $h = $info[1];
                        $needResize = ($w > $maxDim || $h > $maxDim);

                        if ($mime === 'image/jpeg' && !$needResize) {
                            if (move_uploaded_file($file['tmp_name'], $cardFile)) $ok = true;
                        } else {
                            $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
                            if ($src) {
                                if ($needResize) {
                                    $ratio = min($maxDim / $w, $maxDim / $h);
                                    $newW = (int)round($w * $ratio);
                                    $newH = (int)round($h * $ratio);
                                } else {
                                    $newW = $w; $newH = $h;
                                }
                                $dst = imagecreatetruecolor($newW, $newH);
                                $white = imagecolorallocate($dst, 255, 255, 255);
                                imagefill($dst, 0, 0, $white);
                                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

                                if (imagejpeg($dst, $cardFile, 88)) $ok = true;
                                imagedestroy($src);
                                imagedestroy($dst);
                            }
                        }

                        if ($ok) {
                            @touch($cardFile);
                            $alteracoes['Imagem do Card'] = [
                                'antigo' => 'anterior',
                                'novo'   => "nova imagem (" . formatarBytes(filesize($cardFile)) . ")"
                            ];
                        } else {
                            $mensagem = "❌ Falha ao processar a imagem. Verifique a extensão GD do PHP.";
                            $tipoMensagem = "danger";
                        }
                    }
                }
            }
        }

        // METADADOS
        $setClauses[] = "DataAtualizacao = NOW()";
        $setClauses[] = "UsuarioAtu = :usuario_atu";
        $params[':usuario_atu'] = $_SESSION['Usuario'];

        // UPDATE
        if (!empty($setClauses) && empty($mensagem)) {
            try {
                $sql = "UPDATE empreendimento SET " . implode(', ', $setClauses) . " WHERE Empreend_ID = :id";
                $params[':id'] = $id;
                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                $remote = $_SERVER['REMOTE_ADDR'] ?? '';
                if (!empty($alteracoes) && $remote !== '127.0.0.1' && $remote !== '::1') {
                    $emailEnviado = enviarEmailAtualizacao(
                        $id, $dadosAntigos['NomeEmpreendimento'],
                        $dadosAntigos['NomeEmpresa'], $_SESSION['Usuario'], $alteracoes
                    );
                }

                // Salva sucesso na sessão e redireciona (PRG)
                $_SESSION['flash_success'] = [
                    'alteracoes' => $alteracoes,
                    'email' => $emailEnviado
                ];
                
                header("Location: ?id=" . $id . "&saved=1");
                exit();

            } catch (PDOException $e) {
                $mensagem = "❌ Erro ao atualizar: " . $e->getMessage();
                $tipoMensagem = "danger";
            }
        }
    }

    // Renova CSRF
    $_SESSION['csrf_carac'] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION['csrf_carac'];
}

// RECUPERA FLASH MESSAGE (Após Redirecionamento)
$flashSuccess = null;
if (isset($_GET['saved']) && isset($_SESSION['flash_success'])) {
    $flashSuccess = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
    // Recarrega os dados para pegar os valores atualizados na tela
    $dadosAntigos = buscarEmpreendimento($db, $id);
}

$empreendimento = $dadosAntigos;
$valorCondExibicao = formatarMoedaExibicao($empreendimento['ValorCond']);
$valorIPTUExibicao = formatarMoedaExibicao($empreendimento['ValorIPTU']);
$menorPrecoExibicao = formatarMoedaExibicao($empreendimento['MenorPreco']);
$maiorPrecoExibicao = formatarMoedaExibicao($empreendimento['MaiorPreco']);

$qtdBlocos   = $empreendimento['QTD_Blocos']   ?? 0;
$qtdUnidades = $empreendimento['QTD_Unidades'] ?? 0;

// ESTADO DA IMAGEM
$cardFile = __DIR__ . '/card/' . $id . '.jpg';
clearstatcache(true, $cardFile);
$cardExists    = file_exists($cardFile);
$cardTimestamp = $cardExists ? filemtime($cardFile) : 0;
$cardSize      = $cardExists ? filesize($cardFile) : 0;
$cardUrl       = "card/{$id}.jpg" . ($cardTimestamp ? "?v={$cardTimestamp}" : '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Características — <?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?php echo $themeSafe['primary']; ?>;
            --primary-dark: <?php echo $themeSafe['primary_dark']; ?>;
            --bg-body: <?php echo $themeSafe['bg_body']; ?>;
            --text-color: <?php echo $themeSafe['text_color']; ?>;
            --card-bg: <?php echo $themeSafe['card_bg']; ?>;
            --shadow-color: <?php echo $themeSafe['shadow_color']; ?>;
            --btn-bg: <?php echo $themeSafe['btn_bg']; ?>;
            --btn-hover: <?php echo $themeSafe['btn_hover']; ?>;
            --btn-text: <?php echo $themeSafe['btn_text']; ?>;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding-bottom: 96px;
            -webkit-font-smoothing: antialiased;
        }

        /* HEADER */
        .app-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 50;
            margin-bottom: 24px;
        }
        .app-header::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .logo-img {
            height: 58px; width: auto; max-width: 160px; min-width: 58px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 5px 8px;
            flex-shrink: 0;
        }
        .header-title h1 {
            margin: 0; font-size: 1.15rem; font-weight: 900;
            letter-spacing: -0.3px; line-height: 1.15;
        }
        .header-title p { margin: 4px 0 0; font-size: 0.78rem; font-weight: 600; opacity: 0.9; }
        .header-title p strong { background: rgba(255,255,255,0.18); padding: 1px 8px; border-radius: 6px; }

        .header-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
        .meta-chip {
            background: rgba(0,0,0,0.2); border: 2px solid var(--shadow-color);
            border-radius: 10px; padding: 6px 12px; font-size: 0.74rem;
            font-weight: 700; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-close-header {
            background: var(--danger); color: #fff; border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color); border-radius: 10px;
            padding: 8px 16px; font-weight: 800; font-size: 0.82rem;
            cursor: pointer; transition: all 0.15s; display: inline-flex;
            align-items: center; gap: 6px; flex-shrink: 0;
        }
        .btn-close-header:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37;
        }

        /* CONTAINER / SECTION CARDS */
        .container-neo { max-width: 1300px; margin: 0 auto; padding: 0 20px; }

        .section-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            margin-bottom: 22px;
            overflow: hidden;
        }
        .section-header {
            background: var(--primary); color: #fff; padding: 14px 22px;
            display: flex; align-items: center; gap: 14px;
            border-bottom: 3px solid var(--shadow-color);
        }
        .section-num {
            width: 34px; height: 34px; border-radius: 10px;
            background: rgba(0,0,0,0.25); display: flex;
            align-items: center; justify-content: center;
            font-weight: 900; font-size: 0.9rem; flex-shrink: 0;
        }
        .section-title { flex: 1; min-width: 0; }
        .section-title h2 { margin: 0; font-size: 1.02rem; font-weight: 900; letter-spacing: -0.2px; }
        .section-title p { margin: 2px 0 0; font-size: 0.76rem; opacity: 0.85; font-weight: 500; }
        .section-body { padding: 22px; }

        /* SUCCESS BANNER */
        .success-banner {
            border-color: var(--success);
            box-shadow: 6px 6px 0 0 var(--success);
            margin-bottom: 30px;
            animation: slideDown 0.4s ease-out;
        }
        .success-banner .section-header {
            background: var(--success);
            border-bottom-color: var(--success);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .btn-success-action {
            background: #fff; color: var(--success);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            font-weight: 800; border-radius: 10px; padding: 8px 16px;
            transition: all 0.15s;
        }
        .btn-success-action:hover {
            background: var(--success); color: #fff;
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
        }
        .btn-success-close {
            background: var(--danger); color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            font-weight: 800; border-radius: 10px; padding: 8px 16px;
            transition: all 0.15s;
        }
        .btn-success-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: #b02a37;
        }
        .diff-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .diff-table th {
            background: #f8f9fb; padding: 10px; text-align: left;
            border-bottom: 2px solid var(--shadow-color); font-weight: 800;
        }
        .diff-table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .val-old { color: var(--danger); text-decoration: line-through; opacity: 0.8; font-weight: 600; }
        .val-new { color: var(--success); font-weight: 800; }

        /* FORM */
        .field-label {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.76rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.4px; color: var(--text-color); opacity: 0.85; margin-bottom: 6px;
        }
        .field-label i { color: var(--primary); font-size: 0.85rem; }
        .form-control, .form-select {
            border: 2px solid var(--shadow-color); border-radius: 10px;
            padding: 9px 12px; font-family: inherit; font-weight: 600;
            font-size: 0.88rem; color: var(--text-color); background: #fff;
            transition: all 0.15s;
        }
        .form-control:focus, .form-select:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .text-uppercase { text-transform: uppercase; }
        textarea.form-control { resize: vertical; min-height: 72px; }
        .form-text { font-size: 0.72rem; color: var(--text-color); opacity: 0.6; margin-top: 4px; }
        .invalid-feedback { font-size: 0.74rem; color: var(--danger); font-weight: 700; margin-top: 4px; }
        .form-control.is-invalid { border-color: var(--danger); box-shadow: 3px 3px 0 0 var(--danger); }

        .input-group-neo { display: flex; align-items: stretch; }
        .input-group-neo .icon-box {
            background: rgba(0,0,0,0.05); border: 2px solid var(--shadow-color);
            border-right: none; border-radius: 10px 0 0 10px;
            padding: 0 12px; display: flex; align-items: center;
            color: var(--primary); font-size: 0.9rem; flex-shrink: 0;
        }
        .input-group-neo .form-control { border-radius: 0 10px 10px 0; }

        /* SWITCHES */
        .switch-row { display: flex; flex-wrap: wrap; gap: 12px; }
        .switch-card {
            flex: 1 1 220px; min-width: 180px; display: flex;
            align-items: center; justify-content: space-between;
            gap: 12px; padding: 12px 16px; background: #f8f9fb;
            border: 2px solid var(--shadow-color); border-radius: 12px;
            cursor: pointer; transition: all 0.15s;
        }
        .switch-card:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            background: rgba(0,0,0,0.02);
        }
        .switch-card .switch-info { min-width: 0; flex: 1; }
        .switch-card .switch-name { font-weight: 800; font-size: 0.88rem; margin: 0; line-height: 1.2; }
        .switch-card .switch-desc { font-size: 0.72rem; opacity: 0.65; margin: 2px 0 0; }
        .switch-toggle { position: relative; width: 46px; height: 24px; flex-shrink: 0; }
        .switch-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
        .switch-toggle .track {
            position: absolute; inset: 0; background: #d1d5db;
            border-radius: 999px; cursor: pointer; transition: background 0.2s;
            border: 2px solid var(--shadow-color);
        }
        .switch-toggle .track::after {
            content: ""; position: absolute; width: 16px; height: 16px;
            background: #fff; border-radius: 50%; top: 2px; left: 2px;
            transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .switch-toggle input:checked + .track { background: var(--primary); }
        .switch-toggle input:checked + .track::after { transform: translateX(22px); }
        .switch-toggle.danger input:checked + .track { background: var(--danger); }

        /* COMODIDADES */
        .comodidades-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .comodidade-item {
            display: flex; align-items: center; gap: 10px; padding: 10px 14px;
            background: #f8f9fb; border: 2px solid var(--shadow-color);
            border-radius: 10px; cursor: pointer; transition: all 0.15s; user-select: none;
        }
        .comodidade-item:hover { transform: translate(-1px, -1px); box-shadow: 2px 2px 0 0 var(--shadow-color); }
        .comodidade-item input { display: none; }
        .comodidade-item .check-box {
            width: 22px; height: 22px; border: 2px solid var(--shadow-color);
            border-radius: 6px; background: #fff; display: flex;
            align-items: center; justify-content: center;
            flex-shrink: 0; transition: all 0.15s; color: transparent; font-size: 0.7rem;
        }
        .comodidade-item input:checked + .check-box { background: var(--primary); color: #fff; }
        .comodidade-item .com-nome { font-weight: 700; font-size: 0.84rem; flex: 1; min-width: 0; }
        .comodidade-item .com-icon { color: var(--primary); font-size: 0.85rem; }
        .comodidade-item input:checked ~ .com-nome { color: var(--primary); }

        /* UPLOAD DO CARD */
        .card-upload-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }
        @media (max-width: 900px) { .card-upload-layout { grid-template-columns: 1fr; } }

        .card-preview {
            position: relative; aspect-ratio: 16 / 9;
            border: 3px solid var(--shadow-color); border-radius: 12px;
            overflow: hidden; box-shadow: 5px 5px 0 0 var(--shadow-color); background: #f8f9fb;
        }
        .card-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .card-preview-empty {
            width: 100%; height: 100%; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 8px;
            color: #9ca3af; font-weight: 700;
        }
        .card-preview-empty i { font-size: 2.2rem; opacity: 0.7; }
        .card-preview-empty span { font-size: 0.82rem; }

        .card-preview-badge {
            position: absolute; top: 10px; right: 10px;
            background: rgba(0,0,0,0.75); color: #fff; padding: 4px 10px;
            border-radius: 20px; font-size: 0.68rem; font-weight: 800;
            display: inline-flex; align-items: center; gap: 5px;
            backdrop-filter: blur(4px); border: 1.5px solid rgba(255,255,255,0.25);
        }
        .card-preview-badge.is-ok { background: rgba(16,185,129,0.9); }
        .card-preview-badge.is-empty { background: rgba(239,68,68,0.9); }

        .card-preview-meta { margin-top: 8px; font-size: 0.72rem; color: var(--text-color); opacity: 0.6; font-weight: 600; text-align: center; }

        .card-upload-actions { display: flex; flex-direction: column; gap: 12px; }
        .upload-title { font-size: 0.95rem; font-weight: 900; margin: 0; }
        .upload-desc { font-size: 0.78rem; opacity: 0.7; margin: 0; font-weight: 500; }

        .upload-dropzone {
            display: block; padding: 22px; border: 2px dashed var(--shadow-color);
            border-radius: 12px; background: #f8f9fb; text-align: center;
            cursor: pointer; transition: all 0.15s; color: var(--text-color);
        }
        .upload-dropzone:hover, .upload-dropzone.dragover {
            background: rgba(0,0,0,0.04); border-color: var(--primary);
            border-style: solid; transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .upload-dropzone .upload-icon { display: block; font-size: 1.8rem; color: var(--primary); margin-bottom: 8px; }
        .upload-dropzone .upload-main { display: block; font-weight: 800; font-size: 0.9rem; margin-bottom: 4px; }
        .upload-dropzone .upload-sub { display: block; font-size: 0.72rem; opacity: 0.6; font-weight: 600; }

        .file-info { background: rgba(23,162,184,0.08); border: 2px solid var(--info); border-radius: 10px; padding: 10px 14px; }
        .file-info-row { display: flex; align-items: center; gap: 10px; font-size: 0.82rem; font-weight: 600; }
        .file-info-row > i { color: var(--info); }
        .file-info-row #fileName { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .file-size { color: var(--info); font-weight: 800; font-size: 0.76rem; }
        .file-clear { background: transparent; border: none; color: var(--text-color); cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; font-size: 0.85rem; }
        .file-clear:hover { background: rgba(0,0,0,0.08); color: var(--danger); }

        .card-remove-wrap { display: flex; justify-content: flex-start; }
        .btn-remove {
            background: var(--danger); color: #fff; border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color); border-radius: 10px;
            padding: 8px 16px; font-weight: 800; font-size: 0.8rem;
            cursor: pointer; display: inline-flex; align-items: center;
            gap: 6px; transition: all 0.15s; font-family: inherit;
        }
        .btn-remove:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 0 var(--shadow-color); background: #b02a37; }
        .btn-remove.btn-undo { background: var(--warning); color: #000; }
        .btn-remove.btn-undo:hover { background: #e0a800; color: #000; }

        /* ACTION BAR */
        .action-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: var(--card-bg); border-top: 3px solid var(--shadow-color);
            box-shadow: 0 -4px 0 0 var(--shadow-color); padding: 14px 20px; z-index: 40;
        }
        .action-bar-inner {
            max-width: 1300px; margin: 0 auto; display: flex;
            justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .action-info { font-size: 0.82rem; font-weight: 600; opacity: 0.75; display: flex; align-items: center; gap: 6px; }
        .action-info .dirty-badge {
            background: var(--warning); color: #000; padding: 3px 10px;
            border-radius: 12px; font-weight: 800; font-size: 0.72rem; display: none;
        }
        .action-info .dirty-badge.visible { display: inline-block; }
        .btn-neo {
            border: 2px solid var(--shadow-color); box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px; padding: 10px 22px; font-weight: 800;
            font-size: 0.88rem; cursor: pointer; transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 6px; font-family: inherit;
        }
        .btn-save { background: var(--btn-bg); color: var(--btn-text); }
        .btn-save:hover:not(:disabled) {
            transform: translate(-2px, -2px); box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: var(--btn-hover); color: #fff;
        }
        .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-cancel { background: #fff; color: var(--text-color); }
        .btn-cancel:hover {
            transform: translate(-1px, -1px); box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #f8f9fb; color: var(--text-color);
        }

        /* TIMER */
        .session-timer {
            position: fixed; bottom: 96px; right: 20px;
            background: var(--card-bg); border: 2px solid var(--shadow-color);
            border-radius: 10px; box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 8px 14px; font-size: 0.78rem; font-weight: 700;
            display: flex; align-items: center; gap: 8px; z-index: 35;
        }
        .session-timer.warn { background: var(--warning); color: #000; }

        /* RESPONSIVO */
        @media (max-width: 768px) {
            .app-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; }
            .logo-img { height: 44px; max-width: 110px; }
            .header-title h1 { font-size: 0.98rem; }
            .header-title p { font-size: 0.7rem; }
            .header-meta { width: 100%; }
            .header-meta .meta-chip { flex: 1; justify-content: center; font-size: 0.68rem; }
            .btn-close-header { width: 100%; justify-content: center; }
            .container-neo { padding: 0 12px; }
            .section-body { padding: 14px; }
            .switch-card { flex-basis: 100%; }
            .comodidades-grid { grid-template-columns: 1fr; }
            .action-bar-inner { flex-direction: column; align-items: stretch; }
            .action-bar-inner .btn-neo { width: 100%; justify-content: center; }
            .session-timer { display: none; }
        }
    </style>
</head>
<body>

<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logo" class="logo-img">
            <div class="header-title">
                <h1><i class="fas fa-sliders-h me-2"></i> Editar Características</h1>
                <p>
                    <strong>#<?php echo $id; ?></strong>
                    <?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?>
                    <?php if ($empreendimento['NomeEmpresa'] !== '—'): ?>
                        · <?php echo htmlspecialchars($empreendimento['NomeEmpresa']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="header-meta">
            <span class="meta-chip">
                <i class="fas fa-clock"></i>
                <?php
                    $dataAtu = $empreendimento['DataAtualizacao'] ?? null;
                    echo $dataAtu ? date('d/m/Y H:i', strtotime($dataAtu)) : '—';
                ?>
            </span>
            <span class="meta-chip">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($empreendimento['UsuarioAtu'] ?: '—'); ?>
            </span>
            <button type="button" class="btn-close-header" onclick="tentarFechar()">
                <i class="fas fa-times"></i> Fechar
            </button>
        </div>
    </div>
</header>

<div class="container-neo">

    <!-- BANNER DE SUCESSO (RESUMO) -->
    <?php if ($flashSuccess): ?>
    <div class="section-card success-banner">
        <div class="section-header">
            <div class="section-num"><i class="fas fa-check"></i></div>
            <div class="section-title">
                <h2>Dados salvos com sucesso!</h2>
                <p>
                    <?php if (count($flashSuccess['alteracoes']) > 0): ?>
                        <strong><?php echo count($flashSuccess['alteracoes']); ?> campo(s)</strong> alterado(s) e gravado(s).
                    <?php else: ?>
                        Nenhuma alteração de valor — apenas <strong>data/hora</strong> e <strong>usuário</strong> foram atualizados.
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="?id=<?php echo $id; ?>" class="btn-success-action text-decoration-none">
                    <i class="fas fa-edit me-1"></i> Continuar Editando
                </a>
                <button type="button" class="btn-success-close" onclick="window.close()">
                    <i class="fas fa-times-circle me-1"></i> Fechar Aba
                </button>
            </div>
        </div>
        <div class="section-body bg-white">
            <?php if (!empty($flashSuccess['alteracoes'])): ?>
                <div class="table-responsive">
                    <table class="diff-table">
                        <thead>
                            <tr>
                                <th width="30%">Campo</th>
                                <th width="35%">Valor Antigo</th>
                                <th width="35%">Valor Novo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($flashSuccess['alteracoes'] as $campo => $valores): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($campo); ?></td>
                                    <td class="val-old"><?php echo formatarValorResumo($campo, $valores['antigo']); ?></td>
                                    <td class="val-new"><?php echo formatarValorResumo($campo, $valores['novo']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i> Os dados foram processados, mas nenhum campo de valor sofreu alteração.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- FORMULÁRIO -->
    <form method="post" id="formEdicao" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="idEmpresa" value="<?php echo htmlspecialchars($empreendimento['Empresa_ID'] ?? ''); ?>">

        <!-- 1. INFORMAÇÕES BÁSICAS -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-num">1</div>
                <div class="section-title">
                    <h2>Informações Básicas</h2>
                    <p>Identificação, status e localização</p>
                </div>
            </div>
            <div class="section-body">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="field-label" for="Localidade"><i class="fas fa-map-marker-alt"></i> Localidade</label>
                        <input type="text" class="form-control text-uppercase" id="Localidade" name="Localidade"
                               value="<?php echo htmlspecialchars($empreendimento['Localidade']); ?>">
                    </div>
<div class="col-md-6">
    <label class="field-label" for="Tipologia"><i class="fas fa-layer-group"></i> Tipologia</label>
    <input type="text" class="form-control text-uppercase" id="Tipologia" name="Tipologia"
           value="<?php echo htmlspecialchars(strtoupper($empreendimento['Tipologia'])); ?>"
           placeholder="Ex.: BANG/DUPLEX/22" oninput="formatarTipologia(this)">
    <div class="form-text">Separe com barra. Aceita: CASA, BANG, STUDIO, FLAT, DUPLEX, 1Q, 2Q, 3Q, 4Q, 5Q</div>
    <div class="invalid-feedback">Formato inválido. Ex.: BANG/DUPLEX/1Q/2Q</div>
</div>
                </div>

                <div class="switch-row mb-3">
                    <label class="switch-card">
                        <div class="switch-info">
                            <p class="switch-name">Entregue</p>
                            <p class="switch-desc">Obra finalizada</p>
                        </div>
                        <div class="switch-toggle">
                            <input type="checkbox" id="Entregue" name="Entregue" <?php echo $empreendimento['Entregue'] ? 'checked' : ''; ?>>
                            <span class="track"></span>
                        </div>
                    </label>
                    <label class="switch-card">
                        <div class="switch-info">
                            <p class="switch-name">100% Vendido</p>
                            <p class="switch-desc">Sem unidades disponíveis</p>
                        </div>
                        <div class="switch-toggle">
                            <input type="checkbox" id="Vendido" name="Vendido" <?php echo $empreendimento['Vendido'] ? 'checked' : ''; ?>>
                            <span class="track"></span>
                        </div>
                    </label>
                    <label class="switch-card">
                        <div class="switch-info">
                            <p class="switch-name">Ocultar do site</p>
                            <p class="switch-desc">Não exibir publicamente</p>
                        </div>
                        <div class="switch-toggle danger">
                            <input type="checkbox" id="Ocultar" name="Ocultar" <?php echo $empreendimento['Ocultar'] ? 'checked' : ''; ?>>
                            <span class="track"></span>
                        </div>
                    </label>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="field-label" for="MesEntrega"><i class="fas fa-calendar-alt"></i> Mês Entrega</label>
                        <select class="form-select" id="MesEntrega" name="MesEntrega">
                            <option value="">—</option>
                            <?php
                            $mesAtual = (int)($empreendimento['MesEntrega'] ?? 0);
                            for ($i = 1; $i <= 12; $i++):
                                $mesNome = date('M', mktime(0, 0, 0, $i, 1));
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo $mesAtual == $i ? 'selected' : ''; ?>><?php echo $mesNome . " ($i)"; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="AnoEntrega"><i class="fas fa-calendar"></i> Ano Entrega</label>
                        <select class="form-select" id="AnoEntrega" name="AnoEntrega">
                            <option value="">—</option>
                            <?php
                            $anoAtual = (int)($empreendimento['AnoEntrega'] ?? 0);
                            for ($i = 2020; $i <= 2035; $i++):
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo $anoAtual == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="ProximoMar"><i class="fas fa-water"></i> Distância Mar (m)</label>
                        <input type="number" class="form-control" id="ProximoMar" name="ProximoMar" min="0"
                               value="<?php echo htmlspecialchars($empreendimento['ProximoMar'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="Pavimentos"><i class="fas fa-building"></i> Pavimentos</label>
                        <input type="number" class="form-control" id="Pavimentos" name="Pavimentos" min="0"
                               value="<?php echo htmlspecialchars($empreendimento['Pavimentos'] ?? ''); ?>">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="field-label" for="Descricao"><i class="fas fa-align-left"></i> Descrição</label>
                        <textarea class="form-control text-uppercase" id="Descricao" name="Descricao" rows="3"><?php echo htmlspecialchars($empreendimento['Descricao']); ?></textarea>
                    </div>
                </div>

            </div>
        </section>

        <!-- 2. VALORES -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-num">2</div>
                <div class="section-title">
                    <h2>Valores e Metragens</h2>
                    <p>Custos, faixa de preço e área útil</p>
                </div>
            </div>
            <div class="section-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="field-label" for="ValorCond"><i class="fas fa-home"></i> Condomínio (R$)</label>
                        <input type="text" class="form-control money-mask" id="ValorCond" name="ValorCond" value="<?php echo $valorCondExibicao; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="ValorIPTU"><i class="fas fa-receipt"></i> IPTU (R$)</label>
                        <input type="text" class="form-control money-mask" id="ValorIPTU" name="ValorIPTU" value="<?php echo $valorIPTUExibicao; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="MenorPreco"><i class="fas fa-tag"></i> Menor Preço (R$)</label>
                        <input type="text" class="form-control money-mask" id="MenorPreco" name="MenorPreco" value="<?php echo $menorPrecoExibicao; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="MaiorPreco"><i class="fas fa-tags"></i> Maior Preço (R$)</label>
                        <input type="text" class="form-control money-mask" id="MaiorPreco" name="MaiorPreco" value="<?php echo $maiorPrecoExibicao; ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="field-label" for="MetragemMin"><i class="fas fa-ruler-combined"></i> Metragem Mín. (m²)</label>
                        <div class="input-group-neo">
                            <span class="icon-box"><i class="fas fa-ruler-combined"></i></span>
                            <input type="text" class="form-control area-mask" id="MetragemMin" name="MetragemMin"
                                   value="<?php echo htmlspecialchars($empreendimento['MetragemMin'] ?? ''); ?>" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="MetragemMax"><i class="fas fa-ruler"></i> Metragem Máx. (m²)</label>
                        <div class="input-group-neo">
                            <span class="icon-box"><i class="fas fa-ruler"></i></span>
                            <input type="text" class="form-control area-mask" id="MetragemMax" name="MetragemMax"
                                   value="<?php echo htmlspecialchars($empreendimento['MetragemMax'] ?? ''); ?>" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="QTD_Blocos"><i class="fas fa-cubes"></i> Qtd. Blocos</label>
                        <input type="number" class="form-control" id="QTD_Blocos" name="QTD_Blocos" min="0"
                               value="<?php echo htmlspecialchars($qtdBlocos); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="QTD_Unidades"><i class="fas fa-door-open"></i> Qtd. Unidades</label>
                        <input type="number" class="form-control" id="QTD_Unidades" name="QTD_Unidades" min="0"
                               value="<?php echo htmlspecialchars($qtdUnidades); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="VagasGaragem"><i class="fas fa-parking"></i> Vagas Garagem</label>
                        <input type="number" class="form-control" id="VagasGaragem" name="VagasGaragem" min="0"
                               value="<?php echo htmlspecialchars($empreendimento['VagasGaragem'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="TipoGaragem"><i class="fas fa-car"></i> Tipo Garagem</label>
                        <select class="form-select" id="TipoGaragem" name="TipoGaragem">
                            <option value="">—</option>
                            <option value="Privativa" <?php echo $empreendimento['TipoGaragem'] == 'Privativa' ? 'selected' : ''; ?>>Privativa</option>
                            <option value="Rotativa"  <?php echo $empreendimento['TipoGaragem'] == 'Rotativa'  ? 'selected' : ''; ?>>Rotativa</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="Suites"><i class="fas fa-bed"></i> Suítes</label>
                        <input type="number" class="form-control" id="Suites" name="Suites" min="0"
                               value="<?php echo htmlspecialchars($empreendimento['Suites'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="field-label" for="WCTotal"><i class="fas fa-bath"></i> Banheiros</label>
                        <input type="number" class="form-control" id="WCTotal" name="WCTotal" min="0"
                               value="<?php echo htmlspecialchars($empreendimento['WCTotal'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. COMODIDADES -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-num">3</div>
                <div class="section-title">
                    <h2>Comodidades e Lazer</h2>
                    <p>Clique nos itens para ativar/desativar</p>
                </div>
            </div>
            <div class="section-body">
                <div class="comodidades-grid">
                    <?php
                    $comodidades = [
                        'BeiraMar' => ['Beira Mar', 'fa-umbrella-beach'],
                        'RoofTop' => ['RoofTop', 'fa-building'],
                        'Academia' => ['Academia', 'fa-dumbbell'],
                        'Sauna' => ['Sauna', 'fa-hot-tub'],
                        'PetPlace' => ['Pet Place', 'fa-paw'],
                        'EspacoGourmet' => ['Espaço Gourmet', 'fa-utensils'],
                        'Restaurante' => ['Restaurante', 'fa-utensils'],
                        'Coworking' => ['Coworking', 'fa-laptop-code'],
                        'QuadraTenis' => ['Quadra Tênis', 'fa-table-tennis'],
                        'Emporio' => ['Empório', 'fa-store'],
                        'Piscina2' => ['Piscina', 'fa-swimming-pool'],
                        'Spa' => ['Spa', 'fa-spa'],
                        'Recepcao24h' => ['Recepção 24h', 'fa-concierge-bell'],
                        'LoungeBar' => ['Lounge Bar', 'fa-cocktail'],
                        'Brinquedoteca' => ['Brinquedoteca', 'fa-gamepad'],
                        'Varanda' => ['Varanda', 'fa-door-open'],
                        'VarandaGourmet' => ['Varanda Gourmet', 'fa-grill-hot'],
                        'Salaojogos' => ['Sala de Jogos', 'fa-chess'],
                        'GestoraAluguel' => ['Gestora Aluguel', 'fa-key'],
                        'Lavanderia' => ['Lavanderia', 'fa-tshirt'],
                        'Bicicletario' => ['Bicicletário', 'fa-bicycle'],
                        'Hidromassagem' => ['Hidromassagem', 'fa-hot-tub'],
                        'GasEncanado' => ['Gás Encanado', 'fa-fire'],
                        'PiscinaPrivativa' => ['Piscina Privativa', 'fa-water'],
                        'FechaduraEletronica' => ['Fechadura Eletrônica', 'fa-lock'],
                    ];
                    foreach ($comodidades as $campo => [$label, $icone]):
                        $checked = !empty($empreendimento[$campo]) ? 'checked' : '';
                    ?>
                    <label class="comodidade-item">
                        <input type="checkbox" name="<?php echo $campo; ?>" id="<?php echo $campo; ?>" <?php echo $checked; ?>>
                        <span class="check-box"><i class="fas fa-check"></i></span>
                        <span class="com-nome">
                            <i class="fas <?php echo $icone; ?> com-icon"></i>
                            <?php echo htmlspecialchars($label); ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 4. CARD DO IMÓVEL -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-num">4</div>
                <div class="section-title">
                    <h2>Card do Imóvel</h2>
                    <p>Imagem de destaque exibida na listagem pública</p>
                </div>
            </div>
            <div class="section-body">
                <div class="card-upload-layout">

                    <div>
                        <div class="card-preview" id="cardPreviewContainer">
                            <img src="<?php echo $cardUrl; ?>"
                                 alt="Card do imóvel"
                                 id="cardPreviewImg"
                                 <?php echo $cardExists ? '' : 'style="display:none;"'; ?>>

                            <div class="card-preview-empty" id="cardPreviewEmpty"
                                 <?php echo $cardExists ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-image"></i>
                                <span>Nenhuma imagem cadastrada</span>
                            </div>

                            <span class="card-preview-badge <?php echo $cardExists ? 'is-ok' : 'is-empty'; ?>"
                                  id="cardPreviewBadge">
                                <i class="fas fa-<?php echo $cardExists ? 'check-circle' : 'times-circle'; ?>"></i>
                                <span id="cardPreviewBadgeText"><?php echo $cardExists ? 'Imagem atual' : 'Sem imagem'; ?></span>
                            </span>
                        </div>
                        <div class="card-preview-meta" id="cardPreviewMeta">
                            <?php if ($cardExists): ?>
                                <?php echo formatarBytes($cardSize); ?> ·
                                Atualizada em <?php echo date('d/m/Y H:i', $cardTimestamp); ?>
                            <?php else: ?>
                                Nenhuma imagem cadastrada ainda
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-upload-actions">
                        <h3 class="upload-title">Enviar ou Trocar Imagem</h3>
                        <p class="upload-desc">Recomendado: JPG em proporção 16:9. Máximo 5 MB.</p>

                        <label class="upload-dropzone" id="uploadDropzone" for="card_image">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <span class="upload-main">Clique ou arraste uma imagem aqui</span>
                            <span class="upload-sub">JPG · PNG · WebP · máx. 5 MB</span>
                            <input type="file" id="card_image" name="card_image"
                                   accept="image/jpeg,image/png,image/webp"
                                   style="display:none;">
                        </label>

                        <div class="file-info" id="fileInfo" style="display:none;">
                            <div class="file-info-row">
                                <i class="fas fa-file-image"></i>
                                <span id="fileName">—</span>
                                <span class="file-size" id="fileSize">—</span>
                                <button type="button" class="file-clear" id="fileClear" title="Cancelar seleção">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-remove-wrap" id="removeWrap" <?php echo $cardExists ? '' : 'style="display:none;"'; ?>>
                            <button type="button" class="btn-remove" id="btnRemoveCard">
                                <i class="fas fa-trash"></i> Remover imagem atual
                            </button>
                            <input type="hidden" name="remover_card" id="removerCard" value="0">
                        </div>
                    </div>

                </div>
            </div>
        </section>

    </form>
</div>

<div class="action-bar">
    <div class="action-bar-inner">
        <div class="action-info">
            <i class="fas fa-info-circle"></i>
            <span id="actionHint">Nenhuma alteração pendente</span>
            <span class="dirty-badge" id="dirtyBadge">0 pendentes</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn-neo btn-cancel" onclick="tentarFechar()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" class="btn-neo btn-save" id="btnSave" form="formEdicao">
                <i class="fas fa-save"></i> Salvar Características
            </button>
        </div>
    </div>
</div>

<div class="session-timer" id="sessionTimer">
    <i class="bi bi-clock"></i>
    <span id="timeRemaining">--:--</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script>
(function () {
    'use strict';

    const THEME_PRIMARY = '<?php echo $themeSafe['primary']; ?>';
    const MAX_BYTES     = 5 * 1024 * 1024;
    const ALLOWED_MIME  = ['image/jpeg', 'image/png', 'image/webp'];

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true
    });

    let cardImageChanged   = false;
    let currentObjectUrl   = null;
    let originalImgSrc     = null;
    let originalHasImage   = false;
    let originalRemoverVal = '0';

    window.formatarTipologia = function (input) {
        input.value = input.value.toUpperCase().replace(/[^A-Z0-9\/]/g, '');
        
        const permitidos = '(CASA|BANG|STUDIO|FLAT|DUPLEX|1Q|2Q|3Q|4Q|5Q)';
        const regex = new RegExp(`^${permitidos}(\\/${permitidos})*$`);
        
        const isValid = regex.test(input.value);
        input.classList.toggle('is-invalid', input.value !== '' && !isValid);
    };


    function formatarBytes(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2).replace('.', ',') + ' MB';
        if (bytes >= 1024)    return (bytes / 1024).toFixed(1).replace('.', ',') + ' KB';
        return bytes + ' B';
    }

    let formOriginal = null;

    function capturarEstado() {
        const form = document.getElementById('formEdicao');
        const data = new FormData(form);
        const obj = {};
        for (const [k, v] of data.entries()) {
            if (k === 'csrf_token') continue;
            if (k === 'card_image') continue;
            if (typeof v === 'string') obj[k] = v;
        }
        return JSON.stringify(obj);
    }

    function verificarDirty() {
        if (!formOriginal) return false;
        const atual = capturarEstado();
        const formChanged = atual !== formOriginal;
        const dirty = formChanged || cardImageChanged;

        const badge = document.getElementById('dirtyBadge');
        const hint  = document.getElementById('actionHint');

        if (dirty) {
            badge.classList.add('visible');
            badge.textContent = 'Alterações pendentes';
            hint.textContent = 'Você tem alterações não salvas';
        } else {
            badge.classList.remove('visible');
            hint.textContent = 'Nenhuma alteração pendente';
        }
        return dirty;
    }

    function setPreviewFromFile(file) {
        if (!ALLOWED_MIME.includes(file.type)) {
            Toast.fire({ icon: 'error', title: 'Formato inválido. Use JPG, PNG ou WebP.' });
            return false;
        }
        if (file.size > MAX_BYTES) {
            Toast.fire({ icon: 'error', title: 'Imagem muito grande (' + formatarBytes(file.size) + '). Máx 5 MB.' });
            return false;
        }

        if (currentObjectUrl) URL.revokeObjectURL(currentObjectUrl);
        currentObjectUrl = URL.createObjectURL(file);

        const img   = document.getElementById('cardPreviewImg');
        const empty = document.getElementById('cardPreviewEmpty');
        img.src = currentObjectUrl;
        img.style.display = 'block';
        empty.style.display = 'none';

        const badge = document.getElementById('cardPreviewBadge');
        badge.classList.remove('is-empty');
        badge.classList.add('is-ok');
        badge.querySelector('i').className = 'fas fa-clock';
        document.getElementById('cardPreviewBadgeText').textContent = 'Pré-visualização';

        document.getElementById('cardPreviewMeta').textContent =
            'Novo arquivo: ' + formatarBytes(file.size) + ' · ' + file.name;

        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatarBytes(file.size);
        document.getElementById('fileInfo').style.display = 'block';

        const removerInput = document.getElementById('removerCard');
        if (removerInput) removerInput.value = '0';

        const removeWrap = document.getElementById('removeWrap');
        if (removeWrap) removeWrap.style.display = 'none';

        cardImageChanged = true;
        verificarDirty();
        return true;
    }

    function restorePreview() {
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }

        const img   = document.getElementById('cardPreviewImg');
        const empty = document.getElementById('cardPreviewEmpty');
        const badge = document.getElementById('cardPreviewBadge');

        if (originalHasImage) {
            img.src = originalImgSrc;
            img.style.display = 'block';
            empty.style.display = 'none';
            badge.classList.remove('is-empty');
            badge.classList.add('is-ok');
            badge.querySelector('i').className = 'fas fa-check-circle';
            document.getElementById('cardPreviewBadgeText').textContent = 'Imagem atual';
        } else {
            img.style.display = 'none';
            empty.style.display = 'flex';
            badge.classList.remove('is-ok');
            badge.classList.add('is-empty');
            badge.querySelector('i').className = 'fas fa-times-circle';
            document.getElementById('cardPreviewBadgeText').textContent = 'Sem imagem';
        }

        document.getElementById('cardPreviewMeta').textContent = originalMetaText;

        document.getElementById('fileInfo').style.display = 'none';
        document.getElementById('fileName').textContent = '—';
        document.getElementById('fileSize').textContent = '—';

        const removeWrap = document.getElementById('removeWrap');
        if (removeWrap) removeWrap.style.display = originalHasImage ? 'flex' : 'none';

        const input = document.getElementById('card_image');
        if (input) input.value = '';

        const removerInput = document.getElementById('removerCard');
        if (removerInput) removerInput.value = originalRemoverVal;

        cardImageChanged = false;
        verificarDirty();
    }

    let originalMetaText = '';

    document.addEventListener('DOMContentLoaded', function () {

        formOriginal    = capturarEstado();
        originalHasImage = <?php echo $cardExists ? 'true' : 'false'; ?>;
        originalImgSrc   = document.getElementById('cardPreviewImg').getAttribute('src');
        originalMetaText = document.getElementById('cardPreviewMeta').textContent;
        const remInput = document.getElementById('removerCard');
        if (remInput) originalRemoverVal = remInput.value;

        $('.money-mask').mask('#.##0,00', { reverse: true });
        $('.area-mask').mask('00000,00', { reverse: true });

        document.querySelectorAll('#formEdicao input, #formEdicao select, #formEdicao textarea').forEach(function (el) {
            if (el.type === 'file') return;
            el.addEventListener('change', verificarDirty);
            el.addEventListener('input', verificarDirty);
        });

        const fileInput = document.getElementById('card_image');
        fileInput.addEventListener('change', function (e) {
            if (e.target.files && e.target.files[0]) {
                setPreviewFromFile(e.target.files[0]);
            }
        });

        const dropzone = document.getElementById('uploadDropzone');
        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault(); e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault(); e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', function (e) {
            const files = e.dataTransfer.files;
            if (files && files[0]) {
                if (setPreviewFromFile(files[0])) {
                    fileInput.files = files;
                }
            }
        });

        document.getElementById('fileClear').addEventListener('click', function () {
            restorePreview();
        });

        document.getElementById('btnRemoveCard').addEventListener('click', function () {
            const btn = this;

            if (btn.classList.contains('btn-undo')) {
                if (currentObjectUrl) {
                    URL.revokeObjectURL(currentObjectUrl);
                    currentObjectUrl = null;
                }
                const img   = document.getElementById('cardPreviewImg');
                const empty = document.getElementById('cardPreviewEmpty');
                const badge = document.getElementById('cardPreviewBadge');
                img.style.display = 'block';
                empty.style.display = 'none';
                badge.classList.remove('is-empty');
                badge.classList.add('is-ok');
                badge.querySelector('i').className = 'fas fa-check-circle';
                document.getElementById('cardPreviewBadgeText').textContent = 'Imagem atual';

                document.getElementById('removerCard').value = '0';
                document.getElementById('cardPreviewMeta').textContent = originalMetaText;

                btn.classList.remove('btn-undo');
                btn.innerHTML = '<i class="fas fa-trash"></i> Remover imagem atual';

                cardImageChanged = false;
                verificarDirty();
                return;
            }

            Swal.fire({
                title: 'Remover imagem?',
                text: 'A imagem do card será removida quando você salvar.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: THEME_PRIMARY,
                cancelButtonColor: '#6b7280'
            }).then(r => {
                if (!r.isConfirmed) return;

                const img   = document.getElementById('cardPreviewImg');
                const empty = document.getElementById('cardPreviewEmpty');
                const badge = document.getElementById('cardPreviewBadge');

                img.style.display = 'none';
                empty.style.display = 'flex';
                badge.classList.remove('is-ok');
                badge.classList.add('is-empty');
                badge.querySelector('i').className = 'fas fa-times-circle';
                document.getElementById('cardPreviewBadgeText').textContent = 'Será removida';

                document.getElementById('removerCard').value = '1';
                document.getElementById('cardPreviewMeta').textContent = 'A imagem atual será removida ao salvar';

                btn.classList.add('btn-undo');
                btn.innerHTML = '<i class="fas fa-undo"></i> Desfazer remoção';

                cardImageChanged = true;
                verificarDirty();
            });
        });

        // CORREÇÃO DO SUBMIT (MÁSCARAS)
        document.getElementById('formEdicao').addEventListener('submit', function () {
            $('.money-mask, .area-mask').each(function () {
                var digits = ($(this).val() || '').replace(/\D/g, ''); 
                if (digits === '') {
                    $(this).val('0.00');
                } else {
                    $(this).val((parseInt(digits, 10) / 100).toFixed(2));
                }
            });
            const btn = document.getElementById('btnSave');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
        });

        <?php if ($mensagem): ?>
            Toast.fire({
                icon: '<?php echo $tipoMensagem === "success" ? "success" : ($tipoMensagem === "danger" ? "error" : "info"); ?>',
                title: <?php echo json_encode($mensagem); ?>,
                timer: <?php echo $emailEnviado ? 6000 : 4500; ?>
            });
        <?php endif; ?>

        window.addEventListener('beforeunload', function (e) {
            if (verificarDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        iniciarTimerSessao(<?php echo (int)ini_get('session.gc_maxlifetime') ?: 1440; ?>);
    });

    window.tentarFechar = function () {
        if (verificarDirty()) {
            Swal.fire({
                title: 'Sair sem salvar?',
                text: 'Você tem alterações não salvas. Elas serão perdidas.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sair mesmo assim',
                cancelButtonText: 'Ficar',
                confirmButtonColor: THEME_PRIMARY,
                cancelButtonColor: '#6b7280'
            }).then(function (r) {
                if (r.isConfirmed) {
                    window.onbeforeunload = null;
                    window.close();
                }
            });
        } else {
            window.onbeforeunload = null;
            window.close();
        }
    };

    function iniciarTimerSessao(minutosTotais) {
        var restanteSeg = minutosTotais * 60;
        var el  = document.getElementById('timeRemaining');
        var box = document.getElementById('sessionTimer');
        if (!el || !box) return;

        function tick() {
            if (restanteSeg <= 0) {
                window.location.href = 'login_p1.php?timeout=1';
                return;
            }
            var h = Math.floor(restanteSeg / 3600);
            var m = Math.floor((restanteSeg % 3600) / 60);
            var s = restanteSeg % 60;
            el.textContent = (h > 0 ? h + 'h ' : '') +
                             String(m).padStart(2, '0') + ':' +
                             String(s).padStart(2, '0');

            if (restanteSeg <= 300) box.classList.add('warn');
            else box.classList.remove('warn');

            restanteSeg--;
            setTimeout(tick, 1000);
        }
        tick();
    }
})();
</script>
</body>
</html>
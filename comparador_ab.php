<?php
// ============================================================
// comparador_ab.php — Comparador A/B + Sincronização + Imagens
// Exibe imagens de A e B via URL e faz o link correto
// ============================================================
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'theme_config.php';

$configPath = __DIR__ . '/comparador_config.php';
if (!file_exists($configPath)) die("comparador_config.php não encontrado.");
$config = require $configPath;

// ============================================================
// CONFIGURAÇÃO DAS IMAGENS
// ============================================================
$urlCardA = rtrim($config['url_card_a'] ?? 'https://dev.gabnetweb.com.br/Sunny2601/card/', '/') . '/';
$urlCardB = rtrim($config['url_card_b'] ?? 'https://sunnyimob.com.br/orlasunny/card/', '/') . '/';
$cardDir  = rtrim($config['card_dir'] ?? (__DIR__ . '/card/'), '/') . '/';

// ============================================================
// HELPERS
// ============================================================
function conectar($cfg) {
    try {
        return new PDO(
            "mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4",
            $cfg['user'], $cfg['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 15,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        return null;
    }
}

function slog($msg) {
    @file_put_contents(__DIR__ . '/comparador_sync.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

function tabelaExiste($pdo, $nome) {
    if (!$pdo) return false;
    try { $pdo->query("SELECT 1 FROM `$nome` LIMIT 1"); return true; }
    catch (Exception $e) { return false; }
}

function sanitizarNome($nome) {
    if (empty($nome)) return 'empreendimento';
    $mapa = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o','Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N','&'=>'e',
    ];
    $nome = strtr($nome, $mapa);
    $nome = strtolower($nome);
    $nome = preg_replace('/[^a-z0-9]+/', '_', $nome);
    $nome = trim($nome, '_');
    if (strlen($nome) > 50) $nome = substr($nome, 0, 50);
    return empty($nome) ? 'empreendimento' : $nome;
}

function montarUpsert($tabela, array $campos, $pkField) {
    if (empty($campos)) throw new Exception("Nenhuma coluna válida para `$tabela`.");
    if (!in_array($pkField, $campos, true)) throw new Exception("PK `$pkField` ausente.");
    $outros = array_values(array_diff($campos, [$pkField]));
    $cols   = '`' . implode('`,`', $campos) . '`';
    $ph     = implode(',', array_fill(0, count($campos), '?'));
    $updates = '';
    if (!empty($outros)) {
        $updates = ' ON DUPLICATE KEY UPDATE ' .
            implode(', ', array_map(fn($f) => "`$f` = VALUES(`$f`)", $outros));
    }
    return "INSERT INTO `$tabela` ($cols) VALUES ($ph)$updates";
}

function valoresNaOrdem(array $row, array $campos) {
    $out = [];
    foreach ($campos as $c) $out[] = $row[$c] ?? null;
    return $out;
}

function prepararColunas(array $rowKeys, array $nomesB, array &$ignoradas = []) {
    $campos = [];
    foreach ($rowKeys as $f) {
        if (!is_string($f) || $f === '') continue;
        if (!in_array($f, $nomesB, true)) continue;
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $f)) { $ignoradas[] = $f; continue; }
        $campos[] = $f;
    }
    return $campos;
}

// ============================================================
// HELPER: Sincroniza a empresa de A para B (cria ou atualiza)
// ============================================================
function sincronizarEmpresaDeAparaB($connA, $connB, $tabEmpresa, $empIdA) {
    if (empty($empIdA)) return null;

    $stmt = $connA->prepare("SELECT * FROM `$tabEmpresa` WHERE `Empresa_ID` = ?");
    $stmt->execute([$empIdA]);
    $empresaA = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empresaA) return null;

    $nomeEmpresa = trim($empresaA['NomeEmpresa'] ?? '');
    if (empty($nomeEmpresa)) return null;

    $stmtB = $connB->prepare("SELECT Empresa_ID FROM `$tabEmpresa` WHERE NomeEmpresa = ? LIMIT 1");
    $stmtB->execute([$nomeEmpresa]);
    $idEmpB = $stmtB->fetchColumn();

    $colsB  = $connB->query("SHOW COLUMNS FROM `$tabEmpresa`")->fetchAll(PDO::FETCH_ASSOC);
    $nomesB = array_column($colsB, 'Field');
    
    $ignoradas = [];
    $campos = prepararColunas(array_keys($empresaA), $nomesB, $ignoradas);

    if ($idEmpB) {
        $camposOutros = array_values(array_diff($campos, ['Empresa_ID']));
        if (empty($camposOutros)) return (int)$idEmpB;

        $updates = implode(', ', array_map(fn($f) => "`$f` = ?", $camposOutros));
        $sql = "UPDATE `$tabEmpresa` SET $updates WHERE Empresa_ID = ?";
        $params = valoresNaOrdem($empresaA, $camposOutros);
        $params[] = $idEmpB;
        $connB->prepare($sql)->execute($params);
        slog("AUTO-UPDATE: Empresa '$nomeEmpresa' (B:#$idEmpB) atualizada.");
        return (int)$idEmpB;
    } else {
        $camposSemPK = array_values(array_diff($campos, ['Empresa_ID']));
        if (empty($camposSemPK)) return null;

        $cols = '`' . implode('`,`', $camposSemPK) . '`';
        $ph   = implode(',', array_fill(0, count($camposSemPK), '?'));
        $sql  = "INSERT INTO `$tabEmpresa` ($cols) VALUES ($ph)";
        $params = valoresNaOrdem($empresaA, $camposSemPK);
        $connB->prepare($sql)->execute($params);
        $novoId = (int)$connB->lastInsertId();
        slog("AUTO-CRIAÇÃO: Empresa '$nomeEmpresa' (A:#$empIdA) criada em B (#$novoId).");
        return $novoId;
    }
}

// ============================================================
// EXIBIÇÃO: Empresa + Empreendimento
// ============================================================
function nomeExibicao($row, $empNomes, $isEmpreend) {
    if (!$row) return '—';
    if ($isEmpreend) {
        $eid  = $row['Empresa_ID'] ?? $row['Empresa_Id'] ?? null;
        $emp  = ($eid !== null && isset($empNomes[$eid]))
              ? $empNomes[$eid]
              : ($row['NomeEmpresa'] ?? '');
        $nome = $row['NomeEmpreendimento'] ?? '';
        if ($emp && $nome) return $emp . ' — ' . $nome;
        if ($emp)  return $emp;
        if ($nome) return $nome;
        return '#' . ($row['Empreend_ID'] ?? '');
    }
    return $row['NomeEmpresa'] ?? ('#' . ($row['Empresa_ID'] ?? ''));
}

// ============================================================
// CONEXÕES + TABELAS
// ============================================================
$connA = conectar($config['A']);
$connB = conectar($config['B']);

$tabEmpresa = null;
foreach (['empresa', 'empresas', 'sunnyempresas'] as $t) {
    if (tabelaExiste($connA, $t) && tabelaExiste($connB, $t)) { $tabEmpresa = $t; break; }
}
$tabEmpreend = null;
foreach (['empreendimento', 'empreendimentos'] as $t) {
    if (tabelaExiste($connA, $t) && tabelaExiste($connB, $t)) { $tabEmpreend = $t; break; }
}

// ============================================================
// CARREGAR DADOS
// ============================================================
$empresasA = $empresasB = $empreendA = $empreendB = [];
$mapEmpA = $mapEmpB = $mapEmprA = $mapEmprB = [];
$empNomesA = $empNomesB = [];
$stats = ['onlyA' => 0, 'onlyB' => 0, 'diff' => 0, 'equal' => 0];
$statsEmpr = ['onlyA' => 0, 'onlyB' => 0, 'diff' => 0, 'equal' => 0];
$erroInicial = null;

if (!$connA || !$connB) {
    $erroInicial = 'Falha em uma ou ambas as conexões.';
} elseif (!$tabEmpresa || !$tabEmpreend) {
    $erroInicial = 'Tabelas não encontradas em A e B.';
} else {
    try {
        $empresasA = $connA->query("SELECT * FROM `$tabEmpresa`")->fetchAll();
        $empresasB = $connB->query("SELECT * FROM `$tabEmpresa`")->fetchAll();
        $empreendA = $connA->query("SELECT * FROM `$tabEmpreend`")->fetchAll();
        $empreendB = $connB->query("SELECT * FROM `$tabEmpreend`")->fetchAll();

        foreach ($empresasA as $r) {
            $id = (string)($r['Empresa_ID'] ?? $r['Empresa_Id'] ?? '');
            $mapEmpA[$id] = $r;
            $empNomesA[$id] = $r['NomeEmpresa'] ?? '';
        }
        foreach ($empresasB as $r) {
            $id = (string)($r['Empresa_ID'] ?? $r['Empresa_Id'] ?? '');
            $mapEmpB[$id] = $r;
            $empNomesB[$id] = $r['NomeEmpresa'] ?? '';
        }
        foreach ($empreendA as $r) $mapEmprA[(string)$r['Empreend_ID']] = $r;
        foreach ($empreendB as $r) $mapEmprB[(string)$r['Empreend_ID']] = $r;

        $exclude = $config['diff_exclude_fields'] ?? [];
        
        $diff = function($a, $b) use ($exclude) {
            if (!$a || !$b) return [];
            $d = [];
            foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $f) {
                if (in_array($f, $exclude)) continue;
                
                if ($f === 'Empresa_ID' || $f === 'Empresa_Id') continue;

                $va = $a[$f] ?? null; $vb = $b[$f] ?? null;
                if ((string)$va === (string)$vb) continue;
                if (($va === null || $va === '') && ($vb === null || $vb === '')) continue;
                if (is_numeric($va) && is_numeric($vb) && (float)$va == (float)$vb) continue;
                $d[$f] = ['A' => $va, 'B' => $vb];
            }
            return $d;
        };
        foreach (array_unique(array_merge(array_keys($mapEmpA), array_keys($mapEmpB))) as $id) {
            $a = $mapEmpA[$id] ?? null; $b = $mapEmpB[$id] ?? null;
            if ($a && $b) { $diff($a, $b) ? $stats['diff']++ : $stats['equal']++; }
            elseif ($a) $stats['onlyA']++; else $stats['onlyB']++;
        }
        foreach (array_unique(array_merge(array_keys($mapEmprA), array_keys($mapEmprB))) as $id) {
            $a = $mapEmprA[$id] ?? null; $b = $mapEmprB[$id] ?? null;
            if ($a && $b) { $diff($a, $b) ? $statsEmpr['diff']++ : $statsEmpr['equal']++; }
            elseif ($a) $statsEmpr['onlyA']++; else $statsEmpr['onlyB']++;
        }
    } catch (Throwable $e) {
        $erroInicial = $e->getMessage();
    }
}

// ============================================================
// DOWNLOAD IMAGEM UNITÁRIA
// ============================================================
if (isset($_GET['download_img'])) {
    ob_end_clean();
    $prefix = strtoupper($_GET['download_img'] ?? 'A');
    if (!in_array($prefix, ['A', 'B'])) $prefix = 'A';
    $imgId = (int)($_GET['id'] ?? 0);
    if ($imgId <= 0) { http_response_code(400); die('ID inválido.'); }

    $nome = '';
    if ($prefix === 'A') {
        $nome = $mapEmprA[(string)$imgId]['NomeEmpreendimento'] ?? ($mapEmprB[(string)$imgId]['NomeEmpreendimento'] ?? '');
    } else {
        $nome = $mapEmprB[(string)$imgId]['NomeEmpreendimento'] ?? ($mapEmprA[(string)$imgId]['NomeEmpreendimento'] ?? '');
    }
    $filename = "{$prefix}_{$imgId}_" . sanitizarNome($nome) . ".jpg";

    if ($prefix === 'A') {
        $srcPath = $cardDir . $imgId . '.jpg';
        if (file_exists($srcPath)) {
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($srcPath));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($srcPath);
            exit;
        }
        $srcUrl = $urlCardA . $imgId . '.jpg';
        $content = @file_get_contents($srcUrl);
        if ($content !== false) {
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        }
        http_response_code(404); die("Imagem A #$imgId não encontrada.");
    } else {
        $srcUrl = $urlCardB . $imgId . '.jpg';
        $content = @file_get_contents($srcUrl);
        if ($content !== false) {
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            header('Cache-Control: no-cache, must-revalidate');
            echo $content;
            exit;
        }
        http_response_code(404); die("Imagem B #$imgId não encontrada.");
    }
}

// ============================================================
// DOWNLOAD ZIP (Apenas imagens de A)
// ============================================================
if (isset($_GET['download_zip'])) {
    ob_end_clean();
    $files = glob($cardDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    if (empty($files)) die("Nenhuma imagem local em $cardDir");

    $tmp = tempnam(sys_get_temp_dir(), 'cards_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $f) {
        $id = (int)basename($f, '.' . pathinfo($f, PATHINFO_EXTENSION));
        $nome = $mapEmprA[(string)$id]['NomeEmpreendimento']
              ?? ($mapEmprB[(string)$id]['NomeEmpreendimento'] ?? '');
        $zip->addFile($f, 'A_' . $id . '_' . sanitizarNome($nome) . '.jpg');
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="cards_A_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

// ============================================================
// AJAX HANDLERS
// ============================================================
if (isset($_GET['action'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];
    $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    slog("action=$action id=$id");

    try {
        if ($action === 'diag') {
            echo json_encode([
                'ok' => true,
                'A'  => ['conectado' => (bool)$connA, 'label' => $config['A']['label'], 'db' => $config['A']['db'], 'empresa' => $tabEmpresa, 'empreend' => $tabEmpreend],
                'B'  => ['conectado' => (bool)$connB, 'label' => $config['B']['label'], 'db' => $config['B']['db']],
            ]);
            exit;
        }

        if (!$connA || !$connB) throw new Exception("Sem conexão.");
        if (!$tabEmpresa || !$tabEmpreend) throw new Exception("Tabelas não encontradas.");

        if ($action === 'debug_sync') {
            $tipo    = $_POST['tipo'] ?? 'empreend';
            $tabela  = ($tipo === 'empresa') ? $tabEmpresa : $tabEmpreend;
            $pk      = ($tipo === 'empresa') ? 'Empresa_ID' : 'Empreend_ID';

            $stmt = $connA->prepare("SELECT * FROM `$tabela` WHERE `$pk` = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) throw new Exception("Registro #$id não encontrado em A.");

            $colsB  = $connB->query("SHOW COLUMNS FROM `$tabela`")->fetchAll();
            $nomesB = array_column($colsB, 'Field');
            $ignoradas = [];
            $campos = prepararColunas(array_keys($row), $nomesB, $ignoradas);

            echo json_encode([
                'ok' => true,
                'debug' => [
                    'tabela' => $tabela, 'pk' => $pk, 'id' => $id,
                    'colunas_A' => array_keys($row), 'colunas_B' => $nomesB,
                    'colunas_usadas' => $campos, 'colunas_ignoradas' => $ignoradas,
                    'sql' => montarUpsert($tabela, $campos, $pk),
                    'params_count' => count($campos),
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'sync_empresa') {
            if ($id <= 0) throw new Exception("ID inválido.");
            $stmt = $connA->prepare("SELECT * FROM `$tabEmpresa` WHERE `Empresa_ID` = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) throw new Exception("Empresa #$id não existe em A.");

            $colsB  = $connB->query("SHOW COLUMNS FROM `$tabEmpresa`")->fetchAll();
            $nomesB = array_column($colsB, 'Field');
            $ignoradas = [];
            $campos = prepararColunas(array_keys($row), $nomesB, $ignoradas);
            $sql    = montarUpsert($tabEmpresa, $campos, 'Empresa_ID');
            $params = valoresNaOrdem($row, $campos);

            try {
                $connB->beginTransaction();
                $connB->prepare($sql)->execute($params);
                $connB->commit();
                slog("OK sync_empresa id=$id");
                echo json_encode(['ok' => true, 'msg' => "Empresa #$id sincronizada para B.", 'colunas_ignoradas' => $ignoradas]);
            } catch (PDOException $e) {
                if ($connB->inTransaction()) $connB->rollBack();
                slog("ERRO sync_empresa id=$id: " . $e->getMessage());
                throw new Exception("Erro SQL: " . $e->getMessage());
            }
            exit;
        }

        if ($action === 'sync_empreend') {
            if ($id <= 0) throw new Exception("ID inválido.");
            $stmt = $connA->prepare("SELECT * FROM `$tabEmpreend` WHERE `Empreend_ID` = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) throw new Exception("Empreendimento #$id não existe em A.");

            $empIdA = $row['Empresa_ID'] ?? $row['Empresa_Id'] ?? null;
            if (!empty($empIdA)) {
                $novoIdB = sincronizarEmpresaDeAparaB($connA, $connB, $tabEmpresa, $empIdA);
                if ($novoIdB) {
                    if (isset($row['Empresa_ID'])) $row['Empresa_ID'] = $novoIdB;
                    if (isset($row['Empresa_Id'])) $row['Empresa_Id'] = $novoIdB;
                }
            }

            $colsB  = $connB->query("SHOW COLUMNS FROM `$tabEmpreend`")->fetchAll();
            $nomesB = array_column($colsB, 'Field');
            $ignoradas = [];
            $campos = prepararColunas(array_keys($row), $nomesB, $ignoradas);
            $sql    = montarUpsert($tabEmpreend, $campos, 'Empreend_ID');
            $params = valoresNaOrdem($row, $campos);

            try {
                $connB->beginTransaction();
                $connB->prepare($sql)->execute($params);
                $connB->commit();
                slog("OK sync_empreend id=$id");
                echo json_encode(['ok' => true, 'msg' => "Empreendimento #$id sincronizado para B.", 'colunas_ignoradas' => $ignoradas]);
            } catch (PDOException $e) {
                if ($connB->inTransaction()) $connB->rollBack();
                slog("ERRO sync_empreend id=$id: " . $e->getMessage());
                throw new Exception("Erro SQL: " . $e->getMessage());
            }
            exit;
        }

        if ($action === 'sync_all_empresas') {
            $ids   = $connA->query("SELECT Empresa_ID FROM `$tabEmpresa`")->fetchAll(PDO::FETCH_COLUMN);
            $colsB = array_column($connB->query("SHOW COLUMNS FROM `$tabEmpresa`")->fetchAll(), 'Field');
            $ok = 0; $erros = [];
            foreach ($ids as $pk) {
                try {
                    $stmt = $connA->prepare("SELECT * FROM `$tabEmpresa` WHERE `Empresa_ID` = ?");
                    $stmt->execute([$pk]);
                    $row = $stmt->fetch();
                    if (!$row) continue;
                    $ignoradas = [];
                    $campos = prepararColunas(array_keys($row), $colsB, $ignoradas);
                    if (count($campos) < 2) continue;
                    $connB->beginTransaction();
                    $connB->prepare(montarUpsert($tabEmpresa, $campos, 'Empresa_ID'))->execute(valoresNaOrdem($row, $campos));
                    $connB->commit();
                    $ok++;
                } catch (Exception $e) {
                    if ($connB->inTransaction()) $connB->rollBack();
                    $erros[] = "Empresa #$pk: " . $e->getMessage();
                }
            }
            echo json_encode(['ok' => true, 'msg' => "$ok empresa(s) sincronizada(s).", 'erros' => $erros]);
            exit;
        }

        if ($action === 'sync_all_empreend') {
            $ids   = $connA->query("SELECT Empreend_ID FROM `$tabEmpreend`")->fetchAll(PDO::FETCH_COLUMN);
            $colsB = array_column($connB->query("SHOW COLUMNS FROM `$tabEmpreend`")->fetchAll(), 'Field');
            $ok = 0; $erros = []; $empresasCriadas = 0;
            
            foreach ($ids as $pk) {
                try {
                    $stmt = $connA->prepare("SELECT * FROM `$tabEmpreend` WHERE `Empreend_ID` = ?");
                    $stmt->execute([$pk]);
                    $row = $stmt->fetch();
                    if (!$row) continue;

                    $empIdA = $row['Empresa_ID'] ?? $row['Empresa_Id'] ?? null;
                    if (!empty($empIdA)) {
                        $novoIdB = sincronizarEmpresaDeAparaB($connA, $connB, $tabEmpresa, $empIdA);
                        if ($novoIdB) {
                            if ($novoIdB !== (int)$empIdA) $empresasCriadas++;
                            if (isset($row['Empresa_ID'])) $row['Empresa_ID'] = $novoIdB;
                            if (isset($row['Empresa_Id'])) $row['Empresa_Id'] = $novoIdB;
                        }
                    }

                    $ignoradas = [];
                    $campos = prepararColunas(array_keys($row), $colsB, $ignoradas);
                    if (count($campos) < 2) continue;
                    $connB->beginTransaction();
                    $connB->prepare(montarUpsert($tabEmpreend, $campos, 'Empreend_ID'))->execute(valoresNaOrdem($row, $campos));
                    $connB->commit();
                    $ok++;
                } catch (Exception $e) {
                    if ($connB->inTransaction()) $connB->rollBack();
                    $erros[] = "Empreend #$pk: " . $e->getMessage();
                }
            }
            
            $msgFinal = "$ok empreendimento(s) sincronizado(s).";
            if ($empresasCriadas > 0) $msgFinal .= " $empresasCriadas empresa(s) criada(s).";
            
            echo json_encode(['ok' => true, 'msg' => $msgFinal, 'erros' => $erros]);
            exit;
        }

        throw new Exception("Ação desconhecida: $action");
    } catch (Throwable $e) {
        slog("ERRO action=$action id=$id: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        exit;
    }
}

// ============================================================
// PREPARAR ABA
// ============================================================
$tab = $_GET['tab'] ?? 'empreend';
if (!in_array($tab, ['empresa', 'empreend', 'imagens', 'diag'])) $tab = 'empreend';

$statsAtual    = ($tab === 'empresa') ? $stats : $statsEmpr;
$excludeFields = $config['diff_exclude_fields'] ?? [];
$diffFn = function($a, $b) use ($excludeFields) {
    if (!$a || !$b) return [];
    $d = [];
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $f) {
        if (in_array($f, $excludeFields)) continue;
        if ($f === 'Empresa_ID' || $f === 'Empresa_Id') continue;

        $va = $a[$f] ?? null; $vb = $b[$f] ?? null;
        if ((string)$va === (string)$vb) continue;
        if (($va === null || $va === '') && ($vb === null || $vb === '')) continue;
        if (is_numeric($va) && is_numeric($vb) && (float)$va == (float)$vb) continue;
        $d[$f] = ['A' => $va, 'B' => $vb];
    }
    return $d;
};

$imagens = [];
foreach (glob($cardDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) {
    $imagens[basename($f, '.' . pathinfo($f, PATHINFO_EXTENSION))] = $f;
}
$qtdImagens = count($imagens);

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparador A/B · SunnyImob</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: <?= $theme['primary'] ?? '#800020' ?>;
            --shadow-color: <?= $theme['shadow_color'] ?? '#1a1a2e' ?>;
            --bg-body: <?= $theme['bg_body'] ?? '#f0f2f5' ?>;
            --card-bg: <?= $theme['card_bg'] ?? '#ffffff' ?>;
            --text-color: <?= $theme['text_color'] ?? '#1a1a2e' ?>;
            --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; padding: 20px;
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .container-neo { max-width: 1700px; margin: 0 auto; }

        .app-header {
            background: var(--primary); color: #fff;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            border-radius: 18px 18px 0 0;
            padding: 16px 24px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; flex-wrap: wrap; margin-bottom: 22px;
        }
        .app-header h1 { margin: 0; font-size: 1.3rem; font-weight: 900; }
        .app-header p { margin: 4px 0 0; font-size: 0.78rem; font-weight: 600; opacity: 0.9; }

        .conn-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(0,0,0,0.2);
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 0.75rem; font-weight: 800;
        }
        .conn-badge .dot { width: 8px; height: 8px; border-radius: 50%; box-shadow: 0 0 8px currentColor; }
        .conn-badge .dot.on  { background: var(--success); color: var(--success); }
        .conn-badge .dot.off { background: var(--danger);  color: var(--danger); }

        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 700; font-size: 0.88rem;
            margin-bottom: 18px;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .alert-neo.danger { background: var(--danger); color: #fff; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 22px; }
        .stat-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 12px 16px;
            display: flex; align-items: center; gap: 12px;
        }
        .stat-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .stat-icon.i-info    { background: var(--info); }
        .stat-icon.i-danger  { background: var(--danger); }
        .stat-icon.i-warning { background: var(--warning); color: #000; }
        .stat-icon.i-success { background: var(--success); }
        .stat-num { font-size: 1.4rem; font-weight: 900; line-height: 1; }
        .stat-lbl { font-size: 0.64rem; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700; opacity: 0.65; margin-top: 3px; }

        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
        .tab-btn {
            background: var(--card-bg); color: var(--text-color);
            border: 3px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 800; font-size: 0.84rem;
            text-decoration: none;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.15s;
        }
        .tab-btn:hover { transform: translate(-1px, -1px); box-shadow: 4px 4px 0 0 var(--shadow-color); color: var(--text-color); }
        .tab-btn.active { background: var(--primary); color: #fff; }

        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; }

        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 800; font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 6px;
            font-family: inherit;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-neo:hover:not(:disabled) { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 0 var(--shadow-color); color: #fff; }
        .btn-neo:active:not(:disabled) { transform: translate(1px, 1px); box-shadow: 1px 1px 0 0 var(--shadow-color); }
        .btn-neo:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-neo-success   { background: var(--success); color: #fff; }
        .btn-neo-primary   { background: var(--primary); color: #fff; }
        .btn-neo-secondary { background: #fff; color: var(--text-color); }

        .btn-icon {
            width: 30px; height: 30px; padding: 0;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            font-size: 0.72rem;
            text-decoration: none;
            transition: all 0.15s;
            background: var(--primary); color: #fff;
        }
        .btn-icon:hover:not(:disabled) { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); color: #fff; }
        .btn-icon:disabled { opacity: 0.5; cursor: wait; }
        .btn-icon.info    { background: var(--info); }
        .btn-icon.danger  { background: var(--danger); }
        .btn-icon.success { background: var(--success); }

        .table-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
        }
        .table-responsive { overflow-x: auto; }
        table.cmp-table { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--card-bg); color: var(--text-color); }
        table.cmp-table thead th {
            background: var(--primary); color: #fff;
            font-weight: 800; text-transform: uppercase;
            font-size: 0.68rem; letter-spacing: 0.8px;
            padding: 12px 10px;
            border-bottom: 3px solid var(--shadow-color);
            white-space: nowrap;
        }
        table.cmp-table thead th.col-a { background: #5e1821; }
        table.cmp-table thead th.col-b { background: #1f3e5c; }
        table.cmp-table tbody td {
            padding: 8px 10px; font-size: 0.82rem;
            border-bottom: 1px solid rgba(0,0,0,0.07);
            vertical-align: middle;
        }
        table.cmp-table tbody tr:last-child td { border-bottom: none; }
        table.cmp-table tbody tr:hover td { background: rgba(0,0,0,0.02); }
        table.cmp-table tbody tr.row-onlyA td { background: rgba(59,130,246,0.05); }
        table.cmp-table tbody tr.row-onlyB td { background: rgba(239,68,68,0.05); }
        table.cmp-table tbody tr.row-diff td  { background: rgba(245,158,11,0.06); }

        .cell-id {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-weight: 800; font-size: 0.72rem;
            background: rgba(0,0,0,0.06);
            padding: 2px 6px; border-radius: 6px;
        }

        .cell-nome {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            max-width: 280px;
        }
        .cell-empresa {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--info);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .cell-empresa i { font-size: 0.65rem; opacity: 0.7; }
        .cell-empresa.col-b-cor { color: var(--danger); }
        .cell-empreend {
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Imagens dentro da tabela */
        .thumb-cell {
            width: 54px;
            height: 42px;
            object-fit: cover;
            border: 2px solid var(--shadow-color);
            border-radius: 6px;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            cursor: pointer;
            flex-shrink: 0;
            background: rgba(0,0,0,0.05);
            transition: all 0.15s;
        }
        .thumb-cell:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); }
        .thumb-cell-empty {
            width: 54px;
            height: 42px;
            display: flex; align-items: center; justify-content: center;
            border: 2px dashed rgba(0,0,0,0.15);
            border-radius: 6px;
            background: rgba(0,0,0,0.03);
            color: var(--text-color);
            opacity: 0.35;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Aba Imagens */
        .img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
        .img-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            overflow: hidden;
            transition: all 0.15s;
        }
        .img-card:hover { transform: translateY(-2px); box-shadow: 6px 6px 0 0 var(--shadow-color); }
        .img-card-imgs { display: flex; height: 140px; }
        .img-card-half { width: 50%; position: relative; background: rgba(0,0,0,0.03); cursor: pointer; }
        .img-card-half img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .img-card-half .img-empty {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-color); opacity: 0.2; font-size: 1.5rem;
        }
        .img-card-label {
            position: absolute; top: 4px; left: 4px;
            font-size: 0.55rem; font-weight: 900; text-transform: uppercase;
            padding: 2px 6px; border-radius: 4px; border: 1px solid var(--shadow-color);
        }
        .img-card-label.a { background: var(--info); color: #fff; }
        .img-card-label.b { background: var(--danger); color: #fff; right: 4px; left: auto; }
        
        .img-card-body { padding: 10px 12px; }
        .img-card-title { font-weight: 800; font-size: 0.82rem; margin-bottom: 4px; line-height: 1.2; }
        .img-card-empresa { font-size: 0.7rem; font-weight: 700; color: var(--info); text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 4px; }
        .img-card-meta { font-size: 0.68rem; opacity: 0.6; font-weight: 600; display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
        .img-card-badges { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }

        .info-box {
            background: rgba(59,130,246,0.08);
            border: 2px solid var(--info);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.82rem; font-weight: 600;
            margin-bottom: 16px;
        }

        .diag-box {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 18px;
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.78rem;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .diag-box .k { color: var(--primary); font-weight: 800; }

        .empty-state { text-align: center; padding: 50px 20px; opacity: 0.6; font-weight: 700; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: 0.5; }

        #imgPreviewModal {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.92);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            cursor: zoom-out;
        }
        #imgPreviewModal img {
            max-width: 95vw; max-height: 90vh;
            object-fit: contain;
            border: 4px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 8px 8px 0 0 var(--shadow-color);
            cursor: default;
        }
        #imgPreviewModal .close-modal {
            position: absolute; top: 15px; right: 30px;
            color: #fff; font-size: 2.5rem; font-weight: 900;
            cursor: pointer; line-height: 1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.6);
            transition: transform 0.15s;
        }
        #imgPreviewModal .close-modal:hover { transform: scale(1.15); }
    </style>
</head>
<body>

<?php if ($erroInicial): ?>
    <div class="container-neo">
        <div class="alert-neo danger"><i class="fas fa-exclamation-triangle"></i><div><?= htmlspecialchars($erroInicial) ?></div></div>
    </div>
<?php endif; ?>

<div class="container-neo">

    <header class="app-header">
        <div>
            <h1><i class="fas fa-code-compare me-2"></i> Comparador A/B</h1>
            <p>Empresas · Empreendimentos · Imagens · Sincronização</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="conn-badge"><span class="dot <?= $connA ? 'on' : 'off' ?>"></span>A · <?= htmlspecialchars($config['A']['label']) ?></span>
            <span class="conn-badge"><span class="dot <?= $connB ? 'on' : 'off' ?>"></span>B · <?= htmlspecialchars($config['B']['label']) ?></span>
        </div>
    </header>

    <nav class="tabs">
        <a class="tab-btn <?= $tab === 'empresa'  ? 'active' : '' ?>" href="?tab=empresa">
            <i class="fas fa-building"></i> Empresas (<?= count($mapEmpA) ?>)
        </a>
        <a class="tab-btn <?= $tab === 'empreend' ? 'active' : '' ?>" href="?tab=empreend">
            <i class="fas fa-city"></i> Empreendimentos (<?= count($mapEmprA) ?>)
        </a>
        <a class="tab-btn <?= $tab === 'imagens'  ? 'active' : '' ?>" href="?tab=imagens">
            <i class="fas fa-images"></i> Imagens
        </a>
        <a class="tab-btn <?= $tab === 'diag'     ? 'active' : '' ?>" href="?tab=diag">
            <i class="fas fa-stethoscope"></i> Diagnóstico
        </a>
    </nav>

    <?php if ($tab === 'diag'): ?>
        <div class="toolbar">
            <button class="btn-neo btn-neo-primary" onclick="carregarDiag()"><i class="fas fa-play"></i> Executar</button>
        </div>
        <div class="diag-box" id="diagBox">Clique em "Executar".</div>

    <?php elseif ($tab === 'imagens'): ?>
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Como transferir imagens de A → B:</strong>
            <ol style="margin: 8px 0 0; padding-left: 22px;">
                <li>Clique em <b>"Baixar todas (ZIP)"</b> — arquivos já vêm renomeados como <code>A_581_nome_empreendimento.jpg</code>.</li>
                <li>No servidor de <b>B</b>: cPanel → Gerenciador de Arquivos → <code>card/</code> → Upload do ZIP → Extrair.</li>
            </ol>
        </div>

        <div class="toolbar">
            <a href="?download_zip=1" class="btn-neo btn-neo-primary"><i class="fas fa-file-zipper"></i> Baixar todas de A (ZIP)</a>
            <a href="?tab=empreend" class="btn-neo btn-neo-secondary"><i class="fas fa-arrow-left"></i> Voltar aos Empreendimentos</a>
        </div>

        <?php if (empty($imagens) && empty($mapEmprA)): ?>
            <div class="empty-state"><i class="fas fa-images"></i>Nenhuma imagem e nenhum empreendimento.</div>
        <?php else: ?>
            <div class="img-grid">
            <?php foreach (array_unique(array_merge(array_keys($mapEmprA), array_keys($mapEmprB))) as $id):
                $a = $mapEmprA[$id] ?? null;
                $b = $mapEmprB[$id] ?? null;
                $nome = $a['NomeEmpreendimento'] ?? $b['NomeEmpreendimento'] ?? "Empreendimento #$id";
                
                $empIdA = $a['Empresa_ID'] ?? null;
                $empIdB = $b['Empresa_ID'] ?? null;
                $empId = $empIdA ?? $empIdB;
                $empNome = $empId ? ($empNomesA[$empId] ?? $empNomesB[$empId] ?? '') : ($a['NomeEmpresa'] ?? '');

                $urlA = $urlCardA . $id . '.jpg';
                $urlB = $urlCardB . $id . '.jpg';
            ?>
                <div class="img-card">
                    <div class="img-card-imgs">
                        <div class="img-card-half" onclick="abrirImagem('<?= $urlA ?>')">
                            <span class="img-card-label a">A</span>
                            <img src="<?= $urlA ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="img-empty" style="display:none;"><i class="fas fa-image"></i></div>
                        </div>
                        <div class="img-card-half" onclick="abrirImagem('<?= $urlB ?>')">
                            <span class="img-card-label b">B</span>
                            <img src="<?= $urlB ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="img-empty" style="display:none;"><i class="fas fa-image"></i></div>
                        </div>
                    </div>
                    <div class="img-card-body">
                        <?php if ($empNome): ?>
                            <div class="img-card-empresa"><i class="fas fa-building me-1"></i><?= htmlspecialchars($empNome) ?></div>
                        <?php endif; ?>
                        <div class="img-card-title"><span class="cell-id">#<?= htmlspecialchars((string)$id) ?></span> <?= htmlspecialchars(mb_substr($nome, 0, 40)) ?></div>
                        <div class="img-card-meta">
                            <?php if ($a): ?><span><i class="fas fa-circle-check" style="color:var(--info)"></i> Em A</span><?php endif; ?>
                            <?php if ($b): ?><span><i class="fas fa-circle-check" style="color:var(--danger)"></i> Em B</span><?php endif; ?>
                        </div>
                        <div class="img-card-badges">
                            <?php if ($a): ?>
                                <a href="?download_img=A&id=<?= (int)$id ?>" class="btn-icon info" style="width:26px;height:26px;" title="Baixar A_<?= $id ?>_<?= sanitizarNome($nome) ?>.jpg">
                                    <i class="fas fa-download"></i> A
                                </a>
                            <?php endif; ?>
                            <?php if ($b): ?>
                                <a href="?download_img=B&id=<?= (int)$id ?>" class="btn-icon danger" style="width:26px;height:26px;" title="Baixar B_<?= $id ?>_<?= sanitizarNome($nome) ?>.jpg">
                                    <i class="fas fa-download"></i> B
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <?php $isEmpreend = ($tab === 'empreend'); ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon i-info"><i class="fas fa-arrow-right-from-bracket"></i></div>
                <div><div class="stat-num"><?= $statsAtual['onlyA'] ?></div><div class="stat-lbl">Só em A</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon i-danger"><i class="fas fa-arrow-right-to-bracket"></i></div>
                <div><div class="stat-num"><?= $statsAtual['onlyB'] ?></div><div class="stat-lbl">Só em B</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon i-warning"><i class="fas fa-not-equal"></i></div>
                <div><div class="stat-num"><?= $statsAtual['diff'] ?></div><div class="stat-lbl">Diferentes</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon i-success"><i class="fas fa-equals"></i></div>
                <div><div class="stat-num"><?= $statsAtual['equal'] ?></div><div class="stat-lbl">Iguais</div></div>
            </div>
        </div>

        <div class="toolbar">
            <button class="btn-neo btn-neo-success" id="btnSyncAll" onclick="syncAll('<?= $tab ?>')">
                <i class="fas fa-cloud-arrow-up"></i> Sincronizar TUDO A → B
            </button>
            <a href="?tab=<?= $tab ?>" class="btn-neo btn-neo-secondary"><i class="fas fa-rotate"></i> Recarregar</a>
            <span style="font-size: 0.82rem; font-weight: 700; margin-left: auto;">
                <i class="fas fa-database"></i> <code><?= htmlspecialchars($isEmpreend ? $tabEmpreend : $tabEmpresa) ?></code>
            </span>
        </div>

        <?php
        $idsTabela = $isEmpreend
            ? array_unique(array_merge(array_keys($mapEmprA), array_keys($mapEmprB)))
            : array_unique(array_merge(array_keys($mapEmpA), array_keys($mapEmpB)));
        $mapA = $isEmpreend ? $mapEmprA : $mapEmpA;
        $mapB = $isEmpreend ? $mapEmprB : $mapEmpB;
        ?>

        <div class="table-card">
            <div class="table-responsive">
                <table class="cmp-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th style="width:130px;">Status</th>
                            <th class="col-a">A — <?= htmlspecialchars($config['A']['label']) ?></th>
                            <th class="col-b">B — <?= htmlspecialchars($config['B']['label']) ?></th>
                            <th style="width:200px;">Diferenças</th>
                            <th style="width:120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($idsTabela)): ?>
                        <tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i>Nenhum registro.</td></tr>
                    <?php else: foreach ($idsTabela as $id):
                        $a = $mapA[$id] ?? null;
                        $b = $mapB[$id] ?? null;
                        if ($a && $b) { $diffs = $diffFn($a, $b); $status = empty($diffs) ? 'equal' : 'diff'; }
                        elseif ($a)   { $status = 'onlyA'; $diffs = []; }
                        else          { $status = 'onlyB'; $diffs = []; }
                        $rowClass = match($status) { 'onlyA' => 'row-onlyA', 'onlyB' => 'row-onlyB', 'diff' => 'row-diff', default => '' };

                        $empIdA = $a['Empresa_ID'] ?? null;
                        $empIdB = $b['Empresa_ID'] ?? null;

                        $empA = $isEmpreend && $a
                            ? ($empNomesA[$empIdA] ?? ($a['NomeEmpresa'] ?? ''))
                            : '';
                        $empB = $isEmpreend && $b
                            ? ($empNomesB[$empIdB] ?? ($b['NomeEmpresa'] ?? ''))
                            : '';

                        $empAmostraA = $isEmpreend ? ($a['NomeEmpreendimento'] ?? '') : ($a['NomeEmpresa'] ?? '');
                        $empAmostraB = $isEmpreend ? ($b['NomeEmpreendimento'] ?? '') : ($b['NomeEmpresa'] ?? '');

                        $urlA = $urlCardA . $id . '.jpg';
                        $urlB = $urlCardB . $id . '.jpg';
                    ?>
                        <tr class="<?= $rowClass ?>">
                            <td><span class="cell-id">#<?= htmlspecialchars((string)$id) ?></span></td>

                            <td>
                                <?php if ($status === 'onlyA'): ?><span class="badge-status onlyA"><i class="fas fa-circle"></i> Só A</span>
                                <?php elseif ($status === 'onlyB'): ?><span class="badge-status onlyB"><i class="fas fa-circle"></i> Só B</span>
                                <?php elseif ($status === 'diff'): ?><span class="badge-status diff"><i class="fas fa-triangle-exclamation"></i> <?= count($diffs) ?> diff</span>
                                <?php else: ?><span class="badge-status equal"><i class="fas fa-check"></i> Igual</span><?php endif; ?>
                            </td>

                            <td>
                                <?php if ($a): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($isEmpreend): ?>
                                            <img src="<?= $urlA ?>" class="thumb-cell" onclick="abrirImagem('<?= $urlA ?>')" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="thumb-cell-empty" style="display:none;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                        
                                        <div class="cell-nome">
                                            <?php if ($isEmpreend && $empA): ?>
                                                <div class="cell-empresa" title="<?= htmlspecialchars($empA) ?>">
                                                    <i class="fas fa-building"></i> <?= htmlspecialchars(mb_substr($empA, 0, 34)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="cell-empreend" title="<?= htmlspecialchars($empAmostraA) ?>">
                                                <?= htmlspecialchars(mb_substr($empAmostraA, 0, 60)) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?><span style="opacity:0.4">—</span><?php endif; ?>
                            </td>

                            <td>
                                <?php if ($b): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($isEmpreend): ?>
                                            <img src="<?= $urlB ?>" class="thumb-cell" onclick="abrirImagem('<?= $urlB ?>')" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="thumb-cell-empty" style="display:none;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>

                                        <div class="cell-nome">
                                            <?php if ($isEmpreend && $empB): ?>
                                                <div class="cell-empresa col-b-cor" title="<?= htmlspecialchars($empB) ?>">
                                                    <i class="fas fa-building"></i> <?= htmlspecialchars(mb_substr($empB, 0, 34)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="cell-empreend" title="<?= htmlspecialchars($empAmostraB) ?>">
                                                <?= htmlspecialchars(mb_substr($empAmostraB, 0, 60)) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?><span style="opacity:0.4">—</span><?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($diffs)): ?>
                                    <div class="diff-chips">
                                        <?php foreach (array_keys($diffs) as $f): ?>
                                            <span class="diff-chip" title="A: <?= htmlspecialchars((string)($diffs[$f]['A'] ?? 'null')) ?> · B: <?= htmlspecialchars((string)($diffs[$f]['B'] ?? 'null')) ?>"><?= htmlspecialchars($f) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?><span style="opacity:0.4">—</span><?php endif; ?>
                            </td>

                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <?php if ($a): ?>
                                        <button class="btn-icon success" onclick="syncOne('<?= $tab ?>', <?= (int)$id ?>, this)" title="Sincronizar A → B">
                                            <i class="fas fa-arrow-right"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="imgPreviewModal" onclick="if(event.target===this) fecharImagem()">
    <span class="close-modal" onclick="fecharImagem()">&times;</span>
    <img id="imgPreviewSrc" alt="">
</div>

<script>
const BASE_URL = window.location.pathname;
const AJAX_URL = (extra) => BASE_URL + '?' + extra;

function abrirImagem(src) {
    document.getElementById('imgPreviewSrc').src = src;
    document.getElementById('imgPreviewModal').style.display = 'flex';
}
function fecharImagem() {
    document.getElementById('imgPreviewModal').style.display = 'none';
    document.getElementById('imgPreviewSrc').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharImagem(); });

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function syncOne(tipo, id, btnEl) {
    const acao  = tipo === 'empresa' ? 'sync_empresa' : 'sync_empreend';
    const label = tipo === 'empresa' ? 'empresa' : 'empreendimento';

    const r = await Swal.fire({
        title: `Sincronizar ${label} #${id}?`,
        html: `A → B<br><small style="color:#666">Se a empresa não existir em B, ela será criada/atualizada automaticamente.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sincronizar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981'
    });
    if (!r.isConfirmed) return;

    const originalHTML = btnEl ? btnEl.innerHTML : null;
    if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
    Swal.fire({ title: 'Sincronizando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch(AJAX_URL('action=' + acao), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id),
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const rawText = await resp.text();
        console.log('[SYNC RAW]', rawText);
        let res;
        try { res = JSON.parse(rawText); }
        catch (e) {
            Swal.fire({ icon: 'error', title: 'Resposta inválida',
                html: '<pre style="text-align:left;font-size:11px;background:#111;color:#0f0;padding:10px;border-radius:6px;max-height:300px;overflow:auto;">' + escapeHtml(rawText) + '</pre>',
                width: 700 });
            return;
        }
        if (res.ok) {
            let extra = '';
            if (res.colunas_ignoradas && res.colunas_ignoradas.length) {
                extra = '<br><br><small style="color:#f59e0b;font-weight:700;">Colunas ignoradas: ' + res.colunas_ignoradas.map(escapeHtml).join(', ') + '</small>';
            }
            await Swal.fire({ icon: 'success', title: 'Sincronizado!', html: res.msg + extra, timer: 2500, showConfirmButton: false });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: res.msg || 'Falha ao sincronizar' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Erro de rede', text: String(err) });
    } finally {
        if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = originalHTML; }
    }
}

async function syncAll(tipo) {
    const acao  = tipo === 'empresa' ? 'sync_all_empresas' : 'sync_all_empreend';
    const label = tipo === 'empresa' ? 'empresas' : 'empreendimentos';
    const r = await Swal.fire({
        title: `Sincronizar TODAS as ${label}?`,
        html: `Todos os registros de <b>A</b> serão copiados/atualizados em <b>B</b>.<br><br><small style="color:#d97706;font-weight:700;">⚠️ Empresas faltantes serão criadas automaticamente em B.</small>`,
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Sim, sincronizar tudo', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444'
    });
    if (!r.isConfirmed) return;

    const btn = document.getElementById('btnSyncAll');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...'; }
    Swal.fire({ title: 'Sincronizando em lote...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await fetch(AJAX_URL('action=' + acao), { method: 'POST', credentials: 'same-origin', cache: 'no-store' });
        const rawText = await resp.text();
        let res;
        try { res = JSON.parse(rawText); }
        catch (e) { Swal.fire({ icon: 'error', title: 'Resposta inválida', html: '<pre>' + escapeHtml(rawText) + '</pre>' }); return; }

        if (res.ok) {
            let extra = '';
            if (res.erros && res.erros.length) {
                extra = '<br><br><b>Erros (até 5):</b><br>' + res.erros.slice(0, 5).map(escapeHtml).join('<br>');
            }
            await Swal.fire({ icon: 'success', title: 'Concluído', html: res.msg + extra });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: res.msg });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Erro de rede', text: String(err) });
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Sincronizar TUDO A → B'; }
    }
}

async function carregarDiag() {
    const box = document.getElementById('diagBox');
    box.textContent = 'Carregando...';
    try {
        const resp = await fetch(AJAX_URL('action=diag'), { credentials: 'same-origin', cache: 'no-store' });
        const rawText = await resp.text();
        let res;
        try { res = JSON.parse(rawText); }
        catch (e) { box.textContent = 'Resposta não-JSON:\n\n' + rawText; return; }
        box.innerHTML =
            '<span class="k">== CONEXÕES ==</span>\n' +
            '<span class="k">A:</span> ' + (res.A.conectado ? '✅ OK' : '❌ FALHA') + '  (' + escapeHtml(res.A.db) + ')\n' +
            '<span class="k">B:</span> ' + (res.B.conectado ? '✅ OK' : '❌ FALHA') + '  (' + escapeHtml(res.B.db) + ')\n\n' +
            '<span class="k">== TABELAS ==</span>\n' +
            'empresa: ' + escapeHtml(res.A.empresa || '—') + '\n' +
            'empreendimento: ' + escapeHtml(res.A.empreend || '—') + '\n';
    } catch (err) {
        box.textContent = 'Erro: ' + err;
    }
}
</script>
</body>
</html>
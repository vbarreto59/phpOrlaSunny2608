<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php';

if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

$empresaId   = $_SESSION['IdEmp'] ?? 1;
$nomeEmpresa = $_SESSION['Empresa'] ?? 'ORLA CAPTAÇÕES';
$usuarioId   = $_SESSION['UserId'] ?? 0;
$nomeUsuario = $_SESSION['Usuario'] ?? 'SISTEMA_TESTE';

$themeDefaults = ['primary' => '#800020', 'primary_dark' => '#4B0C1D', 'bg_body' => '#f8f9fa', 'text_color' => '#1a1a2e', 'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e'];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

try { $db->exec("ALTER TABLE empreendimento ADD COLUMN is_teste TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE empreendimento ADD COLUMN Usuario_ID INT(11) DEFAULT NULL"); } catch (Exception $e) {}

// Função para deletar pasta fisicamente
function deletarPastaRecursiva($dir) {
    if (!is_dir($dir)) return;
    $itens = array_diff(scandir($dir), array('.', '..'));
    foreach ($itens as $item) {
        $caminho = $dir . '/' . $item;
        if (is_dir($caminho)) { deletarPastaRecursiva($caminho); } 
        else { @chmod($caminho, 0777); @unlink($caminho); }
    }
    @chmod($dir, 0777); @rmdir($dir);
}

$mensagem = ""; $tipoMsg = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'inserir') {
        $imagensCard = glob(__DIR__ . '/card/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG}', GLOB_BRACE);
        
        $nomesProprietarios = ['João Silva', 'Maria Oliveira', 'Carlos Souza', 'Ana Costa', 'Pedro Santos', 'Juliana Lima', 'Marcos Pereira', 'Fernanda Alves', 'Lucas Rocha', 'Beatriz Gomes'];
        $tiposImovel = ['Apartamento', 'Casa', 'Cobertura', 'Studio', 'Flat'];
        $localidades = ['Boa Viagem', 'Pina', 'Setúbal', 'Piedade', 'Candeias', 'Ponta de Pedras', 'Porto de Galinhas', 'Muro Alto', 'Carneiros'];
        $captadores = ['Carlos Corretor', 'Ana Vendas', 'Roberto Capta', 'Julia Imóveis', 'Marcos Corretor'];
        $pontosFortes = ['Vista mar incrível.', 'Próximo ao shopping.', 'Infraestrutura completa.', 'Área de lazer maravilhosa.'];
        $pontosFracos = ['Precisa de reformas.', 'Vaga apertada.', 'Sem elevador.', 'Condomínio caro.'];

        $inseridos = 0;
        for ($i = 1; $i <= 10; $i++) {
            $nomeProp = $nomesProprietarios[array_rand($nomesProprietarios)];
            
            // CORREÇÃO DO ERRO DE DIGITAÇÃO AQUI (tiposImovel)
            $tipo = $tiposImovel[array_rand($tiposImovel)]; 
            
            $localidade = $localidades[array_rand($localidades)];
            $captador = $captadores[array_rand($captadores)];
            $pontoForte = $pontosFortes[array_rand($pontosFortes)];
            $pontoFraco = $pontosFracos[array_rand($pontosFracos)];
            
            $quartos = rand(1, 4); $metragem = rand(40, 200); $vagas = rand(1, 3);
            $valorDesejado = rand(200000, 1500000); $valorMercado = $valorDesejado + rand(10000, 50000);
            $percentualCaptador = rand(10, 20); $anoConstrucao = rand(1990, 2023);
            
            // Nome agora vai incluir o tipo corretamente
            $nomeEmpreendimento = "IMÓVEL TESTE $i - $tipo em $localidade";

            $fotosOriginais = [];
            if (!empty($imagensCard)) {
                shuffle($imagensCard);
                $qtdFotos = min(count($imagensCard), rand(3, 6));
                for ($j = 0; $j < $qtdFotos; $j++) { $fotosOriginais[] = $imagensCard[$j]; }
            }

            $sql = "INSERT INTO empreendimento (
                Empresa_ID, NomeEmpresa, Usuario_ID, Origem, is_teste, CondicaoImovel, TipoImovelCaptacao, NomeEmpreendimento, 
                Localidade, Quartos, MetragemTotal, VagasGaragem, AnoEntrega, EstadoConservacao, 
                ProprietarioNome, ProprietarioTelefone, ProprietarioEmail, 
                CaptadorNome, CaptadorTelefone, PercentualCaptador, 
                ValorDesejado, ValorMercado, ValorCond, ValorIPTU, 
                PontoForte, PontoFraco, DataCadastro, UsuarioCad
            ) VALUES (
                ?, ?, ?, 'CAPTACAO', 1, 'Usado', ?, ?, 
                ?, ?, ?, ?, ?, 'Bom', 
                ?, '(81) 99999-0000', 'teste@email.com', 
                ?, '(81) 98888-0000', ?, 
                ?, ?, 500.00, 150.00, 
                ?, ?, NOW(), ?
            )";

            try {
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $empresaId, $nomeEmpresa, $usuarioId, 
                    $tipo, $nomeEmpreendimento,
                    $localidade, $quartos, $metragem, $vagas, $anoConstrucao,
                    $nomeProp, $captador, $percentualCaptador,
                    $valorDesejado, $valorMercado,
                    $pontoForte, $pontoFraco,
                    $nomeUsuario
                ]);

                $novoImovelId = $db->lastInsertId();
                
                if (!empty($fotosOriginais)) {
                    $pastaDestino = __DIR__ . '/captacoes/' . $novoImovelId . '/';
                    if (!is_dir($pastaDestino)) { mkdir($pastaDestino, 0777, true); }

                    foreach ($fotosOriginais as $caminhoOrigem) {
                        $nomeArquivo = basename($caminhoOrigem);
                        $novoNome = uniqid() . '_' . $nomeArquivo; 
                        copy($caminhoOrigem, $pastaDestino . $novoNome);
                    }
                }
                $inseridos++;
            } catch (PDOException $e) {
                error_log("Erro ao inserir teste: " . $e->getMessage());
            }
        }
        $mensagem = "$inseridos imóveis de teste foram inseridos e suas fotos copiadas para /captacoes/!";
    } 
    elseif ($acao === 'excluir') {
        try {
            $stmtBusca = $db->prepare("SELECT Empreend_ID FROM empreendimento WHERE is_teste = 1 AND Empresa_ID = ?");
            $stmtBusca->execute([$empresaId]);
            $testesParaExcluir = $stmtBusca->fetchAll(PDO::FETCH_COLUMN);

            $pastasApagadas = 0;
            foreach ($testesParaExcluir as $idTeste) {
                $pasta = __DIR__ . '/captacoes/' . $idTeste . '/';
                if (is_dir($pasta)) {
                    deletarPastaRecursiva($pasta);
                    $pastasApagadas++;
                }
            }

            $stmt = $db->prepare("DELETE FROM empreendimento WHERE is_teste = 1 AND Empresa_ID = ?");
            $stmt->execute([$empresaId]);
            $mensagem = "Registros e $pastasApagadas pastas de fotos foram excluídos!";
            $tipoMsg = "danger";
        } catch (PDOException $e) {
            $mensagem = "Erro ao excluir: " . $e->getMessage();
            $tipoMsg = "danger";
        }
    }
}

$stmtTestes = $db->prepare("SELECT * FROM empreendimento WHERE is_teste = 1 AND Empresa_ID = ? ORDER BY Empreend_ID DESC");
$stmtTestes->execute([$empresaId]);
$testes = $stmtTestes->fetchAll(PDO::FETCH_ASSOC);
$totalTestes = count($testes);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerador de Dados de Teste | Captações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: <?= $themeSafe['primary'] ?>; --shadow-color: <?= $themeSafe['shadow_color'] ?>; --success: #16a34a; --danger: #dc2626; }
        body { background-color: #f8f9fa; font-family: sans-serif; padding: 2.5rem 1rem; }
        
        .card-neo { background: #fff; border: 3px solid var(--shadow-color); border-radius: 16px; box-shadow: 6px 6px 0 0 var(--shadow-color); max-width: 1300px; margin: 0 auto; overflow: hidden;}
        .card-neo .card-header { background: var(--primary); color: #fff; border-bottom: 3px solid var(--shadow-color); padding: 1rem 1.5rem; font-weight: 900; display: flex; justify-content: space-between;}
        .btn-neo { padding: 10px 20px; border: 2.5px solid var(--shadow-color); border-radius: 10px; box-shadow: 3px 3px 0 var(--shadow-color); font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer;}
        .btn-neo.success { background: var(--success); color: #fff; }
        .btn-neo.danger { background: var(--danger); color: #fff; }
        .btn-neo.secondary { background: #fff; color: #1a1a2e; }
        
        .table-neo { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table-neo th, .table-neo td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; font-size: 0.85rem; vertical-align: middle; }
        
        .alert-neo { padding: 1rem; margin-bottom: 1rem; border-radius: 10px; font-weight: bold; text-align: center; max-width: 1300px; margin-left: auto; margin-right: auto;}
        .alert-neo.success { background: #d1fae5; color: #065f46; }
        .alert-neo.danger { background: #fee2e2; color: #991b1b; }
        
        /* Estilo para a galeria de miniaturas e caminhos */
        .photo-item {
            display: inline-block;
            text-align: center;
            margin-right: 8px;
            margin-bottom: 8px;
            vertical-align: top;
        }
        .photo-link {
            text-decoration: none;
            display: block;
        }
        .thumb-img { 
            height: 60px; 
            width: 60px; 
            object-fit: cover; 
            border-radius: 6px; 
            border: 2px solid #ccc; 
            box-shadow: 1px 1px 3px rgba(0,0,0,0.2); 
            display: block;
            margin-bottom: 4px;
            transition: all 0.2s ease-in-out;
        }
        .photo-link:hover .thumb-img {
            border-color: var(--primary);
            transform: scale(1.08);
            box-shadow: 2px 2px 6px rgba(0,0,0,0.3);
        }
        .caminho-arquivo {
            font-size: 0.65rem;
            color: #666;
            background: #f1f1f1;
            padding: 2px 4px;
            border-radius: 4px;
            word-break: break-all;
            max-width: 80px;
            display: block;
            line-height: 1.2;
        }
        .caminho-arquivo i {
            color: var(--primary);
            font-size: 0.7rem;
            margin-right: 2px;
        }
    </style>
</head>
<body>
    <?php if ($mensagem): ?><div class="alert-neo <?= $tipoMsg ?>"><?= $mensagem ?></div><?php endif; ?>
    <div class="card-neo">
        <div class="card-header">
            <span><i class="fas fa-vial"></i> Testes (<?= htmlspecialchars($nomeEmpresa) ?>)</span>
            <a href="listar_captacoes.php" class="btn-neo secondary" style="padding: 6px 12px; font-size: 0.75rem;"><i class="fas fa-eye"></i> Ver na Vitrine</a>
        </div>
        <div class="card-body p-4">
            <div class="row mb-4 text-center">
                <div class="col-md-6 mb-2">
                    <form method="POST"><input type="hidden" name="acao" value="inserir">
                        <button type="submit" class="btn-neo success w-100 py-3"><i class="fas fa-plus-circle"></i> Inserir 10 Testes</button>
                    </form>
                </div>
                <div class="col-md-6 mb-2">
                    <form method="POST" onsubmit="return confirm('Apagar os testes desta empresa e suas pastas de fotos?');"><input type="hidden" name="acao" value="excluir">
                        <button type="submit" class="btn-neo danger w-100 py-3" <?= $totalTestes == 0 ? 'disabled' : '' ?>><i class="fas fa-trash-alt"></i> Excluir Testes</button>
                    </form>
                </div>
            </div>
            <hr>
            <h5 class="fw-bold">Registros no Banco (<?= $totalTestes ?>)</h5>
            <?php if ($totalTestes > 0): ?>
                <div class="table-responsive">
                    <table class="table-neo">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Imóvel</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Fotos e Caminhos</th>
                                <th>Cadastrado Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testes as $t): 
                                // Busca as imagens da pasta física deste imóvel
                                $pastaImovel = __DIR__ . '/captacoes/' . $t['Empreend_ID'] . '/';
                                $arquivos = glob($pastaImovel . '*.{jpg,jpeg,png,webp,JPG,JPEG,PNG}', GLOB_BRACE);
                            ?>
                            <tr>
                                <td>#<?= $t['Empreend_ID'] ?></td>
                                <td><?= htmlspecialchars($t['NomeEmpreendimento']) ?></td>
                                <td><?= htmlspecialchars($t['TipoImovelCaptacao']) ?></td>
                                <td>R$ <?= number_format($t['ValorDesejado'], 2, ',', '.') ?></td>
                                <td>
                                    <?php if (!empty($arquivos)): ?>
                                        <?php 
                                        $count = 0;
                                        $totalFotos = count($arquivos);
                                        foreach ($arquivos as $arq): 
                                            if ($count >= 4) break; 
                                            
                                            $nomeArquivo = basename($arq);
                                            $caminhoRelativo = 'captacoes/' . $t['Empreend_ID'] . '/' . $nomeArquivo;
                                        ?>
                                            <div class="photo-item">
                                                <!-- Link abrindo em nova aba -->
                                                <a href="<?= htmlspecialchars($caminhoRelativo) ?>" target="_blank" class="photo-link" title="Abrir imagem em nova aba">
                                                    <img src="<?= htmlspecialchars($caminhoRelativo) ?>" class="thumb-img">
                                                </a>
                                                <!-- Caminho com ícone de link externo -->
                                                <a href="<?= htmlspecialchars($caminhoRelativo) ?>" target="_blank" style="text-decoration: none;">
                                                    <span class="caminho-arquivo">
                                                        <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($caminhoRelativo) ?>
                                                    </span>
                                                </a>
                                            </div>
                                        <?php 
                                            $count++;
                                        endforeach; 
                                        
                                        if ($totalFotos > 4): 
                                        ?>
                                            <br><span class="badge bg-secondary mt-1">E mais <?= $totalFotos - 4 ?> fotos na pasta...</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Nenhuma foto copiada</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-dark"><?= htmlspecialchars($t['UsuarioCad'] ?? 'Sistema') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">Nenhum teste gerado.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
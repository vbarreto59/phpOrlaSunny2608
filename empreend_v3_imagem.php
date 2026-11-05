<?php
// Iniciar sessão
session_start();

// Inclui a conexão PDO
require_once 'conn.php';




// *** Configurações ***
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    $pastaDestino = "E:/ClientHome/gabnetweb.com.br/httpdocs/SunnyImob/card/";
} else {
    //$pastaDestino = "C:/inetpub/wwwroot/ImobVendas/card/";
    $pastaDestino = "C:/xampp/htdocs/phpSunny2601/card/";
}

// Garantir que a pasta termine com /
$pastaDestino = rtrim($pastaDestino, '/') . '/';

// *** Get Empreend_ID FROM uRL ***
$idEmpreendimento = isset($_GET['Empreend_id']) ? $_GET['Empreend_id'] : '';

// *** Verify if the development exists ***
$nomeEmpreendimento = '';
$empreendimentoValido = false;

if ($idEmpreendimento !== '' && is_numeric($idEmpreendimento)) {
    try {
        $sql = "SELECT NomeEmpreendimento FROM empreendimento WHERE Empreend_ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $idEmpreendimento, PDO::PARAM_INT);
        $stmt->execute();
        
        $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empreendimento) {
            $nomeEmpreendimento = $empreendimento['NomeEmpreendimento'];
            $empreendimentoValido = true;
        } else {
            echo "<script>alert('Empreendimento não encontrado!'); window.location.href='empreend_imagem.php';</script>";
            exit;
        }
    } catch (PDOException $e) {
        die("Erro ao consultar empreendimento: " . $e->getMessage());
    }
}

// Verificar se a extensão GD está disponível
$gd_disponivel = extension_loaded('gd') && function_exists('gd_info');

// Função para otimizar imagem JPEG (deve vir antes de ser usada)
function otimizarImagem($caminhoArquivo, $gd_disponivel) {
    if (!file_exists($caminhoArquivo)) {
        return false;
    }
    
    // Se GD não estiver disponível, apenas copia o arquivo
    if (!$gd_disponivel) {
        // Apenas retorna true para indicar sucesso (sem otimização)
        return true;
    }
    
    try {
        // Carregar a imagem
        $imagem = @imagecreatefromjpeg($caminhoArquivo);
        if (!$imagem) {
            // Tentar como string se falhar
            $data = file_get_contents($caminhoArquivo);
            $imagem = @imagecreatefromstring($data);
            if (!$imagem) {
                // Se não conseguir carregar, apenas retorna true
                return true;
            }
        }
        
        // Redimensionar se for muito grande (máximo 1200x800)
        $larguraOriginal = imagesx($imagem);
        $alturaOriginal = imagesy($imagem);
        
        $larguraMaxima = 1200;
        $alturaMaxima = 800;
        
        if ($larguraOriginal > $larguraMaxima || $alturaOriginal > $alturaMaxima) {
            $ratio = $larguraOriginal / $alturaOriginal;
            
            if ($larguraOriginal > $alturaOriginal) {
                $novaLargura = $larguraMaxima;
                $novaAltura = $larguraMaxima / $ratio;
            } else {
                $novaAltura = $alturaMaxima;
                $novaLargura = $alturaMaxima * $ratio;
            }
            
            // Arredondar para valores inteiros
            $novaLargura = (int)round($novaLargura);
            $novaAltura = (int)round($novaAltura);
            
            $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
            
            // Preencher fundo branco
            $corBranca = imagecolorallocate($novaImagem, 255, 255, 255);
            imagefill($novaImagem, 0, 0, $corBranca);
            
            imagecopyresampled($novaImagem, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $larguraOriginal, $alturaOriginal);
            imagedestroy($imagem);
            $imagem = $novaImagem;
        }
        
        // Salvar imagem otimizada
        $resultado = imagejpeg($imagem, $caminhoArquivo, 85); // 85% de qualidade
        imagedestroy($imagem);
        
        return $resultado;
    } catch (Exception $e) {
        // Em caso de erro, apenas retorna true para continuar o processo
        error_log("Erro ao otimizar imagem: " . $e->getMessage());
        return true;
    }
}

// *** Processamento do Upload ***
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($empreendimentoValido) {
        // Verificar se um arquivo foi enviado
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $arquivo = $_FILES['foto'];
            
            // Verificar se é uma imagem válida
            $tipoArquivo = mime_content_type($arquivo['tmp_name']);
            $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png'];
            
            if (!in_array($tipoArquivo, $tiposPermitidos)) {
                echo "<script>alert('Apenas arquivos JPG/PNG são permitidos!'); window.history.back();</script>";
                exit;
            }
            
            // Verificar tamanho do arquivo (máximo 5MB)
            if ($arquivo['size'] > 5 * 1024 * 1024) {
                echo "<script>alert('Arquivo muito grande! Tamanho máximo: 5MB'); window.history.back();</script>";
                exit;
            }
            
            // Gerar nome do arquivo
            $nomeArquivoOriginal = basename($arquivo['name']);
            $nomeArquivoRenomeado = $idEmpreendimento . '.jpg';
            
            $caminhoOriginal = $pastaDestino . $nomeArquivoOriginal;
            $caminhoRenomeado = $pastaDestino . $nomeArquivoRenomeado;
            
            // Mover arquivo para pasta destino
            if (move_uploaded_file($arquivo['tmp_name'], $caminhoRenomeado)) {
                
                // Se for PNG e GD estiver disponível, converter para JPG
                if ($tipoArquivo === 'image/png' && $gd_disponivel) {
                    try {
                        $imagemPNG = imagecreatefrompng($caminhoRenomeado);
                        if ($imagemPNG) {
                            $largura = imagesx($imagemPNG);
                            $altura = imagesy($imagemPNG);
                            $imagemJPG = imagecreatetruecolor($largura, $altura);
                            
                            // Fundo branco para JPG
                            $corBranca = imagecolorallocate($imagemJPG, 255, 255, 255);
                            imagefill($imagemJPG, 0, 0, $corBranca);
                            
                            // Copiar PNG sobre fundo branco
                            imagecopy($imagemJPG, $imagemPNG, 0, 0, 0, 0, $largura, $altura);
                            imagejpeg($imagemJPG, $caminhoRenomeado, 85);
                            
                            imagedestroy($imagemPNG);
                            imagedestroy($imagemJPG);
                        }
                    } catch (Exception $e) {
                        error_log("Erro ao converter PNG para JPG: " . $e->getMessage());
                        // Se falhar na conversão, tenta renomear o arquivo PNG para JPG
                        rename($caminhoRenomeado, $caminhoRenomeado . '.png');
                        echo "<script>alert('Erro ao processar imagem PNG. A imagem foi salva, mas pode precisar de conversão manual.'); window.close();</script>";
                        exit;
                    }
                }
                
                // Otimizar imagem (se GD estiver disponível)
                otimizarImagem($caminhoRenomeado, $gd_disponivel);
                
                // Excluir o arquivo original, se ele for diferente do arquivo renomeado
                if (strtoupper($nomeArquivoOriginal) !== strtoupper($nomeArquivoRenomeado) && file_exists($caminhoOriginal)) {
                    unlink($caminhoOriginal);
                }
                
                // ------------envio de email --------------
                if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
                    $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
                    $subject = "SUN-IMAGEM-" . strtoupper($_SESSION['Usuario'] ?? 'Sistema') . " - " . 
                               $_SERVER['REMOTE_ADDR'] . " - " . date('Y-m-d') . " - " . date('H:i:s');
                    $message = "Envio de imagem do empreendimento " . $nomeEmpreendimento;
                    $headers = "From: sendmail@gabnetweb.com.br\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    
                    mail($to, $subject, $message, $headers);
                }
                // ----------- fim envio de email'
                
                echo "<script>alert('Foto do empreendimento " . htmlspecialchars($nomeEmpreendimento) . " (ID: " . htmlspecialchars($idEmpreendimento) . ") atualizada com sucesso!'); window.close();</script>";
                exit;
            } else {
                echo "<script>alert('Erro ao salvar o arquivo. Verifique as permissões da pasta.'); window.history.back();</script>";
                exit;
            }
        } else {
            $erro = $_FILES['foto']['error'] ?? 'desconhecido';
            $mensagensErro = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo servidor.',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido pelo formulário.',
                UPLOAD_ERR_PARTIAL => 'Upload parcial do arquivo.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
                UPLOAD_ERR_EXTENSION => 'Upload interrompido por extensão PHP.'
            ];
            
            $mensagemErro = $mensagensErro[$erro] ?? "Erro desconhecido no upload: $erro";
            echo "<script>alert('$mensagemErro'); window.history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('ID do empreendimento inválido!'); window.history.back();</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload de Foto do Empreendimento</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bordo-escuro: #4B0C1D;
            --bordo: #800020;
            --bordo-claro: #A01C3A;
            --dourado: #D4AF37;
            --dourado-claro: #F0E68C;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .btn-custom {
            background-color: #A0522D;
            border-color: #A0522D;
            color: white;
        }
        
        .btn-custom:hover {
            background-color: #8B4513;
            border-color: #8B4513;
            color: white;
        }
        
        .alert-info {
            background-color: rgba(23, 162, 184, 0.2);
            border-color: rgba(23, 162, 184, 0.3);
        }
        
        .form-control-file:focus {
            border-color: var(--dourado);
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
        
        .gd-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <header class="container-fluid py-3 mb-4 border-bottom" style="background-color: #800000; color: white;">
            <div class="container">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-2 mb-md-0 d-flex align-items-center">
                        <img src="img/logosunny.jpg" alt="Logotipo Tocca" style="height: 50px; margin-right: 15px;">
                        <div>
                            <h1 class="mb-0 fs-4">
                                <i class="fas fa-building me-2"></i> Empreendimento Imagem
                            </h1>
                            <p class="mb-0 fs-6">SunnyImob</p>
                        </div>
                    </div>
                    <div>
                        <a href="#" class="btn btn-secondary" onclick="window.close()" style="background-color: #A0522D; border-color: #A0522D; color: white;">
                            <i class="fas fa-times me-1"></i> Fechar
                        </a>
                    </div>
                </div>
            </div>
        </header>
    </div>

    <div class="container mt-4">
        <?php if ($empreendimentoValido): ?>
            <?php if (!$gd_disponivel): ?>
            <div class="gd-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Atenção:</strong> A extensão GD não está disponível no servidor. As imagens serão salvas sem otimização automática. Para melhor funcionamento, habilite a extensão GD no PHP.
            </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                Empreendimento selecionado: <strong><?php echo htmlspecialchars(strtoupper($nomeEmpreendimento)); ?> (ID: <?php echo htmlspecialchars($idEmpreendimento); ?>)</strong>
                <input type="hidden" name="idEmpreendimento" value="<?php echo htmlspecialchars($idEmpreendimento); ?>">
            </div>
            
            <?php 
            // Verificar se já existe imagem
            $caminhoImagemAtual = $pastaDestino . $idEmpreendimento . '.jpg';
            if (file_exists($caminhoImagemAtual)): 
            ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-image me-2"></i>Imagem Atual</h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="card/<?php echo htmlspecialchars($idEmpreendimento); ?>.jpg?t=<?php echo time(); ?>" 
                             alt="<?php echo htmlspecialchars($nomeEmpreendimento); ?>" 
                             class="img-fluid rounded" 
                             style="max-height: 300px; max-width: 100%;">
                        <div class="mt-2">
                            <small class="text-muted">Tamanho: <?php echo round(filesize($caminhoImagemAtual) / 1024, 2); ?> KB</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload de Nova Imagem</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" id="formUpload">
                        <div class="form-group">
                            <label for="foto">Selecione a Foto:</label>
                            <input type="file" class="form-control-file" id="foto" name="foto" accept=".jpg,.jpeg,.png" required>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Formatos aceitos: JPG, PNG. Tamanho máximo: 5MB.
                            </small>
                        </div>
                        
                        <?php if ($gd_disponivel): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> Imagens PNG serão convertidas para JPG. A imagem será automaticamente redimensionada (máx: 1200x800) e otimizada.
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> A extensão GD não está disponível. Recomenda-se enviar apenas arquivos JPG. Imagens PNG podem não funcionar corretamente.
                        </div>
                        <?php endif; ?>
                        
                        <div id="previewContainer" class="text-center mb-3" style="display: none;">
                            <h6><i class="fas fa-eye me-1"></i> Preview:</h6>
                            <img id="previewImagem" src="" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" onclick="window.close()" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" id="btnSubmit">
                                <i class="fas fa-save me-1"></i> Salvar Foto
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ID do empreendimento não fornecido ou inválido na URL.
            </div>
            <a href="empreend_imagem.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Validação do arquivo antes do envio
            $('#formUpload').on('submit', function(e) {
                var arquivo = $('#foto').val();
                var btnSubmit = $('#btnSubmit');
                
                if (!arquivo) {
                    alert('Por favor, selecione um arquivo!');
                    return false;
                }
                
                // Verificar extensão
                var extensao = arquivo.split('.').pop().toLowerCase();
                if (extensao !== 'jpg' && extensao !== 'jpeg' && extensao !== 'png') {
                    alert('Apenas arquivos JPG/PNG são permitidos!');
                    return false;
                }
                
                // Mostrar indicador de carregamento
                btnSubmit.prop('disabled', true);
                btnSubmit.html('<i class="fas fa-spinner fa-spin me-1"></i> Enviando...');
                
                return true;
            });
            
            // Preview da imagem selecionada
            $('#foto').on('change', function(e) {
                var arquivo = this.files[0];
                if (arquivo) {
                    // Verificar tamanho (5MB)
                    if (arquivo.size > 5 * 1024 * 1024) {
                        alert('Arquivo muito grande! Tamanho máximo: 5MB');
                        $(this).val('');
                        return;
                    }
                    
                    // Verificar tipo
                    if (!arquivo.type.match('image/jpeg') && !arquivo.type.match('image/jpg') && !arquivo.type.match('image/png')) {
                        alert('Apenas arquivos JPG/PNG são permitidos!');
                        $(this).val('');
                        return;
                    }
                    
                    // Mostrar preview
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#previewContainer').show();
                        $('#previewImagem').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(arquivo);
                } else {
                    $('#previewContainer').hide();
                }
            });
        });
    </script>
</body>
</html>
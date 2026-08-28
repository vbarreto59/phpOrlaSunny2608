<?php
// pdf_selecionados.php

// Iniciar sessão
session_start();

// Verificar sessão
if (empty($_SESSION['Usuario'])) {
    header('Location: login_p1.php');
    exit();
}

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');

// Verificar se há PDFs selecionados
if (empty($_POST['pdfSelecionado'])) {
    die('<div class="alert alert-danger">Nenhum PDF selecionado.</div>');
}

// Obter PDFs selecionados
$pdfsSelecionados = is_array($_POST['pdfSelecionado']) ? $_POST['pdfSelecionado'] : [$_POST['pdfSelecionado']];
$usuario = htmlspecialchars($_POST['usuario'] ?? $_SESSION['usuario']);

// Caminho da pasta de PDFs
$pastaPDFs = __DIR__ . '/pdfs';

// Verificar se a pasta existe
if (!is_dir($pastaPDFs)) {
    die('<div class="alert alert-danger">Pasta de PDFs não encontrada!</div>');
}

// Verificar cada PDF selecionado
$pdfsValidos = [];
foreach ($pdfsSelecionados as $pdf) {
    $caminhoCompleto = $pastaPDFs . '/' . basename($pdf);
    
    // Verificar se o arquivo existe e é um PDF
    if (file_exists($caminhoCompleto) && strtolower(pathinfo($caminhoCompleto, PATHINFO_EXTENSION)) === 'pdf') {
        $pdfsValidos[] = [
            'nome' => htmlspecialchars($pdf),
            'caminho' => $caminhoCompleto,
            'tamanho' => filesize($caminhoCompleto),
            'url' => 'pdfs/' . urlencode($pdf)
        ];
    }
}

if (empty($pdfsValidos)) {
    die('<div class="alert alert-danger">Nenhum PDF válido encontrado.</div>');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Links dos PDFs Selecionados</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container {
            max-width: 900px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: bold;
        }
        .pdf-item {
            border-bottom: 1px solid #e9ecef;
            padding: 15px;
            transition: background-color 0.2s;
        }
        .pdf-item:hover {
            background-color: #f8f9fa;
        }
        .pdf-link {
            text-decoration: none;
            color: #007bff;
            font-weight: 500;
        }
        .pdf-link:hover {
            text-decoration: underline;
            color: #0056b3;
        }
        .badge {
            font-size: 0.8rem;
        }
        .btn-copiar {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        .btn-copiar:hover {
            background-color: #545b62;
        }
        .btn-download-all {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-download-all:hover {
            background-color: #218838;
        }
        .text-muted {
            font-size: 0.9rem;
        }
        .alert-success {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-link"></i> Links dos PDFs Selecionados
                <span class="badge badge-light float-right"><?php echo count($pdfsValidos); ?> PDF(s)</span>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Usuário:</strong> <?php echo htmlspecialchars($usuario); ?> | 
                    <strong>Data/Hora:</strong> <?php echo date('d/m/Y H:i:s'); ?>
                </div>
                
                <div id="links-list">
                    <?php foreach ($pdfsValidos as $index => $pdf): 
                        $tamanhoFormatado = formatarTamanho($pdf['tamanho']);
                        $idUnico = 'link-' . md5($pdf['nome'] . $index);
                    ?>
                    <div class="pdf-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-pdf text-danger mr-2"></i>
                                <a href="<?php echo htmlspecialchars($pdf['url']); ?>" 
                                   class="pdf-link" 
                                   target="_blank"
                                   id="<?php echo $idUnico; ?>">
                                    <?php echo $pdf['nome']; ?>
                                </a>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-hdd"></i> <?php echo $tamanhoFormatado; ?>
                                </small>
                            </div>
                            <div>
                                <button class="btn btn-copiar btn-sm" 
                                        data-clipboard-text="<?php echo htmlspecialchars($pdf['url']); ?>"
                                        title="Copiar link">
                                    <i class="fas fa-copy"></i> Copiar Link
                                </button>
                                <a href="<?php echo htmlspecialchars($pdf['url']); ?>" 
                                   class="btn btn-primary btn-sm ml-2"
                                   download="<?php echo htmlspecialchars($pdf['nome']); ?>">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-download"></i> Baixar Todos os PDFs</h5>
                            <p class="text-muted mb-0">Clique no botão abaixo para baixar um ZIP com todos os PDFs selecionados.</p>
                        </div>
                        <button id="btnDownloadAll" class="btn btn-download-all">
                            <i class="fas fa-file-archive"></i> Baixar Todos (ZIP)
                        </button>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="selecionar_pdfs.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar para Seleção
                    </a>
                    <button id="btnCopiarTodos" class="btn btn-info ml-2">
                        <i class="fas fa-copy"></i> Copiar Todos os Links
                    </button>
                    <button onclick="window.print()" class="btn btn-outline-primary ml-2">
                        <i class="fas fa-print"></i> Imprimir Lista
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mensagem de sucesso para cópia -->
        <div id="copy-success" class="alert alert-success mt-3" style="display: none; position: fixed; top: 20px; right: 20px; z-index: 1000;">
            <i class="fas fa-check-circle"></i> Link copiado para a área de transferência!
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar Clipboard.js
            var clipboard = new ClipboardJS('.btn-copiar');
            
            // Mostrar mensagem de sucesso ao copiar
            clipboard.on('success', function(e) {
                $('#copy-success').fadeIn().delay(2000).fadeOut();
                e.clearSelection();
            });
            
            clipboard.on('error', function(e) {
                alert('Erro ao copiar. Use Ctrl+C para copiar manualmente.');
            });
            
            // Copiar todos os links
            $('#btnCopiarTodos').click(function() {
                var todosLinks = [];
                $('.pdf-link').each(function() {
                    todosLinks.push($(this).attr('href'));
                });
                
                var textoParaCopiar = todosLinks.join('\n');
                
                // Criar elemento temporário para copiar
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(textoParaCopiar).select();
                
                try {
                    var successful = document.execCommand('copy');
                    if (successful) {
                        $('#copy-success')
                            .html('<i class="fas fa-check-circle"></i> Todos os links copiados para a área de transferência!')
                            .fadeIn()
                            .delay(3000)
                            .fadeOut();
                    }
                } catch (err) {
                    alert('Erro ao copiar. Use Ctrl+C para copiar manualmente.');
                }
                
                $temp.remove();
            });
            
            // Baixar todos os PDFs como ZIP
            $('#btnDownloadAll').click(function() {
                var $btn = $(this);
                var pdfsSelecionados = [];
                
                $('.pdf-link').each(function() {
                    pdfsSelecionados.push($(this).attr('href').replace('pdfs/', ''));
                });
                
                if (pdfsSelecionados.length === 0) {
                    alert('Nenhum PDF para baixar.');
                    return;
                }
                
                // Mostrar loading
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Gerando ZIP...');
                $btn.prop('disabled', true);
                
                // Criar formulário para download do ZIP
                var form = $('<form>', {
                    method: 'post',
                    action: 'gerar_zip.php',
                    style: 'display: none;'
                });
                
                // Adicionar PDFs como inputs hidden
                $.each(pdfsSelecionados, function(index, pdf) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'pdfs[]',
                        value: pdf
                    }).appendTo(form);
                });
                
                $('body').append(form);
                form.submit();
                
                // Restaurar botão após 3 segundos
                setTimeout(function() {
                    $btn.html('<i class="fas fa-file-archive"></i> Baixar Todos (ZIP)');
                    $btn.prop('disabled', false);
                }, 3000);
            });
        });
    </script>
</body>
</html>

<?php
// Função para formatar tamanho de arquivo
function formatarTamanho($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<?php
session_start();
require_once 'conn.php';

// *** Configuração de Pasta Unificada ***
$pastaRelativa = '/Sunny2601/card/';
$pastaDestino = $_SERVER['DOCUMENT_ROOT'] . $pastaRelativa;

if (!file_exists($pastaDestino)) {
    mkdir($pastaDestino, 0777, true);
}

// *** LÓGICA DE SELEÇÃO DE ID (NOVIDADE) ***
$idEmpreendimento = isset($_GET['Empreend_id']) ? (int)$_GET['Empreend_id'] : 0;

try {
    // Se o ID vier vazio (0), buscamos o último registro inserido no banco
    if ($idEmpreendimento <= 0) {
        $sqlUltimo = "SELECT Empreend_ID FROM empreendimento ORDER BY Empreend_ID DESC LIMIT 1";
        $stmtUltimo = $conn->query($sqlUltimo);
        $ultimoId = $stmtUltimo->fetchColumn();
        
        if ($ultimoId) {
            $idEmpreendimento = (int)$ultimoId;
        }
    }

    // Busca Lista de todos os Empreendimentos para a Coluna Esquerda
    $sqlLista = "SELECT Empreend_ID, NomeEmpreendimento, Localidade FROM empreendimento ORDER BY NomeEmpreendimento ASC";
    $stmtLista = $conn->query($sqlLista);
    $listaEmpreendimentos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

    // Busca dados do Empreendimento Selecionado (Atual)
    $nomeEmpreendimento = '';
    $localidade = '';
    $empreendimentoValido = false;

    if ($idEmpreendimento > 0) {
        $sql = "SELECT NomeEmpreendimento, Localidade FROM empreendimento WHERE Empreend_ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $idEmpreendimento, PDO::PARAM_INT);
        $stmt->execute();
        $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empreendimento) {
            $nomeEmpreendimento = $empreendimento['NomeEmpreendimento'];
            $localidade = $empreendimento['Localidade'];
            $empreendimentoValido = true;
        }
    }
} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}

// *** Processamento do Upload ***
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $empreendimentoValido) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $caminhoFinal = $pastaDestino . $idEmpreendimento . '.jpg';
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoFinal)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?Empreend_id=" . $idEmpreendimento . "&sucesso=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciador de Imagens - SunnyImob</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; height: 100vh; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-wrapper { display: flex; height: 100vh; }
        
        /* Barra Lateral */
        .sidebar { width: 380px; background: white; border-right: 1px solid #ddd; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; background: #800000; color: white; }
        .sidebar-search { padding: 15px; border-bottom: 1px solid #eee; background: #fdfdfd; }
        .empreend-list { flex-grow: 1; overflow-y: auto; }
        
        .empreend-item { 
            display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #f1f1f1; 
            text-decoration: none !important; color: #333; transition: 0.2s;
        }
        .empreend-item:hover { background: #f8f9fa; }
        .empreend-item.active { background: #fff5f5; border-left: 5px solid #800000; }
        
        .thumb-list { width: 60px; height: 60px; border-radius: 6px; object-fit: cover; margin-right: 15px; background: #eee; border: 1px solid #ddd; }
        .item-info strong { display: block; font-size: 0.95rem; color: #800000; line-height: 1.2; }
        .item-info small { color: #666; font-size: 0.8rem; }

        /* Conteúdo Direita */
        .content-area { flex-grow: 1; overflow-y: auto; padding: 30px; }
        .card-panel { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; }
        
        #pasteArea {
            border: 3px dashed #A0522D; background: #fdfaf8; padding: 30px;
            text-align: center; cursor: pointer; border-radius: 12px; transition: 0.3s;
        }
        #pasteArea:hover { border-color: #800000; background: #fff; }
        .img-main { max-height: 400px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: 5px solid white; }
    </style>
</head>
<body>

<div class="main-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Empreendimentos</h5>
            <i class="fas fa-building opacity-50"></i>
        </div>
        
        <div class="sidebar-search">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                </div>
                <input type="text" id="buscaInput" class="form-control border-left-0" placeholder="Procurar imóvel...">
            </div>
        </div>

        <div class="empreend-list" id="listaMenu">
            <?php foreach ($listaEmpreendimentos as $emp): 
                $thumbPath = $pastaRelativa . $emp['Empreend_ID'] . ".jpg";
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $thumbPath;
                $thumbUrl = file_exists($fullPath) ? $thumbPath . "?t=" . time() : "https://via.placeholder.com/60x60?text=Sem+Foto";
                $active = ($emp['Empreend_ID'] == $idEmpreendimento) ? 'active' : '';
            ?>
                <a href="?Empreend_id=<?= $emp['Empreend_ID'] ?>" class="empreend-item <?= $active ?>">
                    <img src="<?= $thumbUrl ?>" class="thumb-list">
                    <div class="item-info">
                        <strong><?= htmlspecialchars($emp['NomeEmpreendimento']) ?></strong>
                        <small><i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($localidade_list = $emp['Localidade'] ?: 'Não informada') ?></small>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Workspace -->
    <div class="content-area">
        <?php if ($empreendimentoValido): ?>
            <div class="card-panel p-4 mb-4 border-bottom" style="border-bottom: 5px solid #D4AF37 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="font-weight-bold mb-1 text-uppercase" style="color: #800000;"><?= htmlspecialchars($nomeEmpreendimento) ?></h2>
                        <p class="text-muted mb-0"><i class="fas fa-location-arrow mr-2"></i><?= htmlspecialchars($localidade) ?> | <span class="badge badge-secondary">ID: <?= $idEmpreendimento ?></span></p>
                    </div>
                    <?php if (isset($_GET['sucesso'])): ?>
                        <div class="text-success font-weight-bold animate__animated animate__fadeIn">
                            <i class="fas fa-check-circle mr-1"></i> Salvo com sucesso!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7">
                    <div class="card-panel p-4 text-center mb-4">
                        <h6 class="text-left text-muted mb-4 uppercase small font-weight-bold">Visualização da Capa</h6>
                        <?php 
                        $mainImg = $pastaRelativa . $idEmpreendimento . ".jpg";
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $mainImg)): ?>
                            <img src="<?= $mainImg ?>?t=<?= time() ?>" class="img-fluid img-main">
                        <?php else: ?>
                            <div class="py-5 bg-light rounded border border-dashed">
                                <i class="fas fa-images fa-4x mb-3 text-light"></i><br>
                                <span class="text-muted">Nenhuma imagem definida para este imóvel.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div id="pasteArea" class="mb-4">
                        <i class="fas fa-paste fa-3x mb-3" style="color: #A0522D;"></i>
                        <h5>Clique e cole a imagem (Ctrl + V)</h5>
                        <p class="text-muted small">A imagem colada substituirá a atual imediatamente após salvar.</p>
                    </div>

                    <div class="card-panel p-4">
                        <form method="post" enctype="multipart/form-data" id="formUpload">
                            <div class="form-group">
                                <label class="font-weight-bold">Ou escolha um arquivo:</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="foto" name="foto" accept="image/*">
                                    <label class="custom-file-label" for="foto">Selecionar arquivo...</label>
                                </div>
                            </div>

                            <div id="previewContainer" class="text-center my-4" style="display: none;">
                                <img id="previewImagem" src="" style="max-height: 180px;" class="img-thumbnail border-primary shadow-sm">
                                <div class="text-primary small mt-2 font-weight-bold">Nova imagem pronta!</div>
                            </div>

                            <button type="submit" class="btn btn-lg btn-block btn-success shadow-sm" id="btnSubmit" disabled>
                                <i class="fas fa-save mr-2"></i> SALVAR ALTERAÇÕES
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center mt-5">
                <i class="fas fa-exclamation-circle fa-4x text-muted mb-3"></i>
                <h3>Nenhum empreendimento cadastrado.</h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(document).ready(function() {
    // Filtro de Busca
    $("#buscaInput").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#listaMenu .empreend-item").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    const fotoInput = document.getElementById('foto');

    function handleFile(file) {
        if (!file || !file.type.match('image.*')) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            $('#previewImagem').attr('src', e.target.result);
            $('#previewContainer').fadeIn();
            $('#btnSubmit').prop('disabled', false).removeClass('btn-success').addClass('btn-primary');
            $('.custom-file-label').text(file.name || 'Imagem colada');
        };
        reader.readAsDataURL(file);

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fotoInput.files = dataTransfer.files;
    }

    // Colar Imagem (Ctrl + V)
    window.addEventListener('paste', e => {
        const item = e.clipboardData.items[0];
        if (item && item.type.indexOf('image') !== -1) {
            handleFile(item.getAsFile());
        }
    });

    // Seleção Manual
    $('#foto').on('change', function() {
        if (this.files.length > 0) handleFile(this.files[0]);
    });
});
</script>

</body>
</html>
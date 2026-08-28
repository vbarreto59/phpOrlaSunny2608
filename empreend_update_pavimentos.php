<?php
// atualizar_pavimentos.php

// Iniciar sessão (se necessário para controle de acesso)
session_start();

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');

// Incluir arquivo de conexão
require_once 'conn.php'; // Supondo que você tenha um arquivo conn.php com sua conexão PDO

try {
    // Obter conexão usando sua função do conn.php
    $conn = GetOpenConnectionLog();
    
    // Consulta para obter todos os empreendimentos
    $sql = "SELECT * FROM empreendimento ORDER BY NomeEmpreendimento";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atualizar Pavimentos</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1400px;
        }
        .header-banner {
            background-color: #800000;
            color: white;
            padding: 15px 0;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .logo-container {
            display: flex;
            align-items: center;
        }
        .logo-container img {
            height: 50px;
            margin-right: 15px;
            border-radius: 5px;
        }
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
        }
        .image-modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .image-modal-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 1060;
        }
        .image-modal-close:hover,
        .image-modal-close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
        .enlargeable-image {
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid #dee2e6;
            border-radius: 4px;
        }
        .enlargeable-image:hover {
            transform: scale(1.05);
            border-color: #007bff;
        }
        .btn-action {
            min-width: 100px;
        }
        .form-control-sm {
            width: 80px;
            text-align: center;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .badge-localidade {
            background-color: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .btn-marrom {
            background-color: #A0522D;
            border-color: #A0522D;
            color: white;
        }
        .btn-marrom:hover {
            background-color: #8B4513;
            border-color: #8B4513;
            color: white;
        }
        .dataTables_wrapper {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .text-truncate {
            max-width: 250px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="header-banner">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo-container">
                    <img src="img/logotocca.jpg" alt="Logotipo Tocca">
                    <div>
                        <h1 class="h4 mb-1">
                            <i class="fas fa-building me-2"></i> Atualizar Pavimentos
                        </h1>
                        <p class="mb-0 small">SunnyImob - Sistema de Gestão</p>
                    </div>
                </div>
                <div>
                    <button class="btn btn-marrom" onclick="window.close()">
                        <i class="fas fa-times me-1"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle"></i> 
        <strong>Instruções:</strong> Atualize o número de pavimentos de cada empreendimento. Clique na imagem para ampliar.
    </div>
    
    <table id="tabelaPavimentos" class="table table-bordered table-striped table-hover">
        <thead class="thead-light">
            <tr>
                <th width="60">ID</th>
                <th width="120">Imagem</th>
                <th width="150">Pavimentos Atual</th>
                <th width="120">Novos Pavimentos</th>
                <th width="100">Ação</th>
                <th width="150">Localidade</th>
                <th>Empreendimento</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($empreendimentos as $emp): 
                $id = htmlspecialchars($emp['Empreend_ID']);
                $pavimentos = htmlspecialchars($emp['Pavimentos'] ?? '0');
                $localidade = htmlspecialchars($emp['Localidade'] ?? '');
                $nomeEmpreendimento = htmlspecialchars($emp['NomeEmpreendimento'] ?? '');
                $nomeEmpresa = htmlspecialchars($emp['NomeEmpresa'] ?? '');
                $imagemSrc = "card/{$id}.jpg";
            ?>
            <tr id="linha_<?php echo $id; ?>">
                <td class="text-center">
                    <span class="badge badge-dark"><?php echo $id; ?></span>
                </td>
                <td class="text-center">
                    <img src="<?php echo $imagemSrc; ?>" 
                        alt="Empreendimento <?php echo $id; ?>" 
                        class="enlargeable-image img-fluid"
                        style="max-width: 100px; max-height: 80px; object-fit: cover;"
                        onclick="showImageModal(this.src)"
                        onerror="this.onerror=null;this.src='https://placehold.co/100x80/cccccc/333333?text=Imagem\\nNão\\nDisponível';">
                </td>
                <td class="text-center">
                    <span class="badge <?php echo ($pavimentos > 0) ? 'badge-info' : 'badge-secondary'; ?> p-2" 
                          style="font-size: 1rem;">
                        <?php echo $pavimentos; ?>
                    </span>
                </td>
                <td class="text-center">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text">
                                <i class="fas fa-layer-group"></i>
                            </span>
                        </div>
                        <input type="number" 
                               class="form-control form-control-sm text-center" 
                               id="pavimentos_<?php echo $id; ?>" 
                               value="<?php echo $pavimentos; ?>"
                               min="0"
                               max="100"
                               step="1">
                    </div>
                </td>
                <td class="text-center">
                    <button class="btn btn-primary btn-sm btn-action" 
                            onclick="atualizarPavimentos(<?php echo $id; ?>)">
                        <i class="fas fa-sync-alt"></i> Atualizar
                    </button>
                </td>
                <td>
                    <span class="badge-localidade"><?php echo $localidade; ?></span>
                </td>
                <td>
                    <strong class="text-truncate"><?php echo $nomeEmpreendimento; ?></strong>
                    <br>
                    <small class="text-muted"><?php echo $nomeEmpresa; ?></small>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="alert alert-warning mt-4">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Total de empreendimentos:</strong> <?php echo count($empreendimentos); ?>
    </div>
</div>

<!-- Modal para ampliar a imagem -->
<div id="image-modal" class="image-modal">
    <span class="image-modal-close">&times;</span>
    <img class="image-modal-content" id="modal-image" alt="Imagem ampliada">
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    // Inicializar DataTable com configurações
    var table = $('#tabelaPavimentos').DataTable({
        "language": {
            "sEmptyTable": "Nenhum registro encontrado",
            "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
            "sInfoFiltered": "(Filtrados de _MAX_ registros)",
            "sInfoPostFix": "",
            "sInfoThousands": ".",
            "sLengthMenu": "_MENU_ resultados por página",
            "sLoadingRecords": "Carregando...",
            "sProcessing": "Processando...",
            "sZeroRecords": "Nenhum registro encontrado",
            "sSearch": "Pesquisar:",
            "oPaginate": {
                "sNext": "Próximo",
                "sPrevious": "Anterior",
                "sFirst": "Primeiro",
                "sLast": "Último"
            },
            "oAria": {
                "sSortAscending": ": Ordenar colunas de forma ascendente",
                "sSortDescending": ": Ordenar colunas de forma descendente"
            }
        },
        "pageLength": 50,
        "lengthMenu": [25, 50, 100, 200],
        "order": [[0, "asc"]],
        "responsive": true,
        "autoWidth": false,
        "columnDefs": [
            { "width": "60px", "targets": 0 },
            { "width": "120px", "targets": 1 },
            { "width": "150px", "targets": 2 },
            { "width": "120px", "targets": 3 },
            { "width": "100px", "targets": 4 },
            { "width": "150px", "targets": 5 }
        ],
        "initComplete": function() {
            // Adicionar campo de busca personalizado
            $('.dataTables_filter input').addClass('form-control').attr('placeholder', 'Buscar...');
            $('.dataTables_length select').addClass('form-control');
        }
    });
    
    // Aplicar classe de validação aos campos de entrada
    $('input[type="number"]').on('input', function() {
        var value = parseInt($(this).val());
        if (value < 0) $(this).val(0);
        if (value > 100) $(this).val(100);
    });
});

function atualizarPavimentos(id) {
    var novosPavimentos = document.getElementById("pavimentos_" + id).value;
    var linha = document.getElementById("linha_" + id);
    var btn = event.target;
    
    // Validação básica
    if (novosPavimentos === "" || novosPavimentos < 0) {
        alert("Por favor, insira um valor válido para pavimentos (número positivo).");
        return;
    }
    
    // Mostrar loading no botão
    var originalHTML = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
    btn.disabled = true;
    
    // Adicionar classe de processamento na linha
    linha.classList.add('table-warning');
    
    $.ajax({
        url: "atualizar_pavimentos.php",
        type: "POST",
        data: {
            id: id,
            pavimentos: novosPavimentos
        },
        dataType: "json",
        success: function(response) {
            if(response.success) {
                // Atualizar visualmente o número atual de pavimentos
                linha.querySelector('.badge').textContent = novosPavimentos;
                linha.querySelector('.badge').className = 'badge badge-success p-2';
                
                // Feedback visual
                linha.classList.remove('table-warning');
                linha.classList.add('table-success');
                
                setTimeout(function() {
                    linha.classList.remove('table-success');
                }, 2000);
                
                // Feedback no console
                console.log("Pavimentos atualizados com sucesso para ID " + id + ": " + novosPavimentos);
            } else {
                alert("Erro: " + response.message);
                linha.classList.remove('table-warning');
                linha.classList.add('table-danger');
                
                setTimeout(function() {
                    linha.classList.remove('table-danger');
                }, 3000);
            }
        },
        error: function(xhr, status, error) {
            console.error("Erro na requisição AJAX:", error);
            console.error("Resposta do servidor:", xhr.responseText);
            
            alert("Erro na comunicação com o servidor. Verifique o console para detalhes.");
            
            linha.classList.remove('table-warning');
            linha.classList.add('table-danger');
            
            setTimeout(function() {
                linha.classList.remove('table-danger');
            }, 3000);
        },
        complete: function() {
            // Restaurar o botão
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });
}

// Funções para o modal de imagem
function showImageModal(imageUrl) {
    var modal = document.getElementById("image-modal");
    var modalImg = document.getElementById("modal-image");
    modal.style.display = "flex";
    modalImg.src = imageUrl;
}

function closeImageModal() {
    var modal = document.getElementById("image-modal");
    modal.style.display = "none";
}

// Event listeners para o modal
document.addEventListener('DOMContentLoaded', function() {
    var span = document.getElementsByClassName("image-modal-close")[0];
    var modal = document.getElementById("image-modal");

    span.onclick = function() {
        closeImageModal();
    }

    modal.onclick = function(event) {
        if (event.target === modal) {
            closeImageModal();
        }
    }
    
    // Fechar modal com tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeImageModal();
        }
    });
});

// Função auxiliar para fechar janela
function fecharJanela() {
    if (confirm("Deseja realmente fechar esta janela?")) {
        window.close();
    }
}
</script>
</body>
</html>

<?php
// Fechar conexão
$conn = null;
?>
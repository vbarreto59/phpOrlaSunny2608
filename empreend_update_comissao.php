<?php
session_start();
require_once 'conn.php'; // Arquivo com a conexão PDO

$empreendimentos = []; // Inicializa a variável como array vazio
$error_message = null; // Inicializa a variável de erro

try {
    // Verifica se a conexão existe
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("Conexão com o banco de dados não estabelecida");
    }
    
    // Consulta SQL para obter os empreendimentos
    $sql = "SELECT * FROM empreendimento ORDER BY NomeEmpreendimento";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erro na consulta SQL: " . $e->getMessage();
    error_log("Erro na consulta de empreendimentos: " . $e->getMessage());
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atualizar Comissão de Venda</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos para melhorar a aparência dos campos de número */
        input[type="number"] {
            max-width: 120px;
        }
        .table td, .table th {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="container mt-4">
<div class="container-fluid mt-3">
    <header class="container-fluid py-3 mb-4 border-bottom" style="background-color: #800000; color: white;">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-2 mb-md-0 d-flex align-items-center">
                    <img src="img/logotocca.jpg" alt="Logotipo Tocca" style="height: 50px; margin-right: 15px;">
                    <div>
                        <h1 class="mb-0 fs-4">
                            <i class="fas fa-building me-2"></i> Editar Comissão
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

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (empty($empreendimentos) && !$error_message): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    Nenhum empreendimento encontrado no banco de dados.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

    <div class="card mb-4">
        <div class="card-header" style="background-color: #f8f9fa;">
            <h5 class="mb-0">
                <i class="fas fa-percentage me-2"></i>Empreendimentos Cadastrados
                <span class="badge bg-primary ms-2"><?php echo count($empreendimentos); ?> registros</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <table id="tabelaComissao" class="table table-bordered table-striped mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th width="70">ID</th>
                        <th>Empresa</th>
                        <th>Empreendimento</th>
                        <th width="150">Comissão Venda (%)</th>
                        <th width="120">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($empreendimentos)): ?>
                        <?php foreach ($empreendimentos as $empreendimento): ?>
                        <tr id="linha_<?php echo htmlspecialchars($empreendimento['Empreend_ID']); ?>">
                            <td class="text-center"><?php echo htmlspecialchars($empreendimento['Empreend_ID']); ?></td>
                            <td><?php echo htmlspecialchars($empreendimento['NomeEmpresa']); ?></td>
                            <td><?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?></td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" max="100" 
                                           class="form-control form-control-sm text-center" 
                                           id="comissao_<?php echo htmlspecialchars($empreendimento['Empreend_ID']); ?>" 
                                           value="<?php echo htmlspecialchars(number_format($empreendimento['ComissaoVenda'], 2, '.', '')); ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-primary btn-sm" 
                                        onclick="atualizarComissao(<?php echo htmlspecialchars($empreendimento['Empreend_ID']); ?>)"
                                        title="Salvar alteração">
                                    <i class="fas fa-save me-1"></i> Salvar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="fas fa-database fa-2x text-muted mb-3"></i><br>
                                <span class="text-muted">Nenhum empreendimento encontrado</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Instruções:</strong> Altere o valor da comissão e clique em "Salvar" para atualizar.
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#tabelaComissao').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "lengthMenu": [10, 25, 50, 100, 200],
        "order": [[1, "asc"]], // Ordena por empresa por padrão
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [4] } // Coluna de ação não ordenável
        ]
    });
});

function atualizarComissao(id) {
    var inputElement = document.getElementById("comissao_" + id);
    var novaComissao = inputElement.value.trim();
    
    // Validação básica
    if (novaComissao === "") {
        alert("Por favor, informe um valor para a comissão.");
        inputElement.focus();
        return;
    }
    
    var comissaoNum = parseFloat(novaComissao);
    if (isNaN(comissaoNum) || comissaoNum < 0 || comissaoNum > 100) {
        alert("Por favor, informe um valor válido entre 0 e 100%.");
        inputElement.focus();
        return;
    }
    
    console.log("Atualizando ID: " + id + " para comissão: " + comissaoNum + "%");

    // Desabilitar o botão durante a requisição
    var btn = event.target;
    var originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Salvando...';
    btn.disabled = true;

    $.ajax({
        url: "atualizar_comissao.php", // Arquivo PHP para atualização
        type: "POST",
        data: {
            id: id,
            comissaoVenda: comissaoNum
        },
        success: function(response) {
            if (response.trim() === "success") {
                // Destacar a linha atualizada temporariamente
                var linha = document.getElementById("linha_" + id);
                linha.style.backgroundColor = "#d4edda";
                setTimeout(function() {
                    linha.style.backgroundColor = "";
                }, 2000);
                
                alert("Comissão de venda atualizada com sucesso!");
            } else {
                alert("Erro ao atualizar comissão: " + response);
            }
        },
        error: function(xhr, status, error) {
            alert("Erro na requisição: " + error);
        },
        complete: function() {
            // Restaurar o botão
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}
</script>
</body>
</html>
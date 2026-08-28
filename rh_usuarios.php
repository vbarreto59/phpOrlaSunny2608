<?php
// lista_usuarios_grupo.php
session_start();
require_once 'conn.php';

// Configurar headers para UTF-8
header('Content-Type: text/html; charset=utf-8');

// Verificar conexão
if (!$conn) {
    die("Erro ao conectar ao banco de dados");
}

// Grupo fixo para a consulta
$vGrupo = "TOCCA_ONZE";

// Query para obter todos os usuários com informações de grupo, diretoria e gerência
$vSql = "SELECT 
            u.UserID, u.Nome, u.Usuario, 
            g.ID_Grupo, g.Nome_Grupo, 
            d.DiretoriaID, d.NomeDiretoria, 
            ge.GerenciaID, ge.NomeGerencia 
         FROM 
            (((Usuarios u 
            INNER JOIN usuario_grupo ug ON u.UserID = ug.Userid) 
            INNER JOIN Grupo g ON ug.ID_Grupo = g.ID_Grupo) 
            LEFT JOIN diretorias d ON u.DiretoriaID = d.DiretoriaID) 
            LEFT JOIN gerencias ge ON u.GerenciaID = ge.GerenciaID 
         WHERE g.Nome_Grupo = :grupo 
         ORDER BY d.NomeDiretoria, ge.NomeGerencia, u.Nome";

try {
    // Preparar e executar a consulta
    $stmt = $conn->prepare($vSql);
    $stmt->bindParam(':grupo', $vGrupo);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Sunny - Lista de Usuários</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .table-responsive {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
        }
        .table th {
            white-space: nowrap;
            background-color: #343a40;
            color: white;
        }
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        .header-section h4 {
            margin: 0;
            font-weight: 600;
        }
        .btn-export {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            font-weight: 500;
        }
        .btn-export:hover {
            background: linear-gradient(135deg, #20c997 0%, #1ba87e 100%);
            color: white;
        }
        .badge-grupo {
            background-color: #6c757d;
            color: white;
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .footer-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php 
    // Incluir header se existir
    if (file_exists('gestao_header.inc')) {
        include 'gestao_header.inc';
    } elseif (file_exists('gestao_header.php')) {
        include 'gestao_header.php';
    }
    ?>
    
    <div class="container">
        <div class="header-section text-center">
            <h4><i class="fas fa-users mr-2"></i>Lista Completa de Usuários - Grupo: <?php echo htmlspecialchars($vGrupo); ?></h4>
            <p class="mb-0 mt-2">Total de usuários: <?php echo count($usuarios); ?></p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="rh_list.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Voltar para Gestão
                </a>
            </div>
            <div>

            </div>
        </div>

        <div class="table-responsive">
            <table id="tabelaUsuarios" class="table table-striped table-bordered" style="width:100%">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Grupo</th>
                        <th>Diretoria</th>
                        <th>Gerência</th>
                        <th>Usuário</th>
                        <th>Nome Completo</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): 
                        $grupo = !empty($usuario['Nome_Grupo']) ? htmlspecialchars($usuario['Nome_Grupo']) : 'Sem Grupo';
                        $diretoria = !empty($usuario['NomeDiretoria']) ? htmlspecialchars($usuario['NomeDiretoria']) : 'Sem Diretoria';
                        $gerencia = !empty($usuario['NomeGerencia']) ? htmlspecialchars($usuario['NomeGerencia']) : 'Sem Gerência';
                        $nomeUsuario = !empty($usuario['Usuario']) ? strtoupper(htmlspecialchars($usuario['Usuario'])) : 'N/A';
                        $nomeCompleto = !empty($usuario['Nome']) ? htmlspecialchars($usuario['Nome']) : 'N/A';
                    ?>
                    <tr>
                        <td><strong><?php echo $usuario['UserID']; ?></strong></td>
                        <td>
                            <span class="badge badge-grupo"><?php echo $grupo; ?></span>
                        </td>
                        <td><?php echo $diretoria; ?></td>
                        <td><?php echo $gerencia; ?></td>
                        <td><strong><?php echo $nomeUsuario; ?></strong></td>
                        <td><?php echo $nomeCompleto; ?></td>
                        <td class="text-center">
                            <span class="badge badge-success" title="Usuário ativo no sistema">
                                <i class="fas fa-check-circle"></i> Ativo
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            <i class="fas fa-users-slash fa-2x mb-2"></i><br>
                            Nenhum usuário encontrado no grupo "<?php echo htmlspecialchars($vGrupo); ?>"
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="footer-info">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Informações:</strong></p>
                    <ul>
                        <li>Consulta específica para o grupo: <strong><?php echo htmlspecialchars($vGrupo); ?></strong></li>
                        <li>Data da consulta: <?php echo date('d/m/Y H:i:s'); ?></li>
                        <li>Total de registros: <?php echo count($usuarios); ?></li>
                    </ul>
                </div>
                <div class="col-md-6 text-right">
                    <p><strong>Sistema Sunny Imobiliária</strong></p>
                    <p>Relatório gerado automaticamente</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    
    <!-- Biblioteca para exportação Excel -->
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Inicializar DataTable com opções de exportação
        var table = $('#tabelaUsuarios').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ registros por página",
                "zeroRecords": "Nenhum registro encontrado",
                "info": "Mostrando página _PAGE_ de _PAGES_",
                "infoEmpty": "Nenhum registro disponível",
                "infoFiltered": "(filtrado de _MAX_ registros totais)",
                "search": "Pesquisar:",
                "paginate": {
                    "first": "Primeira",
                    "last": "Última",
                    "next": "Próxima",
                    "previous": "Anterior"
                }
            },
            "pageLength": 100,
            "order": [[1, "asc"], [2, "asc"], [3, "asc"]],
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                    className: 'btn btn-success',
                    title: 'Usuarios_Grupo_<?php echo date('Y-m-d'); ?>',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print mr-1"></i> Imprimir',
                    className: 'btn btn-info',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5]
                    }
                }
            ],
            "initComplete": function() {
                // Adicionar classes aos controles
                $('.dataTables_filter input').addClass('form-control').attr('placeholder', 'Pesquisar...');
                $('.dataTables_length select').addClass('form-control');
                
                // Adicionar tooltips
                $('[title]').tooltip({
                    trigger: 'hover',
                    placement: 'top'
                });
            }
        });
        
        // Adicionar contador de registros filtrados
        $('#tabelaUsuarios').on('draw.dt', function () {
            var info = table.page.info();
            $('.dataTables_info').html(
                'Mostrando ' + (info.start + 1) + ' a ' + (info.end) + ' de ' + info.recordsTotal + ' registros'
            );
        });
    });
    
    // Função para exportar para Excel
    function exportarParaExcel() {
        $('.buttons-excel').click();
    }
    
    // Função para imprimir
    function imprimirRelatorio() {
        $('.buttons-print').click();
    }
    
    // Adicionar evento para fechar com ESC
    $(document).keydown(function(e) {
        if (e.keyCode === 27) { // ESC key
            if (window.opener && !window.opener.closed) {
                window.close();
            }
        }
    });
    </script>
    
    <?php 
    // Incluir footer se existir
    if (file_exists('footer.inc')) {
        include 'footer.inc';
    } elseif (file_exists('footer.php')) {
        include 'footer.php';
    } else {
    ?>
    <footer class="text-center text-muted mt-4 py-3">
        <div class="container">
            <p class="mb-0">
                Sunny System &copy; <?php echo date('Y'); ?> 
                | Grupo: <?php echo htmlspecialchars($vGrupo); ?>
                | <?php echo count($usuarios); ?> usuário(s)
            </p>
        </div>
    </footer>
    <?php } ?>
</body>
</html>
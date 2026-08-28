<?php
// diretoria_list.php - Versão corrigida e completa (query SQL arrumada)
session_start();
require_once 'conn.php';

// Configurar headers para UTF-8
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Diretorias</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/gestao_estilo.css">
    
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .navbar {
            background: linear-gradient(90deg, #0d6efd, #0b5ed7);
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
        .welcome-section {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
            padding: 4rem 0;
            margin-bottom: 2rem;
        }
        .table-responsive { overflow-x: auto; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .text-danger { color: #dc3545 !important; }
        footer {
            background: #343a40;
            color: white;
            padding: 1.5rem 0;
        }
        .social-icons a {
            color: white;
            font-size: 1.3rem;
            transition: 0.3s;
        }
        .social-icons a:hover {
            color: #0d6efd;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="fas fa-sun me-2 text-warning"></i>SunnyImob.
        </a>
        <div class="ms-auto">
            <a href="javascript:window.close()" class="btn btn-outline-danger btn-sm border-0">
                <i class="fas fa-times-circle me-1"></i> Fechar Janela
            </a>
        </div>
    </div>
</nav>

    <section class="welcome-section text-center">
        <div class="container">
            <h1 class="display-4 mb-2">Tocca Onze</h1>
            <p class="lead">Gerencie as operações de gestão e vendas</p>
        </div>
    </section>

    <div class="container py-5">
        <h2 class="text-center mb-4">Lista de Diretorias</h2>
        
        <p class="mb-4 text-center">
            <a class="btn btn-primary btn-lg" href="diretoria_create.php" target="_blank">
                <i class="fas fa-plus me-1"></i> Nova Diretoria
            </a>
        </p>

        <div class="table-responsive">
            <table id="tabelaDiretorias" class="display compact nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Diretoria</th>
                        <th>Diretor</th>
                        <th>Telefone</th>
                        <th>Usuário</th>
                        <th>Nome do Usuário</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                try {
                    // Query corrigida
                    $sql = "
                        SELECT 
                            d.DiretoriaID,
                            d.NomeDiretoria,
                            d.NomeDiretor,
                            d.TelefoneDiretor,
                            d.UserId,
                            d.EstadoDiretoria,
                            u.Nome AS NomeUsuario,
                            u.Usuario
                        FROM diretorias d
                        LEFT JOIN usuarios u ON d.UserId = u.UserId
                        ORDER BY d.NomeDiretoria ASC
                    ";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $diretorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($diretorias)) {
                        echo '<tr><td colspan="8" class="text-center py-4 text-muted">Nenhuma diretoria cadastrada ainda.</td></tr>';
                    }

                    foreach ($diretorias as $diretoria) {
                        $diretor     = !empty($diretoria['NomeDiretor'])     ? htmlspecialchars($diretoria['NomeDiretor'])     : '—';
                        $telefone    = !empty($diretoria['TelefoneDiretor'])  ? htmlspecialchars($diretoria['TelefoneDiretor'])  : '—';
                        $usuario     = !empty($diretoria['Usuario'])          ? strtolower(htmlspecialchars($diretoria['Usuario'])) : '—';
                        $nomeUsuario = !empty($diretoria['NomeUsuario'])      ? htmlspecialchars($diretoria['NomeUsuario'])      : '—';
                        $estado      = !empty($diretoria['EstadoDiretoria'])  ? htmlspecialchars($diretoria['EstadoDiretoria'])  : '—';
                ?>
                    <tr>
                        <td class="text-center"><?= htmlspecialchars($diretoria['DiretoriaID']) ?></td>
                        <td title="<?= htmlspecialchars($diretoria['NomeDiretoria']) ?>">
                            <?= htmlspecialchars($diretoria['NomeDiretoria']) ?>
                        </td>
                        <td title="<?= $diretor ?>"><?= $diretor ?></td>
                        <td><?= $telefone ?></td>
                        <td><?= $usuario ?></td>
                        <td><?= $nomeUsuario ?></td>
                        <td><?= $estado ?></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-sm btn-info" href="diretoria_update.php?id=<?= $diretoria['DiretoriaID'] ?>" target="_blank">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a class="btn btn-sm btn-danger" href="diretoria_delete.php?id=<?= $diretoria['DiretoriaID'] ?>"
                                   onclick="return confirm('Tem certeza que deseja excluir esta diretoria? Isso pode afetar cadastros vinculados.')" target="_blank">
                                    <i class="fas fa-trash"></i> Excluir
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php
                    }
                } catch (PDOException $e) {
                    echo '<tr><td colspan="8" class="text-center text-danger fw-bold py-4">
                            Erro ao carregar diretorias: ' . htmlspecialchars($e->getMessage()) . '
                          </td></tr>';
                    error_log("Erro em diretoria_list.php: " . $e->getMessage());
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="text-center mt-auto py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-sun me-2"></i>SunnyImob</h5>
                    <p>Valter Barreto</p>
                </div>
                <div class="col-md-6">
                    <p>&copy; <?= date('Y') ?> Todos os direitos reservados</p>
                    <div class="social-icons mt-2">
                        <a href="#" class="me-3 text-white"><i class="fab fa-facebook-f fa-lg"></i></a>
                        <a href="#" class="me-3 text-white"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="me-3 text-white"><i class="fab fa-linkedin-in fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tabelaDiretorias').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json"
                },
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": true,
                "responsive": true,
                "pageLength": 25,
                "order": [[0, 'desc']],
                "columnDefs": [
                    { "className": "text-center", "targets": [0, 7] }
                ]
            });
        });
    </script>
</body>
</html>
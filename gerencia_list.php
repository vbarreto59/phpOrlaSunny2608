<?php
// gerencia_list.php
session_start();
require_once 'conn.php';
require_once 'usr_acoes.php';
//require_once 'gestao_header.inc';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SunnyImob | Gestão de Gerências</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --dark-blue: #1e293b;
            --light-bg: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: #334155;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .navbar {
            background: var(--dark-blue);
            padding: 0.8rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: #fff !important;
        }

        .welcome-section {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 50px 0;
            margin-bottom: -40px;
        }

        .main-card {
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 50px;
        }

        .table thead th {
            background-color: #f1f5f9;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 15px;
            border: none;
        }

        .badge-id {
            background: #e2e8f0;
            color: #475569;
            font-weight: 600;
        }

        .text-data {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 400;
        }

        .btn-close-window {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 6px;
        }
        .btn-close-window:hover {
            color: #ff4d4d;
            border-color: #ff4d4d;
            background: rgba(255, 77, 77, 0.1);
        }

        footer {
            background: #fff;
            padding: 30px 0;
            border-top: 1px solid #e2e8f0;
            margin-top: auto;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="#">
                <i class="fas fa-sun me-2 text-warning"></i>SunnyImob.
            </a>
            <a href="javascript:window.close()" class="btn-close-window">
                <i class="fas fa-times-circle me-1"></i> Fechar Janela
            </a>
        </div>
    </nav>

    <section class="welcome-section text-center">
        <div class="container">
            <h1 class="h2 fw-bold mb-1">Tocca Onze</h1>
            <p class="opacity-75 mb-0">Gestão centralizada de gerências</p>
        </div>
    </section>

    <div class="container">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="m-0 fw-bold text-dark">Lista de Gerências</h4>
                <a class="btn btn-primary px-4 shadow-sm" href="gerencia_create.php">
                    <i class="fas fa-plus me-2"></i>Nova Gerência
                </a>
            </div>
            
            <div class="table-responsive">
                <table id="tabelaGerencias" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Diretoria</th>
                            <th>Gerência</th>
                            <th>Usuário Responsável</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    try {
                        global $conn;
                        $sql = "SELECT G.*, D.NomeDiretoria, U.Nome as NomeUsuario 
                                FROM gerencias G 
                                LEFT JOIN diretorias D ON G.DiretoriaID = D.DiretoriaID
                                LEFT JOIN usuarios U ON G.UserId = U.UserId
                                WHERE G.DataEncerramento IS NULL"; 
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $gerencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($gerencias as $gerencia) {
                            $nomeDiretoria = !empty($gerencia['NomeDiretoria']) ? htmlspecialchars($gerencia['NomeDiretoria']) : '<span class="text-muted">Sem Diretoria</span>';
                            $nomeGerencia = !empty($gerencia['NomeGerencia']) ? htmlspecialchars($gerencia['NomeGerencia']) : 'N/A';
                            
                            // Nome em MAIÚSCULAS
                            $nomeUsuario = !empty($gerencia['NomeUsuario']) ? mb_strtoupper(htmlspecialchars($gerencia['NomeUsuario']), 'UTF-8') : 'N/A';
                            
                            // Lógica para data de criação vazia
                            if (empty($gerencia['DataCriacao']) || $gerencia['DataCriacao'] == '0000-00-00 00:00:00') {
                                $dataCriacao = '25/11/2025';
                            } else {
                                $dataCriacao = date('d/m/Y', strtotime($gerencia['DataCriacao']));
                            }
                    ?>
                        <tr>
                            <td><span class="badge badge-id"><?php echo $gerencia['GerenciaID']; ?></span></td>
                            <td class="fw-medium"><?php echo $nomeDiretoria; ?></td>
                            <td>
                                <?php echo $nomeGerencia; ?> 
                                <span class="text-data">(<?php echo $dataCriacao; ?>)</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-circle text-secondary me-2"></i>
                                    <?php echo $nomeUsuario; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a class="btn btn-outline-info btn-sm px-3" href="gerencia_update.php?id=<?php echo $gerencia['GerenciaID']; ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a class="btn btn-outline-warning btn-sm px-3" href="gerencia_transfer.php?id=<?php echo $gerencia['GerenciaID']; ?>" title="Transferir de Diretoria">
                                        <i class="fas fa-random"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php
                        }
                    } catch(PDOException $e) {
                        echo '<tr><td colspan="5" class="text-center text-danger">Erro ao carregar dados.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer>
        <div class="container text-center">
            <p class="text-muted mb-0 small">&copy; <?php echo date('Y'); ?> SunnyImob - SGVendas</p>
            <p class="text-muted small">Valter Barreto | CRECI-PE 22003</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tabelaGerencias').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
                },
                "pageLength": 10,
                "order": [[0, "desc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [4] }
                ]
            });
        });
    </script>
</body>
</html>
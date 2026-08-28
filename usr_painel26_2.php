<?php
session_start();

// Adicionando as variáveis de sessão (simulação - na prática essas viriam do processo de login)
// Se essas variáveis já foram definidas no login, elas serão preservadas
// Se não, podemos definir valores padrão ou redirecionar para login

// Verifica se o usuário está logado (se existir login no seu sistema)
if (!isset($_SESSION['Usuario'])) {
    header("Location: login_p1.php"); // ajuste para sua página de login
    exit();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Acesso | Sistema Administrativo</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-color);
            padding-bottom: 30px;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        
        .user-info {
            background-color: white;
            border-radius: 10px;
            padding: 10px 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .main-container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .welcome-card .card-body {
            padding: 2rem;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: none;
            transition: var(--transition);
            height: 100%;
            margin-bottom: 20px;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
        }
        
        .card-title {
            color: white;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .menu-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .menu-list li {
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .menu-list li:last-child {
            margin-bottom: 0;
        }
        
        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background-color: rgba(67, 97, 238, 0.05);
            border-radius: 8px;
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .menu-link:hover {
            background-color: rgba(67, 97, 238, 0.15);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
            text-decoration: none;
            transform: translateX(5px);
        }
        
        .menu-link i {
            margin-right: 12px;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        
        .menu-text {
            font-weight: 500;
            flex-grow: 1;
        }
        
        .badge-new {
            background-color: var(--warning-color);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 8px;
        }
        
        .category-title {
            color: var(--secondary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(67, 97, 238, 0.1);
        }
        
        .logout-btn {
            background-color: var(--warning-color);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .logout-btn:hover {
            background-color: #d61a6f;
            transform: scale(1.05);
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin-top: 15px;
            }
            
            .welcome-card .card-body {
                padding: 1.5rem;
            }
            
            .card-body {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>


    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock me-2"></i>
                Painel Administrativo
            </a>
            <div class="d-flex align-items-center">
                <div class="user-info me-3">
                    <i class="bi bi-person-circle me-2"></i>
                    <span><?php echo ($_SESSION['Usuario']); ?></span>
                    <?php if(isset($_SESSION['Permissao'])): ?>
                        <span class="badge bg-secondary ms-2"><?php echo ($_SESSION['Permissao']); ?></span>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="bi bi-box-arrow-right me-1"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container main-container">
        <!-- Welcome Card -->
        <div class="card welcome-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="card-title mb-3">Painel de Controle - v3 - 10/02/2026</h2>
                        <p class="card-text mb-0">Gerencie usuários, logs, históricos e configurações do sistema de forma centralizada.</p>
                        <?php if(isset($_SESSION['UserId'])): ?>

                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="bi bi-speedometer2" style="font-size: 4rem; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Menu Principal -->
            <div class="col-lg-8">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-menu-button-wide me-2"></i>Menu de Navegação</h5>
                    </div>
                    <div class="card-body">
                        <!-- Gestão de Usuários -->
                        <h6 class="category-title">Gestão de Usuários</h6>
                        <ul class="menu-list">

                            <li>
                                <a href="usr_admin_listar3b.php" target="_blank" class="menu-link">
                                    <i class="bi bi-bar-chart"></i>
                                    <span class="menu-text">Admin - Listagem Usuários Ativos/Licenciados</span>
                                </a>
                            </li> 

                            <li>
                                <a href="usr_grupo_contagem.php" target="_blank" class="menu-link">
                                    <i class="bi bi-bar-chart"></i>
                                    <span class="menu-text">Admin - Contagem de Grupos</span>
                                </a>
                            </li>
                           
                        </ul>
                        

                        
                        <!-- Logs e Histórico -->
                        <h6 class="category-title mt-4">Logs e Histórico</h6>
                        <ul class="menu-list">
                            <li>
                                <a href="historico_list.php" target="_blank" class="menu-link">
                                    <i class="bi bi-clock-history"></i>
                                    <span class="menu-text">Lista de Históricos</span>
                                </a>
                            </li>
                            <li>
                                <a href="log_list.php" target="_blank" class="menu-link">
                                    <i class="bi bi-journal-text"></i>
                                    <span class="menu-text">Lista de Logs</span>
                                </a>
                            </li>
                            <li>
                                <a href="lg_conta_paginas.php" target="_blank" class="menu-link">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <span class="menu-text">Nao existe. Contagem de Páginas</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Informações e Estatísticas -->
            <div class="col-lg-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                <i class="bi bi-shield-check text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Acesso Seguro</h6>
                                <p class="text-muted small mb-0">Sessão autenticada e protegida</p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center mb-4">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Sistema Online</h6>
                                <p class="text-muted small mb-0">Todos os serviços operacionais</p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center mb-4">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                                <i class="bi bi-clock text-warning" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Último Acesso</h6>
                                <p class="text-muted small mb-0"><?php echo date('d/m/Y H:i'); ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3">Acesso Rápido</h6>
                        <div class="d-grid gap-2">
                            <a href="usr_listar.php" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-people me-1"></i> Ver Todos Usuários
                            </a>
                            <a href="log_list.php" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-journal-text me-1"></i> Ver Logs Recentes
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card dashboard-card mt-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-question-circle me-2"></i>Ajuda Rápida</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">
                            <i class="bi bi-info-circle me-1"></i> Para acessar qualquer funcionalidade, clique no item desejado no menu.
                        </p>
                        <p class="small text-muted">
                            <i class="bi bi-box-arrow-up-right me-1"></i> As páginas serão abertas em uma nova aba.
                        </p>
                        <p class="small text-muted mb-0">
                            <i class="bi bi-shield me-1"></i> Certifique-se de fazer logout ao terminar suas atividades.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p class="mb-0">Sistema Administrativo &copy; <?php echo date('Y'); ?> - Todos os direitos reservados</p>
            <p class="small text-muted">v1.0.0 | Última atualização: <?php echo date('d/m/Y'); ?></p>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Adiciona efeito de loading ao clicar nos links
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('.menu-link');
            links.forEach(link => {
                link.addEventListener('click', function() {
                    const text = this.querySelector('.menu-text').textContent;
                    console.log(`Abrindo: ${text}`);
                    // Aqui você pode adicionar um efeito de loading se necessário
                });
            });
            
            // Adiciona um efeito sutil aos cards quando a página carrega
            setTimeout(() => {
                document.querySelectorAll('.dashboard-card').forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100 * index);
                });
            }, 100);
        });
    </script>
</body>
</html>
<!-- ok -->
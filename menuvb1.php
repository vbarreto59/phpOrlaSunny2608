<?php
// menu_principal.php

// Iniciar sessão
session_start();

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');

// Incluir arquivos de configuração
require_once 'conn.php'; // Seu arquivo de conexão PDO
// require_once 'log_nanvegacao.php'; // Arquivo de log de navegação (se necessário)
// require_once 'atualizar.php'; // Arquivo de atualização (se necessário)
// require_once 'usr_acoes.inc'; // Ações de usuário (se necessário)
// require_once 'empreend_faixa_preco1.php'; // Faixa de preço (se necessário)
require_once 'manutencao_config.php'; // Configurações de manutenção

// Verificação se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header('Location: login_p1.php');
    exit();
}


// Obter nome do usuário logado
$usuarioLogado = $_SESSION['Usuario'] ?? '';
$userId = $_SESSION['UserId'] ?? '';
$userFuncao = $_SESSION['Funcao'] ?? '';

// Verificar se está em manutenção (exceto para admin)
//$mostrarAlertaManutencao = estaEmManutencao();
$mostrarAlertaManutencao = false;

// Função para obter função do usuário (simulada)
function getUserFuncao() {
    return $_SESSION['Funcao'] ?? 'Usuário';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="600">
    <title>Menu Principal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-image: url('praia.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.95);
            max-width: 900px;
            margin: 30px auto;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
        }

        .alert-manutencao {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            border-radius: 0;
            text-align: center;
            padding: 15px;
            font-weight: bold;
            animation: pulse 2s infinite;
            font-size: 1.1rem;
        }

        @keyframes pulse {
            0% { background-color: #dc3545; }
            50% { background-color: #c82333; }
            100% { background-color: #dc3545; }
        }

        header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 25px;
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .user-info {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 14px;
            color: white;
            background-color: rgba(0, 0, 0, 0.3);
            padding: 8px 15px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .user-info a {
            color: white;
            margin-left: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .user-info a:hover {
            color: #ffd700;
            transform: scale(1.1);
        }

        .logo-container {
            text-align: center;
            margin: 30px 0;
        }

        .logo-container img {
            max-width: 360px;
            height: auto;
            border-radius: 10px;
            border: 8px solid white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s;
        }

        .logo-container img:hover {
            transform: scale(1.02);
        }

        footer {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin-top: 30px;
            border-radius: 10px;
            font-size: 14px;
            color: #2c3e50;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .btn-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .btn-grid {
                grid-template-columns: 1fr;
            }
        }

        .custom-btn {
            width: 100%;
            color: white;
            border: none;
            padding: 12px 8px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 65px;
            font-size: 13px;
            text-decoration: none !important;
            position: relative;
            overflow: hidden;
        }

        .custom-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .custom-btn:hover:before {
            left: 100%;
        }

        .custom-btn:hover {
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
            color: white;
        }

        .custom-btn i {
            margin-bottom: 5px;
            font-size: 18px;
        }

        .group-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 30px 0 18px;
            display: block;
            text-align: center;
            position: relative;
            padding-bottom: 8px;
        }

        .group-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
        }

        /* Cores por grupo de assunto */
        .group-leads { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); }
        .group-empresas { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); }
        .group-fluxo { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .group-sistema { background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); }
        .group-imoveis { background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%); color: #2c3e50; }
        .group-usuarios { background: linear-gradient(135deg, #8e44ad 0%, #732d91 100%); }
        .group-outros { background: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%); }
        .group-site { background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%); }

        .btn-more {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            margin: 10px;
        }

        .btn-more:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }

        .badge-user {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
            font-size: 0.8rem;
        }
    </style>
    <script type="text/javascript">
        // Auto refresh após 5 minutos
        setTimeout(function() {
            window.location.reload(true);
        }, 300000);
        
        // Verificar inatividade
        let timeout;
        function resetTimer() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                alert('Sessão expirada por inatividade. Você será redirecionado para o login.');
                window.location.href = 'logout.php';
            }, 1200000); // 20 minutos
        }
        
        document.addEventListener('mousemove', resetTimer);
        document.addEventListener('keypress', resetTimer);
        resetTimer();
    </script>
</head>
<body>
    <?php if ($mostrarAlertaManutencao): ?>
    <div class="alert alert-danger alert-manutencao">
        <i class="fas fa-exclamation-triangle"></i> ATENÇÃO: SISTEMA EM MANUTENÇÃO - Algumas funcionalidades podem estar indisponíveis
    </div>
    <?php endif; ?>

    <div class="container">
        <header>
            <div class="user-info">
                <span>Usuário: <?php echo htmlspecialchars($userId); ?> - <?php echo htmlspecialchars(strtoupper($usuarioLogado)); ?></span>
                <span class="badge-user"><?php echo htmlspecialchars(getUserFuncao()); ?></span>
                <a href="usuario_trocar_senha.php" title="Trocar Senha">
                    <i class="bi bi-key-fill"></i>
                </a>
                <a href="javascript:void(0)" onclick="logout()" title="Sair">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
            <h2>SunnyImob - v1.5</h2>
            <h4>Gestão Imobiliária Inteligente</h4>
            <p class="mt-2 mb-0"><small>Último acesso: <?php echo date('d/m/Y H:i:s'); ?></small></p>
        </header>

        <?php if (strtoupper($usuarioLogado) === 'BARRETO'): ?>
            <!-- Grupo: Gestão de Leads -->
            <span class="group-title">Gestão de Leads</span>
            <div class="btn-grid">
                <a href="http://www.gabnetweb.com.br/ImobVendas/leads_list3.php" class="custom-btn group-leads">
                    <i class="fas fa-users"></i> Leads
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/lead_paraLigar.php" target="_blank" class="custom-btn group-leads">
                    <i class="fas fa-phone"></i> L-Prio
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/kanban1.php" class="custom-btn group-leads">
                    <i class="fas fa-columns"></i> L-Kanban
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/kanban_atuposicao.php" class="custom-btn group-leads">
                    <i class="fas fa-columns"></i> L-Kanban-Pos
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/leads_list3b.php" class="custom-btn group-leads" target="_blank">
                    <i class="fas fa-list"></i> L-Todos
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/lead_ultimo_hist.php" class="custom-btn group-leads">
                    <i class="fas fa-history"></i> L-Hist
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/leads_list4.php" class="custom-btn group-leads">
                    <i class="fas fa-trash-alt"></i> L-Descart
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/lead_estatistica.php" class="custom-btn group-leads">
                    <i class="fas fa-chart-bar"></i> L-Estat
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/lead_importa_email2.php" class="custom-btn group-leads">
                    <i class="fas fa-file-import"></i> L-Importar
                </a>
            </div>

            <!-- Grupo: Cards, Fluxo e Valores -->
            <span class="group-title">Cards, Fluxo e Valores</span>
            <div class="btn-grid">
               <a href="gestao_empreend_cards_fluxo1.php" target="_blank" class="custom-btn group-fluxo">
                    <i class="fas fa-filter"></i> CARDS, Fluxo e Mapa
                </a>          
               <a href="gerar_4cards.php" target="_blank" class="custom-btn group-fluxo">
                    <i class="fas fa-filter"></i> 4 Cards
                </a>                           
               <a href="fluxo_padrao_list.php" target="_blank" class="custom-btn group-fluxo">
                    <i class="fas fa-filter"></i> Fluxo List
                </a>   
               <a href="../vbimoveis/index.php" target="_blank" class="custom-btn group-fluxo">
                    <i class="fas fa-filter"></i> Captação
                </a>  
            </div>

            <!-- Grupo: Site -->
            <span class="group-title">Site</span>
            <div class="btn-grid">
                <a href="gerador_site1.php" target="_blank" class="custom-btn group-site">
                    <i class="fas fa-filter"></i> Gerar _All
                </a>   
                <a href="gerador_site_selected2.php" target="_blank" class="custom-btn group-site">
                    <i class="fas fa-filter"></i> Gerar _Selected
                </a>                               
                <a href="https://imoblitoral.infy.uk/_all" class="custom-btn group-site" target="_blank">
                    <i class="fas fa-globe"></i> Site VB Infinity All
                </a>    
                <a href="https://imoblitoral.infy.uk/_selected" class="custom-btn group-site" target="_blank">
                    <i class="fas fa-globe"></i> Site VB Infinity Selected
                </a>                 
                <a href="html/index.html" class="custom-btn group-site" target="_blank">
                    <i class="fas fa-globe"></i> Site VB Localhost(html)
                </a>    
                <a href="https://dash.infinityfree.com/accounts" class="custom-btn group-site" target="_blank">
                    <i class="fas fa-globe"></i> Infinity CPanel
                </a>  
            </div>               

            <!-- Grupo: Gestão de Empresas e Empreendimentos -->
            <span class="group-title">Gestão de Empresas e Empreendimentos</span>
            <div class="btn-grid">
                <a href="empresa_estrela_list.php" class="custom-btn group-empresas" target="_blank">
                    <i class="fas fa-star"></i> E-Avaliação
                </a>
                <a href="gestao_empresa_list.php" class="custom-btn group-empresas">
                    <i class="fas fa-building"></i> Empresas
                </a>
                <a href="empreendimento_list.php" class="custom-btn group-empresas">
                    <i class="fas fa-home"></i> Empreendimentos
                </a>
                <a href="empreend_update_tipologia.php" target="_blank" class="custom-btn group-empresas">
                    <i class="fas fa-link"></i> E-Tipologia
                </a>
                <a href="empreend_update_localizacao.php" target="_blank" class="custom-btn group-empresas">
                    <i class="fas fa-link"></i> E-Localização
                </a>
                <a href="empreend_update_comissao.php" target="_blank" class="custom-btn group-empresas">
                    <i class="fas fa-filter"></i> E-% Comissao
                </a>                
                <a href="empreend_update_pavimentos.php" target="_blank" class="custom-btn group-empresas">
                    <i class="fas fa-cogs"></i> Pavimentos
                </a>
            </div>

            <!-- Grupo: Gestão de Usuários -->
            <span class="group-title">Gestão de Usuários</span>
            <div class="btn-grid">
                <a href="usr_listar.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH Cad. USR
                </a>
                <a href="rh_list.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH Listagem 1
                </a>
                <a href="rh_usuarios.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH Listagem 2
                </a>
                <a href="usr_lista_email1.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH USR no Pwr
                </a>
                <a href="usr_lista_email_pwr1.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH USR com Pwr
                </a>
                <a href="mapaIp3.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH Map
                </a>
                <a href="diretoria_list.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH Diretorias
                </a>
                <a href="gerencia_list.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> RH Gerências
                </a>
                <a href="SunnyEmpresa_crud.php" class="custom-btn group-usuarios" target="_blank">
                    <i class="fas fa-file-alt"></i> Sunny Empresas Clientes
                </a>
            </div>

            <!-- Grupo: Gestão do Sistema -->
            <span class="group-title">Gestão do Sistema</span>
            <div class="btn-grid">
                <a href="historico_list.php" class="custom-btn group-sistema" target="_blank">
                    <i class="fas fa-clipboard-list"></i> Log Acessos
                </a>
                <a href="log_list.php" class="custom-btn group-sistema" target="_blank">
                    <i class="fas fa-clipboard-list"></i> Log Páginas
                </a>
                <a href="log_conta_paginas.php" class="custom-btn group-sistema" target="_blank">
                    <i class="fas fa-clipboard-list"></i> Log Páginas 2
                </a>
                <a href="manutmenu.php" class="custom-btn group-sistema" target="_blank">
                    <i class="fas fa-tools"></i> Manutenção
                </a>
                <a href="empreend_atualizacao_usuarios.php" class="custom-btn group-sistema">
                    <i class="fas fa-sync-alt"></i> S-Atualiz
                </a>
                <a href="https://drive.google.com/drive/folders/1640xHxrVoMHNulSiK1xfylNpGjnAV6U7" target="_blank" class="custom-btn group-sistema">
                    <i class="fas fa-link"></i> Link Prime
                </a>
                <a href="log_atualizar_nome_arquivo.php" class="custom-btn group-sistema" target="_blank">
                    <i class="fas fa-cogs"></i> Atualiza Log
                </a>
            </div>

            <!-- Grupo: Imóveis e Ferramentas -->
            <span class="group-title">Imóveis e Ferramentas</span>
            <div class="btn-grid">
                <a href="empresa_list4.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-building"></i> Imóveis
                </a>
                <a href="empresa_list_valores.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-dollar-sign"></i> Lista Valores
                </a>
                <a href="empresa_list_distancia_mar.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-umbrella-beach"></i> Pé na Areia
                </a>
                <a href="empresa_list_distancia_mar2.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-umbrella-beach"></i> Pé na Areia 2
                </a>
                <a href="empreend_entrega2.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-calendar-check"></i> Lista Entrega
                </a>
                <a href="filtro1.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-filter"></i> Filtro
                </a>
                <a href="calcInterativa5.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-calculator"></i> Calculadora %/R$
                </a>
                <a href="calcRandon3.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-robot"></i> Calculadora Inteligente
                </a>
                <a href="docs_links.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-file-archive"></i> Documentos
                </a>
                <a href="empreend_verificacao2.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-file-archive"></i> Dados Vazios
                </a>
                <a href="empreend_filtro_local.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-file-archive"></i> Cards
                </a>
                <a href="empreend_mapa_interativo2.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-map"></i> Mapa
                </a>
                <a href="empreend_mapa_preco_v2.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-map"></i> Mapa Filtro
                </a>
                <a href="empreend_filtro_local.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-filter"></i> V-Filtro Local
                </a>
                <a href="empreend_selecao_imoveis.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-filter"></i> V-Seleciona Imóveis PDF
                </a>
            </div>

            <!-- Grupo: Outros -->
            <span class="group-title">Outros</span>
            <div class="btn-grid">
                <a href="listar_simulacao.php" class="custom-btn group-outros" target="_blank">
                    <i class="fas fa-cogs"></i> M2 Completa
                </a>
                <a href="listar_simulacao_simples.php" class="custom-btn group-outros" target="_blank">
                    <i class="fas fa-cogs"></i> M2 Simples
                </a>
                <a href="empreend2map3a.php" class="custom-btn group-outros" target="_blank">
                    <i class="fas fa-cogs"></i> Selec. Mapa
                </a>
                <a href="backupEmail.php" class="custom-btn group-outros" target="_blank">
                    <i class="fas fa-cogs"></i> Backup
                </a>
                <a href="pdf_listar.php" class="custom-btn group-outros" target="_blank">
                    <i class="fas fa-cogs"></i> PDF
                </a>
                <a href="../iTeste/gestao_login.php" class="custom-btn group-outros" target="_blank">
                    <i class="fas fa-cogs"></i> Vendas
                </a>
                <a href="http://www.gabnetweb.com.br/ImobVendas/menu.php" class="custom-btn group-outros" target="_blank">
                    <i class="fas fa-cogs"></i> Testes
                </a>
            </div>
        <?php else: ?>
            <!-- Menu para usuários não-BARRETO -->
            <div class="alert alert-info text-center">
                <h4>Bem-vindo, <?php echo htmlspecialchars($usuarioLogado); ?>!</h4>
                <p>Seu acesso é limitado. Entre em contato com o administrador para mais funcionalidades.</p>
            </div>
            
            <div class="btn-grid">
                <a href="empreendimento_list.php" class="custom-btn group-empresas">
                    <i class="fas fa-home"></i> Empreendimentos
                </a>
                <a href="filtro1.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-filter"></i> Filtro
                </a>
                <a href="calcInterativa5.php" class="custom-btn group-imoveis" target="_blank">
                    <i class="fas fa-calculator"></i> Calculadora
                </a>
                <a href="usuario_trocar_senha.php" class="custom-btn group-usuarios">
                    <i class="fas fa-key"></i> Trocar Senha
                </a>
            </div>
        <?php endif; ?>

        <div class="logo-container">
            <img src="img/logoSunny.jpg" alt="Logo do Sistema SunnyImob" width="405" height="126">
        </div>

        <hr class="my-4">

        <footer>
            <p><strong>Sistema Sunnyimob - Valter Barreto - <?php echo date('F/Y'); ?></strong></p>
            <p>Todos os Direitos Reservados | Versão 1.5</p>
            <p class="small mt-2">
                <i class="fas fa-server"></i> Servidor: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?> | 
                <i class="fas fa-code"></i> PHP: <?php echo PHP_VERSION; ?>
            </p>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function logout() {
            if (confirm('Deseja realmente sair do sistema?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Adicionar efeito de digitação no título
        document.addEventListener('DOMContentLoaded', function() {
            const titles = document.querySelectorAll('h2, h4');
            titles.forEach(title => {
                const text = title.textContent;
                title.textContent = '';
                let i = 0;
                function typeWriter() {
                    if (i < text.length) {
                        title.textContent += text.charAt(i);
                        i++;
                        setTimeout(typeWriter, 50);
                    }
                }
                typeWriter();
            });
        });
    </script>
</body>
</html>
<?php
// ManutencaoOn.php

// Iniciar sessão
session_start();

// Incluir arquivo de configuração
require_once 'manutencao_config.php';

// Verificar se é admin
if (!eAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo "Acesso negado";
    exit();
}

// Criar arquivo de manutenção
$arquivoPath = dirname(__FILE__) . '/manut.txt';
$conteudo = "Manutenção ativada em " . date('d/m/Y H:i:s');

if (file_put_contents($arquivoPath, $conteudo) !== false) {
    // Redirecionar conforme o usuário
    $usuario = isset($_SESSION['usuario']) ? strtoupper($_SESSION['usuario']) : '';
    
    if ($usuario === 'BARRETO') {
        header('Location: manutmenu.php');
    } else {
        header('Location: manutencao.php');
    }
    exit();
} else {
    // Erro ao criar arquivo
    echo "Erro ao ativar manutenção. Verifique as permissões do servidor.";
    exit();
}
?>
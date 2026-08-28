<?php
// ManutencaoOff.php

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

// Remover arquivo de manutenção
$arquivoPath = dirname(__FILE__) . '/manut.txt';

if (file_exists($arquivoPath)) {
    if (unlink($arquivoPath)) {
        // Redirecionar para a página inicial
        header('Location: manutmenu.php');
        exit();
    } else {
        echo "Erro ao remover arquivo de manutenção";
        exit();
    }
} else {
    // Arquivo não existe, redirecionar mesmo assim
    header('Location: manutmenu.php');
    exit();
}
?>
<?php
// manutencao_config.php
// Arquivo de configuração de manutenção do sistema

/**
 * Verifica se o sistema está em modo de manutenção
 * Retorna true se o arquivo manut.txt existir
 */
/** 11 03 2026
 * Verifica se um sistema específico está em manutenção
 * @param string $sistema_id O ID do sistema (ex: 'gabnet', 'sunny', 'todolist')
 * @return bool Retorna true se o arquivo de manutenção existir
 */
function EstaEmManutencao($sistema_id) {
    // Busca na pasta /manut/ na raiz do servidor o arquivo correspondente ao ID
    $arquivoManutencao = $_SERVER['DOCUMENT_ROOT'] . "/manut/manut_{$sistema_id}.txt";
    
    return file_exists($arquivoManutencao);
}

/**
 * Verifica se o usuário atual é administrador
 * Retorna true se o usuário for "barreto" (case-insensitive)
 */
function EAdmin() {

    return (isset($_SESSION['Usuario']) && strtolower(Lower($_SESSION['Usuario'])) == "barreto");
}

/**
 * Verifica se o diretório de vendas está bloqueado
 * Retorna true se o arquivo DIRETORIAS_VENDAS.TXT existir
 */
function DiretorioVendasBloqueado() {
    $arquivoBloqueio = dirname(__FILE__) . "/DIRETORIAS_VENDAS.TXT";
    return file_exists($arquivoBloqueio);
}

/**
 * Redireciona para página de manutenção se necessário
 * Deve ser chamada no início de cada página que precise verificar manutenção
 */
function VerificarManutencao() {
    if (EstaEmManutencao() && !EAdmin()) {
        $script_name = $_SERVER['SCRIPT_NAME'];
        $basename = basename($script_name);
        
        if ($basename !== "manutencao.php") {
            header("Location: manutencao.php");
            exit();
        }
    }
}

/**
 * Verifica e exibe mensagem se vendas estão bloqueadas
 * Deve ser chamada onde for necessário mostrar o bloqueio
 */
function VerificarVendasBloqueadas() {
    if (DiretorioVendasBloqueado()) {
        return true; // Vendas bloqueadas
    }
    return false; // Vendas liberadas
}

/**
 * Cria arquivo de manutenção
 * @param bool $ativar - true para ativar, false para desativar manutenção
 */
function ControlarManutencao($ativar = true) {
    $arquivoManutencao = dirname(__FILE__) . "/manut.txt";
    
    if ($ativar) {
        // Criar arquivo de manutenção
        file_put_contents($arquivoManutencao, "Sistema em manutenção - " . date('d/m/Y H:i:s'));
        return true;
    } else {
        // Remover arquivo de manutenção
        if (file_exists($arquivoManutencao)) {
            unlink($arquivoManutencao);
        }
        return true;
    }
}

/**
 * Cria arquivo de bloqueio de vendas
 * @param bool $bloquear - true para bloquear, false para liberar vendas
 */
function ControlarVendas($bloquear = true) {
    $arquivoBloqueio = dirname(__FILE__) . "/DIRETORIAS_VENDAS.TXT";
    
    if ($bloquear) {
        // Criar arquivo de bloqueio
        file_put_contents($arquivoBloqueio, "Vendas bloqueadas - " . date('d/m/Y H:i:s'));
        return true;
    } else {
        // Remover arquivo de bloqueio
        if (file_exists($arquivoBloqueio)) {
            unlink($arquivoBloqueio);
        }
        return true;
    }
}

/**
 * Retorna status do sistema
 * @return array Array com status do sistema
 */
function StatusSistema() {
    return [
        'manutencao' => EstaEmManutencao(),
        'admin' => EAdmin(),
        'vendas_bloqueadas' => DiretorioVendasBloqueado(),
        'data_hora' => date('d/m/Y H:i:s'),
        'usuario' => isset($_SESSION['Usuario']) ? $_SESSION['Usuario'] : 'N/A',
        'ip' => $_SERVER['REMOTE_ADDR']
    ];
}

// Inicializar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
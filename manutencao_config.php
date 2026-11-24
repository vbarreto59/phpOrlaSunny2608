<?php
// manutencao_config.php
// Arquivo de configuração de manutenção do sistema

/**
 * Verifica se um sistema específico ou o sistema padrão está em manutenção
 * @param string|null $sistema_id O ID do sistema (ex: 'sunny', 'gabnet') ou null para o padrão
 * @return bool Retorna true se o arquivo de manutenção existir
 */
function EstaEmManutencao($sistema_id = null) {
    $arquivoGeral = __DIR__ . '/manut.txt';

    if (file_exists($arquivoGeral)) {
        return true;
    }

    if (!empty($sistema_id)) {
        $arquivoSistema = __DIR__ . "/manut/manut_{$sistema_id}.txt";
        if (file_exists($arquivoSistema)) {
            return true;
        }
    }

    return false;
}

/**
 * Verifica se o usuário atual é administrador
 * Retorna true se o usuário for "barreto" (case-insensitive)
 */
function EAdmin() {
    return (isset($_SESSION['Usuario']) && strtolower(trim($_SESSION['Usuario'])) === "barreto");
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
 * @param string|null $sistema_id
 */
function VerificarManutencao($sistema_id = null) {
    if (EstaEmManutencao($sistema_id) && !EAdmin()) {
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
 */
function VerificarVendasBloqueadas() {
    return DiretorioVendasBloqueado();
}

/**
 * Cria arquivo de manutenção
 * @param bool $ativar - true para ativar, false para desativar manutenção
 * @param string|null $sistema_id
 */
function ControlarManutencao($ativar = true, $sistema_id = null) {
    if ($sistema_id) {
        $pastaManut = $_SERVER['DOCUMENT_ROOT'] . "/manut/";
        if (!is_dir($pastaManut)) {
            mkdir($pastaManut, 0755, true);
        }
        $arquivoManutencao = $pastaManut . "manut_{$sistema_id}.txt";
    } else {
        $arquivoManutencao = dirname(__FILE__) . "/manut.txt";
    }
    
    if ($ativar) {
        file_put_contents($arquivoManutencao, "Sistema em manutenção - " . date('d/m/Y H:i:s'));
        return true;
    } else {
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
        file_put_contents($arquivoBloqueio, "Vendas bloqueadas - " . date('d/m/Y H:i:s'));
        return true;
    } else {
        if (file_exists($arquivoBloqueio)) {
            unlink($arquivoBloqueio);
        }
        return true;
    }
}

/**
 * Retorna status do sistema
 * @param string|null $sistema_id
 * @return array Array com status do sistema
 */
function StatusSistema($sistema_id = null) {
    return [
        'manutencao' => EstaEmManutencao($sistema_id),
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
<?php
// logout.php
// Finaliza a sessão do usuário e redireciona para a página de login

// Inicia a sessão (necessário para destruí-la)
session_start();

// ================================================
// Limpa todas as variáveis de sessão
// ================================================
$_SESSION = array();  // esvazia o array da sessão

// ================================================
// Destrói a sessão completamente
// ================================================
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destrói a sessão no servidor
session_destroy();

// ================================================
// Redireciona para a página de login
// ================================================
// Use o caminho correto conforme o nome do seu arquivo de login
header('Location: login.php', true, 302);
exit;
?>
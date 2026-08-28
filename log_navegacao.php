<?php
/**
 * Registra o acesso atual no histórico de navegação
 * 
 * @param PDO    $conn          Conexão PDO ativa com o banco de dados
 * @param array  $sessionData   Dados da sessão do usuário (ex: $_SESSION)
 * @param bool   $silent        Se true, não gera saída visível mesmo em caso de erro (padrão: true)
 * @return bool  Sucesso da operação de inserção
 */
// uso: registrarNavegacao($conn, $_SESSION);
function registrarNavegacao(
    PDO $conn,
    array $sessionData = [],
    bool $silent = true
): bool {
    // =============================================
    // 1. Obtém informações do usuário da sessão
    // =============================================
    $userID = $sessionData['UserID']   ?? 0;
    $usuario = $sessionData['Usuario'] ?? '(não identificado)';

    // Se não houver usuário logado → não registra (ou registra como anônimo, dependendo da regra do sistema)
    if ($userID === 0 && empty($usuario)) {
        return false; // ou true, dependendo se quer registrar acessos anônimos
    }

    // =============================================
    // 2. Coleta dados do ambiente HTTP
    // =============================================
    $caminhoPagina = $_SERVER['REQUEST_URI'] ?? '/desconhecido';
    $ipUsuario     = $_SERVER['REMOTE_ADDR']  ?? '0.0.0.0';
    $userAgent     = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';
    $dataHora      = date('Y-m-d H:i:s');

    // Limita tamanho dos campos (evita erro de truncamento ou overflow)
    $caminhoPagina = mb_substr($caminhoPagina, 0, 255);
    $userAgent     = mb_substr($userAgent,     0, 255);

    // =============================================
    // 3. Prepara e executa a inserção
    // =============================================
    try {
        $sql = "
            INSERT INTO historico_navegacao 
            (UserID, Usuario, Caminho_Pagina, IP_Usuario, Data_Hora_Visita, User_Agent)
            VALUES 
            (:userID, :usuario, :caminho, :ip, :data_hora, :user_agent)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            ':userID'     => $userID,
            ':usuario'    => $usuario,
            ':caminho'    => $caminhoPagina,
            ':ip'         => $ipUsuario,
            ':data_hora'  => $dataHora,
            ':user_agent' => $userAgent
        ]);

        return $stmt->rowCount() > 0;

    } catch (PDOException $e) {
        // Log em arquivo ou sistema de monitoramento (recomendado em produção)
        error_log(
            "Erro ao registrar navegação | " .
            "UserID: $userID | " .
            "Página: $caminhoPagina | " .
            "Mensagem: " . $e->getMessage() . " | " .
            date('Y-m-d H:i:s')
        );

        // Em ambiente de desenvolvimento você pode querer ver o erro
        if (!$silent && defined('ENV') && ENV === 'development') {
            echo "<!-- Erro no log de navegação: " . htmlspecialchars($e->getMessage()) . " -->";
        }

        return false;
    }
}
<?php
// enviacredencial_pdo.php
// Conversão do ASP para PHP com PDO
// Versão: 1.0 - Data: <?php echo date('Y-m-d'); ?>

<?php
// Habilitar relatório de erros (desative em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Incluir conexão PDO
require_once "conn.php"; // Seu arquivo de conexão PDO

// Forçar charset UTF-8
header('Content-Type: text/html; charset=UTF-8');

// --- 1. Obter o UserID da URL ---
$UserID = isset($_GET['UserID']) ? trim($_GET['UserID']) : '';

if ($UserID === '' || !is_numeric($UserID)) {
    echo "<p style='color: red;'>Erro: O parâmetro UserID é obrigatório e deve ser um número.</p>";
    exit;
}

$UserID = (int)$UserID; // Converter para inteiro

try {
    // --- 2. Conectar ao Banco de Dados e Buscar Credenciais ---
    // (A conexão já está no conn.php, usaremos $pdo)
    
    // Preparar e executar a consulta usando prepared statement
    $sql = "SELECT * FROM usuarios WHERE UserID = :UserID";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':UserID', $UserID, PDO::PARAM_INT);
    $stmt->execute();
    
    // --- 3. Verificar se o Usuário Existe e Possui E-mail ---
    if ($stmt->rowCount() == 0) {
        echo "<p>Usuário com ID " . htmlspecialchars($UserID) . " não encontrado no sistema.</p>";
    } else {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $userEmail = trim($row['Email']);
        $userName = strtolower($row['Usuario']);
        $userPassword = $row['SenhaAtual'];
        
        if (empty($userEmail)) {
            echo "<p>O usuário '" . htmlspecialchars($userName) . "' (ID: " . htmlspecialchars($UserID) . ") não possui um e-mail cadastrado. Não foi possível enviar as credenciais.</p>";
        } else {
            // --- 4. Enviar o E-mail usando PHPMailer (recomendado) ou mail() ---
            
            // Configurações do e-mail
            $to = $userEmail;
            $cc = "valterpb@hotmail.com";
            $subject = "SI-Credenciais";
            
            // Corpo do e-mail (texto simples)
            $message = "Olá, você está recebendo a credencial para acessar o sistema SUNNYIMOB.\n\n";
            $message .= "Usuário: " . $userName . "\n";
            $message .= "Senha: " . $userPassword . "\n\n";
            $message .= "Link do SunnyImob: https://dev.gabnetweb.com.br/Sunny2601/login_p1.php\n\n";
            $message .= "Qualquer dúvida, entrar em contato com Valter Barreto - 81 98842-1455 (WhatsApp).\n\n";
 
            $message .= "Atenciosamente,\n";
            $message .= "Equipe SUNNYIMOB";
            
            // Cabeçalhos
            $headers = "From: sendmail@gabnetweb.com.br\r\n";
            $headers .= "Cc: " . $cc . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            
            // Tentar enviar o e-mail
            if (mail($to, $subject, $message, $headers)) {
                echo "<p>E-mail com credenciais enviado com sucesso para: <strong>" . htmlspecialchars($userEmail) . "</strong></p>";
                echo "<p>Usuário: <strong>" . htmlspecialchars($userName) . "</strong></p>";
                echo "<p>Senha: <strong>" . htmlspecialchars($userPassword) . "</strong></p>";
                echo "<p>Uma cópia também foi enviada para <strong>" . htmlspecialchars($cc) . "</strong>.</p>";
            } else {
                echo "<p style='color: red;'>Erro ao enviar o e-mail. Verifique a configuração do servidor SMTP.</p>";
                // Para debug, você pode verificar o log de erros
                error_log("Falha ao enviar email para: " . $userEmail);
            }
        }
    }
    
    // Fechar statement
    $stmt->closeCursor();
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro no banco de dados: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Erro PDO: " . $e->getMessage());
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Erro geral: " . $e->getMessage());
}

// Fechar conexão (opcional, será fechada automaticamente no fim do script)
$pdo = null;
?>
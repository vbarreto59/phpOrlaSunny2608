<?php
// excluir_empreendimento.php
// Data: 11/08/2025 - Valter Barreto
// Convertido de Classic ASP para PHP + PDO

session_start();

// Verifica login (ajuste conforme seu sistema de autenticação)
if (empty($_SESSION['Usuario'])) {
    header('Location: gestao_login.php');
    exit;
}

require_once 'conn.php'; // sua conexão PDO ($pdo)

// Verifica se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = "ID do empreendimento não informado.";
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$empreendimentoID = (int)$_GET['id'];

try {
    // 1. Obter dados do empreendimento antes de excluir
    $sqlSelect = "SELECT 
                    e.nomeempreendimento, 
                    e.localidade, 
                    em.nomeempresa 
                  FROM empreendimento e 
                  INNER JOIN empresa em ON e.empresa_id = em.empresa_id 
                  WHERE e.empreend_id = :id";
    
    $stmt = $pdo->prepare($sqlSelect);
    $stmt->bindParam(':id', $empreendimentoID, PDO::PARAM_INT);
    $stmt->execute();
    
    $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empreendimento) {
        $_SESSION['erro'] = "Empreendimento não encontrado.";
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    $nomeEmpreendimento = $empreendimento['nomeempreendimento'] ?? '';
    $localidade = $empreendimento['localidade'] ?? '';
    $nomeEmpresa = $empreendimento['nomeempresa'] ?? '';
    
    // 2. Executar o DELETE para remover o empreendimento
    $sqlDelete = "DELETE FROM empreendimento WHERE empreend_id = :id";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->bindParam(':id', $empreendimentoID, PDO::PARAM_INT);
    $stmtDelete->execute();
    
    // Verificar se foi excluído com sucesso
    if ($stmtDelete->rowCount() > 0) {
        $_SESSION['sucesso'] = "Empreendimento excluído com sucesso!";
        
        // 3. Envio de email de notificação (apenas se não for localhost)
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            $usuario = strtoupper($_SESSION['Usuario'] ?? 'Desconhecido');
            $data = date('d/m/Y');
            $hora = date('H:i:s');
            
            // Prepara os dados do email
            $assunto = "SI-DELETE-EMPREEND-" . $usuario . " - " . $ip . " - " . $data . " - " . $hora;
            
            $mensagem = "O empreendimento '" . $nomeEmpreendimento . "' da empresa '" . 
                       $nomeEmpresa . "' em '" . $localidade . "' foi excluído por " . 
                       $usuario . ".";
            
            // Destinatários (separados por vírgula conforme original)
            $para = "valterpb@gmail.com, valterpb@hotmail.com";
            
            // Remetente
            $de = "sendmail@gabnetweb.com.br";
            
            // Cabeçalhos do email
            $headers = "From: " . $de . "\r\n" .
                      "Reply-To: " . $de . "\r\n" .
                      "Content-Type: text/plain; charset=utf-8\r\n" .
                      "X-Mailer: PHP/" . phpversion();
            
            // Envia o email
            $enviado = mail($para, $assunto, $mensagem, $headers);
            
            if (!$enviado) {
                // Log de erro se o email não for enviado
                error_log("Falha ao enviar email de exclusão de empreendimento: " . 
                         $assunto . " | " . $mensagem);
                
                // Você pode adicionar esta informação à sessão se quiser
                // $_SESSION['aviso'] = "Email de notificação não pôde ser enviado.";
            } else {
                // Log de sucesso (opcional)
                error_log("Email enviado com sucesso: " . $assunto);
            }
        }
    } else {
        $_SESSION['erro'] = "Não foi possível excluir o empreendimento.";
    }
    
} catch (PDOException $e) {
    error_log("Erro ao excluir empreendimento: " . $e->getMessage());
    $_SESSION['erro'] = "Ocorreu um erro ao excluir o empreendimento.";
}

// Redireciona de volta para a página anterior
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitização e recebimento dos dados do formulário
    $nome_remetente = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $assunto_email  = filter_input(INPUT_POST, 'assunto', FILTER_SANITIZE_SPECIAL_CHARS);
    $mensagem_corpo = filter_input(INPUT_POST, 'mensagem', FILTER_SANITIZE_SPECIAL_CHARS);

    // Definição dos e-mails conforme solicitado
    $email_remetente = "sendmail@sunnyimob.com.br";
    $email_destino   = "valterpb@hotmail.com";

    // Configuração dos cabeçalhos (Headers) do e-mail para evitar cair no SPAM
    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . $nome_remetente . " <" . $email_remetente . ">" . "\r\n";
    $headers .= "Reply-To: " . $email_remetente . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Montagem do layout do e-mail em HTML
    $corpo_html = "
    <html>
    <head>
        <title>$assunto_email</title>
    </head>
    <body style='font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; border-top: 4px solid #ff9f43;'>
            <h2 style='color: #ff9f43; margin-top: 0;'>☀️ SunnyImob - Notificação de Teste</h2>
            <p style='color: #333333; font-size: 16px; line-height: 1.6;'>
                <strong>Assunto:</strong> $assunto_email <br>
                <strong>Origem:</strong> $email_remetente
            </p>
            <hr style='border: 0; border-top: 1px solid #eeeeee; margin: 20px 0;'>
            <div style='color: #555555; font-size: 15px; line-height: 1.6; white-space: pre-wrap;'>
                $mensagem_corpo
            </div>
            <hr style='border: 0; border-top: 1px solid #eeeeee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #999999; text-align: center; margin-bottom: 0;'>
                Este é um disparo de teste gerado pela plataforma de testes SunnyImob.
            </p>
        </div>
    </body>
    </html>
    ";

    // Executa a função nativa de e-mail do PHP
    if (mail($email_destino, $assunto_email, $corpo_html, $headers)) {
        echo "<script>
                alert('E-mail de teste enviado com sucesso para $email_destino!');
                window.location.href = 'index.html';
              </script>";
    } else {
        echo "<script>
                alert('Falha ao enviar o e-mail. Verifique as configurações de SMTP do seu servidor PHP.');
                window.history.back();
              </script>";
    }
} else {
    // Redireciona caso o arquivo seja acessado diretamente sem o formulário
    header("Location: index.html");
    exit();
}
?>

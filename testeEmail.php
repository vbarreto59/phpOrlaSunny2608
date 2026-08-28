<?php
// Inclui o arquivo onde a função foi gravada
require_once 'send_email.php';

// Define os dados do e-mail
$destinatario = 'valterpb@gmail.com,valterpb@hotmail.com';
$assunto = 'Teste SGVendas - ' . date('H:i:s');
$mensagemHTML = "
    <div style='background-color: #f4f4f4; padding: 20px;'>
        <h2 style='color: #0056b3;'>Confirmação de Acesso</h2>
        <p>Olá Valter, este é um teste disparado pelo arquivo <b>send_email.php</b>.</p>
        <p>Data: " . date('d/m/Y') . "</p>
    </div>
";

// Chama a função e armazena o resultado
$resultado = enviarEmailSGVendas($destinatario, $assunto, $mensagemHTML);

// Exibe o retorno
if ($resultado['status']) {
    echo "<h3>SUCESSO: " . $resultado['msg'] . "</h3>";
    echo "<h3>Enviado para: " . $destinatario . "</h3>";
} else {
    echo "<h3>ERRO: " . $resultado['msg'] . "</h3>";
}
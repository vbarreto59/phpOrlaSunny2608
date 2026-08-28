<?php
function log_acesso($usuario_id) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO log_acessos (usuario_id, ip) VALUES (?, ?)");
    $stmt->execute([$usuario_id, $ip]);

    // Envia e-mail
    $to = "valterpb@hotmail.com";
    $subject = "Acesso ao Sistema de Captação";
    $message = "Usuário: " . $_SESSION['username'] . "\nData: " . date('d/m/Y H:i:s') . "\nIP: $ip";
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    mail($to, $subject, $message, $headers);
}

function log_historico($imovel_id, $acao, $descricao) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO historico_imoveis (imovel_id, usuario_id, acao, descricao) VALUES (?, ?, ?, ?)");
    $stmt->execute([$imovel_id, $_SESSION['user_id'], $acao, $descricao]);
}

function upload_fotos($imovel_id) {
    global $conn;
    $dir = "uploads/fotos_imoveis/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    foreach ($_FILES['fotos']['name'] as $i => $nome) {
        if ($_FILES['fotos']['error'][$i] == 0) {
            $ext = pathinfo($nome, PATHINFO_EXTENSION);
            $novo_nome = "imovel_{$imovel_id}_" . date('Ymd_His') . "_$i.$ext";
            $caminho = $dir . $novo_nome;
            move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $caminho);

            $stmt = $conn->prepare("INSERT INTO fotos_imoveis (imovel_id, caminho) VALUES (?, ?)");
            $stmt->execute([$imovel_id, $caminho]);
        }
    }
}
?>
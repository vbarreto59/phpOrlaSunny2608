<?php
// transferir_ftp_para_ftp.php
set_time_limit(0); // Evita timeout em conexões lentas
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==========================================
// CONFIGURAÇÕES FTP A (Origem)
// ==========================================
$ftpA = [
    'host' => 'htdcvrwb1.hotlink.com.br',
    'user' => 'cli213_gabnetweb',
    'pass' => '@o6adiYYW',
    'dir'  => '/Sunny2601/card/' // Pasta das imagens no A
];

// ==========================================
// CONFIGURAÇÕES FTP B (Destino)
// ==========================================
$ftpB = [
    'host' => 'htdcvrwb1.hotlink.com.br',
    'user' => 'cli18432_sunnyimob',
    'pass' => '5&?5semW',
    'dir'  => '/web/orlasunny/card/' // Pasta destino no B
];

// Estilo básico para o navegador
echo "<html><head><meta charset='UTF-8'><title>Transferência FTP</title></head>";
echo "<body style='font-family: monospace; padding: 20px; background: #f4f4f4;'>";
echo "<h2>Transferência de Imagens: A ➡️ B</h2>";
echo "<div style='background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";

// 1. Conectar no FTP de A
echo "<p>🔄 Conectando no FTP de A...</p>"; flush();
$connA = @ftp_connect($ftpA['host'], 21, 30);
if (!$connA) die("<p style='color:red'>❌ Falha ao conectar no FTP A. Verifique o host.</p>");
if (!@ftp_login($connA, $ftpA['user'], $ftpA['pass'])) die("<p style='color:red'>❌ Falha no login FTP A. Verifique usuário/senha.</p>");
ftp_pasv($connA, true);

// 2. Conectar no FTP de B
echo "<p>🔄 Conectando no FTP de B...</p>"; flush();
$connB = @ftp_connect($ftpB['host'], 21, 30);
if (!$connB) die("<p style='color:red'>❌ Falha ao conectar no FTP B.</p>");
if (!@ftp_login($connB, $ftpB['user'], $ftpB['pass'])) die("<p style='color:red'>❌ Falha no login FTP B.</p>");
ftp_pasv($connB, true);

// 3. Garantir que a pasta destino em B exista
@ftp_mkdir($connB, $ftpB['dir']);

// 4. Listar arquivos em A
$filesA = ftp_nlist($connA, $ftpA['dir'] . '*.jpg');
if (!$filesA || count($filesA) == 0) {
    die("<p style='color:orange'>⚠️ Nenhuma imagem .jpg encontrada em " . $ftpA['dir'] . "</p>");
}

echo "<p>✅ Encontradas <b>" . count($filesA) . "</b> imagens em A.</p>";
echo "<hr>";

$transferidos = 0;
$erros = 0;

// 5. Loop de transferência
foreach ($filesA as $remoteFileA) {
    $fileName = basename($remoteFileA);
    
    // Ignora arquivos ocultos ou que não sejam jpg
    if ($fileName === '.' || $fileName === '..' || strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'jpg') {
        continue;
    }

    $remoteFileB = rtrim($ftpB['dir'], '/') . '/' . $fileName;
    
    // Cria arquivo temporário local para fazer a ponte entre os servidores
    $tmpFile = tempnam(sys_get_temp_dir(), 'ftp_');
    
    // Baixa do A para o temporário
    if (@ftp_get($connA, $tmpFile, $remoteFileA, FTP_BINARY)) {
        // Envia do temporário para o B
        if (@ftp_put($connB, $remoteFileB, $tmpFile, FTP_BINARY)) {
            echo "✅ <span style='color:green'>[OK] $fileName</span><br>";
            $transferidos++;
        } else {
            echo "❌ <span style='color:red'>[ERRO B] Falha ao enviar: $fileName</span><br>";
            $erros++;
        }
    } else {
        echo "❌ <span style='color:red'>[ERRO A] Falha ao baixar: $fileName</span><br>";
        $erros++;
    }
    
    // Limpa o arquivo temporário
    @unlink($tmpFile);
    flush(); // Atualiza a tela do navegador em tempo real
}

ftp_close($connA);
ftp_close($connB);

echo "<hr>";
echo "<h3>Resumo da Operação:</h3>";
echo "<p>✅ Transferidos: <b style='color:green'>$transferidos</b></p>";
echo "<p>❌ Erros: <b style='color:red'>$erros</b></p>";
echo "<p><i>As imagens mantêm o mesmo nome (ex: 581.jpg). O sistema comparador lerá automaticamente as URLs configuradas.</i></p>";
echo "</div></body></html>";
?>
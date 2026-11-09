<?php
/**
 * SISTEMA: SGVENDAS
 * FUNÇÃO: Enviar e-mail usando a função nativa mail() do PHP
 * DATA: 20/01/2026
 */

/**
 * @param string $para      E-mail do destinatário
 * @param string $assunto   Assunto da mensagem
 * @param string $mensagem  Corpo do e-mail (HTML)
 * @return array            Array com status e mensagem de retorno
 */
function enviarEmailSGVendas($para, $assunto, $mensagem) {
    
    // 1. Obtém o IP completo do cliente
    $ipCompleto = $_SERVER['REMOTE_ADDR'] ?? 'IP não disponível';
    
    // 2. Adiciona os 3 primeiros dígitos do IP no final do assunto
    // Primeiro, remove pontos para pegar apenas dígitos
    $digitosIP = preg_replace('/[^0-9]/', '', $ipCompleto);
    
    if (strlen($digitosIP) >= 3) {
        $tresPrimeirosDigitosIP = substr($digitosIP, 0, 3);
        $assunto .= " [$tresPrimeirosDigitosIP]";
    } else {
        // Se não tiver 3 dígitos, usa os disponíveis ou um padrão
        $assunto .= " [" . ($digitosIP ?: '000') . "]";
    }
    
    // 3. Obtém informações adicionais sobre o IP (se disponível)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipCompleto .= ' (Forwarded: ' . $_SERVER['HTTP_X_FORWARDED_FOR'] . ')';
    }
    
    // 4. Detecta o tipo de dispositivo
    $dispositivo = 'Dispositivo não identificado';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Detecção de sistema operacional
    if (stripos($userAgent, 'Windows') !== false) {
        $dispositivo = 'Windows';
        
        // Detalhamento da versão do Windows
        if (stripos($userAgent, 'Windows NT 10.0') !== false) {
            $dispositivo = 'Windows 10/11';
        } elseif (stripos($userAgent, 'Windows NT 6.3') !== false) {
            $dispositivo = 'Windows 8.1';
        } elseif (stripos($userAgent, 'Windows NT 6.2') !== false) {
            $dispositivo = 'Windows 8';
        } elseif (stripos($userAgent, 'Windows NT 6.1') !== false) {
            $dispositivo = 'Windows 7';
        } elseif (stripos($userAgent, 'Windows NT 6.0') !== false) {
            $dispositivo = 'Windows Vista';
        } elseif (stripos($userAgent, 'Windows NT 5.1') !== false) {
            $dispositivo = 'Windows XP';
        }
    } elseif (stripos($userAgent, 'Android') !== false) {
        $dispositivo = 'Android';
    } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
        $dispositivo = 'iOS';
    } elseif (stripos($userAgent, 'Mac OS') !== false || stripos($userAgent, 'Macintosh') !== false) {
        $dispositivo = 'macOS';
    } elseif (stripos($userAgent, 'Linux') !== false) {
        $dispositivo = 'Linux';
        if (stripos($userAgent, 'X11') !== false) {
            $dispositivo = 'Linux Desktop';
        }
    }
    
    // Detecção de navegador
    $navegador = 'Navegador não identificado';
    if (stripos($userAgent, 'Chrome') !== false) {
        $navegador = 'Chrome';
    } elseif (stripos($userAgent, 'Firefox') !== false) {
        $navegador = 'Firefox';
    } elseif (stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false) {
        $navegador = 'Safari';
    } elseif (stripos($userAgent, 'Edge') !== false) {
        $navegador = 'Edge';
    } elseif (stripos($userAgent, 'Opera') !== false || stripos($userAgent, 'OPR') !== false) {
        $navegador = 'Opera';
    }
    
    // 5. Adiciona o IP e tipo de dispositivo ao final da mensagem
    $informacoesAdicionais = "<br><br><hr>";
    $informacoesAdicionais .= "<div style='font-size: 12px; color: #666; margin-top: 20px; padding: 10px; background-color: #f5f5f5; border-left: 3px solid #007bff;'>";
    $informacoesAdicionais .= "<strong>📊 Informações Técnicas do Envio:</strong><br><br>";
    $informacoesAdicionais .= "🔗 <strong>IP Completo:</strong> " . htmlspecialchars($ipCompleto) . "<br>";
    $informacoesAdicionais .= "💻 <strong>Dispositivo/Sistema:</strong> " . htmlspecialchars($dispositivo) . "<br>";
    $informacoesAdicionais .= "🌐 <strong>Navegador:</strong> " . htmlspecialchars($navegador) . "<br>";
    $informacoesAdicionais .= "📅 <strong>Data/Hora do Envio:</strong> " . date('d/m/Y H:i:s') . "<br>";
    
    // Adiciona o User Agent truncado
    if (!empty($userAgent)) {
        $informacoesAdicionais .= "🆔 <strong>User Agent:</strong> " . htmlspecialchars(substr($userAgent, 0, 150)) . 
                                 (strlen($userAgent) > 150 ? '...' : '') . "<br>";
    }
    
    $informacoesAdicionais .= "</div>";
    
    $mensagem .= $informacoesAdicionais;
    
    // Configuração dos Cabeçalhos (Headers) para e-mail HTML
    $remetente = 'sendmail@sunnyimob.com.br';
    
    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: SunnyImob <$remetente>" . "\r\n";
    $headers .= "Reply-To: $remetente" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    try {
        // A função mail() retorna true se o e-mail foi aceito para envio
        if (mail($para, $assunto, $mensagem, $headers)) {
            return [
                'status' => true,
                'msg' => 'E-mail enviado com sucesso (via função nativa)!'
            ];
        } else {
            return [
                'status' => false,
                'msg' => 'O servidor PHP não conseguiu processar o envio.'
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => false,
            'msg' => "Erro de exceção: " . $e->getMessage()
        ];
    }
}
?>
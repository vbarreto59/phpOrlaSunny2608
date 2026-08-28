<?php
session_start();
// Inclui a conexão PDO
require_once 'conn.php';

$mensagemSucesso = "";
$showSuccess = false;
$novoID = null;
$nomeEmpreendimentoCadastrado = "";

// Verifica se o formulário foi submetido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupera os dados do formulário
    $urlRetorno = $_POST['urlRetorno'] ?? '';
    $EmpresaID = $_POST['EmpresaID'] ?? '';
    
    // CORREÇÃO: Use mb_strtoupper() para caracteres acentuados
    $Nome = isset($_POST['Nome']) ? mb_strtoupper($_POST['Nome'], 'UTF-8') : '';
    $Localidade = isset($_POST['Localidade']) ? mb_strtoupper($_POST['Localidade'], 'UTF-8') : '';

    // Validação básica
    if (!empty($EmpresaID) && !empty($Nome) && !empty($Localidade)) {
        try {
            // Prepara a consulta SQL usando prepared statement
            $sql = "INSERT INTO empreendimento (Empresa_Id, NomeEmpreendimento, Localidade) 
                    VALUES (:empresa_id, :nome, :localidade)";
            
            $stmt = $conn->prepare($sql);
            
            // Vincula os parâmetros
            $stmt->bindParam(':empresa_id', $EmpresaID, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $Nome, PDO::PARAM_STR);
            $stmt->bindParam(':localidade', $Localidade, PDO::PARAM_STR);
            
            // Executa a inserção
            $stmt->execute();
            
            // Obtém o ID do último registro inserido
            $novoID = $conn->lastInsertId();
            $nomeEmpreendimentoCadastrado = $Nome;
            
            // Recupera o nome da empresa para exibir
            $sqlEmpresa = "SELECT NomeEmpresa FROM empresa WHERE Empresa_Id = :empresa_id";
            $stmtEmpresa = $conn->prepare($sqlEmpresa);
            $stmtEmpresa->bindParam(':empresa_id', $EmpresaID, PDO::PARAM_INT);
            $stmtEmpresa->execute();
            $empresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);
            $nomeEmpresa = isset($empresa['NomeEmpresa']) ? mb_strtoupper($empresa['NomeEmpresa'], 'UTF-8') : '';
            
            // ENVIO DE EMAIL
            $ip = $_SERVER['REMOTE_ADDR'];
            $usuario = isset($_SESSION['Usuario']) ? strtoupper($_SESSION['Usuario']) : 'DESCONHECIDO';
            $data = date('d/m/Y');
            $hora = date('H:i:s');
            
            // Configuração do email
            $para = "valterpb@gmail.com, valterpb@hotmail.com";
            $de = "sendmail@gabnetweb.com.br";
            
            // Assunto do email
            $assunto = "SI-NOVO-EMPREEND-" . $usuario . " - " . $ip . " - " . $data . " - " . $hora;
            
            // Corpo do email
            $mensagemEmail = "NOVO EMPREENDIMENTO CADASTRADO NO SISTEMA\r\n";
            $mensagemEmail .= "===========================================\r\n";
            $mensagemEmail .= "\r\n";
            $mensagemEmail .= "ID do Empreendimento: #" . $novoID . "\r\n";
            $mensagemEmail .= "Nome: " . $Nome . "\r\n";
            $mensagemEmail .= "Empresa: " . $nomeEmpresa . "\r\n";
            $mensagemEmail .= "Localidade: " . $Localidade . "\r\n";
            $mensagemEmail .= "\r\n";
            $mensagemEmail .= "Cadastrado por: " . $usuario . "\r\n";
            $mensagemEmail .= "Data/Hora: " . $data . " - " . $hora . "\r\n";
            $mensagemEmail .= "IP: " . $ip . "\r\n";
            
            // Cabeçalhos do email
            $headers = "From: " . $de . "\r\n" .
                      "Reply-To: " . $de . "\r\n" .
                      "Content-Type: text/plain; charset=utf-8\r\n" .
                      "X-Mailer: PHP/" . phpversion();
            
            // Envia o email apenas se não for localhost
            //if ($ip !== '127.0.0.1' && $ip !== '::1') {
                $enviado = mail($para, $assunto, $mensagemEmail, $headers);
                
                if (!$enviado) {
                    error_log("Falha ao enviar email de novo empreendimento: " . $assunto);
                } else {
                    error_log("Email enviado: Novo empreendimento cadastrado - " . $Nome);
                }
            //}
            
            $mensagemSucesso = "Empreendimento cadastrado com sucesso!";
            $showSuccess = true;
            
        } catch (PDOException $e) {
            // Em caso de erro, você pode mostrar uma mensagem
            $mensagemSucesso = "Erro ao cadastrar empreendimento: " . $e->getMessage();
            $showSuccess = false;
        }
    } else {
        $mensagemSucesso = "Por favor, preencha todos os campos obrigatórios.";
        $showSuccess = false;
    }
}

// Recupera a lista de empresas para o select
try {
    $sqlEmpresas = "SELECT Empresa_Id, NomeEmpresa FROM empresa ORDER BY NomeEmpresa";
    $stmtEmpresas = $conn->prepare($sqlEmpresas);
    $stmtEmpresas->execute();
    $empresas = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar empresas: " . $e->getMessage());
}

// URL anterior para retorno
$urlAnterior = $_SERVER['HTTP_REFERER'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="600">
    <title>Cadastrar Empreendimento</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bordo-escuro: #4B0C1D;
            --bordo: #800020;
            --bordo-claro: #A01C3A;
            --dourado: #D4AF37;
            --dourado-claro: #F0E68C;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-custom {
            background-color: var(--bordo-escuro);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-custom .navbar-brand {
            color: var(--dourado);
            font-weight: 600;
        }
        
        .card-custom {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border-top: 4px solid var(--bordo);
            background-color: white;
        }
        
        .card-header-custom {
            background-color: var(--bordo);
            color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .btn-custom {
            background-color: var(--bordo);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .btn-custom:hover {
            background-color: var(--bordo-claro);
            color: white;
        }
        
        .btn-outline-custom {
            border: 1px solid var(--bordo);
            color: var(--bordo);
            background-color: transparent;
        }
        
        .btn-outline-custom:hover {
            background-color: var(--bordo);
            color: white;
        }
        
        .form-label {
            font-weight: 500;
            color: #555;
        }
        
        .required-field::after {
            content: " *";
            color: var(--bordo);
        }
        
        .form-control:focus {
            border-color: var(--dourado);
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
        
        .page-header {
            color: var(--bordo-escuro);
            border-bottom: 2px solid var(--dourado);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success-custom {
            background-color: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.3);
            color: #155724;
        }
        
        .novo-cadastro-info {
            background-color: rgba(212, 175, 55, 0.1);
            border: 1px solid var(--dourado);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .novo-cadastro-info h5 {
            color: var(--bordo);
            margin-bottom: 10px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 150px;
            color: var(--bordo-escuro);
        }
        
        .info-value {
            font-weight: 500;
            color: #333;
        }
        
        .id-badge {
            background-color: var(--bordo);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .email-info {
            background-color: rgba(23, 162, 184, 0.1);
            border: 1px solid #17a2b8;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .email-info h6 {
            color: #17a2b8;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($showSuccess): ?>
                <div class="alert alert-success-custom alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i><strong><?php echo htmlspecialchars($mensagemSucesso); ?></strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                
                <!-- Mostra informações do novo cadastro -->
                <div class="novo-cadastro-info mb-4">
                    <h5><i class="fas fa-info-circle me-2"></i>Novo Empreendimento Cadastrado</h5>
                    <div class="info-item">
                        <span class="info-label">ID do Empreendimento:</span>
                        <span class="info-value">
                            <span class="id-badge">#<?php echo htmlspecialchars($novoID); ?></span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nome:</span>
                        <span class="info-value"><?php echo htmlspecialchars($nomeEmpreendimentoCadastrado); ?></span>
                    </div>
                    <?php if (!empty($nomeEmpresa)): ?>
                    <div class="info-item">
                        <span class="info-label">Empresa:</span>
                        <span class="info-value"><?php echo htmlspecialchars($nomeEmpresa); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($Localidade)): ?>
                    <div class="info-item">
                        <span class="info-label">Localidade:</span>
                        <span class="info-value"><?php echo htmlspecialchars($Localidade); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Data/Hora:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s'); ?></span>
                    </div>
                </div>
                
                <?php 
                $ip = $_SERVER['REMOTE_ADDR'];
                if ($ip !== '127.0.0.1' && $ip !== '::1'): 
                ?>
                <div class="email-info mb-4">
                    <h6><i class="fas fa-envelope me-2"></i>Email de Notificação Enviado</h6>
                    <p class="mb-0">Um email foi enviado para a equipe informando sobre o novo empreendimento cadastrado.</p>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-info mb-4">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Dica:</strong> Você pode fechar esta janela ou cadastrar um novo empreendimento.
                    <div class="mt-2">
                        <button type="button" onclick="window.close();" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-times me-1"></i>Fechar Janela
                        </button>
                        <button type="button" onclick="location.reload();" class="btn btn-sm btn-custom">
                            <i class="fas fa-plus me-1"></i>Novo Cadastro
                        </button>
                    </div>
                </div>
                <?php endif;?>

                
                <div class="card card-custom mb-4">
                    <div class="card-header card-header-custom">
                        <h3 class="mb-0"><i class="fas fa-building me-2"></i>Cadastrar Empreendimento</h3>
                    </div>
                    <div class="card-body p-4">
                        <form method="post">
                            <input type="hidden" name="urlRetorno" value="<?php echo htmlspecialchars($urlAnterior); ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="EmpresaID" class="form-label required-field">Empresa</label>
                                    <select class="form-select" id="EmpresaID" name="EmpresaID" required>
                                        <option value="">Selecione uma empresa</option>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?php echo htmlspecialchars($empresa['Empresa_Id']); ?>" 
                                                <?php echo ($showSuccess && $EmpresaID == $empresa['Empresa_Id']) ? 'selected' : ''; ?>>
                                                <?php echo mb_strtoupper(htmlspecialchars($empresa['NomeEmpresa'] ?? ''), 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="Nome" class="form-label required-field">Nome do Empreendimento</label>
                                    <input type="text" class="form-control" id="Nome" name="Nome" 
                                           value="<?php echo $showSuccess ? '' : htmlspecialchars($Nome ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="Localidade" class="form-label required-field">Localidade</label>
                                    <input type="text" class="form-control" id="Localidade" name="Localidade" 
                                           value="<?php echo $showSuccess ? '' : htmlspecialchars($Localidade ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" onclick="window.close();" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Fechar
                                </button>
                                <button type="submit" class="btn btn-custom">
                                    <i class="fas fa-save me-2"></i>Salvar Empreendimento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Fechar o alerta após 5 segundos
        $(document).ready(function(){
            setTimeout(function(){
                $('.alert').alert('close');
            }, 10000); // Aumentei para 10 segundos para dar tempo de ler
            
            // Limpar o formulário após envio bem-sucedido
            <?php if ($showSuccess): ?>
                // Mantém o campo da empresa selecionado
                // Limpa apenas os campos Nome e Localidade
                $('#Nome').val('');
                $('#Localidade').val('');
                
                // Foca no campo Nome para novo cadastro
                setTimeout(function() {
                    $('#Nome').focus();
                }, 500);
            <?php endif; ?>
            
            // Botão para novo cadastro
            $('.btn-novo-cadastro').click(function() {
                // Limpa apenas os campos Nome e Localidade
                $('#Nome').val('');
                $('#Localidade').val('');
                $('#Nome').focus();
                
                // Esconde a seção de informações do cadastro anterior
                $('.novo-cadastro-info').hide();
                $('.alert-info').hide();
                
                // Rola para o topo do formulário
                $('html, body').animate({
                    scrollTop: $('.card-custom').offset().top - 50
                }, 500);
            });
        });
    </script>
</body>
</html>
<?php
session_start();
require_once 'conn.php';

// V1
// ------------envio de email de login'
function sendMail($message) {
    $remote_addr = $_SERVER['REMOTE_ADDR'];
    
    if ($remote_addr !== "127.0.0.1" && $remote_addr !== "::1") {
        // Aqui você pode implementar o envio de email usando PHPMailer, mail() ou outra biblioteca
        // Exemplo básico com mail() (ajuste conforme sua necessidade):
        /*
        $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
        $subject = "SI-USR.NOVO-" . strtoupper($_SESSION['Usuario']) . " - " . $remote_addr . " - " . date('Y-m-d') . " - " . date('H:i:s');
        $headers = "From: sendmail@gabnetweb.com.br\r\n";
        mail($to, $subject, $message, $headers);
        */
        
        // Para este exemplo, apenas registramos no log
        error_log("EMAIL LOG: " . $message);
    }
}
// ----------- fim envio de email'

// ====================================================================================
// BLOCO PHP: Processamento do formulário (executado ao submeter o POST)
// ====================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta dos dados do formulário
    $usuario = trim($_POST['Usuario'] ?? '');
    $senha = trim($_POST['Senha'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $telefones = trim($_POST['Telefones'] ?? '');
    $permissao = 5; // Força a permissão para CORRETOR
    $funcao = "CORRETOR"; // Força a função para CORRETOR
    $acao = "/CONSULTAR/";
    $ativo = isset($_POST['Ativo']) ? 1 : 0;
    $nome = trim($_POST['Nome'] ?? '');
    $creci = trim($_POST['CRECI'] ?? '');
    
    // Validações básicas
    if (empty($usuario) || empty($senha) || empty($email) || empty($nome)) {
        $error = "Preencha todos os campos obrigatórios!";
    } else {
        try {
            // Iniciar transação
            $conn->beginTransaction();
            
            // 1. Inserir o novo usuário na tabela Usuarios
            $sql_usuario = "INSERT INTO Usuarios (Usuario, SenhaAtual, Nome, Creci, Email, Telefones, Acoes, Permissao, Funcao, Ativo, UsuarioCadastro, GrupoId, Grupo, Data) 
                           VALUES (:usuario, :senha, :nome, :creci, :email, :telefones, :acao, :permissao, :funcao, :ativo, :usuario_cadastro, :grupo_id, :grupo, NOW())";
            
            $stmt = $conn->prepare($sql_usuario);
            $stmt->execute([
                ':usuario' => $usuario,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT), // Recomendo usar hash para senha
                ':nome' => $nome,
                ':creci' => $creci,
                ':email' => $email,
                ':telefones' => $telefones,
                ':acao' => $acao,
                ':permissao' => $permissao,
                ':funcao' => $funcao,
                ':ativo' => $ativo,
                ':usuario_cadastro' => $_SESSION['Usuario'] ?? 'SISTEMA',
                ':grupo_id' => 1, // GRUPO TOCCA_ONZE
                ':grupo' => 'TOCCA_ONZE'
            ]);
            
            // 2. Obter o UserId do usuário recém-inserido
            $newUserId = $conn->lastInsertId();
            
            if (!$newUserId) {
                throw new Exception("Não foi possível obter o ID do novo usuário.");
            }
            
            // 3. Inserir a relação na tabela Usuario_Grupo
            $sql_grupo = "INSERT INTO Usuario_Grupo (UserId, ID_Grupo) VALUES (:user_id, :grupo_id)";
            $stmt_grupo = $conn->prepare($sql_grupo);
            $stmt_grupo->execute([
                ':user_id' => $newUserId,
                ':grupo_id' => 1 // GRUPO TOCCA_ONZE
            ]);
            
            // Confirmar transação
            $conn->commit();
            
            // Registrar Log
            $log_message = "Novo usuário: " . $usuario . 
                          " - Senha: " . $senha . 
                          " - Email: " . $email . 
                          " - Telefones: " . $telefones . 
                          " - Grupo: TOCCA_ONZE";
            
            sendMail($log_message);
            
            // Redirecionar para a lista de usuários
            header("Location: usrv_gestao_listar.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            $conn->rollBack();
            $error = "Erro ao cadastrar usuário: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sunny - Novo Usuário</title>
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">  
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-form {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-header {
            background-color: #343a40;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-custom {
            min-width: 100px;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #28a745;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .status-label {
            margin-left: 10px;
            font-weight: 500;
        }
        .status-ativo {
            color: #28a745;
        }
        .status-inativo {
            color: #dc3545;
        }
        .input-group.password-toggle {
            position: relative;
        }
        .input-group.password-toggle .form-control {
            padding-right: 40px;
        }
        .input-group.password-toggle .input-group-append {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            z-index: 5;
            display: flex;
            align-items: center;
        }
        .input-group.password-toggle .input-group-text {
            background-color: transparent;
            border: none;
            padding: 0.375rem 0.75rem;
            cursor: pointer;
            color: #888;
        }
        .input-group.password-toggle .input-group-text:hover {
            color: #333;
        }
        .fixed-value {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="card card-form mb-4">
                    <div class="card-header form-header">
                        <h4 class="mb-0"><i class="fas fa-user-plus mr-2"></i>Novo Usuário</h4>
                    </div>
                    
                    <div class="card-body">
                        <form method="post" action="usr_novo.php">
                            <div class="form-group">
                                <label for="Nome" class="required-field">Nome Completo</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-signature"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="Nome" name="Nome" 
                                           placeholder="Digite o nome completo do usuário" required 
                                           value="<?php echo htmlspecialchars($_POST['Nome'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="Usuario" class="required-field">Usuário</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="Usuario" name="Usuario" 
                                           placeholder="Usuário será gerado automaticamente"
                                           value="<?php echo htmlspecialchars($_POST['Usuario'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="Senha" class="required-field">Senha</label>
                                <div class="input-group password-toggle">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    </div>
                                    <input type="password" class="form-control" id="Senha" name="Senha" 
                                           placeholder="Senha será gerada automaticamente"
                                           value="<?php echo htmlspecialchars($_POST['Senha'] ?? ''); ?>" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="CRECI">CRECI</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-id-card-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="CRECI" name="CRECI" 
                                           placeholder="Digite o CRECI (opcional)"
                                           value="<?php echo htmlspecialchars($_POST['CRECI'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="Email" class="required-field">Email</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" id="Email" name="Email" 
                                           placeholder="Digite o email" required
                                           value="<?php echo htmlspecialchars($_POST['Email'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="Telefones">Telefones</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="Telefones" name="Telefones" 
                                           placeholder="Ex: (XX) XXXX-XXXX, (XX) XXXX-XXXX"
                                           value="<?php echo htmlspecialchars($_POST['Telefones'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="required-field">Grupo</label>
                                <input type="text" class="form-control fixed-value" value="TOCCA_ONZE" readonly>
                                <input type="hidden" name="ID_Grupo" value="1">
                            </div>

                            <div class="form-group">
                                <label class="required-field">Nível de Permissão</label>
                                <input type="text" class="form-control fixed-value" value="CORRETOR" readonly>
                                <input type="hidden" name="Permissao" value="5">
                            </div>

                            <div class="form-group">
                                <label class="required-field">Função</label>
                                <input type="text" class="form-control fixed-value" id="Funcao" name="Funcao" value="CORRETOR" readonly>
                            </div>

                            <div class="form-group">
                                <label class="required-field">Ações Permitidas</label>
                                <textarea class="form-control fixed-value" id="Acao" name="Acao" rows="3" readonly>/CONSULTAR/</textarea>
                            </div>

                            <div class="form-group">
                                <label>Status do Usuário</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="Ativo" name="Ativo" value="1" 
                                               <?php echo (!isset($_POST['Ativo']) || $_POST['Ativo'] == '1') ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="status-label status-ativo">ATIVO</span>
                                </div>
                            </div>

                            <div class="form-group text-right mt-4">
                                <button type="submit" class="btn btn-primary btn-custom">
                                    <i class="fas fa-save mr-1"></i> Salvar
                                </button>
                                <a href="usr_listar.php" class="btn btn-secondary btn-custom">
                                    <i class="fas fa-times mr-1"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center text-muted small">
                    Sunny &copy; <?php echo date('Y'); ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Função para remover acentos e caracteres especiais
        function removerAcentos(str) {
            return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }

        // Função para gerar o usuário
        function gerarUsuario(nomeCompleto) {
            if (!nomeCompleto) return "";
            const partesNome = removerAcentos(nomeCompleto).toLowerCase().split(" ");
            let usuario = partesNome[0];
            for (let i = 1; i < partesNome.length; i++) {
                if (partesNome[i].length > 0) {
                    usuario += partesNome[i].charAt(0);
                }
            }
            return usuario;
        }

        // Função para gerar a senha
        function gerarSenha(primeiroNome) {
            if (!primeiroNome) return "";
            const nomeBase = removerAcentos(primeiroNome).toLowerCase();
            const digitosAleatorios = Math.floor(Math.random() * 99) + 1;
            const digitosFormatados = digitosAleatorios < 10 ? "0" + digitosAleatorios : "" + digitosAleatorios;
            return `${nomeBase}@tv${digitosFormatados}`;
        }

        // Executa quando o DOM estiver completamente carregado
        $(document).ready(function() {
            // Lógica para o switch de Ativo/Inativo
            $('#Ativo').change(function() {
                if($(this).is(':checked')) {
                    $('.status-label').removeClass('status-inativo').addClass('status-ativo').text('ATIVO');
                } else {
                    $('.status-label').removeClass('status-ativo').addClass('status-inativo').text('INATIVO');
                }
            });

            // Lógica para o "olho" de mostrar/esconder senha
            $('#togglePassword').click(function() {
                const type = $('#Senha').attr('type') === 'password' ? 'text' : 'password';
                $('#Senha').attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            // Gera usuário e senha automaticamente ao digitar o nome
            $('#Nome').on('input', function() {
                const nomeCompleto = $(this).val().trim();
                const primeiroNome = nomeCompleto.split(" ")[0] || "";
                $('#Usuario').val(gerarUsuario(nomeCompleto));
                $('#Senha').val(gerarSenha(primeiroNome));
            });
        });
    </script>
</body>
</html>
<?php
// Fechar conexão
$conn = null;
?>
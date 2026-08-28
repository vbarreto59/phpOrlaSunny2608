<?php
// usrv_gestao_novo_usuario.php
session_start();
require_once 'conn.php';
require_once 'send_email.php';


// Função para tratar valores NULL/Empty
function safeValue($value, $defaultValue = '') {
    return (is_null($value) || $value === '') ? $defaultValue : $value;
}

// Obter diretorias para o dropdown
$stmtDiretorias = $conn->query("SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria");
$diretorias = $stmtDiretorias->fetchAll(PDO::FETCH_ASSOC);

// Obter todas as gerências para JavaScript
$stmtGerencias = $conn->query("SELECT GerenciaID, DiretoriaID, NomeGerencia FROM gerencias ORDER BY NomeGerencia");
$gerencias = $stmtGerencias->fetchAll(PDO::FETCH_ASSOC);

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta dos dados do formulário
    $usuario = $_POST['Usuario'] ?? '';
    $senha = $_POST['Senha'] ?? '';
    $email = $_POST['Email'] ?? '';
    $telefones = $_POST['Telefones'] ?? '';
    $nome = $_POST['Nome'] ?? '';
    $creci = $_POST['CRECI'] ?? '';
    $diretoriaID = $_POST['DiretoriaID'] ?? '';
    $gerenciaID = $_POST['GerenciaID'] ?? '';
    $ativo = isset($_POST['Ativo']) ? 1 : 0;
    
    // Valores fixos
    $permissao = 5;
    $funcao = 'CORRETOR';
    $acao = '/CONSULTAR/';
    $grupoId = 2;
    $grupo = 'TOCCA_ONZE';
    $idEmp = 2;
    
    // Obter nomes da diretoria e gerência
    $nomeDiretoria = '';
    $nomeGerencia = '';
    
    if (!empty($diretoriaID)) {
        $stmt = $conn->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
        $stmt->execute([$diretoriaID]);
        if ($row = $stmt->fetch()) {
            $nomeDiretoria = $row['NomeDiretoria'];
        }
    }
    
    if (!empty($gerenciaID)) {
        $stmt = $conn->prepare("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = ?");
        $stmt->execute([$gerenciaID]);
        if ($row = $stmt->fetch()) {
            $nomeGerencia = $row['NomeGerencia'];
        }
    }
    
    // Determinar valores NULL para os parâmetros
    $parDiretoriaID = !empty($diretoriaID) ? (int)$diretoriaID : null;
    $parGerenciaID = !empty($gerenciaID) ? (int)$gerenciaID : null;
    
    try {
        $conn->beginTransaction();
        
        // 1. Inserir o novo usuário
        $sql = "INSERT INTO usuarios (
            Usuario, SenhaAtual, Nome, Creci, Email, Telefones, Acoes, 
            Permissao, Funcao, Ativo, UsuarioCadastro, GrupoId, Grupo, 
            DiretoriaID, Diretoria, GerenciaID, Gerencia, IdEmp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $usuario,
            $senha,
            $nome,
            $creci,
            $email,
            $telefones,
            $acao,
            $permissao,
            $funcao,
            $ativo,
            $_SESSION['Usuario'] ?? 'SISTEMA',
            $grupoId,
            $grupo,
            $parDiretoriaID,
            $nomeDiretoria,
            $parGerenciaID,
            $nomeGerencia,
            $idEmp
        ]);
        
        // 2. Obter o UserId do usuário recém-inserido
        $newUserId = $conn->lastInsertId();
        
        // 3. Inserir a relação na tabela Usuario_Grupo
        $sqlGrupo = "INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)";
        $stmtGrupo = $conn->prepare($sqlGrupo);
        $stmtGrupo->execute([$newUserId, 1]); // GRUPO TOCCA_ONZE
        
        $conn->commit();
        
        // Enviar email de notificação
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            $assunto = "SV-USR.NOVO-" . strtoupper($_SESSION['Usuario'] ?? 'SISTEMA') . " - " . 
                      $_SERVER['REMOTE_ADDR'] . " - " . date('Y-m-d') . " - " . date('H:i:s');
            
            $mensagem = "Novo usuário: $usuario\n";
            $mensagem .= "Senha: $senha\n";
            $mensagem .= "Email: $email\n";
            $mensagem .= "Telefones: $telefones\n";
            $mensagem .= "Grupo: TOCCA_ONZE\n";
            $mensagem .= "Diretoria: $nomeDiretoria\n";
            $mensagem .= "Gerência: $nomeGerencia\n";
            $mensagem .= "Nome: $nome\n";
            $mensagem .= "CRECI: $creci\n";
            
            // Usar a função de envio de email do SGVendas
            enviarEmailSGVendas('valterpb@hotmail.com', $assunto, nl2br($mensagem));
            
            // Enviar também para o email do usuário se estiver configurado
            if (!empty($email)) {
                $assuntoUsuario = "Bem-vindo ao Sunny Imobiliária - Sua conta foi criada";
                $mensagemUsuario = "Olá $nome,<br><br>";
                $mensagemUsuario .= "Sua conta foi criada com sucesso!<br><br>";
                $mensagemUsuario .= "<strong>Usuário:</strong> $usuario<br>";
                $mensagemUsuario .= "<strong>Senha:</strong> $senha<br><br>";
                $mensagemUsuario .= "Acesse o sistema em: http://" . $_SERVER['HTTP_HOST'] . "<br><br>";
                $mensagemUsuario .= "Atenciosamente,<br>Equipe Sunny Imobiliária";
                
                enviarEmailSGVendas($email, $assuntoUsuario, $mensagemUsuario);
            }
        }
        
        header('Location: usrv_gestao_listar2.php');
        exit;
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        die("Erro ao cadastrar usuário: " . $e->getMessage());
    }
}

// Preparar dados para JavaScript (gerências agrupadas por diretoria)
$gerenciasPorDiretoria = [];
foreach ($gerencias as $gerencia) {
    $diretoriaId = $gerencia['DiretoriaID'];
    if (!isset($gerenciasPorDiretoria[$diretoriaId])) {
        $gerenciasPorDiretoria[$diretoriaId] = [];
    }
    $gerenciasPorDiretoria[$diretoriaId][] = [
        'id' => $gerencia['GerenciaID'],
        'nome' => $gerencia['NomeGerencia']
    ];
}

$allGerenciasJSON = json_encode($gerenciasPorDiretoria, JSON_UNESCAPED_UNICODE);
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
        body { background-color: #f8f9fa; }
        .card-form {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-header {
            background-color: #343a40;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-custom { min-width: 100px; }
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
        .status-ativo { color: #28a745; }
        .status-inativo { color: #dc3545; }
        .input-group.password-toggle { position: relative; }
        .input-group.password-toggle .form-control { padding-right: 40px; }
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
        .input-group.password-toggle .input-group-text:hover { color: #333; }
        .fixed-value {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card card-form mb-4">
                    <div class="card-header form-header">
                        <h4 class="mb-0"><i class="fas fa-user-plus mr-2"></i>Novo Usuário</h4>
                    </div>
                    
                    <div class="card-body">
                        <form method="post" action="usrv_gestao_novo_usuario.php" id="formNovoUsuario">
                            <div class="form-group">
                                <label for="Nome" class="required-field">Nome Completo</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-signature"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="Nome" name="Nome" 
                                           placeholder="Digite o nome completo do usuário" required
                                           autocomplete="off" autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="Usuario" class="required-field">Usuário</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="Usuario" name="Usuario" 
                                           placeholder="Usuário será gerado automaticamente" required
                                           readonly autocomplete="username">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="Senha" class="required-field">Senha</label>
                                <div class="input-group password-toggle">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    </div>
                                    <input type="password" class="form-control" id="Senha" name="Senha" 
                                           placeholder="Senha será gerada automaticamente" required
                                           readonly autocomplete="new-password">
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
                                           autocomplete="off">
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
                                           autocomplete="email">
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
                                           autocomplete="tel">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="DiretoriaID" class="required-field">Diretoria</label>
                                <select class="form-control" id="DiretoriaID" name="DiretoriaID" required>
                                    <option value="">Selecione uma diretoria...</option>
                                    <?php foreach ($diretorias as $diretoria): ?>
                                        <option value="<?= htmlspecialchars($diretoria['DiretoriaID']) ?>">
                                            <?= htmlspecialchars($diretoria['NomeDiretoria']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="GerenciaID" class="required-field">Gerência</label>
                                <select class="form-control" id="GerenciaID" name="GerenciaID" required>
                                    <option value="">Selecione uma gerência...</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="required-field">Grupo</label>
                                <input type="text" class="form-control fixed-value" value="TOCCA_ONZE" readonly>
                                <input type="hidden" name="ID_Grupo" value="2">
                            </div>

                            <div class="form-group">
                                <label class="required-field">Nível de Permissão</label>
                                <input type="text" class="form-control fixed-value" value="CORRETOR" readonly>
                                <input type="hidden" name="Permissao" value="5">
                            </div>

                            <div class="form-group">
                                <label class="required-field">Função</label>
                                <input type="text" class="form-control fixed-value" id="Funcao" name="Funcao" 
                                       value="CORRETOR" readonly>
                            </div>

                            <div class="form-group">
                                <label class="required-field">Ações Permitidas</label>
                                <textarea class="form-control fixed-value" id="Acao" name="Acao" rows="3" 
                                          readonly>/CONSULTAR/</textarea>
                            </div>

                            <div class="form-group">
                                <label>Status do Usuário</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch">
                                        <input type="checkbox" id="Ativo" name="Ativo" value="1" checked>
                                        <span class="slider"></span>
                                    </label>
                                    <span class="status-label status-ativo">ATIVO</span>
                                </div>
                            </div>

                            <div class="form-group text-right mt-4">
                                <button type="submit" class="btn btn-primary btn-custom" id="btnSalvar">
                                    <i class="fas fa-save mr-1"></i> Salvar
                                </button>
                                <a href="usrv_gestao_listar2.php" class="btn btn-secondary btn-custom">
                                    <i class="fas fa-times mr-1"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center text-muted small">
                    Sunny &copy; <?= date('Y') ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Dados pré-carregados das gerências
        var allGerencias = <?= $allGerenciasJSON ?>;

        // Função para atualizar gerências baseado na diretoria selecionada
        function updateGerencias() {
            var diretoriaId = $('#DiretoriaID').val();
            var gerenciaSelect = $('#GerenciaID');
            
            // Limpa as opções anteriores
            gerenciaSelect.html('<option value="">Selecione uma gerência...</option>');
            
            if (diretoriaId && allGerencias[diretoriaId]) {
                allGerencias[diretoriaId].forEach(function(gerencia) {
                    gerenciaSelect.append('<option value="' + gerencia.id + '">' + 
                                         gerencia.nome + '</option>');
                });
            }
        }

        // Função para remover acentos e caracteres especiais
        function removerAcentos(str) {
            return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        }

        // Função para gerar o usuário
        function gerarUsuario(nomeCompleto) {
            if (!nomeCompleto) return "";
            const partesNome = removerAcentos(nomeCompleto).toLowerCase().split(" ");
            if (partesNome.length === 0) return "";
            
            let usuario = partesNome[0];
            for (let i = 1; i < partesNome.length && i <= 2; i++) {
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
            // Evento para atualizar gerências quando a diretoria mudar
            $('#DiretoriaID').change(updateGerencias);

            // Lógica para o switch de Ativo/Inativo
            $('#Ativo').change(function() {
                const $label = $('.status-label');
                if($(this).is(':checked')) {
                    $label.removeClass('status-inativo').addClass('status-ativo').text('ATIVO');
                } else {
                    $label.removeClass('status-ativo').addClass('status-inativo').text('INATIVO');
                }
            });

            // Lógica para o "olho" de mostrar/esconder senha
            $('#togglePassword').click(function() {
                const $senha = $('#Senha');
                const type = $senha.attr('type') === 'password' ? 'text' : 'password';
                $senha.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            // Gera usuário e senha automaticamente ao digitar o nome
            $('#Nome').on('input', function() {
                const nomeCompleto = $(this).val().trim();
                const primeiroNome = nomeCompleto.split(" ")[0] || "";
                $('#Usuario').val(gerarUsuario(nomeCompleto));
                $('#Senha').val(gerarSenha(primeiroNome));
            });

            // Prevenir duplo clique no botão salvar
            $('#formNovoUsuario').on('submit', function() {
                const $btnSalvar = $('#btnSalvar');
                $btnSalvar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Salvando...');
                
                const diretoria = $('#DiretoriaID').val();
                const gerencia = $('#GerenciaID').val();
                
                if (!diretoria) {
                    alert('Por favor, selecione uma diretoria.');
                    $('#DiretoriaID').focus();
                    $btnSalvar.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Salvar');
                    return false;
                }
                
                if (!gerencia) {
                    alert('Por favor, selecione uma gerência.');
                    $('#GerenciaID').focus();
                    $btnSalvar.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Salvar');
                    return false;
                }
                
                // Validação de email
                const email = $('#Email').val();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Por favor, digite um email válido.');
                    $('#Email').focus();
                    $btnSalvar.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Salvar');
                    return false;
                }
                
                return true;
            });

            // Foco no campo Nome ao carregar a página
            $('#Nome').focus();
        });
    </script>
</body>
</html>
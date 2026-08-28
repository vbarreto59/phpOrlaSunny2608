<?php
// diretoria_create.php
session_start();
require_once 'conn.php';
require_once 'usr_acoes.php';
require_once 'gestao_header.inc';



// Configurar headers para UTF-8
header('Content-Type: text/html; charset=utf-8');

// =========================================================
// PROCESSAMENTO DO FORMULÁRIO
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Pega os dados do formulário
        $NomeDiretoria = $_POST['NomeDiretoria'] ?? '';
        $NomeDiretor = $_POST['NomeDiretor'] ?? '';
        $TelefoneDiretor = $_POST['TelefoneDiretor'] ?? '';
        $EstadoDiretoria = $_POST['EstadoDiretoria'] ?? '';
        $UserId = $_POST['UserId'] ?? '';
        
        // Validação básica
        if (empty($NomeDiretoria) || empty($NomeDiretor) || empty($TelefoneDiretor) || empty($EstadoDiretoria)) {
            throw new Exception("Preencha todos os campos obrigatórios.");
        }
        
        // Valores padrão para o usuário
        $sUserId = null;
        $sUsuario = null;
        $sNomeUsuario = null;
        
        // Se um UserId foi selecionado, busca os dados do usuário na tabela Usuarios
        if (!empty($UserId)) {
            $sqlUsuario = "SELECT Usuario, Nome FROM usuarios WHERE UserId = ?";
            $stmtUsuario = $conn->prepare($sqlUsuario);
            $stmtUsuario->execute([$UserId]);
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                $sUserId = $UserId;
                $sUsuario = $usuario['Usuario'];
                $sNomeUsuario = $usuario['Nome'];
            }
        }
        
        // Prepara o comando SQL de INSERT
        $sql = "INSERT INTO diretorias 
                (NomeDiretoria, NomeDiretor, TelefoneDiretor, EstadoDiretoria, UserId, Usuario, Nome) 
                VALUES 
                (:NomeDiretoria, :NomeDiretor, :TelefoneDiretor, :EstadoDiretoria, :UserId, :Usuario, :Nome)";
        
        $stmt = $conn->prepare($sql);
        
        // Bind dos parâmetros
        $stmt->bindParam(':NomeDiretoria', $NomeDiretoria);
        $stmt->bindParam(':NomeDiretor', $NomeDiretor);
        $stmt->bindParam(':TelefoneDiretor', $TelefoneDiretor);
        $stmt->bindParam(':EstadoDiretoria', $EstadoDiretoria);
        $stmt->bindParam(':UserId', $sUserId, PDO::PARAM_INT);
        $stmt->bindParam(':Usuario', $sUsuario);
        $stmt->bindParam(':Nome', $sNomeUsuario);
        
        // Executa a inserção
        if ($stmt->execute()) {
            // Redireciona para a lista de diretorias
            header('Location: diretoria_list.php');
            exit;
        } else {
            throw new Exception("Erro ao cadastrar diretoria.");
        }
        
    } catch(PDOException $e) {
        $error_message = "Erro de banco de dados: " . $e->getMessage();
        error_log($error_message);
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// =========================================================
// BUSCAR USUÁRIOS PARA O SELECT
// =========================================================

try {
    $sqlUsuarios = "SELECT UserId, Usuario, Nome FROM usuarios ORDER BY Nome";
    $stmtUsuarios = $conn->prepare($sqlUsuarios);
    $stmtUsuarios->execute();
    $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $usuarios = [];
    error_log("Erro ao buscar usuários: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Diretoria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/gestao_estilo.css">
    <style>
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-top: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        select option[value=""] {
            color: #6c757d;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-sun me-2"></i>SunnyImob.
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="gestao_painel2.php"><i class="fas fa-home me-1"></i> Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-cog me-1"></i> Configurações</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestao_logout.php"><i class="fas fa-sign-out-alt me-1"></i> Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <section class="welcome-section text-center">
        <div class="container">
            <h1 class="display-4 mb-2">Tocca Onze</h1>
            <p class="lead">Gerencie as operações de gestão e vendas</p>
        </div>
    </section>

    <div class="container">
        <h2 class="text-center mb-4">Cadastrar Nova Diretoria</h2>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="card p-4">
            <form method="post" action="diretoria_create.php" onsubmit="return validarFormulario()">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="NomeDiretoria" class="form-label">Nome da Diretoria *</label>
                        <input type="text" name="NomeDiretoria" id="NomeDiretoria" 
                               value="<?php echo isset($_POST['NomeDiretoria']) ? htmlspecialchars($_POST['NomeDiretoria']) : ''; ?>"
                               required class="form-control" placeholder="Digite o nome da diretoria">
                        <div class="invalid-feedback" id="NomeDiretoriaError"></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="NomeDiretor" class="form-label">Nome do Diretor *</label>
                        <input type="text" name="NomeDiretor" id="NomeDiretor" 
                               value="<?php echo isset($_POST['NomeDiretor']) ? htmlspecialchars($_POST['NomeDiretor']) : ''; ?>"
                               required class="form-control" placeholder="Digite o nome do diretor">
                        <div class="invalid-feedback" id="NomeDiretorError"></div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="TelefoneDiretor" class="form-label">Telefone do Diretor *</label>
                        <input type="text" name="TelefoneDiretor" id="TelefoneDiretor" 
                               value="<?php echo isset($_POST['TelefoneDiretor']) ? htmlspecialchars($_POST['TelefoneDiretor']) : ''; ?>"
                               required class="form-control" placeholder="(00) 00000-0000">
                        <div class="invalid-feedback" id="TelefoneDiretorError"></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="EstadoDiretoria" class="form-label">Estado da Diretoria *</label>
                        <select name="EstadoDiretoria" id="EstadoDiretoria" required class="form-control">
                            <option value="">Selecione...</option>
                            <option value="Ativo" <?php echo (isset($_POST['EstadoDiretoria']) && $_POST['EstadoDiretoria'] == 'Ativo') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="Inativo" <?php echo (isset($_POST['EstadoDiretoria']) && $_POST['EstadoDiretoria'] == 'Inativo') ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                        <div class="invalid-feedback" id="EstadoDiretoriaError"></div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="mb-3">
                    <label class="form-label">Associar Usuário</label>
                    <p class="text-muted small">Selecione um usuário para associar à diretoria (opcional)</p>
                    
                    <div class="mb-3">
                        <label for="searchUserInput" class="form-label">Buscar Usuário</label>
                        <input type="text" id="searchUserInput" class="form-control" 
                               placeholder="Digite nome ou usuário para filtrar...">
                    </div>

                    <div class="mb-3">
                        <label for="UserId" class="form-label">Usuário</label>
                        <select name="UserId" id="UserId" class="form-control">
                            <option value="">Selecione um Usuário (Opcional)</option>
                            <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?php echo $usuario['UserId']; ?>" 
                                    data-search-text="<?php echo strtolower(htmlspecialchars($usuario['Nome'] . ' ' . $usuario['Usuario'])); ?>"
                                    <?php echo (isset($_POST['UserId']) && $_POST['UserId'] == $usuario['UserId']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['Nome'] . ' (' . $usuario['Usuario'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" class="btn btn-primary me-md-2">
                        <i class="fas fa-save me-1"></i> Salvar Diretoria
                    </button>
                    <a href="diretoria_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Voltar para Lista
                    </a>
                    <a href="javascript:window.close()" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times me-1"></i> Fechar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <footer class="text-center mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-sun me-2"></i>SunnyImob</h5>
                    <p>Valter Barreto</p>
                </div>
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> Todos os direitos reservados</p>
                    <div class="social-icons">
                        <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filtro de busca para usuários
        document.getElementById('searchUserInput').addEventListener('keyup', function() {
            var filter = this.value.toLowerCase();
            var options = document.getElementById('UserId').options;
            
            // Mostra todas as opções se o filtro estiver vazio
            if (filter === '') {
                for (var i = 0; i < options.length; i++) {
                    options[i].style.display = '';
                }
                return;
            }
            
            // Filtra as opções
            for (var i = 0; i < options.length; i++) {
                var optionText = options[i].getAttribute('data-search-text');
                if (optionText) {
                    if (optionText.indexOf(filter) > -1) {
                        options[i].style.display = '';
                    } else {
                        options[i].style.display = 'none';
                    }
                }
            }
            
            // Seleciona a primeira opção visível (exceto a primeira que é o placeholder)
            for (var i = 1; i < options.length; i++) {
                if (options[i].style.display !== 'none') {
                    document.getElementById('UserId').selectedIndex = i;
                    break;
                }
            }
        });
        
        // Máscara para telefone
        document.getElementById('TelefoneDiretor').addEventListener('input', function(e) {
            var value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 10) {
                // Formato (00) 0000-0000
                value = value.replace(/^(\d{2})(\d{4})(\d{4})$/, '($1) $2-$3');
            } else {
                // Formato (00) 00000-0000
                value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            }
            
            e.target.value = value;
        });
        
        // Validação do formulário
        function validarFormulario() {
            let isValid = true;
            
            // Limpa mensagens de erro anteriores
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            document.querySelectorAll('.invalid-feedback').forEach(el => {
                el.textContent = '';
            });
            
            // Valida NomeDiretoria
            const nomeDiretoria = document.getElementById('NomeDiretoria').value.trim();
            if (!nomeDiretoria) {
                document.getElementById('NomeDiretoria').classList.add('is-invalid');
                document.getElementById('NomeDiretoriaError').textContent = 'Campo obrigatório';
                isValid = false;
            }
            
            // Valida NomeDiretor
            const nomeDiretor = document.getElementById('NomeDiretor').value.trim();
            if (!nomeDiretor) {
                document.getElementById('NomeDiretor').classList.add('is-invalid');
                document.getElementById('NomeDiretorError').textContent = 'Campo obrigatório';
                isValid = false;
            }
            
            // Valida TelefoneDiretor
            const telefone = document.getElementById('TelefoneDiretor').value.trim();
            if (!telefone) {
                document.getElementById('TelefoneDiretor').classList.add('is-invalid');
                document.getElementById('TelefoneDiretorError').textContent = 'Campo obrigatório';
                isValid = false;
            }
            
            // Valida EstadoDiretoria
            const estado = document.getElementById('EstadoDiretoria').value;
            if (!estado) {
                document.getElementById('EstadoDiretoria').classList.add('is-invalid');
                document.getElementById('EstadoDiretoriaError').textContent = 'Selecione um estado';
                isValid = false;
            }
            
            return isValid;
        }
        
        // Previne o envio do formulário ao pressionar Enter no campo de busca
        document.getElementById('searchUserInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        
        // Foco no primeiro campo ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('NomeDiretoria').focus();
        });
    </script>
</body>
</html>
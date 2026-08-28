<?php
// gerencia_update.php
session_start();
require_once 'conn.php';
require_once 'usr_acoes.php';
require_once 'gestao_header.inc';

header('Content-Type: text/html; charset=utf-8');

$GerenciaID = $_GET['id'] ?? $_POST['GerenciaID'] ?? 0;
$GerenciaID = (int)$GerenciaID;

// --- Lógica de Processamento mantida conforme seu original ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $DiretoriaID = $_POST['DiretoriaID'] ?? '';
        $NomeGerencia = $_POST['NomeGerencia'] ?? '';
        $NomeGerente = $_POST['NomeGerente'] ?? '';
        $TelefoneGerente = $_POST['TelefoneGerente'] ?? '';
        $UserId = $_POST['UserId'] ?? '';
        $GerenciaID = (int)($_POST['GerenciaID'] ?? 0);
        
        if (empty($DiretoriaID) || empty($NomeGerencia) || empty($NomeGerente)) {
            throw new Exception("Preencha todos os campos obrigatórios.");
        }
        
        $sUserId = null; $sUsuario = null; $sNomeUsuario = null;
        
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
        
        $sql = "UPDATE gerencias SET DiretoriaID = :DiretoriaID, NomeGerencia = :NomeGerencia, 
                NomeGerente = :NomeGerente, TelefoneGerente = :TelefoneGerente, UserId = :UserId, 
                Usuario = :Usuario, Nome = :Nome WHERE GerenciaID = :GerenciaID";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':DiretoriaID', $DiretoriaID, PDO::PARAM_INT);
        $stmt->bindParam(':NomeGerencia', $NomeGerencia);
        $stmt->bindParam(':NomeGerente', $NomeGerente);
        $stmt->bindParam(':TelefoneGerente', $TelefoneGerente);
        $stmt->bindParam(':UserId', $sUserId, PDO::PARAM_INT);
        $stmt->bindParam(':Usuario', $sUsuario);
        $stmt->bindParam(':Nome', $sNomeUsuario);
        $stmt->bindParam(':GerenciaID', $GerenciaID, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            header('Location: gerencia_list.php?msg=success');
            exit;
        }
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// --- Busca de dados mantida ---
try {
    if ($GerenciaID > 0) {
        $stmt = $conn->prepare("SELECT * FROM gerencias WHERE GerenciaID = ?");
        $stmt->execute([$GerenciaID]);
        $gerencia = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$gerencia) throw new Exception("Registro não encontrado.");
    } else {
        throw new Exception("ID inválido.");
    }
    
    $diretorias = $conn->query("SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria")->fetchAll(PDO::FETCH_ASSOC);
    $usuarios = $conn->query("SELECT UserId, Usuario, Nome FROM usuarios ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    die($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SunnyImob | Editar Gerência</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --dark-blue: #1e293b;
            --light-bg: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: #334155;
        }

        .navbar { background: var(--dark-blue); padding: 1rem 0; }
        .navbar-brand { font-weight: 700; color: #fff !important; }

        .header-edit {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 40px 0 80px;
            margin-bottom: -60px;
        }

        .card-form {
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header-custom {
            background: #f1f5f9;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #1e293b;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .input-group-text {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
        }

        .required::after {
            content: " *";
            color: #ef4444;
        }

        .btn-update {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2);
        }

        .btn-cancel {
            padding: 12px 25px;
            font-weight: 500;
            border-radius: 8px;
            color: #64748b;
        }

        footer { padding: 40px 0; color: #94a3b8; font-size: 0.9rem; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-sun me-2 text-warning"></i>SunnyImob.</a>
        </div>
    </nav>

    <div class="header-edit text-center">
        <div class="container">
            <h1 class="h2 fw-bold">Configuração de Gerência</h1>
            <p class="opacity-75">Atualize as informações administrativas do setor</p>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-3"></i>
                    <div><?php echo $error_message; ?></div>
                </div>
                <?php endif; ?>

                <div class="card card-form">
                    <div class="card-header-custom d-flex align-items-center">
                        <i class="fas fa-edit me-2 text-primary"></i>
                        Editando Registro #<?php echo $gerencia['GerenciaID']; ?>
                    </div>
                    
                    <form method="post" action="gerencia_update.php" onsubmit="return validarFormulario()" class="p-4 p-md-5">
                        <input type="hidden" name="GerenciaID" value="<?php echo $gerencia['GerenciaID']; ?>">
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="DiretoriaID" class="form-label required">Diretoria</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                                    <select name="DiretoriaID" id="DiretoriaID" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($diretorias as $dir): ?>
                                            <option value="<?php echo $dir['DiretoriaID']; ?>" <?php echo ($dir['DiretoriaID'] == $gerencia['DiretoriaID']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dir['NomeDiretoria']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="NomeGerencia" class="form-label required">Nome da Setor/Gerência</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                    <input type="text" name="NomeGerencia" id="NomeGerencia" class="form-control" 
                                           value="<?php echo htmlspecialchars($gerencia['NomeGerencia']); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="NomeGerente" class="form-label required">Nome do Responsável</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                    <input type="text" name="NomeGerente" id="NomeGerente" class="form-control" 
                                           value="<?php echo htmlspecialchars($gerencia['NomeGerente']); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="TelefoneGerente" class="form-label">Telefone de Contato</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="TelefoneGerente" id="TelefoneGerente" class="form-control" 
                                           value="<?php echo htmlspecialchars($gerencia['TelefoneGerente']); ?>" placeholder="(00) 00000-0000">
                                </div>
                            </div>

                            <div class="col-12 mt-5">
                                <hr class="text-light-emphasis">
                                <h6 class="text-dark fw-bold mb-3"><i class="fas fa-link me-2"></i>Vincular Conta de Acesso</h6>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Filtrar Usuário</label>
                                <input type="text" id="searchUserInput" class="form-control form-control-sm" placeholder="Pesquisar nome...">
                            </div>

                            <div class="col-md-7">
                                <label for="UserId" class="form-label">Usuário do Sistema</label>
                                <select name="UserId" id="UserId" class="form-select">
                                    <option value="">Nenhum usuário vinculado</option>
                                    <?php foreach ($usuarios as $usr): ?>
                                        <option value="<?php echo $usr['UserId']; ?>" 
                                                data-search-text="<?php echo strtolower($usr['Nome'].' '.$usr['Usuario']); ?>"
                                                <?php echo ($usr['UserId'] == $gerencia['UserId']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($usr['Nome']." (".$usr['Usuario'].")"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
                            <a href="gerencia_list.php" class="btn btn-cancel">
                                <i class="fas fa-arrow-left me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-update">
                                <i class="fas fa-check-circle me-2"></i>Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center">
        <p>&copy; <?php echo date('Y'); ?> SunnyImob - Sistema Interno de Gestão</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Lógica de filtro e máscara mantida e otimizada
        document.getElementById('searchUserInput').addEventListener('input', function() {
            let filter = this.value.toLowerCase();
            let options = document.getElementById('UserId').options;
            for (let opt of options) {
                let text = opt.getAttribute('data-search-text') || '';
                opt.style.display = (text.includes(filter) || opt.value === "") ? '' : 'none';
            }
        });

        document.getElementById('TelefoneGerente').addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });

        function validarFormulario() {
            const campos = ['NomeGerencia', 'NomeGerente', 'DiretoriaID'];
            let valid = true;
            campos.forEach(id => {
                const el = document.getElementById(id);
                if (!el.value.trim()) {
                    el.classList.add('is-invalid');
                    valid = false;
                } else {
                    el.classList.remove('is-invalid');
                }
            });
            return valid;
        }
    </script>
</body>
</html>
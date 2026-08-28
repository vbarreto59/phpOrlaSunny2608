<?php
// gerencia_create.php
session_start();
require_once 'conn.php';
require_once 'usr_acoes.php';
require_once 'gestao_header.inc';

header('Content-Type: text/html; charset=utf-8');

// =========================================================
// PROCESSAMENTO DO FORMULÁRIO (INSERT)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $DiretoriaID = $_POST['DiretoriaID'] ?? '';
        $NomeGerencia = $_POST['NomeGerencia'] ?? '';
        $NomeGerente = $_POST['NomeGerente'] ?? '';
        $TelefoneGerente = $_POST['TelefoneGerente'] ?? '';
        $UserId = $_POST['UserId'] ?? null;

        if (empty($DiretoriaID) || empty($NomeGerencia) || empty($NomeGerente)) {
            throw new Exception("Preencha todos os campos obrigatórios.");
        }

        $sUsuario = null;
        $sNomeUsuario = null;

        // Se um UserId foi selecionado, busca os dados para denormalização (conforme seu padrão)
        if (!empty($UserId)) {
            $stmtUser = $conn->prepare("SELECT Usuario, Nome FROM usuarios WHERE UserId = ?");
            $stmtUser->execute([$UserId]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($userRow) {
                $sUsuario = $userRow['Usuario'];
                $sNomeUsuario = $userRow['Nome'];
            }
        }

        $sql = "INSERT INTO gerencias (DiretoriaID, NomeGerencia, NomeGerente, TelefoneGerente, UserId, Usuario, Nome) 
                VALUES (:DiretoriaID, :NomeGerencia, :NomeGerente, :TelefoneGerente, :UserId, :Usuario, :Nome)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':DiretoriaID', $DiretoriaID, PDO::PARAM_INT);
        $stmt->bindParam(':NomeGerencia', $NomeGerencia);
        $stmt->bindParam(':NomeGerente', $NomeGerente);
        $stmt->bindParam(':TelefoneGerente', $TelefoneGerente);
        $stmt->bindParam(':UserId', $UserId);
        $stmt->bindParam(':Usuario', $sUsuario);
        $stmt->bindParam(':Nome', $sNomeUsuario);

        if ($stmt->execute()) {
            header('Location: gerencia_list.php?msg=created');
            exit;
        }
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

// =========================================================
// BUSCAR DADOS PARA OS SELECTS
// =========================================================
try {
    $diretorias = $conn->query("SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria")->fetchAll(PDO::FETCH_ASSOC);
    $usuarios = $conn->query("SELECT UserId, Usuario, Nome FROM usuarios ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SunnyImob | Nova Gerência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #0d6efd; --dark-blue: #1e293b; --light-bg: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: #334155; }
        .navbar { background: var(--dark-blue); padding: 1rem 0; }
        .header-section { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 60px 0; margin-bottom: -50px; }
        .card-form { background: white; border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
        .required::after { content: " *"; color: #ef4444; }
        .input-group-text { background-color: #f8fafc; border-color: #e2e8f0; color: #94a3b8; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="gestao_painel2.php"><i class="fas fa-sun me-2 text-warning"></i>SunnyImob.</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="gerencia_list.php">Lista de Gerências</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="header-section text-center">
        <div class="container">
            <h1 class="display-6 fw-bold">Nova Gerência</h1>
            <p class="opacity-75">Cadastre um novo setor administrativo no sistema Tocca Onze</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger shadow-sm d-flex align-items-center mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-3"></i>
                    <div><?php echo $error_message; ?></div>
                </div>
                <?php endif; ?>

                <div class="card card-form p-4 p-md-5">
                    <form method="post" action="gerencia_create.php" onsubmit="return validarFormulario()">
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label for="DiretoriaID" class="form-label required">Diretoria</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                                    <select name="DiretoriaID" id="DiretoriaID" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($diretorias as $dir): ?>
                                            <option value="<?php echo $dir['DiretoriaID']; ?>" <?php echo (isset($_POST['DiretoriaID']) && $_POST['DiretoriaID'] == $dir['DiretoriaID']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dir['NomeDiretoria']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="NomeGerencia" class="form-label required">Nome da Gerência</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                    <input type="text" name="NomeGerencia" id="NomeGerencia" class="form-control" 
                                           placeholder="Ex: Comercial Sul" value="<?php echo $_POST['NomeGerencia'] ?? ''; ?>" required>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <label for="NomeGerente" class="form-label required">Nome do Responsável</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="NomeGerente" id="NomeGerente" class="form-control" 
                                           placeholder="Nome completo" value="<?php echo $_POST['NomeGerente'] ?? ''; ?>" required>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label for="TelefoneGerente" class="form-label">Telefone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="TelefoneGerente" id="TelefoneGerente" class="form-control" 
                                           placeholder="(00) 00000-0000" value="<?php echo $_POST['TelefoneGerente'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="col-12 mt-5">
                                <div class="p-3 border rounded bg-light">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-key me-2 text-primary"></i>Vincular Conta de Acesso</h6>
                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <label class="form-label small">Buscar Usuário</label>
                                            <input type="text" id="searchUserInput" class="form-control form-control-sm" placeholder="Digite nome ou login...">
                                        </div>
                                        <div class="col-md-7">
                                            <label for="UserId" class="form-label small">Selecionar Conta</label>
                                            <select name="UserId" id="UserId" class="form-select form-select-sm">
                                                <option value="">Não vincular agora</option>
                                                <?php foreach ($usuarios as $usr): ?>
                                                    <option value="<?php echo $usr['UserId']; ?>" 
                                                            data-search-text="<?php echo strtolower($usr['Nome'].' '.$usr['Usuario']); ?>">
                                                        <?php echo htmlspecialchars($usr['Nome']." (".$usr['Usuario'].")"); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-text mt-2">Isso permite que o gerente acesse o painel administrativo.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-5 pt-4 border-top">
                            <a href="gerencia_list.php" class="btn btn-light px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-5 fw-bold">
                                <i class="fas fa-save me-2"></i>Finalizar Cadastro
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Filtro de usuários em tempo real
        document.getElementById('searchUserInput').addEventListener('input', function() {
            let filter = this.value.toLowerCase();
            let options = document.getElementById('UserId').options;
            for (let opt of options) {
                let text = opt.getAttribute('data-search-text') || '';
                opt.style.display = (text.includes(filter) || opt.value === "") ? '' : 'none';
            }
        });

        // Máscara de Telefone
        document.getElementById('TelefoneGerente').addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });

        function validarFormulario() {
            const campos = ['NomeGerencia', 'NomeGerente', 'DiretoriaID'];
            let valid = true;
            campos.forEach(id => {
                const el = document.getElementById(id);
                if (!el.value.trim()) { el.classList.add('is-invalid'); valid = false; }
                else { el.classList.remove('is-invalid'); }
            });
            return valid;
        }
    </script>
</body>
</html>
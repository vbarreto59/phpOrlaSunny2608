<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

$empresaId = isset($_GET['id']) ? $_GET['id'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $empresaId = $_POST['empresaId'] ?? '';
    
    $updateFields = "";
    $params = array();
    
    if (!empty($_POST['NomeEmpresa'])) {
        $NomeEmpresa = $_POST['NomeEmpresa'];
        $updateFields .= "NomeEmpresa = ?,";
        $params[] = $NomeEmpresa;
    }
    if (!empty($_POST['Descricao'])) {
        $Descricao = $_POST['Descricao'];
        $updateFields .= "Descricao = ?,";
        $params[] = $Descricao;
    }
    if (!empty($_POST['CNPJ'])) {
        $CNPJ = $_POST['CNPJ'];
        $updateFields .= "CNPJ = ?,";
        $params[] = $CNPJ;
    }
    if (!empty($_POST['Endereco'])) {
        $Endereco = $_POST['Endereco'];
        $updateFields .= "Endereco = ?,";
        $params[] = $Endereco;
    }
    if (!empty($_POST['Cidade'])) {
        $Cidade = $_POST['Cidade'];
        $updateFields .= "Cidade = ?,";
        $params[] = $Cidade;
    }
    if (!empty($_POST['Bairro'])) {
        $Bairro = $_POST['Bairro'];
        $updateFields .= "Bairro = ?,";
        $params[] = $Bairro;
    }
    if (!empty($_POST['CEP'])) {
        $CEP = $_POST['CEP'];
        $updateFields .= "CEP = ?,";
        $params[] = $CEP;
    }
    if (!empty($_POST['Contato'])) {
        $Contato = $_POST['Contato'];
        $updateFields .= "Contato = ?,";
        $params[] = $Contato;
    }
    if (!empty($_POST['Telefone'])) {
        $Telefone = $_POST['Telefone'];
        $updateFields .= "Telefone = ?,";
        $params[] = $Telefone;
    }
    if (!empty($_POST['Email'])) {
        $Email = $_POST['Email'];
        $updateFields .= "Email = ?,";
        $params[] = $Email;
    }
    if (!empty($_POST['Obs'])) {
        $Obs = $_POST['Obs'];
        $updateFields .= "Obs = ?,";
        $params[] = $Obs;
    }
    
    if ($updateFields != "") {
        $updateFields = substr($updateFields, 0, -1);
        $params[] = $empresaId;
        
        $sql = "UPDATE Empresa SET " . $updateFields . " WHERE Empresa_Id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            header("Location: gestao_empresa_list2.php");
            exit();
        } catch(PDOException $e) {
            echo "<script>alert('Erro ao atualizar: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// Carrega os dados da empresa para edição
try {
    $stmt = $pdo->prepare("SELECT * FROM empresa WHERE Empresa_Id = ?");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empresa) {
        header("Location: gestao_empresa_list2.php");
        exit();
    }
} catch(PDOException $e) {
    die("Erro ao buscar empresa: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="600">
    <title>Editar Empresa</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA (via variáveis CSS)                  */
        /* ============================================================ */
        :root {
            --primary: <?php echo $theme['primary']; ?>;
            --primary-dark: <?php echo $theme['primary_dark']; ?>;
            --secondary: <?php echo $theme['secondary']; ?>;
            --accent: <?php echo $theme['accent']; ?>;
            --bg-body: <?php echo $theme['bg_body']; ?>;
            --text-color: <?php echo $theme['text_color']; ?>;
            --card-bg: <?php echo $theme['card_bg']; ?>;
            --shadow-color: <?php echo $theme['shadow_color']; ?>;
            --btn-bg: <?php echo $theme['btn_bg']; ?>;
            --btn-hover: <?php echo $theme['btn_hover']; ?>;
            --btn-text: <?php echo $theme['btn_text']; ?>;
        }

        body {
            background: var(--bg-body);
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            padding: 1.5rem;
        }

        /* Navbar neobrutalista */
        .navbar-neo {
            background: var(--primary);
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            padding: 0.7rem 0;
            margin-bottom: 2rem;
        }
        .navbar-neo .navbar-brand {
            font-weight: 800;
            color: #fff;
        }
        .navbar-neo .navbar-brand i {
            color: #fff;
        }

        /* Card neobrutalista */
        .card-neo {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            overflow: hidden;
        }
        .card-neo .card-header {
            background: var(--card-bg);
            border-bottom: 3px solid var(--shadow-color);
            padding: 1rem 1.5rem;
            font-weight: 700;
            color: var(--text-color);
        }
        .card-neo .card-header h4 {
            color: var(--primary);
            font-weight: 800;
        }
        .card-neo .card-body {
            padding: 1.5rem;
        }

        /* Formulário */
        .form-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.3rem;
        }
        .form-control {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 0.6rem 1rem;
            background: #fff;
            color: var(--text-color);
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        textarea.form-control {
            resize: vertical;
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
            font-weight: 700;
        }

        /* Botões neobrutalistas */
        .btn-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.5rem 1.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo-secondary {
            background: #6c757d;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.5rem 1.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-secondary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #5a6268;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .card-neo .card-body { padding: 1rem; }
        }
    </style>
</head>
<body>

    <!-- Navbar neobrutalista -->
    <nav class="navbar navbar-neo">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-building me-2"></i>Gestão de Empresas
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card-neo mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Empresa</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" action="gestao_empresa_update.php?id=<?php echo $empresaId; ?>">
                            <input type="hidden" id="empresaId" name="empresaId" value="<?php echo $empresaId; ?>" required>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="NomeEmpresa" class="form-label required-field">Nome da Empresa</label>
                                    <input type="text" class="form-control" id="NomeEmpresa" name="NomeEmpresa" value="<?php echo htmlspecialchars($empresa['NomeEmpresa'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="CNPJ" class="form-label">CNPJ</label>
                                    <input type="text" class="form-control" id="CNPJ" name="CNPJ" value="<?php echo htmlspecialchars($empresa['CNPJ'] ?? ''); ?>" placeholder="00.000.000/0000-00">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="Descricao" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="Descricao" name="Descricao" rows="2"><?php echo htmlspecialchars($empresa['Descricao'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="Endereco" class="form-label">Endereço</label>
                                    <input type="text" class="form-control" id="Endereco" name="Endereco" value="<?php echo htmlspecialchars($empresa['Endereco'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="Cidade" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="Cidade" name="Cidade" value="<?php echo htmlspecialchars($empresa['Cidade'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="Bairro" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="Bairro" name="Bairro" value="<?php echo htmlspecialchars($empresa['Bairro'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="CEP" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="CEP" name="CEP" value="<?php echo htmlspecialchars($empresa['CEP'] ?? ''); ?>" placeholder="00000-000">
                                </div>
                                <div class="col-md-3">
                                    <label for="Contato" class="form-label">Contato</label>
                                    <input type="text" class="form-control" id="Contato" name="Contato" value="<?php echo htmlspecialchars($empresa['Contato'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="Telefone" class="form-label">Telefone</label>
                                    <input type="text" class="form-control" id="Telefone" name="Telefone" value="<?php echo htmlspecialchars($empresa['Telefone'] ?? ''); ?>" placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-3">
                                    <label for="Email" class="form-label">E-mail</label>
                                    <input type="email" class="form-control" id="Email" name="Email" value="<?php echo htmlspecialchars($empresa['Email'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="Obs" class="form-label">Observações</label>
                                    <textarea class="form-control" id="Obs" name="Obs" rows="3"><?php echo htmlspecialchars($empresa['Obs'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <a class="btn btn-neo-secondary" href="javascript:window.close()">
                                    <i class="fas fa-arrow-left"></i> Voltar
                                </a>
                                
                                <button type="submit" class="btn-neo">
                                    <i class="fas fa-save me-2"></i>Atualizar Empresa
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
    
    <!-- jQuery Mask -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <script>
        $(document).ready(function(){
            $('#CNPJ').mask('00.000.000/0000-00');
            $('#CEP').mask('00000-000');
            $('#Telefone').mask('(00) 00000-0000');
        });
    </script>
</body>
</html>
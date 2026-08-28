<?php
session_start();
require_once 'conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $NomeEmpresa = $_POST['NomeEmpresa'] ?? '';
    $Descricao = $_POST['Descricao'] ?? '';
    $CNPJ = $_POST['CNPJ'] ?? '';
    $Endereco = $_POST['Endereco'] ?? '';
    $Cidade = $_POST['Cidade'] ?? '';
    $Bairro = $_POST['Bairro'] ?? '';
    $CEP = $_POST['CEP'] ?? '';
    $Contato = $_POST['Contato'] ?? '';
    $Telefone = $_POST['Telefone'] ?? '';
    $Email = $_POST['Email'] ?? '';
    $Obs = $_POST['Obs'] ?? '';
    
    // Inicializa as strings de campos e valores
    $campos = "";
    $valores = "";
    $params = array();
    
    // Verifica cada campo e o adiciona à consulta se tiver valor
    if (!empty($NomeEmpresa)) {
        $campos .= ",NomeEmpresa";
        $valores .= ",?";
        $params[] = $NomeEmpresa;
    }
    
    if (!empty($Descricao)) {
        $campos .= ",Descricao";
        $valores .= ",?";
        $params[] = $Descricao;
    }
    
    if (!empty($CNPJ)) {
        $campos .= ",CNPJ";
        $valores .= ",?";
        $params[] = $CNPJ;
    }
    
    if (!empty($Endereco)) {
        $campos .= ",Endereco";
        $valores .= ",?";
        $params[] = $Endereco;
    }
    
    if (!empty($Cidade)) {
        $campos .= ",Cidade";
        $valores .= ",?";
        $params[] = $Cidade;
    }
    
    if (!empty($Bairro)) {
        $campos .= ",Bairro";
        $valores .= ",?";
        $params[] = $Bairro;
    }
    
    if (!empty($CEP)) {
        $campos .= ",CEP";
        $valores .= ",?";
        $params[] = $CEP;
    }
    
    if (!empty($Contato)) {
        $campos .= ",Contato";
        $valores .= ",?";
        $params[] = $Contato;
    }
    
    if (!empty($Telefone)) {
        $campos .= ",Telefone";
        $valores .= ",?";
        $params[] = $Telefone;
    }
    
    if (!empty($Email)) {
        $campos .= ",Email";
        $valores .= ",?";
        $params[] = $Email;
    }
    
    if (!empty($Obs)) {
        $campos .= ",Obs";
        $valores .= ",?";
        $params[] = $Obs;
    }
    
    // Monta a instrução SQL final se houver pelo menos um campo com valor
    if (strlen($campos) > 0) {
        // Remove a vírgula inicial das strings de campos e valores
        $campos = substr($campos, 1);
        $valores = substr($valores, 1);
        
        $sql = "INSERT INTO empresa (" . $campos . ") VALUES (" . $valores . ")";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            header("Location: gestao_empresa_list2.php");
            exit();
        } catch(PDOException $e) {
            echo "<script>alert('Erro ao salvar: " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('Nenhum campo foi preenchido. A inserção foi cancelada.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="600">
    <title>Cadastrar Empresa</title>
    
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
            background-color: #A01C3A;
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
        }
        
        .card-header-custom {
            background-color: white;
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-building me-2"></i>Gestão de Empresas
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card card-custom mb-4">
                    <div class="card-header card-header-custom">
                        <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Cadastrar Nova Empresa</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" action="gestao_empresa_create.php">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="NomeEmpresa" class="form-label required-field">Nome da Empresa</label>
                                    <input type="text" class="form-control" id="NomeEmpresa" name="NomeEmpresa" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="CNPJ" class="form-label">CNPJ</label>
                                    <input type="text" class="form-control" id="CNPJ" name="CNPJ" placeholder="00.000.000/0000-00">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="Descricao" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="Descricao" name="Descricao" rows="2"></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="Endereco" class="form-label">Endereço</label>
                                    <input type="text" class="form-control" id="Endereco" name="Endereco">
                                </div>
                                <div class="col-md-3">
                                    <label for="Cidade" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="Cidade" name="Cidade">
                                </div>
                                <div class="col-md-3">
                                    <label for="Bairro" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="Bairro" name="Bairro">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="CEP" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="CEP" name="CEP" placeholder="00000-000">
                                </div>
                                <div class="col-md-3">
                                    <label for="Contato" class="form-label">Contato</label>
                                    <input type="text" class="form-control" id="Contato" name="Contato">
                                </div>
                                <div class="col-md-3">
                                    <label for="Telefone" class="form-label">Telefone</label>
                                    <input type="text" class="form-control" id="Telefone" name="Telefone" placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-3">
                                    <label for="Email" class="form-label">E-mail</label>
                                    <input type="email" class="form-control" id="Email" name="Email">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="Obs" class="form-label">Observações</label>
                                    <textarea class="form-control" id="Obs" name="Obs" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="gestao_empresa_list.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Voltar
                                </a>
                                <button type="submit" class="btn btn-custom">
                                    <i class="fas fa-save me-2"></i>Salvar Empresa
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
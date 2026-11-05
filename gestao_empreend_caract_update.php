<?php
session_start();

require_once 'conn.php';


// Funções auxiliares
function usuarioPodeEditar() {
    // Implemente sua lógica de permissões aqui
    return isset($_SESSION['Usuario']) && !empty($_SESSION['Usuario']);
}

function getCheckboxValue($valor) {
    return ($valor == "on") ? 1 : 0;
}

function formatarParaDecimalPonto($valor) {
    if (empty($valor)) return '';
    $valorSemPontos = str_replace('.', '', $valor);
    return str_replace(',', '.', $valorSemPontos);
}

function removerNumeros($texto) {
    return preg_replace('/[0-9*-]/', '', $texto);
}

// Verificação de login
if (empty($_SESSION['Usuario'])) {
    header("Location: login_v3a.php");
    exit();
}

// Obter ID do empreendimento
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar inputs
    $localidade = isset($_POST['Localidade']) ? strtoupper(trim($_POST['Localidade'])) : '';
    $suites = isset($_POST['Suites']) ? intval($_POST['Suites']) : null;
    $wcTotal = isset($_POST['WCTotal']) ? intval($_POST['WCTotal']) : null;
    $dataEntrega = isset($_POST['DataEntrega']) ? trim($_POST['DataEntrega']) : '';
    $vagasGaragem = isset($_POST['VagasGaragem']) ? intval($_POST['VagasGaragem']) : null;
    $tipoGaragem = isset($_POST['TipoGaragem']) ? trim($_POST['TipoGaragem']) : '';
    $valorCond = isset($_POST['ValorCond']) ? trim($_POST['ValorCond']) : '';
    $valorIPTU = isset($_POST['ValorIPTU']) ? trim($_POST['ValorIPTU']) : '';
    $metragemMin = isset($_POST['MetragemMin']) ? trim($_POST['MetragemMin']) : '';
    $metragemMax = isset($_POST['MetragemMax']) ? trim($_POST['MetragemMax']) : '';
    $tipologia = isset($_POST['Tipologia']) ? strtoupper(trim($_POST['Tipologia'])) : '';
    $descricao = isset($_POST['Descricao']) ? strtoupper(trim($_POST['Descricao'])) : '';
    $mesEntrega = isset($_POST['MesEntrega']) ? intval($_POST['MesEntrega']) : null;
    $anoEntrega = isset($_POST['AnoEntrega']) ? intval($_POST['AnoEntrega']) : null;
    $proximoMar = isset($_POST['ProximoMar']) ? intval($_POST['ProximoMar']) : null;
    $pavimentos = isset($_POST['Pavimentos']) ? intval($_POST['Pavimentos']) : null;
    $qtdBlocos = isset($_POST['QTD_Blocos']) ? intval($_POST['QTD_Blocos']) : 0;
    $qtdUnidades = isset($_POST['QTD_Unidades']) ? intval($_POST['QTD_Unidades']) : 0;
    $menorPreco = isset($_POST['MenorPreco']) ? trim($_POST['MenorPreco']) : '';
    $maiorPreco = isset($_POST['MaiorPreco']) ? trim($_POST['MaiorPreco']) : '';
    
    // Campos booleanos
    $camposBooleanos = [
        'BeiraMar', 'RoofTop', 'Academia', 'Sauna', 'PetPlace', 'EspacoGourmet',
        'Restaurante', 'Coworking', 'QuadraTenis', 'Emporio', 'Piscina2', 'Spa',
        'Recepcao24h', 'LoungeBar', 'Brinquedoteca', 'Varanda', 'VarandaGourmet',
        'Salaojogos', 'Entregue', 'Vendido', 'Ocultar', 'GestoraAluguel',
        'Lavanderia', 'Bicicletario', 'Hidromassagem', 'GasEncanado',
        'PiscinaPrivativa', 'FechaduraEletronica'
    ];
    
    // Preparar dados para UPDATE
    $setClauses = [];
    $params = [];
    
    // Adicionar campos condicionalmente
    if (!empty($localidade) && $localidade != 'X') {
        $setClauses[] = "Localidade = :localidade";
        $params[':localidade'] = $localidade;
    }
    
    if (!is_null($suites) && $suites > 0) {
        $setClauses[] = "Suites = :suites";
        $params[':suites'] = $suites;
    }
    
    if (!is_null($wcTotal) && $wcTotal > 0) {
        $setClauses[] = "WCTotal = :wctotal";
        $params[':wctotal'] = $wcTotal;
    }
    
    if (!empty($dataEntrega) && $dataEntrega != '2035-01-01') {
        $setClauses[] = "DataEntrega = :data_entrega";
        $params[':data_entrega'] = $dataEntrega;
    }
    
    if (!empty($tipoGaragem)) {
        $setClauses[] = "TipoGaragem = :tipo_garagem";
        $params[':tipo_garagem'] = $tipoGaragem;
    }
    
    if (!is_null($vagasGaragem) && $vagasGaragem > 0) {
        $setClauses[] = "VagasGaragem = :vagas_garagem";
        $params[':vagas_garagem'] = $vagasGaragem;
    }
    
    // Tratar valores monetários
    if (!empty($valorCond)) {
       // $valorCond = formatarParaDecimalPonto($valorCond);
        $setClauses[] = "ValorCond = :valor_cond";
        $params[':valor_cond'] = $valorCond;
    }
    
    if (!empty($valorIPTU)) {
      //  $valorIPTU = formatarParaDecimalPonto($valorIPTU);
        $setClauses[] = "ValorIPTU = :valor_iptu";
        $params[':valor_iptu'] = $valorIPTU;
    }
    
    if (!empty($menorPreco)) {

    //  $menorPreco = formatarParaDecimalPonto($menorPreco);

      $setClauses[] = "MenorPreco = :menor_preco";
      $params[':menor_preco'] = $menorPreco;
    }
    
    if (!empty($maiorPreco)) {
     //   $maiorPreco = formatarParaDecimalPonto($maiorPreco);
        $setClauses[] = "MaiorPreco = :maior_preco";
        $params[':maior_preco'] = $maiorPreco;
    }
    
    // Metragens
    if (!empty($metragemMin)) {
        $metragemMin = str_replace(',', '.', $metragemMin);
        $setClauses[] = "MetragemMin = :metragem_min";
        $params[':metragem_min'] = $metragemMin;
    }
    
    if (!empty($metragemMax)) {
        $metragemMax = str_replace(',', '.', $metragemMax);
        $setClauses[] = "MetragemMax = :metragem_max";
        $params[':metragem_max'] = $metragemMax;
    }
    
    if (!empty($tipologia)) {
        $setClauses[] = "Tipologia = :tipologia";
        $params[':tipologia'] = $tipologia;
    }
    
    if (!empty($descricao)) {
        $setClauses[] = "Descricao = :descricao";
        $params[':descricao'] = $descricao;
    }
    
    if (!is_null($mesEntrega) && $mesEntrega > 0) {
        $setClauses[] = "MesEntrega = :mes_entrega";
        $params[':mes_entrega'] = $mesEntrega;
    }
    
    if (!is_null($anoEntrega) && $anoEntrega > 0) {
        $setClauses[] = "AnoEntrega = :ano_entrega";
        $params[':ano_entrega'] = $anoEntrega;
    }
    
    if (!is_null($proximoMar) && $proximoMar > 0) {
        $setClauses[] = "ProximoMar = :proximo_mar";
        $params[':proximo_mar'] = $proximoMar;
    }
    
    if (!is_null($pavimentos) && $pavimentos > 0) {
        $setClauses[] = "Pavimentos = :pavimentos";
        $params[':pavimentos'] = $pavimentos;
    }
    
    if ($qtdBlocos > 0) {
        $setClauses[] = "QTD_Blocos = :qtd_blocos";
        $params[':qtd_blocos'] = $qtdBlocos;
    }
    
    if ($qtdUnidades > 0) {
        $setClauses[] = "QTD_Unidades = :qtd_unidades";
        $params[':qtd_unidades'] = $qtdUnidades;
    }
    
    // Adicionar campos booleanos
    foreach ($camposBooleanos as $campo) {
        $valor = isset($_POST[$campo]) ? getCheckboxValue($_POST[$campo]) : 0;
        $setClauses[] = "$campo = :$campo";
        $params[":$campo"] = $valor;
    }
    
    // Campos adicionais
    $setClauses[] = "DataAtualizacao = NOW()";
    $setClauses[] = "UsuarioAtu = :usuario_atu";
    $params[':usuario_atu'] = $_SESSION['Usuario'];
    
    // Executar UPDATE se houver campos para atualizar
    if (!empty($setClauses)) {
        try {
            $sql = "UPDATE empreendimento SET " . implode(', ', $setClauses) . " WHERE Empreend_ID = :id";
            $params[':id'] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['mensagem'] = "Dados atualizados com sucesso!";
            $_SESSION['tipo_mensagem'] = "success";
            
            // Registrar histórico (implemente conforme necessário)
            // registrarHistorico($id, $_POST['idEmpresa'], "Atualização das Características");
            
        } catch(PDOException $e) {
            $_SESSION['mensagem'] = "Erro ao atualizar: " . $e->getMessage();
            $_SESSION['tipo_mensagem'] = "danger";
        }
    }
}

// Buscar dados do empreendimento
try {
    $stmt = $pdo->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = :id");
    $stmt->execute([':id' => $id]);
    $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empreendimento) {
        die("Empreendimento não encontrado");
    }
} catch(PDOException $e) {
    die("Erro ao buscar empreendimento: " . $e->getMessage());
}

// Formatar valores para exibição
function formatarMoedaExibicao($valor) {
    if (empty($valor) || !is_numeric($valor)) return '0,00';
    return number_format($valor, 2, ',', '.');
}

$valorCondExibicao = formatarMoedaExibicao($empreendimento['ValorCond']);
$valorIPTUExibicao = formatarMoedaExibicao($empreendimento['ValorIPTU']);
$menorPrecoExibicao = formatarMoedaExibicao($empreendimento['MenorPreco']);
$maiorPrecoExibicao = formatarMoedaExibicao($empreendimento['MaiorPreco']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="600">
    <title>Editar Características do Empreendimento</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .form-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-section h5 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .form-check-label {
            margin-left: 5px;
        }
        .img-loading {
            opacity: 0.3;
        }
        .img-fallback {
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Contador de sessão -->
        <audio id="alertSound" src="notification.mp3" preload="auto"></audio>
        
        <div id="sessionTimer" style="position:fixed; bottom:20px; left:20px; background:#f8f9fa; padding:10px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.1); z-index:1000; border:1px solid #ddd;">
            <i class="bi bi-clock" style="margin-right:5px;"></i>
            Tempo restante: <span id="time" style="font-weight:bold;">--:--</span>
        </div>
        
        <div id="sessionWarning" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1050;">
            <div style="background:white; width:400px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 20px rgba(0,0,0,0.2);">
                <h3 style="color:#dc3545;"><i class="bi bi-exclamation-triangle-fill"></i> Sessão Prestes a Expirar</h3>
                <p>Sua sessão expirará em <span id="warningTime">5</span> minutos.</p>
                <p>Os dados foram salvos automaticamente.</p>
                <div style="text-align:right; margin-top:20px;">
                    <button onclick="logout()" class="btn btn-secondary me-2">Sair</button>
                    <button onclick="extendSession()" class="btn btn-primary">Continuar</button>
                </div>
            </div>
        </div>
        
        <header class="container-fluid py-3 mb-4 border-bottom" style="background-color: #800000; color: white;">
            <div class="container">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-2 mb-md-0 d-flex align-items-center">
                        <img src="img/logosunny.jpg" alt="Logotipo Tocca" style="height: 50px; margin-right: 15px;">
                        <div>
                            <h1 class="mb-0 fs-4">
                                <i class="fas fa-building me-2"></i> Empreendimentos Características
                            </h1>
                            <h3 class="mb-0 fs-4">
                                <i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?>
                            </h3>
                            <h5 class="mb-0 fs-4">
                                <i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($empreendimento['NomeEmpresa']); ?>
                            </h5>
                            <p class="mb-0 fs-6">SunnyImob</p>
                        </div>
                    </div>
                    <div>
                        <a href="#" class="btn btn-secondary" onclick="window.close()" style="background-color: #A0522D; border-color: #A0522D; color: white;">
                            <i class="fas fa-times me-1"></i> Fechar
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-<?php echo $_SESSION['tipo_mensagem']; ?> alert-dismissible fade show">
                <?php echo $_SESSION['mensagem']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php 
            unset($_SESSION['mensagem']);
            unset($_SESSION['tipo_mensagem']);
            ?>
        <?php endif; ?>
        
        <!-- Imagem do empreendimento -->
        <div class="d-flex justify-content-center">
            <div class="card" style="width: 18rem;">
                <img src="card/<?php echo $empreendimento['Empreend_ID']; ?>.jpg"
                    class="card-img-top ficha-imagem img-loading"
                    alt="<?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?>"
                    onload="this.classList.remove('img-loading')"
                    onerror="this.onerror=null;this.src='card/default.jpg';this.classList.add('img-fallback');this.classList.remove('img-loading')">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?></h5>
                </div>
            </div>
        </div>
        
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="idEmpresa" value="<?php echo $empreendimento['Empresa_Id']; ?>">
            <input type="hidden" name="NomeEmpreend" value="<?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?>">
            
            <!-- Seção 1: Informações Básicas -->
            <div class="form-section" style="background-color: #E1E1E1;">
                <h5><i class="fas fa-info-circle"></i> Informações Básicas</h5>
                
                <div class="card mb-3" style="background-color: #F5F2F2;">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label for="Localidade" class="form-label">Localidade</label>
                                <input type="text" class="form-control" id="Localidade" name="Localidade" value="<?php echo htmlspecialchars($empreendimento['Localidade']); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="Tipologia" class="form-label">Tipologia (ex: CASA/BANG/FLAT/STUDIO/2Q ou 1Q/2Q/3Q)</label>
                                <input type="text" class="form-control text-uppercase" id="Tipologia" name="Tipologia"
                                    value="<?php echo htmlspecialchars(strtoupper($empreendimento['Tipologia'])); ?>"
                                    title="Digite combinações como CASA, BANG, STUDIO, 1Q/2Q ou BANG/STUDIO/3Q"
                                    oninput="formatarTipologia(this)">
                                <small class="form-text text-muted">Separe os valores com barra (ex: BANG/STUDIO/1Q/2Q)</small>
                                <div class="invalid-feedback">
                                    Formato inválido! Use: BANG, STUDIO ou 1Q-6Q separados por barra (ex: BANG/STUDIO/1Q/2Q)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3" style="background-color: #F5F2F2;">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div class="form-check form-switch me-3">
                            <input class="form-check-input" type="checkbox" id="Entregue" name="Entregue" <?php echo $empreendimento['Entregue'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="Entregue">Entregue</label>
                        </div>
                        
                        <div class="form-check form-switch me-3">
                            <input class="form-check-input" type="checkbox" id="Vendido" name="Vendido" <?php echo $empreendimento['Vendido'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="Vendido" style="color: red; font-size: <?php echo $empreendimento['Vendido'] ? '1.5em' : 'inherit'; ?>;">100% Vendido</label>
                        </div>
                        
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="Ocultar" name="Ocultar" <?php echo $empreendimento['Ocultar'] ? 'checked' : ''; ?> onchange="confirmarOcultar(this)">
                            <label class="form-check-label" for="Ocultar">
                                Ocultar
                                <span id="textoConfirmacao" style="color: red; display: none; margin-left: 10px;">
                                    Este empreendimento será ocultado no site
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3" style="background-color: #F5F2F2;">
                    <div class="card-body">
                        <div class="row">
                            <!-- Campo Mês Entrega -->
                            <div class="col-md-3">
                                <label for="MesEntrega" class="form-label">Mês Entrega</label>
                                <select class="form-select" id="MesEntrega" name="MesEntrega">
                                    <option value="">Selecione...</option>
                                    <?php
                                    $mesAtual = $empreendimento['MesEntrega'] ? intval($empreendimento['MesEntrega']) : 0;
                                    for ($i = 1; $i <= 12; $i++) {
                                        $selected = ($mesAtual == $i) ? 'selected' : '';
                                        $monthName = date('M', mktime(0, 0, 0, $i, 1));
                                        echo "<option value='$i' $selected>$monthName ($i)</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Campo Ano Entrega -->
                            <div class="col-md-3">
                                <label for="AnoEntrega" class="form-label">Ano Entrega</label>
                                <select class="form-select" id="AnoEntrega" name="AnoEntrega">
                                    <option value="">Selecione...</option>
                                    <?php
                                    $anoAtual = $empreendimento['AnoEntrega'] ? intval($empreendimento['AnoEntrega']) : 0;
                                    for ($i = 2020; $i <= 2035; $i++) {
                                        $selected = ($anoAtual == $i) ? 'selected' : '';
                                        echo "<option value='$i' $selected>$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Campo Distância do Mar -->
                            <div class="col-md-3">
                                <label for="ProximoMar" class="form-label">Distância do Mar (m)</label>
                                <input type="number" class="form-control" id="ProximoMar" name="ProximoMar" value="<?php echo $empreendimento['ProximoMar']; ?>">
                            </div>
                            
                            <!-- Campo Pavimentos -->
                            <div class="col-md-3">
                                <label for="Pavimentos" class="form-label">Pavimentos (Andares)</label>
                                <input type="number" class="form-control" id="Pavimentos" name="Pavimentos" value="<?php echo $empreendimento['Pavimentos']; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3" style="background-color: #F5F2F2;">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <label for="Descricao" class="form-label">Descrição</label>
                                <textarea class="form-control" id="Descricao" name="Descricao" rows="3"><?php echo htmlspecialchars($empreendimento['Descricao']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seção 2: Valores -->
            <div class="form-section" style="background-color: #E1E1E1;">
                <h5><i class="fas fa-money-bill-wave"></i> Valores</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="ValorCond" class="form-label">Valor do Condomínio (R$)</label>
                        <input type="text" class="form-control" id="ValorCond" name="ValorCond" value="<?php echo $valorCondExibicao; ?>" onkeyup="formatarCond();">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="ValorIPTU" class="form-label">Valor do IPTU (R$)</label>
                        <input type="text" class="form-control" id="ValorIPTU" name="ValorIPTU" value="<?php echo $valorIPTUExibicao; ?>" onkeyup="formatarIPTU();">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="MenorPreco" class="form-label">Menor Preço (R$)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-tag"></i></span>
                            <input type="text" class="form-control" id="MenorPreco" name="MenorPreco" value="<?php echo $menorPrecoExibicao; ?>" onkeyup="formatarMoeda1();">
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="MaiorPreco" class="form-label">Maior Preço (R$)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-tags"></i></span>
                            <input type="text" class="form-control" id="MaiorPreco" name="MaiorPreco" value="<?php echo $maiorPrecoExibicao; ?>" onkeyup="formatarMoeda2();">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="MetragemMin" class="form-label">Metragem Mínima (m²)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-ruler-combined"></i></span>
                            <input type="text" class="form-control metragem-input" id="MetragemMin" name="MetragemMin" 
                                value="<?php echo htmlspecialchars($empreendimento['MetragemMin']); ?>"
                                placeholder="0,00">
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="MetragemMax" class="form-label">Metragem Máxima (m²)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-ruler"></i></span>
                            <input type="text" class="form-control metragem-input" id="MetragemMax" name="MetragemMax" 
                                value="<?php echo htmlspecialchars($empreendimento['MetragemMax']); ?>"
                                placeholder="0,00">
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="VagasGaragem" class="form-label">Vagas de Garagem</label>
                        <input type="number" class="form-control" id="VagasGaragem" name="VagasGaragem" value="<?php echo $empreendimento['VagasGaragem']; ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="TipoGaragem" class="form-label">Tipo de Garagem</label>
                        <select class="form-select" id="TipoGaragem" name="TipoGaragem">
                            <option value="">Selecione...</option>
                            <option value="Privativa" <?php echo $empreendimento['TipoGaragem'] == 'Privativa' ? 'selected' : ''; ?>>Privativa</option>
                            <option value="Rotativa" <?php echo $empreendimento['TipoGaragem'] == 'Rotativa' ? 'selected' : ''; ?>>Rotativa</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="QTD_Blocos" class="form-label">Quantidade de Blocos</label>
                        <input type="number" class="form-control" id="QTD_Blocos" name="QTD_Blocos" value="<?php echo $empreendimento['Qtd_Blocos']; ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="QTD_Unidades" class="form-label">Quantidade de Unidades</label>
                        <input type="number" class="form-control" id="QTD_Unidades" name="QTD_Unidades" value="<?php echo $empreendimento['Qtd_Unidades']; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Seção 3: Comodidades -->
            <div class="form-section" style="background-color: #E1E1E1;">
                <h5><i class="fas fa-swimming-pool"></i> Comodidades</h5>
                <div class="checkbox-grid">
                    <?php
                    $comodidades = [
                        1 => ['Academia', 'fa-dumbbell'],
                        2 => ['BeiraMar', 'fa-umbrella-beach'],
                        3 => ['Brinquedoteca', 'fa-gamepad'],
                        4 => ['Coworking', 'fa-laptop-code'],
                        5 => ['Emporio', 'fa-store'],
                        6 => ['EspacoGourmet', 'fa-utensils'],
                        7 => ['LoungeBar', 'fa-cocktail'],
                        8 => ['PetPlace', 'fa-paw'],
                        9 => ['Piscina2', 'fa-swimming-pool'],
                        10 => ['QuadraTenis', 'fa-table-tennis'],
                        11 => ['Recepcao24h', 'fa-concierge-bell'],
                        12 => ['Restaurante', 'fa-utensils'],
                        13 => ['RoofTop', 'fa-building'],
                        14 => ['Salaojogos', 'fa-chess'],
                        15 => ['Sauna', 'fa-hot-tub'],
                        16 => ['Spa', 'fa-spa'],
                        17 => ['Varanda', 'fa-door-open'],
                        18 => ['VarandaGourmet', 'fa-grill-hot'],
                        19 => ['GestoraAluguel', 'fa-key'],
                        20 => ['Lavanderia', 'fa-tshirt'],
                        21 => ['Bicicletario', 'fa-bicycle'],
                        22 => ['Hidromassagem', 'fa-hot-tub'],
                        23 => ['GasEncanado', 'fa-fire'],
                        24 => ['PiscinaPrivativa', 'fa-water'],
                        25 => ['FechaduraEletronica', 'fa-lock']
                    ];
                    
                    foreach ($comodidades as $numero => $comodidade) {
                        $nome = $comodidade[0];
                        $icone = $comodidade[1];
                        $checked = $empreendimento[$nome] ? 'checked' : '';
                        echo "
                        <div class='form-check form-switch mb-3'>
                            <input class='form-check-input' type='checkbox' id='$nome' name='$nome' $checked>
                            <label class='form-check-label' for='$nome'>$numero. <i class='fas $icone mr-2'></i> " . str_replace(['_', '2'], [' ', ''], $nome) . "</label>
                        </div>";
                    }
                    ?>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="empresa_list2.php" class="btn btn-secondary me-md-2">Voltar</a>
                <?php if (usuarioPodeEditar()): ?>
                    <button type="submit" class="btn btn-primary">Atualizar Características</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <script>
    function confirmarOcultar(checkbox) {
        var texto = document.getElementById("textoConfirmacao");
        
        if (checkbox.checked) {
            texto.style.display = "inline";
            var confirmacao = confirm("Tem certeza de que deseja ocultar este empreendimento?");
            if (!confirmacao) {
                checkbox.checked = false;
                texto.style.display = "none";
            }
        } else {
            texto.style.display = "none";
        }
    }
    
    function formatarTipologia(input) {
        input.value = input.value.toUpperCase();
        input.value = input.value.replace(/[^A-Z0-9\/]/g, '');
        
        const isValid = /^((CASA|BANG|STUDIO|FLAT|[1-6]Q)(\/(BANG|STUDIO|[1-6]Q))*)$/.test(input.value);
        
        if (input.value && !isValid) {
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
        }
    }
    
    $(document).ready(function() {
        // Máscara para valores monetários
        $('#MenorPreco, #MaiorPreco, #ValorCond, #ValorIPTU').mask('#.##0,00', {reverse: true});
        
        // Máscara para metragens
        $('#MetragemMin, #MetragemMax').mask('0000,00', {reverse: true});
        
        // Validação no submit
        $('form').on('submit', function(e) {
            // Remover formatação antes do envio
            $('#MenorPreco, #MaiorPreco, #ValorCond, #ValorIPTU').each(function() {
                var valor = $(this).val().replace(/\./g, '').replace(',', '.');
                $(this).val(valor);
            });
            
            $('#MetragemMin, #MetragemMax').each(function() {
                var valor = $(this).val().replace(',', '.');
                $(this).val(valor);
            });
        });
    });
    
    // Script do contador de sessão (simplificado)
    var sessionTimeout = <?php echo (ini_get('session.gc_maxlifetime') ?: 1440) * 60; ?>;
    
    function updateTimer() {
        var minutes = Math.floor(sessionTimeout / 60);
        var seconds = sessionTimeout % 60;
        var timeString = minutes.toString().padStart(2, '0') + ":" + seconds.toString().padStart(2, '0');
        document.getElementById('time').textContent = timeString;
        
        var timerElement = document.getElementById('sessionTimer');
        if(sessionTimeout <= 300) { // 5 minutos
            timerElement.style.backgroundColor = '#fff3cd';
            timerElement.style.borderColor = '#ffc107';
        }
        
        sessionTimeout--;
        if(sessionTimeout > 0) {
            setTimeout(updateTimer, 1000);
        } else {
            window.location.href = "login2.php?timeout=1";
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        updateTimer();
    });
    </script>
</body>
</html>
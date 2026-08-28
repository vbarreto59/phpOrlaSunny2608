<?php
// Inclui a conexão PDO
require_once 'conn.php';

// Iniciar sessão
session_start();

// Função para remover números de uma string
function removerNumeros($strTexto) {
    return preg_replace('/[0-9]/', '', $strTexto);
}

// Verificar se o ID foi fornecido
if (empty($_GET['id'])) {
    header("Location: empreendimento_list.php");
    exit();
}

$empreendimentoId = $_GET['id'];

// Verificar se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header("Location: login2.php");
    exit();
}

// Consultar o empreendimento usando PDO
try {
    $sql = "SELECT * FROM empreendimento WHERE Empreend_ID = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $empreendimentoId, PDO::PARAM_INT);
    $stmt->execute();
    $empreendimento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empreendimento) {
        header("Location: empreendimento_list.php");
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao carregar empreendimento: " . $e->getMessage());
}

// ------------envio de email --------------
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    // Usando PHPMailer ou função mail() nativa
    // Exemplo com função mail() nativa (requer configuração SMTP no php.ini)
    $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
    $subject = "SI-Ficha1-" . strtoupper($_SESSION['Usuario']) . " - " . $_SERVER['REMOTE_ADDR'] . " - " . date('Y-m-d') . " - " . date('H:i:s');
    $message = "Empreendimento: " . $empreendimento['NomeEmpreendimento'];
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($to, $subject, $message, $headers);
}
// ----------- fim envio de email

// Gerar nome do arquivo PDF
$nomeArquivoPDF = "Ficha_" . strtoupper($empreendimento['Empreend_ID']) . "_" . 
                  strtoupper(removerNumeros($empreendimento['NomeEmpreendimento'])) . ".pdf";
$nomeArquivoPDF = str_replace(" ", "_", $nomeArquivoPDF);

// Obter data e hora atuais no formato brasileiro
$dataAtual = date('d/m/Y');
$horaAtual = date('H:i:s');

// Função auxiliar para formatar moeda
function formatCurrency($valor) {
    if (is_numeric($valor)) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha do Empreendimento</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .ficha-container {
            max-width: 900px;
            margin: 20px auto;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
            width: 210mm; /* A4 width for PDF */
            box-sizing: border-box;
        }
        
        .ficha-header {
            background-color: #0275d8;
            color: white;
            padding: 15px;
            text-align: center;
        }
        
        .ficha-body {
            padding: 20px;
            background-color: white;
        }
        
        .ficha-imagem {
            width: 50%;
            height: 350px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            display: block;
            margin-left: auto;
            margin-right: auto;
            background-color: #f8f9fa;
        }
        
        .img-fallback {
            object-fit: contain;
            padding: 15px;
            background-color: #f8f9fa;
        }
        
        .img-loading {
            background: linear-gradient(90deg, #f8f9fa 25%, #e9ecef 50%, #f8f9fa 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .detalhe-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .detalhe-item:last-child {
            border-bottom: none;
        }
        
        .detalhe-titulo {
            font-weight: bold;
            color: #555;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            font-size: 0.9em;
        }
        
        .detalhe-titulo i {
            margin-right: 8px;
            color: #0275d8;
        }
        
        .detalhe-valor {
            font-size: 0.95em;
            padding-left: 24px;
        }
        
        .badge-status {
            font-size: 0.85em;
            padding: 6px 12px;
        }
        
        .btn-voltar {
            margin-top: 15px;
            font-size: 0.9em;
        }
        
        .valor-destaque {
            color: #d9534f;
            font-weight: bold;
        }
        
        .amenities-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            margin-top: 15px;
        }
        
        .amenity-item {
            display: flex;
            align-items: center;
            padding: 6px;
            border-radius: 4px;
            background-color: #f8f9fa;
            font-size: 0.9em;
        }
        
        .amenity-item i {
            margin-right: 8px;
            color: #0275d8;
            width: 18px;
            text-align: center;
        }
        
        .section-title {
            font-size: 1.1em;
            font-weight: bold;
            color: #0275d8;
            margin: 20px 0 10px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #0275d8;
        }
        
        .info-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .info-col {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
            box-sizing: border-box;
        }
        
        @media (max-width: 768px) {
            .info-col {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .ficha-imagem {
                width: 100%;
                height: 300px;
            }
        }
        
        .btn-pdf {
            background-color: #d9534f;
            color: white;
            margin-left: 8px;
            font-size: 0.9em;
        }
        
        .btn-pdf:hover {
            background-color: #c9302c;
            color: white;
        }
        
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .data-hora {
            text-align: center;
            font-size: 0.9em;
            color: #555;
            margin-top: 15px;
        }

        /* Ensure content fits within A4 for PDF */
        @page {
            size: A4;
            margin: 10mm;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="ficha-container" id="fichaParaPDF">
            <div class="ficha-header">
                <h2><img src="<?php echo isset($_SESSION['Conf_ImgLogo']) ? htmlspecialchars($_SESSION['Conf_ImgLogo']) : ''; ?>" width="200" height="60" alt="LogoTocca"></h2>
                <h3><i class="fas fa-building"></i> FICHA DO EMPREENDIMENTO</h3>
                <h4><?php echo htmlspecialchars(strtoupper(removerNumeros($empreendimento['NomeEmpreendimento']))); ?></h4>
            </div>
            
            <div class="ficha-body">
                <img src="card/<?php echo htmlspecialchars($empreendimento['Empreend_ID']); ?>.jpg" 
                     class="ficha-imagem img-loading" 
                     alt="<?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?>" 
                     onload="this.classList.remove('img-loading')"
                     onerror="this.onerror=null;this.src='card/default.jpg';this.classList.add('img-fallback');this.classList.remove('img-loading')">
                
                <div class="info-row">
                    <div class="info-col">
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-city"></i> EMPRESA</div>
                            <div class="detalhe-valor"><?php echo htmlspecialchars(strtoupper($empreendimento['NomeEmpresa'] ?? '')); ?></div>
                        </div>
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-map-marker-alt"></i> LOCALIDADE</div>
                            <div class="detalhe-valor"><?php echo htmlspecialchars(strtoupper($empreendimento['Localidade'] ?? '')); ?></div>
                        </div>
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-calendar-check"></i> STATUS DE ENTREGA</div>
                            <div class="detalhe-valor">
                                <?php if (!empty($empreendimento['Entregue']) && $empreendimento['Entregue'] == 1): ?>
                                    <span class="badge badge-success badge-status"><i class="fas fa-check-circle"></i> ENTREGUE</span>
                                <?php else: 
                                    $mesEntrega = $empreendimento['MesEntrega'] ?? '';
                                    $anoEntrega = $empreendimento['AnoEntrega'] ?? '';
                                    $entregaTexto = '';
                                    
                                    if (is_numeric($mesEntrega) && is_numeric($anoEntrega)) {
                                        $mesEntrega = intval($mesEntrega);
                                        $anoEntrega = intval($anoEntrega);
                                        
                                        if ($mesEntrega >= 1 && $mesEntrega <= 12 && $anoEntrega >= 2025) {
                                            $entregaTexto = str_pad($mesEntrega, 2, '0', STR_PAD_LEFT) . "/" . $anoEntrega;
                                        }
                                    }
                                    
                                    if (!empty($entregaTexto)): ?>
                                        <span class="badge badge-warning badge-status"><i class="fas fa-clock"></i> PREVISÃO: <?php echo htmlspecialchars($entregaTexto); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary badge-status"><i class="fas fa-question-circle"></i> SEM PREVISÃO</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-bed"></i> TIPOLOGIA</div>
                            <div class="detalhe-valor">
                                <?php if (!empty(trim($empreendimento['Tipologia'] ?? ''))): ?>
                                    <?php echo htmlspecialchars(strtoupper($empreendimento['Tipologia'])); ?>
                                <?php else: ?>
                                    Não informado
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-col">
                        <?php 
                        // Armazena os valores em variáveis para facilitar a leitura e a verificação
                        $menorPreco = $empreendimento['MenorPreco'] ?? null;
                        $maiorPreco = $empreendimento['MaiorPreco'] ?? null;

                        // Verifica se os campos não são nulos e têm valores numéricos
                        if ($menorPreco !== null && $maiorPreco !== null && is_numeric($menorPreco) && is_numeric($maiorPreco)):
                        ?>
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-tag"></i> FAIXA DE PREÇO*</div>
                            <div class="detalhe-valor">
                                <?php echo formatCurrency($menorPreco); ?> a <?php echo formatCurrency($maiorPreco); ?>
                                <br>*Consultar unidades disponíveis
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Armazena os valores em variáveis
                        $metragemMin = $empreendimento['MetragemMin'] ?? null;
                        $metragemMax = $empreendimento['MetragemMax'] ?? null;

                        // Verifica se pelo menos um dos campos tem um valor válido
                        if (($metragemMin !== null && $metragemMin > 0) || ($metragemMax !== null && $metragemMax > 0)):
                        ?>
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-ruler-combined"></i> FAIXA DE METRAGEM</div>
                            <div class="detalhe-valor">
                                <?php
                                if (($metragemMin !== null && $metragemMin > 0) && ($metragemMax !== null && $metragemMax > 0)) {
                                    echo htmlspecialchars($metragemMin) . "m² a " . htmlspecialchars($metragemMax) . "m²";
                                } elseif ($metragemMin !== null && $metragemMin > 0) {
                                    echo htmlspecialchars($metragemMin) . "m²";
                                } elseif ($metragemMax !== null && $metragemMax > 0) {
                                    echo htmlspecialchars($metragemMax) . "m²";
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Armazena os valores em variáveis para facilitar a verificação
                        $beiraMar = $empreendimento['BeiraMar'] ?? null;
                        $proximoMar = $empreendimento['ProximoMar'] ?? null;

                        // Verifica se a informação deve ser exibida
                        if (($beiraMar !== null && $beiraMar == 1) || ($proximoMar !== null && is_numeric($proximoMar) && $proximoMar > 0)):
                        ?>
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-umbrella-beach"></i> DISTÂNCIA PARA O MAR</div>
                            <div class="detalhe-valor">
                                <?php if ($beiraMar !== null && $beiraMar == 1): ?>
                                    <span class="badge badge-info badge-status"><i class="fas fa-check-circle"></i> BEIRA MAR</span>
                                <?php elseif ($proximoMar > 0): ?>
                                    <span><?php echo htmlspecialchars(number_format($proximoMar, 0, ',', '.')); ?> metros</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detalhe-item">
                            <div class="detalhe-titulo"><i class="fas fa-car"></i> GARAGEM</div>
                            <div class="detalhe-valor">
                                <?php 
                                $tipoGaragem = trim($empreendimento['TipoGaragem'] ?? '');
                                
                                if (!empty($empreendimento['VagasGaragem']) && $empreendimento['VagasGaragem'] > 0) {
                                    echo htmlspecialchars($empreendimento['VagasGaragem']) . " vaga(s)";
                                    if (!empty($tipoGaragem)) {
                                        echo " - " . htmlspecialchars($tipoGaragem);
                                    }
                                } else {
                                    echo "Não informado";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section-title">
                    <i class="fas fa-list-check"></i> CARACTERÍSTICAS DO EMPREENDIMENTO
                </div>
                
                <div class="amenities-container">
                    <?php if (!empty($empreendimento['BeiraMar']) && $empreendimento['BeiraMar'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-umbrella-beach"></i>
                            <span>Beira Mar</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Academia']) && $empreendimento['Academia'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-dumbbell"></i>
                            <span>Academia</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Brinquedoteca']) && $empreendimento['Brinquedoteca'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-car"></i>
                            <span>Brinquedoteca</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Coworking']) && $empreendimento['Coworking'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-laptop-code"></i>
                            <span>Coworking</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Emporio']) && $empreendimento['Emporio'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-store"></i>
                            <span>Empório</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['EspacoGourmet']) && $empreendimento['EspacoGourmet'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-utensils"></i>
                            <span>Espaço Gourmet</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['LoungeBar']) && $empreendimento['LoungeBar'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-cocktail"></i>
                            <span>Lounge Bar</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['PetPlace']) && $empreendimento['PetPlace'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-paw"></i>
                            <span>Pet Place</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Piscina2']) && $empreendimento['Piscina2'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-swimming-pool"></i>
                            <span>Piscina</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['QuadraTenis']) && $empreendimento['QuadraTenis'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-table-tennis-paddle-ball"></i>
                            <span>Quadra de Tênis</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Recepcao24h']) && $empreendimento['Recepcao24h'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-clock"></i>
                            <span>Recepção 24h</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Restaurante']) && $empreendimento['Restaurante'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-utensils"></i>
                            <span>Restaurante</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['RoofTop']) && $empreendimento['RoofTop'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-building"></i>
                            <span>Roof Top</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['SalaoJogos']) && $empreendimento['SalaoJogos'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-gamepad"></i>
                            <span>Salão de Jogos</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Sauna']) && $empreendimento['Sauna'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-hot-tub-person"></i>
                            <span>Sauna</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Spa']) && $empreendimento['Spa'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-spa"></i>
                            <span>Spa</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Varanda']) && $empreendimento['Varanda'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-person-shelter"></i>
                            <span>Varanda</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['VarandaGourmet']) && $empreendimento['VarandaGourmet'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-grill-hot"></i>
                            <span>Varanda Gourmet</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['GestoraAluguel']) && $empreendimento['GestoraAluguel'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-key"></i>
                            <span>Gestora de Aluguel</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Lavanderia']) && $empreendimento['Lavanderia'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-soap"></i>
                            <span>Lavanderia</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Bicicletario']) && $empreendimento['Bicicletario'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-bicycle"></i>
                            <span>Bicicletário</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['Hidromassagem']) && $empreendimento['Hidromassagem'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-water-ladder"></i>
                            <span>Hidromassagem</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['GasEncanado']) && $empreendimento['GasEncanado'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-fire"></i>
                            <span>Gás Encanado</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['PiscinaPrivativa']) && $empreendimento['PiscinaPrivativa'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-water"></i>
                            <span>Piscina Privativa</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($empreendimento['FechaduraEletronica']) && $empreendimento['FechaduraEletronica'] == 1): ?>
                        <div class="amenity-item">
                            <i class="fas fa-lock"></i>
                            <span>Fechadura Eletrônica</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty(trim($empreendimento['Descricao'] ?? '')) && strlen(trim($empreendimento['Descricao'])) > 2): ?>
                    <div class="section-title">
                        <i class="fas fa-list-check"></i> DESCRIÇÃO
                    </div>
                    <div class="desc-container">
                        <?php
                        $descricao = trim($empreendimento['Descricao']);
                        if (strlen($descricao) > 2) {
                            echo nl2br(htmlspecialchars($descricao));
                        } else {
                            echo "Descrição não disponível.";
                        }
                        ?>
                    </div>    
                <?php endif; ?>
                
                <div class="data-hora">
                    Data: <?php echo htmlspecialchars($dataAtual); ?> | Hora: <?php echo htmlspecialchars($horaAtual); ?>
                </div>
            </div>
        </div>

        <div class="btn-container">
            <a href="javascript:window.close();" class="btn btn-primary btn-voltar">
                <i class="fas fa-times"></i> Fechar
            </a>
            <button id="btnGerarPDF" class="btn btn-pdf btn-voltar">
                <i class="fas fa-file-pdf"></i> Salvar como PDF
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Função para aguardar o carregamento de todas as imagens
        function waitForImages(element) {
            return new Promise((resolve, reject) => {
                const images = element.getElementsByTagName('img');
                let loadedCount = 0;
                const totalImages = images.length;

                if (totalImages === 0) {
                    resolve();
                    return;
                }

                const onImageLoad = () => {
                    loadedCount++;
                    if (loadedCount === totalImages) {
                        resolve();
                    }
                };

                const onImageError = () => {
                    loadedCount++;
                    if (loadedCount === totalImages) {
                        resolve();
                    }
                };

                for (let img of images) {
                    if (img.complete) {
                        loadedCount++;
                        if (loadedCount === totalImages) {
                            resolve();
                        }
                    } else {
                        img.addEventListener('load', onImageLoad);
                        img.addEventListener('error', onImageError);
                    }
                }
            });
        }

        function gerarPDF() {
            const button = document.getElementById('btnGerarPDF');
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando PDF...';
            button.disabled = true;

            const element = document.getElementById('fichaParaPDF');
            
            // Configurar dimensões para A4 (210mm x 297mm)
            const a4WidthPx = 794; // 210mm em pixels (96dpi)
            const a4HeightPx = 1123; // 297mm em pixels (96dpi)
            
            // Forçar dimensões fixas para PDF
            element.style.width = `${a4WidthPx}px`;
            element.style.overflow = 'visible';
            
            // Aguardar o carregamento de imagens antes de gerar o PDF
            waitForImages(element).then(() => {
                const options = {
                    scale: 1.5, // Reduzir escala para melhor desempenho em móveis
                    useCORS: true,
                    allowTaint: true,
                    logging: false,
                    width: a4WidthPx,
                    height: element.scrollHeight
                };
                
                html2canvas(element, options).then(canvas => {
                    const imgData = canvas.toDataURL('image/jpeg', 0.95); // Usar JPEG para melhor desempenho
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF({
                        orientation: 'portrait',
                        unit: 'mm',
                        format: 'a4'
                    });
                    
                    const pdfWidth = pdf.internal.pageSize.getWidth();
                    const pdfHeight = pdf.internal.pageSize.getHeight();
                    const imgProps = pdf.getImageProperties(imgData);
                    let imgHeight = (imgProps.height * pdfWidth) / imgProps.width;
                    
                    // Escalar para caber em uma única página A4
                    if (imgHeight > pdfHeight) {
                        imgHeight = pdfHeight;
                    }
                    
                    pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, imgHeight);
                    pdf.save('<?php echo htmlspecialchars($nomeArquivoPDF); ?>');
                    button.innerHTML = '<i class="fas fa-file-pdf"></i> Salvar como PDF';
                    button.disabled = false;
                    
                    // Restaurar estilos
                    element.style.width = '';
                    element.style.overflow = '';
                }).catch(error => {
                    console.error('Erro ao gerar PDF:', error);
                    alert('Ocorreu um erro ao gerar o PDF. Por favor, tente novamente.');
                    button.innerHTML = '<i class="fas fa-file-pdf"></i> Salvar como PDF';
                    button.disabled = false;
                    element.style.width = '';
                    element.style.overflow = '';
                });
            }).catch(error => {
                console.error('Erro ao carregar imagens:', error);
                alert('Erro ao carregar imagens. Por favor, tente novamente.');
                button.innerHTML = '<i class="fas fa-file-pdf"></i> Salvar como PDF';
                button.disabled = false;
                element.style.width = '';
                element.style.overflow = '';
            });
        }
        
        window.jsPDF = window.jspdf.jsPDF;
        
        // Adicionar evento de clique ao botão sem jQuery
        document.getElementById('btnGerarPDF').addEventListener('click', gerarPDF);
    </script>
    <?php 
    // Incluir footer se existir
    if (file_exists('footer_ficha.inc')) {
        include 'footer_ficha.inc';
    }
    ?>
</body>
</html>
<?php
// Fechar a conexão (opcional se usar conn.php com conexão persistente)
// $conn = null;
?>
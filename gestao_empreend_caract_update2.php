<?php
session_start();

require_once 'conn.php';

// ==================== FUNÇÕES AUXILIARES ====================
function usuarioPodeEditar() {
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

// ==================== FUNÇÃO DE ENVIO DE EMAIL ====================
function enviarEmailAtualizacao($empreendimentoId, $nomeEmpreendimento, $usuario, $alteracoes, $sql, $dadosAntigos, $dadosNovos) {
    $to = "valterpb@gmail.com, valterpb@hotmail.com";
    $subject = "[SI-CARAC] " . htmlspecialchars($nomeEmpreendimento) . " - ID: " . $empreendimentoId;
    
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    $headers .= "Reply-To: sendmail@gabnetweb.com.br\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Montar o corpo do email em HTML
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Atualização de Empreendimento</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
            .container { background-color: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 900px; margin: 0 auto; }
            .header { background-color: #800000; color: white; padding: 20px; border-radius: 5px; margin-bottom: 25px; }
            h1 { margin: 0; font-size: 22px; font-weight: bold; }
            h2 { color: #333; border-bottom: 2px solid #800000; padding-bottom: 10px; margin-top: 25px; font-size: 18px; }
            .info-box { background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 18px; margin: 20px 0; border-radius: 5px; }
            .field-change { background-color: #fff3cd; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ffc107; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
            th { background-color: #800000; color: white; padding: 12px; text-align: left; font-weight: bold; }
            td { padding: 12px; border-bottom: 1px solid #ddd; }
            .timestamp { color: #666; font-size: 13px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #ddd; }
            .btn { display: inline-block; background-color: #800000; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 15px; font-weight: bold; }
            .ip-address { background-color: #e9ecef; padding: 6px 12px; border-radius: 4px; font-family: monospace; font-size: 14px; }
            .changed-field { background-color: #fff3cd; }
            .no-changes { background-color: #d4edda; padding: 15px; border-radius: 5px; text-align: center; }
            .sql-box { background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-family: "Courier New", monospace; font-size: 13px; overflow-x: auto; border: 1px solid #ddd; }
            .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
            .badge-old { background-color: #f8d7da; color: #721c24; }
            .badge-new { background-color: #d4edda; color: #155724; }
            .summary { display: flex; justify-content: space-between; background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .summary-item { text-align: center; }
            .summary-number { font-size: 24px; font-weight: bold; color: #800000; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📋 Atualização de Empreendimento - Sistema SunnyImob</h1>
                <p>Notificação automática de alterações no sistema</p>
            </div>
            
            <div class="info-box">
                <p><strong>🆔 ID do Empreendimento:</strong> ' . $empreendimentoId . '</p>
                <p><strong>🏢 Nome:</strong> <strong style="color: #800000;">' . htmlspecialchars($nomeEmpreendimento) . '</strong></p>
                <p><strong>👤 Usuário responsável:</strong> <strong>' . htmlspecialchars($usuario) . '</strong></p>
                <p><strong>🌐 IP de origem:</strong> <span class="ip-address">' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . '</span></p>
                <p><strong>🕐 Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</p>
            </div>';
    
    // Resumo das alterações
    $totalAlteracoes = count($alteracoes);
    $body .= '
            <div class="summary">
                <div class="summary-item">
                    <div class="summary-number">' . $totalAlteracoes . '</div>
                    <div>Campos Alterados</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">' . date('H:i') . '</div>
                    <div>Horário</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">' . date('d/m') . '</div>
                    <div>Data</div>
                </div>
            </div>';
    
    $body .= '<h2>🔍 Alterações Detalhadas</h2>';
    
    // Listar alterações específicas
    if (!empty($alteracoes)) {
        $body .= '<div style="margin-bottom: 20px;">';
        foreach ($alteracoes as $campo => $valores) {
            $body .= '<div class="field-change">';
            $body .= '<strong style="font-size: 15px;">' . htmlspecialchars($campo) . '</strong><br>';
            $body .= '<span class="badge badge-old">Antigo: ' . htmlspecialchars($valores['antigo']) . '</span> ';
            $body .= '→ ';
            $body .= '<span class="badge badge-new">Novo: ' . htmlspecialchars($valores['novo']) . '</span>';
            $body .= '</div>';
        }
        $body .= '</div>';
    } else {
        $body .= '<div class="no-changes">
                    <p style="font-size: 16px;">✅ Nenhuma alteração de valor detectada</p>
                    <p><em>Apenas campos de data/hora foram atualizados automaticamente.</em></p>
                  </div>';
    }
    
    // Tabela de comparação completa
    $body .= '
            <h2>📊 Comparação Completa dos Valores</h2>
            <table>
                <thead>
                    <tr>
                        <th width="25%">Campo</th>
                        <th width="37%" style="background-color: #f8d7da;">Valor Antigo</th>
                        <th width="37%" style="background-color: #d4edda;">Valor Novo</th>
                    </tr>
                </thead>
                <tbody>';
    
    // Lista de campos para comparar
    $camposParaComparar = [
        'Localidade' => 'Localidade',
        'Suites' => 'Suítes',
        'WCTotal' => 'Banheiros Total',
        'VagasGaragem' => 'Vagas Garagem',
        'TipoGaragem' => 'Tipo Garagem',
        'ValorCond' => 'Valor Condomínio',
        'ValorIPTU' => 'Valor IPTU',
        'MetragemMin' => 'Metragem Mínima (m²)',
        'MetragemMax' => 'Metragem Máxima (m²)',
        'Tipologia' => 'Tipologia',
        'Descricao' => 'Descrição',
        'Entregue' => 'Entregue',
        'Vendido' => 'Vendido',
        'Ocultar' => 'Ocultar',
        'MesEntrega' => 'Mês Entrega',
        'AnoEntrega' => 'Ano Entrega',
        'ProximoMar' => 'Distância do Mar (m)',
        'Pavimentos' => 'Pavimentos',
        'QTD_Blocos' => 'Qtd. Blocos',
        'QTD_Unidades' => 'Qtd. Unidades'
    ];
    
    foreach ($camposParaComparar as $campo => $label) {
        $antigo = isset($dadosAntigos[$campo]) ? $dadosAntigos[$campo] : '';
        $novo = isset($dadosNovos[$campo]) ? $dadosNovos[$campo] : '';
        
        // Verificar se é um campo que foi enviado no POST
        if (!array_key_exists($campo, $dadosNovos) && $campo != 'DataAtualizacao' && $campo != 'UsuarioAtu') {
            $novo = $antigo; // Mantém o valor antigo se não foi alterado
        }
        
        // Formatando valores booleanos
        if (in_array($campo, ['Entregue', 'Vendido', 'Ocultar'])) {
            $antigo = $antigo ? '✅ SIM' : '❌ NÃO';
            $novo = $novo ? '✅ SIM' : '❌ NÃO';
        }
        
        // Formatando valores monetários
        if (in_array($campo, ['ValorCond', 'ValorIPTU'])) {
            if (is_numeric($antigo)) {
                $antigo = 'R$ ' . number_format(floatval($antigo), 2, ',', '.');
            }
            if (is_numeric($novo)) {
                $novo = 'R$ ' . number_format(floatval($novo), 2, ',', '.');
            }
        }
        
        // Formatando datas
        if ($campo == 'DataEntrega' && !empty($antigo) && $antigo != '2035-01-01') {
            $antigo = date('d/m/Y', strtotime($antigo));
        }
        if ($campo == 'DataEntrega' && !empty($novo) && $novo != '2035-01-01') {
            $novo = date('d/m/Y', strtotime($novo));
        }
        
        // Formatando meses
        if ($campo == 'MesEntrega' && !empty($antigo)) {
            $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            $antigo = isset($meses[$antigo]) ? $meses[$antigo] . " ($antigo)" : $antigo;
        }
        if ($campo == 'MesEntrega' && !empty($novo)) {
            $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            $novo = isset($meses[$novo]) ? $meses[$novo] . " ($novo)" : $novo;
        }
        
        // Verificar se houve alteração
        $alterado = ($antigo != $novo);
        $rowClass = $alterado ? 'class="changed-field"' : '';
        
        $body .= '
                <tr ' . $rowClass . '>
                    <td><strong>' . $label . '</strong></td>
                    <td style="background-color: ' . ($alterado ? '#f8d7da' : 'transparent') . ';">' . htmlspecialchars($antigo) . '</td>
                    <td style="background-color: ' . ($alterado ? '#d4edda' : 'transparent') . ';">' . htmlspecialchars($novo) . '</td>
                </tr>';
    }
    
    // Adicionar comodidades alteradas
    $camposBooleanos = [
        'BeiraMar', 'RoofTop', 'Academia', 'Sauna', 'PetPlace', 'EspacoGourmet',
        'Restaurante', 'Coworking', 'QuadraTenis', 'Emporio', 'Piscina2', 'Spa',
        'Recepcao24h', 'LoungeBar', 'Brinquedoteca', 'Varanda', 'VarandaGourmet',
        'Salaojogos', 'GestoraAluguel', 'Lavanderia', 'Bicicletario', 'Hidromassagem',
        'GasEncanado', 'PiscinaPrivativa', 'FechaduraEletronica'
    ];
    
    $comodidadesAlteradas = [];
    foreach ($camposBooleanos as $campo) {
        $antigo = isset($dadosAntigos[$campo]) ? $dadosAntigos[$campo] : 0;
        $novo = isset($dadosNovos[$campo]) ? $dadosNovos[$campo] : 0;
        
        if ($antigo != $novo) {
            $comodidadesAlteradas[] = [
                'nome' => str_replace(['_', '2'], [' ', ''], $campo),
                'antigo' => $antigo ? '✅ Ativo' : '❌ Inativo',
                'novo' => $novo ? '✅ Ativo' : '❌ Inativo'
            ];
        }
    }
    
    if (!empty($comodidadesAlteradas)) {
        $body .= '
                <tr>
                    <td colspan="3" style="background-color: #e7f1ff; padding: 15px; text-align: center;">
                        <strong>🏊 Comodidades Alteradas</strong>
                    </td>
                </tr>';
        
        foreach ($comodidadesAlteradas as $comodidade) {
            $body .= '
                <tr class="changed-field">
                    <td><em>' . $comodidade['nome'] . '</em></td>
                    <td style="background-color: #f8d7da;">' . $comodidade['antigo'] . '</td>
                    <td style="background-color: #d4edda;">' . $comodidade['novo'] . '</td>
                </tr>';
        }
    }
    
    $body .= '
                </tbody>
            </table>
            
            <h2>🔧 SQL Executado</h2>
            <div class="sql-box">
                <pre style="margin: 0;">' . htmlspecialchars($sql) . '</pre>
            </div>
            
            <div class="timestamp">
                <p><strong>📧 Este email foi gerado automaticamente pelo sistema SunnyImob.</strong></p>
                <p>⚠️ Não responda a este email. Em caso de dúvidas, entre em contato com o administrador do sistema.</p>
                <p>🛡️ Registro de auditoria para controle de alterações.</p>
                
                <div style="margin-top: 25px; text-align: center;">
                    <a href="https://seusistema.com.br/admin/empreendimento.php?id=' . $empreendimentoId . '" class="btn">🔗 Acessar Empreendimento no Sistema</a>
                </div>
                
                <div style="margin-top: 20px; font-size: 12px; color: #999; text-align: center;">
                    <p>Sistema SunnyImob &copy; ' . date('Y') . ' - Todos os direitos reservados</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    // Enviar email
    return mail($to, $subject, $body, $headers);
}

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// ==================== OBTER ID DO EMPREENDIMENTO ====================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ==================== BUSCAR DADOS ANTIGOS ====================
try {
    $stmt = $pdo->prepare("SELECT * FROM empreendimento WHERE Empreend_ID = :id");
    $stmt->execute([':id' => $id]);
    $dadosAntigos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosAntigos) {
        die("Empreendimento não encontrado");
    }
} catch(PDOException $e) {
    die("Erro ao buscar empreendimento: " . $e->getMessage());
}

// ==================== PROCESSAR POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Arrays para armazenar alterações e dados novos
    $alteracoesDetectadas = [];
    $dadosNovos = [];
    
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
    $alteracoes = [];
    
    // ==================== COMPARAR CAMPOS ====================
    
    // Localidade
    if (!empty($localidade) && $localidade != 'X') {
        if ($dadosAntigos['Localidade'] != $localidade) {
            $alteracoes['Localidade'] = [
                'antigo' => $dadosAntigos['Localidade'] ?? '',
                'novo' => $localidade
            ];
        }
        $setClauses[] = "Localidade = :localidade";
        $params[':localidade'] = $localidade;
        $dadosNovos['Localidade'] = $localidade;
    }
    
    // Suites
    if (!is_null($suites) && $suites > 0) {
        if ($dadosAntigos['Suites'] != $suites) {
            $alteracoes['Suites'] = [
                'antigo' => $dadosAntigos['Suites'] ?? '',
                'novo' => $suites
            ];
        }
        $setClauses[] = "Suites = :suites";
        $params[':suites'] = $suites;
        $dadosNovos['Suites'] = $suites;
    }
    
    // WCTotal
    if (!is_null($wcTotal) && $wcTotal > 0) {
        if ($dadosAntigos['WCTotal'] != $wcTotal) {
            $alteracoes['WCTotal'] = [
                'antigo' => $dadosAntigos['WCTotal'] ?? '',
                'novo' => $wcTotal
            ];
        }
        $setClauses[] = "WCTotal = :wctotal";
        $params[':wctotal'] = $wcTotal;
        $dadosNovos['WCTotal'] = $wcTotal;
    }
    
    // DataEntrega
    if (!empty($dataEntrega) && $dataEntrega != '2035-01-01') {
        if ($dadosAntigos['DataEntrega'] != $dataEntrega) {
            $alteracoes['DataEntrega'] = [
                'antigo' => $dadosAntigos['DataEntrega'] ?? '',
                'novo' => $dataEntrega
            ];
        }
        $setClauses[] = "DataEntrega = :data_entrega";
        $params[':data_entrega'] = $dataEntrega;
        $dadosNovos['DataEntrega'] = $dataEntrega;
    }
    
    // TipoGaragem
    if (!empty($tipoGaragem)) {
        if ($dadosAntigos['TipoGaragem'] != $tipoGaragem) {
            $alteracoes['TipoGaragem'] = [
                'antigo' => $dadosAntigos['TipoGaragem'] ?? '',
                'novo' => $tipoGaragem
            ];
        }
        $setClauses[] = "TipoGaragem = :tipo_garagem";
        $params[':tipo_garagem'] = $tipoGaragem;
        $dadosNovos['TipoGaragem'] = $tipoGaragem;
    }
    
    // VagasGaragem
    if (!is_null($vagasGaragem) && $vagasGaragem > 0) {
        if ($dadosAntigos['VagasGaragem'] != $vagasGaragem) {
            $alteracoes['VagasGaragem'] = [
                'antigo' => $dadosAntigos['VagasGaragem'] ?? '',
                'novo' => $vagasGaragem
            ];
        }
        $setClauses[] = "VagasGaragem = :vagas_garagem";
        $params[':vagas_garagem'] = $vagasGaragem;
        $dadosNovos['VagasGaragem'] = $vagasGaragem;
    }
    
    // Tratar valores monetários
    if (!empty($valorCond)) {
      //  $valorCondFormatado = formatarParaDecimalPonto($valorCond);
      $valorCondFormatado = $valorCond;
        if ($dadosAntigos['ValorCond'] != $valorCondFormatado) {
            $alteracoes['ValorCond'] = [
                'antigo' => $dadosAntigos['ValorCond'] ?? '',
                'novo' => $valorCondFormatado
            ];
        }
        $setClauses[] = "ValorCond = :valor_cond";
        $params[':valor_cond'] = $valorCondFormatado;
        $dadosNovos['ValorCond'] = $valorCondFormatado;
    }
    
    if (!empty($valorIPTU)) {
      //  $valorIPTUFormatado = formatarParaDecimalPonto($valorIPTU);
      $valorIPTUFormatado = $valorIPTU;
        if ($dadosAntigos['ValorIPTU'] != $valorIPTUFormatado) {
            $alteracoes['ValorIPTU'] = [
                'antigo' => $dadosAntigos['ValorIPTU'] ?? '',
                'novo' => $valorIPTUFormatado
            ];
        }
        $setClauses[] = "ValorIPTU = :valor_iptu";
        $params[':valor_iptu'] = $valorIPTUFormatado;
        $dadosNovos['ValorIPTU'] = $valorIPTUFormatado;
    }
    
    if (!empty($menorPreco)) {
      //  $menorPrecoFormatado = formatarParaDecimalPonto($menorPreco);
      $menorPrecoFormatado = $menorPreco;
        if ($dadosAntigos['MenorPreco'] != $menorPrecoFormatado) {
            $alteracoes['MenorPreco'] = [
                'antigo' => $dadosAntigos['MenorPreco'] ?? '',
                'novo' => $menorPrecoFormatado
            ];
        }
        $setClauses[] = "MenorPreco = :menor_preco";
        $params[':menor_preco'] = $menorPrecoFormatado;
        $dadosNovos['MenorPreco'] = $menorPrecoFormatado;
    }
    
    if (!empty($maiorPreco)) {
       // $maiorPrecoFormatado = formatarParaDecimalPonto($maiorPreco);
       $maiorPrecoFormatado = $maiorPreco;
        if ($dadosAntigos['MaiorPreco'] != $maiorPrecoFormatado) {
            $alteracoes['MaiorPreco'] = [
                'antigo' => $dadosAntigos['MaiorPreco'] ?? '',
                'novo' => $maiorPrecoFormatado
            ];
        }
        $setClauses[] = "MaiorPreco = :maior_preco";
        $params[':maior_preco'] = $maiorPrecoFormatado;
        $dadosNovos['MaiorPreco'] = $maiorPrecoFormatado;
    }
    
    // Metragens
    if (!empty($metragemMin)) {
        $metragemMinFormatada = str_replace(',', '.', $metragemMin);
        if ($dadosAntigos['MetragemMin'] != $metragemMinFormatada) {
            $alteracoes['MetragemMin'] = [
                'antigo' => $dadosAntigos['MetragemMin'] ?? '',
                'novo' => $metragemMinFormatada
            ];
        }
        $setClauses[] = "MetragemMin = :metragem_min";
        $params[':metragem_min'] = $metragemMinFormatada;
        $dadosNovos['MetragemMin'] = $metragemMinFormatada;
    }
    
    if (!empty($metragemMax)) {
        $metragemMaxFormatada = str_replace(',', '.', $metragemMax);
        if ($dadosAntigos['MetragemMax'] != $metragemMaxFormatada) {
            $alteracoes['MetragemMax'] = [
                'antigo' => $dadosAntigos['MetragemMax'] ?? '',
                'novo' => $metragemMaxFormatada
            ];
        }
        $setClauses[] = "MetragemMax = :metragem_max";
        $params[':metragem_max'] = $metragemMaxFormatada;
        $dadosNovos['MetragemMax'] = $metragemMaxFormatada;
    }
    
    // Tipologia
    if (!empty($tipologia)) {
        if ($dadosAntigos['Tipologia'] != $tipologia) {
            $alteracoes['Tipologia'] = [
                'antigo' => $dadosAntigos['Tipologia'] ?? '',
                'novo' => $tipologia
            ];
        }
        $setClauses[] = "Tipologia = :tipologia";
        $params[':tipologia'] = $tipologia;
        $dadosNovos['Tipologia'] = $tipologia;
    }
    
    // Descrição
    if (!empty($descricao)) {
        if ($dadosAntigos['Descricao'] != $descricao) {
            $alteracoes['Descricao'] = [
                'antigo' => $dadosAntigos['Descricao'] ?? '',
                'novo' => $descricao
            ];
        }
        $setClauses[] = "Descricao = :descricao";
        $params[':descricao'] = $descricao;
        $dadosNovos['Descricao'] = $descricao;
    }
    
    // MesEntrega
    if (!is_null($mesEntrega) && $mesEntrega > 0) {
        if ($dadosAntigos['MesEntrega'] != $mesEntrega) {
            $alteracoes['MesEntrega'] = [
                'antigo' => $dadosAntigos['MesEntrega'] ?? '',
                'novo' => $mesEntrega
            ];
        }
        $setClauses[] = "MesEntrega = :mes_entrega";
        $params[':mes_entrega'] = $mesEntrega;
        $dadosNovos['MesEntrega'] = $mesEntrega;
    }
    
    // AnoEntrega
    if (!is_null($anoEntrega) && $anoEntrega > 0) {
        if ($dadosAntigos['AnoEntrega'] != $anoEntrega) {
            $alteracoes['AnoEntrega'] = [
                'antigo' => $dadosAntigos['AnoEntrega'] ?? '',
                'novo' => $anoEntrega
            ];
        }
        $setClauses[] = "AnoEntrega = :ano_entrega";
        $params[':ano_entrega'] = $anoEntrega;
        $dadosNovos['AnoEntrega'] = $anoEntrega;
    }
    
    // ProximoMar
    if (!is_null($proximoMar) && $proximoMar > 0) {
        if ($dadosAntigos['ProximoMar'] != $proximoMar) {
            $alteracoes['ProximoMar'] = [
                'antigo' => $dadosAntigos['ProximoMar'] ?? '',
                'novo' => $proximoMar
            ];
        }
        $setClauses[] = "ProximoMar = :proximo_mar";
        $params[':proximo_mar'] = $proximoMar;
        $dadosNovos['ProximoMar'] = $proximoMar;
    }
    
    // Pavimentos
    if (!is_null($pavimentos) && $pavimentos > 0) {
        if ($dadosAntigos['Pavimentos'] != $pavimentos) {
            $alteracoes['Pavimentos'] = [
                'antigo' => $dadosAntigos['Pavimentos'] ?? '',
                'novo' => $pavimentos
            ];
        }
        $setClauses[] = "Pavimentos = :pavimentos";
        $params[':pavimentos'] = $pavimentos;
        $dadosNovos['Pavimentos'] = $pavimentos;
    }
    
    // QTD_Blocos
    if ($qtdBlocos > 0) {
        if ($dadosAntigos['QTD_Blocos'] != $qtdBlocos) {
            $alteracoes['QTD_Blocos'] = [
                'antigo' => $dadosAntigos['QTD_Blocos'] ?? '',
                'novo' => $qtdBlocos
            ];
        }
        $setClauses[] = "QTD_Blocos = :qtd_blocos";
        $params[':qtd_blocos'] = $qtdBlocos;
        $dadosNovos['QTD_Blocos'] = $qtdBlocos;
    }
    
    // QTD_Unidades
    if ($qtdUnidades > 0) {
        if ($dadosAntigos['QTD_Unidades'] != $qtdUnidades) {
            $alteracoes['QTD_Unidades'] = [
                'antigo' => $dadosAntigos['QTD_Unidades'] ?? '',
                'novo' => $qtdUnidades
            ];
        }
        $setClauses[] = "QTD_Unidades = :qtd_unidades";
        $params[':qtd_unidades'] = $qtdUnidades;
        $dadosNovos['QTD_Unidades'] = $qtdUnidades;
    }
    
    // Adicionar campos booleanos
    foreach ($camposBooleanos as $campo) {
        $valor = isset($_POST[$campo]) ? getCheckboxValue($_POST[$campo]) : 0;
        $valorAntigo = $dadosAntigos[$campo] ?? 0;
        
        if ($valor != $valorAntigo) {
            $alteracoes[$campo] = [
                'antigo' => $valorAntigo ? 'Sim' : 'Não',
                'novo' => $valor ? 'Sim' : 'Não'
            ];
        }
        
        $setClauses[] = "$campo = :$campo";
        $params[":$campo"] = $valor;
        $dadosNovos[$campo] = $valor;
    }
    
    // Campos adicionais (sempre atualizados)
    $setClauses[] = "DataAtualizacao = NOW()";
    $setClauses[] = "UsuarioAtu = :usuario_atu";
    $params[':usuario_atu'] = $_SESSION['Usuario'];
    $dadosNovos['DataAtualizacao'] = date('Y-m-d H:i:s');
    $dadosNovos['UsuarioAtu'] = $_SESSION['Usuario'];
    
    // ==================== EXECUTAR UPDATE ====================
    if (!empty($setClauses)) {
        try {
            $sql = "UPDATE empreendimento SET " . implode(', ', $setClauses) . " WHERE Empreend_ID = :id";
            $params[':id'] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // ==================== ENVIAR EMAIL DE NOTIFICAÇÃO ====================
            $nomeEmpreendimento = $dadosAntigos['NomeEmpreendimento'];
            $usuario = $_SESSION['Usuario'];
            
            // Verificar se não é localhost para enviar email
            $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($remote_addr !== '127.0.0.1' && $remote_addr !== '::1') {
                $emailEnviado = enviarEmailAtualizacao(
                    $id,
                    $nomeEmpreendimento,
                    $usuario,
                    $alteracoes,
                    $sql,
                    $dadosAntigos,
                    $dadosNovos
                );
                
                $_SESSION['mensagem'] = "✅ Dados atualizados com sucesso!" . ($emailEnviado ? " Email de notificação enviado." : " Falha no envio do email.");
                $_SESSION['tipo_mensagem'] = "success";
            } else {
                // Em ambiente local, apenas mostrar mensagem de sucesso
                $_SESSION['mensagem'] = "✅ Dados atualizados com sucesso! (Ambiente local - Email não enviado)";
                $_SESSION['tipo_mensagem'] = "success";
            }
            
            // Registrar histórico (implemente conforme necessário)
            // registrarHistorico($id, $_POST['idEmpresa'], "Atualização das Características");
            
        } catch(PDOException $e) {
            $_SESSION['mensagem'] = "❌ Erro ao atualizar: " . $e->getMessage();
            $_SESSION['tipo_mensagem'] = "danger";
        }
    }
}

// ==================== BUSCAR DADOS ATUALIZADOS ====================
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

// ==================== FORMATAR VALORES PARA EXIBIÇÃO ====================
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
        .email-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 300px;
            display: none;
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
        
        <!-- Notificação de email enviado -->
        <div id="emailNotification" class="email-notification alert alert-success alert-dismissible fade show">
            <i class="fas fa-envelope me-2"></i>
            <strong>Email enviado!</strong>
            <p class="mb-0">Uma notificação foi enviada para os administradores.</p>
            <button type="button" class="btn-close" onclick="hideEmailNotification()"></button>
        </div>
        
        <header class="container-fluid py-3 mb-4 border-bottom" style="background-color: #800000; color: white;">
            <div class="container">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-2 mb-md-0 d-flex align-items-center">
                        <img src="img/logotocca.jpg" alt="Logotipo Tocca" style="height: 50px; margin-right: 15px;">
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
                <?php if ($_SESSION['tipo_mensagem'] == 'success' && strpos($_SESSION['mensagem'], 'Email') !== false): ?>
                    <i class="fas fa-envelope me-2"></i>
                <?php endif; ?>
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
                    <p class="card-text text-muted">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Última atualização: <?php echo date('d/m/Y H:i', strtotime($empreendimento['DataAtualizacao'])); ?><br>
                            <i class="fas fa-user me-1"></i>
                            Por: <?php echo htmlspecialchars($empreendimento['UsuarioAtu']); ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>
        
        <form method="post" id="formEdicao">
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
                    <button type="submit" class="btn btn-primary" onclick="showEmailNotification()">
                        <i class="fas fa-save me-1"></i> Atualizar Características
                    </button>
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
    
    function showEmailNotification() {
        // Mostrar notificação de email enviado
        setTimeout(function() {
            document.getElementById('emailNotification').style.display = 'block';
        }, 1000);
    }
    
    function hideEmailNotification() {
        document.getElementById('emailNotification').style.display = 'none';
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
        
        // Esconder notificação de email após 5 segundos
        setTimeout(function() {
            hideEmailNotification();
        }, 5000);
    });
    
    // Script do contador de sessão
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
            window.location.href = "login_p1.php?timeout=1";
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        updateTimer();
        
        // Mostrar notificação se o formulário foi enviado com sucesso
        <?php if (isset($_SESSION['mensagem']) && $_SESSION['tipo_mensagem'] == 'success'): ?>
            setTimeout(function() {
                showEmailNotification();
            }, 500);
        <?php endif; ?>
    });
    </script>
</body>
</html>
<?php
// salvar_fluxoAPIX7d.php - Processa o envio do formulário
require_once 'conn.php';
//require_once 'log_navegacao.php';

// Configurações
header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Inicia transação
        $pdo->beginTransaction();
        
        // Coletar dados básicos
        $data = [
            'data' => $_POST['data'] ?? date('Y-m-d'),
            'empreendimento_id' => $_POST['empreendimento'] ?? null,
            'cliente' => strtoupper($_POST['cliente'] ?? ''),
            'corretor' => strtoupper($_POST['corretor'] ?? ''),
            'unidade' => strtoupper($_POST['unidade'] ?? ''),
            'm2' => str_replace(',', '.', $_POST['m2'] ?? '0'),
            'valor_unidade' => str_replace(['.', ','], ['', '.'], $_POST['valorUnidade'] ?? '0'),
            'usuario_id' => $_SESSION['Usuario'] ?? 'SISTEMA',
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'data_registro' => date('Y-m-d H:i:s')
        ];
        
        // Validar dados obrigatórios
        if (empty($data['empreendimento_id']) || empty($data['valor_unidade'])) {
            throw new Exception("Empreendimento e valor da unidade são obrigatórios.");
        }
        
        // Converter valores
        $data['m2'] = floatval($data['m2']);
        $data['valor_unidade'] = floatval($data['valor_unidade']);
        
        // Buscar nome do empreendimento
        $stmt = $pdo->prepare("SELECT NomeEmpreendimento FROM empreendimento WHERE Empreend_ID = ?");
        $stmt->execute([$data['empreendimento_id']]);
        $empreendimento = $stmt->fetch();
        $data['empreendimento_nome'] = $empreendimento['NomeEmpreendimento'] ?? 'N/A';
        
        // Inserir na tabela principal
        $sqlPrincipal = "INSERT INTO fluxo_caixa (
            data_simulacao, empreendimento_id, empreendimento_nome, 
            cliente, corretor, unidade, m2, valor_unidade,
            usuario, ip_address, data_registro
        ) VALUES (
            :data, :empreendimento_id, :empreendimento_nome,
            :cliente, :corretor, :unidade, :m2, :valor_unidade,
            :usuario_id, :ip_address, :data_registro
        )";
        
        $stmtPrincipal = $pdo->prepare($sqlPrincipal);
        $stmtPrincipal->execute([
            ':data' => $data['data'],
            ':empreendimento_id' => $data['empreendimento_id'],
            ':empreendimento_nome' => $data['empreendimento_nome'],
            ':cliente' => $data['cliente'],
            ':corretor' => $data['corretor'],
            ':unidade' => $data['unidade'],
            ':m2' => $data['m2'],
            ':valor_unidade' => $data['valor_unidade'],
            ':usuario_id' => $data['usuario_id'],
            ':ip_address' => $data['ip_address'],
            ':data_registro' => $data['data_registro']
        ]);
        
        $fluxo_id = $pdo->lastInsertId();
        
        // Processar as etapas de pagamento
        $etapas = [
            'entrada1' => 'Pix Reserva',
            'entrada2' => 'Ato/Contrato',
            'entrada3' => '30 dias',
            'entrada4' => '60 dias',
            'entrada5' => '90 Dias',
            'entrada6' => '120 Dias',
            'entrada7' => '150 Dias',
            'entrada8' => '180 Dias',
            'entrada9' => 'Parcelas Mensais',
            'entrada10' => 'Parcelas Semestrais',
            'entrada11' => 'Chaves'
        ];
        
        foreach ($etapas as $key => $nomeEtapa) {
            $qtd = $_POST[$key . '_qtd'] ?? 0;
            $pct = $_POST[$key . '_pct'] ?? '0,00%';
            $valor = $_POST[$key . '_valor'] ?? '0,00';
            $percentual = $_POST[$key . '_percentual'] ?? '0,00%';
            
            if ($qtd > 0) {
                // Converter valores
                $valor_convertido = str_replace(['.', ','], ['', '.'], $valor);
                $pct_convertido = str_replace(['%', ','], ['', '.'], $pct);
                $percentual_convertido = str_replace(['%', ','], ['', '.'], $percentual);
                
                $sqlEtapa = "INSERT INTO fluxo_etapas (
                    fluxo_id, etapa_nome, quantidade, percentual, valor, percentual_total,
                    data_registro
                ) VALUES (
                    :fluxo_id, :etapa_nome, :quantidade, :percentual, :valor, :percentual_total,
                    :data_registro
                )";
                
                $stmtEtapa = $pdo->prepare($sqlEtapa);
                $stmtEtapa->execute([
                    ':fluxo_id' => $fluxo_id,
                    ':etapa_nome' => $nomeEtapa,
                    ':quantidade' => intval($qtd),
                    ':percentual' => floatval($pct_convertido),
                    ':valor' => floatval($valor_convertido),
                    ':percentual_total' => floatval($percentual_convertido),
                    ':data_registro' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        // Calcular totais
        $total_pago = 0;
        $sqlTotal = "SELECT SUM(valor * quantidade) as total FROM fluxo_etapas WHERE fluxo_id = ?";
        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute([$fluxo_id]);
        $result = $stmtTotal->fetch();
        $total_pago = $result['total'] ?? 0;
        
        $percentual_total = ($total_pago / $data['valor_unidade']) * 100;
        $saldo_devedor = $data['valor_unidade'] - $total_pago;
        $saldo_percentual = 100 - $percentual_total;
        
        // Atualizar totais na tabela principal
        $sqlUpdate = "UPDATE fluxo_caixa SET 
            total_pago = :total_pago,
            percentual_total = :percentual_total,
            saldo_devedor = :saldo_devedor,
            saldo_percentual = :saldo_percentual
            WHERE id = :id";
        
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':total_pago' => $total_pago,
            ':percentual_total' => $percentual_total,
            ':saldo_devedor' => $saldo_devedor,
            ':saldo_percentual' => $saldo_percentual,
            ':id' => $fluxo_id
        ]);
        
        // Confirmar transação
        $pdo->commit();
        
        // Registrar log
        registrarLog("Salvou fluxo ID: $fluxo_id - Cliente: " . $data['cliente']);
        
        // Gerar resposta de sucesso
        $resposta = "SIMULAÇÃO SALVA COM SUCESSO!\n";
        $resposta .= "===============================\n";
        $resposta .= "ID da Simulação: $fluxo_id\n";
        $resposta .= "Data: " . date('d/m/Y H:i:s') . "\n";
        $resposta .= "Empreendimento: " . $data['empreendimento_nome'] . "\n";
        $resposta .= "Cliente: " . $data['cliente'] . "\n";
        $resposta .= "Unidade: " . $data['unidade'] . "\n";
        $resposta .= "Valor da Unidade: R$ " . number_format($data['valor_unidade'], 2, ',', '.') . "\n";
        $resposta .= "Total Pago: R$ " . number_format($total_pago, 2, ',', '.') . "\n";
        $resposta .= "Percentual Pago: " . number_format($percentual_total, 2, ',', '.') . "%\n";
        $resposta .= "Saldo Devedor: R$ " . number_format($saldo_devedor, 2, ',', '.') . "\n";
        $resposta .= "Saldo Percentual: " . number_format($saldo_percentual, 2, ',', '.') . "%\n";
        $resposta .= "===============================\n";
        $resposta .= "Simulação registrada por: " . $data['usuario_id'] . "\n";
        
        echo $resposta;
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        http_response_code(500);
        echo "ERRO: " . $e->getMessage();
        error_log("Erro ao salvar fluxo: " . $e->getMessage());
    }
} else {
    http_response_code(405);
    echo "Método não permitido. Use POST.";
}
?>
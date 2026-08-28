<?php
// atualizar_pavimentos.php

// Habilitar exibição de erros (apenas para desenvolvimento)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Definir cabeçalhos
header('Content-Type: application/json; charset=UTF-8');

// Habilita o CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Se for uma requisição OPTIONS (preflight), retorna apenas os cabeçalhos
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir arquivo de conexão
require_once 'conn.php';

// Array para a resposta
$response = [
    'debug' => [],
    'success' => false,
    'message' => '',
    'affected_rows' => 0
];

try {
    // Verificar método da requisição
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }

    // Obter parâmetros
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    $pavimentos = isset($_POST['pavimentos']) ? trim($_POST['pavimentos']) : '';

    // Adicionar debug
    $response['debug']['received_id'] = $id;
    $response['debug']['received_pavimentos'] = $pavimentos;
    $response['debug']['request_method'] = $_SERVER['REQUEST_METHOD'];
    $response['debug']['content_type'] = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'Não definido';

    // Verifica se os parâmetros são válidos
    if ($id === '' || !is_numeric($id) || $pavimentos === '' || !is_numeric($pavimentos)) {
        throw new Exception('Parâmetros inválidos. ID e Pavimentos devem ser números.');
    }

    // Converte para números
    $id = (int)$id;
    $pavimentos = (int)$pavimentos;

    // Validações adicionais
    if ($id <= 0) {
        throw new Exception('ID deve ser maior que zero.');
    }

    if ($pavimentos < 0) {
        throw new Exception('Pavimentos não podem ser negativos.');
    }

    // Limitar pavimentos a um valor razoável (opcional)
    if ($pavimentos > 1000) {
        throw new Exception('Pavimentos não podem exceder 1000.');
    }

    // Tenta abrir a conexão
    $conn = GetOpenConnectionLog();
    
    if (!$conn) {
        throw new Exception('Erro na conexão com o banco de dados.');
    }

    // Preparar a query SQL com prepared statement
    $sql = "UPDATE Empreendimento SET Pavimentos = :pavimentos WHERE Empreend_ID = :id";
    
    $stmt = $conn->prepare($sql);
    
    // Bind dos parâmetros
    $stmt->bindParam(':pavimentos', $pavimentos, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    // Executar a query
    $stmt->execute();
    
    // Obter número de linhas afetadas
    $affectedRows = $stmt->rowCount();
    
    $response['affected_rows'] = $affectedRows;
    $response['debug']['sql'] = $sql;
    $response['debug']['id_value'] = $id;
    $response['debug']['pavimentos_value'] = $pavimentos;

    if ($affectedRows > 0) {
        $response['success'] = true;
        $response['message'] = 'Pavimentos atualizados com sucesso';
        
        // Opcional: Buscar dados atualizados para verificação
        $sqlSelect = "SELECT Empreend_ID, Pavimentos FROM empreendimento WHERE Empreend_ID = :id";
        $stmtSelect = $conn->prepare($sqlSelect);
        $stmtSelect->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtSelect->execute();
        $resultado = $stmtSelect->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            $response['debug']['updated_data'] = $resultado;
        }
    } else {
        // Verificar se o ID existe
        $sqlCheck = "SELECT COUNT(*) as total FROM empreendimento WHERE Empreend_ID = :id";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($checkResult['total'] == 0) {
            throw new Exception('ID não encontrado no banco de dados.');
        } else {
            // ID existe, mas os dados já eram iguais
            $response['success'] = true;
            $response['message'] = 'Pavimentos já estavam com este valor. Nenhuma alteração necessária.';
        }
    }

    // Fechar a conexão
    $conn = null;

} catch (PDOException $e) {
    // Erro do PDO/banco de dados
    $response['success'] = false;
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    $response['debug']['pdo_error'] = [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    // Log do erro (opcional)
    error_log('Erro PDO em atualizar_pavimentos.php: ' . $e->getMessage());
    
} catch (Exception $e) {
    // Outros erros
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['debug']['exception'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

// Em modo de debug, pode ser útil ver todos os dados recebidos
if (isset($_POST) && !empty($_POST)) {
    $response['debug']['all_post_data'] = $_POST;
}

// Se estiver em modo de desenvolvimento, pode mostrar mais informações
if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
    $response['debug']['server_info'] = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
    ];
}

// Remover debug em produção (opcional)
// unset($response['debug']);

// Enviar resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Encerrar script
exit();
?>
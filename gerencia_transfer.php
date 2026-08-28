<?php
// gerencia_transfer.php
session_start();
require_once 'conn.php';
require_once 'usr_acoes.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: gerencia_list.php");
    exit;
}

// Processar a Transferência
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novaDiretoriaId = filter_input(INPUT_POST, 'nova_diretoria', FILTER_VALIDATE_INT);
    
    if ($novaDiretoriaId) {
        try {
            $conn->beginTransaction();

            // 1. Marcar a gerência antiga como encerrada
            $sqlEncerrar = "UPDATE gerencias SET DataEncerramento = NOW() WHERE GerenciaID = :id";
            $stmtEnc = $conn->prepare($sqlEncerrar);
            $stmtEnc->execute(['id' => $id]);

            // 2. Criar a nova gerência vinculada à nova diretoria e ao pai
            $sqlDuplicar = "INSERT INTO gerencias (DiretoriaID, NomeGerencia, NomeGerente, TelefoneGerente, GerenciaIdPai, UserId, Usuario, Nome)
                            SELECT :novaDir, NomeGerencia, NomeGerente, TelefoneGerente, GerenciaID, UserId, Usuario, Nome
                            FROM gerencias WHERE GerenciaID = :id";
            $stmtDup = $conn->prepare($sqlDuplicar);
            $stmtDup->execute(['novaDir' => $novaDiretoriaId, 'id' => $id]);
            
            $novoIdGerencia = $conn->lastInsertId();

            // 3. Migrar usuários/corretores para o novo ID de gerência
            $sqlUser = "UPDATE usuarios SET GerenciaID = :novoId WHERE GerenciaID = :velhoId";
            $stmtUsr = $conn->prepare($sqlUser);
            $stmtUsr->execute(['novoId' => $novoIdGerencia, 'velhoId' => $id]);

            $conn->commit();
            header("Location: gerencia_list.php?msg=sucesso");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $erro = "Erro ao transferir: " . $e->getMessage();
        }
    }
}

// Buscar dados da gerência atual e lista de diretorias
$stmt = $conn->prepare("SELECT * FROM gerencias WHERE GerenciaID = ?");
$stmt->execute([$id]);
$gerencia = $stmt->fetch(PDO::FETCH_ASSOC);

$diretorias = $conn->query("SELECT DiretoriaId, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Transferir Gerência | SunnyImob</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .card-transfer { border: none; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .header-transfer { background: #1e293b; color: white; border-radius: 12px 12px 0 0; padding: 20px; }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-transfer">
                <div class="header-transfer">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i> Transferir Gerência</h5>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($erro)): ?>
                        <div class="alert alert-danger"><?php echo $erro; ?></div>
                    <?php endif; ?>

                    <p>Você está movendo a gerência <strong><?php echo htmlspecialchars($gerencia['NomeGerencia']); ?></strong>.</p>
                    <div class="alert alert-warning small">
                        <i class="fas fa-info-circle"></i> O registro atual será encerrado para preservar o histórico de vendas. Uma nova instância será criada para a nova diretoria e os corretores serão migrados.
                    </div>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Selecione a Nova Diretoria Destino</label>
                            <select name="nova_diretoria" class="form-select form-select-lg" required>
                                <option value="">Escolha...</option>
                                <?php foreach($diretorias as $dir): ?>
                                    <option value="<?php echo $dir['DiretoriaId']; ?>" <?php echo ($dir['DiretoriaId'] == $gerencia['DiretoriaID']) ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($dir['NomeDiretoria']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Confirmar Transferência</button>
                            <a href="gerencia_list.php" class="btn btn-light">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
// Configurações de Conexão com o Banco de Dados
$host = '189.1.1.185';
$db   = 'cli18432_teste';
$user = 'cli18432_vbarreto';
$pass = 'pgX#2586';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$mensagem = '';
$tipoAlerta = '';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 1. Criar a tabela clientes se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        telefone VARCHAR(20) NOT NULL,
        cidade VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Popular com 10 registros aleatórios se a tabela estiver vazia
    $stmt = $pdo->query("SELECT COUNT(*) FROM clientes");
    if ($stmt->fetchColumn() == 0) {
        $nomes = ['Carlos Silva', 'Ana Souza', 'Mariana Santos', 'José Oliveira', 'Fernanda Lima', 'Lucas Pereira', 'Juliana Costa', 'Marcos Almeida', 'Beatriz Ribeiro', 'Roberto Martins'];
        $cidades = ['Recife', 'Jaboatão dos Guararapes', 'Olinda', 'Caruaru', 'Paulista', 'Camaragibe'];
        
        for ($i = 0; $i < 10; $i++) {
            $nome = $nomes[$i];
            $email = strtolower(str_replace(' ', '.', $nome)) . '@email.com';
            $telefone = '(81) 9' . rand(1000, 9999) . '-' . rand(1000, 9999);
            $cidade = $cidades[array_rand($cidades)];
            
            $stmtIns = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, cidade) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$nome, $email, $telefone, $cidade]);
        }
        $mensagem = "Tabela criada e populada com 10 registros iniciais com sucesso!";
        $tipoAlerta = "success";
    }

    // 3. Processar Ações do CRUD (Salvar / Inserir / Atualizar)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';
        
        if ($acao === 'salvar') {
            $id = $_POST['id'] ?? '';
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            $telefone = trim($_POST['telefone']);
            $cidade = trim($_POST['cidade']);

            if (!empty($id)) {
                // Atualizar
                $stmtUpd = $pdo->prepare("UPDATE clientes SET nome = ?, email = ?, telefone = ?, cidade = ? WHERE id = ?");
                $stmtUpd->execute([$nome, $email, $telefone, $cidade, $id]);
                $mensagem = "Cliente atualizado com sucesso!";
                $tipoAlerta = "success";
            } else {
                // Inserir Novo
                $stmtIns = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, cidade) VALUES (?, ?, ?, ?)");
                $stmtIns->execute([$nome, $email, $telefone, $cidade]);
                $mensagem = "Cliente cadastrado com sucesso!";
                $tipoAlerta = "success";
            }
        }
    }

    // 4. Processar Exclusão
    if (isset($_GET['excluir'])) {
        $idExcluir = $_GET['excluir'];
        $stmtDel = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmtDel->execute([$idExcluir]);
        $mensagem = "Cliente excluído com sucesso!";
        $tipoAlerta = "success";
    }

    // 5. Buscar dados para edição (se aplicável)
    $clienteEdicao = null;
    if (isset($_GET['editar'])) {
        $idEditar = $_GET['editar'];
        $stmtEd = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmtEd->execute([$idEditar]);
        $clienteEdicao = $stmtEd->fetch();
    }

    // 6. Listar todos os registros
    $stmtList = $pdo->query("SELECT * FROM clientes ORDER BY id DESC");
    $clientes = $stmtList->fetchAll();

} catch (\PDOException $e) {
    $mensagem = "Erro na conexão ou operação com o banco de dados: " . $e->getMessage();
    $tipoAlerta = "danger";
    $clientes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Hospedagem - CRUD Clientes</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <div class="container py-5">
        <header class="mb-4 text-center">
            <h1 class="h3 fw-bold text-primary">Teste de Servidor - Gerenciamento de Clientes</h1>
            <p class="text-muted">Banco de dados: <code>cli18432_teste</code> em <code>189.1.1.185</code></p>
        </header>

        <!-- Mensagens de Alerta -->
        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipoAlerta; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Formulário de Cadastro / Edição -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <?php echo $clienteEdicao ? '<i class="bi bi-pencil-square"></i> Editar Cliente' : '<i class="bi bi-person-plus"></i> Novo Cliente'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="testedb.php">
                            <input type="hidden" name="acao" value="salvar">
                            <input type="hidden" name="id" value="<?php echo $clienteEdicao['id'] ?? ''; ?>">

                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($clienteEdicao['nome'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($clienteEdicao['email'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($clienteEdicao['telefone'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($clienteEdicao['cidade'] ?? ''); ?>" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Salvar
                                </button>
                                <?php if ($clienteEdicao): ?>
                                    <a href="testedb.php" class="btn btn-secondary">Cancelar Edição</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabela de Listagem -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-table"></i> Clientes Cadastrados</h5>
                        <span class="badge bg-light text-dark"><?php echo count($clientes); ?> registros</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Telefone</th>
                                        <th>Cidade</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($clientes) > 0): ?>
                                        <?php foreach ($clientes as $cli): ?>
                                            <tr>
                                                <td><?php echo $cli['id']; ?></td>
                                                <td><?php echo htmlspecialchars($cli['nome']); ?></td>
                                                <td><?php echo htmlspecialchars($cli['email']); ?></td>
                                                <td><?php echo htmlspecialchars($cli['telefone']); ?></td>
                                                <td><?php echo htmlspecialchars($cli['cidade']); ?></td>
                                                <td class="text-center">
                                                    <a href="testedb.php?editar=<?php echo $cli['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="testedb.php?excluir=<?php echo $cli['id']; ?>" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este cliente?');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">Nenhum cliente cadastrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
echo "<ul>";
echo "<li><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . " segundos</li>";
echo "<li><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</li>";
echo "<li><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</li>";
echo "<li><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</li>";
echo "</ul>";
?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
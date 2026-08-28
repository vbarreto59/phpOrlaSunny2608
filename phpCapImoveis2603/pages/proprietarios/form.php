<?php
// pages/proprietarios/form.php
// Cadastro e edição de proprietário
session_start();
require_once '../../includes/auth.php';        // proteção de login
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// Verifica se é edição ou novo cadastro
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$modo = $id > 0 ? 'Editar' : 'Novo';

$proprietario = [
    'nome'     => '',
    'telefone' => '',
    'email'    => '',
    'cpf'      => '',
    'endereco' => ''
];

$erro    = '';
$sucesso = '';

// Carrega dados existentes se for edição
if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT nome, telefone, email, cpf, endereco 
        FROM proprietarios 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $proprietario = $row;
    } else {
        $erro = "Proprietário não encontrado ou já excluído.";
    }
}

// Processa o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $dados = [
        'nome'     => trim($_POST['nome'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'cpf'      => trim($_POST['cpf'] ?? ''),
        'endereco' => trim($_POST['endereco'] ?? '')
    ];

    // Validações básicas
    if (empty($dados['nome'])) {
        $erro = "O campo **Nome** é obrigatório.";
    } elseif (strlen($dados['nome']) < 3) {
        $erro = "O nome deve ter pelo menos 3 caracteres.";
    } elseif (!empty($dados['email']) && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erro = "O e-mail informado não é válido.";
    } else {
        try {
            if ($id > 0) {
                // Atualização
                $sql = "
                    UPDATE proprietarios 
                    SET nome = ?, telefone = ?, email = ?, cpf = ?, endereco = ?, updated_at = NOW()
                    WHERE id = ?
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([...array_values($dados), $id]);

                log_historico($id, 'atualizar_proprietario', "Proprietário atualizado: " . $dados['nome']);
                $sucesso = "Proprietário atualizado com sucesso!";
            } else {
                // Novo cadastro
                $sql = "
                    INSERT INTO proprietarios 
                    (nome, telefone, email, cpf, endereco, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute(array_values($dados));

                $novo_id = $conn->lastInsertId();
                log_historico($novo_id, 'criar_proprietario', "Novo proprietário cadastrado: " . $dados['nome']);
                
                $sucesso = "Proprietário cadastrado com sucesso!";
                
                // Redireciona para edição (para continuar adicionando informações se quiser)
                header("Location: form.php?id=$novo_id");
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao salvar no banco de dados: " . $e->getMessage();
        }
    }

    // Mantém os valores digitados em caso de erro
    $proprietario = $dados;
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col">
        <h2 class="text-primary"><?= $modo ?> Proprietário</h2>
        <p class="text-muted">
            <?= $modo === 'Novo' ? 'Cadastre um novo proprietário' : 'Altere os dados do proprietário' ?>
        </p>
    </div>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($sucesso) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($erro) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow border-primary">
    <div class="card-body">
        <form method="post" class="row g-3 needs-validation" novalidate>
            
            <!-- Nome -->
            <div class="col-md-12">
                <label for="nome" class="form-label fw-bold">Nome completo <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg" id="nome" name="nome"
                       value="<?= htmlspecialchars($proprietario['nome']) ?>" 
                       required autofocus>
                <div class="invalid-feedback">
                    Informe o nome completo do proprietário.
                </div>
            </div>

            <!-- Telefone / WhatsApp -->
            <div class="col-md-6">
                <label for="telefone" class="form-label">Telefone / WhatsApp</label>
                <input type="text" class="form-control form-control-lg" id="telefone" name="telefone"
                       value="<?= htmlspecialchars($proprietario['telefone']) ?>"
                       placeholder="(81) 99999-9999">
            </div>

            <!-- E-mail -->
            <div class="col-md-6">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email"
                       value="<?= htmlspecialchars($proprietario['email']) ?>"
                       placeholder="exemplo@email.com">
            </div>

            <!-- CPF -->
            <div class="col-md-6">
                <label for="cpf" class="form-label">CPF</label>
                <input type="text" class="form-control form-control-lg" id="cpf" name="cpf"
                       value="<?= htmlspecialchars($proprietario['cpf']) ?>"
                       placeholder="000.000.000-00">
            </div>

            <!-- Endereço -->
            <div class="col-md-12">
                <label for="endereco" class="form-label">Endereço completo</label>
                <textarea class="form-control form-control-lg" id="endereco" name="endereco" rows="3"
                          placeholder="Rua Exemplo, 123 - Bairro - Cidade/PE"><?= htmlspecialchars($proprietario['endereco']) ?></textarea>
            </div>

            <!-- Botões -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-save me-2"></i> Salvar
                </button>
                
                <a href="../proprietarios/list.php" class="btn btn-outline-secondary btn-lg ms-3">
                    <i class="bi bi-arrow-left me-2"></i> Voltar para a lista
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
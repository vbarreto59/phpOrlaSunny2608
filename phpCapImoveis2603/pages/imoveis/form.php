<?php
session_start();

require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// ================================================
// Determina se é novo ou edição
// ================================================
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$modo = $id > 0 ? 'Editar' : 'Novo';

$imovel = [
    'proprietario_id' => '',
    'titulo'          => '',
    'endereco'        => '',
    'bairro'          => '',
    'cidade'          => '',
    'estado'          => 'PE',
    'cep'             => '',
    'latitude'        => '',
    'longitude'       => '',
    'preco'           => '',
    'quartos'         => '',
    'banheiros'       => '',
    'area'            => '',
    'tipo'            => 'casa',
    'descricao'       => '',
    'status'          => 'captado'
];

$erro    = '';
$sucesso = '';

// Carrega dados se for edição
if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM imoveis 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $imovel = $row;
    } else {
        $erro = "Imóvel não encontrado ou já excluído.";
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $dados = [
        'proprietario_id' => (int)($_POST['proprietario_id'] ?? 0),
        'titulo'          => trim($_POST['titulo'] ?? ''),
        'endereco'        => trim($_POST['endereco'] ?? ''),
        'bairro'          => trim($_POST['bairro'] ?? ''),
        'cidade'          => trim($_POST['cidade'] ?? ''),
        'estado'          => trim($_POST['estado'] ?? 'PE'),
        'cep'             => trim($_POST['cep'] ?? ''),
        'latitude'        => trim($_POST['latitude'] ?? ''),
        'longitude'       => trim($_POST['longitude'] ?? ''),
        'preco'           => (float) str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? 0),
        'quartos'         => (int)($_POST['quartos'] ?? 0),
        'banheiros'       => (int)($_POST['banheiros'] ?? 0),
        'area'            => (float)($_POST['area'] ?? 0),
        'tipo'            => $_POST['tipo'] ?? 'casa',
        'descricao'       => trim($_POST['descricao'] ?? ''),
        'status'          => $_POST['status'] ?? 'captado'
    ];

    // Validações básicas
    if ($dados['proprietario_id'] <= 0) {
        $erro = "Selecione o proprietário.";
    } elseif (empty($dados['titulo'])) {
        $erro = "O título do imóvel é obrigatório.";
    } elseif (empty($dados['bairro']) || empty($dados['cidade'])) {
        $erro = "Informe bairro e cidade.";
    } elseif ($dados['preco'] <= 0 && $dados['status'] !== 'vendido') {
        $erro = "Informe um preço válido (ou deixe como 0 se for sob consulta).";
    } else {
        try {
            if ($id > 0) {
                // Atualizar
                $sql = "
                    UPDATE imoveis SET 
                        proprietario_id = ?, titulo = ?, endereco = ?, bairro = ?, cidade = ?, 
                        estado = ?, cep = ?, latitude = ?, longitude = ?, preco = ?, 
                        quartos = ?, banheiros = ?, area = ?, tipo = ?, descricao = ?, 
                        status = ?
                    WHERE id = ?
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([...array_values($dados), $id]);

               // log_historico($id, 'atualizar_imovel', "Imóvel atualizado: " . $dados['titulo']);
                $sucesso = "Imóvel atualizado com sucesso!";
            } else {
                // Inserir novo
                $sql = "
                    INSERT INTO imoveis (
                        proprietario_id, titulo, endereco, bairro, cidade, estado, cep, 
                        latitude, longitude, preco, quartos, banheiros, area, tipo, 
                        descricao, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute(array_values($dados));

                $novo_id = $conn->lastInsertId();
                log_historico($novo_id, 'criar_imovel', "Novo imóvel cadastrado: " . $dados['titulo']);

                // Upload de fotos (se houver)
                if (!empty($_FILES['fotos']['name'][0])) {
                    upload_fotos($novo_id);
                }

                $sucesso = "Imóvel cadastrado com sucesso!";
                header("Location: form.php?id=$novo_id");
                exit;
            }

            // Upload de fotos em edição também
            if ($id > 0 && !empty($_FILES['fotos']['name'][0])) {
                upload_fotos($id);
            }

        } catch (PDOException $e) {
            $erro = "Erro ao salvar no banco: " . $e->getMessage();
        }
    }

    $imovel = $dados; // mantém valores em caso de erro
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col">
        <h2 class="text-primary"><?= $modo ?> Imóvel</h2>
        <p class="text-muted"><?= $modo === 'Novo' ? 'Cadastre um novo imóvel captado' : 'Altere os dados do imóvel' ?></p>
    </div>
</div>

<?php if ($sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($sucesso) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($erro) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow border-primary">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate>

            <!-- Proprietário -->
            <div class="col-md-12">
                <label class="form-label fw-bold">Proprietário <span class="text-danger">*</span></label>
                <select name="proprietario_id" class="form-select form-select-lg" required>
                    <option value="">Selecione o proprietário</option>
                    <?php
                    $prop_stmt = $conn->query("SELECT id, nome FROM proprietarios WHERE deleted_at IS NULL ORDER BY nome");
                    while ($prop = $prop_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $selected = ($imovel['proprietario_id'] == $prop['id']) ? 'selected' : '';
                        echo "<option value='{$prop['id']}' $selected>" . htmlspecialchars($prop['nome']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Título -->
            <div class="col-md-12">
                <label class="form-label fw-bold">Título do imóvel <span class="text-danger">*</span></label>
                <input type="text" name="titulo" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['titulo']) ?>" required autofocus>
            </div>

            <!-- Endereço completo -->
            <div class="col-md-12">
                <label class="form-label">Endereço completo</label>
                <input type="text" name="endereco" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['endereco']) ?>">
            </div>

            <!-- Bairro / Cidade / Estado / CEP -->
            <div class="col-md-4">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['bairro']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Cidade</label>
                <input type="text" name="cidade" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['cidade']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <input type="text" name="estado" class="form-control form-control-lg text-uppercase" 
                       value="<?= htmlspecialchars($imovel['estado']) ?>" maxlength="2">
            </div>
            <div class="col-md-2">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['cep']) ?>">
            </div>

            <!-- Coordenadas (opcional) -->
            <div class="col-md-6">
                <label class="form-label">Latitude</label>
                <input type="text" name="latitude" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['latitude']) ?>" placeholder="Ex: -8.058890">
            </div>
            <div class="col-md-6">
                <label class="form-label">Longitude</label>
                <input type="text" name="longitude" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['longitude']) ?>" placeholder="Ex: -34.871140">
            </div>

            <!-- Preço -->
            <div class="col-md-4">
                <label class="form-label fw-bold">Preço (R$)</label>
                <input type="text" name="preco" class="form-control form-control-lg" 
                       value="<?= $imovel['preco'] ? number_format($imovel['preco'], 2, ',', '.') : '' ?>">
            </div>

            <!-- Características -->
            <div class="col-md-2">
                <label class="form-label">Quartos</label>
                <input type="number" name="quartos" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['quartos']) ?>" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Banheiros</label>
                <input type="number" name="banheiros" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['banheiros']) ?>" min="0">
            </div>
            <div class="col-md-4">
                <label class="form-label">Área (m²)</label>
                <input type="number" name="area" class="form-control form-control-lg" 
                       value="<?= htmlspecialchars($imovel['area']) ?>" step="0.01" min="0">
            </div>

            <!-- Tipo -->
            <div class="col-md-6">
                <label class="form-label">Tipo de imóvel</label>
                <select name="tipo" class="form-select form-select-lg">
                    <option value="casa" <?= $imovel['tipo']==='casa'?'selected':'' ?>>Casa</option>
                    <option value="apartamento" <?= $imovel['tipo']==='apartamento'?'selected':'' ?>>Apartamento</option>
                    <option value="terreno" <?= $imovel['tipo']==='terreno'?'selected':'' ?>>Terreno</option>
                    <option value="comercial" <?= $imovel['tipo']==='comercial'?'selected':'' ?>>Comercial</option>
                    <option value="outro" <?= $imovel['tipo']==='outro'?'selected':'' ?>>Outro</option>
                </select>
            </div>

            <!-- Status -->
            <div class="col-md-6">
                <label class="form-label">Status atual</label>
                <select name="status" class="form-select form-select-lg">
                    <option value="captado" <?= $imovel['status']==='captado'?'selected':'' ?>>Captado</option>
                    <option value="em_negociacao" <?= $imovel['status']==='em_negociacao'?'selected':'' ?>>Em negociação</option>
                    <option value="vendido" <?= $imovel['status']==='vendido'?'selected':'' ?>>Vendido</option>
                </select>
            </div>

            <!-- Descrição -->
            <div class="col-md-12">
                <label class="form-label">Descrição detalhada</label>
                <textarea name="descricao" class="form-control form-control-lg" rows="6"><?= htmlspecialchars($imovel['descricao']) ?></textarea>
            </div>

            <!-- Upload de fotos -->
            <div class="col-md-12">
                <label class="form-label">Fotos do imóvel (múltiplas permitidas)</label>
                <input type="file" name="fotos[]" class="form-control form-control-lg" multiple accept="image/*">
                <small class="text-muted">Formatos: JPG, PNG, WEBP. Máximo recomendado: 10 fotos por vez.</small>
            </div>

            <!-- Botões -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-save me-2"></i>Salvar Imóvel
                </button>
                <a href="list.php" class="btn btn-outline-secondary btn-lg ms-3">
                    <i class="bi bi-arrow-left me-2"></i>Voltar para lista
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
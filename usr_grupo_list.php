<?php
session_start();
require_once 'conn.php';

// =========================================================
// FUNÇÕES DE GERENCIAMENTO
// =========================================================

function getPermissaoTexto($codigo) {
    switch($codigo) {
        case 1: return "SUPERADMIN";
        case 2: return "ADMIN";
        case 3: return "GESTOR-EDITOR";
        case 4: return "EDITOR";
        case 5: return "CORRETOR";
        case 6: return "CORRETOR-EDITOR";
        case 7: return "VISUALIZADOR";
        default: return "DESCONHECIDO";
    }
}

// =========================================================
// PROCESSAMENTO DE FORMULÁRIOS E AÇÕES (POST e GET)
// =========================================================

$mensagem = '';
$acao = $_GET['acao'] ?? '';
$grupo_id = $_GET['id'] ?? 0;

// Processamento POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $acao_form = $_POST['acao'] ?? '';
    $nome_grupo = trim($_POST['nomeGrupo'] ?? '');
    $descricao_grupo = trim($_POST['descricaoGrupo'] ?? '');
    $grupo_id_form = intval($_POST['id'] ?? 0);

    try {
        switch ($acao_form) {
            case "adicionar":
                if (empty($nome_grupo)) {
                    throw new Exception("O nome do grupo é obrigatório.");
                }
                
                // Verifica se já existe grupo com este nome
                $sql_check = "SELECT ID_Grupo FROM grupo WHERE Nome_Grupo = :nome";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->execute([':nome' => $nome_grupo]);
                
                if ($stmt_check->fetch()) {
                    throw new Exception("Já existe um grupo com este nome.");
                }
                
                // Insere o novo grupo
                $sql_insert = "INSERT INTO Grupo (Nome_Grupo, Descricao_Grupo) VALUES (:nome, :descricao)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->execute([
                    ':nome' => $nome_grupo,
                    ':descricao' => $descricao_grupo
                ]);
                
                $mensagem = "sucssoGrupo adicionado com sucesso!";
                break;
                
            case "salvar_edicao":
                if ($grupo_id_form <= 0 || empty($nome_grupo)) {
                    throw new Exception("Dados inválidos para edição.");
                }
                
                // Verifica se já existe outro grupo com este nome
                $sql_check = "SELECT ID_Grupo FROM grupo WHERE Nome_Grupo = :nome AND ID_Grupo != :id";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->execute([
                    ':nome' => $nome_grupo,
                    ':id' => $grupo_id_form
                ]);
                
                if ($stmt_check->fetch()) {
                    throw new Exception("Já existe outro grupo com este nome.");
                }
                
                // Atualiza o grupo
                $sql_update = "UPDATE Grupo SET Nome_Grupo = :nome, Descricao_Grupo = :descricao WHERE ID_Grupo = :id";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->execute([
                    ':nome' => $nome_grupo,
                    ':descricao' => $descricao_grupo,
                    ':id' => $grupo_id_form
                ]);
                
                $mensagem = "sucssoGrupo atualizado com sucesso!";
                break;
                
            default:
                throw new Exception("Ação POST não reconhecida.");
        }
        
        // Redireciona com mensagem de sucesso
        header("Location: usr_grupo_list.php?msg=" . urlencode($mensagem));
        exit();
        
    } catch (Exception $e) {
        // Redireciona com mensagem de erro
        header("Location: usr_grupo_list.php?msg=err" . urlencode($e->getMessage()));
        exit();
    }
}

// Processamento GET (para exclusão)
if ($_SERVER["REQUEST_METHOD"] == "GET" && $acao == "excluir") {
    $grupo_id_excluir = intval($_GET['id'] ?? 0);
    
    if ($grupo_id_excluir <= 0) {
        header("Location: usr_grupo_list.php?msg=errID de grupo inválido para exclusão.");
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Primeiro, remover as associações do grupo na tabela Usuario_Grupo
        $sql_delete_assoc = "DELETE FROM usuario_Grupo WHERE ID_Grupo = :id";
        $stmt_assoc = $conn->prepare($sql_delete_assoc);
        $stmt_assoc->execute([':id' => $grupo_id_excluir]);
        
        // Exclui o grupo
        $sql_delete_grupo = "DELETE FROM grupo WHERE ID_Grupo = :id";
        $stmt_grupo = $conn->prepare($sql_delete_grupo);
        $stmt_grupo->execute([':id' => $grupo_id_excluir]);
        
        $conn->commit();
        
        header("Location: usr_grupo_list.php?msg=sucssoGrupo e suas associações removidos com sucesso!");
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: usr_grupo_list.php?msg=errErro ao excluir grupo: " . urlencode($e->getMessage()));
        exit();
    }
}

// Carregar dados do grupo para edição
$grupo_editar = null;
if ($acao == "editar") {
    $grupo_id_editar = intval($_GET['id'] ?? 0);
    
    if ($grupo_id_editar <= 0) {
        header("Location: usr_grupo_list.php?msg=errID Inválido ou não fornecido.");
        exit();
    }
    
    try {
        $sql_grupo = "SELECT ID_Grupo, Nome_Grupo, Descricao_Grupo FROM grupo WHERE ID_Grupo = :id";
        $stmt_grupo = $conn->prepare($sql_grupo);
        $stmt_grupo->execute([':id' => $grupo_id_editar]);
        $grupo_editar = $stmt_grupo->fetch();
        
        if (!$grupo_editar) {
            header("Location: usr_grupo_list.php?msg=errGrupo não encontrado para edição.");
            exit();
        }
    } catch (Exception $e) {
        header("Location: usr_grupo_list.php?msg=errErro ao carregar grupo: " . urlencode($e->getMessage()));
        exit();
    }
}

// Exibir mensagens da query string
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    if (substr($msg, 0, 3) == 'suc') {
        $mensagem = ['tipo' => 'success', 'texto' => substr($msg, 3)];
    } elseif (substr($msg, 0, 3) == 'err') {
        $mensagem = ['tipo' => 'danger', 'texto' => substr($msg, 3)];
    } else {
        $mensagem = ['tipo' => 'info', 'texto' => $msg];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Grupos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; }
        .container { max-width: 960px; }
        .alert-container { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Gerenciamento de Grupos</h1>
        <a href="usr_listar.php" class="btn btn-secondary mb-3">Voltar para a Lista de Usuários</a>

        <?php if ($mensagem): ?>
        <div class="alert-container">
            <div class="alert alert-<?php echo $mensagem['tipo']; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem['texto']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($acao == "editar" && $grupo_editar): ?>
            <h2 class="mb-3">Editar Grupo: <?php echo htmlspecialchars($grupo_editar['Nome_Grupo']); ?></h2>
            <form action="usr_grupo_list.php" method="post" class="mb-4">
                <input type="hidden" name="acao" value="salvar_edicao">
                <input type="hidden" name="id" value="<?php echo $grupo_editar['ID_Grupo']; ?>">
                <div class="mb-3">
                    <label for="nomeGrupo" class="form-label">Nome do Grupo:</label>
                    <input type="text" class="form-control" id="nomeGrupo" name="nomeGrupo" 
                           value="<?php echo htmlspecialchars($grupo_editar['Nome_Grupo']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="descricaoGrupo" class="form-label">Descrição:</label>
                    <textarea class="form-control" id="descricaoGrupo" name="descricaoGrupo" rows="3"><?php echo htmlspecialchars($grupo_editar['Descricao_Grupo']); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="usr_grupo_list.php" class="btn btn-secondary">Cancelar</a>
            </form>
        <?php else: ?>
            <h2 class="mb-3">Adicionar Novo Grupo</h2>
            <form action="usr_grupo_list.php" method="post" class="mb-4">
                <input type="hidden" name="acao" value="adicionar">
                <div class="mb-3">
                    <label for="nomeGrupo" class="form-label">Nome do Grupo:</label>
                    <input type="text" class="form-control" id="nomeGrupo" name="nomeGrupo" required>
                </div>
                <div class="mb-3">
                    <label for="descricaoGrupo" class="form-label">Descrição:</label>
                    <textarea class="form-control" id="descricaoGrupo" name="descricaoGrupo" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-success">Adicionar Grupo</button>
            </form>
        <?php endif; ?>

        <h2 class="mb-3">Grupos Cadastrados</h2>
        <table id="tabelaGrupos" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome do Grupo</th>
                    <th>Descrição</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $sql_grupos = "SELECT ID_Grupo, Nome_Grupo, Descricao_Grupo FROM grupo ORDER BY Nome_Grupo";
                    $stmt_grupos = $conn->query($sql_grupos);
                    $grupos = $stmt_grupos->fetchAll();
                    
                    if (count($grupos) > 0) {
                        foreach ($grupos as $grupo) {
                            ?>
                            <tr>
                                <td><?php echo $grupo['ID_Grupo']; ?></td>
                                <td><?php echo htmlspecialchars($grupo['Nome_Grupo']); ?></td>
                                <td><?php echo htmlspecialchars($grupo['Descricao_Grupo']); ?></td>
                                <td>
                                    <a href="usr_grupo_list.php?acao=editar&id=<?php echo $grupo['ID_Grupo']; ?>" 
                                       class="btn btn-sm btn-primary">Editar</a>
                                    <a href="usr_gerenciar_grupos.php?id=<?php echo $grupo['ID_Grupo']; ?>" 
                                       class="btn btn-sm btn-info">Gerenciar Usuários</a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="confirmarExclusao(<?php echo $grupo['ID_Grupo']; ?>, '<?php echo addslashes(htmlspecialchars($grupo['Nome_Grupo'])); ?>')">
                                        Excluir
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='4'>Nenhum grupo cadastrado.</td></tr>";
                    }
                } catch (Exception $e) {
                    echo "<tr><td colspan='4'>Erro ao carregar grupos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tabelaGrupos').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/2.0.7/i18n/pt-BR.json"
                },
                "pageLength": 50,
                "order": [[1, 'asc']]
            });
        });

        function confirmarExclusao(id, nome) {
            if (confirm('Tem certeza que deseja excluir o grupo "' + nome + '"? Isso removerá todas as associações de usuários com este grupo.')) {
                window.location.href = 'usr_grupo_list.php?acao=excluir&id=' + id;
            }
        }
    </script>
</body>
</html>
<?php
// Fechar conexão
$conn = null;
?>
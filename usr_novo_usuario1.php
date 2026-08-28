<?php
session_start();
require_once 'conn.php';

/**
 * CONFIGURAÇÕES FIXAS (TOCCA ONZE)
 */
$ID_GRUPO_FIXO   = 11; 
$NOME_GRUPO_FIXO = "TOCCA_ONZE";
$ID_EMP_FIXO     = 1; 
$NOME_EMP_FIXO   = "Tocca Onze";
$PERMISSAO_FIXA  = 5; 
$FUNCAO_FIXA     = "CORRETOR";
$ACOES_FIXAS     = "/CONSULTAR/";
$ATIVO_FIXO      = 0; 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['form_data']);
    unset($_SESSION['MsgErro']);
}

$erros = [];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome      = trim($_POST['Nome']      ?? '');
    $usuario   = trim($_POST['Usuario']   ?? '');
    $senha     = trim($_POST['Senha']     ?? '');
    $email     = trim($_POST['Email']     ?? '');
    $creci     = trim($_POST['CRECI']     ?? '');
    $telefones = trim($_POST['Telefones'] ?? '');

    if (empty($nome) || empty($usuario)) {
        $erros[] = "Nome e Usuário são obrigatórios.";
    }

    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT 1 FROM usuarios WHERE Usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) {
            $erros[] = "O usuário '$usuario' já existe.";
        }
    }

    if (empty($erros)) {
        try {
            $conn->beginTransaction();

            $sql = "INSERT INTO usuarios (
                Usuario, SenhaAtual, Nome, CRECI, Email, Telefones,
                Acoes, Permissao, Funcao, Ativo, idEmp, Empresa,
                GrupoId, Grupo, Data, UsuarioCadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

            $stmt = $conn->prepare($sql);
            $usuarioLogado = $_SESSION['Usuario'] ?? 'SISTEMA';

            $stmt->execute([
                $usuario, $senha, $nome, $creci, $email, $telefones,
                $ACOES_FIXAS, $PERMISSAO_FIXA, $FUNCAO_FIXA, $ATIVO_FIXO,
                $ID_EMP_FIXO, $NOME_EMP_FIXO, $ID_GRUPO_FIXO, $NOME_GRUPO_FIXO,
                $usuarioLogado
            ]);

            $new_user_id = $conn->lastInsertId();
            $stmt = $conn->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)");
            $stmt->execute([$new_user_id, $ID_GRUPO_FIXO]);

            $conn->commit();

            // ==========================================================
            // ENVIO DE E-MAIL COM TODOS OS CAMPOS
            // ==========================================================
            $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
            $subject = "SI-Novo Usuário-" . strtoupper($usuarioLogado) . " - " . $_SERVER['REMOTE_ADDR'] . " - " . date('Y-m-d H:i:s');
            
            $message = "NOVO CORRETOR CADASTRADO NO SISTEMA\n";
            $message .= "==========================================\n\n";
            $message .= "DADOS DO NOVO USUÁRIO:\n";
            $message .= "------------------------------------------\n";
            $message .= "Nome Completo: " . $nome . "\n";
            $message .= "Usuário Login: " . $usuario . "\n";
            $message .= "Senha Inicial: " . $senha . "\n";
            $message .= "E-mail:        " . $email . "\n";
            $message .= "CRECI:         " . $creci . "\n";
            $message .= "Telefone:      " . $telefones . "\n";
            $message .= "Empresa:       " . $NOME_EMP_FIXO . "\n";
            $message .= "Grupo:         " . $NOME_GRUPO_FIXO . "\n";
            $message .= "Status:        PENDENTE (INATIVO)\n\n";
            
            $message .= "INFORMAÇÕES DE AUDITORIA:\n";
            $message .= "------------------------------------------\n";
            $message .= "Cadastrado por: " . $usuarioLogado . "\n";
            $message .= "Data/Hora:      " . date('d/m/Y H:i:s') . "\n";
            $message .= "IP Origem:      " . $_SERVER['REMOTE_ADDR'] . "\n";
            $message .= "Navegador:      " . $_SERVER['HTTP_USER_AGENT'] . "\n";
            
            $headers = "From: sendmail@gabnetweb.com.br\r\n";
            $headers .= "Reply-To: sendmail@gabnetweb.com.br\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            @mail($to, $subject, $message, $headers);
            // ==========================================================

            $_SESSION['MsgSucesso'] = "Corretor cadastrado e administradores notificados!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $erros[] = "Erro ao salvar: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tocca Onze • Gestão de Cadastro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; padding: 30px 0; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .bg-custom { background: #1a1a1a; color: white; border-radius: 12px 12px 0 0 !important; }
        .label-small { font-size: 0.75rem; font-weight: bold; color: #666; text-transform: uppercase; }
        .table-custom { font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container" style="max-width: 800px;">
    <!-- Formulário de Cadastro -->
    <div class="card mb-4">
        <div class="card-header bg-custom py-3 text-center">
            <h5 class="mb-0">CADASTRO DE CORRETOR - TOCCA ONZE</h5>
        </div>
        <div class="card-body p-4">
            <?php if (isset($_SESSION['MsgSucesso'])): ?>
                <div class="alert alert-success py-2"><?= $_SESSION['MsgSucesso']; unset($_SESSION['MsgSucesso']); ?></div>
            <?php endif; ?>
            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger py-2"><?= implode("<br>", $erros); ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="mb-3">
                    <label class="label-small">Nome Completo</label>
                    <input type="text" class="form-control form-control-lg border-primary" name="Nome" id="Nome" required autocomplete="off" autofocus>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="label-small">Usuário</label>
                        <input type="text" class="form-control bg-light fw-bold text-primary" name="Usuario" id="Usuario" required>
                    </div>
                    <div class="col-md-4">
                        <label class="label-small">Senha Padrão</label>
                        <input type="text" class="form-control bg-light" name="Senha" id="Senha" required>
                    </div>
                    <div class="col-md-4">
                        <label class="label-small">Telefone</label>
                        <input type="text" class="form-control" name="Telefones" id="Telefones">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="label-small">E-mail </label>
                        <input type="email" class="form-control" name="Email" id="Email">
                    </div>
                    <div class="col-md-4">
                        <label class="label-small">CRECI</label>
                        <input type="text" class="form-control" name="CRECI" id="CRECI">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-dark w-100 btn-lg shadow-sm">GRAVAR</button>
            </form>
        </div>
    </div>

    <!-- Tabela de Últimos 20 -->
    <div class="card">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                <i class="fa-solid fa-history me-2"></i>Histórico Recente (Últimos 20)
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-custom align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">NOME</th>
                        <th>USUÁRIO</th>
                        <th>CADASTRADO POR</th>
                        <th class="text-end pe-3">DATA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT Nome, Usuario, UsuarioCadastro, Data FROM usuarios WHERE GrupoId = ? ORDER BY Data DESC LIMIT 20");
                    $stmt->execute([$ID_GRUPO_FIXO]);
                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($row['Nome']) ?></td>
                        <td><code class="text-primary"><?= htmlspecialchars($row['Usuario']) ?></code></td>
                        <td class="text-uppercase small fw-semibold text-muted"><?= htmlspecialchars($row['UsuarioCadastro'] ?? 'SISTEMA') ?></td>
                        <td class="text-end pe-3 text-muted"><?= date('d/m/Y H:i', strtotime($row['Data'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function formatar(t) { 
    return t.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z]/g, ""); 
}

document.getElementById("Nome").addEventListener("input", function() {
    const val = this.value.trim();
    if(val.length > 2) {
        const p = val.split(/\s+/);
        const pri = formatar(p[0]);
        const sub = p[1] ? formatar(p[1])[0] : "";
        
        document.getElementById("Usuario").value = pri + sub;
        document.getElementById("Senha").value = pri + "@123";
        document.getElementById("Email").value = pri + "@toccaonze.com.br";
        document.getElementById("CRECI").value = "999999";
        document.getElementById("Telefones").value = "99999999999";
    }
});
</script>

<footer style="text-align: center; font-size: 12px; color: #777; padding: 20px 0; font-family: sans-serif;">
    <p>&copy; <?php echo date("Y"); ?> Desenvolvido por <strong>Valter Barreto</strong>. Todos os direitos reservados.</p>
</footer>

</body>
</html>
<?php $conn = null; ?>
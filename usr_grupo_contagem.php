<?php
session_start();
require_once 'conn.php';

// Função para obter iniciais do nome de usuário
function getIniciais($nomeUsuario) {
    $nomeUsuario = strtoupper(trim($nomeUsuario));
    $partes = explode(' ', $nomeUsuario);
    $iniciais = '';
    
    if (count($partes) > 0) {
        $iniciais = substr($partes[0], 0, 1);
    }
    
    if (count($partes) > 1) {
        $iniciais .= substr($partes[1], 0, 1);
    }
    
    return $iniciais;
}

// Obter todos os grupos e seus usuários
try {
    $sql_grupos = "SELECT g.ID_Grupo, g.Nome_Grupo, 
                          COUNT(ug.UserId) AS QtdUsuarios, 
                          SUM(CASE WHEN u.Ativo = 1 THEN 1 ELSE 0 END) AS QtdUsuariosAtivos
                   FROM grupo g 
                   LEFT JOIN usuario_grupo ug ON g.ID_Grupo = ug.ID_Grupo 
                   LEFT JOIN usuarios u ON ug.UserId = u.UserId
                   GROUP BY g.ID_Grupo, g.Nome_Grupo 
                   ORDER BY g.Nome_Grupo";
    
    $stmt_grupos = $conn->prepare($sql_grupos);
    $stmt_grupos->execute();
    $grupos = $stmt_grupos->fetchAll();
} catch (PDOException $e) {
    die("Erro ao carregar grupos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Sunny - Lista de Grupos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }
    .card-header {
      background-color: #343a40;
      color: white;
      border-radius: 10px 10px 0 0 !important;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .badge-qtd {
      font-size: 1em;
      padding: 0.5em 0.8em;
    }
    .user-badge {
      margin-right: 8px;
      margin-bottom: 8px;
      display: inline-flex;
      align-items: center;
      background-color: #f8f9fa;
      border-radius: 20px;
      padding: 5px 10px;
      border: 1px solid #dee2e6;
    }
    .user-badge.active {
      background-color: #d1ecf1; /* Azul claro para usuários ativos */
      color: #0c5460; /* Cor do texto */
    }
    .user-avatar {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background-color: #6c757d;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 8px;
      font-weight: bold;
      flex-shrink: 0;
    }
    .btn-group-sm .btn {
      padding: 0.25rem 0.5rem;
      font-size: 0.875rem;
    }
    .empty-group {
      color: #6c757d;
      font-style: italic;
    }
    .header-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }
    .header-title {
      display: flex;
      align-items: center;
    }
    @media (max-width: 768px) {
      .card-header {
        flex-direction: column;
        align-items: flex-start;
      }
      .card-header > div {
        margin-top: 10px;
      }
    }
  </style>
</head>
<body>
  
  <div class="container py-4">
    <div class="header-container">
      <div class="header-title">
        <h2 class="mb-0"><i class="fas fa-users mr-2"></i>Lista de Grupos</h2>
      </div>
      <div>
        <a href="usr_listar.php" class="btn btn-primary">
          <i class="fas fa-arrow-left mr-1"></i> Voltar para Usuários
        </a>
        <a href="usr_grupo_list.php" class="btn btn-info ml-2">
          <i class="fas fa-cogs mr-1"></i> Gerenciar Grupos
        </a>
      </div>
    </div>

    <?php if (empty($grupos)): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle mr-2"></i> Nenhum grupo cadastrado
      </div>
    <?php else: ?>
      <?php foreach ($grupos as $grupo): 
        $grupoId = $grupo['ID_Grupo'];
        $grupoNome = htmlspecialchars($grupo['Nome_Grupo']);
        $qtdUsuarios = $grupo['QtdUsuarios'];
        $qtdUsuariosAtivos = $grupo['QtdUsuariosAtivos'];
      ?>
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><?php echo $grupoNome; ?></h5>
          <div class="d-flex align-items-center">
            <span class="badge badge-primary badge-qtd">
              <i class="fas fa-users mr-1"></i> <?php echo $qtdUsuarios; ?> usuário(s)
            </span>
            <span class="badge badge-success badge-qtd ml-2">
              <i class="fas fa-user-check mr-1"></i> <?php echo $qtdUsuariosAtivos; ?> ativo(s)
            </span>
            
            <?php if (isset($_SESSION['Usuario']) && strtoupper($_SESSION['Usuario']) == 'BARRETO'): ?>
              <div class="btn-group btn-group-sm ml-2">
                <a href="usr_gerenciar_grupos.php?id=<?php echo $grupoId; ?>" class="btn btn-warning" title="Gerenciar Usuários">
                  <i class="fas fa-user-edit"></i>
                </a>
                <a href="usr_grupo_edit.php?id=<?php echo $grupoId; ?>" class="btn btn-info" title="Editar Grupo">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="usr_grupo_excluir.php?id=<?php echo $grupoId; ?>" class="btn btn-danger" title="Excluir Grupo" 
                   onclick="return confirm('Tem certeza que deseja excluir este grupo?')">
                  <i class="fas fa-trash-alt"></i>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="card-body">
          <?php
          // Obter usuários deste grupo
          try {
            $sql_usuarios = "SELECT u.UserId, u.Usuario, u.Nome, u.Ativo 
                             FROM usuarios u 
                             INNER JOIN usuario_grupo ug ON u.UserId = ug.UserId 
                             WHERE ug.ID_Grupo = :grupo_id 
                             ORDER BY u.Usuario";
            
            $stmt_usuarios = $conn->prepare($sql_usuarios);
            $stmt_usuarios->execute([':grupo_id' => $grupoId]);
            $usuarios = $stmt_usuarios->fetchAll();
            
            if (empty($usuarios)): ?>
              <div class="empty-group">
                <i class="fas fa-user-slash mr-1"></i> Nenhum usuário neste grupo
              </div>
            <?php else: 
              foreach ($usuarios as $usuario):
                $nomeUsuario = strtoupper(htmlspecialchars($usuario['Usuario']));
                $nomeCompleto = htmlspecialchars($usuario['Nome'] ?? $usuario['Usuario']);
                $iniciais = getIniciais($nomeCompleto);
                $classeAtivo = $usuario['Ativo'] ? 'active' : ''; // Classe para usuários ativos
              ?>
              <div class="user-badge <?php echo $classeAtivo; ?>" title="<?php echo $nomeCompleto; ?>">
                <div class="user-avatar" data-toggle="tooltip" title="<?php echo $nomeCompleto; ?>">
                  <?php echo $iniciais; ?>
                </div>
                <span><?php echo $nomeUsuario; ?></span>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php } catch (PDOException $e) { ?>
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle mr-1"></i> Erro ao carregar usuários do grupo
            </div>
          <?php } ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Estatísticas totais -->
    <?php if (!empty($grupos)): 
      $total_grupos = count($grupos);
      $total_usuarios = 0;
      $total_usuarios_ativos = 0; // Inicializa a variável

      foreach ($grupos as $grupo) {
        $total_usuarios += $grupo['QtdUsuarios'];
        $total_usuarios_ativos += $grupo['QtdUsuariosAtivos']; // Soma os usuários ativos
      }
    ?>
    <div class="card mt-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Estatísticas</h5>
      </div>
      <div class="card-body">
        <div class="row text-center">
          <div class="col-md-4">
            <div class="display-4 text-primary"><?php echo $total_grupos; ?></div>
            <div class="text-muted">Total de Grupos</div>
          </div>
          <div class="col-md-4">
            <div class="display-4 text-success"><?php echo $total_usuarios; ?></div>
            <div class="text-muted">Total de Associações</div>
          </div>
          <div class="col-md-4">
            <div class="display-4 text-warning"><?php echo $total_usuarios_ativos; ?></div>
            <div class="text-muted">Total de Usuários Ativos</div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- jQuery first, then Popper.js, then Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  
  <script>
    $(document).ready(function() {
      // Ativar tooltips
      $('[data-toggle="tooltip"]').tooltip();
      
      // Adicionar comportamento hover nos badges de usuário
      $('.user-badge').hover(
        function() {
          $(this).css({
            'background-color': '#e9ecef',
            'transform': 'translateY(-2px)',
            'transition': 'all 0.2s ease'
          });
        },
        function() {
          $(this).css({
            'background-color': '',
            'transform': 'translateY(0)'
          });
        }
      );
    });
  </script>
</body>
</html>
<?php
// Fechar conexão
$conn = null;
?>
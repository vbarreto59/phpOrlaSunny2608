<?php
// Incluir arquivo de conexão e tema
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

session_start();

// Verificação de login
if (empty($_SESSION['Usuario'])) {
    header("Location: login_v3a.php");
    exit();
}

// Função auxiliar para verificar se um campo está vazio
function isCampoVazio($valor) {
    return empty($valor) || trim($valor) === '' || $valor == 0;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Dados em Branco</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA (via variáveis CSS)                  */
        /* ============================================================ */
        :root {
            --primary: <?php echo $theme['primary']; ?>;
            --primary-dark: <?php echo $theme['primary_dark']; ?>;
            --secondary: <?php echo $theme['secondary']; ?>;
            --accent: <?php echo $theme['accent']; ?>;
            --bg-body: <?php echo $theme['bg_body']; ?>;
            --text-color: <?php echo $theme['text_color']; ?>;
            --card-bg: <?php echo $theme['card_bg']; ?>;
            --shadow-color: <?php echo $theme['shadow_color']; ?>;
            --btn-bg: <?php echo $theme['btn_bg']; ?>;
            --btn-hover: <?php echo $theme['btn_hover']; ?>;
            --btn-text: <?php echo $theme['btn_text']; ?>;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #fd7e14;
        }

        body {
            background: var(--bg-body);
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }

        /* Título principal */
        .page-title {
            color: var(--primary);
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }
        .page-title i {
            color: var(--primary);
            margin-right: 10px;
        }

        /* Botão voltar neobrutalista */
        .btn-neo-secondary {
            background: #6c757d;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.8rem;
            font-weight: 700;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-neo-secondary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #5a6268;
            color: #fff;
        }

        /* Responsável (título de grupo) */
        .resp-cadastro {
            background: var(--primary);
            color: #fff;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            border: 3px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        .resp-cadastro i {
            margin-right: 8px;
        }

        /* Card do empreendimento neobrutalista */
        .empreendimento {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.2rem;
            transition: all 0.2s;
        }
        .empreendimento:hover {
            transform: translateY(-3px);
            box-shadow: 7px 7px 0px 0px var(--shadow-color);
        }

        .empreendimento-nome {
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            border-bottom: 2px solid var(--shadow-color);
            padding-bottom: 0.3rem;
        }
        .empreendimento-nome i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Badges de campos vazios/preenchidos */
        .campo-vazio {
            background: var(--danger);
            color: #fff;
            font-weight: 700;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 6px;
            font-size: 0.85rem;
        }
        .campo-vazio strong {
            color: #fff;
        }

        .campo-preenchido {
            background: var(--success);
            color: #fff;
            font-weight: 700;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 6px;
            font-size: 0.85rem;
        }
        .campo-preenchido i {
            margin-right: 4px;
        }

        /* Container */
        .container-neo {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .page-title { font-size: 1.5rem; }
            .empreendimento { padding: 1rem; }
            .empreendimento-nome { font-size: 1rem; }
        }
    </style>
</head>
<body>

<div class="container container-neo py-4">
    <h1 class="page-title"><i class="fas fa-clipboard-check"></i> Verificação de Dados em Branco</h1>

    <a class="btn-neo-secondary" href="javascript:window.close()">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>

    <hr style="border: 2px solid var(--shadow-color); opacity: 0.3;">

    <?php
    try {
        // SQL para obter todos os empreendimentos ordenados por RespCadastro e NomeEmpreendimento
        $sql = "SELECT Empreend_ID, NomeEmpreendimento, NomeEmpresa, AnoEntrega, MesEntrega, 
                       BeiraMar, Localidade, RespCadastro, DataEntrega, VagasGaragem, 
                       TipoGaragem, ValorCond, ValorIPTU, MetragemMin, MetragemMax, 
                       Tipologia, Descricao, ProximoMar, QTD_Blocos, QTD_Unidades, ComissaoVenda 
                FROM empreendimento 
                WHERE Vendido = 0 
                ORDER BY RespCadastro, NomeEmpreendimento";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $currentRespCadastro = "";
        
        foreach ($empreendimentos as $empreendimento) {
            // Verifica se mudou o RespCadastro
            if ($empreendimento['RespCadastro'] !== $currentRespCadastro) {
                $currentRespCadastro = $empreendimento['RespCadastro'];
                echo "<div class='resp-cadastro'><i class='fas fa-user-tie'></i> Responsável: " . htmlspecialchars($currentRespCadastro) . "</div>";
            }
            
            echo "<div class='empreendimento'>";
            echo "<div class='empreendimento-nome'><i class='fas fa-building'></i> " . 
                 htmlspecialchars($empreendimento['NomeEmpreendimento']) . " - " . 
                 htmlspecialchars($empreendimento['Localidade']) . " - " . 
                 htmlspecialchars($empreendimento['NomeEmpresa']) . 
                 "</div>";
            echo "<div>";
            
            $algumCampoVazio = false;
            
            // Verifica e exibe apenas os campos vazios
            if (isCampoVazio($empreendimento['Localidade'])) {
                echo "<span class='campo-vazio'><strong>Localidade:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['AnoEntrega'])) {
                echo "<span class='campo-vazio'><strong>Data de Entrega:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['VagasGaragem'])) {
                echo "<span class='campo-vazio'><strong>Vagas de Garagem:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['TipoGaragem'])) {
                echo "<span class='campo-vazio'><strong>Tipo de Garagem:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['MetragemMin'])) {
                echo "<span class='campo-vazio'><strong>Metragem Mínima:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['MetragemMax'])) {
                echo "<span class='campo-vazio'><strong>Metragem Máxima:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['Tipologia'])) {
                echo "<span class='campo-vazio'><strong>Tipologia:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['Descricao'])) {
                echo "<span class='campo-vazio'><strong>Descrição:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['MesEntrega'])) {
                echo "<span class='campo-vazio'><strong>Mês de Entrega:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['AnoEntrega'])) {
                echo "<span class='campo-vazio'><strong>Ano de Entrega:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            // Verifica ProximoMar apenas se não for BeiraMar
            $beiraMar = (int)$empreendimento['BeiraMar'];
            if (!$beiraMar && isCampoVazio($empreendimento['ProximoMar'])) {
                echo "<span class='campo-vazio'><strong>Distância do Mar (m):</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['QTD_Unidades'])) {
                echo "<span class='campo-vazio'><strong>Quantidade de Unidades:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (isCampoVazio($empreendimento['ComissaoVenda'])) {
                echo "<span class='campo-vazio'><strong>Comissão:</strong> vazio</span>";
                $algumCampoVazio = true;
            }
            
            if (!$algumCampoVazio) {
                echo "<span class='campo-preenchido'><i class='fas fa-check-circle'></i> Todos os campos estão preenchidos.</span>";
            }
            
            echo "</div>";
            echo "</div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger' style='border: 2px solid var(--shadow-color); box-shadow: 4px 4px 0px 0px var(--shadow-color); border-radius: 12px; background: var(--card-bg); color: var(--text-color);'>Erro ao buscar dados: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
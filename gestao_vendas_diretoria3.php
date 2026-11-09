<?php
// ######################################
// SISTEMA: SGVENDAS
// AUTOR: VALTER BARRETO
// Data: 04/12/2025
// CODIGO_ARQUIVO: FWNUUKVOKD
// ######################################
// CORREÇÃO: Inclusão de JOINs para relacionar vendas com empreendimento e empresa,
// garantindo que NomeEmpresa seja sempre preenchido, mesmo se estiver em branco na tabela vendas.
// ######################################

session_start();

// Verifica se o usuário não está logado
if (!isset($_SESSION['Usuario']) || empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit;
}

// Incluir arquivos de configuração
require_once "conn.php"; // Arquivo unificado de conexão
require_once "usr_acoes.php"; // Funções de autorização

// Configuração para evitar cache
header("Expires: -1");
header("Pragma: no-cache");
header("Cache-Control: no-store, must-revalidate");

// Função para converter número brasileiro para JavaScript
function converterParaJS($valor) {
    // Remover formatação brasileira
    $resultado = str_replace(".", "", $valor); // Remove separadores de milhar
    $resultado = str_replace(",", ".", $resultado); // Converte vírgula decimal para ponto
    
    if (is_numeric($resultado)) {
        return $resultado;
    } else {
        return 0;
    }
}

try {
    $to = "sendmail@sunnyimob.com.br, valterpb@hotmail.com";
    $subject = "SV-DIR-VENDAS-" . strtoupper($_SESSION['Usuario']) . " - " . $_SERVER['REMOTE_ADDR'] . " - " . date('Y-m-d') . " - " . date('H:i:s');
    $message = "Página Vendas Diretoria: " . strtoupper($_SESSION['Usuario']);
    
    // Adicionando os cabeçalhos para codificação UTF-8 e suporte a acentuação
    $headers  = "From: sendmail@sunnyimob.com.br\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($to, $subject, $message, $headers);
} catch (Exception $e) {
    error_log("Erro ao enviar email: " . $e->getMessage());
}

// Parâmetros do filtro
$ano = isset($_POST['ano']) ? $_POST['ano'] : '';
$anoParaUsar = $ano;
$mes = isset($_POST['mes']) ? $_POST['mes'] : '';
$trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : '';
$semestre = isset($_POST['semestre']) ? $_POST['semestre'] : '';
$corretor = isset($_POST['corretor']) ? $_POST['corretor'] : '';
$gerencia = isset($_POST['gerencia']) ? $_POST['gerencia'] : '';

// Nova lógica: se ano não foi enviado, definir automaticamente como ano atual
$anoAtual = date('Y');
$anoSelecionadoAutomaticamente = false;

if ($ano == '') {
    $anoSelecionadoAutomaticamente = true;
}
$paginaRedirecionamento1 = "login_p1.php";
$paginaRedirecionamento2 = "login_p1.php";

// Nova lógica de filtro de diretoria
$isBarreto = (strtoupper($_SESSION['Usuario'] ?? '') === 'BARRETO');
$diretoriaID = $_SESSION['Dir_DiretoriaID'] ?? '';

if ($diretoriaID == '' && !$isBarreto) {

    echo "Erro de processamento! (100)";
    
    if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
        header("Location: " . $paginaRedirecionamento1);
    } else {
        header("Location: " . $paginaRedirecionamento2);
    }
    exit();
}

// Construir cláusula WHERE - usando nomes de tabelas em minúsculas
$whereClause = " WHERE v.Excluido = 0";
$isFiltered = false;

// 1. Aplicar filtro de Diretoria se o diretor estiver logado
if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
    $whereClause .= " AND v.DiretoriaId = :diretoriaid";
    $isFiltered = true;
}

// 2. Aplicar filtro de ano (agora $anoParaUsar já está definido)
if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
    $whereClause .= " AND v.AnoVenda = :ano";
    $isFiltered = true;
}

try {
    // Abrir conexão com PDO usando conn.php
    $conn = new PDO("mysql:host=$host;dbname=$banco", $usuario, $senha);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
// --- BUSCAR ANOS DISPONÍVEIS NO BANCO DE DADOS ---
$sqlAnos = "SELECT DISTINCT AnoVenda FROM vendas WHERE vendas.Excluido = 0";
if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
    $sqlAnos .= " AND vendas.DiretoriaId = :diretoriaid";
}
$sqlAnos .= " ORDER BY AnoVenda DESC";

$stmtAnos = $conn->prepare($sqlAnos);
if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
    $stmtAnos->bindParam(':diretoriaid', $diretoriaID, PDO::PARAM_INT);
}
$stmtAnos->execute();
$anosDisponiveis = $stmtAnos->fetchAll(PDO::FETCH_COLUMN);
$anosCount = count($anosDisponiveis);

// --- DEFINIR O ANO A SER USADO (automático se nenhum for POSTado) ---
$anoAtual = date('Y');
$anoSelecionadoAutomaticamente = false;
$anoParaUsar = $ano; // $ano vem do POST (ou vazio)

if ($ano == '') {
    // Nenhum ano foi enviado: aplicar filtro automático
    if (in_array($anoAtual, $anosDisponiveis)) {
        $anoParaUsar = $anoAtual;
        $anoSelecionadoAutomaticamente = true;
    } elseif ($anosCount > 0) {
        $anoParaUsar = $anosDisponiveis[0]; // ano mais recente
        $anoSelecionadoAutomaticamente = true;
    }
    $ano = $anoParaUsar; // sincroniza a variável para uso posterior
}
// ###############################
    
    // ###################################################
// --- BUSCAR GERÊNCIAS DISPONÍVEIS NO BANCO DE DADOS ---
$sqlGerencias = "SELECT DISTINCT Gerencia FROM vendas WHERE vendas.Excluido = 0 AND Gerencia IS NOT NULL AND TRIM(Gerencia) <> ''";
if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
    $sqlGerencias .= " AND vendas.DiretoriaId = :diretoriaidGerencias";
}
if ($corretor !== '') {
    $sqlGerencias .= " AND vendas.Corretor = :corretorGerencias";
}
$sqlGerencias .= " ORDER BY Gerencia";

$stmtGerencias = $conn->prepare($sqlGerencias);
if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
    $stmtGerencias->bindParam(':diretoriaidGerencias', $diretoriaID, PDO::PARAM_INT);
}
if ($corretor !== '') {
    $stmtGerencias->bindParam(':corretorGerencias', $corretor, PDO::PARAM_STR);
}

$stmtGerencias->execute();
$gerenciasDisponiveis = $stmtGerencias->fetchAll(PDO::FETCH_COLUMN);
$gerenciasCount = count($gerenciasDisponiveis);
    
    // #####################################
    // Continuar construindo a cláusula WHERE com o ano definido
    if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
        $whereClause .= " AND v.AnoVenda = :ano";
        $isFiltered = true;
    }
    
    // Continuar com outros filtros
    if ($mes !== '' && is_numeric($mes)) {
        $whereClause .= " AND v.MesVenda = :mes";
        $isFiltered = true;
    }
    
    if ($trimestre !== '' && is_numeric($trimestre)) {
        $whereClause .= " AND v.Trimestre = :trimestre";
        $isFiltered = true;
    }
    
    if ($semestre !== '' && is_numeric($semestre)) {
        $whereClause .= " AND v.Semestre = :semestre";
        $isFiltered = true;
    }
    
    // Filtro: Corretor
    if ($corretor !== '') {
        $whereClause .= " AND v.Corretor = :corretor";
        $isFiltered = true;
    }
    
    // Filtro: Gerência
    if ($gerencia !== '') {
        $whereClause .= " AND v.Gerencia = :gerencia";
        $isFiltered = true;
    }
    
    // --- BUSCAR CORRETORES SIMPLES PARA O DROPDOWN ---
    $sqlCorretores = "SELECT DISTINCT Corretor FROM vendas WHERE vendas.Excluido = 0 AND Corretor IS NOT NULL AND TRIM(Corretor) <> ''";
    
    if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
        $sqlCorretores .= " AND vendas.DiretoriaId = :diretoriaidCorretores";
    }
    
    if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
        $sqlCorretores .= " AND vendas.AnoVenda = :anoCorretores";
    }
    
    if ($mes !== '' && is_numeric($mes)) {
        $sqlCorretores .= " AND vendas.MesVenda = :mesCorretores";
    }
    
    if ($trimestre !== '' && is_numeric($trimestre)) {
        $sqlCorretores .= " AND vendas.Trimestre = :trimestreCorretores";
    }
    
    if ($semestre !== '' && is_numeric($semestre)) {
        $sqlCorretores .= " AND vendas.Semestre = :semestreCorretores";
    }
    
    if ($gerencia !== '') {
        $sqlCorretores .= " AND vendas.Gerencia = :gerenciaCorretores";
    }
    
    $sqlCorretores .= " ORDER BY Corretor";
    
    $stmtCorretores = $conn->prepare($sqlCorretores);
    
    // Bind parameters
    if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
        $stmtCorretores->bindParam(':diretoriaidCorretores', $diretoriaID, PDO::PARAM_INT);
    }
    if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
        $stmtCorretores->bindParam(':anoCorretores', $anoParaUsar, PDO::PARAM_INT);
    }
    if ($mes !== '' && is_numeric($mes)) {
        $stmtCorretores->bindParam(':mesCorretores', $mes, PDO::PARAM_INT);
    }
    if ($trimestre !== '' && is_numeric($trimestre)) {
        $stmtCorretores->bindParam(':trimestreCorretores', $trimestre, PDO::PARAM_INT);
    }
    if ($semestre !== '' && is_numeric($semestre)) {
        $stmtCorretores->bindParam(':semestreCorretores', $semestre, PDO::PARAM_INT);
    }
    if ($gerencia !== '') {
        $stmtCorretores->bindParam(':gerenciaCorretores', $gerencia, PDO::PARAM_STR);
    }
    
    $stmtCorretores->execute();
    $corretoresResult = $stmtCorretores->fetchAll(PDO::FETCH_ASSOC);
    
    $corretoresDisponiveis = [];
    foreach ($corretoresResult as $row) {
        if (!empty($row['Corretor'])) {
            $corretoresDisponiveis[] = strtoupper(trim($row['Corretor']));
        }
    }
    $corretoresCount = count($corretoresDisponiveis);
    
    // Se houver filtros aplicados, executar as consultas principais
    if ($isFiltered) {
        // -------------------------------------------------------------
        // CONSULTA PRINCIPAL COM JOIN PARA EMPREENDIMENTO E EMPRESA
        // -------------------------------------------------------------
        $sqlMain = "SELECT 
                        v.*,
                        emp.NomeEmpresa AS NomeEmpresa_do_Empreendimento,
                        e.NomeEmpresa AS NomeEmpresa_Tabela_Empresa,
                        COALESCE(e.NomeEmpresa, emp.NomeEmpresa, v.NomeEmpresa) AS NomeEmpresa_Corrigido
                    FROM vendas v
                    LEFT JOIN empreendimento emp ON (emp.Empreend_ID = v.Empreend_Id OR emp.NomeEmpreendimento = v.NomeEmpreendimento)
                    LEFT JOIN empresa e ON (e.Empresa_ID = v.Empresa_Id OR e.Empresa_ID = emp.Empresa_ID)
                    " . $whereClause;
        $stmtMain = $conn->prepare($sqlMain);
        
        // Bind parameters para a consulta principal
        if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
            $stmtMain->bindParam(':diretoriaid', $diretoriaID, PDO::PARAM_INT);
        }
        if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
            $stmtMain->bindParam(':ano', $anoParaUsar, PDO::PARAM_INT);
        }
        if ($mes !== '' && is_numeric($mes)) {
            $stmtMain->bindParam(':mes', $mes, PDO::PARAM_INT);
        }
        if ($trimestre !== '' && is_numeric($trimestre)) {
            $stmtMain->bindParam(':trimestre', $trimestre, PDO::PARAM_INT);
        }
        if ($semestre !== '' && is_numeric($semestre)) {
            $stmtMain->bindParam(':semestre', $semestre, PDO::PARAM_INT);
        }
        if ($corretor !== '') {
            $stmtMain->bindParam(':corretor', $corretor, PDO::PARAM_STR);
        }
        if ($gerencia !== '') {
            $stmtMain->bindParam(':gerencia', $gerencia, PDO::PARAM_STR);
        }
        
        $stmtMain->execute();
        $vendas = $stmtMain->fetchAll(PDO::FETCH_ASSOC);
        
        // 1. Total de unidades vendidas
        $totalUnidades = count($vendas);
        
        // 2. Total VGV (Valor Geral de Vendas)
        $totalVGV = 0;
        foreach ($vendas as $venda) {
            $totalVGV += floatval($venda['ValorUnidade']);
        }
        
        // 3. Dados para os cards de VGV por mês
        $mesesVGV = array_fill(1, 12, 0);
        $mesesUnidades = array_fill(1, 12, 0);
        $arrMesesNome = [];
        $mesesParaExibir = array_fill(1, 12, true);
        
        // Inicializar array com as 3 primeiras letras dos meses em pt-BR
        $arrNomesMeses = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 
            4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 
            10 => 'Out', 11 => 'Nov', 12 => 'Dez'
        ];

        for ($i = 1; $i <= 12; $i++) {
            $arrMesesNome[$i] = $arrNomesMeses[$i];
            $mesesVGV[$i] = 0;
            $mesesUnidades[$i] = 0;
        }
        
        // Calcular VGV e unidades por mês
        foreach ($vendas as $venda) {
            if (!empty($venda['MesVenda']) && $venda['MesVenda'] >= 1 && $venda['MesVenda'] <= 12) {
                $mesNum = intval($venda['MesVenda']);
                $mesesVGV[$mesNum] += floatval($venda['ValorUnidade']);
                $mesesUnidades[$mesNum]++;
            }
        }
        
        // Verificar filtros de período
        $filtroMesEspecifico = ($mes !== '');
        $filtroTrimestre = ($trimestre !== '');
        $filtroSemestre = ($semestre !== '');
        
        // Aplicar filtros de exibição dos meses
        if ($filtroMesEspecifico) {
            for ($i = 1; $i <= 12; $i++) {
                $mesesParaExibir[$i] = (intval($mes) == $i);
            }
        } elseif ($filtroTrimestre) {
            switch (intval($trimestre)) {
                case 1: // Jan-Mar
                    for ($i = 1; $i <= 12; $i++) {
                        $mesesParaExibir[$i] = ($i >= 1 && $i <= 3);
                    }
                    break;
                case 2: // Abr-Jun
                    for ($i = 1; $i <= 12; $i++) {
                        $mesesParaExibir[$i] = ($i >= 4 && $i <= 6);
                    }
                    break;
                case 3: // Jul-Set
                    for ($i = 1; $i <= 12; $i++) {
                        $mesesParaExibir[$i] = ($i >= 7 && $i <= 9);
                    }
                    break;
                case 4: // Out-Dez
                    for ($i = 1; $i <= 12; $i++) {
                        $mesesParaExibir[$i] = ($i >= 10 && $i <= 12);
                    }
                    break;
            }
        } elseif ($filtroSemestre) {
            switch (intval($semestre)) {
                case 1: // Jan-Jun
                    for ($i = 1; $i <= 12; $i++) {
                        $mesesParaExibir[$i] = ($i >= 1 && $i <= 6);
                    }
                    break;
                case 2: // Jul-Dez
                    for ($i = 1; $i <= 12; $i++) {
                        $mesesParaExibir[$i] = ($i >= 7 && $i <= 12);
                    }
                    break;
            }
        }
        
        // Variável para controlar se podemos mostrar o gráfico
        $podeMostrarGrafico = ($ano !== '' && is_numeric($ano) && $mes === '' && $gerencia === '' && $corretor === '');
        
        // Calcular título dinâmico baseado nos filtros
        $tituloPeriodo = '';
        if ($filtroMesEspecifico) {
            $tituloPeriodo = $arrMesesNome[intval($mes)] . " de " . $ano;
        } elseif ($filtroTrimestre) {
            $tituloPeriodo = $trimestre . "º Trimestre de " . $ano;
        } elseif ($filtroSemestre) {
            $tituloPeriodo = $semestre . "º Semestre de " . $ano;
        } else {
            $tituloPeriodo = $ano;
        }
        
        // Adicionar gerência ao título se filtrada
        if ($gerencia !== '') {
            $tituloPeriodo .= " - Gerência: " . $gerencia;
        }
        
        // Adicionar corretor ao título se filtrado
        if ($corretor !== '') {
            $tituloPeriodo .= " - Corretor: " . strtoupper($corretor);
        }
        
        // --- CONSULTAS ADICIONAIS PARA AS SEÇÕES DE ANÁLISE ---
        // Todas as consultas agora utilizam JOINs para obter os nomes corretos.
        
        // VENDAS POR DIRETORIA (mantido como estava, mas usando v.)
        $sqlDiretorias = "SELECT v.Diretoria, COUNT(*) as unidades, SUM(v.ValorUnidade) as vgv 
                         FROM vendas v" . $whereClause . " 
                         GROUP BY v.Diretoria ORDER BY SUM(v.ValorUnidade) DESC";
        $stmtDiretorias = $conn->prepare($sqlDiretorias);
        
        // Bind parameters para a consulta de diretorias
        if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
            $stmtDiretorias->bindParam(':diretoriaid', $diretoriaID, PDO::PARAM_INT);
        }
        if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
            $stmtDiretorias->bindParam(':ano', $anoParaUsar, PDO::PARAM_INT);
        }
        if ($mes !== '' && is_numeric($mes)) {
            $stmtDiretorias->bindParam(':mes', $mes, PDO::PARAM_INT);
        }
        if ($trimestre !== '' && is_numeric($trimestre)) {
            $stmtDiretorias->bindParam(':trimestre', $trimestre, PDO::PARAM_INT);
        }
        if ($semestre !== '' && is_numeric($semestre)) {
            $stmtDiretorias->bindParam(':semestre', $semestre, PDO::PARAM_INT);
        }
        if ($corretor !== '') {
            $stmtDiretorias->bindParam(':corretor', $corretor, PDO::PARAM_STR);
        }
        if ($gerencia !== '') {
            $stmtDiretorias->bindParam(':gerencia', $gerencia, PDO::PARAM_STR);
        }
        
        $stmtDiretorias->execute();
        $dadosDiretorias = $stmtDiretorias->fetchAll(PDO::FETCH_ASSOC);
        
        // VENDAS POR EMPREENDIMENTO (usando JOIN para obter nome atualizado)
        $sqlEmpreendimentos = "SELECT 
                                    COALESCE(emp.NomeEmpreendimento, v.NomeEmpreendimento) AS NomeEmpreendimento, 
                                    COUNT(*) as unidades, 
                                    SUM(v.ValorUnidade) as vgv 
                               FROM vendas v
                               LEFT JOIN empreendimento emp ON (emp.Empreend_ID = v.Empreend_Id OR emp.NomeEmpreendimento = v.NomeEmpreendimento)
                               " . $whereClause . " 
                               GROUP BY NomeEmpreendimento ORDER BY SUM(v.ValorUnidade) DESC 
                               LIMIT 10";
        $stmtEmpreendimentos = $conn->prepare($sqlEmpreendimentos);
        
        // Bind parameters
        if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
            $stmtEmpreendimentos->bindParam(':diretoriaid', $diretoriaID, PDO::PARAM_INT);
        }
        if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
            $stmtEmpreendimentos->bindParam(':ano', $anoParaUsar, PDO::PARAM_INT);
        }
        if ($mes !== '' && is_numeric($mes)) {
            $stmtEmpreendimentos->bindParam(':mes', $mes, PDO::PARAM_INT);
        }
        if ($trimestre !== '' && is_numeric($trimestre)) {
            $stmtEmpreendimentos->bindParam(':trimestre', $trimestre, PDO::PARAM_INT);
        }
        if ($semestre !== '' && is_numeric($semestre)) {
            $stmtEmpreendimentos->bindParam(':semestre', $semestre, PDO::PARAM_INT);
        }
        if ($corretor !== '') {
            $stmtEmpreendimentos->bindParam(':corretor', $corretor, PDO::PARAM_STR);
        }
        if ($gerencia !== '') {
            $stmtEmpreendimentos->bindParam(':gerencia', $gerencia, PDO::PARAM_STR);
        }
        
        $stmtEmpreendimentos->execute();
        $dadosEmpreendimentos = $stmtEmpreendimentos->fetchAll(PDO::FETCH_ASSOC);
        
        // VENDAS POR EMPRESA (CORRIGIDO: usa JOIN para obter nome da empresa)
        $sqlEmpresas = "SELECT 
                            COALESCE(e.NomeEmpresa, emp.NomeEmpresa, v.NomeEmpresa) AS NomeEmpresa,
                            COUNT(*) as unidades, 
                            SUM(v.ValorUnidade) as vgv 
                        FROM vendas v
                        LEFT JOIN empreendimento emp ON (emp.Empreend_ID = v.Empreend_Id OR emp.NomeEmpreendimento = v.NomeEmpreendimento)
                        LEFT JOIN empresa e ON (e.Empresa_ID = v.Empresa_Id OR e.Empresa_ID = emp.Empresa_ID)
                        " . $whereClause . " 
                        GROUP BY NomeEmpresa ORDER BY SUM(v.ValorUnidade) DESC 
                        LIMIT 10";
        $stmtEmpresas = $conn->prepare($sqlEmpresas);
        
        // Bind parameters
        if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
            $stmtEmpresas->bindParam(':diretoriaid', $diretoriaID, PDO::PARAM_INT);
        }
        if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
            $stmtEmpresas->bindParam(':ano', $anoParaUsar, PDO::PARAM_INT);
        }
        if ($mes !== '' && is_numeric($mes)) {
            $stmtEmpresas->bindParam(':mes', $mes, PDO::PARAM_INT);
        }
        if ($trimestre !== '' && is_numeric($trimestre)) {
            $stmtEmpresas->bindParam(':trimestre', $trimestre, PDO::PARAM_INT);
        }
        if ($semestre !== '' && is_numeric($semestre)) {
            $stmtEmpresas->bindParam(':semestre', $semestre, PDO::PARAM_INT);
        }
        if ($corretor !== '') {
            $stmtEmpresas->bindParam(':corretor', $corretor, PDO::PARAM_STR);
        }
        if ($gerencia !== '') {
            $stmtEmpresas->bindParam(':gerencia', $gerencia, PDO::PARAM_STR);
        }
        
        $stmtEmpresas->execute();
        $dadosEmpresas = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);
        
        // VENDAS POR GERÊNCIA (mantido)
        $sqlGerenciaVendas = "SELECT v.Gerencia, COUNT(*) as unidades, SUM(v.ValorUnidade) as vgv 
                             FROM vendas v" . $whereClause . " 
                             GROUP BY v.Gerencia ORDER BY SUM(v.ValorUnidade) DESC";
        $stmtGerenciaVendas = $conn->prepare($sqlGerenciaVendas);
        
        // Bind parameters
        if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
            $stmtGerenciaVendas->bindParam(':diretoriaid', $diretoriaID, PDO::PARAM_INT);
        }
        if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
            $stmtGerenciaVendas->bindParam(':ano', $anoParaUsar, PDO::PARAM_INT);
        }
        if ($mes !== '' && is_numeric($mes)) {
            $stmtGerenciaVendas->bindParam(':mes', $mes, PDO::PARAM_INT);
        }
        if ($trimestre !== '' && is_numeric($trimestre)) {
            $stmtGerenciaVendas->bindParam(':trimestre', $trimestre, PDO::PARAM_INT);
        }
        if ($semestre !== '' && is_numeric($semestre)) {
            $stmtGerenciaVendas->bindParam(':semestre', $semestre, PDO::PARAM_INT);
        }
        if ($corretor !== '') {
            $stmtGerenciaVendas->bindParam(':corretor', $corretor, PDO::PARAM_STR);
        }
        if ($gerencia !== '') {
            $stmtGerenciaVendas->bindParam(':gerencia', $gerencia, PDO::PARAM_STR);
        }
        
        $stmtGerenciaVendas->execute();
        $dadosGerenciaVendas = $stmtGerenciaVendas->fetchAll(PDO::FETCH_ASSOC);
        
        // CORRETORES COM VGV (mantido)
        $sqlCorretoresVGV = "SELECT v.Corretor, COUNT(*) as unidades, SUM(v.ValorUnidade) as vgv 
                            FROM vendas v" . $whereClause . " 
                            GROUP BY v.Corretor ORDER BY SUM(v.ValorUnidade) DESC 
                            LIMIT 20";
        
        $stmtCorretoresVGV = $conn->prepare($sqlCorretoresVGV);
        
        // Bind parameters
        if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
            $stmtCorretoresVGV->bindParam(':diretoriaid', $diretoriaID, PDO::PARAM_INT);
        }
        if ($anoParaUsar !== '' && is_numeric($anoParaUsar)) {
            $stmtCorretoresVGV->bindParam(':ano', $anoParaUsar, PDO::PARAM_INT);
        }
        if ($mes !== '' && is_numeric($mes)) {
            $stmtCorretoresVGV->bindParam(':mes', $mes, PDO::PARAM_INT);
        }
        if ($trimestre !== '' && is_numeric($trimestre)) {
            $stmtCorretoresVGV->bindParam(':trimestre', $trimestre, PDO::PARAM_INT);
        }
        if ($semestre !== '' && is_numeric($semestre)) {
            $stmtCorretoresVGV->bindParam(':semestre', $semestre, PDO::PARAM_INT);
        }
        if ($corretor !== '') {
            $stmtCorretoresVGV->bindParam(':corretor', $corretor, PDO::PARAM_STR);
        }
        if ($gerencia !== '') {
            $stmtCorretoresVGV->bindParam(':gerencia', $gerencia, PDO::PARAM_STR);
        }
        
        $stmtCorretoresVGV->execute();
        $dadosCorretoresVGV = $stmtCorretoresVGV->fetchAll(PDO::FETCH_ASSOC);
        
    } // Fim do if ($isFiltered)
    
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Fechar conexão
//$conn = null;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Vendas - Diretoria <?php echo isset($_SESSION['Dir_Nome']) ? $_SESSION['Dir_Nome'] : ''; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<!--  -->
<!-- jQuery (obrigatório para DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables CSS e JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<!--  -->

    <style>
        /* Todos os estilos CSS da versão original mantidos aqui */
        body { 
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card { 
            margin-bottom: 20px; 
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            padding: 15px 20px;
        }
        .table th { 
            background-color: #f8f9fa; 
            font-weight: 600;
        }
        .table td { vertical-align: middle; }
        .total { 
            font-weight: bold; 
            background-color: #e8f4ff !important;
        }
        .badge-percent {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        .metric-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
            margin-bottom: 15px;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 5px 0;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .btn-filter {
            padding: 10px 20px;
            font-weight: 600;
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        .progress-thin {
            height: 5px;
            border-radius: 2px;
        }
        .card-body {
            overflow-x: auto;
            overflow-y: hidden;
        }
        #graficoVGV {
            min-width: 100%;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 500px;
            }
        }
        
        .card-mes-vazio {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
        }
        .card-mes-com-dados {
            background-color: #e8f4ff;
            border: 1px solid #3498db;
            color: #2c3e50;
        }
        .card-mes-filtrado {
            background-color: #ffe8e8;
            border: 2px solid #e74c3c;
            color: #c0392b;
        }
        .auto-filter-badge {
            font-size: 0.75rem;
            padding: 2px 6px;
            margin-left: 5px;
        }
        
        /* Estilo para corretor filtrado */
        .corretor-filtrado {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }
        /* Estilo para gerência filtrada */
        .gerencia-filtrada {
            background-color: #d4edda !important;
            border-left: 4px solid #28a745 !important;
        }
        .lista-corretores {
            max-height: 400px;
            overflow-y: auto;
        }
        .corretor-item {
            transition: all 0.3s ease;
        }
        .corretor-item:hover {
            background-color: #f8f9fa;
        }
        
        /* Novos estilos para cards de mês */
        .card-mes-completo {
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .card-mes-completo:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .unidades-mes {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .valor-mes {
            font-size: 1.1rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .card-mes-sem-dados {
            opacity: 0.6;
        }
        
        /* Estilo para ranking de corretores */
        .ranking-badge {
            font-size: 0.8rem;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 8px;
        }
        .ranking-1 { background-color: #ffd700; color: #000; }
        .ranking-2 { background-color: #c0c0c0; color: #000; }
        .ranking-3 { background-color: #cd7f32; color: #000; }
        .ranking-outros { background-color: #6c757d; color: #fff; }
        
        /* Estilo para filtros em colunas responsivas */
        @media (max-width: 768px) {
            .filtro-row {
                flex-direction: column;
            }
            .filtro-col {
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        /* Estilo para zoom da página */
        body {
            transform: scale(0.9); 
            transform-origin: 0 0; 
            width: calc(100% / 0.9); 
        }
        
        @media (max-width: 768px) {
            body {
                transform: scale(1); 
                width: 100%; 
            }
        }
    </style>    
</head>
<body>
    <div class="container mt-4">
<div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #1a2a3a 0%, #2980b9 100%); border-radius: 12px;">
    <div class="card-body text-white p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <span class="text-uppercase fw-bold mb-1 d-block" style="letter-spacing: 1px; font-size: 0.85rem; opacity: 0.8;">
                    <i class="fas fa-chart-line me-1"></i> Relatório de Vendas
                </span>
                
                <h1 class="display-6 mb-0">
                    <span class="fw-light opacity-75">Diretoria:</span> 
                    <strong class="fw-bold">
                        <?php echo isset($_SESSION['NOME_DIRETORIA']) ? $_SESSION['NOME_DIRETORIA'] : 'Geral'; ?>
                    </strong>
                </h1>
            </div>

            <?php if ($anoSelecionadoAutomaticamente && $anoParaUsar !== '') : ?>
            <div class="mt-3 mt-lg-0">
                <div class="p-2 px-3 rounded-3" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                    <small class="d-block mb-1 opacity-75">Período de Referência:</small>
                    <div class="d-flex align-items-center">
                        <strong class="fs-5 me-2"><?php echo $anoParaUsar; ?></strong>
                        <?php if (in_array($anoParaUsar, $anosDisponiveis)) : ?>
                            <span class="badge bg-success shadow-sm">Ano atual</span>
                        <?php else : ?>
                            <span class="badge bg-warning text-dark shadow-sm">Mais recente</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($anoSelecionadoAutomaticamente) : ?>
        <div class="mt-3 pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
            <small class="opacity-75">
                <i class="fas fa-robot me-1"></i> Inteligência de dados: Filtro automático aplicado com base no histórico disponível.
            </small>
        </div>
        <?php endif; ?>
    </div>
</div>
        
        <!-- FILTROS -->
        <div class="card mb-4">
            <div class="card-header text-white" style="background: #3498db;">
                <i class="fas fa-filter me-2"></i>Filtros do Relatório
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-3">
                        <label for="ano" class="form-label fw-bold">Ano:</label>
                        <select class="form-select" id="ano" name="ano">
                            <option value="">Selecione o ano</option>
                            <?php if ($anosCount > 0) : ?>
                                <?php foreach ($anosDisponiveis as $anoDisponivel) : ?>
                                    <option value="<?php echo $anoDisponivel; ?>" <?php echo ($anoDisponivel == $ano) ? 'selected' : ''; ?>>
                                        <?php echo $anoDisponivel; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="">Nenhum ano disponível</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($anosCount == 0) : ?>
                            <small class="text-warning">Nenhum ano com vendas encontrado</small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label for="mes" class="form-label fw-bold">Mês:</label>
                        <select class="form-select" id="mes" name="mes">
                            <option value="">Todos os meses</option>
                            <?php
                            // Array com os nomes dos meses em português
                            $meses_pt = [
                                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                            ];
                            ?>
                            <?php for ($i = 1; $i <= 12; $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $mes) ? 'selected' : ''; ?>>
                                    <?php echo $meses_pt[$i]; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="trimestre" class="form-label fw-bold">Trimestre:</label>
                        <select class="form-select" id="trimestre" name="trimestre">
                            <option value="">Todos trimestres</option>
                            <?php for ($i = 1; $i <= 4; $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $trimestre) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>º Trimestre
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="semestre" class="form-label fw-bold">Semestre:</label>
                        <select class="form-select" id="semestre" name="semestre">
                            <option value="">Todos semestres</option>
                            <?php for ($i = 1; $i <= 2; $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $semestre) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>º Semestre
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <!-- FILTRO: GERÊNCIA -->
                    <div class="col-md-4">
                        <label for="gerencia" class="form-label fw-bold">Gerência:</label>
                        <select class="form-select" id="gerencia" name="gerencia">
                            <option value="">Todas as gerências</option>
                            <?php if ($gerenciasCount > 0) : ?>
                                <?php foreach ($gerenciasDisponiveis as $gerenciaDisponivel) : ?>
                                    <option value="<?php echo htmlspecialchars($gerenciaDisponivel); ?>" 
                                        <?php echo ($gerenciaDisponivel == $gerencia) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($gerenciaDisponivel); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="">Nenhuma gerência disponível</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($gerenciasCount == 0) : ?>
                            <small class="text-warning">Nenhuma gerência encontrada</small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- FILTRO: CORRETOR - VERSÃO SIMPLIFICADA -->
                    <div class="col-md-4">
                        <label for="corretor" class="form-label fw-bold">Corretor:</label>
                        <select class="form-select" id="corretor" name="corretor">
                            <option value="">Todos os corretores</option>
                            <?php if ($corretoresCount > 0) : ?>
                                <?php foreach ($corretoresDisponiveis as $corretorNome) : ?>
                                    <option value="<?php echo htmlspecialchars($corretorNome); ?>" 
                                        <?php echo ($corretorNome == $corretor) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($corretorNome); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="">Nenhum corretor disponível</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($corretoresCount == 0) : ?>
                            <small class="text-warning">Nenhum corretor encontrado</small>
                        <?php endif; ?>
                        <?php if ($gerencia !== '') : ?>
                            <small class="text-info">
                                <i class="fas fa-info-circle me-1"></i>Mostrando apenas corretores da gerência: <strong><?php echo htmlspecialchars($gerencia); ?></strong>
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4"></div>
                    
                    <div class="col-12 d-flex gap-3 mt-2">
                        <button type="submit" class="btn btn-primary btn-filter">
                            <i class="fas fa-search me-2"></i>Aplicar Filtros
                        </button>
                        <a href="gestao_vendas_diretoria3.php" class="btn btn-secondary btn-filter">
                            <i class="fas fa-times me-2"></i>Limpar Filtro
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($isFiltered) : ?>
        <!-- FILTROS ATIVOS -->
        <div class="card mb-4 border-warning">
            <div class="card-header text-white" style="background: #ffc107;">
                <i class="fas fa-check-circle me-2"></i>Filtros Ativos
                <?php if ($anoSelecionadoAutomaticamente) : ?>
                    <span class="float-end badge bg-info">Filtro automático</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php if (!empty($diretoriaID) && is_numeric($diretoriaID)) : ?>
                        <div class="col-auto">
                            <span class="badge bg-primary p-2"><i class="fas fa-building me-1"></i> Diretoria ID: <?php echo $diretoriaID; ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($ano !== '') : ?>
                        <div class="col-auto">
                            <span class="badge bg-info p-2">
                                <i class="fas fa-calendar me-1"></i> Ano: <?php echo $ano; ?>
                                <?php if ($anoSelecionadoAutomaticamente) : ?>
                                    <span class="badge bg-light text-dark ms-1">Automático</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($mes !== '') : ?>
                        <div class="col-auto">
                            <span class="badge bg-success p-2"><i class="fas fa-calendar-alt me-1"></i> Mês: <?php echo date('F', mktime(0, 0, 0, $mes, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($trimestre !== '') : ?>
                        <div class="col-auto">
                            <span class="badge bg-warning text-dark p-2"><i class="fas fa-chart-pie me-1"></i> 
                                <?php echo $trimestre; ?>º Trim
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($semestre !== '') : ?>
                        <div class="col-auto">
                            <span class="badge bg-danger p-2"><i class="fas fa-chart-line me-1"></i> 
                                <?php echo $semestre; ?>º Sem
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($gerencia !== '') : ?>
                        <div class="col-auto">
                            <span class="badge bg-success p-2"><i class="fas fa-user-tie me-1"></i> Gerência: <?php echo htmlspecialchars($gerencia); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($corretor !== '') : ?>
                        <div class="col-auto">
                            <span class="badge bg-dark p-2"><i class="fas fa-user me-1"></i> Corretor: <?php echo strtoupper(htmlspecialchars($corretor)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="resultados">
            <?php if ($isFiltered) : ?>
                <!-- Título do período exibido -->
                <div class="text-center mb-3">
                    <h5 class="text-muted">
                        VGV por mês em <strong><?php echo htmlspecialchars($tituloPeriodo); ?></strong>
                        <?php if ($anoSelecionadoAutomaticamente) : ?>
                            <span class="badge bg-info ms-2"><i class="fas fa-robot me-1"></i> Filtro automático</span>
                        <?php endif; ?>
                        <?php if ($mes !== '' && is_numeric($mes)) : ?>
                            <span class="badge bg-info ms-2"><?php echo $arrMesesNome[intval($mes)]; ?> selecionado</span>
                        <?php endif; ?>
                        <?php if ($gerencia !== '') : ?>
                            <span class="badge bg-success ms-2"><i class="fas fa-user-tie me-1"></i> Gerência: <?php echo htmlspecialchars($gerencia); ?></span>
                        <?php endif; ?>
                        <?php if ($corretor !== '') : ?>
                            <span class="badge bg-dark ms-2"><i class="fas fa-user me-1"></i> Corretor: <?php echo strtoupper(htmlspecialchars($corretor)); ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                
                <!-- 12 CARDS DE VGV POR MÊS -->
                <div class="row mb-4 g-3">
                    <?php
                    $mesesExibidos = 0;
                    for ($i = 1; $i <= 12; $i++) :
                        if ($mesesParaExibir[$i]) :
                            $mesesExibidos++;
                            
                            // Determinar se é o mês filtrado
                            $isMesFiltrado = false;
                            if ($mes !== '' && is_numeric($mes) && intval($mes) == $i) {
                                $isMesFiltrado = true;
                            }
                            
                            // Determinar classe CSS baseada nos dados
                            if ($mesesVGV[$i] > 0) {
                                if ($isMesFiltrado) {
                                    $bgClass = "card-mes-filtrado";
                                    $cardClass = "card-mes-completo";
                                } else {
                                    $bgClass = "card-mes-com-dados";
                                    $cardClass = "card-mes-completo";
                                }
                                
                                // Formatar valor para exibição
                                if ($mesesVGV[$i] >= 1000000) {
                                    $valorMesFormatado = "R$ " . number_format($mesesVGV[$i]/1000000, 2, ',', '.') . " M";
                                } elseif ($mesesVGV[$i] >= 1000) {
                                    $valorMesFormatado = "R$ " . number_format($mesesVGV[$i]/1000, 0, ',', '.') . " mil";
                                } else {
                                    $valorMesFormatado = "R$ " . number_format($mesesVGV[$i], 0, ',', '.');
                                }
                                
                                // Formatar unidades
                                $unidadesMesFormatado = number_format($mesesUnidades[$i], 0, ',', '.') . " unid.";
                            } else {
                                $bgClass = "card-mes-vazio";
                                $cardClass = "card-mes-completo card-mes-sem-dados";
                                $valorMesFormatado = "R$ 0";
                                $unidadesMesFormatado = "0 unid.";
                            }
                    ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="card h-100 shadow-sm <?php echo $cardClass; ?> <?php echo $bgClass; ?>">
                            <div class="card-body text-center p-3">
                                <h6 class="mb-2 fw-bold">
                                    <?php echo strtoupper(substr($arrMesesNome[$i], 0, 3)); ?>
                                </h6>
                                <div class="valor-mes mb-2">
                                    <?php echo $valorMesFormatado; ?>
                                </div>
                                <div class="unidades-mes">
                                    <?php echo $unidadesMesFormatado; ?>
                                </div>
                                <?php if ($isMesFiltrado) : ?>
                                    <small class="badge bg-danger text-white mt-2">FILTRADO</small>
                                <?php elseif ($mesesVGV[$i] == 0) : ?>
                                    <small class="text-muted mt-2">Sem vendas</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                        endif;
                    endfor;
                    
                    // Se nenhum mês for exibido (filtro muito restritivo)
                    if ($mesesExibidos == 0) :
                    ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>Nenhum mês corresponde aos filtros aplicados.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- TOTAIS GERAIS -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="metric-card" style="background: #3498db;">
                            <div class="metric-label"><i class="fas fa-cube me-2"></i> Total de Unidades Vendidas</div>
                            <div class="metric-value"><?php echo number_format($totalUnidades, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="metric-card" style="background: #2ecc71;">
                            <div class="metric-label"><i class="fas fa-money-bill-wave me-2"></i> Total VGV</div>
                            <div class="metric-value">R$ <?php echo number_format($totalVGV, 2, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- GRÁFICO DE VGV POR MÊS -->
                <?php if ($podeMostrarGrafico) : ?>
                    <div class="card mb-4">
                        <div class="card-header text-white" style="background: #9b59b6;">
                            <i class="fas fa-chart-line me-2"></i>VGV por Mês
                            <?php
                            if ($filtroMesEspecifico) {
                                echo "<span class='float-end badge bg-info'>Mês específico: " . $arrMesesNome[intval($mes)] . "</span>";
                            } elseif ($filtroTrimestre) {
                                echo "<span class='float-end badge bg-info'>Trimestre " . $trimestre . "</span>";
                            } elseif ($filtroSemestre) {
                                echo "<span class='float-end badge bg-info'>Semestre " . $semestre . "</span>";
                            }
                            ?>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="graficoVGV"></canvas>
                            </div>
                        </div>
                    </div>
                <?php elseif ($mes !== '' || $gerencia !== '' || $corretor !== '') : ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Gráfico não exibido quando filtro de <strong>mês</strong>, <strong>gerência</strong> ou <strong>corretor</strong> está ativo.
                    </div>
                <?php else : ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Selecione um ano para visualizar o gráfico
                    </div>
                <?php endif; ?>
                
                <!-- SEÇÕES DE ANÁLISE -->
                <div class="row">
                    <!-- VENDAS POR DIRETORIA -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header text-white" style="background: #e74c3c;">
                                <i class="fas fa-building me-2"></i>Vendas por Diretoria
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dadosDiretorias)) : ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Diretoria</th>
                                                    <th class="text-center">Unid.</th>
                                                    <th class="text-end">VGV</th>
                                                    <th class="text-end">%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $totalUnidadesDiretoria = 0;
                                                $totalVGVDiretoria = 0;
                                                foreach ($dadosDiretorias as $diretoria) :
                                                    $vgvDiretoria = floatval($diretoria['vgv']);
                                                    $percentual = ($totalVGV != 0) ? ($vgvDiretoria / $totalVGV) * 100 : 0;
                                                    $totalUnidadesDiretoria += intval($diretoria['unidades']);
                                                    $totalVGVDiretoria += $vgvDiretoria;
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($diretoria['Diretoria']); ?></td>
                                                        <td class="text-center"><?php echo number_format($diretoria['unidades'], 0, ',', '.'); ?></td>
                                                        <td class="text-end">R$ <?php echo number_format($vgvDiretoria, 0, ',', '.'); ?></td>
                                                        <td class="text-end"><span class="badge bg-primary badge-percent"><?php echo number_format($percentual, 1, ',', '.'); ?>%</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="total">
                                                    <td><strong>TOTAL</strong></td>
                                                    <td class="text-center"><strong><?php echo number_format($totalUnidadesDiretoria, 0, ',', '.'); ?></strong></td>
                                                    <td class="text-end"><strong>R$ <?php echo number_format($totalVGVDiretoria, 0, ',', '.'); ?></strong></td>
                                                    <td class="text-end"><strong>100%</strong></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <p class="text-center text-muted py-4">Nenhuma venda encontrada</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- VENDAS POR GERÊNCIA -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header text-white" style="background: #3498db;">
                                <i class="fas fa-user-tie me-2"></i>Vendas por Gerência
                                <?php if ($gerencia !== '') : ?>
                                <span class="float-end badge bg-success">Filtrada: <?php echo htmlspecialchars($gerencia); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dadosGerenciaVendas)) : ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Gerência</th>
                                                    <th class="text-center">Unid.</th>
                                                    <th class="text-end">VGV</th>
                                                    <th class="text-end">%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $totalUnidadesGerencia = 0;
                                                $totalVGVGerencia = 0;
                                                foreach ($dadosGerenciaVendas as $gerenciaRow) :
                                                    $vgvGerencia = floatval($gerenciaRow['vgv']);
                                                    $percentual = ($totalVGV != 0) ? ($vgvGerencia / $totalVGV) * 100 : 0;
                                                    $totalUnidadesGerencia += intval($gerenciaRow['unidades']);
                                                    $totalVGVGerencia += $vgvGerencia;
                                                    
                                                    $linhaClass = ($gerencia !== '' && $gerenciaRow['Gerencia'] == $gerencia) ? 'gerencia-filtrada' : '';
                                                ?>
                                                    <tr class="<?php echo $linhaClass; ?>">
                                                        <td>
                                                            <?php echo htmlspecialchars($gerenciaRow['Gerencia']); ?>
                                                            <?php if ($gerencia !== '' && $gerenciaRow['Gerencia'] == $gerencia) : ?>
                                                                <span class="badge bg-success ms-2">FILTRADA</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center"><?php echo number_format($gerenciaRow['unidades'], 0, ',', '.'); ?></td>
                                                        <td class="text-end">R$ <?php echo number_format($vgvGerencia, 0, ',', '.'); ?></td>
                                                        <td class="text-end"><span class="badge bg-success badge-percent"><?php echo number_format($percentual, 1, ',', '.'); ?>%</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="total">
                                                    <td><strong>TOTAL</strong></td>
                                                    <td class="text-center"><strong><?php echo number_format($totalUnidadesGerencia, 0, ',', '.'); ?></strong></td>
                                                    <td class="text-end"><strong>R$ <?php echo number_format($totalVGVGerencia, 0, ',', '.'); ?></strong></td>
                                                    <td class="text-end"><strong>100%</strong></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <p class="text-center text-muted py-4">Nenhuma venda encontrada</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- OUTRAS SEÇÕES -->
                <div class="row mt-4">
                    <!-- VENDAS POR EMPREENDIMENTO -->
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header text-white" style="background: #2ecc71;">
                                <i class="fas fa-building me-2"></i> Empreendimentos
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dadosEmpreendimentos)) : ?>
                                    <div class="list-group list-group-flush">
                                        <?php
                                        $cont = 0;
                                        foreach ($dadosEmpreendimentos as $empreendimento) :
                                            $cont++;
                                            $vgvEmpreendimento = floatval($empreendimento['vgv']);
                                            $percentual = ($totalVGV != 0) ? ($vgvEmpreendimento / $totalVGV) * 100 : 0;
                                        ?>
                                            <div class="list-group-item border-0 py-2 px-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo $cont . "-" . htmlspecialchars($empreendimento['NomeEmpreendimento']); ?></strong><br>
                                                        <small class="text-muted"><?php echo number_format($empreendimento['unidades'], 0, ',', '.'); ?> unidades</small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold">R$ <?php echo number_format($vgvEmpreendimento, 0, ',', '.'); ?></div>
                                                        <small class="text-muted"><?php echo number_format($percentual, 1, ',', '.'); ?>%</small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p class="text-center text-muted py-4">Nenhum dado</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- VENDAS POR EMPRESA (CORRIGIDO) -->
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header text-white" style="background: #9b59b6;">
                                <i class="fas fa-industry me-2"></i>Vendas por Empresa
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dadosEmpresas)) : ?>
                                    <div class="list-group list-group-flush">
                                        <?php
                                        $cont = 0;
                                        foreach ($dadosEmpresas as $empresa) :
                                            $cont++;
                                            $vgvEmpresa = floatval($empresa['vgv']);
                                            $percentual = ($totalVGV != 0) ? ($vgvEmpresa / $totalVGV) * 100 : 0;
                                            // Se o nome da empresa estiver vazio, exibir "(Sem empresa)"
                                            $nomeEmpresa = trim($empresa['NomeEmpresa']);
                                            if (empty($nomeEmpresa)) {
                                                $nomeEmpresa = "(Sem empresa)";
                                            }
                                        ?>
                                            <div class="list-group-item border-0 py-2 px-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo $cont . "-" . htmlspecialchars($nomeEmpresa); ?></strong><br>
                                                        <small class="text-muted"><?php echo number_format($empresa['unidades'], 0, ',', '.'); ?> unidades</small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold">R$ <?php echo number_format($vgvEmpresa, 0, ',', '.'); ?></div>
                                                        <small class="text-muted"><?php echo number_format($percentual, 1, ',', '.'); ?>%</small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p class="text-center text-muted py-4">Nenhum dado</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- LISTA SIMPLIFICADA DE CORRETORES -->
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header text-white" style="background: #f39c12;">
                                <i class="fas fa-users me-2"></i> 
                                <?php if ($corretor !== '') : ?>
                                    Corretor Selecionado
                                <?php else : ?>
                                    Principais Corretores
                                <?php endif; ?>
                                <?php if ($gerencia !== '' && $corretor == '') : ?>
                                <span class="float-end badge bg-success">Gerência: <?php echo htmlspecialchars($gerencia); ?></span>
                                <?php else : ?>
                                <span class="float-end badge bg-light text-dark">
                                    <?php if ($corretor !== '') : ?>
                                        1 corretor
                                    <?php else : ?>
                                        <?php echo count($dadosCorretoresVGV); ?> corretores
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dadosCorretoresVGV)) : ?>
                                    <div class="list-group list-group-flush">
                                        <?php
                                        $cont = 0;
                                        foreach ($dadosCorretoresVGV as $corretorRow) :
                                            $cont++;
                                            $vgvCorretor = floatval($corretorRow['vgv']);
                                            $percentual = ($totalVGV != 0) ? ($vgvCorretor / $totalVGV) * 100 : 0;
                                            
                                            // Determinar classe de ranking
                                            if ($cont == 1) {
                                                $rankingClass = "ranking-1";
                                            } elseif ($cont == 2) {
                                                $rankingClass = "ranking-2";
                                            } elseif ($cont == 3) {
                                                $rankingClass = "ranking-3";
                                            } else {
                                                $rankingClass = "ranking-outros";
                                            }
                                            
                                            // Determinar se é o corretor filtrado
                                            $isCorretorFiltrado = ($corretor !== '' && $corretorRow['Corretor'] == $corretor);
                                            $listItemClass = "list-group-item corretor-item";
                                            if ($isCorretorFiltrado) {
                                                $listItemClass .= " corretor-filtrado";
                                            }
                                        ?>
                                            <a href="javascript:void(0)" onclick="document.getElementById('corretor').value='<?php echo htmlspecialchars($corretorRow['Corretor']); ?>'; document.forms[0].submit();" class="<?php echo $listItemClass; ?> text-decoration-none">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <span class="ranking-badge <?php echo $rankingClass; ?>"><?php echo $cont; ?></span>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($corretorRow['Corretor']); ?></strong>
                                                            <?php if ($isCorretorFiltrado) : ?>
                                                                <span class="badge bg-warning text-dark ms-2">Filtrado</span>
                                                            <?php endif; ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo number_format($corretorRow['unidades'], 0, ',', '.'); ?> unidades</small>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold">R$ <?php echo number_format($vgvCorretor, 0, ',', '.'); ?></div>
                                                        <small class="text-muted"><?php echo number_format($percentual, 1, ',', '.'); ?>%</small>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- RESUMO SIMPLES -->
                                    <div class="mt-3">
                                        <div class="alert alert-light">
                                            <div class="row">
                                                <div class="col-12">
                                                    <strong>Total de Corretores:</strong> 
                                                    <?php if ($corretor !== '') : ?>
                                                        <span class="badge bg-warning text-dark">1 filtrado</span>
                                                    <?php else : ?>
                                                        <?php echo count($dadosCorretoresVGV); ?>
                                                        <?php if ($gerencia !== '') : ?>
                                                            <span class="badge bg-success ms-2">da gerência: <?php echo htmlspecialchars($gerencia); ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($corretor == '') : ?>
                                                <div class="col-12 mt-1">
                                                    <strong>Média VGV/Corretor:</strong> 
                                                    <?php if (count($dadosCorretoresVGV) > 0) : ?>
                                                        R$ <?php echo number_format($totalVGV/count($dadosCorretoresVGV), 0, ',', '.'); ?>
                                                    <?php else : ?>
                                                        R$ 0
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($corretor !== '') : ?>
                                        <div class="d-grid gap-2">
                                            <a href="javascript:void(0)" onclick="document.getElementById('corretor').value=''; document.forms[0].submit();" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-times me-1"></i> Remover Filtro de Corretor
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else : ?>
                                    <div class="text-center text-muted py-4">
                                        Nenhum corretor encontrado
                                        <?php if ($gerencia !== '') : ?>
                                        <br><small>para a gerência: <strong><?php echo htmlspecialchars($gerencia); ?></strong></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else : ?>
                <!-- INSTRUÇÕES QUANDO NÃO HÁ FILTRO -->
                <div class="card border-info">
                    <div class="card-header text-white" style="background: #17a2b8;">
                        <i class="fas fa-info-circle me-2"></i>Instruções
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="fas fa-filter fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted mb-3">Selecione os filtros acima para visualizar os dados de vendas</h4>
                        <?php if (!empty($diretoriaID) && is_numeric($diretoriaID)) : ?>
                            <div class="alert alert-info">
                                O filtro de <strong>Diretoria (ID: <?php echo $diretoriaID; ?>)</strong> já está aplicado automaticamente para o seu perfil.
                            </div>
                        <?php endif; ?>
                        <?php if ($anosCount == 0) : ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>Nenhum ano com vendas encontrado para sua diretoria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!--  -->
<style>
    /* Forçar estilo do cabeçalho da tabela de vendas */
    .table-vendas thead th {
        background-color: #2c3e50 !important;
        color: white !important;
        font-weight: bold;
        border-color: #1a252f;
    }
    /* Ajuste para o DataTable */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        margin-bottom: 15px;
    }
    /* Alinhamento de valores */
    .text-right {
        text-align: right !important;
    }
</style>

<!-- NOVA SEÇÃO: LISTA DETALHADA DE VENDAS DA DIRETORIA COM DATATABLE -->
<!-- CORRIGIDO: Adicionada coluna Empresa com JOIN -->
<div class="row mt-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header text-white" style="background: #1abc9c;">
                <i class="fas fa-list-alt me-2"></i> Lista de Vendas da Diretoria
                <span class="float-end badge bg-light text-dark">Carregando...</span>
            </div>
            <div class="card-body">
                <?php
                // --- RECONSTRÓI O ARRAY DE PARÂMETROS COM BASE NOS FILTROS ATUAIS ---
                $paramsLista = [];
                $whereLista = " WHERE v.Excluido = 0";
                
                if (!empty($diretoriaID) && is_numeric($diretoriaID)) {
                    $whereLista .= " AND v.DiretoriaId = :diretoriaid";
                    $paramsLista[':diretoriaid'] = $diretoriaID;
                }
                if (!empty($anoParaUsar) && is_numeric($anoParaUsar)) {
                    $whereLista .= " AND v.AnoVenda = :ano";
                    $paramsLista[':ano'] = $anoParaUsar;
                }
                if (!empty($mes) && is_numeric($mes)) {
                    $whereLista .= " AND v.MesVenda = :mes";
                    $paramsLista[':mes'] = $mes;
                }
                if (!empty($trimestre) && is_numeric($trimestre)) {
                    $whereLista .= " AND v.Trimestre = :trimestre";
                    $paramsLista[':trimestre'] = $trimestre;
                }
                if (!empty($semestre) && is_numeric($semestre)) {
                    $whereLista .= " AND v.Semestre = :semestre";
                    $paramsLista[':semestre'] = $semestre;
                }
                if (!empty($corretor)) {
                    $whereLista .= " AND v.Corretor = :corretor";
                    $paramsLista[':corretor'] = $corretor;
                }
                if (!empty($gerencia)) {
                    $whereLista .= " AND v.Gerencia = :gerencia";
                    $paramsLista[':gerencia'] = $gerencia;
                }
                
                // --- CONSULTA COM JOIN PARA EMPRESA E EMPREENDIMENTO ---
                $sqlVendasLista = "
                    SELECT 
                        v.NomeEmpreendimento, 
                        v.DataVenda, 
                        v.Gerencia, 
                        v.Corretor, 
                        v.Localidade, 
                        v.ValorUnidade,
                        COALESCE(e.NomeEmpresa, emp.NomeEmpresa, v.NomeEmpresa) AS NomeEmpresa
                    FROM vendas v
                    LEFT JOIN empreendimento emp ON (emp.Empreend_ID = v.Empreend_Id OR emp.NomeEmpreendimento = v.NomeEmpreendimento)
                    LEFT JOIN empresa e ON (e.Empresa_ID = v.Empresa_Id OR e.Empresa_ID = emp.Empresa_ID)
                    " . $whereLista . " 
                    ORDER BY v.DataVenda DESC
                ";
                $stmtVendasLista = $conn->prepare($sqlVendasLista);
                $stmtVendasLista->execute($paramsLista);
                $vendasLista = $stmtVendasLista->fetchAll(PDO::FETCH_ASSOC);
                $totalRegistros = count($vendasLista);
                ?>
                
                <?php if (!empty($vendasLista)) : ?>
                    <div class="table-responsive">
                        <table id="tabelaVendas" class="table table-striped table-bordered table-vendas" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Data da Venda</th>
                                    <th>Empreendimento</th>
                                    <th>Empresa</th> <!-- NOVA COLUNA -->
                                    <th>Gerência</th>
                                    <th>Corretor</th>
                                    <th>Localidade</th>
                                    <th>Valor Unidade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendasLista as $vendaItem) : 
                                    $valorFormatado = "R$ " . number_format(floatval($vendaItem['ValorUnidade'] ?? 0), 2, ',', '.');
                                    $empresaExibida = trim($vendaItem['NomeEmpresa'] ?? '');
                                    if (empty($empresaExibida)) {
                                        $empresaExibida = '(Não definida)';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vendaItem['DataVenda'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($vendaItem['NomeEmpreendimento'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($empresaExibida); ?></td>
                                        <td><?php echo htmlspecialchars($vendaItem['Gerencia'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($vendaItem['Corretor'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($vendaItem['Localidade'] ?? ''); ?></td>
                                        <td class="text-right"><?php echo $valorFormatado; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <script>
                        // Atualiza o badge com o total de registros
                        document.querySelector('.card-header .badge').innerHTML = '<?php echo $totalRegistros; ?> vendas';
                    </script>
                <?php else : ?>
                    <div class="alert alert-info text-center mb-0">
                        <i class="fas fa-info-circle me-2"></i> Nenhuma venda encontrada para os filtros selecionados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>    
        <!--  -->
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<!--  -->
<script>
    $(document).ready(function() {
        $('#tabelaVendas').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json"
            },
            "order": [[0, "desc"]], // ordena pela coluna "Data da Venda" decrescente
            "pageLength": 100,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]]
        });
    });
</script>
<!--  -->

    
    <?php if ($isFiltered && $podeMostrarGrafico) : ?>
    <script>
    // Preparar dados JavaScript para o gráfico
    const mesesLabels = [
        <?php
        $labelCount = 0;
        for ($i = 1; $i <= 12; $i++) {
            if ($mesesParaExibir[$i]) {
                if ($labelCount > 0) echo ", ";
                echo "'" . strtoupper(substr($arrMesesNome[$i], 0, 3)) . "'";
                $labelCount++;
            }
        }
        ?>
    ];
    
    const vgvData = [
        <?php
        $dataCount = 0;
        for ($i = 1; $i <= 12; $i++) {
            if ($mesesParaExibir[$i]) {
                if ($dataCount > 0) echo ", ";
                echo $mesesVGV[$i];
                $dataCount++;
            }
        }
        ?>
    ];
    
    // Encontrar qual índice corresponde ao mês filtrado (para destaque)
    const indiceFiltrado = <?php
        $indiceFiltrado = -1;
        $contadorIndice = 0;
        if ($filtroMesEspecifico) {
            for ($i = 1; $i <= 12; $i++) {
                if ($mesesParaExibir[$i]) {
                    if (intval($mes) == $i) {
                        $indiceFiltrado = $contadorIndice;
                        break;
                    }
                    $contadorIndice++;
                }
            }
        }
        echo $indiceFiltrado;
    ?>;
    
    // Aguardar o carregamento da página
    document.addEventListener('DOMContentLoaded', function() {
        // Configuração do gráfico
        const ctx = document.getElementById('graficoVGV').getContext('2d');
        
        // Verificar se já existe um gráfico e destruí-lo
        if (window.myChart) {
            window.myChart.destroy();
        }
        
        // Converter dados para números
        const vgvDataNumerico = vgvData.map(item => Number(item));
        
        // Registrar o plugin datalabels
        Chart.register(ChartDataLabels);
        
        // Criar novo gráfico
        window.myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: mesesLabels,
                datasets: [{
                    label: 'VGV por Mês',
                    data: vgvDataNumerico,
                    backgroundColor: function(context) {
                        const index = context.dataIndex;
                        const value = context.dataset.data[index];
                        // Destacar a barra do mês filtrado (se houver)
                        if (index == indiceFiltrado) {
                            return 'rgba(231, 76, 60, 0.7)';
                        }
                        return value > 0 ? 'rgba(255, 165, 0, 0.7)' : 'rgba(200, 200, 200, 0.3)';
                    },
                    borderColor: function(context) {
                        const index = context.dataIndex;
                        if (index == indiceFiltrado) {
                            return 'rgb(231, 76, 60)';
                        }
                        return 'rgb(52, 152, 219)';
                    },
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.y;
                                return 'VGV: R$ ' + value.toLocaleString('pt-BR', {
                                    minimumFractionDigits: 2, 
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    },
                    datalabels: {
                        anchor: 'center',
                        align: 'center',
                        color: '#2c3e50',
                        font: {
                            weight: 'bold',
                            size: 11
                        },
                        rotation: -90,
                        formatter: function(value, context) {
                            if (value <= 0) return '';
                            
                            if (value >= 1000000) {
                                return 'R$' + (value / 1000000).toFixed(1).replace('.', ',') + 'M';
                            }
                            if (value >= 1000) {
                                return 'R$' + (value / 1000).toFixed(0) + 'K';
                            }
                            return 'R$' + value.toFixed(0);
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: {
                            display: true
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return 'R$' + (value / 1000000).toFixed(1).replace('.', ',') + 'M';
                                }
                                if (value >= 1000) {
                                    return 'R$' + (value / 1000).toFixed(0) + 'K';
                                }
                                return 'R$' + value.toFixed(0);
                            }
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
        
        // Redimensionar gráfico quando a janela for redimensionada
        window.addEventListener('resize', function() {
            if (window.myChart) {
                window.myChart.resize();
            }
        });
    });
    
    // Função para mostrar todos os corretores
    function mostrarTodosCorretores() {
        alert('Para ver todos os corretores, remova o filtro de corretor selecionando "Todos os corretores" no campo de filtro.');
    }
    </script>
    <?php endif; ?>

    <!-- Bloco vendas com empreendimentos -->

    <!--  -->
<footer style="text-align: center; padding: 20px; font-family: sans-serif; border-top: 1px solid #eee;">
    <p style="margin-bottom: 10px;"><strong>Valter Barreto</strong></p>

    <p style="font-size: 0.8rem; color: #888;">
        &copy; Abril de 2026 | Todos os Direitos Reservados.
    </p>
</footer>    
</body>
</html>
<!-- funcional 21:44 -->
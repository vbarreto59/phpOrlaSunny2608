<?php
//usr_acoes.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =================================================================================
// FUNÇÕES AUXILIARES GERAIS
// =================================================================================

// 26 04 2026

/**
 * Carrega configurações da empresa para a sessão
 * Corrigido para usar a variável $pdo que o seu sistema utiliza
 */
function CarregarConfiguracoesEmpresa($idEmp) {
    global $pdo; // Alterado de $conn para $pdo para evitar o erro

    if (empty($idEmp)) return false;

    try {
        $sql = "SELECT * FROM sunnyempresas WHERE IdEmp = :id LIMIT 1";
        $stmt = $pdo->prepare($sql); // Alterado para $pdo
        $stmt->execute(['id' => $idEmp]);

        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($empresa) {
            foreach ($empresa as $campo => $valor) {
                if (strpos($campo, 'Conf_') === 0) {
                    $_SESSION[$campo] = Nz($valor);
                }
            }
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Erro CarregarConfiguracoesEmpresa: " . $e->getMessage());
        return false;
    }
}

// ===========================



function Nz($value, $default = '') {
    return ($value === null || $value === '') ? $default : $value;
}

/**
 * Redireciona para a página de login mais apropriada
 */
function redirectToLogin(): void {
    $arquivos = ['login_p1.php', 'login_v66a.php', 'login.php'];
    foreach ($arquivos as $file) {
        if (file_exists($file)) {
            header("Location: $file");
            exit;
        }
    }
    header("Location: login.php");
    exit;
}

/**
 * Verifica se existe sessão válida de usuário logado
 */
function usuarioLogado(): bool {
    return isset($_SESSION['UserId'], $_SESSION['Usuario'])
        && is_numeric($_SESSION['UserId'])
        && (int)$_SESSION['UserId'] > 0
        && !empty(trim($_SESSION['Usuario']));
}

/**
 * Verifica se o usuário está ATIVO (valor já carregado na sessão)
 */
function usuarioAtivo(): bool {
    return usuarioLogado()
        && isset($_SESSION['Ativo'])
        && (int)$_SESSION['Ativo'] === 1;
}

/**
 * Verifica se o usuário possui LICENÇA ATIVA (valor já carregado na sessão)
 */
function usuarioComLicenca(): bool {
    return usuarioLogado()
        && isset($_SESSION['Licenca'])
        && (bool)$_SESSION['Licenca'] === true;
}

/**
 * Protege a página: exige login + ativo + (opcionalmente) licença
 * Use esta função no topo de todas as páginas protegidas
 */
function protegerPagina(bool $exigirLicenca = true): void {
    if (!usuarioLogado()) {
        redirectToLogin();
    }

    if (!usuarioAtivo()) {
        // Pode adicionar logout automático ou mensagem específica
        // session_destroy();
        redirectToLogin(); // ou header("Location: login_p1.php?erro=conta-inativa");
    }

    if ($exigirLicenca && !usuarioComLicenca()) {
        redirectToLogin(); // ou header("Location: login_p1.php?erro=licenca-invalida");
    }
}

// =================================================================================
// FUNÇÕES DE AUTENTICAÇÃO (usadas apenas no login)
// =================================================================================

/**
 * Verifica login válido e retorna dados do usuário
 * @return array ['success' => bool, 'data' => array]
 */
function VerificarLogin(string $usuario, string $senha): array {
    global $pdo;
    $success = false;
    $userData = [];

    $sql = "
        SELECT UserId, Usuario, Nome, Email, Telefones, Permissao, Funcao,
               Gerencia, Diretoria, Grupo, Licenca, Ativo, SenhaAtual,
               Data, DataHoraCadastro, IdEmp
        FROM usuarios
        WHERE LOWER(Usuario) = LOWER(:usuario)
          AND LOWER(SenhaAtual) = LOWER(:senha)
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $usuario, ':senha' => $senha]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $success = true;

            // Campos mínimos na sessão
            $_SESSION['UserId']    = (int)$row['UserId'];
            $_SESSION['Usuario']   = $row['Usuario'];
            $_SESSION['Permissao'] = Nz($row['Permissao']);
            $_SESSION['Ativo']     = (int)$row['Ativo'];
            $_SESSION['Licenca']   = (bool)$row['Licenca'];

            $userData = $row;
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Erro VerificarLogin: " . $e->getMessage());
    }

    return ['success' => $success, 'data' => $userData];
}

/**
 * Verifica se existe conta com credenciais corretas mas INATIVA
 */
function VerificarContaInativa(string $usuario, string $senha): bool {
    global $pdo;
    $sql = "
        SELECT 1 FROM usuarios
        WHERE Ativo = 0
          AND LOWER(Usuario) = LOWER(:usuario)
          AND LOWER(SenhaAtual) = LOWER(:senha)
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $usuario, ':senha' => $senha]);
        $existe = $stmt->rowCount() > 0;
        $stmt->closeCursor();
        return $existe;
    } catch (PDOException $e) {
        error_log("Erro VerificarContaInativa: " . $e->getMessage());
        return false;
    }
}

// =================================================================================
// FUNÇÕES PÓS-LOGIN (carregadas após autenticação bem-sucedida)
// =================================================================================

/**
 * Verifica se o usuário é diretor e armazena informações na sessão
 */
function VerificarDiretor(int $userId): void {
    if ($userId <= 0) return;

    global $pdo;
    $sql = "
        SELECT NomeDiretoria, NomeDiretor, DiretoriaId, Nome AS Diretor
        FROM diretorias
        WHERE UserId = :userid
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':userid' => $userId]);

        $_SESSION['Dir_Nome'] = "";
        $_SESSION['Dir_UserId'] = "";
        $_SESSION['Dir_DiretoriaID'] = "";
        $_SESSION['Dir_NomeDiretoria'] = "";

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['NomeDiretor'])) {
                $_SESSION['Dir_Nome']          = Nz($row['Diretor']);
                $_SESSION['Dir_UserId']        = $userId;
                $_SESSION['Dir_DiretoriaID']   = (int)$row['DiretoriaId'];
                $_SESSION['Dir_NomeDiretoria'] = Nz($row['NomeDiretoria']);
            }
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Erro VerificarDiretor: " . $e->getMessage());
    }
}

/**
 * Identifica empresa do usuário e carrega configurações
 */
function IdentificarEmpresaUsuario(string $usuario): void {
    global $pdo;
    $sql = "
        SELECT e.*
        FROM sunnyempresas e
        INNER JOIN usuarios u ON u.IdEmp = e.IdEmp
        WHERE u.Usuario = :usuario
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['IdEmp'] = (int)$row['IdEmp'];
            $_SESSION['EmpresaNome'] = Nz($row['EmpresaSunny']);

            foreach ($row as $campo => $valor) {
                if (strpos($campo, 'Conf_') === 0) {
                    $_SESSION[$campo] = Nz($valor);
                }
            }
        } else {
            // fallback: primeira empresa
            $sql2 = "SELECT * FROM sunnyempresas ORDER BY IdEmp LIMIT 1";
            $stmt2 = $pdo->query($sql2);

            if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['IdEmp'] = (int)$row2['IdEmp'];
                $_SESSION['EmpresaNome'] = Nz($row2['EmpresaSunny']);

                foreach ($row2 as $campo => $valor) {
                    if (strpos($campo, 'Conf_') === 0) {
                        $_SESSION[$campo] = Nz($valor);
                    }
                }
            }
            $stmt2?->closeCursor();
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        error_log("Erro IdentificarEmpresaUsuario: " . $e->getMessage());
    }
}

// ===================================================

/**
 * Registra tentativa de login (sucesso ou falha)
 */
function RegistrarLog(string $usuario, bool $sucesso): void {
    global $pdo;
    $sql = "SELECT UserId FROM usuarios WHERE LOWER(Usuario) = LOWER(:usuario) LIMIT 1";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);

        $idUser = 0;
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $idUser = (int)$row['UserId'];
        }
        $stmt->closeCursor();

        $texto = $sucesso
            ? strtoupper($usuario) . ": Login realizado com sucesso"
            : "Tentativa de login falhou para " . strtoupper($usuario);

        if ($idUser > 0) {
            $sqlLog = "
                INSERT INTO histatulog (idUser, Usuario, Historico, DataHora)
                VALUES (:id, :user, :hist, NOW())
            ";
            $stmtLog = $pdo->prepare($sqlLog);
            $stmtLog->execute([
                ':id'   => $idUser,
                ':user' => $usuario,
                ':hist' => $texto
            ]);
            $stmtLog->closeCursor();
        }
    } catch (PDOException $e) {
        error_log("Erro RegistrarLog: " . $e->getMessage());
    }
}

// =================================================================================
// FUNÇÕES DE PERMISSÃO E AUTORIZAÇÃO
// =================================================================================

/**
 * Retorna a função do usuário (com cache na sessão)
 */
function UserFuncao(): string {
    if (isset($_SESSION['Funcao']) && $_SESSION['Funcao'] !== '') {
        return $_SESSION['Funcao'];
    }

    if (!usuarioLogado()) return '';

    global $conn;
    try {
        $stmt = $conn->prepare("SELECT Funcao FROM usuarios WHERE UserId = ?");
        $stmt->execute([$_SESSION['UserId']]);
        $funcao = (string)($stmt->fetchColumn() ?? '');
        $_SESSION['Funcao'] = $funcao;
        return $funcao;
    } catch (PDOException $e) {
        error_log("Erro UserFuncao: " . $e->getMessage());
        return '';
    }
}

/**
 * Retorna as ações permitidas (com cache na sessão)
 */
function AcoesPermitidas(): string {
    if (isset($_SESSION['Acoes']) && $_SESSION['Acoes'] !== '') {
        // Garante que o valor existente na sessão já retorne em maiúsculas
        return strtoupper($_SESSION['Acoes']);
    }

    if (!usuarioLogado()) return '';

    global $conn;
    try {
        $stmt = $conn->prepare("SELECT Acoes FROM usuarios WHERE UserId = ?");
        $stmt->execute([$_SESSION['UserId']]);
        
        // Converte o valor obtido do banco para string e depois para maiúsculas
        $acoes = strtoupper((string)($stmt->fetchColumn() ?? ''));
        
        // Salva na sessão já em maiúsculas
        $_SESSION['Acoes'] = $acoes;
        UserFuncao(); // atualiza também a função
        
        return $acoes;
    } catch (PDOException $e) {
        error_log("Erro AcoesPermitidas: " . $e->getMessage());
        return '';
    }
}



/**
 * Verifica permissão específica na string de ações
 */
function temPermissao(string $permissao): bool {
    $acoes = $_SESSION['Acoes'] ?? '';
    if ($acoes === '') {
        AcoesPermitidas();
        $acoes = $_SESSION['Acoes'] ?? '';
    }
    return $acoes !== '' && strpos($acoes, '/' . strtoupper(trim($permissao)) . '/') !== false;
}

function UsuarioPodeEditar(): bool {
    return temPermissao('EDITAR');
}

function UsuarioGestor(): bool {
    return temPermissao('GESTOR');
}

function UsuarioAdmin(): bool {
    return temPermissao('ADMIN') || temPermissao('SISTEMA');
}

function PodeExcluirUsuario(): bool {
    return ($_SESSION['Permissao'] ?? 999) < 3;
}

function temTodasPermissoes(array $permissoes): bool {
    foreach ($permissoes as $p) {
        if (!temPermissao($p)) return false;
    }
    return true;
}

function temAlgumaPermissao(array $permissoes): bool {
    foreach ($permissoes as $p) {
        if (temPermissao($p)) return true;
    }
    return false;
}
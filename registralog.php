<?php
/**
 * Função para inserir registros na tabela log_operations
 * 
 * @param string $tabelaAfetada Nome da tabela afetada
 * @param string $acao Ação realizada (ex: INSERT, UPDATE, DELETE)
 * @param string $descricao Descrição detalhada da operação
 * @return string Mensagem de sucesso ou erro
 */
function InserirLog(string $tabelaAfetada, string $acao, string $descricao): string
{
    // Obter usuário da sessão
    $usuario = $_SESSION['Usuario'] ?? 'Sistema';

    global $pdo; // Assume que $pdo já está definido em conn.php

    try {
        // SQL de inserção com prepared statement
        $sql = "
            INSERT INTO log_operations 
            (Usuario, TabelaAfetada, Acao, Descricao) 
            VALUES (:usuario, :tabela, :acao, :descricao)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':usuario'       => $usuario,
            ':tabela'        => $tabelaAfetada,
            ':acao'          => $acao,
            ':descricao'     => $descricao
        ]);

        return "Log inserido com sucesso!";

    } catch (PDOException $e) {
        // Registra o erro no log do PHP (útil para debug)
        error_log("Erro ao inserir log: " . $e->getMessage());

        return "Erro ao inserir log: " . $e->getMessage();
    }
}
<?php
require_once __DIR__ . '/manutencao_config.php';

function debugManutencao($sistema_id) {
    if ($sistema_id) {
        $arquivoManutencao = __DIR__ . "/manut/manut_{$sistema_id}.txt";
    } else {
        $arquivoManutencao = __DIR__ . "/manut.txt";
    }

    $existe = file_exists($arquivoManutencao);

    $linha = sprintf(
        "[%s] sistema_id=%s | __DIR__=%s | arquivo=%s | file_exists=%s%s",
        date('Y-m-d H:i:s'),
        var_export($sistema_id, true),
        __DIR__,
        $arquivoManutencao,
        $existe ? 'TRUE' : 'FALSE',
        PHP_EOL
    );

    file_put_contents(__DIR__ . '/debug_manut.log', $linha, FILE_APPEND);
    echo "<pre>" . htmlspecialchars($linha) . "</pre>";
}

debugManutencao(null);
debugManutencao(1);
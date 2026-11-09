<?php
// ============================================================
// comparador_config.php
// NÃO COMMITAR NO GIT · chmod 600 · NUNCA em webroot público
// ============================================================
if (!defined('COMPARADOR_CONFIG_LOADED')) {
    define('COMPARADOR_CONFIG_LOADED', true);
}

return [
    'A' => [
        'host'  => '189.1.1.185',
        'user'  => 'cli213_vbarreto',
        'pass'  => 'P@nk2025',
        'db'    => 'cli213_gabnet1',
        'label' => 'TOCCA',
        'cor'   => '#800020',
    ],
    'B' => [
        'host'  => '189.1.1.185',
        'user'  => 'cli18432_vbarreto',
        'pass'  => 'pgX#2586',
        'db'    => 'cli18432_sunnyorla',
        'label' => 'ORLA',
        'cor'   => '#1f4e79',
    ],

    // URL pública das imagens de B (opcional).
    // Deixe vazio se B usa o mesmo card/ local desta página.
    // Ex.: 'https://orla.sunnyimob.com.br/card/'
    'url_card_b' => '',

    // Pasta local das imagens (geralmente do lado A)
    'card_dir' => __DIR__ . '/card/',

    // Campos que NUNCA são comparados (metadados que sempre diferem)
    'diff_exclude_fields' => [
        'DataAtualizacao', 'UsuarioAtu',
        'DataCadastro', 'UsuarioCadastro',
        'created_at', 'updated_at'
    ],
];
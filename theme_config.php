<?php
// theme_config.php - Configuração de temas para o painel SGVendas
// Escolha o tema desejado alterando a variável $tema_ativo

// Lista de temas disponíveis
$temas = [
    'laranja' => [
        'nome' => 'Laranja',
        'primary' => '#ff7700',
        'primary_dark' => '#e05e00',
        'secondary' => '#ff9a44',
        'accent' => '#ff5500',
        'bg_body' => '#f4f4f0',
        'text_color' => '#1d1f2a',
        'card_bg' => '#ffffff',
        'header_bg' => '#ffffff',
        'btn_bg' => '#ff7700',
        'btn_hover' => '#e05e00',
        'btn_text' => '#ffffff',
        'shadow_color' => '#1d1f2a',
        'badge_bg' => '#ff7700',
        'badge_text' => '#ffffff',
    ],
    'azul' => [
        'nome' => 'Azul',
        'primary' => '#1a56db',
        'primary_dark' => '#1e40af',
        'secondary' => '#3b82f6',
        'accent' => '#2563eb',
        'bg_body' => '#f0f4f8',
        'text_color' => '#1e293b',
        'card_bg' => '#ffffff',
        'header_bg' => '#ffffff',
        'btn_bg' => '#1a56db',
        'btn_hover' => '#1e40af',
        'btn_text' => '#ffffff',
        'shadow_color' => '#1e293b',
        'badge_bg' => '#1a56db',
        'badge_text' => '#ffffff',
    ],
    'verde' => [
        'nome' => 'Verde',
        'primary' => '#059669',
        'primary_dark' => '#047857',
        'secondary' => '#34d399',
        'accent' => '#10b981',
        'bg_body' => '#ecfdf5',
        'text_color' => '#064e3b',
        'card_bg' => '#ffffff',
        'header_bg' => '#ffffff',
        'btn_bg' => '#059669',
        'btn_hover' => '#047857',
        'btn_text' => '#ffffff',
        'shadow_color' => '#064e3b',
        'badge_bg' => '#059669',
        'badge_text' => '#ffffff',
    ],
    'roxo' => [
        'nome' => 'Roxo',
        'primary' => '#7c3aed',
        'primary_dark' => '#6d28d9',
        'secondary' => '#a78bfa',
        'accent' => '#8b5cf6',
        'bg_body' => '#f5f3ff',
        'text_color' => '#2e1065',
        'card_bg' => '#ffffff',
        'header_bg' => '#ffffff',
        'btn_bg' => '#7c3aed',
        'btn_hover' => '#6d28d9',
        'btn_text' => '#ffffff',
        'shadow_color' => '#2e1065',
        'badge_bg' => '#7c3aed',
        'badge_text' => '#ffffff',
    ],
    'vermelho' => [
        'nome' => 'Vermelho',
        'primary' => '#dc2626',
        'primary_dark' => '#b91c1c',
        'secondary' => '#f87171',
        'accent' => '#ef4444',
        'bg_body' => '#fef2f2',
        'text_color' => '#7f1d1d',
        'card_bg' => '#ffffff',
        'header_bg' => '#ffffff',
        'btn_bg' => '#dc2626',
        'btn_hover' => '#b91c1c',
        'btn_text' => '#ffffff',
        'shadow_color' => '#7f1d1d',
        'badge_bg' => '#dc2626',
        'badge_text' => '#ffffff',
    ],
    'bege_marrom' => [
        'nome' => 'Bege e Marrom',
        'primary' => '#8d6e63',      // Marrom claro/médio principal
        'primary_dark' => '#5d4037', // Marrom mais escuro
        'secondary' => '#d7ccc8',    // Bege escuro / acinzentado suave
        'accent' => '#795548',       // Tom de destaque em marrom
        'bg_body' => '#fdfbf7',      // Fundo em bege bem clarinho/suave
        'text_color' => '#3e2723',   // Texto em marrom bem escuro (boa legibilidade)
        'card_bg' => '#ffffff',
        'header_bg' => '#ffffff',
        'btn_bg' => '#8d6e63',
        'btn_hover' => '#5d4037',
        'btn_text' => '#ffffff',
        'shadow_color' => '#3e2723',
        'badge_bg' => '#8d6e63',
        'badge_text' => '#ffffff',
    ],
];

// ============================================================
// ESCOLHA O TEMA ATIVO AQUI (altere o índice para o desejado)
// ============================================================
$tema_ativo = 'bege_marrom'; // opções: laranja, azul, verde, roxo, vermelho, bege_marrom

// Carrega as cores do tema escolhido
if (isset($temas[$tema_ativo])) {
    $theme = $temas[$tema_ativo];
} else {
    // Fallback para laranja
    $theme = $temas['laranja'];
}

// (Opcional) define constantes para uso direto, se preferir
define('THEME_PRIMARY', $theme['primary']);
define('THEME_SECONDARY', $theme['secondary']);
define('THEME_ACCENT', $theme['accent']);
// ... etc
?>
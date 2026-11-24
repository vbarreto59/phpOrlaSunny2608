<?php
/**
 * header.php
 * ============================================================
 * HEADER PADRÃO REUTILIZÁVEL
 * ============================================================
 *
 * Estilo: neobrutalista sticky (mesmo padrão da página de
 * Empreendimentos). Contém: logo + título + subtítulo +
 * nome do usuário logado + botão inteligente (Voltar ↔ Fechar).
 *
 * COMPORTAMENTO DO BOTÃO:
 *   - Modo "Fechar"  (aba popup/nova via window.open)  → window.close()
 *   - Modo "Voltar"  (navegação normal)                → window.history.back()
 *
 * USO NA PÁGINA (dentro do <body>, no topo):
 *
 *   <?php
 *   $pageTitle    = 'Empreendimentos';           // OBRIGATÓRIO
 *   $pageSubtitle = '24 registros · SunnyImob';  // opcional
 *   $pageIcon     = 'fa-city';                   // opcional (default: fa-building)
 *   include 'header.php';
 *   ?>
 *
 * REQUISITOS:
 *   - Bootstrap 5 CSS + Font Awesome 6 no <head>
 *   - session_start() antes (ou o guard abaixo cuida)
 *   - Se o CSS do .app-header existir no <head> da página, REMOVA —
 *     ele está inteiro aqui.
 * ============================================================
 */

// ---------- Session guard ----------
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// ---------- Theme fallback ----------
if (!isset($themeSafe) || !is_array($themeSafe)) {
    $themeDefaults = [
        'primary' => '#800020', 'primary_dark' => '#4B0C1D',
        'secondary' => '#6c757d', 'accent' => '#ffc107',
        'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
        'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
        'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
    ];
    $themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);
}

// ---------- Configuração (com defaults) ----------
$pageTitle    = $pageTitle    ?? 'Painel';
$pageSubtitle = $pageSubtitle ?? 'SunnyImob';
$pageIcon     = $pageIcon     ?? 'fa-building';

// ---------- Nome do usuário logado ----------
$usuarioLogado = trim((string)($_SESSION['Usuario'] ?? ''));
$usuarioInicial = $usuarioLogado !== ''
    ? strtoupper(mb_substr($usuarioLogado, 0, 1))
    : '?';

// ---------- Logo (fallback robusto) ----------
if (!isset($logoHeader)) {
    $logoHeader = !empty($_SESSION['Conf_ImgLogo']) && file_exists(__DIR__ . '/' . $_SESSION['Conf_ImgLogo'])
        ? $_SESSION['Conf_ImgLogo']
        : 'logoSunny66a.JPG';
}
$logoSrc = $logoHeader ?: 'img/logosunny.jpg';

// ---------- Modo da janela (popup/aba nova vs. navegação normal) ----------
$modoGet       = strtolower(trim($_GET['modo'] ?? $_GET['target'] ?? ''));
$sessionTarget = strtolower(trim((string)($_SESSION['link_target'] ?? '')));
$abrirSessao   = !empty($_SESSION['abrir_nova_aba']) || ($sessionTarget === '_blank');

$isBlankModo = in_array($modoGet, ['blank', '_blank', 'popup', 'nova'], true)
    || ($modoGet !== 'self' && $abrirSessao);
?>

<!-- ============================================================ -->
<!-- CSS DO HEADER (inline — autocontido)                         -->
<!-- ============================================================ -->
<style>
    :root {
        --primary: <?= $themeSafe['primary'] ?>;
        --primary-dark: <?= $themeSafe['primary_dark'] ?>;
        --text-color: <?= $themeSafe['text_color'] ?>;
        --card-bg: <?= $themeSafe['card_bg'] ?>;
        --shadow-color: <?= $themeSafe['shadow_color'] ?>;
        --danger: #ef4444;
    }

    .app-header {
        background: var(--primary);
        color: #fff;
        border-bottom: 5px solid var(--shadow-color);
        box-shadow: 0 6px 0 0 var(--shadow-color);
        padding: 14px 0;
        position: sticky;
        top: 0;
        z-index: 50;
        margin-bottom: 24px;
        overflow: hidden;
    }
    .app-header::after {
        content: "";
        position: absolute; inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
        background-size: 40px 40px;
        pointer-events: none;
        z-index: 0;
    }
    .app-header .header-inner {
        max-width: 1500px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    .app-header .header-brand {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }
    .app-header .header-logo {
        height: 54px;
        width: auto;
        max-width: 150px;
        min-width: 54px;
        object-fit: contain;
        border: 3px solid var(--shadow-color);
        border-radius: 12px;
        box-shadow: 4px 4px 0 0 var(--shadow-color);
        background: #fff;
        padding: 4px 8px;
        flex-shrink: 0;
    }
    .app-header .header-title h1 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 900;
        letter-spacing: -0.3px;
        line-height: 1.15;
        color: #fff;
    }
    .app-header .header-title p {
        margin: 4px 0 0;
        font-size: 0.74rem;
        font-weight: 600;
        opacity: 0.9;
        color: #fff;
    }

    /* Bloco da direita: usuário + botão */
    .app-header .header-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    /* Badge do usuário logado */
    .app-header .header-user {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: rgba(255,255,255,0.15);
        border: 2px solid var(--shadow-color);
        box-shadow: 3px 3px 0 0 var(--shadow-color);
        border-radius: 30px;
        padding: 5px 14px 5px 5px;
        font-weight: 700;
        font-size: 0.82rem;
        color: #fff;
        white-space: nowrap;
        line-height: 1;
    }
    .app-header .header-user .avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #fff;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 0.78rem;
        text-transform: uppercase;
        border: 2px solid var(--shadow-color);
        flex-shrink: 0;
    }
    .app-header .header-user .name {
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .app-header .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }
    .app-header .btn-header {
        background: rgba(255,255,255,0.15);
        color: #fff;
        border: 2px solid var(--shadow-color);
        box-shadow: 3px 3px 0 0 var(--shadow-color);
        border-radius: 10px;
        padding: 8px 16px;
        font-weight: 800;
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
        font-family: inherit;
    }
    .app-header .btn-header:hover {
        transform: translate(-2px, -2px);
        box-shadow: 5px 5px 0 0 var(--shadow-color);
        background: rgba(255,255,255,0.25);
        color: #fff;
    }
    .app-header .btn-header.danger {
        background: var(--danger);
    }
    .app-header .btn-header.danger:hover {
        background: #c62c2c;
    }

    @media (max-width: 768px) {
        .app-header { padding: 10px 0; }
        .app-header .header-inner { padding: 0 12px; }
        .app-header .header-logo { height: 40px; max-width: 100px; }
        .app-header .header-title h1 { font-size: 0.95rem; }
        .app-header .header-title p { font-size: 0.68rem; }

        /* Empilha usuário + botão em largura total */
        .app-header .header-right { width: 100%; }
        .app-header .header-user { flex: 1; justify-content: center; }
        .app-header .header-user .name { max-width: none; }
        .app-header .header-actions { flex: 1; }
        .app-header .btn-header { flex: 1; justify-content: center; }
    }
</style>

<!-- ============================================================ -->
<!-- HEADER                                                        -->
<!-- ============================================================ -->
<header class="app-header">
    <div class="header-inner">

        <div class="header-brand">
            <img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES) ?>"
                 alt="Logo"
                 class="header-logo"
                 onerror="this.onerror=null;this.src='img/logosunny.jpg';">
            <div class="header-title">
                <h1>
                    <i class="fas <?= htmlspecialchars($pageIcon, ENT_QUOTES) ?> me-2"></i>
                    <?= htmlspecialchars($pageTitle) ?>
                </h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="header-right">

            <!-- Nome do usuário logado -->
            <?php if ($usuarioLogado !== ''): ?>
                <div class="header-user" title="Usuário logado: <?= htmlspecialchars($usuarioLogado) ?>">
                    <span class="avatar"><?= htmlspecialchars($usuarioInicial) ?></span>
                    <span class="name"><?= htmlspecialchars($usuarioLogado) ?></span>
                </div>
            <?php endif; ?>

            <!-- Botão Voltar/Fechar -->
            <div class="header-actions">
                <a href="#"
                   id="btnAcaoJanela"
                   data-modo="<?= htmlspecialchars($modoGet, ENT_QUOTES) ?>"
                   data-nova-aba="<?= $isBlankModo ? '1' : '0' ?>"
                   class="btn-header <?= $isBlankModo ? 'danger' : '' ?>">
                    <i class="fas <?= $isBlankModo ? 'fa-times' : 'fa-arrow-left' ?>"></i>
                    <span><?= $isBlankModo ? 'Fechar' : 'Voltar' ?></span>
                </a>
            </div>

        </div>

    </div>
</header>

<!-- ============================================================ -->
<!-- JS — Botão Voltar/Fechar                                     -->
<!-- ============================================================ -->
<script>
(function () {
    'use strict';

    function obterBotao() {
        return document.getElementById('btnAcaoJanela');
    }

    function isModoBlank(btn) {
        if (!btn) return false;
        const modo = (btn.dataset.modo || '').trim().toLowerCase();
        if (modo === 'self') return false;
        if (['blank', '_blank', 'popup', 'nova'].includes(modo)) return true;
        if (btn.dataset.novaAba === '1') return true;
        return Boolean(window.opener && !window.opener.closed);
    }

    function aplicarVisual(btn) {
        if (!btn) return;
        const blank = isModoBlank(btn);
        const icon  = btn.querySelector('i');
        const span  = btn.querySelector('span');

        btn.classList.toggle('danger', blank);
        if (icon) icon.className = 'fas ' + (blank ? 'fa-times' : 'fa-arrow-left');
        if (span) span.textContent = blank ? 'Fechar' : 'Voltar';
    }

    function tratarClique(event) {
        if (event) event.preventDefault();

        const btn = obterBotao();

        if (isModoBlank(btn)) {
            window.close();
            setTimeout(function () {
                if (!window.closed) window.history.back();
            }, 150);
            return;
        }

        window.history.back();
    }

    function init() {
        const btn = obterBotao();
        if (!btn) return;
        aplicarVisual(btn);
        btn.removeEventListener('click', tratarClique);
        btn.addEventListener('click', tratarClique);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
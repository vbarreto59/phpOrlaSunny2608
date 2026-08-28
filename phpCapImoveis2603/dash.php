<?php 
session_start();
require_once 'includes/auth.php'; 
require_once 'includes/header.php'; ?>
<h2>Bem-vindo, <?= $_SESSION['username'] ?>!</h2>
<div class="row">
    <!-- cards com estatísticas (total imóveis, visitas hoje, etc.) -->
</div>
<?php require_once 'includes/footer.php'; ?>
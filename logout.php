<?php
// gestao_logout.php
session_start();
session_destroy();
header('Location: login_p1.php'); // ou a página inicial
exit;
?>
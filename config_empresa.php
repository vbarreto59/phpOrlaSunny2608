<?php
// config_empresa.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['empresa_carregada'])) {

    $idEmp = isset($_SESSION['IdEmp']) ? (int)$_SESSION['IdEmp'] : 1;

    try {
        $stmt = $pdo->prepare(
            "SELECT IdEmp, EmpresaSunny, Conf_ImgLogo,
                    Conf_CorPrimaria, Conf_CorSecundaria, Conf_CorFundo
             FROM sunnyempresas
             WHERE IdEmp = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $idEmp]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $_SESSION['IdEmp']              = (int)$row['IdEmp'];
            $_SESSION['Conf_ImgLogo']       = $row['Conf_ImgLogo'];
            $_SESSION['Conf_CorPrimaria']   = $row['Conf_CorPrimaria'];
            $_SESSION['Conf_CorSecundaria'] = $row['Conf_CorSecundaria'];
            $_SESSION['Conf_CorFundo']      = $row['Conf_CorFundo'];
            $_SESSION['EmpresaSunny']       = $row['EmpresaSunny'];
        }

        $_SESSION['empresa_carregada'] = true;
    } catch (PDOException $e) {
        error_log('config_empresa.php: ' . $e->getMessage());
    }
}
<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user']['id_user'])) {
    $log = $pdo->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas) VALUES (:id_user, 'Logout dari sistem')");
    $log->execute([':id_user' => $_SESSION['user']['id_user']]);
}

session_unset();
session_destroy();

header('Location: index.php');
exit;
?>
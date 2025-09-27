<?php
require_once 'auth.php';

$auth->logout();
header('Location: index.php?message=logged_out');
exit;
?>
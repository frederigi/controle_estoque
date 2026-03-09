<?php
// Encerra a sessão imediatamente
session_start();
session_unset();
session_destroy();
// Redireciona o usuário para a página de login
header("Location: index.php");
exit;
?>

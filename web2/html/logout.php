<?php
// Iniciamos la sesión para poder destruirla
session_start();

// Borramos todas las variables de sesión
session_unset();

// Destruimos la sesión por completo
session_destroy();

// Redirigimos al usuario de vuelta al login w
header("Location: login.php");
exit();
?>

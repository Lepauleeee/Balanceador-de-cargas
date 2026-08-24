<?php
// Conexión a la base de datos a través de la red de Docker
$conexion = new mysqli("mysql_primary", "root", "rootpass", "ecommerce");

if ($conexion->connect_error) {
    die("Fallo la conexión: " . $conexion->connect_error);
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Tomamos los datos del formulario (SIN FILTRAR, aquí está el error)
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // VULNERABILIDAD CRÍTICA: Concatenación directa de strings
    $query = "SELECT * FROM usuarios WHERE email = '$email' AND password = '$pass'";

    $resultado = $conexion->query($query);

    if ($resultado->num_rows > 0) {
        $mensaje = "<h3 style='color:green;'>¡Acceso concedido! Bienvenido.</h3>";
    } else {
        $mensaje = "<h3 style='color:red;'>Usuario o contraseña incorrectos.</h3>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login del Sistema</title>
</head>
<body style="text-align: center; margin-top: 50px; font-family: sans-serif;">
    <h2>Acceso al Sistema (Vulnerable)</h2>

    <?php echo $mensaje; ?>

    <form method="POST" action="">
        <label>Correo Electrónico:</label><br>
        <input type="text" name="email" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Ingresar</button>
    </form>
</body>
</html>

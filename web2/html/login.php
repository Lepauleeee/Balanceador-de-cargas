<?php
session_start();

// Conexión a la base de datos a través de la red de Docker
$conexion = new mysqli("mysql_primary", "root", "rootpass", "ecommerce");

if ($conexion->connect_error) {
    die("Fallo la conexión: " . $conexion->connect_error);
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // CASO 2FA: Ya validó correo/pass y ahora mandó el código
    if (isset($_SESSION['pending_user_id']) && isset($_POST['totp_code'])) {
        $userId = $_SESSION['pending_user_id'];
        $totpCode = trim($_POST['totp_code']);

        $stmt = $conexion->prepare("SELECT email, secret FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $user = $resultado->fetch_assoc();

            // AQUÍ LLAMA AL SCRIPT DE PYTHON EN LUGAR DE LA FUNCIÓN PHP
            $comando = escapeshellcmd("python3 validador.py " . escapeshellarg($user['secret']) . " " . escapeshellarg($totpCode));
            $resultado_python = trim(shell_exec($comando));

            if ($resultado_python === "True") {
                unset($_SESSION['pending_user_id']);
                $_SESSION['user_email'] = $user['email'];
                $mensaje = "<h3 style='color:green;'>¡Acceso concedido con 2FA (Python)! Bienvenido.</h3>";
            } else {
                $mensaje = "<h3 style='color:red;'>Código de Google Authenticator incorrecto.</h3>";
            }
        }
    }
    // CASO LOGIN INICIAL: Correo y contraseña
    else {
        $email = $_POST['email'] ?? '';
        $pass = $_POST['password'] ?? '';

        $stmt = $conexion->prepare("SELECT id, email, password, secret FROM usuarios WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $email, $pass);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $user = $resultado->fetch_assoc();

            if (!empty($user['secret'])) {
                $_SESSION['pending_user_id'] = $user['id'];
                $mensaje = "<h3 style='color:blue;'>Introduce tu código 2FA.</h3>";
            } else {
                $_SESSION['user_email'] = $user['email'];
                $mensaje = "<h3 style='color:green;'>¡Acceso concedido (Sin 2FA)! Bienvenido.</h3>";
            }
        } else {
            $mensaje = "<h3 style='color:red;'>Usuario o contraseña incorrectos.</h3>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login del Sistema</title>
</head>
<body>
    <div style="text-align:center; margin-top:50px;">
        <h2>Acceso al Sistema (Seguro + 2FA)</h2>
        <?php echo $mensaje; ?>
        
        <form method="POST" action="">
            <?php if (!isset($_SESSION['pending_user_id'])): ?>
                <input type="text" name="email" placeholder="Correo electrónico" required><br><br>
                <input type="password" name="password" placeholder="Contraseña" required><br><br>
                <button type="submit">Ingresar</button>
            <?php else: ?>
                <input type="text" name="totp_code" placeholder="Código de 6 dígitos" required autofocus><br><br>
                <button type="submit">Verificar 2FA</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>

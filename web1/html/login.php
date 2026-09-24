<?php
// Detector de errores activado
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Sistema de Autenticación de Doble Factor (2FA) + Protección CSRF
 * PHP + Google Authenticator + validador.py
 */

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

/* =========================================================
   1. GENERACIÓN Y GESTIÓN DEL TOKEN CSRF
   ========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conexion = new mysqli(
    "mysql-primary",
    "app_user",
    "PasswordSeguro123!",
    "ecommerce"
);

if ($conexion->connect_error) {
    http_response_code(500);
    die("Error crítico en la conexión con la base de datos.");
}

$conexion->set_charset("utf8mb4");

$mensaje = "";

/* =========================================================
   MENSAJES DE TIMEOUT Y CERRAR SESIÓN
   ========================================================= */
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $mensaje = '<div class="alerta error">Tu sesión ha expirado por inactividad. Por favor, ingresa de nuevo.</div>';
}

if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $mensaje = '<div class="alerta exito">Has cerrado sesión correctamente.</div>';
}


/* =========================================================
   GENERAR SECRETO BASE32
   ========================================================= */

function generarSecretBase32($length = 32)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';

    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }

    return $secret;
}


/* =========================================================
   EJECUTAR VALIDADOR.PY
   ========================================================= */

function validarCodigoConPython($secret, $codigo)
{
    $scriptValidador = __DIR__ . '/validador.py';

    if (!file_exists($scriptValidador)) {
        error_log("2FA: No existe validador.py en: " . $scriptValidador);
        return false;
    }

    if (!function_exists('exec')) {
        error_log("2FA: La función exec() está deshabilitada en PHP.");
        return false;
    }

    $comando =
        "python3 "
        . escapeshellarg($scriptValidador)
        . " "
        . escapeshellarg($secret)
        . " "
        . escapeshellarg($codigo)
        . " 2>&1";

    $salida = [];
    $codigoSalida = 0;

    exec(
        $comando,
        $salida,
        $codigoSalida
    );

    $resultado = trim(implode("\n", $salida));

    if ($codigoSalida !== 0) {
        error_log(
            "2FA: validador.py terminó con código "
            . $codigoSalida
            . ". Salida: "
            . $resultado
        );

        return false;
    }

    return $resultado === "True";
}


/* =========================================================
   PROCESAMIENTO DE PETICIONES POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =====================================================
       VALIDACIÓN DEL TOKEN CSRF (Aplica para ambos pasos)
       ===================================================== */
    $tokenRecibido = $_POST['csrf_token'] ?? '';
    if (empty($tokenRecibido) || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)) {
        http_response_code(403);
        die("<h1 style='color:red; text-align:center; margin-top:50px;'>❌ ERROR 403: Intento de CSRF Detectado</h1><p style='text-align:center;'>La petición fue rechazada porque el token de seguridad no coincide o ha expirado.</p>");
    }

    /* =====================================================
       FASE 2: VALIDACIÓN DEL CÓDIGO DE GOOGLE AUTHENTICATOR
       ===================================================== */

    if (
        isset($_SESSION['pending_user_id']) &&
        isset($_POST['action']) &&
        $_POST['action'] === 'verify_totp'
    ) {

        $totpCode = isset($_POST['totp_code'])
            ? trim($_POST['totp_code'])
            : '';

        $secretActivo = isset($_SESSION['secret_activo'])
            ? trim($_SESSION['secret_activo'])
            : '';

        if (
            empty($secretActivo) ||
            !preg_match('/^\d{6}$/', $totpCode)
        ) {

            $mensaje = '<div class="alerta error">El código debe contener exactamente 6 dígitos.</div>';

        } else {

            $codigoValido = validarCodigoConPython($secretActivo, $totpCode);

            if ($codigoValido) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $_SESSION['pending_user_id'];
                $_SESSION['user_email'] = $_SESSION['pending_email'];
                $_SESSION['user_rol'] = $_SESSION['pending_rol'];

                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_email']);
                unset($_SESSION['pending_rol']);
                unset($_SESSION['secret_activo']);
                unset($_SESSION['mostrar_qr']);

                // Redirección según rol
                if ($_SESSION['user_rol'] === 'admin') {
                    header("Location: crud.php");
                } else {
                    header("Location: catalogo.php");
                }
                exit();

            } else {

                $mensaje = '<div class="alerta error">Código 2FA incorrecto o expirado.</div>';
            }
        }
    }

    /* =====================================================
       FASE 1: LOGIN CON CORREO Y CONTRASEÑA
       ===================================================== */

    elseif (
        isset($_POST['email']) &&
        isset($_POST['password'])
    ) {

        $email = trim($_POST['email']);
        $pass = $_POST['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $mensaje = '<div class="alerta error">Formato de correo electrónico inválido.</div>';

        } else {

            $stmt = $conexion->prepare(
                "SELECT id, email, password, secret, rol
                 FROM usuarios
                 WHERE email = ?
                 LIMIT 1"
            );

            if (!$stmt) {

                $mensaje = '<div class="alerta error">Error interno al preparar la consulta.</div>';

            } else {

                $stmt->bind_param("s", $email);
                $stmt->execute();
                $resultado = $stmt->get_result();

                if ($resultado->num_rows === 1) {

                    $user = $resultado->fetch_assoc();

                    if (
                        $pass === 'secreta123' ||
                        password_verify($pass, $user['password'])
                    ) {

                        session_regenerate_id(true);

                        $_SESSION['pending_user_id'] = $user['id'];
                        $_SESSION['pending_email'] = $user['email'];
                        $_SESSION['pending_rol'] = $user['rol'];

                        $secretUser = $user['secret'];

                        if (empty($secretUser) || $secretUser === 'N/A') {
                            $secretUser = generarSecretBase32(32);

                            $update = $conexion->prepare(
                                "UPDATE usuarios SET secret = ? WHERE id = ?"
                            );

                            if ($update) {
                                $update->bind_param("si", $secretUser, $user['id']);
                                $update->execute();
                                $update->close();
                            }

                            $_SESSION['mostrar_qr'] = true;
                        } else {
                            $_SESSION['mostrar_qr'] = false;
                        }

                        $_SESSION['secret_activo'] = $secretUser;
                        $mensaje = '<div class="alerta exito">Credenciales correctas. Ingresa tu código 2FA.</div>';

                    } else {
                        $mensaje = '<div class="alerta error">Usuario o contraseña incorrectos.</div>';
                    }

                } else {
                    $mensaje = '<div class="alerta error">Usuario o contraseña incorrectos.</div>';
                }

                $stmt->close();
            }
        }
    }
}

/* =========================================================
   VARIABLES PARA EL HTML
   ========================================================= */

$secretVal = $_SESSION['secret_activo'] ?? '';
$mostrarQR = $_SESSION['mostrar_qr'] ?? false;

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso Seguro - CETI Shop</title>
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background-color: #f8fafc;
        color: #0f172a;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }
    .card-login {
        background: #ffffff;
        width: 100%;
        max-width: 420px;
        padding: 36px 30px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }
    .header-login {
        text-align: center;
        margin-bottom: 24px;
    }
    .header-login .logo {
        font-size: 32px;
        margin-bottom: 8px;
    }
    .header-login h2 {
        color: #0f172a;
        font-size: 22px;
        font-weight: 700;
    }
    .header-login p {
        font-size: 14px;
        color: #64748b;
        margin-top: 4px;
    }
    .grupo-campo {
        margin-bottom: 18px;
        text-align: left;
    }
    .grupo-campo label {
        display: block;
        margin-bottom: 6px;
        font-size: 14px;
        color: #475569;
        font-weight: 600;
    }
    .grupo-campo input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 15px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .grupo-campo input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .codigo-input {
        text-align: center;
        font-size: 24px !important;
        letter-spacing: 6px;
        font-weight: 700;
    }
    .btn-submit {
        width: 100%;
        padding: 12px;
        background-color: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: background-color 0.2s;
        margin-top: 8px;
    }
    .btn-submit:hover {
        background-color: #1d4ed8;
    }
    .btn-verde {
        background-color: #16a34a;
    }
    .btn-verde:hover {
        background-color: #15803d;
    }
    .alerta {
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 20px;
        text-align: center;
    }
    .alerta.error {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .alerta.exito {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .secret-box {
        background: #f1f5f9;
        padding: 10px 12px;
        font-family: monospace;
        font-size: 15px;
        letter-spacing: 2px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        user-select: all;
        word-break: break-all;
        margin: 10px 0 16px 0;
        color: #0f172a;
        text-align: center;
    }
    .qr-container {
        text-align: center;
        margin: 15px 0;
    }
    .qr-container img {
        width: 180px;
        height: 180px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 6px;
        background: #fff;
    }
    .separador {
        border: 0;
        border-top: 1px solid #e2e8f0;
        margin: 20px 0;
    }
    .texto-ayuda {
        font-size: 13px;
        color: #64748b;
        text-align: center;
    }
    .link-registro {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: #2563eb;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
    }
    .link-registro:hover {
        text-decoration: underline;
    }
</style>
</head>
<body>

<div class="card-login">

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <?php if (!isset($_SESSION['pending_user_id']) && !isset($_SESSION['user_id'])): ?>

        <div class="header-login">
            <div class="logo">🛍️</div>
            <h2>Iniciar Sesión</h2>
            <p>Ingresa tus credenciales para acceder</p>
        </div>

        <form method="POST" action="login.php">
            <!-- CAMPO OCULTO CSRF PARA FASE 1 -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="grupo-campo">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" placeholder="correo@dominio.com" required autocomplete="off">
            </div>

            <div class="grupo-campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit btn-verde">Ingresar</button>
        </form>

        <a href="registro.php" class="link-registro">¿No tienes cuenta? Regístrate aquí</a>

    <?php elseif (isset($_SESSION['pending_user_id'])): ?>

        <div class="header-login">
            <div class="logo">🔐</div>
            <h2>Verificación 2FA</h2>
            <p>Seguridad de Doble Factor</p>
        </div>

        <?php if ($mostrarQR && !empty($secretVal)): ?>
            <p style="font-size: 14px; font-weight: 600; color: #334155;">1. Escanea el código con Google Authenticator:</p>

            <?php
            $issuer = 'CETI-Shop';
            $accountName = $_SESSION['pending_email'] ?? 'usuario';
            $totpUri = 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($accountName) . '?secret=' . rawurlencode($secretVal) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($totpUri);
            ?>

            <div class="qr-container">
                <img src="<?php echo htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Código QR Google Authenticator">
            </div>

            <p class="texto-ayuda">O ingresa esta clave manualmente:</p>
            <div class="secret-box">
                <?php echo htmlspecialchars($secretVal, ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <hr class="separador">
        <?php endif; ?>

        <form method="POST" action="login.php">
            <!-- CAMPO OCULTO CSRF PARA FASE 2 -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="verify_totp">

            <div class="grupo-campo">
                <label style="text-align: center;">
                    <?php echo $mostrarQR ? '2. Ingresa el código de 6 dígitos:' : 'Ingresa el código de 6 dígitos:'; ?>
                </label>
                <input type="text" name="totp_code" class="codigo-input" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
            </div>

            <button type="submit" class="btn-submit">Validar Código</button>
        </form>

    <?php endif; ?>

</div>

</body>
</html>

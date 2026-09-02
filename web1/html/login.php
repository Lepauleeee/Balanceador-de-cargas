<?php
/**
 * Sistema de Autenticación de Doble Factor (2FA)
 * PHP + Google Authenticator + validador.py
 */

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

$conexion = new mysqli(
    "mysql_primary",
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

    // Comprobar que el archivo existe
    if (!file_exists($scriptValidador)) {
        error_log("2FA: No existe validador.py en: " . $scriptValidador);
        return false;
    }

    /*
     * Comprobar que exec() está disponible.
     */
    if (!function_exists('exec')) {
        error_log("2FA: La función exec() está deshabilitada en PHP.");
        return false;
    }

    /*
     * Construimos el comando de forma segura.
     *
     * El secret y el código se escapan con escapeshellarg()
     * para evitar problemas con caracteres especiales.
     */

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

    /*
     * Ejecutar Python.
     */
    exec(
        $comando,
        $salida,
        $codigoSalida
    );

    /*
     * Convertir toda la salida en una sola cadena.
     */
    $resultado = trim(implode("\n", $salida));

    /*
     * Registrar errores reales en el log del servidor,
     * pero no mostrarlos al usuario.
     */

    if ($codigoSalida !== 0) {
        error_log(
            "2FA: validador.py terminó con código "
            . $codigoSalida
            . ". Salida: "
            . $resultado
        );

        return false;
    }

    /*
     * Nuestro validador.py debe devolver:
     *
     * True
     *
     * o
     *
     * False
     */

    return $resultado === "True";
}


/* =========================================================
   PROCESAMIENTO DE PETICIONES POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =====================================================
       FASE 2
       VALIDACIÓN DEL CÓDIGO DE GOOGLE AUTHENTICATOR
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


        /*
         * Comprobar que el código tenga exactamente
         * 6 números.
         */

        if (
            empty($secretActivo) ||
            !preg_match('/^\d{6}$/', $totpCode)
        ) {

            $mensaje =
                '<h3 style="color:red;">
                    El código debe contener exactamente 6 dígitos.
                </h3>';

        } else {

            /*
             * AQUÍ SE USA TU validador.py
             */

            $codigoValido =
                validarCodigoConPython(
                    $secretActivo,
                    $totpCode
                );


            if ($codigoValido) {

                /*
                 * Regenerar el ID de sesión después
                 * de completar el 2FA.
                 */

                session_regenerate_id(true);


                /*
                 * Crear sesión de usuario autenticado.
                 */

                $_SESSION['user_id'] =
                    $_SESSION['pending_user_id'];

                $_SESSION['user_email'] =
                    $_SESSION['pending_email'];


                /*
                 * Limpiar datos temporales del 2FA.
                 */

                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_email']);
                unset($_SESSION['secret_activo']);
                unset($_SESSION['mostrar_qr']);


                $mensaje =
                    '<h3 style="color:green;">
                        ¡Acceso concedido con éxito!
                    </h3>';

            } else {

                $mensaje =
                    '<h3 style="color:red;">
                        Código de doble factor incorrecto o expirado.
                    </h3>';
            }
        }
    }


    /* =====================================================
       FASE 1
       LOGIN CON CORREO Y CONTRASEÑA
       ===================================================== */

    elseif (
        isset($_POST['email']) &&
        isset($_POST['password'])
    ) {

        $email = trim($_POST['email']);
        $pass = $_POST['password'];


        /*
         * Validar correo.
         */

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $mensaje =
                '<h3 style="color:red;">
                    Formato de correo electrónico inválido.
                </h3>';

        } else {

            /*
             * Buscar usuario únicamente por correo.
             */

            $stmt = $conexion->prepare(
                "SELECT id, email, password, secret
                 FROM usuarios
                 WHERE email = ?
                 LIMIT 1"
            );


            if (!$stmt) {

                $mensaje =
                    '<h3 style="color:red;">
                        Error interno al preparar la consulta.
                    </h3>';

            } else {

                $stmt->bind_param("s", $email);

                $stmt->execute();

                $resultado = $stmt->get_result();


                if ($resultado->num_rows === 1) {

                    $user = $resultado->fetch_assoc();


                    /*
                     * Verificar contraseña.
                     *
                     * Se mantiene como en tu sistema actual.
                     */

                    if (
                        password_verify(
                            $pass,
                            $user['password']
                        )
                    ) {


                        /*
                         * Regenerar sesión después
                         * de validar la contraseña.
                         */

                        session_regenerate_id(true);


                        /*
                         * Crear sesión temporal.
                         *
                         * Todavía NO está completamente
                         * autenticado hasta validar el 2FA.
                         */

                        $_SESSION['pending_user_id'] =
                            $user['id'];

                        $_SESSION['pending_email'] =
                            $user['email'];


                        /* =================================
                           USUARIO SIN 2FA
                           ================================= */

                        if (empty($user['secret'])) {

                            /*
                             * Generar secreto nuevo.
                             */

                            $nuevoSecreto =
                                generarSecretBase32(32);


                            /*
                             * Guardar secreto en la base de datos.
                             */

                            $update = $conexion->prepare(
                                "UPDATE usuarios
                                 SET secret = ?
                                 WHERE id = ?"
                            );


                            if (!$update) {

                                unset(
                                    $_SESSION['pending_user_id']
                                );

                                unset(
                                    $_SESSION['pending_email']
                                );

                                $mensaje =
                                    '<h3 style="color:red;">
                                        No se pudo preparar la configuración del 2FA.
                                    </h3>';

                            } else {

                                $update->bind_param(
                                    "si",
                                    $nuevoSecreto,
                                    $user['id']
                                );


                                if ($update->execute()) {

                                    /*
                                     * Guardar secreto en sesión.
                                     */

                                    $_SESSION['secret_activo'] =
                                        $nuevoSecreto;


                                    /*
                                     * Mostrar QR.
                                     */

                                    $_SESSION['mostrar_qr'] =
                                        true;

                                } else {

                                    unset(
                                        $_SESSION['pending_user_id']
                                    );

                                    unset(
                                        $_SESSION['pending_email']
                                    );

                                    $mensaje =
                                        '<h3 style="color:red;">
                                            No se pudo guardar el secreto del 2FA.
                                        </h3>';
                                }

                                $update->close();
                            }


                        }


                        /* =================================
                           USUARIO CON 2FA
                           ================================= */

                        else {

                            /*
                             * Usar secreto existente.
                             *
                             * NO generar uno nuevo.
                             */

                            $_SESSION['secret_activo'] =
                                $user['secret'];


                            /*
                             * No mostrar QR durante un
                             * login normal.
                             */

                            $_SESSION['mostrar_qr'] =
                                false;
                        }


                    } else {

                        $mensaje =
                            '<h3 style="color:red;">
                                Usuario o contraseña incorrectos.
                            </h3>';
                    }


                } else {

                    $mensaje =
                        '<h3 style="color:red;">
                            Usuario o contraseña incorrectos.
                        </h3>';
                }


                $stmt->close();
            }
        }
    }
}


/* =========================================================
   VARIABLES PARA EL HTML
   ========================================================= */

$secretVal =
    $_SESSION['secret_activo'] ?? '';

$mostrarQR =
    $_SESSION['mostrar_qr'] ?? false;

?>
<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login Seguro - 2FA</title>

<style>

body {
    font-family: Arial, sans-serif;
    text-align: center;
    margin-top: 50px;
    background-color: #f4f4f4;
}

.caja-2fa {
    background: white;
    width: 420px;
    max-width: 92%;
    margin: 0 auto;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    box-sizing: border-box;
}

.boton {
    background-color: #007BFF;
    color: white;
    padding: 11px 20px;
    border: none;
    cursor: pointer;
    width: 100%;
    font-size: 16px;
    border-radius: 4px;
}

.boton:hover {
    background-color: #0056b3;
}

.boton-verde {
    background-color: #4CAF50;
}

.boton-verde:hover {
    background-color: #3d8b40;
}

input[type="text"],
input[type="email"],
input[type="password"] {
    width: 90%;
    padding: 10px;
    margin-top: 5px;
    box-sizing: border-box;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.codigo {
    text-align: center;
    font-size: 20px;
    letter-spacing: 5px;
}

.secret-box {
    background: #eaeaea;
    padding: 10px 12px;
    font-family: monospace;
    font-size: 16px;
    letter-spacing: 2px;
    display: inline-block;
    border-radius: 4px;
    user-select: all;
    word-break: break-all;
}

.qr {
    width: 200px;
    height: 200px;
}

.separador {
    border: 0;
    border-top: 1px solid #ddd;
    margin: 20px 0;
}

.mensaje {
    margin-bottom: 20px;
}

.texto-ayuda {
    font-size: 12px;
    color: #777;
}

</style>

</head>

<body>


<?php if (!empty($mensaje)): ?>

<div class="mensaje">

    <?php echo $mensaje; ?>

</div>

<?php endif; ?>


<!-- =====================================================
     FASE 1
     LOGIN
     ===================================================== -->

<?php if (
    !isset($_SESSION['pending_user_id']) &&
    !isset($_SESSION['user_id'])
): ?>

<div class="caja-2fa">

    <h2>Iniciar Sesión</h2>

    <form method="POST">

        <div style="text-align:left; margin-bottom:15px;">

            <label>
                Correo Electrónico:
            </label>

            <br>

            <input
                type="email"
                name="email"
                placeholder="correo@dominio.com"
                required
            >

        </div>


        <div style="text-align:left; margin-bottom:15px;">

            <label>
                Contraseña:
            </label>

            <br>

            <input
                type="password"
                name="password"
                placeholder="Contraseña"
                required
            >

        </div>


        <button
            type="submit"
            class="boton boton-verde"
        >
            Ingresar
        </button>

    </form>

</div>


<!-- =====================================================
     FASE 2
     GOOGLE AUTHENTICATOR
     ===================================================== -->

<?php elseif (
    isset($_SESSION['pending_user_id'])
): ?>

<div class="caja-2fa">

    <h2>Seguridad Doble Factor</h2>


    <!-- =================================================
         PRIMERA CONFIGURACIÓN
         ================================================= -->

    <?php if (
        $mostrarQR &&
        !empty($secretVal)
    ): ?>

        <p>
            <strong>
                1. Escanea este código con Google Authenticator:
            </strong>
        </p>


        <?php

        $issuer =
            'CETI-Shop';

        $accountName =
            $_SESSION['pending_email'] ?? 'usuario';


        /*
         * URI TOTP estándar.
         *
         * SHA1
         * 6 dígitos
         * 30 segundos
         */

        $totpUri =
            'otpauth://totp/' .
            rawurlencode($issuer) .
            ':' .
            rawurlencode($accountName) .
            '?secret=' .
            rawurlencode($secretVal) .
            '&issuer=' .
            rawurlencode($issuer) .
            '&algorithm=SHA1' .
            '&digits=6' .
            '&period=30';


        /*
         * Generar QR.
         */

        $qrUrl =
            'https://api.qrserver.com/v1/create-qr-code/' .
            '?size=200x200' .
            '&data=' .
            urlencode($totpUri);

        ?>


        <img
            src="<?php echo htmlspecialchars(
                $qrUrl,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            alt="Código QR Google Authenticator"
            class="qr"
        >


        <br><br>


        <p class="texto-ayuda">
            O ingresa este código manualmente:
        </p>


        <div class="secret-box">

            <?php echo htmlspecialchars(
                $secretVal,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>


        <hr class="separador">

    <?php endif; ?>


    <!-- =================================================
         CÓDIGO DE 6 DÍGITOS
         ================================================= -->

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="verify_totp"
        >


        <p>

            <strong>

                <?php

                if ($mostrarQR) {
                    echo '2. Ingresa los 6 dígitos de la App:';
                } else {
                    echo 'Ingresa los 6 dígitos de la App:';
                }

                ?>

            </strong>

        </p>


        <input
            type="text"
            name="totp_code"
            class="codigo"
            inputmode="numeric"
            pattern="[0-9]{6}"
            minlength="6"
            maxlength="6"
            autocomplete="one-time-code"
            placeholder="000000"
            required
            autofocus
        >


        <br><br>


        <button
            type="submit"
            class="boton"
        >
            Validar Código
        </button>

    </form>

</div>

<?php endif; ?>


</body>

</html>

<?php
session_start();

// 1. Si no han iniciado sesión, van pa' fuera
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// NUEVO: 2. Candado de ROL: Si no es cliente, va pa' fuera (Evita el salto por URL)
if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'cliente') {
    // Si un admin intenta entrar aquí, lo regresamos a su panel
    header("Location: crud.php");
    exit();
}

// NUEVO: 3. Destruir la sesión después de 2 minutos (120 segundos) de inactividad
$tiempo_limite = 120;
if (isset($_SESSION['ultima_actividad'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultima_actividad'];
    if ($tiempo_transcurrido > $tiempo_limite) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
}
$_SESSION['ultima_actividad'] = time();

// NUEVO: 4. Generar Token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conexion = new mysqli("mysql-primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    die("Error de conexión a la base de datos.");
}
$conexion->set_charset("utf8mb4");

// Detectar qué tabla estamos viendo
$tablaParam = isset($_GET['tabla']) && $_GET['tabla'] === 'zapateria' ? 'zapateria' : 'muebles';
$tablaActiva = $tablaParam === 'zapateria' ? 'catalogo_zapateria' : 'catalogo_muebles';

$mensaje = "";

// Lógica para procesar la compra y bajar el stock
if (isset($_GET['accion']) && $_GET['accion'] === 'comprar' && isset($_GET['id'])) {
    
    // NUEVO: Validar Token CSRF al hacer una compra
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error de seguridad: Token CSRF inválido al intentar comprar.");
    }

    $idProducto = intval($_GET['id']);

    $stmt = $conexion->prepare("UPDATE $tablaActiva SET stock = stock - 1 WHERE id = ? AND stock > 0");
    $stmt->bind_param("i", $idProducto);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $mensaje = '<div class="alerta exito">¡Compra realizada con éxito! Se descontó 1 unidad del inventario.</div>';
    } else {
        $mensaje = '<div class="alerta error">No se pudo realizar la compra o el producto se encuentra agotado.</div>';
    }
    $stmt->close();
}

// Consultar los productos
$resultado = $conexion->query("SELECT * FROM $tablaActiva");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catálogo - CETI Shop</title>
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background-color: #f8fafc;
        color: #1e293b;
        min-height: 100vh;
    }

    /* Header superior */
    header {
        background-color: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        padding: 15px 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .brand {
        font-size: 20px;
        font-weight: 700;
        color: #2563eb;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .user-info {
        font-size: 14px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .user-email {
        font-weight: 600;
        color: #334155;
    }
    .btn-logout {
        color: #ef4444;
        text-decoration: none;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 6px;
        background-color: #fef2f2;
        transition: background 0.2s;
    }
    .btn-logout:hover {
        background-color: #fee2e2;
    }

    /* Contenedor Principal */
    .main-container {
        max-width: 1100px;
        margin: 40px auto;
        padding: 0 20px;
    }

    /* Tabs de navegación */
    .tabs-container {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-bottom: 30px;
    }
    .tab-btn {
        padding: 10px 24px;
        text-decoration: none;
        background-color: #ffffff;
        color: #64748b;
        border: 1px solid #cbd5e1;
        border-radius: 30px;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.2s ease;
    }
    .tab-btn:hover {
        border-color: #2563eb;
        color: #2563eb;
    }
    .tab-btn.activo {
        background-color: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    /* Grid de Productos */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
    }

    /* Cards de Producto */
    .card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px -3px rgba(0, 0, 0, 0.08);
    }
    .card-title {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }
    .card-subtitle {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 16px;
    }
    .card-price {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 16px;
    }

    /* Badges de Stock */
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 20px;
        width: fit-content;
    }
    .badge-success {
        background-color: #dcfce7;
        color: #15803d;
    }
    .badge-danger {
        background-color: #fee2e2;
        color: #b91c1c;
    }

    /* Botón Comprar */
    .btn-comprar {
        display: block;
        width: 100%;
        text-align: center;
        padding: 12px;
        background-color: #16a34a;
        color: #ffffff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 15px;
        transition: background-color 0.2s;
    }
    .btn-comprar:hover {
        background-color: #15803d;
    }
    .btn-disabled {
        background-color: #94a3b8;
        cursor: not-allowed;
    }
    .btn-disabled:hover {
        background-color: #94a3b8;
    }

    /* Alert */
    .alerta {
        max-width: 600px;
        margin: 0 auto 25px auto;
        padding: 12px 18px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        text-align: center;
    }
    .alerta.exito { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .alerta.error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
</style>
</head>
<body>

<header>
    <div class="brand">🛍️ CETI-Shop</div>
    <div class="user-info">
        <span>Sesión activa: <strong class="user-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Cliente'); ?></strong></span>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="main-container">

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <div class="tabs-container">
        <a href="catalogo.php?tabla=muebles" class="tab-btn <?php echo $tablaParam === 'muebles' ? 'activo' : ''; ?>">🛋️ Muebles</a>
        <a href="catalogo.php?tabla=zapateria" class="tab-btn <?php echo $tablaParam === 'zapateria' ? 'activo' : ''; ?>">👟 Zapatería</a>
    </div>

    <div class="products-grid">
        <?php while($row = $resultado->fetch_assoc()): ?>
            <?php
                $nombreProducto = $tablaActiva === 'catalogo_muebles' ? $row['articulo'] : $row['modelo'];
                $tallaInfo = isset($row['tALLA']) ? "Talla: " . htmlspecialchars($row['tALLA']) : null;
                $hayStock = $row['stock'] > 0;
            ?>
            <div class="card">
                <div>
                    <div class="card-title"><?php echo htmlspecialchars($nombreProducto); ?></div>
                    <?php if ($tallaInfo): ?>
                        <div class="card-subtitle"><?php echo $tallaInfo; ?></div>
                    <?php endif; ?>

                    <div class="card-price">$<?php echo number_format($row['precio'], 2); ?></div>

                    <div>
                        <?php if ($hayStock): ?>
                            <span class="badge badge-success">✓ <?php echo $row['stock']; ?> disponibles</span>
                        <?php else: ?>
                            <span class="badge badge-danger">✕ Agotado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <?php if ($hayStock): ?>
                        <!-- NUEVO: Agregamos el Token CSRF directamente en la URL del botón Comprar -->
                        <a href="catalogo.php?tabla=<?php echo $tablaParam; ?>&accion=comprar&id=<?php echo $row['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn-comprar">Comprar</a>
                    <?php else: ?>
                        <a href="#" class="btn-comprar btn-disabled" onclick="return false;">Sin Stock</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

</div>

</body>
</html>

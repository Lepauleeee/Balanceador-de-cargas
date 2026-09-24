<?php
session_start();

// 1. Candado de sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Candado de ROL: Si no es admin, va pa' fuera (Evita el salto por URL)
if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: catalogo.php");
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

// NUEVO: 4. Generar Token CSRF si no existe en la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conexion = new mysqli("mysql-primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    die("Error de conexión a la base de datos.");
}
$conexion->set_charset("utf8mb4");

// Detectar qué tabla estamos viendo
$tablaActiva = isset($_GET['tabla']) && $_GET['tabla'] === 'zapateria' ? 'catalogo_zapateria' : 'catalogo_muebles';

// Lógica de Eliminación (Delete)
if (isset($_GET['eliminar'])) {
    // NUEVO: Validar Token CSRF al eliminar
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error de seguridad: Token CSRF inválido al intentar eliminar.");
    }

    $idEliminar = intval($_GET['eliminar']);
    $stmtDel = $conexion->prepare("DELETE FROM $tablaActiva WHERE id = ?");
    $stmtDel->bind_param("i", $idEliminar);
    $stmtDel->execute();
    $stmtDel->close();
    header("Location: crud.php?tabla=" . ($tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'));
    exit();
}

// Lógica de Inserción (Create) y Actualización (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // NUEVO: Validar Token CSRF al enviar el formulario
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error de seguridad: Token CSRF inválido en el formulario.");
    }

    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);

    if ($_POST['accion'] === 'crear') {
        if ($tablaActiva === 'catalogo_muebles') {
            $articulo = trim($_POST['articulo']);
            $stmtIns = $conexion->prepare("INSERT INTO catalogo_muebles (articulo, precio, stock) VALUES (?, ?, ?)");
            $stmtIns->bind_param("sdi", $articulo, $precio, $stock);
        } else {
            $modelo = trim($_POST['modelo']);
            $talla = trim($_POST['talla']);
            $stmtIns = $conexion->prepare("INSERT INTO catalogo_zapateria (modelo, tALLA, precio, stock) VALUES (?, ?, ?, ?)");
            $stmtIns->bind_param("ssdi", $modelo, $talla, $precio, $stock);
        }
        $stmtIns->execute();
        $stmtIns->close();
    } elseif ($_POST['accion'] === 'editar') {
        $idUpdate = intval($_POST['id']);
        if ($tablaActiva === 'catalogo_muebles') {
            $articulo = trim($_POST['articulo']);
            $stmtUp = $conexion->prepare("UPDATE catalogo_muebles SET articulo = ?, precio = ?, stock = ? WHERE id = ?");
            $stmtUp->bind_param("sdii", $articulo, $precio, $stock, $idUpdate);
        } else {
            $modelo = trim($_POST['modelo']);
            $talla = trim($_POST['talla']);
            $stmtUp = $conexion->prepare("UPDATE catalogo_zapateria SET modelo = ?, tALLA = ?, precio = ?, stock = ? WHERE id = ?");
            $stmtUp->bind_param("ssdii", $modelo, $talla, $precio, $stock, $idUpdate);
        }
        $stmtUp->execute();
        $stmtUp->close();
    }
    header("Location: crud.php?tabla=" . ($tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'));
    exit();
}

// Lógica para cargar datos a Editar
$registroEditar = null;
if (isset($_GET['editar'])) {
    $idEditar = intval($_GET['editar']);
    $res = $conexion->query("SELECT * FROM $tablaActiva WHERE id = $idEditar");
    if ($res && $res->num_rows > 0) {
        $registroEditar = $res->fetch_assoc();
    }
}

$resultado = $conexion->query("SELECT * FROM $tablaActiva");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Admin CRUD - CETI Shop</title>
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
    .brand span {
        background: #dbeafe;
        color: #1e40af;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
        max-width: 1000px;
        margin: 40px auto;
        padding: 0 20px;
    }

    /* Tabs de navegación */
    .tabs-container {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-bottom: 25px;
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

    /* Card del Formulario */
    .card-form {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 28px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .card-form h3 {
        font-size: 18px;
        color: #0f172a;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    .grupo-campo {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .grupo-campo label {
        font-size: 13px;
        font-weight: 600;
        color: #475569;
    }
    .grupo-campo input {
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
    }
    .grupo-campo input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .actions-form {
        margin-top: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .btn-submit {
        padding: 10px 20px;
        background-color: #16a34a;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-submit:hover {
        background-color: #15803d;
    }
    .btn-submit.btn-edit {
        background-color: #2563eb;
    }
    .btn-submit.btn-edit:hover {
        background-color: #1d4ed8;
    }
    .btn-cancelar {
        color: #ef4444;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
    }
    .btn-cancelar:hover {
        text-decoration: underline;
    }

    /* Tabla de Inventario */
    .card-table {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .card-table-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        background-color: #ffffff;
    }
    .card-table-header h3 {
        font-size: 18px;
        color: #0f172a;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 14px;
    }
    th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        padding: 12px 20px;
        border-bottom: 1px solid #e2e8f0;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }
    td {
        padding: 14px 20px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }
    tr:last-child td {
        border-bottom: none;
    }
    tr:hover td {
        background-color: #f8fafc;
    }

    /* Badges y Botones de Tabla */
    .badge-stock {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
    }
    .badge-stock.ok { background: #dcfce7; color: #15803d; }
    .badge-stock.low { background: #fee2e2; color: #b91c1c; }

    .action-btn {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: opacity 0.2s;
    }
    .action-btn:hover {
        opacity: 0.85;
    }
    .btn-tabla-editar {
        background-color: #fef3c7;
        color: #d97706;
        margin-right: 6px;
    }
    .btn-tabla-eliminar {
        background-color: #fee2e2;
        color: #dc2626;
    }
</style>
</head>
<body>

<header>
    <div class="brand">
        ⚙️ Admin Panel <span>CRUD</span>
    </div>
    <div class="user-info">
        <span>Admin: <strong class="user-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Admin'); ?></strong></span>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="main-container">

    <div class="tabs-container">
        <a href="crud.php?tabla=muebles" class="tab-btn <?php echo $tablaActiva === 'catalogo_muebles' ? 'activo' : ''; ?>">🛋️ Muebles</a>
        <a href="crud.php?tabla=zapateria" class="tab-btn <?php echo $tablaActiva === 'catalogo_zapateria' ? 'activo' : ''; ?>">👟 Zapatería</a>
    </div>

    <!-- Formulario Agregar/Editar -->
    <div class="card-form">
        <h3><?php echo $registroEditar ? '✏️ Editar Registro' : '➕ Agregar a ' . ($tablaActiva === 'catalogo_muebles' ? 'Catálogo de Muebles' : 'Zapatería'); ?></h3>

        <form method="POST" action="crud.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>">
            
            <!-- NUEVO: Token CSRF Inyectado en el formulario -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <input type="hidden" name="accion" value="<?php echo $registroEditar ? 'editar' : 'crear'; ?>">
            <?php if ($registroEditar): ?>
                <input type="hidden" name="id" value="<?php echo $registroEditar['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <?php if ($tablaActiva === 'catalogo_muebles'): ?>
                    <div class="grupo-campo">
                        <label for="articulo">Nombre del Artículo</label>
                        <input type="text" id="articulo" name="articulo" placeholder="Ej. Sofá Cama" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['articulo']) : ''; ?>" required>
                    </div>
                <?php else: ?>
                    <div class="grupo-campo">
                        <label for="modelo">Modelo del Zapato</label>
                        <input type="text" id="modelo" name="modelo" placeholder="Ej. Sneaker Urban" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['modelo']) : ''; ?>" required>
                    </div>
                    <div class="grupo-campo">
                        <label for="talla">Talla</label>
                        <input type="text" id="talla" name="talla" placeholder="Ej. 27, 28..." value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['tALLA']) : ''; ?>" required>
                    </div>
                <?php endif; ?>

                <div class="grupo-campo">
                    <label for="precio">Precio ($)</label>
                    <input type="number" step="0.01" id="precio" name="precio" placeholder="0.00" value="<?php echo $registroEditar ? $registroEditar['precio'] : ''; ?>" required>
                </div>

                <div class="grupo-campo">
                    <label for="stock">Stock Disponible</label>
                    <input type="number" id="stock" name="stock" placeholder="0" value="<?php echo $registroEditar ? $registroEditar['stock'] : ''; ?>" required>
                </div>
            </div>

            <div class="actions-form">
                <button type="submit" class="btn-submit <?php echo $registroEditar ? 'btn-edit' : ''; ?>">
                    <?php echo $registroEditar ? 'Actualizar Registro' : 'Guardar Registro'; ?>
                </button>
                <?php if ($registroEditar): ?>
                    <a href="crud.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>" class="btn-cancelar">Cancelar Edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla de Productos -->
    <div class="card-table">
        <div class="card-table-header">
            <h3>📦 Inventario Actual de <?php echo $tablaActiva === 'catalogo_muebles' ? 'Muebles' : 'Zapatería'; ?></h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if ($tablaActiva === 'catalogo_muebles'): ?>
                        <th>Artículo</th>
                    <?php else: ?>
                        <th>Modelo</th>
                        <th>Talla</th>
                    <?php endif; ?>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?php echo $row['id']; ?></strong></td>
                    <?php if ($tablaActiva === 'catalogo_muebles'): ?>
                        <td><?php echo htmlspecialchars($row['articulo']); ?></td>
                    <?php else: ?>
                        <td><?php echo htmlspecialchars($row['modelo']); ?></td>
                        <td><?php echo htmlspecialchars($row['tALLA']); ?></td>
                    <?php endif; ?>
                    <td><strong>$<?php echo number_format($row['precio'], 2); ?></strong></td>
                    <td>
                        <span class="badge-stock <?php echo $row['stock'] > 0 ? 'ok' : 'low'; ?>">
                            <?php echo $row['stock']; ?> unid.
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <a href="crud.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>&editar=<?php echo $row['id']; ?>" class="action-btn btn-tabla-editar">Editar</a>
                        
                        <!-- NUEVO: Agregamos el Token CSRF directamente en el enlace de Eliminar -->
                        <a href="crud.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>&eliminar=<?php echo $row['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="action-btn btn-tabla-eliminar" onclick="return confirm('¿Seguro que deseas eliminar este producto?');">Eliminar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>

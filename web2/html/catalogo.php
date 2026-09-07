<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conexion = new mysqli("mysql_primary", "app_user", "PasswordSeguro123!", "ecommerce");
if ($conexion->connect_error) {
    die("Error de conexión.");
}
$conexion->set_charset("utf8mb4");

// Detectar qué tabla estamos viendo
$tablaActiva = isset($_GET['tabla']) && $_GET['tabla'] === 'zapateria' ? 'catalogo_zapateria' : 'catalogo_muebles';

// Lógica de Eliminación (Delete)
if (isset($_GET['eliminar'])) {
    $idEliminar = intval($_GET['eliminar']);
    $stmtDel = $conexion->prepare("DELETE FROM $tablaActiva WHERE id = ?");
    $stmtDel->bind_param("i", $idEliminar);
    $stmtDel->execute();
    $stmtDel->close();
    header("Location: catalogo.php?tabla=" . ($tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'));
    exit();
}

// Lógica de Inserción (Create) y Actualización (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
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
    header("Location: catalogo.php?tabla=" . ($tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'));
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
<title>Gestión de Catálogos - CETI-Shop</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; text-align: center; }
.contenedor { background: white; width: 700px; margin: 0 auto; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
th { background-color: #007BFF; color: white; }
input { padding: 8px; margin: 5px; width: 80%; }
button { background: #28a745; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; }
button:hover { background: #218838; }
.btn-eliminar { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 13px; }
.btn-eliminar:hover { background: #c82333; }
.btn-editar { background: #ffc107; color: black; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 13px; }
.btn-editar:hover { background: #e0a800; }
.menu-tabs { margin-bottom: 20px; }
.menu-tabs a { padding: 10px 20px; text-decoration: none; background: #ddd; color: #333; border-radius: 4px; margin: 0 5px; font-weight: bold; }
.menu-tabs a.activo { background: #007BFF; color: white; }
.form-container { background: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #eee; margin-bottom: 20px;}
</style>
</head>
<body>

<div class="contenedor">
    <h2>Administración de Catálogos</h2>
    <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_email']); ?> | <a href="logout.php">Cerrar Sesión</a></p>
    
    <div class="menu-tabs">
        <a href="catalogo.php?tabla=muebles" class="<?php echo $tablaActiva === 'catalogo_muebles' ? 'activo' : ''; ?>">Muebles</a>
        <a href="catalogo.php?tabla=zapateria" class="<?php echo $tablaActiva === 'catalogo_zapateria' ? 'activo' : ''; ?>">Zapatería</a>
    </div>

    <div class="form-container">
        <h3><?php echo $registroEditar ? 'Editar Registro' : 'Agregar a ' . ($tablaActiva === 'catalogo_muebles' ? 'Catálogo de Muebles' : 'Zapatería'); ?></h3>
        <form method="POST" action="catalogo.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>">
            <input type="hidden" name="accion" value="<?php echo $registroEditar ? 'editar' : 'crear'; ?>">
            <?php if ($registroEditar): ?>
                <input type="hidden" name="id" value="<?php echo $registroEditar['id']; ?>">
            <?php endif; ?>

            <?php if ($tablaActiva === 'catalogo_muebles'): ?>
                <input type="text" name="articulo" placeholder="Nombre del artículo" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['articulo']) : ''; ?>" required><br>
            <?php else: ?>
                <input type="text" name="modelo" placeholder="Modelo del zapato" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['modelo']) : ''; ?>" required><br>
                <input type="text" name="talla" placeholder="Talla (ej. 27, 28...)" value="<?php echo $registroEditar ? htmlspecialchars($registroEditar['tALLA']) : ''; ?>" required><br>
            <?php endif; ?>
            <input type="number" step="0.01" name="precio" placeholder="Precio ($)" value="<?php echo $registroEditar ? $registroEditar['precio'] : ''; ?>" required><br>
            <input type="number" name="stock" placeholder="Stock" value="<?php echo $registroEditar ? $registroEditar['stock'] : ''; ?>" required><br>
            
            <button type="submit" style="<?php echo $registroEditar ? 'background: #007BFF;' : ''; ?>">
                <?php echo $registroEditar ? 'Actualizar Registro' : 'Guardar Registro'; ?>
            </button>
            <?php if ($registroEditar): ?>
                <a href="catalogo.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>" style="margin-left: 10px; color: red; text-decoration: none;">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <h3>Inventario de <?php echo $tablaActiva === 'catalogo_muebles' ? 'Muebles' : 'Zapatería'; ?></h3>
    <table>
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
            <th>Acciones</th>
        </tr>
        <?php while($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <?php if ($tablaActiva === 'catalogo_muebles'): ?>
                <td><?php echo htmlspecialchars($row['articulo']); ?></td>
            <?php else: ?>
                <td><?php echo htmlspecialchars($row['modelo']); ?></td>
                <td><?php echo htmlspecialchars($row['tALLA']); ?></td>
            <?php endif; ?>
            <td>$<?php echo number_format($row['precio'], 2); ?></td>
            <td><?php echo $row['stock']; ?></td>
            <td>
                <a href="catalogo.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>&editar=<?php echo $row['id']; ?>" class="btn-editar">Editar</a>
                <a href="catalogo.php?tabla=<?php echo $tablaActiva === 'catalogo_zapateria' ? 'zapateria' : 'muebles'; ?>&eliminar=<?php echo $row['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Seguro que deseas eliminarlo?');">Eliminar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>

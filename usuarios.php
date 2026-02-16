<?php
session_start();
include 'db.php';

// SEGURIDAD: Solo el ADMIN puede entrar
if (!isset($_SESSION['loggedin']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$mensaje = "";

// CREAR USUARIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear'])) {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $user = $conn->real_escape_string($_POST['usuario']);
    $pass = $_POST['password'];
    $rol = $_POST['rol'];

    $check = $conn->query("SELECT id FROM usuarios WHERE usuario = '$user'");
    if ($check->num_rows > 0) {
        $mensaje = "<div class='alert alert-danger'>El usuario '$user' ya existe.</div>";
    } else {
        $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, password, rol) VALUES ('$nombre', '$user', '$pass_hash', '$rol')";
        if ($conn->query($sql)) {
            $mensaje = "<div class='alert alert-success'>Usuario creado con éxito.</div>";
        }
    }
}

// BORRAR USUARIO
if (isset($_GET['borrar'])) {
    $id_borrar = intval($_GET['borrar']);
    if ($id_borrar != $_SESSION['id']) {
        $conn->query("DELETE FROM usuarios WHERE id = $id_borrar");
        header("Location: usuarios.php");
        exit();
    } else {
        $mensaje = "<div class='alert alert-warning'>No puedes borrarte a ti mismo.</div>";
    }
}

$lista_usuarios = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios - PNP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=20">
</head>
<body style="background-color: #f4f6f9;">

<nav class="navbar navbar-dark mb-4" style="background: linear-gradient(135deg, #034828 0%, #066e3e 100%) !important;">
    <div class="container">
        <a href="index.php" class="text-white text-decoration-none fw-bold">
            <i class="bi bi-arrow-left-circle me-2"></i> Volver a la Biblioteca
        </a>
        <span class="text-white">Panel de Administración</span>
    </div>
</nav>

<div class="container pb-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3">
                    <h5 class="text-success fw-bold">Nuevo Usuario</h5>
                </div>
                <div class="card-body">
                    <?php echo $mensaje; ?>
                    <form method="POST">
                        <div class="mb-2">
                            <label class="small fw-bold text-muted">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-muted">Usuario (Login)</label>
                            <input type="text" name="usuario" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-muted">Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">Rol de Acceso</label>
                            <select name="rol" class="form-select">
                                <option value="lector">Lector (Solo ver)</option>
                                <option value="admin">Administrador (Total)</option>
                            </select>
                        </div>
                        <button type="submit" name="crear" class="btn btn-success w-100 fw-bold" style="background-color: #034828;">Crear Usuario</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Usuario</th>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($u = $lista_usuarios->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold ps-3"><?php echo htmlspecialchars($u['usuario']); ?></td>
                                <td><?php echo htmlspecialchars($u['nombre_completo']); ?></td>
                                <td>
                                    <?php if($u['rol'] == 'admin'): ?>
                                        <span class="badge bg-danger">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">LECTOR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <?php if($u['id'] != $_SESSION['id']): ?>
                                        <a href="usuarios.php?borrar=<?php echo $u['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('¿Estás seguro?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Tú</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
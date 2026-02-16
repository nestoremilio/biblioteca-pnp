<?php
include 'db.php';

// 1. Crear tabla de USUARIOS si no existe
$sql = "CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'lector') NOT NULL DEFAULT 'lector',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Tabla 'usuarios' verificada/creada correctamente.<br>";
} else {
    die("❌ Error creando tabla: " . $conn->error);
}

// 2. Crear Usuario Admin por defecto (si no hay ninguno)
// Usuario: admin
// Clave: admin123
$check = $conn->query("SELECT * FROM usuarios WHERE usuario='admin'");
if ($check->num_rows == 0) {
    $pass = password_hash('admin123', PASSWORD_DEFAULT);
    $sql_insert = "INSERT INTO usuarios (nombre_completo, usuario, password, rol) VALUES ('Super Administrador', 'admin', '$pass', 'admin')";
    if ($conn->query($sql_insert) === TRUE) {
        echo "✅ Usuario 'admin' creado por defecto (Clave: admin123).";
    }
} else {
    echo "ℹ️ El usuario 'admin' ya existe, no se hicieron cambios.";
}
?>
<?php
session_start();

// Verificar si el usuario está logueado Y es ADMIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 1. Obtener nombres de archivos (PDF y Portada)
    $sql_select = "SELECT archivo, portada FROM libros WHERE id = $id";
    $result = $conn->query($sql_select);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Rutas de archivos
        $pdf_path = "uploads/" . $row['archivo'];
        
        // 2. Borrar PDF físico
        if (file_exists($pdf_path)) {
            unlink($pdf_path);
        }

        // 3. Borrar Portada física (si existe)
        if (!empty($row['portada'])) {
            $cover_path = "uploads/" . $row['portada'];
            if (file_exists($cover_path)) {
                unlink($cover_path);
            }
        }

        // 4. Borrar registro de la BD
        $sql_delete = "DELETE FROM libros WHERE id = $id";
        if ($conn->query($sql_delete) === TRUE) {
            header("Location: index.php?msg=deleted");
            exit();
        } else {
            echo "Error al eliminar registro: " . $conn->error;
        }
    } else {
        echo "Libro no encontrado.";
    }
}
$conn->close();
?>
<?php
session_start();

// 1. SEGURIDAD: Verificar que el usuario esté logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Acceso denegado.");
}

// 2. Obtener el nombre del archivo de la URL
if (isset($_GET['archivo'])) {
    // 'basename' es vital por seguridad: evita que alguien pida rutas como ../../config.php
    $archivo = basename($_GET['archivo']); 
    $ruta_archivo = 'uploads/' . $archivo;

    // 3. Verificar si el archivo existe
    if (file_exists($ruta_archivo)) {
        // Cabeceras para decir al navegador "Esto es un PDF, muéstralo aquí (inline)"
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $archivo . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        
        // Leer el archivo y enviarlo al navegador
        readfile($ruta_archivo);
        exit;
    } else {
        echo "El archivo no existe o fue movido.";
    }
} else {
    echo "No se especificó ningún archivo.";
}
?>
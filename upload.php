<?php
session_start();

// Verificar si el usuario está logueado Y es ADMIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $autor = $conn->real_escape_string($_POST['autor']);
    $categoria = $conn->real_escape_string($_POST['categoria']);

    // Procesamiento del archivo PDF
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $allowed_pdf = ['pdf' => 'application/pdf'];
        $filename = $_FILES['pdf_file']['name'];
        $filetype = $_FILES['pdf_file']['type'];
        $filesize = $_FILES['pdf_file']['size'];

        // Verificar extensión PDF
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed_pdf)) {
            die("Error: Por favor selecciona un formato de archivo válido (PDF).");
        }

        // Verificar tamaño (máximo 200MB)
        $maxsize = 200 * 1024 * 1024;
        if ($filesize > $maxsize) {
            die("Error: El archivo es demasiado grande. Máximo 200MB.");
        }

        // Verificar tipo MIME PDF
        if (in_array($filetype, $allowed_pdf)) {
            // Crear carpeta uploads si no existe
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }

            // Nombre único para evitar sobreescrituras
            $new_filename = uniqid() . "_" . $filename;
            $destination = "uploads/" . $new_filename;

            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destination)) {

                // Procesamiento de la imagen de portada
                $portada_filename = null;
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
                    $allowed_img = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
                    $img_name = $_FILES['cover_image']['name'];
                    $img_type = $_FILES['cover_image']['type'];
                    $img_size = $_FILES['cover_image']['size'];
                    $img_ext = pathinfo($img_name, PATHINFO_EXTENSION);

                    // Validar imagen (tipo y tamaño < 5MB)
                    if (array_key_exists(strtolower($img_ext), $allowed_img) && in_array($img_type, $allowed_img) && $img_size < 5 * 1024 * 1024) {
                        $new_img_name = uniqid() . "_cover_" . $img_name;
                        $img_destination = "uploads/" . $new_img_name;
                        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $img_destination)) {
                            $portada_filename = $new_img_name;
                        }
                    }
                }

                // Insertar en base de datos
                $portada_sql = $portada_filename ? "'$portada_filename'" : "NULL";
                $sql = "INSERT INTO libros (titulo, autor, archivo, portada, categoria) VALUES ('$titulo', '$autor', '$new_filename', $portada_sql, '$categoria')";

                if ($conn->query($sql) === TRUE) {
                    header("Location: index.php?msg=uploaded");
                    exit();
                } else {
                    echo "Error en base de datos: " . $conn->error;
                }
            } else {
                echo "Error al mover el archivo.";
            }
        } else {
            echo "Error: Hubo un problema con la subida del archivo. Inténtalo de nuevo.";
        }
    } else {
        echo "Error: " . $_FILES['pdf_file']['error'];
    }
}
$conn->close();
?>
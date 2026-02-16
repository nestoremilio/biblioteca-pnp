<?php
session_start();
include 'db.php';

// Seguridad: Solo admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Obtener libro
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM libros WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows == 1) {
        $libro = $result->fetch_assoc();
    } else {
        die("Libro no encontrado.");
    }
} else {
    header("Location: index.php");
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $autor = $conn->real_escape_string($_POST['autor']);
    $categoria = $conn->real_escape_string($_POST['categoria']);
    
    // 1. Actualizar textos
    $sql = "UPDATE libros SET titulo='$titulo', autor='$autor', categoria='$categoria' WHERE id=$id";
    if (!$conn->query($sql)) {
        echo "Error: " . $conn->error;
        exit();
    }
    
    // 2. ¿Nuevo PDF?
    if (!empty($_FILES['pdf_file']['name'])) {
        $filename = $_FILES['pdf_file']['name'];
        $filesize = $_FILES['pdf_file']['size'];
        $filetype = $_FILES['pdf_file']['type'];
        
        // Validar PDF básico
        if($filetype == 'application/pdf' && $filesize < 200 * 1024 * 1024) {
            // Borrar archivo antiguo del servidor para ahorrar espacio
            $archivo_viejo = "uploads/" . $libro['archivo'];
            if (file_exists($archivo_viejo)) {
                unlink($archivo_viejo);
            }

            // Subir nuevo
            $pdf_name = uniqid() . "_" . $filename;
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], "uploads/" . $pdf_name)) {
                $conn->query("UPDATE libros SET archivo='$pdf_name' WHERE id=$id");
            }
        }
    }

    // 3. ¿Nueva Portada?
    if (!empty($_FILES['cover_image']['name'])) {
        $img_name = $_FILES['cover_image']['name'];
        // Borrar portada antigua si existe
        if ($libro['portada']) {
            $portada_vieja = "uploads/" . $libro['portada'];
            if (file_exists($portada_vieja)) {
                unlink($portada_vieja);
            }
        }

        $cover_name = uniqid() . "_" . $img_name;
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], "uploads/" . $cover_name)) {
            $conn->query("UPDATE libros SET portada='$cover_name' WHERE id=$id");
        }
    }

    header("Location: index.php?msg=updated");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Libro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=15">
</head>
<body style="background-color: #f4f6f9;">

    <nav class="navbar navbar-dark mb-4" style="background: linear-gradient(135deg, #034828 0%, #066e3e 100%) !important;">
        <div class="container">
            <a href="index.php" class="text-white text-decoration-none fw-bold fs-5">
                <i class="bi bi-arrow-left-circle me-2"></i> Volver a la Biblioteca
            </a>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="row justify-content-center">
            
            <div class="col-lg-5 col-md-8">
                
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4"> 
                        <h4 class="fw-bold mb-4 text-center" style="color: #034828;">
                            <i class="bi bi-pencil-square me-2"></i>Editar Libro
                        </h4>
                        
                        <form action="" method="POST" enctype="multipart/form-data">
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted fw-bold">TÍTULO DEL LIBRO</label>
                                <input type="text" class="form-control" name="titulo" value="<?php echo htmlspecialchars($libro['titulo']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted fw-bold">AUTOR</label>
                                <input type="text" class="form-control" name="autor" value="<?php echo htmlspecialchars($libro['autor']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted fw-bold">CATEGORÍA</label>
                                <select class="form-select" name="categoria" required>
                                    <option value="Orden y Seguridad" <?php if($libro['categoria'] == 'Orden y Seguridad') echo 'selected'; ?>>Orden y Seguridad</option>
                                    <option value="Investigación Criminal" <?php if($libro['categoria'] == 'Investigación Criminal') echo 'selected'; ?>>Investigación Criminal</option>
                                    <option value="Complementos" <?php if($libro['categoria'] == 'Complementos') echo 'selected'; ?>>Complementos</option>
                                </select>
                            </div>

                            <!-- SECCIÓN PDF NUEVA -->
                            <div class="p-3 bg-light rounded mb-3 border">
                                <label class="form-label small text-muted fw-bold mb-2 d-block">ARCHIVO PDF ACTUAL</label>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-truncate small text-dark"><i class="bi bi-file-pdf text-danger"></i> <?php echo $libro['archivo']; ?></span>
                                    <a href="uploads/<?php echo $libro['archivo']; ?>" target="_blank" class="btn btn-sm btn-outline-primary ms-2">Ver</a>
                                </div>
                                <hr class="my-2">
                                <label class="form-label small text-muted fw-bold">REEMPLAZAR PDF (OPCIONAL)</label>
                                <input type="file" class="form-control form-control-sm" name="pdf_file" accept=".pdf">
                                <div class="form-text small mt-1">Sube un archivo nuevo solo si quieres cambiar el actual.</div>
                            </div>

                            <div class="p-3 bg-light rounded mb-4 border">
                                <label class="form-label small text-muted fw-bold mb-2 d-block">PORTADA</label>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <?php if($libro['portada']): ?>
                                        <img src="uploads/<?php echo $libro['portada']; ?>" alt="Portada" style="height: 60px; width:45px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center text-white small" style="height:60px; width:45px;">Sin img</div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <input type="file" class="form-control form-control-sm" name="cover_image" accept="image/*">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning fw-bold text-dark">
                                    <i class="bi bi-save me-2"></i>Guardar Cambios
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary border-0">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
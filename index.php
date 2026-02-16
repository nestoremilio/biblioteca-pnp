<?php
session_start();

// SEGURIDAD
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// --- CONFIGURACIÓN ---
$libros_por_pagina = 12;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

$categoria_seleccionada = isset($_GET['categoria']) ? urldecode($_GET['categoria']) : '';
$termino_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// --- FILTROS SQL ---
$condiciones = [];
if (!empty($categoria_seleccionada)) {
    $cat_segura = $conn->real_escape_string($categoria_seleccionada);
    $condiciones[] = "categoria = '$cat_segura'";
}
if (!empty($termino_busqueda)) {
    $busqueda_segura = $conn->real_escape_string($termino_busqueda);
    $condiciones[] = "(titulo LIKE '%$busqueda_segura%' OR autor LIKE '%$busqueda_segura%')";
}
$sql_where = "";
if (count($condiciones) > 0) {
    $sql_where = "WHERE " . implode(' AND ', $condiciones);
}

// --- CONSULTAS ---
$sql_total = "SELECT COUNT(id) as total FROM libros $sql_where";
$result_total = $conn->query($sql_total);
$fila_total = $result_total->fetch_assoc();
$total_libros = $fila_total['total'];
$total_paginas = ceil($total_libros / $libros_por_pagina);

$inicio = ($pagina_actual - 1) * $libros_por_pagina;
$sql = "SELECT * FROM libros $sql_where ORDER BY fecha_subida DESC LIMIT $inicio, $libros_por_pagina";
$result = $conn->query($sql);

$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

function url_pag($num_pag, $cat, $busqueda) {
    $url = "?pagina=" . $num_pag;
    if (!empty($cat)) $url .= "&categoria=" . urlencode($cat);
    if (!empty($busqueda)) $url .= "&busqueda=" . urlencode($busqueda);
    return $url;
}

// --- RESPUESTA AJAX ---
if (isset($_GET['ajax'])) {
    echo '<div class="d-flex flex-column flex-md-row justify-content-between align-items-end mb-4 animate-up">
            <div class="mb-2 mb-md-0">
                <h2 class="fw-bold text-dark mb-1" style="font-size: clamp(1.5rem, 2.5vw, 1.75rem);">Colección Bibliográfica</h2>
                <p class="text-muted mb-0 small">Explora nuestros recursos digitales disponibles</p>
            </div>
            <span class="badge bg-pnp text-white px-3 py-2 rounded-pill shadow-sm align-self-start align-self-md-center">
                ' . ((!empty($termino_busqueda) || !empty($categoria_seleccionada)) ? 'Encontrados: ' . $total_libros : 'Total Libros: ' . $total_libros) . '
            </span>
          </div>';

    echo '<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3 g-lg-4 animate-up">';
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $portada_html = !empty($row['portada']) 
                ? '<img src="uploads/'.htmlspecialchars($row['portada']).'" alt="Portada" class="book-cover">' 
                : '<div class="book-cover-placeholder d-flex align-items-center justify-content-center"><i class="bi bi-book text-muted" style="font-size: 3rem; opacity:0.5;"></i></div>';
            
            $cat_badge = !empty($row['categoria']) 
                ? '<span class="badge badge-custom mb-2">'.htmlspecialchars($row['categoria']).'</span>' 
                : '';

            $admin_btns = '';
            if ($es_admin) {
                $admin_btns = '
                <div class="admin-actions">
                    <a href="edit.php?id='.$row['id'].'" class="btn-icon warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                    <a href="delete.php?id='.$row['id'].'" class="btn-icon danger" onclick="return confirm(\'¿Estás seguro?\');" title="Eliminar"><i class="bi bi-trash-fill"></i></a>
                </div>';
            }

            echo '
            <div class="col">
                <div class="card h-100 book-card border-0">
                    <div class="card-img-wrapper">
                        '.$portada_html.'
                    </div>
                    
                    <div class="card-body d-flex flex-column p-4 text-center">
                        <div class="mb-2">'.$cat_badge.'</div>
                        <h6 class="card-title fw-bold text-dark mb-1 text-truncate-2" title="'.htmlspecialchars($row['titulo']).'">'.htmlspecialchars($row['titulo']).'</h6>
                        <p class="card-text text-muted small mb-3 text-truncate">'.htmlspecialchars($row['autor']).'</p>
                        
                        <div class="mt-auto pt-3 border-top w-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="#" class="btn btn-pnp w-100 rounded-3 fw-bold view-pdf-btn" data-pdf-url="ver_pdf.php?archivo='.$row['archivo'].'">
                                    Leer Libro
                                </a>
                                '.$admin_btns.'
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }
    } else {
        echo '<div class="col-12"><div class="alert alert-light text-center py-5 border rounded-3 shadow-sm">
                <i class="bi bi-search display-4 text-muted mb-3 d-block"></i>
                <h5 class="text-muted">No encontramos libros.</h5>
              </div></div>';
    }
    echo '</div>';

    if ($total_paginas > 1) {
        echo '<div class="d-flex justify-content-center mt-5"><nav aria-label="Navegación"><ul class="pagination pagination-custom flex-wrap">';
        $class_prev = ($pagina_actual <= 1) ? 'disabled' : '';
        $url_prev = ($pagina_actual > 1) ? url_pag($pagina_actual-1, $categoria_seleccionada, $termino_busqueda) : '#';
        echo '<li class="page-item '.$class_prev.'"><a class="page-link ajax-link" href="'.$url_prev.'"><i class="bi bi-chevron-left"></i></a></li>';
        
        for($i = 1; $i <= $total_paginas; $i++) {
            $active = ($pagina_actual == $i) ? 'active' : '';
            echo '<li class="page-item '.$active.'"><a class="page-link ajax-link" href="'.url_pag($i, $categoria_seleccionada, $termino_busqueda).'">'.$i.'</a></li>';
        }

        $class_next = ($pagina_actual >= $total_paginas) ? 'disabled' : '';
        $url_next = ($pagina_actual < $total_paginas) ? url_pag($pagina_actual+1, $categoria_seleccionada, $termino_busqueda) : '#';
        echo '<li class="page-item '.$class_next.'"><a class="page-link ajax-link" href="'.$url_next.'"><i class="bi bi-chevron-right"></i></a></li>';
        echo '</ul></nav></div>';
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Virtual PNP</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>

    <style>
        :root {
            --pnp-green: #006837; --pnp-dark: #004d28; --bg-light: #f4f6f9;
            --navbar-height: 90px; --navbar-height-mobile: 80px;
        }
        
        body { 
            background-color: var(--bg-light); 
            font-family: 'Poppins', sans-serif;
            padding-top: var(--navbar-height); 
            user-select: none;
        }

        /* --- NAVBAR --- */
        .navbar-custom {
            background: linear-gradient(90deg, var(--pnp-green) 0%, var(--pnp-dark) 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15); padding: 0.5rem 0; z-index: 1030;
        }
        .navbar-brand { display: flex; align-items: center; }
        .brand-logo { height: 65px; width: auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); transition: transform 0.3s; }
        .brand-text-container { display: flex; flex-direction: column; justify-content: center; line-height: 1; }
        .brand-title { font-weight: 700; color: white; font-size: 1.6rem; letter-spacing: -0.5px; margin-bottom: 0; }
        .brand-subtitle { color: rgba(255,255,255,0.85); font-size: 0.85rem; font-weight: 300; margin-top: 3px; line-height: 1.2; }

        .nav-btn-custom {
            border: 1px solid rgba(255,255,255,0.4); color: white; transition: all 0.3s ease;
            background: rgba(255,255,255,0.05); min-width: 140px; padding: 0.5rem 1.5rem;
            display: inline-flex; align-items: center; justify-content: center; white-space: nowrap;
        }
        .nav-btn-custom:hover { background: white; color: var(--pnp-green); transform: translateY(-2px); border-color: white; }

        .btn-logout {
            background-color: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.4); color: #ffe6e6;
            min-width: 120px; padding: 0.5rem 1.5rem; transition: all 0.3s ease;
            display: inline-flex; align-items: center; justify-content: center; white-space: nowrap;
        }
        .btn-logout:hover { background-color: #dc3545; color: white; border-color: #dc3545; transform: translateY(-2px); }

        /* --- VISOR PDF PERSONALIZADO --- */
        #pdf-viewer-container {
            width: 100%; height: 100%; overflow: auto; background-color: #525659;
            display: flex; flex-direction: column; align-items: center; padding: 30px;
        }
        .pdf-page-canvas {
            box-shadow: 0 4px 15px rgba(0,0,0,0.5); margin-bottom: 20px; background: white;
        }
        /* Controles de navegación FLOTANTES */
        .pdf-controls {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: rgba(30, 30, 30, 0.9); padding: 12px 25px; border-radius: 50px;
            display: flex; gap: 15px; align-items: center; z-index: 2000;
            color: white; font-weight: bold; box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            backdrop-filter: blur(5px);
        }
        .pdf-controls button {
            background: transparent; border: none; color: white; font-size: 1.3rem; cursor: pointer;
            width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; transition: background 0.2s;
        }
        .pdf-controls button:hover { background: rgba(255,255,255,0.2); }
        .pdf-controls button:disabled { opacity: 0.3; cursor: default; }
        
        .zoom-divider { width: 1px; height: 25px; background: rgba(255,255,255,0.3); margin: 0 5px; }

        /* --- CARDS & UI --- */
        .search-hero { background: white; border-radius: 50px; padding: 8px 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); display: flex; align-items: center; margin-bottom: 2rem; }
        .search-input { border: none; background: transparent; font-size: 1rem; padding: 10px 15px; width: 100%; outline: none; }
        .search-select { border: none; background-color: var(--bg-light); border-radius: 30px; padding: 10px 20px; font-weight: 500; cursor: pointer; outline: none; }
        
        .book-card { background: white; border-radius: 16px; overflow: hidden; transition: all 0.3s; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .book-card:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
        .card-img-wrapper { position: relative; height: 260px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .book-cover { max-height: 85%; max-width: 80%; object-fit: contain; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.15)); transition: transform 0.4s ease; }
        .book-card:hover .book-cover { transform: scale(1.05); }
        
        /* Eliminado .card-overlay para quitar el botón de vista previa */

        .badge-custom { background-color: rgba(0, 104, 55, 0.1); color: var(--pnp-green); font-weight: 600; padding: 5px 10px; border-radius: 6px; font-size: 0.7rem; text-transform: uppercase; }
        .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .btn-pnp { background-color: var(--pnp-green); color: white; border: none; }
        .bg-pnp { background-color: var(--pnp-green); }
        .text-pnp { color: var(--pnp-green); }
        .pagination-custom .page-link { border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 5px; color: #555; font-weight: 600; }
        .pagination-custom .page-item.active .page-link { background-color: var(--pnp-green); color: white; }

        .admin-actions { display: flex; gap: 5px; margin-left: 10px; }
        .btn-icon { width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #555; background: #f1f1f1; text-decoration: none; }
        .btn-icon.warning:hover { background: #ffc107; color: #000; }
        .btn-icon.danger:hover { background: #dc3545; color: white; }

        /* --- RESPONSIVE --- */
        @media (max-width: 991.98px) {
            body { padding-top: var(--navbar-height-mobile); }
            .navbar .container-fluid { padding-right: 15px; }
            .brand-logo { height: 40px; margin-right: 10px; }
            .navbar-brand { margin-right: 0; max-width: 80%; display: flex; align-items: center; }
            .brand-title { font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .brand-subtitle { font-size: 0.65rem; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .navbar-toggler { margin-left: auto; border: 1px solid rgba(255,255,255,0.3); }
            .navbar-collapse { background: white; margin-top: 15px; padding: 20px; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
            .nav-buttons-container { flex-direction: column; gap: 12px !important; }
            .nav-btn-custom, .btn-logout { background: #f8f9fa; color: #333; border: 1px solid #eee; width: 100%; min-height: 50px; justify-content: center; }
            .nav-btn-custom:hover { background: var(--pnp-green); color: white; }
            .btn-logout { color: #dc3545; }
            .user-info-mobile { text-align: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; width: 100%; color: #333 !important; }
            .text-mobile-dark { color: #333 !important; }
            .search-hero { flex-direction: column; align-items: stretch; border-radius: 20px; }
            .search-select { width: 100%; margin-top: 5px; text-align: center; border-top: 1px solid #eee; border-radius: 0 0 20px 20px; }
            .vr { display: none; }
            .modal-fullscreen-mobile { max-width: 100%; margin: 0; height: 100%; }
            .modal-fullscreen-mobile .modal-content { height: 100% !important; border-radius: 0 !important; }
            
            /* Ajuste de controles PDF en móvil */
            .pdf-controls { padding: 8px 15px; width: 90%; justify-content: space-between; bottom: 20px; }
        }

        @media (min-width: 992px) {
            :root { --navbar-height: 110px; }
            .brand-logo { height: 75px; margin-right: 20px; }
            .brand-title { font-size: 2rem; }
            .brand-subtitle { font-size: 1rem; white-space: nowrap; }
            .nav-buttons-container { flex-direction: row !important; align-items: center !important; gap: 1rem !important; }
            .nav-btn-custom, .btn-logout { width: auto; min-width: 140px; }
            .user-info-mobile { text-align: right; border: none; padding: 0; margin: 0; width: auto; }
            .text-mobile-dark { color: white !important; }
        }
        .animate-up { animation: fadeInUp 0.5s ease-out forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body oncontextmenu="return false;">

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo-pnp.png" alt="PNP" class="brand-logo">
                <div class="brand-text-container ms-2 ms-lg-3">
                    <span class="brand-title">Biblioteca Virtual</span>
                    <span class="brand-subtitle">Escuela de Educación Superior Técnico Profesional PNP - Tarapoto</span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                <div class="d-flex ms-auto nav-buttons-container">
                    <?php if ($es_admin): ?>
                        <a href="usuarios.php" class="btn nav-btn-custom rounded-pill fw-medium"><i class="bi bi-people-fill me-2"></i>Usuarios</a>
                    <?php endif; ?>
                    <a href="https://enfpp.repositorio.pnp.edu.pe/" target="_blank" class="btn nav-btn-custom rounded-pill fw-medium"><i class="bi bi-globe me-2"></i>Repositorio</a>
                    <div class="user-info-mobile lh-sm me-2 ms-lg-2">
                        <div class="fw-bold fs-6 text-white text-mobile-dark">Hola, <?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <small class="opacity-75 text-white text-mobile-dark" style="font-size: 0.8rem;">(<?php echo ucfirst($_SESSION['rol']); ?>)</small>
                    </div>
                    <a href="logout.php" class="btn btn-logout rounded-pill fw-bold"><i class="bi bi-box-arrow-right me-2"></i>Salir</a>
                    <img src="images/LOGO-EESTP.png" alt="EESTP" class="brand-logo d-none d-lg-block ms-3" style="height: 55px;">
                </div>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <?php if ($es_admin): ?>
            <div class="card shadow-sm border-0 mb-5 rounded-4 overflow-hidden animate-up">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                     <h5 class="fw-bold text-dark"><i class="bi bi-cloud-plus-fill me-2 text-pnp"></i>Gestión de Libros</h5>
                </div>
                <div class="card-body p-4">
                    <form action="upload.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" class="form-control bg-light border-0 py-2" name="titulo" placeholder="Título" required></div>
                            <div class="col-md-6"><input type="text" class="form-control bg-light border-0 py-2" name="autor" placeholder="Autor" required></div>
                            <div class="col-md-4">
                                <select class="form-select bg-light border-0 py-2" name="categoria" required>
                                    <option value="" disabled selected>Categoría...</option>
                                    <option value="Orden y Seguridad">Orden y Seguridad</option>
                                    <option value="Investigación Criminal">Investigación Criminal</option>
                                    <option value="Complementos">Complementos</option>
                                </select>
                            </div>
                            <div class="col-md-4"><input type="file" class="form-control bg-light border-0" name="pdf_file" accept=".pdf" required></div>
                            <div class="col-md-4"><input type="file" class="form-control bg-light border-0" name="cover_image" accept="image/*"></div>
                            <div class="col-12 text-end mt-4"><button type="submit" class="btn btn-pnp rounded-pill px-5 fw-bold shadow-sm">Guardar Libro</button></div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="search-hero animate-up">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar por título, autor o palabra clave..." value="<?php echo htmlspecialchars($termino_busqueda); ?>" autocomplete="off">
            <button type="button" id="clearBtn" class="btn btn-link text-decoration-none text-muted p-0 me-2" style="display:none;"><i class="bi bi-x-circle-fill"></i></button>
            <div class="vr mx-2 my-auto" style="height: 25px; opacity: 0.2;"></div>
            <select class="search-select" id="categoryFilter">
                <option value="">Todas las Áreas</option>
                <option value="Orden y Seguridad">Orden y Seguridad</option>
                <option value="Investigación Criminal">Investigación Criminal</option>
                <option value="Complementos">Complementos</option>
            </select>
        </div>

        <div id="resultados_container" style="min-height: 400px;">
            <div class="text-center py-5">
                <div class="spinner-grow text-pnp" role="status" style="width: 3rem; height: 3rem;"></div>
                <p class="text-muted mt-3 fw-medium">Cargando biblioteca...</p>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content shadow-none border-0 bg-dark">
                <div class="modal-header border-0 py-2" style="background: var(--pnp-green); color: white;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-book-half me-2"></i>
                        <h6 class="modal-title fw-bold mb-0">Lectura Segura</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 position-relative">
                    <div id="pdf-viewer-container"></div>
                    
                    <div class="pdf-controls" id="pdfControls" style="display: none;">
                        <button id="zoom-out" title="Reducir"><i class="bi bi-dash-lg"></i></button>
                        <button id="zoom-in" title="Aumentar"><i class="bi bi-plus-lg"></i></button>
                        
                        <div class="zoom-divider"></div>
                        
                        <button id="prev-page" title="Anterior"><i class="bi bi-chevron-left"></i></button>
                        <span class="mx-2" style="white-space: nowrap;">
                            <span id="page-num">1</span> / <span id="page-count">--</span>
                        </span>
                        <button id="next-page" title="Siguiente"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    
                    <div id="pdf-loader" class="position-absolute top-50 start-50 translate-middle text-center text-white">
                        <div class="spinner-border mb-2" role="status"></div>
                        <div>Cargando documento...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- 1. LÓGICA DE PDF.JS CON ZOOM ---
        let pdfDoc = null,
            pageNum = 1,
            pageRendering = false,
            pageNumPending = null,
            scale = 1.0, 
            canvasContainer = document.getElementById('pdf-viewer-container');

        // Renderizar página
        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function(page) {
                canvasContainer.innerHTML = ''; 
                
                var viewport = page.getViewport({scale: scale});
                var canvas = document.createElement('canvas');
                canvas.className = 'pdf-page-canvas';
                var ctx = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvasContainer.appendChild(canvas);

                var renderContext = { canvasContext: ctx, viewport: viewport };
                var renderTask = page.render(renderContext);

                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('page-num').textContent = num;
            document.getElementById('prev-page').disabled = num <= 1;
            document.getElementById('next-page').disabled = (pdfDoc && num >= pdfDoc.numPages);
        }

        function queueRenderPage(num) {
            if (pageRendering) { pageNumPending = num; } else { renderPage(num); }
        }

        document.getElementById('prev-page').addEventListener('click', () => { if (pageNum <= 1) return; pageNum--; queueRenderPage(pageNum); });
        document.getElementById('next-page').addEventListener('click', () => { if (pageNum >= pdfDoc.numPages) return; pageNum++; queueRenderPage(pageNum); });

        document.getElementById('zoom-in').addEventListener('click', () => { scale += 0.2; renderPage(pageNum); });
        document.getElementById('zoom-out').addEventListener('click', () => { if (scale <= 0.4) return; scale -= 0.2; renderPage(pageNum); });

        // --- 2. LÓGICA GENERAL ---
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 's' || e.key === 'u')) { e.preventDefault(); }
        });

        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const clearBtn = document.getElementById('clearBtn');
        const resultsContainer = document.getElementById('resultados_container');
        let debounceTimer;

        document.addEventListener("DOMContentLoaded", () => { realizarBusqueda(1); });

        function realizarBusqueda(pagina = 1) {
            const query = searchInput.value;
            const cat = categoryFilter.value;
            clearBtn.style.display = query.length > 0 ? 'block' : 'none';
            resultsContainer.style.opacity = '0.5';

            fetch(`index.php?ajax=1&pagina=${pagina}&busqueda=${encodeURIComponent(query)}&categoria=${encodeURIComponent(cat)}`)
                .then(response => response.text())
                .then(html => {
                    resultsContainer.innerHTML = html;
                    resultsContainer.style.opacity = '1';
                    initPdfButtons();
                    initPaginationLinks();
                });
        }

        searchInput.addEventListener('input', function() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => { realizarBusqueda(1); }, 250); });
        categoryFilter.addEventListener('change', () => realizarBusqueda(1));
        clearBtn.addEventListener('click', () => { searchInput.value = ''; realizarBusqueda(1); searchInput.focus(); });

        function initPaginationLinks() {
            document.querySelectorAll('.ajax-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    realizarBusqueda(new URLSearchParams(new URL(this.href).search).get('pagina') || 1);
                    document.getElementById('resultados_container').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        }

        const pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));

        function initPdfButtons() {
            document.querySelectorAll('.view-pdf-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    let url = this.getAttribute('data-pdf-url');
                    
                    pdfModal.show();
                    // Reiniciar estados visuales
                    document.getElementById('pdf-loader').style.display = 'block';
                    document.getElementById('pdfControls').style.display = 'none';
                    canvasContainer.style.opacity = '0';
                    canvasContainer.innerHTML = '';

                    // Carga del documento
                    pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
                        pdfDoc = pdfDoc_;
                        document.getElementById('page-count').textContent = pdfDoc.numPages;
                        
                        // Ocultar loader y mostrar controles
                        document.getElementById('pdf-loader').style.display = 'none';
                        document.getElementById('pdfControls').style.display = 'flex';
                        canvasContainer.style.opacity = '1';
                        
                        // Escala automática
                        pdfDoc.getPage(1).then(function(page) {
                            var containerWidth = canvasContainer.clientWidth - 40; 
                            var unscaledViewport = page.getViewport({scale: 1.0});
                            scale = containerWidth / unscaledViewport.width;
                            if (scale > 1.5) scale = 1.5; 
                            if (scale < 0.6) scale = 0.6; // Mínimo legible en móvil
                            
                            pageNum = 1;
                            renderPage(pageNum);
                        });

                    }).catch(function(error) {
                        console.error('Error al cargar PDF:', error);
                        document.getElementById('pdf-loader').innerHTML = 'Error al cargar el documento.';
                    });
                });
            });
        }
        
        document.getElementById('pdfModal').addEventListener('hidden.bs.modal', () => {
            pdfDoc = null;
            canvasContainer.innerHTML = '';
        });
    </script>
</body>
</html>
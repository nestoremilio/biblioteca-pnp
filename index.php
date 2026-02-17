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
$pagina_actual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($pagina_actual < 1)
    $pagina_actual = 1;

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

function url_pag($num_pag, $cat, $busqueda)
{
    $url = "?pagina=" . $num_pag;
    if (!empty($cat))
        $url .= "&categoria=" . urlencode($cat);
    if (!empty($busqueda))
        $url .= "&busqueda=" . urlencode($busqueda);
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

    // AQUI ESTA LA MAGIA DEL GRID DE BOOTSTRAP
    echo '<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3 g-lg-4 animate-up">';

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $portada_html = !empty($row['portada'])
                ? '<img src="uploads/' . htmlspecialchars($row['portada']) . '" alt="Portada" class="book-cover">'
                : '<div class="book-cover-placeholder d-flex align-items-center justify-content-center"><i class="bi bi-book text-muted" style="font-size: 3rem; opacity:0.5;"></i></div>';

            $cat_badge = !empty($row['categoria'])
                ? '<span class="badge badge-custom mb-2">' . htmlspecialchars($row['categoria']) . '</span>'
                : '';

            $admin_btns = '';
            if ($es_admin) {
                $admin_btns = '
                <div class="admin-actions">
                    <a href="edit.php?id=' . $row['id'] . '" class="btn-icon warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                    <a href="delete.php?id=' . $row['id'] . '" class="btn-icon danger" onclick="return confirm(\'¿Estás seguro?\');" title="Eliminar"><i class="bi bi-trash-fill"></i></a>
                </div>';
            }

            echo '
            <div class="col">
                <div class="card h-100 book-card border-0">
                    <div class="card-img-wrapper">
                        ' . $portada_html . '
                    </div>
                    
                    <div class="card-body d-flex flex-column p-4 text-center">
                        <div class="mb-2">' . $cat_badge . '</div>
                        <h6 class="card-title fw-bold text-dark mb-1 text-truncate-2" title="' . htmlspecialchars($row['titulo']) . '">' . htmlspecialchars($row['titulo']) . '</h6>
                        <p class="card-text text-muted small mb-3 text-truncate">' . htmlspecialchars($row['autor']) . '</p>
                        
                        <div class="mt-auto pt-3 border-top w-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="#" class="btn btn-pnp w-100 rounded-3 fw-bold view-pdf-btn" data-pdf-url="ver_pdf.php?archivo=' . $row['archivo'] . '">
                                    Leer Libro
                                </a>
                                ' . $admin_btns . '
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
        $url_prev = ($pagina_actual > 1) ? url_pag($pagina_actual - 1, $categoria_seleccionada, $termino_busqueda) : '#';
        echo '<li class="page-item ' . $class_prev . '"><a class="page-link ajax-link" href="' . $url_prev . '"><i class="bi bi-chevron-left"></i></a></li>';

        for ($i = 1; $i <= $total_paginas; $i++) {
            $active = ($pagina_actual == $i) ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><a class="page-link ajax-link" href="' . url_pag($i, $categoria_seleccionada, $termino_busqueda) . '">' . $i . '</a></li>';
        }

        $class_next = ($pagina_actual >= $total_paginas) ? 'disabled' : '';
        $url_next = ($pagina_actual < $total_paginas) ? url_pag($pagina_actual + 1, $categoria_seleccionada, $termino_busqueda) : '#';
        echo '<li class="page-item ' . $class_next . '"><a class="page-link ajax-link" href="' . $url_next . '"><i class="bi bi-chevron-right"></i></a></li>';
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

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
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
                        <a href="usuarios.php" class="btn nav-btn-custom rounded-pill fw-medium"><i
                                class="bi bi-people-fill me-2"></i>Usuarios</a>
                    <?php endif; ?>
                    <a href="https://enfpp.repositorio.pnp.edu.pe/" target="_blank"
                        class="btn nav-btn-custom rounded-pill fw-medium"><i
                            class="bi bi-globe me-2"></i>Repositorio</a>
                    <div class="user-info-mobile lh-sm me-2 ms-lg-2">
                        <div class="fw-bold fs-6 text-white text-mobile-dark">Hola,
                            <?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <small class="opacity-75 text-white text-mobile-dark"
                            style="font-size: 0.8rem;">(<?php echo ucfirst($_SESSION['rol']); ?>)</small>
                    </div>
                    <a href="logout.php" class="btn btn-logout rounded-pill fw-bold"><i
                            class="bi bi-box-arrow-right me-2"></i>Salir</a>
                    <img src="images/LOGO-EESTP.png" alt="EESTP" class="brand-logo d-none d-lg-block ms-3"
                        style="height: 55px;">
                </div>
            </div>
        </div>
    </nav>

    <div class="container pb-5" style="margin-top: 120px;">
        <?php if ($es_admin): ?>
            <div class="card shadow-sm border-0 mb-5 rounded-4 overflow-hidden animate-up">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold text-dark"><i class="bi bi-cloud-plus-fill me-2 text-pnp"></i>Gestión de Libros</h5>
                </div>
                <div class="card-body p-4">
                    <form action="upload.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" class="form-control bg-light border-0 py-2"
                                    name="titulo" placeholder="Título" required></div>
                            <div class="col-md-6"><input type="text" class="form-control bg-light border-0 py-2"
                                    name="autor" placeholder="Autor" required></div>
                            <div class="col-md-4">
                                <select class="form-select bg-light border-0 py-2" name="categoria" required>
                                    <option value="" disabled selected>Categoría...</option>
                                    <option value="Orden y Seguridad">Orden y Seguridad</option>
                                    <option value="Investigación Criminal">Investigación Criminal</option>
                                    <option value="Complementos">Complementos</option>
                                </select>
                            </div>
                            <div class="col-md-4"><input type="file" class="form-control bg-light border-0" name="pdf_file"
                                    accept=".pdf" required></div>
                            <div class="col-md-4"><input type="file" class="form-control bg-light border-0"
                                    name="cover_image" accept="image/*"></div>
                            <div class="col-12 text-end mt-4"><button type="submit"
                                    class="btn btn-pnp rounded-pill px-5 fw-bold shadow-sm">Guardar Libro</button></div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="search-hero animate-up">
            <i class="bi bi-search search-icon ms-3"></i>
            <input type="text" id="searchInput" class="search-input"
                placeholder="Buscar por título, autor o palabra clave..."
                value="<?php echo htmlspecialchars($termino_busqueda); ?>" autocomplete="off">
            <button type="button" id="clearBtn" class="btn btn-link text-decoration-none text-muted p-0 me-2"
                style="display:none;"><i class="bi bi-x-circle-fill"></i></button>
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
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
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

                    <div id="pdf-loader"
                        class="position-absolute top-50 start-50 translate-middle text-center text-white">
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

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function (page) {
                canvasContainer.innerHTML = '';
                var viewport = page.getViewport({ scale: scale });
                var canvas = document.createElement('canvas');
                canvas.className = 'pdf-page-canvas';
                var ctx = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvasContainer.appendChild(canvas);

                var renderContext = { canvasContext: ctx, viewport: viewport };
                var renderTask = page.render(renderContext);

                renderTask.promise.then(function () {
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

        document.addEventListener('keydown', function (e) {
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

        searchInput.addEventListener('input', function () { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => { realizarBusqueda(1); }, 250); });
        categoryFilter.addEventListener('change', () => realizarBusqueda(1));
        clearBtn.addEventListener('click', () => { searchInput.value = ''; realizarBusqueda(1); searchInput.focus(); });

        function initPaginationLinks() {
            document.querySelectorAll('.ajax-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    realizarBusqueda(new URLSearchParams(new URL(this.href).search).get('pagina') || 1);
                    document.getElementById('resultados_container').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        }

        const pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));

        function initPdfButtons() {
            document.querySelectorAll('.view-pdf-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    let url = this.getAttribute('data-pdf-url');
                    pdfModal.show();
                    document.getElementById('pdf-loader').style.display = 'block';
                    document.getElementById('pdfControls').style.display = 'none';
                    canvasContainer.style.opacity = '0';
                    canvasContainer.innerHTML = '';

                    pdfjsLib.getDocument(url).promise.then(function (pdfDoc_) {
                        pdfDoc = pdfDoc_;
                        document.getElementById('page-count').textContent = pdfDoc.numPages;
                        document.getElementById('pdf-loader').style.display = 'none';
                        document.getElementById('pdfControls').style.display = 'flex';
                        canvasContainer.style.opacity = '1';

                        pdfDoc.getPage(1).then(function (page) {
                            var containerWidth = canvasContainer.clientWidth - 40;
                            var unscaledViewport = page.getViewport({ scale: 1.0 });
                            scale = containerWidth / unscaledViewport.width;
                            if (scale > 1.5) scale = 1.5;
                            if (scale < 0.6) scale = 0.6;
                            pageNum = 1;
                            renderPage(pageNum);
                        });
                    }).catch(function (error) {
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
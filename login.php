<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM usuarios WHERE usuario = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows === 1) {
        $data = $result->fetch_assoc();
        if (password_verify($password, $data['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $data['id'];
            $_SESSION['username'] = $data['usuario'];
            $_SESSION['rol'] = $data['rol']; 
            header("Location: index.php");
            exit();
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "El usuario no existe.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso PNP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=60"> 
    
    <style>
        /* --- FONDO ANIMADO DE ALTO CONTRASTE --- */
        body.login-page {
            /* Secuencia: Negro -> Verde PNP -> Blanco -> Verde PNP -> Negro */
            background: linear-gradient(-45deg, #000000, #054a29, #ffffff, #054a29);
            background-size: 400% 400%;
            animation: gradientBG 12s ease infinite; /* 12 segundos por ciclo */
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* --- TARJETA DE LOGIN --- */
        .login-card {
            background: rgba(255, 255, 255, 0.98) !important; 
            backdrop-filter: blur(25px);
            /* Borde gris oscuro suave para que se vea cuando el fondo es blanco */
            border: 1px solid rgba(0, 0, 0, 0.1); 
            /* Sombra muy fuerte para que flote sobre el negro y el blanco */
            box-shadow: 0 30px 60px rgba(0,0,0,0.6); 
            border-radius: 20px;
            overflow: hidden; 
            max-width: 450px;
            width: 100%;
            transition: transform 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
        }
        
        /* CABECERA: Fondo claro con borde verde */
        .login-card .card-header {
            background-color: #f1f8e9 !important; 
            background: linear-gradient(to bottom, #ffffff, #f1f8e9) !important;
            border-bottom: 4px solid #054a29; /* Verde Institucional Fuerte */
            padding-top: 2.5rem;
            padding-bottom: 2rem;
            text-align: center;
        }

        /* --- TIPOGRAFÍA --- */
        .login-card h4 {
            color: #034828 !important; 
            font-weight: 900 !important;
            text-transform: uppercase !important;
            letter-spacing: 1.5px;
            margin-bottom: 0.5rem !important;
            font-size: 1.8rem;
        }

        .login-card small {
            color: #1b5e20 !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 0.75rem !important;
            letter-spacing: 0.5px;
            line-height: 1.4;
        }
        
        .login-card label {
            color: #333 !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        /* Campos de texto */
        .form-control {
            border: 2px solid #e0e0e0;
            padding: 12px;
            font-weight: 600;
            background-color: #f9f9f9;
            transition: all 0.3s;
        }
        .form-control:focus {
            background-color: #fff;
            border-color: #0a6b3d;
            box-shadow: 0 0 0 4px rgba(10, 107, 61, 0.15);
        }

        /* BOTÓN VIBRANTE */
        .btn-ingreso {
            background: linear-gradient(45deg, #000000, #054a29, #0a6b3d);
            background-size: 200% auto;
            border: none;
            color: white;
            padding: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.4s ease;
        }

        .btn-ingreso:hover {
            background-position: right center;
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(5, 74, 41, 0.6);
        }
    </style>
</head>
<body class="login-page">
    <div class="card login-card">
        <div class="card-header">
            <!-- LOGO OFICIAL -->
            <img src="images/logo-pnp.png" alt="Escudo PNP" style="height: 100px; margin-bottom: 15px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));">
            
            <!-- Título -->
            <h4>Acceso Biblioteca</h4>
            
            <!-- Subtítulo -->
            <small class="d-block mt-2">
                Escuela de Educación Superior Técnico Profesional PNP - Tarapoto
            </small>
        </div>
        <div class="card-body pt-4 px-5 pb-5">
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm text-center py-2 mb-3 text-uppercase fw-bold" style="font-size: 0.85rem;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" class="form-control" name="username" required autofocus placeholder="INGRESE SU USUARIO">
                </div>
                <div class="mb-4">
                    <label class="form-label">Contraseña</label>
                    <!-- Input Group con Ojo -->
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" id="passwordInput" required placeholder="INGRESE SU CONTRASEÑA" style="border-right: 0;">
                        <button class="btn btn-outline-secondary bg-white border-start-0 border-2" style="border-color: #e0e0e0;" type="button" id="togglePassword">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-ingreso btn-lg">
                        Ingresar al Sistema
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Script del Ojo
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#passwordInput');
        const eyeIcon = document.querySelector('#eyeIcon');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'text') {
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>
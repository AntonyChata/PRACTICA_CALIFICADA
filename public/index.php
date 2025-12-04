<?php
require_once "../config/db.php";
require_once "../includes/auth.php";

$mensajeError = "";
$mensajeExito = "";
$accion = $_GET['accion'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($accion === 'login') {
        // PROCESO DE LOGIN
        if (!esCorreoInstitucional($email)) {
            $mensajeError = "Solo se permite correo institucional @upt.pe o @virtual.upt.pe";
        } else {
            $usuario = autenticarUsuario($email, $password, $pdo);
            if ($usuario) {
                $_SESSION['usuario'] = $usuario['email'];
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_rol'] = $usuario['rol'];
                
                // Redirigir usando la función de auth.php
                redirigirSegunRol();
            } else {
                $mensajeError = "Correo o contraseña incorrectos.";
            }
        }
    } elseif ($accion === 'registro') {
        // PROCESO DE REGISTRO
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $rol = $_POST['rol'] ?? '';
        $confirmar_password = $_POST['confirmar_password'] ?? '';
        
        if (!esCorreoInstitucional($email)) {
            $mensajeError = "Solo se permite correo institucional @upt.pe o @virtual.upt.pe";
        } elseif (empty($nombres) || empty($apellidos) || empty($password) || empty($confirmar_password)) {
            $mensajeError = "Todos los campos son obligatorios.";
        } elseif ($password !== $confirmar_password) {
            $mensajeError = "Las contraseñas no coinciden.";
        } elseif (strlen($password) < 6) {
            $mensajeError = "La contraseña debe tener al menos 6 caracteres.";
        } elseif (!in_array($rol, ['administrador', 'docente', 'estudiante'])) {
            $mensajeError = "Debe seleccionar un rol válido.";
        } else {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $mensajeError = "Ya existe una cuenta con este correo electrónico.";
            } else {
                try {
                    // Crear usuario
                    if (registrarUsuario($email, $password, $rol, $pdo)) {
                        // Crear perfil completo según el rol
                        if ($rol === 'docente') {
                            $especialidad = trim($_POST['especialidad'] ?? 'Por definir');
                            $codigo_auto = 'DOC' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                            $stmt_docente = $pdo->prepare("INSERT INTO docentes (codigo, apellidos, nombres, email, especialidad) VALUES (?, ?, ?, ?, ?)");
                            $stmt_docente->execute([$codigo_auto, $apellidos, $nombres, $email, $especialidad ?: 'Por definir']);
                        } elseif ($rol === 'estudiante') {
                            $carrera = trim($_POST['carrera'] ?? 'Por definir');
                            $semestre = $_POST['semestre'] ?? 1;
                            $codigo_auto = date('Y') . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                            $stmt_estudiante = $pdo->prepare("INSERT INTO estudiantes (codigo, apellidos, nombres, email, carrera, semestre) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt_estudiante->execute([$codigo_auto, $apellidos, $nombres, $email, $carrera ?: 'Por definir', $semestre]);
                        }
                        
                        $mensajeExito = "Usuario y perfil registrados exitosamente. Ahora puede iniciar sesión.";
                        $accion = 'login';
                    } else {
                        $mensajeError = "Error al registrar usuario. Intente nuevamente.";
                    }
                } catch (Exception $e) {
                    $mensajeError = "Error al crear el perfil: " . $e->getMessage();
                }
            }
        }
    }
}

// Mensaje de error por acceso denegado
if (isset($_GET['error']) && $_GET['error'] === 'acceso_denegado') {
    $mensajeError = "No tiene permisos para acceder a esa sección.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $accion === 'login' ? 'Login' : 'Registro' ?> - Sistema de Consejería</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.8;
            font-size: 0.9em;
        }
        
        .tabs {
            display: flex;
            background: #ecf0f1;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .tab.active {
            background: white;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
        }
        
        .tab:not(.active) {
            background: #bdc3c7;
            color: white;
        }
        
        .tab:hover:not(.active) {
            background: #95a5a6;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        
        .role-option {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .role-option:hover {
            border-color: #3498db;
        }
        
        .role-option.selected {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .role-option input {
            display: none;
        }
        
        .role-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .role-desc {
            font-size: 0.85em;
            opacity: 0.8;
        }
        
        .form-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        @media (max-width: 480px) {
            .container {
                margin: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Sistema de Consejería</h1>
            <p>Universidad Privada de Tacna</p>
        </div>
        
        <div class="tabs">
            <div class="tab <?= $accion === 'login' ? 'active' : '' ?>" onclick="cambiarTab('login')">
                Iniciar Sesión
            </div>
            <div class="tab <?= $accion === 'registro' ? 'active' : '' ?>" onclick="cambiarTab('registro')">
                Registrarse
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($mensajeError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>
            
            <?php if ($mensajeExito): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            
            <?php if ($accion === 'login'): ?>
                <!-- FORMULARIO DE LOGIN -->
                <form method="POST" action="?accion=login">
                    <div class="form-group">
                        <label>Correo Institucional</label>
                        <input type="email" name="email" placeholder="usuario@upt.pe" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                </form>
                
            <?php else: ?>
                <!-- FORMULARIO DE REGISTRO -->
                <form method="POST" action="?accion=registro">
                    <div class="form-group">
                        <label>Nombres</label>
                        <input type="text" name="nombres" placeholder="Ej: Carlos Alberto" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Apellidos</label>
                        <input type="text" name="apellidos" placeholder="Ej: Lanchipa Quispe" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Correo Institucional</label>
                        <input type="email" name="email" placeholder="usuario@upt.pe" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Contraseña (mínimo 6 caracteres)</label>
                        <input type="password" name="password" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmar Contraseña</label>
                        <input type="password" name="confirmar_password" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Seleccione su Rol</label>
                        <div class="role-selector">
                            <label class="role-option" onclick="seleccionarRol(this, 'administrador')">
                                <input type="radio" name="rol" value="administrador" required>
                                <div class="role-title">👨‍💼 Administrador</div>
                                <div class="role-desc">Acceso completo al sistema</div>
                            </label>
                            
                            <label class="role-option" onclick="seleccionarRol(this, 'docente')">
                                <input type="radio" name="rol" value="docente" required>
                                <div class="role-title">👨‍🏫 Docente</div>
                                <div class="role-desc">Registrar y gestionar atenciones</div>
                            </label>
                            
                            <label class="role-option" onclick="seleccionarRol(this, 'estudiante')">
                                <input type="radio" name="rol" value="estudiante" required>
                                <div class="role-title">👨‍🎓 Estudiante</div>
                                <div class="role-desc">Ver sus atenciones y solicitar citas</div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Campos específicos para docente -->
                    <div class="form-group" id="campos-docente" style="display: none;">
                        <label>Especialidad (opcional)</label>
                        <input type="text" name="especialidad" placeholder="Ej: Ingeniería de Sistemas">
                    </div>
                    
                    <!-- Campos específicos para estudiante -->
                    <div class="form-group" id="campos-estudiante" style="display: none;">
                        <label>Carrera (opcional)</label>
                        <input type="text" name="carrera" placeholder="Ej: Ingeniería Industrial">
                        <label style="margin-top: 10px;">Semestre (opcional)</label>
                        <select name="semestre">
                            <option value="1">1er Semestre</option>
                            <option value="2">2do Semestre</option>
                            <option value="3">3er Semestre</option>
                            <option value="4">4to Semestre</option>
                            <option value="5" selected>5to Semestre</option>
                            <option value="6">6to Semestre</option>
                            <option value="7">7mo Semestre</option>
                            <option value="8">8vo Semestre</option>
                            <option value="9">9no Semestre</option>
                            <option value="10">10mo Semestre</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Registrarse</button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="form-footer">
            <strong>Usuarios de Prueba:</strong><br>
            Admin: admin@upt.pe | Docente: mgarcia@upt.pe | Estudiante: jquispe@virtual.upt.pe<br>
            <small>Contraseña: 123456</small>
        </div>
    </div>

    <script>
        function cambiarTab(accion) {
            window.location.href = '?accion=' + accion;
        }
        
        function seleccionarRol(elemento, rol) {
            // Limpiar selección anterior
            document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('selected'));
            
            // Seleccionar nuevo rol
            elemento.classList.add('selected');
            elemento.querySelector('input').checked = true;
            
            // Mostrar campos específicos según el rol
            document.getElementById('campos-docente').style.display = 'none';
            document.getElementById('campos-estudiante').style.display = 'none';
            
            if (rol === 'docente') {
                document.getElementById('campos-docente').style.display = 'block';
            } else if (rol === 'estudiante') {
                document.getElementById('campos-estudiante').style.display = 'block';
            }
        }
    </script>
</body>
</html>

<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

requiereRol('administrador');

$mensajeError = "";
$mensajeExito = "";

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'] ?? '';
        
        if (!esCorreoInstitucional($email)) {
            $mensajeError = "Solo se permite correo institucional @upt.pe o @virtual.upt.pe";
        } elseif (empty($password) || strlen($password) < 6) {
            $mensajeError = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            try {
                if (registrarUsuario($email, $password, $rol, $pdo)) {
                    // Crear automáticamente el registro en docentes o estudiantes si no existe
                    if ($rol === 'docente') {
                        $check_docente = $pdo->prepare("SELECT id FROM docentes WHERE email = ?");
                        $check_docente->execute([$email]);
                        if (!$check_docente->fetch()) {
                            // Extraer nombres del email para crear un registro básico
                            $nombre_base = explode('@', $email)[0];
                            $codigo_auto = 'DOC' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                            
                            $stmt_docente = $pdo->prepare("INSERT INTO docentes (codigo, apellidos, nombres, email, especialidad) VALUES (?, ?, ?, ?, ?)");
                            $stmt_docente->execute([$codigo_auto, $nombre_base, 'Sin especificar', $email, 'Por definir']);
                        }
                    } elseif ($rol === 'estudiante') {
                        $check_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE email = ?");
                        $check_estudiante->execute([$email]);
                        if (!$check_estudiante->fetch()) {
                            // Extraer nombres del email para crear un registro básico
                            $nombre_base = explode('@', $email)[0];
                            $codigo_auto = date('Y') . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                            
                            $stmt_estudiante = $pdo->prepare("INSERT INTO estudiantes (codigo, apellidos, nombres, email, carrera, semestre) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt_estudiante->execute([$codigo_auto, $nombre_base, 'Sin especificar', $email, 'Por definir', 1]);
                        }
                    }
                    
                    $mensajeExito = "Usuario y perfil creados exitosamente. Complete los datos adicionales desde la gestión correspondiente.";
                } else {
                    $mensajeError = "Error al crear el usuario. El email puede estar ya registrado.";
                }
            } catch (Exception $e) {
                $mensajeError = "Error al crear el usuario: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $usuario_id = $_POST['usuario_id'] ?? '';
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
            if ($stmt->execute([$nuevo_estado, $usuario_id])) {
                $mensajeExito = "Estado del usuario actualizado.";
            } else {
                $mensajeError = "Error al actualizar el estado del usuario.";
            }
        } catch (Exception $e) {
            $mensajeError = "Error: " . $e->getMessage();
        }
    } elseif ($accion === 'eliminar') {
        $usuario_id = $_POST['usuario_id'] ?? '';
        
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND id != ?");
            if ($stmt->execute([$usuario_id, $_SESSION['usuario_id']])) {
                $mensajeExito = "Usuario eliminado exitosamente.";
            } else {
                $mensajeError = "No se puede eliminar el usuario o no existe.";
            }
        } catch (Exception $e) {
            $mensajeError = "Error al eliminar: " . $e->getMessage();
        }
    }
}

// Obtener usuarios
try {
    $stmt = $pdo->query("
        SELECT id, email, rol, activo, 
               DATE_FORMAT(fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_format
        FROM usuarios 
        ORDER BY rol, email
    ");
    $usuarios = $stmt->fetchAll();
    
    // Estadísticas
    $stats = [
        'total' => count($usuarios),
        'activos' => count(array_filter($usuarios, fn($u) => $u['activo'])),
        'administradores' => count(array_filter($usuarios, fn($u) => $u['rol'] === 'administrador')),
        'docentes' => count(array_filter($usuarios, fn($u) => $u['rol'] === 'docente')),
        'estudiantes' => count(array_filter($usuarios, fn($u) => $u['rol'] === 'estudiante'))
    ];
} catch (Exception $e) {
    $usuarios = [];
    $stats = ['total' => 0, 'activos' => 0, 'administradores' => 0, 'docentes' => 0, 'estudiantes' => 0];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Administrador</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #c0392b 0%, #8e44ad 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .nav-links {
            margin-top: 20px;
        }
        
        .nav-links a {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .nav-links a:hover {
            background: #2980b9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #e74c3c;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 5px;
            font-size: 1em;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #e74c3c;
        }
        
        .btn {
            background: #e74c3c;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn:hover {
            background: #c0392b;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 0.9em;
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .status-active {
            background: #d5edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .role-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .role-administrador {
            background: #f8d7da;
            color: #721c24;
        }
        
        .role-docente {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .role-estudiante {
            background: #d4edda;
            color: #155724;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Gestión de Usuarios</h1>
            <p>Administración completa de cuentas del sistema</p>
            <div class="nav-links">
                <a href="dashboard_administrador.php">🏠 Dashboard</a>
                <a href="lista_atenciones.php">📋 Atenciones</a>
                <a href="reportes.php">📊 Reportes</a>
                <a href="logout.php">🚪 Salir</a>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['activos'] ?></div>
                <div class="stat-label">Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['administradores'] ?></div>
                <div class="stat-label">Administradores</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['docentes'] ?></div>
                <div class="stat-label">Docentes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['estudiantes'] ?></div>
                <div class="stat-label">Estudiantes</div>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensajeExito): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensajeExito) ?></div>
        <?php endif; ?>
        
        <?php if ($mensajeError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($mensajeError) ?></div>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Formulario de Creación -->
            <div class="card">
                <h3>➕ Crear Nuevo Usuario</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="form-group">
                        <label for="email">Correo Electrónico *</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="usuario@upt.pe o usuario@virtual.upt.pe">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="rol">Rol *</label>
                        <select id="rol" name="rol" required>
                            <option value="">Seleccionar rol...</option>
                            <option value="administrador">🛡️ Administrador</option>
                            <option value="docente">👨‍🏫 Docente</option>
                            <option value="estudiante">👨‍🎓 Estudiante</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Crear Usuario</button>
                </form>
            </div>
            
            <!-- Lista de Usuarios -->
            <div class="card">
                <h3>📋 Lista de Usuarios Registrados</h3>
                
                <?php if (empty($usuarios)): ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                        No hay usuarios registrados en el sistema.
                    </p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($usuario['email']) ?></td>
                                        <td>
                                            <span class="role-badge role-<?= $usuario['rol'] ?>">
                                                <?= ucfirst($usuario['rol']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $usuario['activo'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td><?= $usuario['fecha_creacion_format'] ?></td>
                                        <td>
                                            <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                                <!-- Cambiar Estado -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                    <input type="hidden" name="nuevo_estado" value="<?= $usuario['activo'] ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-small btn-warning">
                                                        <?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?>
                                                    </button>
                                                </form>
                                                
                                                <!-- Eliminar -->
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('¿Está seguro de eliminar este usuario?')">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                    <button type="submit" class="btn btn-small btn-danger">Eliminar</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: #7f8c8d; font-size: 0.9em;">Usuario actual</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
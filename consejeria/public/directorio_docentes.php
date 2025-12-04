<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

requiereLogin();

$mensaje_error = "";
$mensaje_exito = "";

// Procesar formulario de nuevo docente (solo administradores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_SESSION['usuario_rol'] === 'administrador') {
    try {
        if ($_POST['accion'] === 'editar') {
            $id = $_POST['id'];
            $apellidos = trim($_POST['apellidos']);
            $nombres = trim($_POST['nombres']);
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $especialidad = trim($_POST['especialidad'] ?? '');

            if (empty($apellidos) || empty($nombres)) {
                throw new Exception("Los apellidos y nombres son obligatorios.");
            }

            $sql = "UPDATE docentes SET apellidos = ?, nombres = ?, email = ?, telefono = ?, especialidad = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$apellidos, $nombres, $email, $telefono, $especialidad, $id]);
            
            $mensaje_exito = "Datos del docente actualizados exitosamente.";
            
        } elseif ($_POST['accion'] === 'agregar') {
            $codigo = trim($_POST['codigo']);
            $apellidos = trim($_POST['apellidos']);
            $nombres = trim($_POST['nombres']);
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $especialidad = trim($_POST['especialidad'] ?? '');

            if (empty($codigo) || empty($apellidos) || empty($nombres)) {
                throw new Exception("El código, apellidos y nombres son obligatorios.");
            }

            // Insertar docente
            $sql = "INSERT INTO docentes (codigo, apellidos, nombres, email, telefono, especialidad) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo, $apellidos, $nombres, $email, $telefono, $especialidad]);
            
            // Si tiene email, crear usuario automáticamente
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    // Verificar si ya existe el usuario
                    $check_user = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $check_user->execute([$email]);
                    
                    if (!$check_user->fetch()) {
                        // Crear usuario con contraseña por defecto
                        $password_hash = password_hash('123456', PASSWORD_DEFAULT);
                        $user_sql = "INSERT INTO usuarios (email, password, rol) VALUES (?, ?, 'docente')";
                        $user_stmt = $pdo->prepare($user_sql);
                        $user_stmt->execute([$email, $password_hash]);
                        $mensaje_exito = "Docente y usuario creados exitosamente. Contraseña por defecto: 123456";
                    } else {
                        $mensaje_exito = "Docente registrado exitosamente. El usuario ya existía.";
                    }
                } catch (Exception $user_e) {
                    $mensaje_exito = "Docente registrado exitosamente. No se pudo crear el usuario: " . $user_e->getMessage();
                }
            } else {
                $mensaje_exito = "Docente registrado exitosamente.";
            }
        }
    } catch (Exception $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Obtener lista de docentes
try {
    $stmt = $pdo->query("
        SELECT id, codigo, nombres, apellidos, especialidad, email, telefono
        FROM docentes 
        WHERE activo = 1
        ORDER BY apellidos, nombres
    ");
    $docentes = $stmt->fetchAll();
} catch (Exception $e) {
    $docentes = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Docentes - Consejería y Tutoría</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
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
        
        .docentes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .docente-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .docente-card:hover {
            transform: translateY(-5px);
        }
        
        .docente-nombre {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .docente-especialidad {
            background: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .docente-info {
            color: #7f8c8d;
            margin-bottom: 8px;
        }
        
        .docente-info strong {
            color: #2c3e50;
        }
        
        .no-data {
            text-align: center;
            color: #7f8c8d;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .docentes-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 Directorio de Docentes</h1>
            <p>Información de contacto de los docentes consejeros</p>
            <div class="nav-links">
                <?php if ($_SESSION['usuario_rol'] === 'estudiante'): ?>
                    <a href="dashboard_estudiante.php">🏠 Mi Dashboard</a>
                <?php elseif ($_SESSION['usuario_rol'] === 'docente'): ?>
                    <a href="dashboard_docente.php">🏠 Mi Dashboard</a>
                <?php else: ?>
                    <a href="dashboard_administrador.php">🏠 Dashboard</a>
                <?php endif; ?>
                <a href="lista_atenciones.php">📋 Atenciones</a>
                <a href="logout.php">🚪 Salir</a>
            </div>
        </div>

        <?php if (!empty($mensaje_error)): ?>
            <div style="background: #ff6b6b; color: white; padding: 15px; margin-bottom: 20px; border-radius: 10px; text-align: center;">
                ❌ <?= htmlspecialchars($mensaje_error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_exito)): ?>
            <div style="background: #51cf66; color: white; padding: 15px; margin-bottom: 20px; border-radius: 10px; text-align: center;">
                ✅ <?= htmlspecialchars($mensaje_exito) ?>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['usuario_rol'] === 'administrador'): ?>
        <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 30px;">
            <h2 style="color: #2c3e50; margin-bottom: 20px;">➕ Agregar Nuevo Docente</h2>
            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <input type="hidden" name="accion" value="agregar">
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50;">Código del Docente *</label>
                    <input type="text" name="codigo" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 16px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50;">Apellidos *</label>
                    <input type="text" name="apellidos" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 16px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50;">Nombres *</label>
                    <input type="text" name="nombres" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 16px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50;">Email Institucional</label>
                    <input type="email" name="email" placeholder="ejemplo@upt.pe" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 16px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50;">Teléfono</label>
                    <input type="tel" name="telefono" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 16px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50;">Especialidad</label>
                    <input type="text" name="especialidad" placeholder="Ej: Ingeniería de Sistemas" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 16px;">
                </div>
                
                <div style="grid-column: 1 / -1; text-align: center; margin-top: 20px;">
                    <button type="submit" style="background: #27ae60; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer;">
                        ➕ Agregar Docente
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if (empty($docentes)): ?>
            <div class="no-data">
                <h3>📭 Sin docentes registrados</h3>
                <p>Aún no hay docentes registrados en el sistema.</p>
            </div>
        <?php else: ?>
            <div class="docentes-grid">
                <?php foreach ($docentes as $docente): ?>
                    <div class="docente-card">
                        <div class="docente-nombre">
                            👨‍🏫 <?= htmlspecialchars($docente['nombres'] . ' ' . $docente['apellidos']) ?>
                        </div>
                        
                        <?php if ($docente['especialidad']): ?>
                            <div class="docente-especialidad">
                                <?= htmlspecialchars($docente['especialidad']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="docente-info">
                            <strong>📧 Email:</strong> <?= htmlspecialchars($docente['email']) ?>
                        </div>
                        
                        <?php if ($docente['telefono']): ?>
                            <div class="docente-info">
                                <strong>📱 Teléfono:</strong> <?= htmlspecialchars($docente['telefono']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['usuario_rol'] === 'administrador'): ?>
                            <div style="margin-top: 15px; text-align: center;">
                                <button onclick="editarDocente(<?= $docente['id'] ?>, '<?= htmlspecialchars($docente['nombres']) ?>', '<?= htmlspecialchars($docente['apellidos']) ?>', '<?= htmlspecialchars($docente['email']) ?>', '<?= htmlspecialchars($docente['telefono']) ?>', '<?= htmlspecialchars($docente['especialidad']) ?>')" 
                                        style="background: #3498db; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px;">
                                    ✏️ Editar
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de edición (solo para administradores) -->
    <?php if ($_SESSION['usuario_rol'] === 'administrador'): ?>
    <div id="modalEditar" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
            <h3 style="margin-bottom: 20px; color: #2c3e50;">✏️ Editar Datos del Docente</h3>
            
            <form method="POST" style="display: grid; gap: 15px;">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="edit-id">
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nombres *</label>
                    <input type="text" name="nombres" id="edit-nombres" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Apellidos *</label>
                    <input type="text" name="apellidos" id="edit-apellidos" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
                    <input type="email" name="email" id="edit-email" style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Teléfono</label>
                    <input type="tel" name="telefono" id="edit-telefono" style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Especialidad</label>
                    <input type="text" name="especialidad" id="edit-especialidad" style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" style="flex: 1; background: #27ae60; color: white; padding: 12px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer;">
                        💾 Guardar Cambios
                    </button>
                    <button type="button" onclick="cerrarModal()" style="flex: 1; background: #95a5a6; color: white; padding: 12px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer;">
                        ❌ Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function editarDocente(id, nombres, apellidos, email, telefono, especialidad) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-nombres').value = nombres;
            document.getElementById('edit-apellidos').value = apellidos;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-telefono').value = telefono;
            document.getElementById('edit-especialidad').value = especialidad;
            document.getElementById('modalEditar').style.display = 'block';
        }
        
        function cerrarModal() {
            document.getElementById('modalEditar').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera de él
        document.getElementById('modalEditar')?.addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
    </script>
</body>
</html>
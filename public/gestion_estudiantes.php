<?php
require_once "../config/db.php";
require_once "../includes/auth.php";

verificarLogin();

// Procesar formulario de nuevo estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'agregar') {
            $codigo = trim($_POST['codigo']);
            $apellidos = trim($_POST['apellidos']);
            $nombres = trim($_POST['nombres']);
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $carrera = trim($_POST['carrera'] ?? '');
            $semestre = $_POST['semestre'] ?? null;

            if (empty($codigo) || empty($apellidos) || empty($nombres)) {
                throw new Exception("El código, apellidos y nombres son obligatorios.");
            }

            // Insertar estudiante
            $sql = "INSERT INTO estudiantes (codigo, apellidos, nombres, email, telefono, carrera, semestre) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo, $apellidos, $nombres, $email, $telefono, $carrera, $semestre]);
            
            // Si tiene email, crear usuario automáticamente
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    // Verificar si ya existe el usuario
                    $check_user = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $check_user->execute([$email]);
                    
                    if (!$check_user->fetch()) {
                        // Crear usuario con contraseña por defecto
                        $password_hash = password_hash('123456', PASSWORD_DEFAULT);
                        $user_sql = "INSERT INTO usuarios (email, password, rol) VALUES (?, ?, 'estudiante')";
                        $user_stmt = $pdo->prepare($user_sql);
                        $user_stmt->execute([$email, $password_hash]);
                        $mensaje_exito = "Estudiante y usuario creados exitosamente. Contraseña por defecto: 123456";
                    } else {
                        $mensaje_exito = "Estudiante registrado exitosamente. El usuario ya existía.";
                    }
                } catch (Exception $user_e) {
                    $mensaje_exito = "Estudiante registrado exitosamente. No se pudo crear el usuario: " . $user_e->getMessage();
                }
            } else {
                $mensaje_exito = "Estudiante registrado exitosamente.";
            }
        }
    } catch (Exception $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Obtener lista de estudiantes con filtros
$filtro_carrera = $_GET['carrera'] ?? '';
$filtro_semestre = $_GET['semestre'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

$sql = "SELECT * FROM estudiantes WHERE activo = 1";
$params = [];

if (!empty($filtro_carrera)) {
    $sql .= " AND carrera LIKE ?";
    $params[] = "%$filtro_carrera%";
}

if (!empty($filtro_semestre)) {
    $sql .= " AND semestre = ?";
    $params[] = $filtro_semestre;
}

if (!empty($busqueda)) {
    $sql .= " AND (codigo LIKE ? OR apellidos LIKE ? OR nombres LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql .= " ORDER BY apellidos, nombres";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();

// Obtener estadísticas
$total_estudiantes = $pdo->query("SELECT COUNT(*) FROM estudiantes WHERE activo = 1")->fetchColumn();
$carreras_disponibles = $pdo->query("SELECT DISTINCT carrera FROM estudiantes WHERE activo = 1 AND carrera IS NOT NULL ORDER BY carrera")->fetchAll();
$semestres_disponibles = $pdo->query("SELECT DISTINCT semestre FROM estudiantes WHERE activo = 1 AND semestre IS NOT NULL ORDER BY semestre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Estudiantes - Consejería y Tutoría</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .content {
            background: white;
            padding: 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .tabs {
            display: flex;
            background: #ecf0f1;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: #bdc3c7;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .tab.active {
            background: white;
            border-bottom: 3px solid #3498db;
        }
        
        .tab:hover:not(.active) {
            background: #95a5a6;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
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
        
        .required {
            color: #e74c3c;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
            margin-right: 10px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .stats-bar {
            background: #e8f5e8;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            color: #27ae60;
            border-bottom: 1px solid #ddd;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        
        th {
            background: #34495e;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #3498db;
            color: white;
        }
        
        .navigation {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
            border-radius: 0 0 10px 10px;
        }
        
        .navigation a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .navigation a:hover {
            text-decoration: underline;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Gestión de Estudiantes</h1>
            <p>Sistema de Consejería y Tutoría Estudiantil</p>
        </div>
        
        <div class="content">
            <div class="tabs">
                <div class="tab active" onclick="showTab('lista')">📋 Lista de Estudiantes</div>
                <div class="tab" onclick="showTab('nuevo')">➕ Nuevo Estudiante</div>
            </div>
            
            <!-- Tab: Lista de Estudiantes -->
            <div id="tab-lista" class="tab-content active">
                <div class="stats-bar">
                    Total de estudiantes registrados: <?= $total_estudiantes ?>
                </div>
                
                <div class="filters">
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div>
                                <label>Buscar:</label>
                                <input type="text" name="busqueda" placeholder="Código, apellidos o nombres..." 
                                       value="<?= htmlspecialchars($busqueda) ?>">
                            </div>
                            
                            <div>
                                <label>Carrera:</label>
                                <select name="carrera">
                                    <option value="">Todas las carreras</option>
                                    <?php foreach ($carreras_disponibles as $carrera): ?>
                                        <option value="<?= htmlspecialchars($carrera['carrera']) ?>" 
                                                <?= $filtro_carrera == $carrera['carrera'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($carrera['carrera']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label>Semestre:</label>
                                <select name="semestre">
                                    <option value="">Todos los semestres</option>
                                    <?php foreach ($semestres_disponibles as $sem): ?>
                                        <option value="<?= $sem['semestre'] ?>" 
                                                <?= $filtro_semestre == $sem['semestre'] ? 'selected' : '' ?>>
                                            Semestre <?= $sem['semestre'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <button type="submit" class="btn">🔍 Buscar</button>
                                <a href="gestion_estudiantes.php" class="btn" style="background: #95a5a6;">Limpiar</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="table-container">
                    <?php if (empty($estudiantes)): ?>
                        <div class="no-data">
                            <h3>No se encontraron estudiantes</h3>
                            <p>No hay estudiantes registrados con los criterios de búsqueda aplicados.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Apellidos y Nombres</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Carrera</th>
                                    <th>Semestre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $estudiante): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($estudiante['codigo']) ?></strong></td>
                                        <td><?= htmlspecialchars($estudiante['apellidos'] . ', ' . $estudiante['nombres']) ?></td>
                                        <td><?= htmlspecialchars($estudiante['email'] ?: 'No registrado') ?></td>
                                        <td><?= htmlspecialchars($estudiante['telefono'] ?: 'No registrado') ?></td>
                                        <td><?= htmlspecialchars($estudiante['carrera'] ?: 'No especificada') ?></td>
                                        <td>
                                            <?php if ($estudiante['semestre']): ?>
                                                <span class="badge"><?= $estudiante['semestre'] ?></span>
                                            <?php else: ?>
                                                <span style="color: #7f8c8d;">No especificado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab: Nuevo Estudiante -->
            <div id="tab-nuevo" class="tab-content">
                <?php if (isset($mensaje_exito)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
                <?php endif; ?>
                
                <?php if (isset($mensaje_error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>
                
                <h3 style="margin-bottom: 20px; color: #2c3e50;">📝 Registrar Nuevo Estudiante</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Código de Estudiante <span class="required">*</span></label>
                            <input type="text" name="codigo" placeholder="Ej: 2024001234" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Apellidos <span class="required">*</span></label>
                            <input type="text" name="apellidos" placeholder="Apellidos completos" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nombres <span class="required">*</span></label>
                            <input type="text" name="nombres" placeholder="Nombres completos" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="estudiante@virtual.upt.pe">
                        </div>
                        
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="tel" name="telefono" placeholder="999 999 999">
                        </div>
                        
                        <div class="form-group">
                            <label>Carrera</label>
                            <select name="carrera">
                                <option value="">Seleccione una carrera</option>
                                <option value="Ingeniería de Sistemas">Ingeniería de Sistemas</option>
                                <option value="Ingeniería Industrial">Ingeniería Industrial</option>
                                <option value="Administración">Administración</option>
                                <option value="Contabilidad">Contabilidad</option>
                                <option value="Derecho">Derecho</option>
                                <option value="Arquitectura">Arquitectura</option>
                                <option value="Ingeniería Civil">Ingeniería Civil</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Semestre Actual</label>
                            <select name="semestre">
                                <option value="">Seleccione semestre</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?>° Semestre</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-success">💾 Registrar Estudiante</button>
                        <button type="reset" class="btn" style="background: #95a5a6;">🔄 Limpiar Formulario</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="navigation">
            <a href="dashboard.php">← Volver al Dashboard</a> | 
            <a href="registro_atencion.php">Nueva Atención</a> | 
            <a href="lista_atenciones.php">Ver Atenciones</a> | 
            <a href="reportes.php">Ver Reportes</a>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Ocultar todos los tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(button => button.classList.remove('active'));
            
            // Mostrar tab seleccionado
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

requiereRol('docente');

// Obtener información del docente logueado
$stmt = $pdo->prepare("SELECT d.* FROM docentes d INNER JOIN usuarios u ON d.email = u.email WHERE u.id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$docente = $stmt->fetch();

// Estadísticas del docente
$stmt = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE docente_id = ?");
$stmt->execute([$docente['id'] ?? 0]);
$mis_atenciones = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE docente_id = ? AND DATE(fecha_atencion) = CURDATE()");
$stmt->execute([$docente['id'] ?? 0]);
$atenciones_hoy = $stmt->fetchColumn();

$semestre_actual = date('Y') . '-' . (date('n') <= 7 ? '1' : '2');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE docente_id = ? AND semestre = ?");
$stmt->execute([$docente['id'] ?? 0, $semestre_actual]);
$atenciones_semestre = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Docente - Consejería y Tutoría</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5364 0%, #203a43 50%, #0f2027 100%);
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
        
        .user-info {
            background: #e67e22;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #e67e22;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: #e67e22;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .menu-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .menu-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .menu-card p {
            color: #7f8c8d;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .btn {
            background: #e67e22;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #d35400;
        }
        
        .footer {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .footer a {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border: 2px solid #e74c3c;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .footer a:hover {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍🏫 Panel de Docente</h1>
            <p>Sistema de Consejería y Tutoría Estudiantil</p>
            <div class="user-info">
                <strong>Bienvenido/a:</strong> <?= htmlspecialchars($docente['nombres'] ?? 'Docente') ?> <?= htmlspecialchars($docente['apellidos'] ?? '') ?>
                <br><small>Email: <?= htmlspecialchars($_SESSION['usuario']) ?> | Fecha: <?= date('d/m/Y H:i') ?></small>
            </div>
        </div>
        
        <!-- Estadísticas del Docente -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $mis_atenciones ?></div>
                <div class="stat-label">Mis Atenciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $atenciones_hoy ?></div>
                <div class="stat-label">Atenciones Hoy</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $atenciones_semestre ?></div>
                <div class="stat-label">Este Semestre</div>
            </div>
        </div>
        
        <!-- Menú para Docentes -->
        <div class="menu-grid">
            <div class="menu-card">
                <div class="menu-icon">📝</div>
                <h3>Registrar Nueva Atención</h3>
                <p>Registra una nueva sesión de consejería o tutoría con estudiantes bajo tu supervisión.</p>
                <a href="registro_atencion.php" class="btn">Nueva Atención</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">📋</div>
                <h3>Mis Atenciones</h3>
                <p>Consulta todas las atenciones que has registrado y filtra por fechas o tipos de consejería.</p>
                <a href="lista_atenciones.php?docente=<?= $docente['id'] ?? 0 ?>" class="btn">Ver Mis Atenciones</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">📊</div>
                <h3>Mis Estadísticas</h3>
                <p>Visualiza tus métricas de atención y reportes de tu actividad como consejero.</p>
                <a href="reportes_docente.php" class="btn">Ver Estadísticas</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">👥</div>
                <h3>Gestionar Estudiantes</h3>
                <p>Consulta la información de estudiantes y registra nuevos si es necesario.</p>
                <a href="gestion_estudiantes.php" class="btn">Gestionar</a>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin-bottom: 15px; color: #7f8c8d;">Panel exclusivo para docentes consejeros</p>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>
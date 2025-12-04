<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

// Redirigir al dashboard específico del administrador
header("Location: dashboard_administrador.php");
exit;

// Obtener estadísticas generales del sistema
try {
    $total_estudiantes = $pdo->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn();
    $total_docentes = $pdo->query("SELECT COUNT(*) FROM docentes")->fetchColumn();
    $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $total_atenciones = $pdo->query("SELECT COUNT(*) FROM atenciones")->fetchColumn();
    $atenciones_hoy = $pdo->query("SELECT COUNT(*) FROM atenciones WHERE DATE(fecha_atencion) = CURDATE()")->fetchColumn();
    
    $semestre_actual = date('Y') . '-' . (date('n') <= 7 ? '1' : '2');
    $stmt_semestre = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE semestre = ?");
    $stmt_semestre->execute([$semestre_actual]);
    $atenciones_semestre = $stmt_semestre->fetchColumn();
    
    // Obtener últimas atenciones para monitoreo
    $stmt = $pdo->query("
        SELECT a.*, 
               CONCAT(e.nombres, ' ', e.apellidos) as estudiante_nombre,
               e.codigo as estudiante_codigo,
               CONCAT(d.nombres, ' ', d.apellidos) as docente_nombre,
               t.nombre as tipo_nombre
        FROM atenciones a
        INNER JOIN estudiantes e ON a.estudiante_id = e.id
        INNER JOIN docentes d ON a.docente_id = d.id
        INNER JOIN tipos_consejeria t ON a.tipo_consejeria_id = t.id
        ORDER BY a.fecha_atencion DESC, a.hora_atencion DESC
        LIMIT 8
    ");
    $ultimas_atenciones = $stmt->fetchAll();
    
    // Estadísticas por tipo de consejería
    $stmt = $pdo->query("
        SELECT t.nombre, COUNT(a.id) as total
        FROM tipos_consejeria t
        LEFT JOIN atenciones a ON t.id = a.tipo_consejeria_id
        GROUP BY t.id, t.nombre
        ORDER BY total DESC
    ");
    $stats_tipos = $stmt->fetchAll();
    
} catch (Exception $e) {
    $total_estudiantes = 0;
    $total_docentes = 0;
    $total_usuarios = 0;
    $total_atenciones = 0;
    $atenciones_hoy = 0;
    $atenciones_semestre = 0;
    $ultimas_atenciones = [];
    $stats_tipos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Consejería y Tutoría</title>
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
        
        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        .user-info {
            background: #3498db;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: left;
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
            border-left: 5px solid #3498db;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.danger { border-left-color: #e74c3c; }
        
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-card.success .stat-number { color: #27ae60; }
        .stat-card.warning .stat-number { color: #f39c12; }
        .stat-card.danger .stat-number { color: #e74c3c; }
        
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
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: background 0.3s;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        
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
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Sistema de Consejería y Tutoría</h1>
            <p>Universidad Privada de Tacna</p>
            <div class="user-info">
                <strong>👋 Bienvenido/a:</strong> <?= htmlspecialchars($_SESSION['usuario']); ?>
                <br><small>Fecha: <?= date('d/m/Y H:i') ?></small>
            </div>
        </div>
        
        <!-- Estadísticas Rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_atenciones ?></div>
                <div class="stat-label">Total de Atenciones</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number"><?= $atenciones_hoy ?></div>
                <div class="stat-label">Atenciones Hoy</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-number"><?= $atenciones_semestre ?></div>
                <div class="stat-label">Semestre Actual</div>
            </div>
        </div>
        
        <!-- Menú Principal -->
        <div class="menu-grid">
            <div class="menu-card">
                <div class="menu-icon">📝</div>
                <h3>Registrar Nueva Atención</h3>
                <p>Registra una nueva sesión de consejería o tutoría con estudiantes. Incluye fecha, hora, tipo de consejería y detalles de la atención.</p>
                <a href="registro_atencion.php" class="btn btn-success">Nueva Atención</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">📋</div>
                <h3>Ver Atenciones Registradas</h3>
                <p>Consulta y filtra todas las atenciones registradas en el sistema. Busca por semestre, docente, tipo de consejería o fechas.</p>
                <a href="lista_atenciones.php" class="btn">Ver Lista</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">📊</div>
                <h3>Reportes y Estadísticas</h3>
                <p>Genera reportes estadísticos de atenciones por semestre, docente y tipo. Visualiza tendencias y métricas del sistema.</p>
                <a href="reportes.php" class="btn btn-warning">Ver Reportes</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">👥</div>
                <h3>Gestión de Estudiantes</h3>
                <p>Administra la información de estudiantes en el sistema. Consulta códigos, nombres y datos de contacto.</p>
                <a href="gestion_estudiantes.php" class="btn">Gestionar</a>
            </div>
        </div>
        
        <!-- Información del Sistema -->
        <div class="menu-card" style="margin-bottom: 30px;">
            <h3>📌 Información del Sistema</h3>
            <p><strong>Tipos de Consejería Disponibles:</strong></p>
            <ul style="text-align: left; margin-top: 15px; color: #7f8c8d;">
                <li>Asuntos relacionados con el plan de estudios</li>
                <li>Asuntos relacionados con el desarrollo profesional</li>
                <li>Asuntos relacionados con la inserción laboral</li>
                <li>Asuntos Académicos del Proceso de Plan de Tesis o Tesis</li>
                <li>Otros asuntos</li>
            </ul>
        </div>
        
        <div class="footer">
            <p style="margin-bottom: 15px; color: #7f8c8d;">Sistema desarrollado para el registro y seguimiento de atenciones de consejería estudiantil</p>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>

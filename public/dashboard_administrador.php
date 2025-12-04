<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

requiereRol('administrador');

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
    <title>Dashboard Administrador - Consejería y Tutoría</title>
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
        
        .user-info {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
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
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3em;
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
            grid-template-columns: 2fr 1fr;
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
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .menu-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .btn {
            background: #e74c3c;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #c0392b;
        }
        
        .atencion-item {
            padding: 15px;
            border-left: 4px solid #e74c3c;
            background: #f8f9fa;
            margin-bottom: 15px;
            border-radius: 0 5px 5px 0;
        }
        
        .atencion-fecha {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .atencion-estudiante {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .atencion-tipo {
            background: #e74c3c;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            display: inline-block;
        }
        
        .stats-item {
            padding: 10px;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .no-data {
            text-align: center;
            color: #7f8c8d;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
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
            <h1>🛡️ Panel de Administración</h1>
            <p>Sistema de Consejería y Tutoría Estudiantil</p>
            <div class="user-info">
                <strong>Acceso Completo de Administrador</strong>
                <br><small>Gestión total del sistema - Usuario: <?= htmlspecialchars($_SESSION['usuario_email'] ?? 'admin') ?></small>
            </div>
        </div>
        
        <!-- Estadísticas del Sistema -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_usuarios ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $total_estudiantes ?></div>
                <div class="stat-label">Estudiantes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $total_docentes ?></div>
                <div class="stat-label">Docentes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $total_atenciones ?></div>
                <div class="stat-label">Total Atenciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $atenciones_semestre ?></div>
                <div class="stat-label">Este Semestre</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $atenciones_hoy ?></div>
                <div class="stat-label">Hoy</div>
            </div>
        </div>
        
        <!-- Herramientas de Administración -->
        <div class="menu-grid">
            <div class="menu-card">
                <div class="menu-icon">👥</div>
                <h4>Gestión de Usuarios</h4>
                <p>Administrar cuentas de estudiantes, docentes y administradores del sistema.</p>
                <a href="gestion_usuarios.php" class="btn">Gestionar Usuarios</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">🛡️</div>
                <h4>Gestión de Atenciones</h4>
                <p>Aprobar, rechazar y gestionar solicitudes de atención pendientes.</p>
                <a href="gestion_atenciones.php" class="btn">Gestionar Solicitudes</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">📝</div>
                <h4>Registrar Atención</h4>
                <p>Crear nuevos registros de atención de consejería y tutoría.</p>
                <a href="registro_atencion.php" class="btn">Nueva Atención</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">📋</div>
                <h4>Lista de Atenciones</h4>
                <p>Ver y administrar todas las atenciones registradas en el sistema.</p>
                <a href="lista_atenciones.php" class="btn">Ver Atenciones</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">👨‍🎓</div>
                <h4>Gestión de Estudiantes</h4>
                <p>Administrar perfiles y datos de estudiantes.</p>
                <a href="gestion_estudiantes.php" class="btn">Gestionar Estudiantes</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">👨‍🏫</div>
                <h4>Gestión de Docentes</h4>
                <p>Administrar perfiles y asignaciones de docentes.</p>
                <a href="gestion_docentes.php" class="btn">Gestionar Docentes</a>
            </div>
            
            <div class="menu-card">
                <div class="menu-icon">📊</div>
                <h4>Reportes y Estadísticas</h4>
                <p>Generar reportes detallados del sistema.</p>
                <a href="reportes.php" class="btn">Ver Reportes</a>
            </div>
        </div>
        
        <!-- Monitoreo del Sistema -->
        <div class="content-grid">
            <div class="card">
                <h3>🔍 Actividad Reciente</h3>
                <?php if (empty($ultimas_atenciones)): ?>
                    <div class="no-data">
                        <p>No hay atenciones registradas en el sistema.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($ultimas_atenciones as $atencion): ?>
                        <div class="atencion-item">
                            <div class="atencion-fecha">
                                <?= date('d/m/Y H:i', strtotime($atencion['fecha_atencion'] . ' ' . $atencion['hora_atencion'])) ?>
                            </div>
                            <div class="atencion-estudiante">
                                Estudiante: <?= htmlspecialchars($atencion['estudiante_nombre']) ?> (<?= htmlspecialchars($atencion['estudiante_codigo']) ?>)
                                <br>Docente: <?= htmlspecialchars($atencion['docente_nombre']) ?>
                            </div>
                            <div class="atencion-tipo"><?= htmlspecialchars($atencion['tipo_nombre']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="lista_atenciones.php" class="btn">Ver Todas las Atenciones</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>📈 Estadísticas por Tipo</h3>
                <?php if (empty($stats_tipos)): ?>
                    <div class="no-data">
                        <p>No hay datos estadísticos disponibles.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($stats_tipos as $stat): ?>
                        <div class="stats-item">
                            <span><?= htmlspecialchars($stat['nombre']) ?></span>
                            <strong><?= $stat['total'] ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin-bottom: 15px; color: #7f8c8d;">Panel de Administración - Control Total del Sistema</p>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>
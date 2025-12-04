<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

requiereRol('estudiante');

// Obtener información del estudiante logueado
$stmt = $pdo->prepare("SELECT e.* FROM estudiantes e INNER JOIN usuarios u ON e.email = u.email WHERE u.id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$estudiante = $stmt->fetch();

// Estadísticas del estudiante
$stmt = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE estudiante_id = ?");
$stmt->execute([$estudiante['id'] ?? 0]);
$mis_atenciones = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(d.nombres, ' ', d.apellidos) as docente_nombre,
           t.nombre as tipo_nombre
    FROM atenciones a
    INNER JOIN docentes d ON a.docente_id = d.id
    INNER JOIN tipos_consejeria t ON a.tipo_consejeria_id = t.id
    WHERE a.estudiante_id = ?
    ORDER BY a.fecha_atencion DESC, a.hora_atencion DESC
    LIMIT 5
");
$stmt->execute([$estudiante['id'] ?? 0]);
$ultimas_atenciones = $stmt->fetchAll();

$semestre_actual = date('Y') . '-' . (date('n') <= 7 ? '1' : '2');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE estudiante_id = ? AND semestre = ?");
$stmt->execute([$estudiante['id'] ?? 0, $semestre_actual]);
$atenciones_semestre = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Estudiante - Consejería y Tutoría</title>
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
        
        .user-info {
            background: #3498db;
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
            border-left: 5px solid #3498db;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .atencion-item {
            padding: 15px;
            border-left: 4px solid #3498db;
            background: #f8f9fa;
            margin-bottom: 15px;
            border-radius: 0 5px 5px 0;
        }
        
        .atencion-fecha {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .atencion-docente {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .atencion-tipo {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            display: inline-block;
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
            font-size: 3em;
            margin-bottom: 15px;
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
            margin: 5px;
        }
        
        .btn:hover {
            background: #2980b9;
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
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍🎓 Portal del Estudiante</h1>
            <p>Sistema de Consejería y Tutoría Estudiantil</p>
            <div class="user-info">
                <strong>Bienvenido/a:</strong> <?= htmlspecialchars($estudiante['nombres'] ?? 'Estudiante') ?> <?= htmlspecialchars($estudiante['apellidos'] ?? '') ?>
                <br><small>Código: <?= htmlspecialchars($estudiante['codigo'] ?? 'N/A') ?> | Carrera: <?= htmlspecialchars($estudiante['carrera'] ?? 'No especificada') ?></small>
            </div>
        </div>
        
        <!-- Estadísticas del Estudiante -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $mis_atenciones ?></div>
                <div class="stat-label">Total Atenciones</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $atenciones_semestre ?></div>
                <div class="stat-label">Este Semestre</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $estudiante['semestre'] ?? 'N/A' ?></div>
                <div class="stat-label">Semestre Actual</div>
            </div>
        </div>
        
        <!-- Contenido Principal -->
        <div class="content-grid">
            <!-- Últimas Atenciones -->
            <div class="card">
                <h3>📋 Mis Últimas Atenciones</h3>
                <?php if (empty($ultimas_atenciones)): ?>
                    <div class="no-data">
                        <p>Aún no tienes atenciones registradas.</p>
                        <p><small>Las atenciones aparecerán aquí cuando los docentes las registren.</small></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($ultimas_atenciones as $atencion): ?>
                        <div class="atencion-item">
                            <div class="atencion-fecha">
                                <?= date('d/m/Y H:i', strtotime($atencion['fecha_atencion'] . ' ' . $atencion['hora_atencion'])) ?>
                            </div>
                            <div class="atencion-docente">
                                Docente: <?= htmlspecialchars($atencion['docente_nombre']) ?>
                            </div>
                            <div class="atencion-tipo"><?= htmlspecialchars($atencion['tipo_nombre']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="lista_atenciones.php?estudiante=<?= $estudiante['id'] ?? 0 ?>" class="btn">Ver Todas</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Acciones Disponibles -->
            <div class="card">
                <h3>🎯 Acciones Disponibles</h3>
                
                <div class="menu-card" style="margin-bottom: 20px;">
                    <div class="menu-icon">📅</div>
                    <h4>Solicitar Nueva Atención</h4>
                    <p>Agenda una nueva cita de consejería o tutoría con un docente.</p>
                    <a href="registro_atencion.php" class="btn">Nueva Solicitud</a>
                </div>
                
                <div class="menu-card" style="margin-bottom: 20px;">
                    <div class="menu-icon">📋</div>
                    <h4>Consultar Atenciones</h4>
                    <p>Ve el historial completo de tus atenciones de consejería y tutoría.</p>
                    <a href="lista_atenciones.php?estudiante=<?= $estudiante['id'] ?? 0 ?>" class="btn">Ver Atenciones</a>
                </div>
                
                <div class="menu-card" style="margin-bottom: 20px;">
                    <div class="menu-icon">📊</div>
                    <h4>Mis Estadísticas</h4>
                    <p>Visualiza tus métricas de atención por tipo y semestre.</p>
                    <a href="reportes.php?estudiante=<?= $estudiante['id'] ?? 0 ?>" class="btn">Ver Estadísticas</a>
                </div>
                
                <div class="menu-card">
                    <div class="menu-icon">📞</div>
                    <h4>Contactar Docentes</h4>
                    <p>Consulta la información de contacto de los docentes consejeros.</p>
                    <a href="directorio_docentes.php" class="btn">Ver Directorio</a>
                </div>
            </div>
        </div>
        
        <!-- Información Adicional -->
        <div class="card" style="margin-bottom: 30px;">
            <h3>ℹ️ Información del Sistema</h3>
            <p><strong>Tipos de Consejería Disponibles:</strong></p>
            <ul style="margin: 15px 0; padding-left: 20px; color: #7f8c8d;">
                <li>Asuntos relacionados con el plan de estudios</li>
                <li>Asuntos relacionados con el desarrollo profesional</li>
                <li>Asuntos relacionados con la inserción laboral</li>
                <li>Asuntos Académicos del Proceso de Plan de Tesis o Tesis</li>
                <li>Otros asuntos académicos</li>
            </ul>
            <p><small><strong>Nota:</strong> Para solicitar una cita de consejería, contacta directamente con tu docente consejero asignado.</small></p>
        </div>
        
        <div class="footer">
            <p style="margin-bottom: 15px; color: #7f8c8d;">Portal exclusivo para estudiantes</p>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>
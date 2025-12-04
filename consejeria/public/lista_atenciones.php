<?php
require_once "../config/db.php";
require_once "../includes/auth.php";

verificarLogin();

// Parámetros de filtrado
$filtro_semestre = $_GET['semestre'] ?? '';
$filtro_docente = $_GET['docente'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';

// Construir consulta SQL con filtros
$sql = "SELECT a.*, 
               CONCAT(d.apellidos, ', ', d.nombres) as docente_nombre,
               CONCAT(e.apellidos, ', ', e.nombres) as estudiante_nombre,
               e.codigo as estudiante_codigo,
               t.nombre as tipo_consejeria_nombre
        FROM atenciones a
        INNER JOIN docentes d ON a.docente_id = d.id
        INNER JOIN estudiantes e ON a.estudiante_id = e.id
        INNER JOIN tipos_consejeria t ON a.tipo_consejeria_id = t.id
        WHERE 1=1";

$params = [];

if (!empty($filtro_semestre)) {
    $sql .= " AND a.semestre = ?";
    $params[] = $filtro_semestre;
}

if (!empty($filtro_docente)) {
    $sql .= " AND a.docente_id = ?";
    $params[] = $filtro_docente;
}

if (!empty($filtro_tipo)) {
    $sql .= " AND a.tipo_consejeria_id = ?";
    $params[] = $filtro_tipo;
}

if (!empty($filtro_fecha_inicio)) {
    $sql .= " AND a.fecha_atencion >= ?";
    $params[] = $filtro_fecha_inicio;
}

if (!empty($filtro_fecha_fin)) {
    $sql .= " AND a.fecha_atencion <= ?";
    $params[] = $filtro_fecha_fin;
}

$sql .= " ORDER BY a.fecha_atencion DESC, a.hora_atencion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atenciones = $stmt->fetchAll();

// Obtener datos para filtros
$semestres = $pdo->query("SELECT DISTINCT semestre FROM atenciones ORDER BY semestre DESC")->fetchAll();
$docentes = $pdo->query("SELECT id, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM docentes WHERE activo = 1 ORDER BY apellidos")->fetchAll();
$tipos_consejeria = $pdo->query("SELECT id, nombre FROM tipos_consejeria WHERE activo = 1 ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Atenciones - Consejería y Tutoría</title>
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
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .filters {
            padding: 20px;
            background: #ecf0f1;
            border-bottom: 1px solid #ddd;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .stats {
            padding: 15px 20px;
            background: #e8f5e8;
            border-bottom: 1px solid #ddd;
            text-align: center;
            font-weight: 600;
            color: #27ae60;
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
        }
        
        .badge-primary { background: #e3f2fd; color: #1976d2; }
        .badge-success { background: #e8f5e8; color: #388e3c; }
        .badge-warning { background: #fff3e0; color: #f57c00; }
        .badge-info { background: #f3e5f5; color: #7b1fa2; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #f5f5f5; color: #616161; }
        
        .text-truncate {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .navigation {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
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
        
        /* Estilos para evidencia */
        .evidencia-cell {
            text-align: center;
            min-width: 100px;
        }
        
        .file-link {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .file-image {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .file-image:hover {
            background: #c8e6c9;
        }
        
        .file-pdf {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .file-pdf:hover {
            background: #ffcdd2;
        }
        
        .file-doc {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .file-doc:hover {
            background: #bbdefb;
        }
        
        .file-excel {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .file-excel:hover {
            background: #c8e6c9;
        }
        
        .file-other {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .file-other:hover {
            background: #e1bee7;
        }
        
        .evidencia-text {
            font-size: 12px;
            color: #666;
            cursor: help;
        }
        
        .no-evidencia {
            font-size: 12px;
            color: #999;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                margin: 10px;
            }
            
            th, td {
                padding: 8px 4px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lista de Atenciones</h1>
            <p>Sistema de Consejería y Tutoría Estudiantil</p>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Semestre</label>
                        <select name="semestre">
                            <option value="">Todos los semestres</option>
                            <?php foreach ($semestres as $sem): ?>
                                <option value="<?= htmlspecialchars($sem['semestre']) ?>" 
                                        <?= $filtro_semestre == $sem['semestre'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sem['semestre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Docente</label>
                        <select name="docente">
                            <option value="">Todos los docentes</option>
                            <?php foreach ($docentes as $docente): ?>
                                <option value="<?= $docente['id'] ?>" 
                                        <?= $filtro_docente == $docente['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($docente['nombre_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Tipo de Consejería</label>
                        <select name="tipo">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_consejeria as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" 
                                        <?= $filtro_tipo == $tipo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($filtro_fecha_inicio) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Fecha Fin</label>
                        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($filtro_fecha_fin) ?>">
                    </div>
                    
                    <div class="filter-group" style="display: flex; align-items: end; gap: 10px;">
                        <button type="submit" class="btn">Filtrar</button>
                        <a href="lista_atenciones.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="stats">
            Total de atenciones encontradas: <?= count($atenciones) ?>
        </div>
        
        <div class="table-container">
            <?php if (empty($atenciones)): ?>
                <div class="no-data">
                    <h3>No se encontraron atenciones</h3>
                    <p>No hay atenciones registradas con los filtros aplicados.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha/Hora</th>
                            <th>Semestre</th>
                            <th>Estado</th>
                            <th>Estudiante</th>
                            <th>Docente</th>
                            <th>Tipo</th>
                            <th>Consulta</th>
                            <th>Atención</th>
                            <th>Evidencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($atenciones as $atencion): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($atencion['fecha_atencion'])) ?></strong><br>
                                    <small><?= date('H:i', strtotime($atencion['hora_atencion'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?= htmlspecialchars($atencion['semestre']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    $estado_icon = '';
                                    switch($atencion['estado'] ?? 'pendiente') {
                                        case 'pendiente':
                                            $estado_class = 'badge-warning';
                                            $estado_icon = '⏳';
                                            break;
                                        case 'aprobada':
                                            $estado_class = 'badge-success';
                                            $estado_icon = '✅';
                                            break;
                                        case 'rechazada':
                                            $estado_class = 'badge-danger';
                                            $estado_icon = '❌';
                                            break;
                                        case 'completada':
                                            $estado_class = 'badge-info';
                                            $estado_icon = '🎯';
                                            break;
                                        default:
                                            $estado_class = 'badge-secondary';
                                            $estado_icon = '❓';
                                    }
                                    ?>
                                    <span class="badge <?= $estado_class ?>" title="Estado: <?= ucfirst($atencion['estado'] ?? 'pendiente') ?>">
                                        <?= $estado_icon ?> <?= ucfirst($atencion['estado'] ?? 'Pendiente') ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($atencion['estudiante_codigo']) ?></strong><br>
                                    <small><?= htmlspecialchars($atencion['estudiante_nombre']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($atencion['docente_nombre']) ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-secondary';
                                    if (strpos($atencion['tipo_consejeria_nombre'], 'plan de estudios') !== false) {
                                        $badge_class = 'badge-primary';
                                    } elseif (strpos($atencion['tipo_consejeria_nombre'], 'desarrollo profesional') !== false) {
                                        $badge_class = 'badge-success';
                                    } elseif (strpos($atencion['tipo_consejeria_nombre'], 'inserción laboral') !== false) {
                                        $badge_class = 'badge-warning';
                                    } elseif (strpos($atencion['tipo_consejeria_nombre'], 'Tesis') !== false) {
                                        $badge_class = 'badge-info';
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?>" title="<?= htmlspecialchars($atencion['tipo_consejeria_nombre']) ?>">
                                        <?= htmlspecialchars(substr($atencion['tipo_consejeria_nombre'], 0, 20)) ?>...
                                    </span>
                                </td>
                                <td class="text-truncate" title="<?= htmlspecialchars($atencion['consulta_estudiante']) ?>">
                                    <?= htmlspecialchars($atencion['consulta_estudiante']) ?>
                                </td>
                                <td class="text-truncate" title="<?= htmlspecialchars($atencion['descripcion_atencion']) ?>">
                                    <?= htmlspecialchars($atencion['descripcion_atencion']) ?>
                                </td>
                                <td class="evidencia-cell">
                                    <?php if (!empty($atencion['evidencia'])): ?>
                                        <?php if (preg_match('/^uploads\/evidencias\//', $atencion['evidencia'])): ?>
                                            <?php 
                                            $extension = strtolower(pathinfo($atencion['evidencia'], PATHINFO_EXTENSION));
                                            $nombre_archivo = basename($atencion['evidencia']);
                                            $icono = '';
                                            $clase = '';
                                            
                                            // Determinar icono según extensión
                                            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                $icono = '🖼️';
                                                $clase = 'file-image';
                                            } elseif ($extension == 'pdf') {
                                                $icono = '📄';
                                                $clase = 'file-pdf';
                                            } elseif (in_array($extension, ['doc', 'docx'])) {
                                                $icono = '📝';
                                                $clase = 'file-doc';
                                            } elseif (in_array($extension, ['xls', 'xlsx'])) {
                                                $icono = '📊';
                                                $clase = 'file-excel';
                                            } else {
                                                $icono = '📎';
                                                $clase = 'file-other';
                                            }
                                            ?>
                                            <a href="ver_archivo.php?file=<?= urlencode($nombre_archivo) ?>" 
                                               target="_blank" 
                                               class="file-link <?= $clase ?>"
                                               title="Ver archivo: <?= htmlspecialchars($nombre_archivo) ?>">
                                                <?= $icono ?> Archivo
                                            </a>
                                        <?php else: ?>
                                            <span class="evidencia-text" title="<?= htmlspecialchars($atencion['evidencia']) ?>">
                                                🔗 <?= htmlspecialchars(substr($atencion['evidencia'], 0, 30)) ?><?= strlen($atencion['evidencia']) > 30 ? '...' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-evidencia">Sin evidencia</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="navigation">
            <a href="dashboard.php">← Volver al Dashboard</a> | 
            <a href="registro_atencion.php">Nueva Atención</a> | 
            <a href="reportes.php">Ver Reportes</a>
        </div>
    </div>
</body>
</html>
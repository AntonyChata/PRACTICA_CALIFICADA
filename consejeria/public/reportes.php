<?php
require_once "../config/db.php";
require_once "../includes/auth.php";

verificarLogin();

// Obtener estadísticas generales
$stats = [];

// Total de atenciones
$stats['total_atenciones'] = $pdo->query("SELECT COUNT(*) FROM atenciones")->fetchColumn();

// Atenciones por semestre
$stats['por_semestre'] = $pdo->query("
    SELECT semestre, COUNT(*) as total 
    FROM atenciones 
    GROUP BY semestre 
    ORDER BY semestre DESC
")->fetchAll();

// Atenciones por docente
$stats['por_docente'] = $pdo->query("
    SELECT CONCAT(d.apellidos, ', ', d.nombres) as docente, COUNT(*) as total
    FROM atenciones a
    INNER JOIN docentes d ON a.docente_id = d.id
    GROUP BY a.docente_id, d.apellidos, d.nombres
    ORDER BY total DESC
")->fetchAll();

// Atenciones por tipo de consejería
$stats['por_tipo'] = $pdo->query("
    SELECT t.nombre as tipo, COUNT(*) as total
    FROM atenciones a
    INNER JOIN tipos_consejeria t ON a.tipo_consejeria_id = t.id
    GROUP BY a.tipo_consejeria_id, t.nombre
    ORDER BY total DESC
")->fetchAll();

// Atenciones por mes (últimos 12 meses)
$stats['por_mes'] = $pdo->query("
    SELECT 
        DATE_FORMAT(fecha_atencion, '%Y-%m') as mes,
        COUNT(*) as total
    FROM atenciones
    WHERE fecha_atencion >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fecha_atencion, '%Y-%m')
    ORDER BY mes DESC
")->fetchAll();

// Resumen por semestre, docente y tipo
$filtro_semestre = $_GET['semestre'] ?? '';
$sql_resumen = "
    SELECT 
        a.semestre,
        CONCAT(d.apellidos, ', ', d.nombres) as docente,
        t.nombre as tipo_consejeria,
        COUNT(*) as total_atenciones
    FROM atenciones a
    INNER JOIN docentes d ON a.docente_id = d.id
    INNER JOIN tipos_consejeria t ON a.tipo_consejeria_id = t.id";

if (!empty($filtro_semestre)) {
    $sql_resumen .= " WHERE a.semestre = ?";
    $stmt_resumen = $pdo->prepare($sql_resumen . " GROUP BY a.semestre, a.docente_id, a.tipo_consejeria_id ORDER BY a.semestre DESC, docente, tipo_consejeria");
    $stmt_resumen->execute([$filtro_semestre]);
} else {
    $stmt_resumen = $pdo->prepare($sql_resumen . " GROUP BY a.semestre, a.docente_id, a.tipo_consejeria_id ORDER BY a.semestre DESC, docente, tipo_consejeria");
    $stmt_resumen->execute();
}

$resumen_detallado = $stmt_resumen->fetchAll();

// Obtener lista de semestres para el filtro
$semestres = $pdo->query("SELECT DISTINCT semestre FROM atenciones ORDER BY semestre DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Estadísticos - Consejería y Tutoría</title>
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
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #3498db;
        }
        
        .stat-card.primary { border-left-color: #3498db; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.danger { border-left-color: #e74c3c; }
        
        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: bold;
            color: #3498db;
            text-align: center;
            margin: 20px 0;
        }
        
        .stat-list {
            list-style: none;
        }
        
        .stat-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .stat-list li:last-child {
            border-bottom: none;
        }
        
        .progress-bar {
            background: #ecf0f1;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: #3498db;
            transition: width 0.3s ease;
        }
        
        .badge {
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .filter-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
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
        
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #34495e;
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .navigation {
            background: white;
            padding: 20px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .navigation a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
        }
        
        .navigation a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Reportes Estadísticos</h1>
            <p>Sistema de Consejería y Tutoría Estudiantil</p>
        </div>
        
        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <h3>📈 Total de Atenciones</h3>
                <div class="stat-number"><?= $stats['total_atenciones'] ?></div>
                <p>Atenciones registradas en el sistema</p>
            </div>
            
            <div class="stat-card success">
                <h3>📅 Atenciones por Semestre</h3>
                <ul class="stat-list">
                    <?php 
                    $max_semestre = max(array_column($stats['por_semestre'], 'total'));
                    foreach (array_slice($stats['por_semestre'], 0, 5) as $sem): 
                        $percentage = $max_semestre > 0 ? ($sem['total'] / $max_semestre) * 100 : 0;
                    ?>
                        <li>
                            <div>
                                <strong><?= htmlspecialchars($sem['semestre']) ?></strong>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                            <span class="badge"><?= $sem['total'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="stat-card warning">
                <h3>👨‍🏫 Top Docentes</h3>
                <ul class="stat-list">
                    <?php 
                    $max_docente = max(array_column($stats['por_docente'], 'total'));
                    foreach (array_slice($stats['por_docente'], 0, 5) as $doc): 
                        $percentage = $max_docente > 0 ? ($doc['total'] / $max_docente) * 100 : 0;
                    ?>
                        <li>
                            <div>
                                <strong><?= htmlspecialchars($doc['docente']) ?></strong>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                            <span class="badge"><?= $doc['total'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="stat-card danger">
                <h3>📝 Tipos de Consejería</h3>
                <ul class="stat-list">
                    <?php 
                    $max_tipo = max(array_column($stats['por_tipo'], 'total'));
                    foreach ($stats['por_tipo'] as $tipo): 
                        $percentage = $max_tipo > 0 ? ($tipo['total'] / $max_tipo) * 100 : 0;
                    ?>
                        <li>
                            <div>
                                <strong><?= htmlspecialchars(substr($tipo['tipo'], 0, 30)) ?>...</strong>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                            <span class="badge"><?= $tipo['total'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <!-- Filtros para Resumen Detallado -->
        <div class="filter-section">
            <h3>🔍 Filtrar Resumen Detallado</h3>
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
                    
                    <div>
                        <button type="submit" class="btn">Aplicar Filtro</button>
                        <a href="reportes.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Resumen Detallado -->
        <div class="chart-container">
            <h3>📋 Resumen Detallado: Atenciones por Semestre, Docente y Tipo</h3>
            <?php if (!empty($filtro_semestre)): ?>
                <p><strong>Filtrado por semestre:</strong> <?= htmlspecialchars($filtro_semestre) ?></p>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Semestre</th>
                            <th>Docente</th>
                            <th>Tipo de Consejería</th>
                            <th>Total Atenciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resumen_detallado)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px;">
                                    No se encontraron datos con los filtros aplicados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resumen_detallado as $item): ?>
                                <tr>
                                    <td><span class="badge"><?= htmlspecialchars($item['semestre']) ?></span></td>
                                    <td><?= htmlspecialchars($item['docente']) ?></td>
                                    <td><?= htmlspecialchars($item['tipo_consejeria']) ?></td>
                                    <td><strong><?= $item['total_atenciones'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Tendencia por Meses -->
        <?php if (!empty($stats['por_mes'])): ?>
        <div class="chart-container">
            <h3>📈 Tendencia por Meses (Últimos 12 meses)</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Total Atenciones</th>
                            <th>Tendencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $max_mes = max(array_column($stats['por_mes'], 'total'));
                        foreach ($stats['por_mes'] as $mes): 
                            $percentage = $max_mes > 0 ? ($mes['total'] / $max_mes) * 100 : 0;
                            $fecha = DateTime::createFromFormat('Y-m', $mes['mes']);
                            $mes_nombre = $fecha ? $fecha->format('F Y') : $mes['mes'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($mes_nombre) ?></td>
                                <td><strong><?= $mes['total'] ?></strong></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="navigation">
            <a href="dashboard.php">← Volver al Dashboard</a>
            <a href="registro_atencion.php">Nueva Atención</a>
            <a href="lista_atenciones.php">Ver Atenciones</a>
            <a href="javascript:window.print()">🖨️ Imprimir Reporte</a>
        </div>
    </div>
</body>
</html>
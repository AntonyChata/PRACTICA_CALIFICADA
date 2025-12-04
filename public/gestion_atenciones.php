<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

requiereRol('administrador');

$mensajeError = "";
$mensajeExito = "";

// Procesar acciones de gestión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $atencion_id = $_POST['atencion_id'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    
    try {
        if ($accion === 'aprobar') {
            $stmt = $pdo->prepare("
                UPDATE atenciones 
                SET estado = 'aprobada', 
                    aprobada_por = ?, 
                    fecha_aprobacion = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['usuario_id'], $atencion_id]);
            $mensajeExito = "Atención aprobada exitosamente.";
            
        } elseif ($accion === 'rechazar') {
            if (empty($motivo)) {
                throw new Exception("Debe proporcionar un motivo para el rechazo.");
            }
            
            $stmt = $pdo->prepare("
                UPDATE atenciones 
                SET estado = 'rechazada', 
                    motivo_rechazo = ?, 
                    aprobada_por = ?, 
                    fecha_aprobacion = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$motivo, $_SESSION['usuario_id'], $atencion_id]);
            $mensajeExito = "Atención rechazada. Se notificará al solicitante.";
            
        } elseif ($accion === 'completar') {
            $stmt = $pdo->prepare("
                UPDATE atenciones 
                SET estado = 'completada' 
                WHERE id = ?
            ");
            $stmt->execute([$atencion_id]);
            $mensajeExito = "Atención marcada como completada.";
            
        } elseif ($accion === 'pendiente') {
            $stmt = $pdo->prepare("
                UPDATE atenciones 
                SET estado = 'pendiente', 
                    motivo_rechazo = NULL, 
                    aprobada_por = NULL, 
                    fecha_aprobacion = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$atencion_id]);
            $mensajeExito = "Atención regresada a estado pendiente.";
        }
    } catch (Exception $e) {
        $mensajeError = "Error: " . $e->getMessage();
    }
}

// Obtener filtros
$filtro_estado = $_GET['estado'] ?? '';

// Construir consulta con filtros
$sql = "SELECT a.*, 
               CONCAT(d.apellidos, ', ', d.nombres) as docente_nombre,
               CONCAT(e.apellidos, ', ', e.nombres) as estudiante_nombre,
               e.codigo as estudiante_codigo,
               t.nombre as tipo_consejeria_nombre,
               CONCAT(u.email) as aprobada_por_email
        FROM atenciones a
        INNER JOIN docentes d ON a.docente_id = d.id
        INNER JOIN estudiantes e ON a.estudiante_id = e.id
        INNER JOIN tipos_consejeria t ON a.tipo_consejeria_id = t.id
        LEFT JOIN usuarios u ON a.aprobada_por = u.id
        WHERE 1=1";

$params = [];

if (!empty($filtro_estado)) {
    $sql .= " AND a.estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY 
    CASE a.estado 
        WHEN 'pendiente' THEN 1
        WHEN 'aprobada' THEN 2
        WHEN 'completada' THEN 3
        WHEN 'rechazada' THEN 4
    END,
    a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atenciones = $stmt->fetchAll();

// Obtener estadísticas
$stats = [];
$estados = ['pendiente', 'aprobada', 'rechazada', 'completada'];
foreach ($estados as $estado) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM atenciones WHERE estado = ?");
    $stmt->execute([$estado]);
    $stats[$estado] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Atenciones - Administrador</title>
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
            border-left: 5px solid;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.pendiente { border-left-color: #f39c12; }
        .stat-card.aprobada { border-left-color: #27ae60; }
        .stat-card.rechazada { border-left-color: #e74c3c; }
        .stat-card.completada { border-left-color: #3498db; }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-card.pendiente .stat-number { color: #f39c12; }
        .stat-card.aprobada .stat-number { color: #27ae60; }
        .stat-card.rechazada .stat-number { color: #e74c3c; }
        .stat-card.completada .stat-number { color: #3498db; }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 5px;
            font-size: 1em;
        }
        
        .btn {
            background: #e74c3c;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #c0392b;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-info {
            background: #3498db;
        }
        
        .btn-info:hover {
            background: #2980b9;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 0.9em;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #34495e;
            color: white;
            padding: 20px;
        }
        
        .table-content {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pendiente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-aprobada {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rechazada {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-completada {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .actions-cell {
            white-space: nowrap;
        }
        
        .atencion-details {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .motivo-rechazo {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 0.9em;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal h3 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 5px;
            min-height: 100px;
            resize: vertical;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-cell {
                white-space: normal;
            }
            
            .btn-small {
                display: block;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛡️ Gestión de Atenciones</h1>
            <p>Panel de Control Administrativo - Aprobar/Rechazar Solicitudes</p>
            <div class="nav-links">
                <a href="dashboard_administrador.php">🏠 Dashboard</a>
                <a href="gestion_usuarios.php">👥 Usuarios</a>
                <a href="lista_atenciones.php">📋 Lista Completa</a>
                <a href="reportes.php">📊 Reportes</a>
            </div>
        </div>
        
        <!-- Estadísticas por Estado -->
        <div class="stats-grid">
            <div class="stat-card pendiente">
                <div class="stat-number"><?= $stats['pendiente'] ?></div>
                <div class="stat-label">⏳ Pendientes</div>
            </div>
            
            <div class="stat-card aprobada">
                <div class="stat-number"><?= $stats['aprobada'] ?></div>
                <div class="stat-label">✅ Aprobadas</div>
            </div>
            
            <div class="stat-card completada">
                <div class="stat-number"><?= $stats['completada'] ?></div>
                <div class="stat-label">🎯 Completadas</div>
            </div>
            
            <div class="stat-card rechazada">
                <div class="stat-number"><?= $stats['rechazada'] ?></div>
                <div class="stat-label">❌ Rechazadas</div>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensajeExito): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensajeExito) ?></div>
        <?php endif; ?>
        
        <?php if ($mensajeError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($mensajeError) ?></div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Filtrar por Estado</label>
                        <select name="estado" onchange="this.form.submit()">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendientes</option>
                            <option value="aprobada" <?= $filtro_estado === 'aprobada' ? 'selected' : '' ?>>✅ Aprobadas</option>
                            <option value="completada" <?= $filtro_estado === 'completada' ? 'selected' : '' ?>>🎯 Completadas</option>
                            <option value="rechazada" <?= $filtro_estado === 'rechazada' ? 'selected' : '' ?>>❌ Rechazadas</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <a href="?" class="btn btn-info">🔄 Limpiar Filtros</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Tabla de Atenciones -->
        <div class="table-container">
            <div class="table-header">
                <h3>📋 Solicitudes de Atención (<?= count($atenciones) ?> registros)</h3>
            </div>
            
            <?php if (empty($atenciones)): ?>
                <div style="padding: 40px; text-align: center; color: #7f8c8d;">
                    <h3>📭 No se encontraron atenciones</h3>
                    <p>No hay solicitudes de atención que coincidan con los filtros aplicados.</p>
                </div>
            <?php else: ?>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha Solicitud</th>
                                <th>Fecha Atención</th>
                                <th>Estado</th>
                                <th>Estudiante</th>
                                <th>Docente</th>
                                <th>Tipo</th>
                                <th>Consulta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atenciones as $atencion): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d/m/Y H:i', strtotime($atencion['created_at'])) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($atencion['fecha_atencion'])) ?></strong><br>
                                        <small><?= date('H:i', strtotime($atencion['hora_atencion'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $atencion['estado'] ?>">
                                            <?php
                                            $iconos = [
                                                'pendiente' => '⏳',
                                                'aprobada' => '✅',
                                                'rechazada' => '❌',
                                                'completada' => '🎯'
                                            ];
                                            echo $iconos[$atencion['estado']] . ' ' . ucfirst($atencion['estado']);
                                            ?>
                                        </span>
                                        
                                        <?php if ($atencion['estado'] === 'rechazada' && $atencion['motivo_rechazo']): ?>
                                            <div class="motivo-rechazo">
                                                <strong>Motivo:</strong> <?= htmlspecialchars($atencion['motivo_rechazo']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($atencion['aprobada_por_email']): ?>
                                            <div style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">
                                                Por: <?= htmlspecialchars($atencion['aprobada_por_email']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($atencion['estudiante_codigo']) ?></strong><br>
                                        <small><?= htmlspecialchars($atencion['estudiante_nombre']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($atencion['docente_nombre']) ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.9em;">
                                            <?= htmlspecialchars(substr($atencion['tipo_consejeria_nombre'], 0, 30)) ?>...
                                        </span>
                                    </td>
                                    <td>
                                        <div class="atencion-details" title="<?= htmlspecialchars($atencion['consulta_estudiante']) ?>">
                                            <?= htmlspecialchars($atencion['consulta_estudiante']) ?>
                                        </div>
                                    </td>
                                    <td class="actions-cell">
                                        <?php if ($atencion['estado'] === 'pendiente'): ?>
                                            <!-- Aprobar -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="aprobar">
                                                <input type="hidden" name="atencion_id" value="<?= $atencion['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-small" 
                                                        onclick="return confirm('¿Aprobar esta solicitud de atención?')">
                                                    ✅ Aprobar
                                                </button>
                                            </form>
                                            
                                            <!-- Rechazar -->
                                            <button type="button" class="btn btn-small" 
                                                    onclick="mostrarModalRechazo(<?= $atencion['id'] ?>)">
                                                ❌ Rechazar
                                            </button>
                                            
                                        <?php elseif ($atencion['estado'] === 'aprobada'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="completar">
                                                <input type="hidden" name="atencion_id" value="<?= $atencion['id'] ?>">
                                                <button type="submit" class="btn btn-info btn-small"
                                                        onclick="return confirm('¿Marcar como completada?')">
                                                    🎯 Completar
                                                </button>
                                            </form>
                                            
                                        <?php elseif ($atencion['estado'] === 'rechazada'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="pendiente">
                                                <input type="hidden" name="atencion_id" value="<?= $atencion['id'] ?>">
                                                <button type="submit" class="btn btn-warning btn-small"
                                                        onclick="return confirm('¿Regresar a pendiente para nueva revisión?')">
                                                    ⏳ Revisar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Ver detalles -->
                                        <a href="lista_atenciones.php" class="btn btn-info btn-small">👁️ Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para Rechazar -->
    <div id="modalRechazo" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h3>❌ Rechazar Solicitud de Atención</h3>
            
            <form method="POST">
                <input type="hidden" name="accion" value="rechazar">
                <input type="hidden" name="atencion_id" id="rechazo_atencion_id">
                
                <div class="form-group">
                    <label for="motivo">Motivo del Rechazo <span style="color: #e74c3c;">*</span></label>
                    <textarea name="motivo" id="motivo" required 
                              placeholder="Explique por qué se rechaza esta solicitud de atención..."></textarea>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" class="btn btn-info" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn">Rechazar Solicitud</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function mostrarModalRechazo(atencionId) {
            document.getElementById('rechazo_atencion_id').value = atencionId;
            document.getElementById('motivo').value = '';
            document.getElementById('modalRechazo').style.display = 'block';
        }
        
        function cerrarModal() {
            document.getElementById('modalRechazo').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalRechazo');
            if (event.target === modal) {
                cerrarModal();
            }
        }
    </script>
</body>
</html>
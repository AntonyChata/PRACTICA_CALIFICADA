<?php
require_once "../config/db.php";
require_once "../includes/auth.php";

verificarLogin();

// Obtener datos del usuario logueado
$usuario_rol = $_SESSION['usuario_rol'] ?? 'invitado';
$usuario_email = $_SESSION['usuario_email'] ?? $_SESSION['usuario'] ?? '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $semestre = trim($_POST['semestre']);
        $fecha_atencion = $_POST['fecha_atencion'];
        $hora_atencion = $_POST['hora_atencion'];
        $docente_id = $_POST['docente_id'];
        $estudiante_id = $_POST['estudiante_id'];
        $tipo_consejeria_id = $_POST['tipo_consejeria_id'];
        $consulta_estudiante = trim($_POST['consulta_estudiante']);
        $descripcion_atencion = trim($_POST['descripcion_atencion']);
        $evidencia_texto = trim($_POST['evidencia_texto'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validaciones básicas
        if (empty($semestre) || empty($fecha_atencion) || empty($hora_atencion) || 
            empty($docente_id) || empty($estudiante_id) || empty($tipo_consejeria_id) ||
            empty($consulta_estudiante) || empty($descripcion_atencion)) {
            throw new Exception("Todos los campos obligatorios deben ser completados.");
        }
        
        // Validación de fecha - no debe ser anterior a hoy
        $fecha_actual = date('Y-m-d');
        if ($fecha_atencion < $fecha_actual) {
            throw new Exception("No se puede agendar una cita en una fecha pasada.");
        }
        
        // Validación de horario permitido (7:00 AM - 8:00 PM)
        if ($hora_atencion < '07:00' || $hora_atencion > '20:00') {
            throw new Exception("La hora de atención debe estar entre las 7:00 AM y 8:00 PM.");
        }
        
        // Validación de hora - si es hoy, no debe ser una hora pasada
        if ($fecha_atencion === $fecha_actual) {
            $hora_actual = date('H:i');
            if ($hora_atencion <= $hora_actual) {
                throw new Exception("No se puede agendar una cita en una hora que ya pasó. Seleccione una hora futura.");
            }
        }

        // Procesar archivo subido si existe
        $evidencia_final = $evidencia_texto; // Por defecto usar el texto
        
        if (isset($_FILES['evidencia_archivo']) && $_FILES['evidencia_archivo']['error'] === UPLOAD_ERR_OK) {
            // Validaciones del archivo
            $archivo = $_FILES['evidencia_archivo'];
            $nombre_original = $archivo['name'];
            $tipo_archivo = $archivo['type'];
            $tamano_archivo = $archivo['size'];
            $archivo_tmp = $archivo['tmp_name'];
            
            // Tipos de archivo permitidos
            $tipos_permitidos = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            
            // Extensiones permitidas
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
            
            // Validar tipo y extensión
            if (!in_array($tipo_archivo, $tipos_permitidos) || !in_array($extension, $extensiones_permitidas)) {
                throw new Exception("Tipo de archivo no permitido. Solo se permiten: JPG, PNG, PDF, DOC, DOCX, TXT, XLS, XLSX.");
            }
            
            // Validar tamaño (5MB máximo)
            if ($tamano_archivo > 5242880) {
                throw new Exception("El archivo es demasiado grande. Tamaño máximo: 5MB.");
            }
            
            // Generar nombre único para el archivo
            $nombre_unico = date('YmdHis') . '_' . uniqid() . '.' . $extension;
            $ruta_destino = '../uploads/evidencias/' . $nombre_unico;
            
            // Crear directorio si no existe
            if (!is_dir('../uploads/evidencias/')) {
                mkdir('../uploads/evidencias/', 0755, true);
            }
            
            // Mover archivo
            if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                $evidencia_final = 'uploads/evidencias/' . $nombre_unico; // Ruta relativa para BD
            } else {
                throw new Exception("Error al subir el archivo.");
            }
        }

        // Insertar en base de datos
        // Si es estudiante, la atención queda pendiente; si es docente/admin, se aprueba automáticamente
        $estado_inicial = ($usuario_rol === 'estudiante') ? 'pendiente' : 'aprobada';
        $aprobada_por = ($usuario_rol !== 'estudiante') ? $_SESSION['usuario_id'] : null;
        $fecha_aprobacion = ($usuario_rol !== 'estudiante') ? 'NOW()' : 'NULL';
        
        $sql = "INSERT INTO atenciones (semestre, fecha_atencion, hora_atencion, docente_id, 
                estudiante_id, tipo_consejeria_id, consulta_estudiante, descripcion_atencion, 
                evidencia, observaciones, estado, aprobada_por, fecha_aprobacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " . $fecha_aprobacion . ")";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$semestre, $fecha_atencion, $hora_atencion, $docente_id, 
                       $estudiante_id, $tipo_consejeria_id, $consulta_estudiante, 
                       $descripcion_atencion, $evidencia_final, $observaciones, $estado_inicial, $aprobada_por]);

        $mensaje_exito = ($_SESSION['usuario_rol'] === 'estudiante' ? 
                         "Solicitud de atención enviada exitosamente. El docente será notificado." : 
                         "Atención registrada exitosamente.") . 
                        (isset($_FILES['evidencia_archivo']) && $_FILES['evidencia_archivo']['error'] === UPLOAD_ERR_OK ? 
                         " Archivo subido correctamente." : "");
    } catch (Exception $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Obtener datos para los selectores (usuario_rol ya está definido arriba)

// Obtener datos para los selectores
$docentes = $pdo->query("SELECT id, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM docentes WHERE activo = 1 ORDER BY apellidos")->fetchAll();
$estudiantes = $pdo->query("SELECT id, codigo, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM estudiantes WHERE activo = 1 ORDER BY apellidos")->fetchAll();
$tipos_consejeria = $pdo->query("SELECT id, nombre FROM tipos_consejeria WHERE activo = 1 ORDER BY id")->fetchAll();

// Obtener información específica del usuario actual
$docente_actual = null;
$estudiante_actual = null;

if ($usuario_rol === 'docente' && !empty($usuario_email)) {
    $stmt = $pdo->prepare("SELECT id, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM docentes WHERE email = ?");
    $stmt->execute([$usuario_email]);
    $docente_actual = $stmt->fetch();
} elseif ($usuario_rol === 'estudiante' && !empty($usuario_email)) {
    $stmt = $pdo->prepare("SELECT id, codigo, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM estudiantes WHERE email = ?");
    $stmt->execute([$usuario_email]);
    $estudiante_actual = $stmt->fetch();
}

// Generar semestre actual
$año_actual = date('Y');
$mes_actual = date('n');
$semestre_actual = $año_actual . '-' . ($mes_actual <= 7 ? '1' : '2');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Atención - Consejería y Tutoría</title>
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
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.8;
        }
        
        .user-info {
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .form-container {
            padding: 30px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        input:invalid {
            border-color: #e74c3c;
            background-color: #fdf2f2;
        }
        
        input:invalid:focus {
            box-shadow: 0 0 5px rgba(231, 76, 60, 0.3);
        }
        
        small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 0.875em;
        }
        
        small.error {
            color: #e74c3c;
            font-weight: 500;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
            margin-right: 10px;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .navigation {
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
        }
        
        .navigation a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .navigation a:hover {
            text-decoration: underline;
        }
        
        /* Estilos para evidencia con pestañas */
        .evidencia-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        
        .evidencia-tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-btn:hover {
            color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .file-upload-area {
            position: relative;
        }
        
        .file-upload-label {
            display: block;
            padding: 30px 20px;
            border: 3px dashed #ced4da;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .file-upload-label:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .upload-icon {
            font-size: 2em;
            display: block;
            margin-bottom: 10px;
        }
        
        .upload-text {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .upload-types {
            display: block;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        #evidencia_archivo {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-preview {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border: 2px solid #28a745;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-name {
            font-weight: 600;
            color: #28a745;
        }
        
        .remove-file {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .remove-file:hover {
            background: #c82333;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .container {
                margin: 10px;
            }
            
            .evidencia-tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                text-align: center;
                border-bottom: 1px solid #dee2e6;
                border-right: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= $usuario_rol === 'estudiante' ? 'Solicitar Atención de Consejería' : 'Registro de Atención' ?></h1>
            <p>Sistema de Consejería y Tutoría Estudiantil</p>
            <?php if ($usuario_rol === 'estudiante'): ?>
                <div class="user-info">
                    <strong>Solicitante:</strong> <?= htmlspecialchars($estudiante_actual['nombre_completo'] ?? 'Estudiante') ?>
                    <br><small>Código: <?= htmlspecialchars($estudiante_actual['codigo'] ?? 'N/A') ?></small>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="form-container">
            <?php if (isset($mensaje_exito)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
            <?php endif; ?>
            
            <?php if (isset($mensaje_error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($mensaje_error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Semestre <span class="required">*</span></label>
                        <input type="text" name="semestre" value="<?= htmlspecialchars($semestre_actual) ?>" 
                               pattern="[0-9]{4}-[12]" placeholder="2024-1" required>
                        <small>Formato: YYYY-1 o YYYY-2</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha de Atención <span class="required">*</span></label>
                        <input type="date" name="fecha_atencion" 
                               value="<?= date('Y-m-d') ?>" 
                               min="<?= date('Y-m-d') ?>" 
                               max="<?= date('Y-m-d', strtotime('+6 months')) ?>" 
                               required>
                        <small>Solo se permiten citas futuras (hasta 6 meses)</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Hora de Atención <span class="required">*</span></label>
                        <input type="time" name="hora_atencion" 
                               value="<?= date('H:i', strtotime('+1 hour')) ?>" 
                               min="07:00" 
                               max="20:00" 
                               required>
                        <small>Horario disponible: 7:00 AM - 8:00 PM</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Docente Responsable <span class="required">*</span></label>
                        <select name="docente_id" required>
                            <option value="">Seleccione un docente</option>
                            <?php foreach ($docentes as $docente): ?>
                                <option value="<?= $docente['id'] ?>"><?= htmlspecialchars($docente['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Estudiante <span class="required">*</span></label>
                    <?php if ($usuario_rol === 'estudiante' && $estudiante_actual): ?>
                        <select name="estudiante_id" required>
                            <option value="<?= $estudiante_actual['id'] ?>" selected>
                                <?= htmlspecialchars($estudiante_actual['codigo']) ?> - <?= htmlspecialchars($estudiante_actual['nombre_completo']) ?> (Yo)
                            </option>
                        </select>
                        <small style="color: #28a745;">✓ Solicitando para mi cuenta</small>
                    <?php else: ?>
                        <select name="estudiante_id" required>
                            <option value="">Seleccionar estudiante...</option>
                            <?php foreach ($estudiantes as $estudiante): ?>
                                <option value="<?= $estudiante['id'] ?>">
                                    <?= htmlspecialchars($estudiante['codigo']) ?> - <?= htmlspecialchars($estudiante['nombre_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Consejería <span class="required">*</span></label>
                    <select name="tipo_consejeria_id" required>
                        <option value="">Seleccione el tipo de consejería</option>
                        <?php foreach ($tipos_consejeria as $tipo): ?>
                            <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= $usuario_rol === 'estudiante' ? 'Mi Consulta' : 'Consulta del Estudiante' ?> <span class="required">*</span></label>
                    <textarea name="consulta_estudiante" 
                              placeholder="<?= $usuario_rol === 'estudiante' ? 'Describe tu consulta o motivo por el cual solicitas la atención...' : 'Describa la consulta o motivo por el cual el estudiante solicita la atención...' ?>" 
                              required></textarea>
                </div>
                
                <div class="form-group">
                    <label><?= $usuario_rol === 'estudiante' ? 'Detalles Adicionales' : 'Descripción de la Atención' ?> <span class="required">*</span></label>
                    <textarea name="descripcion_atencion" 
                              placeholder="<?= $usuario_rol === 'estudiante' ? 'Información adicional que consideres relevante para la cita...' : 'Describa la orientación brindada, recomendaciones, acciones realizadas...' ?>" 
                              required></textarea>
                    <?php if ($usuario_rol === 'estudiante'): ?>
                        <small>Este campo será completado por el docente durante la atención</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group evidencia-group">
                    <label>Evidencia</label>
                    <div class="evidencia-options">
                        <div class="evidencia-tabs">
                            <button type="button" class="tab-btn active" onclick="mostrarTab('archivo')">📎 Subir Archivo</button>
                            <button type="button" class="tab-btn" onclick="mostrarTab('texto')">📝 URL/Texto</button>
                        </div>
                        
                        <div id="tab-archivo" class="tab-content active">
                            <div class="file-upload-area">
                                <input type="file" id="evidencia_archivo" name="evidencia_archivo" 
                                       accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.xls,.xlsx">
                                <label for="evidencia_archivo" class="file-upload-label">
                                    <span class="upload-icon">📁</span>
                                    <span class="upload-text">Seleccionar archivo (máx. 5MB)</span>
                                    <span class="upload-types">JPG, PNG, PDF, DOC, DOCX, TXT, XLS, XLSX</span>
                                </label>
                                <div class="file-preview" id="file-preview" style="display: none;">
                                    <span class="preview-name"></span>
                                    <button type="button" class="remove-file" onclick="removerArchivo()">✖</button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="tab-texto" class="tab-content">
                            <input type="text" name="evidencia_texto" placeholder="URL de documento, enlace, descripción de evidencia...">
                            <small>Ejemplo: https://drive.google.com/..., Documento entregado físicamente, etc.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" placeholder="Observaciones adicionales (opcional)"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">
                        <?= $usuario_rol === 'estudiante' ? '📅 Solicitar Cita de Consejería' : '📝 Registrar Atención' ?>
                    </button>
                    <button type="reset" class="btn btn-secondary">Limpiar Formulario</button>
                </div>
            </form>
        </div>
        
        <div class="navigation">
            <?php if ($usuario_rol === 'estudiante'): ?>
                <a href="dashboard_estudiante.php">← Volver a Mi Portal</a> | 
                <a href="lista_atenciones.php?estudiante=<?= $estudiante_actual['id'] ?? 0 ?>">Mis Atenciones</a> | 
                <a href="directorio_docentes.php">Directorio de Docentes</a>
            <?php elseif ($usuario_rol === 'docente'): ?>
                <a href="dashboard_docente.php">← Volver al Dashboard</a> | 
                <a href="lista_atenciones.php">Ver Atenciones</a> | 
                <a href="reportes.php">Ver Reportes</a>
            <?php else: ?>
                <a href="dashboard_administrador.php">← Volver al Dashboard</a> | 
                <a href="lista_atenciones.php">Ver Atenciones Registradas</a> | 
                <a href="reportes.php">Ver Reportes</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Función para cambiar entre pestañas
        function mostrarTab(tabName) {
            // Ocultar todos los contenidos de pestañas
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Desactivar todos los botones de pestañas
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => btn.classList.remove('active'));
            
            // Mostrar el contenido seleccionado
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Activar el botón correspondiente
            event.target.classList.add('active');
        }
        
        // Validación de fecha y hora en tiempo real
        function validarFechaHora() {
            const fechaInput = document.querySelector('input[name="fecha_atencion"]');
            const horaInput = document.querySelector('input[name="hora_atencion"]');
            
            if (fechaInput && horaInput) {
                const fechaSeleccionada = fechaInput.value;
                const horaSeleccionada = horaInput.value;
                const ahora = new Date();
                const fechaHoy = ahora.toISOString().split('T')[0];
                const horaActual = ahora.toTimeString().split(' ')[0].substring(0, 5);
                
                // Limpiar mensajes previos
                let fechaSmall = fechaInput.parentNode.querySelector('small');
                let horaSmall = horaInput.parentNode.querySelector('small');
                
                // Validar fecha
                if (fechaSeleccionada < fechaHoy) {
                    fechaInput.setCustomValidity('No se pueden seleccionar fechas pasadas');
                    if (fechaSmall) {
                        fechaSmall.textContent = '❌ No se pueden seleccionar fechas pasadas';
                        fechaSmall.className = 'error';
                    }
                } else {
                    fechaInput.setCustomValidity('');
                    if (fechaSmall) {
                        fechaSmall.textContent = 'Solo se permiten citas futuras (hasta 6 meses)';
                        fechaSmall.className = '';
                    }
                }
                
                // Validar hora si es hoy
                if (fechaSeleccionada === fechaHoy && horaSeleccionada <= horaActual) {
                    horaInput.setCustomValidity('No se puede seleccionar una hora que ya pasó');
                    if (horaSmall) {
                        horaSmall.textContent = '❌ Seleccione una hora futura (actual: ' + horaActual + ')';
                        horaSmall.className = 'error';
                    }
                } else {
                    horaInput.setCustomValidity('');
                    if (horaSmall) {
                        horaSmall.textContent = 'Horario disponible: 7:00 AM - 8:00 PM';
                        horaSmall.className = '';
                    }
                }
            }
        }
        
        // Agregar event listeners cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.querySelector('input[name="fecha_atencion"]');
            const horaInput = document.querySelector('input[name="hora_atencion"]');
            
            if (fechaInput) {
                fechaInput.addEventListener('change', validarFechaHora);
            }
            
            if (horaInput) {
                horaInput.addEventListener('change', validarFechaHora);
                horaInput.addEventListener('blur', validarFechaHora);
            }
        });
        
        // Función para manejar la selección de archivo
        document.getElementById('evidencia_archivo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('file-preview');
            const previewName = preview.querySelector('.preview-name');
            
            if (file) {
                // Validar tamaño (5MB)
                if (file.size > 5242880) {
                    alert('El archivo es demasiado grande. Tamaño máximo: 5MB');
                    e.target.value = '';
                    return;
                }
                
                // Validar tipo de archivo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
                                    'application/pdf', 'application/msword', 
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'text/plain', 'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, PDF, DOC, DOCX, TXT, XLS, XLSX');
                    e.target.value = '';
                    return;
                }
                
                // Mostrar preview
                previewName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                preview.style.display = 'flex';
                
                // Ocultar la zona de subida
                document.querySelector('.file-upload-label').style.display = 'none';
            }
        });
        
        // Función para remover archivo seleccionado
        function removerArchivo() {
            document.getElementById('evidencia_archivo').value = '';
            document.getElementById('file-preview').style.display = 'none';
            document.querySelector('.file-upload-label').style.display = 'block';
        }
        
        // Función para formatear el tamaño del archivo
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
        
        // Prevenir el envío del formulario si hay errores de validación
        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('evidencia_archivo');
            const file = fileInput.files[0];
            const fechaInput = document.querySelector('input[name="fecha_atencion"]');
            const horaInput = document.querySelector('input[name="hora_atencion"]');
            
            // Validar archivo
            if (file) {
                if (file.size > 5242880) {
                    e.preventDefault();
                    alert('El archivo es demasiado grande. Tamaño máximo: 5MB');
                    return false;
                }
            }
            
            // Validar fecha y hora antes del envío
            if (fechaInput && horaInput) {
                const fechaSeleccionada = fechaInput.value;
                const horaSeleccionada = horaInput.value;
                const fechaHoy = new Date().toISOString().split('T')[0];
                const horaActual = new Date().toTimeString().split(' ')[0].substring(0, 5);
                
                // Verificar fecha pasada
                if (fechaSeleccionada < fechaHoy) {
                    e.preventDefault();
                    alert('No se puede agendar una cita en una fecha pasada.');
                    return false;
                }
                
                // Verificar hora pasada SOLO si es hoy
                if (fechaSeleccionada === fechaHoy && horaSeleccionada <= horaActual) {
                    e.preventDefault();
                    alert('No se puede agendar una cita en una hora que ya pasó. Seleccione una hora futura.');
                    return false;
                }
            }
        });
        
        // Drag and drop functionality
        const uploadArea = document.querySelector('.file-upload-label');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            uploadArea.classList.add('drag-over');
        }
        
        function unhighlight() {
            uploadArea.classList.remove('drag-over');
        }
        
        uploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                document.getElementById('evidencia_archivo').files = files;
                document.getElementById('evidencia_archivo').dispatchEvent(new Event('change'));
            }
        }
    </script>
</body>
</html>
<?php
require_once "../config/db.php";

echo "<h2>🔄 Sincronización de Usuarios y Perfiles</h2>";

try {
    // Buscar docentes sin perfil
    $stmt = $pdo->query("
        SELECT u.id, u.email 
        FROM usuarios u 
        WHERE u.rol = 'docente' 
        AND u.email NOT IN (SELECT email FROM docentes WHERE email IS NOT NULL)
    ");
    $docentes_sin_perfil = $stmt->fetchAll();
    
    if (!empty($docentes_sin_perfil)) {
        echo "<h3>Creando perfiles de docentes faltantes:</h3>";
        foreach ($docentes_sin_perfil as $usuario) {
            $nombre_base = explode('@', $usuario['email'])[0];
            $codigo_auto = 'DOC' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            
            $stmt_insert = $pdo->prepare("INSERT INTO docentes (codigo, apellidos, nombres, email, especialidad) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->execute([$codigo_auto, ucfirst($nombre_base), 'Sin especificar', $usuario['email'], 'Por definir']);
            
            echo "✅ Creado perfil de docente para: {$usuario['email']}<br>";
        }
    } else {
        echo "<p>✅ Todos los usuarios docente tienen su perfil correspondiente.</p>";
    }
    
    // Buscar estudiantes sin perfil
    $stmt = $pdo->query("
        SELECT u.id, u.email 
        FROM usuarios u 
        WHERE u.rol = 'estudiante' 
        AND u.email NOT IN (SELECT email FROM estudiantes WHERE email IS NOT NULL)
    ");
    $estudiantes_sin_perfil = $stmt->fetchAll();
    
    if (!empty($estudiantes_sin_perfil)) {
        echo "<h3>Creando perfiles de estudiantes faltantes:</h3>";
        foreach ($estudiantes_sin_perfil as $usuario) {
            $nombre_base = explode('@', $usuario['email'])[0];
            $codigo_auto = date('Y') . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt_insert = $pdo->prepare("INSERT INTO estudiantes (codigo, apellidos, nombres, email, carrera, semestre) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_insert->execute([$codigo_auto, ucfirst($nombre_base), 'Sin especificar', $usuario['email'], 'Por definir', 1]);
            
            echo "✅ Creado perfil de estudiante para: {$usuario['email']}<br>";
        }
    } else {
        echo "<p>✅ Todos los usuarios estudiante tienen su perfil correspondiente.</p>";
    }
    
    echo "<hr>";
    echo "<h3>📊 Resumen actual:</h3>";
    
    // Mostrar conteos
    $docentes_total = $pdo->query("SELECT COUNT(*) as total FROM docentes WHERE activo = 1")->fetch()['total'];
    $estudiantes_total = $pdo->query("SELECT COUNT(*) as total FROM estudiantes WHERE activo = 1")->fetch()['total'];
    $usuarios_total = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")->fetch()['total'];
    
    echo "<ul>";
    echo "<li>👨‍🏫 Docentes: {$docentes_total}</li>";
    echo "<li>👨‍🎓 Estudiantes: {$estudiantes_total}</li>";
    echo "<li>👤 Usuarios: {$usuarios_total}</li>";
    echo "</ul>";
    
    echo "<p><strong>🎯 La sincronización está completa.</strong></p>";
    echo "<p><a href='registro_atencion.php'>➡️ Ir al Formulario de Registro</a> | <a href='directorio_docentes.php'>👨‍🏫 Ver Docentes</a> | <a href='gestion_estudiantes.php'>👨‍🎓 Ver Estudiantes</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
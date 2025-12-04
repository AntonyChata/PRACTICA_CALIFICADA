<?php
require_once "../config/db.php";

echo "<h2>Verificación de Base de Datos</h2>";

// Verificar docentes
echo "<h3>Docentes en la base de datos:</h3>";
try {
    $docentes = $pdo->query("SELECT id, codigo, apellidos, nombres, email FROM docentes WHERE activo = 1 ORDER BY apellidos")->fetchAll();
    if (empty($docentes)) {
        echo "<p>No hay docentes registrados.</p>";
    } else {
        echo "<ul>";
        foreach ($docentes as $docente) {
            echo "<li>ID: {$docente['id']} - {$docente['codigo']} - {$docente['apellidos']}, {$docente['nombres']} - {$docente['email']}</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>Error al consultar docentes: " . $e->getMessage() . "</p>";
}

// Verificar estudiantes
echo "<h3>Estudiantes en la base de datos:</h3>";
try {
    $estudiantes = $pdo->query("SELECT id, codigo, apellidos, nombres, email FROM estudiantes WHERE activo = 1 ORDER BY apellidos")->fetchAll();
    if (empty($estudiantes)) {
        echo "<p>No hay estudiantes registrados.</p>";
    } else {
        echo "<ul>";
        foreach ($estudiantes as $estudiante) {
            echo "<li>ID: {$estudiante['id']} - {$estudiante['codigo']} - {$estudiante['apellidos']}, {$estudiante['nombres']} - {$estudiante['email']}</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>Error al consultar estudiantes: " . $e->getMessage() . "</p>";
}

// Verificar usuarios
echo "<h3>Usuarios en la base de datos:</h3>";
try {
    $usuarios = $pdo->query("SELECT id, email, rol FROM usuarios WHERE activo = 1 ORDER BY email")->fetchAll();
    if (empty($usuarios)) {
        echo "<p>No hay usuarios registrados.</p>";
    } else {
        echo "<ul>";
        foreach ($usuarios as $usuario) {
            echo "<li>ID: {$usuario['id']} - {$usuario['email']} - Rol: {$usuario['rol']}</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>Error al consultar usuarios: " . $e->getMessage() . "</p>";
}
?>
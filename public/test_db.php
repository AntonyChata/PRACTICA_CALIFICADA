<?php
require_once "../config/db.php";

try {
    // Solo probamos que exista la tabla
    $stmt = $pdo->query("SHOW TABLES LIKE 'atenciones';");
    $tabla = $stmt->fetch();

    if ($tabla) {
        echo "✅ Conexión correcta y tabla 'atenciones' encontrada.";
    } else {
        echo "⚠ Conexión ok, pero no se encontró la tabla 'atenciones'.";
    }
} catch (PDOException $e) {
    echo "❌ Error al consultar: " . $e->getMessage();
}

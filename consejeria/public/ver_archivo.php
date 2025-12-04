<?php
require_once "../includes/auth.php";

// Verificar que el usuario esté logueado
requiereLogin();

// Obtener el archivo solicitado
$archivo = $_GET['file'] ?? '';

if (empty($archivo)) {
    http_response_code(400);
    die('Archivo no especificado');
}

// Validar que el archivo esté en el directorio correcto
$archivo_limpio = basename($archivo);
$ruta_archivo = "../uploads/evidencias/" . $archivo_limpio;

// Verificar que el archivo existe
if (!file_exists($ruta_archivo)) {
    http_response_code(404);
    die('Archivo no encontrado');
}

// Validar extensión por seguridad
$extension = strtolower(pathinfo($ruta_archivo, PATHINFO_EXTENSION));
$extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];

if (!in_array($extension, $extensiones_permitidas)) {
    http_response_code(403);
    die('Tipo de archivo no permitido');
}

// Determinar tipo MIME
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Configurar headers de seguridad
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($ruta_archivo));
header('Content-Disposition: inline; filename="' . $archivo_limpio . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Para imágenes y PDFs, mostrar en línea; para otros, forzar descarga
if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
    header('Content-Disposition: attachment; filename="' . $archivo_limpio . '"');
}

// Leer y enviar el archivo
readfile($ruta_archivo);
exit;
?>
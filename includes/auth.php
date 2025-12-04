<?php
session_start();

/**
 * Verifica que el correo sea corporativo UPT
 */
function esCorreoInstitucional($email) {
    // Primero validamos que sea correo con formato correcto
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Solo se permite @upt.pe o @virtual.upt.pe
    return preg_match('/^[A-Za-z0-9._%+-]+@(upt\.pe|virtual\.upt\.pe)$/', $email);
}

/**
 * Autentica usuario en base de datos
 */
function autenticarUsuario($email, $password, $pdo) {
    $stmt = $pdo->prepare("SELECT id, email, password, rol FROM usuarios WHERE email = ? AND activo = 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        return $usuario;
    }
    return false;
}

/**
 * Registra nuevo usuario
 */
function registrarUsuario($email, $password, $rol, $pdo) {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO usuarios (email, password, rol) VALUES (?, ?, ?)");
    return $stmt->execute([$email, $passwordHash, $rol]);
}

/**
 * Verifica si el usuario tiene el rol requerido
 */
function tieneRol($rolRequerido) {
    if (!isset($_SESSION['usuario_rol'])) {
        return false;
    }
    
    // Administrador tiene acceso a todo
    if ($_SESSION['usuario_rol'] === 'administrador') {
        return true;
    }
    
    // Verificar rol específico
    return $_SESSION['usuario_rol'] === $rolRequerido;
}

/**
 * Requiere un rol específico
 */
function requiereRol($rolRequerido) {
    requiereLogin();
    if (!tieneRol($rolRequerido)) {
        header("Location: index.php?error=acceso_denegado");
        exit;
    }
}

/**
 * Requiere que el usuario esté logueado
 */
function requiereLogin() {
    if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario_rol'])) {
        header("Location: index.php");
        exit;
    }
}

/**
 * Alias para verificar login (compatibilidad)
 */
function verificarLogin() {
    requiereLogin();
}

/**
 * Redirecciona según el rol del usuario
 */
function redirigirSegunRol() {
    if (!isset($_SESSION['usuario_rol'])) {
        header("Location: index.php");
        exit;
    }
    
    switch ($_SESSION['usuario_rol']) {
        case 'administrador':
            header("Location: dashboard_administrador.php");
            break;
        case 'docente':
            header("Location: dashboard_docente.php");
            break;
        case 'estudiante':
            header("Location: dashboard_estudiante.php");
            break;
        default:
            header("Location: index.php");
    }
    exit;
}

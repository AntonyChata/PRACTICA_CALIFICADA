-- Eliminar base de datos si existe para evitar conflictos
DROP DATABASE IF EXISTS consejeria_db;

-- Crear base de datos
CREATE DATABASE consejeria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE consejeria_db;

-- Tabla de usuarios del sistema (para login)
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('administrador', 'docente', 'estudiante') NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de docentes responsables de consejería/tutoría
CREATE TABLE IF NOT EXISTS docentes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    especialidad VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de estudiantes
CREATE TABLE IF NOT EXISTS estudiantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    carrera VARCHAR(100),
    semestre INT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de tipos de consejería
CREATE TABLE IF NOT EXISTS tipos_consejeria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
);

-- Insertar tipos de consejería predefinidos
INSERT INTO tipos_consejeria (nombre, descripcion) VALUES
('Asuntos relacionados con el plan de estudios', 'Orientación sobre cursos, malla curricular, prerrequisitos'),
('Asuntos relacionados con el desarrollo profesional', 'Orientación sobre competencias profesionales, habilidades blandas'),
('Asuntos relacionados con la inserción laboral', 'Orientación sobre prácticas, empleabilidad, mercado laboral'),
('Asuntos Académicos del Proceso de Plan de Tesis o Tesis', 'Orientación sobre investigación, metodología, desarrollo de tesis'),
('Otros', 'Otros asuntos no contemplados en las categorías anteriores');

-- Tabla principal de atenciones
CREATE TABLE IF NOT EXISTS atenciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    semestre VARCHAR(10) NOT NULL,
    fecha_atencion DATE NOT NULL,
    hora_atencion TIME NOT NULL,
    docente_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    tipo_consejeria_id INT NOT NULL,
    consulta_estudiante TEXT NOT NULL,
    descripcion_atencion TEXT NOT NULL,
    evidencia VARCHAR(255),
    observaciones TEXT,
    estado ENUM('pendiente', 'aprobada', 'rechazada', 'completada') DEFAULT 'pendiente',
    motivo_rechazo TEXT,
    aprobada_por INT,
    fecha_aprobacion TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docentes(id),
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id),
    FOREIGN KEY (tipo_consejeria_id) REFERENCES tipos_consejeria(id),
    FOREIGN KEY (aprobada_por) REFERENCES usuarios(id)
);

-- Índices para mejorar rendimiento
CREATE INDEX idx_atenciones_semestre ON atenciones(semestre);
CREATE INDEX idx_atenciones_fecha ON atenciones(fecha_atencion);
CREATE INDEX idx_atenciones_docente ON atenciones(docente_id);
CREATE INDEX idx_atenciones_tipo ON atenciones(tipo_consejeria_id);

-- Insertar algunos docentes de ejemplo
INSERT INTO docentes (codigo, apellidos, nombres, email, especialidad) VALUES
('DOC001', 'García López', 'María Elena', 'mgarcia@upt.pe', 'Ingeniería de Sistemas'),
('DOC002', 'Rodríguez Pérez', 'Carlos Alberto', 'crodriguez@upt.pe', 'Ingeniería Industrial'),
('DOC003', 'Martínez Silva', 'Ana Lucía', 'amartinez@upt.pe', 'Administración'),
('DOC004', 'López Vargas', 'José Miguel', 'jlopez@upt.pe', 'Contabilidad');

-- Insertar algunos estudiantes de ejemplo
INSERT INTO estudiantes (codigo, apellidos, nombres, email, carrera, semestre) VALUES
('2021001234', 'Quispe Mamani', 'Juan Carlos', 'jquispe@virtual.upt.pe', 'Ingeniería de Sistemas', 6),
('2020005678', 'Flores Condori', 'María Rosa', 'mflores@virtual.upt.pe', 'Ingeniería Industrial', 8),
('2022001111', 'Huanca Cruz', 'Pedro Luis', 'phuanca@virtual.upt.pe', 'Administración', 4),
('2021002222', 'Mamani Ticona', 'Ana Isabel', 'amamani@virtual.upt.pe', 'Contabilidad', 5);

-- Insertar usuarios del sistema (contraseña por defecto: 123456)
INSERT INTO usuarios (email, password, rol) VALUES
-- Administradores
('admin@upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador'),
('sistema@upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador'),

-- Docentes (usando los emails de la tabla docentes)
('mgarcia@upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'docente'),
('crodriguez@upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'docente'),
('amartinez@upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'docente'),
('jlopez@upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'docente'),

-- Estudiantes (usando los emails de la tabla estudiantes)
('jquispe@virtual.upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante'),
('mflores@virtual.upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante'),
('phuanca@virtual.upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante'),
('amamani@virtual.upt.pe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'estudiante');
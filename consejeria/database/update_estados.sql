-- Script para actualizar tabla de atenciones existente con sistema de estados
-- Ejecutar SOLO si ya tienes la base de datos creada y quieres agregar los nuevos campos

USE consejeria_db;

-- Agregar nuevos campos para el sistema de estados
ALTER TABLE atenciones 
ADD COLUMN estado ENUM('pendiente', 'aprobada', 'rechazada', 'completada') DEFAULT 'pendiente' AFTER observaciones,
ADD COLUMN motivo_rechazo TEXT AFTER estado,
ADD COLUMN aprobada_por INT AFTER motivo_rechazo,
ADD COLUMN fecha_aprobacion TIMESTAMP NULL AFTER aprobada_por;

-- Agregar índice para el campo estado
CREATE INDEX idx_atenciones_estado ON atenciones(estado);

-- Agregar clave foránea para aprobada_por
ALTER TABLE atenciones 
ADD FOREIGN KEY (aprobada_por) REFERENCES usuarios(id);

-- Actualizar atenciones existentes como aprobadas (opcional - si quieres mantener el estado actual)
-- UPDATE atenciones SET estado = 'aprobada' WHERE estado IS NULL;

-- Comentar la línea anterior y descomentar esta si prefieres que todas las existentes queden como completadas
-- UPDATE atenciones SET estado = 'completada' WHERE estado IS NULL;
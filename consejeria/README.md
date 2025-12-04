# Sistema de Registro de Atenciones de Consejería y Tutoría

## Descripción
Sistema web desarrollado para el registro y seguimiento de atenciones de consejería y tutoría estudiantil en instituciones educativas. Permite gestionar de manera eficiente las sesiones de orientación académica, profesional y personal brindadas a los estudiantes.

## Características Principales

### ✅ Registro de Atenciones
- **Información básica**: Semestre, fecha y hora de atención
- **Participantes**: Docente responsable y estudiante atendido
- **Tipos de consejería**:
  - Asuntos relacionados con el plan de estudios
  - Asuntos relacionados con el desarrollo profesional
  - Asuntos relacionados con la inserción laboral
  - Asuntos Académicos del Proceso de Plan de Tesis o Tesis
  - Otros
- **Detalles**: Consulta del estudiante, descripción de la atención, evidencias y observaciones

### 📊 Reportes y Estadísticas
- **Atenciones por semestre**: Conteo y análisis temporal
- **Atenciones por docente**: Seguimiento de la actividad de cada consejero
- **Atenciones por tipo de consejería**: Identificación de las áreas más demandadas
- **Reportes filtrados**: Por periodo, docente específico o tipo de consejería
- **Estadísticas visuales**: Gráficos de barras y tendencias

### 👥 Gestión de Estudiantes
- Registro de información básica de estudiantes
- Búsqueda y filtrado por carrera, semestre o datos personales
- Integración con el sistema de atenciones

### 🔐 Sistema de Autenticación
- Login seguro con validación de correo institucional
- Sesiones protegidas
- Control de acceso a funcionalidades

## Estructura del Proyecto

```
practica_we2/
├── config/
│   └── db.php                 # Configuración de base de datos
├── database/
│   └── init.sql              # Script de inicialización de BD
├── includes/
│   └── auth.php              # Funciones de autenticación
├── public/
│   ├── index.php             # Página de login
│   ├── dashboard.php         # Panel principal
│   ├── registro_atencion.php # Formulario de registro
│   ├── lista_atenciones.php  # Lista y filtros de atenciones
│   ├── reportes.php          # Reportes estadísticos
│   ├── gestion_estudiantes.php # Gestión de estudiantes
│   ├── logout.php            # Cerrar sesión
│   └── test_db.php           # Prueba de conexión BD
```

## Instalación y Configuración

### Requisitos Previos
- XAMPP (Apache + MySQL + PHP)
- Navegador web moderno
- HeidiSQL o similar para gestión de BD (opcional)

### Pasos de Instalación

1. **Clonar o descargar el proyecto**
   ```bash
   # Colocar en: c:\xampp\htdocs\practica_we2\
   ```

2. **Iniciar servicios XAMPP**
   - Abrir XAMPP Control Panel
   - Iniciar Apache y MySQL

3. **Crear la base de datos**
   ```sql
   -- Opción 1: Ejecutar el script completo
   # Abrir HeidiSQL o phpMyAdmin
   # Ejecutar el archivo: database/init.sql
   
   -- Opción 2: Crear manualmente
   CREATE DATABASE consejeria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. **Configurar conexión a base de datos**
   ```php
   // Verificar config/db.php
   $host = "localhost";
   $port = "3306";
   $dbname = "consejeria_db";
   $user = "root";
   $pass = "";  // Cambiar si MySQL tiene contraseña
   ```

5. **Probar la instalación**
   - Abrir navegador: `http://localhost/practica_we2/public/test_db.php`
   - Verificar conexión exitosa a la base de datos
   - Acceder al login: `http://localhost/practica_we2/public/`

### Datos de Prueba

#### Login de Prueba
- **Usuario**: Cualquier correo @upt.pe o @virtual.upt.pe
- **Contraseña**: `123456`

#### Docentes Precargados
- María Elena García López (DOC001) - Ing. de Sistemas
- Carlos Alberto Rodríguez Pérez (DOC002) - Ing. Industrial  
- Ana Lucía Martínez Silva (DOC003) - Administración
- José Miguel López Vargas (DOC004) - Contabilidad

#### Estudiantes Precargados
- Juan Carlos Quispe Mamani (2021001234)
- María Rosa Flores Condori (2020005678)
- Pedro Luis Huanca Cruz (2022001111)
- Ana Isabel Mamani Ticona (2021002222)

## Uso del Sistema

### 1. Acceso al Sistema
- Ingresar con correo institucional (@upt.pe o @virtual.upt.pe)
- Contraseña de prueba: `123456`

### 2. Registro de Nueva Atención
- Ir a "Registrar Nueva Atención"
- Completar todos los campos obligatorios (*)
- Seleccionar docente, estudiante y tipo de consejería
- Describir la consulta y la atención brindada
- Guardar el registro

### 3. Consulta de Atenciones
- Acceder a "Ver Atenciones Registradas"
- Usar filtros por semestre, docente, tipo o fechas
- Ver detalles completos de cada atención

### 4. Generación de Reportes
- Ir a "Reportes y Estadísticas"
- Ver estadísticas generales del sistema
- Filtrar por semestre específico
- Imprimir o exportar reportes

### 5. Gestión de Estudiantes
- Acceder a "Gestión de Estudiantes"
- Ver lista completa con filtros
- Registrar nuevos estudiantes

## Validaciones Implementadas

### Validación de Datos
- ✅ Campos obligatorios marcados con (*)
- ✅ Formato de fechas y horas
- ✅ Códigos únicos de estudiantes
- ✅ Correos electrónicos institucionales
- ✅ Semestre en formato YYYY-1 o YYYY-2

### Validación de Seguridad
- ✅ Protección contra inyección SQL (PDO preparadas)
- ✅ Escape de HTML (htmlspecialchars)
- ✅ Validación de sesiones activas
- ✅ Control de acceso a páginas protegidas

## Base de Datos

### Tablas Principales
- **docentes**: Información de docentes consejeros
- **estudiantes**: Datos de estudiantes
- **tipos_consejeria**: Categorías de atención predefinidas
- **atenciones**: Registro principal de sesiones

### Relaciones
- `atenciones.docente_id` → `docentes.id`
- `atenciones.estudiante_id` → `estudiantes.id`  
- `atenciones.tipo_consejeria_id` → `tipos_consejeria.id`

## Funcionalidades de Reportes

### Métricas Disponibles
1. **Total de atenciones** registradas en el sistema
2. **Atenciones por semestre** con comparativas
3. **Ranking de docentes** por número de atenciones
4. **Distribución por tipo** de consejería más demandada
5. **Tendencias mensuales** de los últimos 12 meses
6. **Resumen detallado** filtrable por criterios específicos

### Capacidades de Filtrado
- Por semestre académico
- Por docente responsable
- Por tipo de consejería
- Por rango de fechas
- Combinación de múltiples filtros

## Características Técnicas

### Tecnologías Utilizadas
- **Backend**: PHP 7.4+
- **Base de Datos**: MySQL 5.7+ / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Seguridad**: PDO, sesiones PHP, validación de datos

### Características de Diseño
- ✅ **Responsive Design**: Adaptable a dispositivos móviles
- ✅ **Interfaz Intuitiva**: Navegación clara y simple
- ✅ **Validación en Tiempo Real**: Feedback inmediato al usuario
- ✅ **Accesibilidad**: Etiquetas semánticas y contraste adecuado

## Mantenimiento

### Backup de Datos
```sql
-- Exportar base de datos
mysqldump -u root -p consejeria_db > backup_consejeria.sql

-- Restaurar base de datos  
mysql -u root -p consejeria_db < backup_consejeria.sql
```

### Logs y Monitoreo
- Verificar logs de Apache: `c:\xampp\apache\logs\`
- Monitorear errores PHP en el navegador (modo desarrollo)
- Revisar integridad de datos periódicamente

## Soporte y Contacto

Para soporte técnico o consultas sobre el sistema:
- Revisar la documentación incluida
- Verificar configuración de XAMPP y MySQL
- Consultar logs de errores del servidor

## Licencia

Sistema desarrollado para uso académico e institucional. Código fuente disponible para modificaciones según necesidades específicas de la institución.
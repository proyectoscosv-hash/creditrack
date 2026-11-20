-- 1. Jerarquía Geográfica y Marcas (Tablas sin dependencias)
CREATE TABLE Pais (
    Id_pais SERIAL PRIMARY KEY,
    Pais VARCHAR(100) NOT NULL,
    requiere_distrito SMALLINT NOT NULL DEFAULT 1
);

CREATE TABLE Marca (
    Id_marca SERIAL PRIMARY KEY,
    Marca VARCHAR(100) NOT NULL UNIQUE
);

-- 2. Divisiones Territoriales
CREATE TABLE Departamento (
    Id_departamento SERIAL PRIMARY KEY,
    Departamento VARCHAR(100) NOT NULL,
    Id_pais INT NOT NULL,
    FOREIGN KEY (Id_pais) REFERENCES Pais(Id_pais) ON DELETE CASCADE
);

CREATE TABLE Municipio (
    Id_municipio SERIAL PRIMARY KEY,
    Municipio VARCHAR(100) NOT NULL,
    Id_departamento INT NOT NULL,
    FOREIGN KEY (Id_departamento) REFERENCES Departamento(Id_departamento) ON DELETE CASCADE
);

CREATE TABLE Distrito (
    Id_distrito SERIAL PRIMARY KEY,
    Distrito VARCHAR(100) NOT NULL,
    Id_municipio INT NOT NULL,
    FOREIGN KEY (Id_municipio) REFERENCES Municipio(Id_municipio) ON DELETE CASCADE
);

-- 3. Usuarios (alineado con CreateAuthTables y UserModel)
CREATE TABLE Usuario (
    Codigo_Empleado VARCHAR(50) PRIMARY KEY,
    Nombre VARCHAR(150) NOT NULL,
    Usuario VARCHAR(50) UNIQUE NOT NULL,
    Contrasena VARCHAR(255) NOT NULL,
    Id_Pais INT NOT NULL,
    telefono VARCHAR(20),
    rol VARCHAR(50) NOT NULL,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado SMALLINT NOT NULL DEFAULT 0,
    ultimo_fallo TIMESTAMP NULL,
    ultimo_login TIMESTAMP NULL,
    creado_en TIMESTAMP NULL,
    actualizado_en TIMESTAMP NULL,
    FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais)
);

-- 4. Agencias (Ubicaciones Fijas)
CREATE TABLE Agencias (
    Idagencia SERIAL PRIMARY KEY,
    nombre_agencia VARCHAR(150) NOT NULL,
    direccion TEXT NOT NULL,
    Id_pais INT NOT NULL,
    Id_departamento INT NOT NULL,
    Id_municipio INT NOT NULL,
    Id_distrito INT NULL,
    latitud DECIMAL(10, 8) NULL,
    longitud DECIMAL(11, 8) NULL,
    Id_marca INT NULL,
    activo SMALLINT NOT NULL DEFAULT 1,
    motivo_deshabilitacion TEXT NULL,
    deshabilitado_en TIMESTAMP NULL,
    deshabilitado_por VARCHAR(50) NULL,
    creado_en TIMESTAMP NULL,
    actualizado_en TIMESTAMP NULL,
    FOREIGN KEY (Id_pais) REFERENCES Pais(Id_pais),
    FOREIGN KEY (Id_departamento) REFERENCES Departamento(Id_departamento),
    FOREIGN KEY (Id_municipio) REFERENCES Municipio(Id_municipio),
    FOREIGN KEY (Id_distrito) REFERENCES Distrito(Id_distrito),
    FOREIGN KEY (Id_marca) REFERENCES Marca(Id_marca) ON DELETE SET NULL,
    FOREIGN KEY (deshabilitado_por) REFERENCES Usuario(Codigo_Empleado)
);

-- Relación M:N Agencias - Marcas
CREATE TABLE Agencia_Marca (
    Id_agencia_marca SERIAL PRIMARY KEY,
    Idagencia INT NOT NULL,
    Id_marca INT NOT NULL,
    FOREIGN KEY (Idagencia) REFERENCES Agencias(Idagencia) ON DELETE CASCADE,
    FOREIGN KEY (Id_marca) REFERENCES Marca(Id_marca) ON DELETE RESTRICT,
    UNIQUE (Idagencia, Id_marca)
);

-- Trazabilidad de Auditoría de Agencias
CREATE TABLE Auditoria_Agencias (
    Id_auditoria SERIAL PRIMARY KEY,
    Idagencia INT NOT NULL,
    Codigo_Empleado VARCHAR(50) NOT NULL,
    fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_operacion VARCHAR(30) NOT NULL CHECK (tipo_operacion IN ('CREAR', 'ACTUALIZAR', 'DESHABILITAR')),
    datos_anteriores TEXT NULL,
    datos_nuevos TEXT NULL,
    motivo_deshabilitacion TEXT NULL,
    FOREIGN KEY (Idagencia) REFERENCES Agencias(Idagencia),
    FOREIGN KEY (Codigo_Empleado) REFERENCES Usuario(Codigo_Empleado)
);

-- Control de Visitas de Asesores Comerciales
CREATE TABLE Visitas (
    Id_visita SERIAL PRIMARY KEY,
    Idagencia INT NOT NULL,
    Codigo_Asesor VARCHAR(50) NOT NULL,
    fecha_visita DATE NOT NULL,
    hora_visita TIME NOT NULL,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    observaciones TEXT NULL,
    creado_en TIMESTAMP NULL,
    FOREIGN KEY (Idagencia) REFERENCES Agencias(Idagencia),
    FOREIGN KEY (Codigo_Asesor) REFERENCES Usuario(Codigo_Empleado)
);

-- Índices de rendimiento
CREATE INDEX idx_agencias_pais_activo ON Agencias (Id_pais, activo);
CREATE INDEX idx_auditoria_agencia ON Auditoria_Agencias (Idagencia, fecha_hora);
CREATE INDEX idx_visitas_agencia_fecha ON Visitas (Idagencia, fecha_visita);

-- 5. Notificaciones
CREATE TABLE Notificaciones (
    UniqueID UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    Mensaje TEXT NOT NULL,
    Estado VARCHAR(20) DEFAULT 'Pendiente',
    Codigo_Empleado VARCHAR(50) NOT NULL,
    FOREIGN KEY (Codigo_Empleado) REFERENCES Usuario(Codigo_Empleado) ON DELETE CASCADE
);

-- 6. Trazabilidad: Rutas y Ubicaciones
CREATE TABLE Ruta (
    ID_Ruta SERIAL PRIMARY KEY,
    Hora_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Hora_fin TIMESTAMP
);

CREATE TABLE Ubicaciones_Ruta (
    ID_Ubicacion SERIAL PRIMARY KEY,
    ID_Ruta INT NOT NULL,
    Latitud DECIMAL(10, 8) NOT NULL,
    Longitud DECIMAL(11, 8) NOT NULL,
    Fecha_Hora_Registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_Ruta) REFERENCES Ruta(ID_Ruta) ON DELETE CASCADE
);

-- 7. Asignaciones y Tareas
CREATE TABLE Asignacion (
    ID_asignacion SERIAL PRIMARY KEY,
    ID_Ruta INT NOT NULL,
    Codigo_empleado VARCHAR(50) NOT NULL,
    FOREIGN KEY (ID_Ruta) REFERENCES Ruta(ID_Ruta),
    FOREIGN KEY (Codigo_empleado) REFERENCES Usuario(Codigo_Empleado)
);

CREATE TABLE Tareas (
    Id_tarea SERIAL PRIMARY KEY,
    Nombre VARCHAR(150) NOT NULL,
    Descripcion TEXT,
    Prioridad VARCHAR(20),
    Estado VARCHAR(30) DEFAULT 'Asignada',
    Evidencia TEXT,
    ID_asignacion INT NOT NULL,
    Idagencia INT NOT NULL,
    FOREIGN KEY (ID_asignacion) REFERENCES Asignacion(ID_asignacion) ON DELETE CASCADE,
    FOREIGN KEY (Idagencia) REFERENCES Agencias(Idagencia)
);

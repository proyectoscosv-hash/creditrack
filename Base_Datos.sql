-- 1. Jerarquía Geográfica y Marcas (Tablas sin dependencias)
CREATE TABLE Pais (
    Id_pais SERIAL PRIMARY KEY,
    Pais VARCHAR(100) NOT NULL
);

CREATE TABLE Marca (
    Id_marca SERIAL PRIMARY KEY,
    Marca VARCHAR(100) NOT NULL
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

-- 3. Agencias (Ubicaciones Fijas)
CREATE TABLE Agencias (
    Idagencia SERIAL PRIMARY KEY,
    nombre_agencia VARCHAR(150) NOT NULL,
    direccion TEXT,
    -- NOTA: Aunque en tu diagrama mantienes las 3 relaciones, aquí te las dejo como las tienes[cite: 3].
    -- Si decides seguir la recomendación de normalización, puedes borrar Id_departamento y Municipio.
    Id_departamento INT, 
    Id_municipio INT, 
    Id_distrito INT NOT NULL,
    -- Sugerencia para coordenadas: Si usas PostGIS, cambia esto a 'geometry(POINT)'. 
    -- Si no, usamos DECIMAL exacto para Google Maps.
    cordenadas point,
    Id_marca INT,
    FOREIGN KEY (Id_departamento) REFERENCES Departamento(Id_departamento),
    FOREIGN KEY (Id_municipio) REFERENCES Municipio(Id_municipio),
    FOREIGN KEY (Id_distrito) REFERENCES Distrito(Id_distrito),
    FOREIGN KEY (Id_marca) REFERENCES Marca(Id_marca)
);

-- 4. Usuarios
CREATE TABLE Usuario (
    Codigo_Empleado VARCHAR(50) PRIMARY KEY, -- Asumo alfanumérico. Si es solo números, usa INT.
    Nombre VARCHAR(150) NOT NULL,
    Usuario VARCHAR(50) UNIQUE NOT NULL,
    Contrasena VARCHAR(255) NOT NULL, -- Suficiente para almacenar un hash (ej. bcrypt)
    Id_Pais INT NOT NULL,
    telefono VARCHAR(20),
    rol VARCHAR(50) NOT NULL,
    FOREIGN KEY (Id_Pais) REFERENCES Pais(Id_pais)
);

-- 5. Notificaciones
CREATE TABLE Notificaciones (
    UniqueID UUID DEFAULT gen_random_uuid() PRIMARY KEY, -- Ideal para IDs únicos generados desde la app
    Mensaje TEXT NOT NULL,
    Estado VARCHAR(20) DEFAULT 'Pendiente', -- Ej: 'Pendiente', 'Leída'
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
    Prioridad VARCHAR(20), -- Ej: 'Alta', 'Media', 'Baja'
    Estado VARCHAR(30) DEFAULT 'Asignada', -- Ej: 'Asignada', 'En Proceso', 'Completada'
    Evidencia TEXT, -- URL de la foto o archivo guardado en tu servidor o blob storage
    ID_asignacion INT NOT NULL,
    Idagencia INT NOT NULL,
    FOREIGN KEY (ID_asignacion) REFERENCES Asignacion(ID_asignacion) ON DELETE CASCADE,
    FOREIGN KEY (Idagencia) REFERENCES Agencias(Idagencia)
);
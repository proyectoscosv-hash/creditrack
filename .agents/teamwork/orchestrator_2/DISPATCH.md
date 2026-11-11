# Dispatch Instructions

## 2026-11-11T15:24:13Z

Implementación de un sistema de login y control de acceso para usuarios en producción, identificando y restringiendo accesos según roles y país para garantizar que ningún usuario acceda a información indebida.

Integrity mode: demo
The team must NOT: copy code from existing open-source projects for core logic, run external scripts or delegate execution to other tools, access or modify .env.

## Requirements

### R1. Autenticación y control de acceso por roles y país
Implementar un sistema de inicio de sesión que valide credenciales de usuario contra la base de datos y establezca la sesión identificando el rol y país del usuario, restringiendo el acceso a información no permitida para cada rol y país.

### R2. Bloqueo de seguridad por intentos fallidos
Bloquear el acceso tras 3 intentos fallidos consecutivos de inicio de sesión erróneos.

### R3. Redirección y página de destino
Redirigir al usuario autenticado a `home.php`. Crear `home.php` con una vista sencilla que confirme el inicio de sesión exitoso y el funcionamiento correcto del sistema según el rol y país.

### R4. Interfaz visual e información de autoría
Diseñar la interfaz de login utilizando la paleta de colores: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`. Incorporar en el diseño información agregada de manera manual sobre quienes diseñaron el sistema.

### R5. Restricción de configuración del entorno
El equipo tiene estrictamente prohibido acceder, leer o modificar el archivo `.env`. La configuración y conexión a la base de datos debe resolverse sin tocar dicho archivo.

## Acceptance Criteria

### Autenticación y Control de Acceso
- [ ] Las credenciales de usuario se validan contra la base de datos durante el inicio de sesión.
- [ ] La sesión iniciada almacena y valida el rol y país del usuario, restringiendo el acceso a información que no le corresponde.
- [ ] Al ingresar credenciales erróneas por 3 veces consecutivas, el sistema bloquea los intentos de inicio de sesión.
- [ ] Un inicio de sesión exitoso redirige automáticamente a `home.php`.
- [ ] `home.php` existe y muestra una confirmación del funcionamiento y estado de la sesión.

### Diseño y Presentación
- [ ] La interfaz de usuario implementa los colores de la paleta: `#BF0426`, `#0511F2`, `#023373`, `#A61717`, `#8D253C`.
- [ ] La interfaz incluye visiblemente la información de los diseñadores del sistema ingresada de forma manual.

### Integridad y Restricciones
- [ ] El archivo `.env` no es accedido, leído ni modificado en ningún momento.
- [ ] No se copia código de proyectos de código abierto existentes para la lógica central.
- [ ] No se ejecutan scripts externos ni se delega la ejecución a herramientas externas.

### Verificación
- [ ] Existen casos o pruebas verificables que validen el inicio de sesión exitoso, el rechazo de credenciales incorrectas, el bloqueo tras 3 intentos fallidos y la restricción por roles y país.

Working directory: c:\xampp\htdocs\creditrack
State folder: c:\xampp\htdocs\creditrack\.agents\teamwork\orchestrator_2
Original request file: c:\xampp\htdocs\creditrack\.agents\teamwork\ORIGINAL_REQUEST.md

Resumption context:
The predecessor orchestrator completed Milestone 1 (Database & Models) and Milestone 2 (Auth Controllers, Filters, Routes) and passed all quality gates.
Milestone 3 (Views & UI: login.php, home.php, color palette, manual authors) was implemented and was in gate verification.
The E2E Test Track published TEST_READY.md with 78 tests across 4 tiers.
Review PROJECT.md, TEST_READY.md, and existing code in app/Controllers, app/Models, app/Views, and app/Filters. Verify that home.php and login.php fulfill all requirements, run the test suites / verification, complete Phase 3, and report completion.

# Guía de Formato de Commits (Conventional Commits + Emojis)

Esta guía define el estándar para la redacción de mensajes de commit en el repositorio, combinando la especificación **[Conventional Commits v1.0.0](https://www.conventionalcommits.org/)** con **Gitmoji**.

---

## 1. Estructura General

Cada mensaje de commit debe seguir la siguiente estructura:

```text
<emoji> <tipo>(<alcance opcional>): <descripción corta>
<LÍNEA EN BLANCO OBLIGATORIA>
<descripción extensa y detallada de los cambios>
<LÍNEA EN BLANCO OPCIONAL>
<pie de página / referencias (opcional)>
```

---

## 2. Reglas del Título (Header)

> [!IMPORTANT]
> **Límite de longitud**: El título completo (emoji + tipo + alcance + descripción) no debe superar los **50 caracteres**.

* **Emoji al inicio**: Debe comenzar con el emoji correspondiente al tipo de cambio seguido de un espacio.
* **Formato**: `<emoji> <tipo>(<alcance>): <descripción>`
* **Modo imperativo**: Redacta en presente imperativo (ej. `agrega`, `corrige`, `elimina`).
* **Minúsculas**: La descripción inicia en minúscula (salvo nombres propios o siglas).
* **Sin punto final**: Nunca termines el título con un punto (`.`).

### Tabla de Emojis y Tipos

| Emoji | Tipo | Propósito | Ejemplo de Título |
| :---: | :--- | :--- | :--- |
| ✨ | `feat` | Nueva funcionalidad | `✨ feat(auth): agregar login con Google` |
| 🐛 | `fix` | Corrección de errores (*bug fix*) | `🐛 fix(jwt): corregir expiración de token` |
| ♻️ | `refactor` | Refactorización de código existente | `♻️ refactor(db): migrar consultas a ORM` |
| ⚡️ | `perf` | Mejora de rendimiento | `⚡️ perf(search): optimizar índice de usuarios` |
| 📝 | `docs` | Modificación en documentación | `📝 docs(api): actualizar endpoint de pagos` |
| 🎨 | `style` | Formato, comas, espacios (sin cambio lógico) | `🎨 style(theme): formatear variables CSS` |
| 🧪 | `test` | Añadir o corregir pruebas unitarias/E2E | `🧪 test(loans): agregar tests de amortización` |
| 📦 | `build` | Dependencias externas o empaquetado | `📦 build: actualizar versión de Composer` |
| 👷 | `ci` | Configuración de CI/CD (GitHub Actions, etc.) | `👷 ci: configurar pipeline de pruebas` |
| 🔧 | `chore` | Tareas generales de mantenimiento | `🔧 chore(env): actualizar variables de ejemplo` |
| ⏪️ | `revert` | Revertir un commit anterior | `⏪️ revert(pay): revertir pasarela de pago` |
| 🔒️ | `security` | Corrección o mejora de seguridad | `🔒️ security: sanitizar inputs en formulario` |

---

## 3. Reglas de la Descripción Extensa (Body)

> [!NOTE]
> El cuerpo del commit es **obligatorio para cambios relevantes** y debe responder a **por qué se hizo el cambio**, **qué detalles técnicos involucra** y **cuál es su impacto**.

* **Separación**: Debe existir una línea en blanco obligatoria entre el título y el cuerpo.
* **Profundidad**: No repetir el título; explicar la causa raíz, la alternativa elegida y las partes afectadas.
* **Estructura recomendada**:
  1. **Motivación / Contexto**: ¿Qué problema ocurría o qué necesidad de negocio motivó el cambio?
  2. **Detalles técnicos**: Cambios clave en clases, servicios, esquemas o algoritmos.
  3. **Efectos colaterales / Requisitos**: ¿Requiere migraciones, nuevas variables en `.env`, dependencias adicionales?
* Puedes utilizar listas de viñetas (`-` o `*`) para mayor legibilidad.

---

## 4. Pie de Página (Footer)

Se utiliza para vincular tareas y advertir sobre rupturas de compatibilidad:
* **Breaking Changes**: Usa `BREAKING CHANGE: <descripción del impacto y migración>`.
* **Tickets / Issues**: Referencia a historias o incidentes (ej. `Closes #123`, `Refs #45`).

---

## 5. Ejemplos Completos

### Ejemplo 1: Corrección de error (`fix`)

```text
🐛 fix(auth): corregir expiración de JWT en login

Se detectó que los tokens JWT generados tras el inicio de sesión
expiraban inmediatamente debido a una discrepancia entre la zona
horaria del servidor de base de datos y la del servidor de aplicación.

Detalles de la solución:
- Se forzó el uso de UTC para la emisión y verificación del claim 'exp'.
- Se incorporó un margen de tolerancia (leeway) de 60 segundos en AuthService
  para absorber desfases menores de reloj entre microservicios.
- Se implementaron tests unitarios simulando distintos husos horarios.

Fixes #142
```

### Ejemplo 2: Nueva funcionalidad (`feat`)

```text
✨ feat(loans): calcular tabla de amortización

Se implementa el cálculo de amortizaciones con cuota fija (sistema francés)
para solicitudes de crédito en estatus aprobado.

Detalles técnicos:
- Creación de AmortizationService para calcular capital e intereses mensuales.
- Endpoint POST /api/loans/{id}/schedule para persistir el plan de pagos.
- Validación contra la tasa activa y reglas de redondeo de dos decimales.

Requiere ejecutar migración: `php artisan migrate`

Refs #89
```

### Ejemplo 3: Refactorización (`refactor`)

```text
♻️ refactor(db): migrar consultas de clientes a ORM

Las consultas SQL directas en CustomerRepository acumulaban lógica
duplicada y complicaban el mantenimiento de filtros de auditoría.

Cambios realizados:
- Sustitución de sentencias nativas por el Query Builder y modelos Eloquent.
- Optimización con eager loading ('with') para prevenir consultas N+1.
- Centralización de scopes de búsqueda por estatus y fecha de alta.
```

---

## 6. Plantilla Base para Copiar y Pegar

```text
<emoji> <tipo>(<alcance>): <título en máx 50 carácteres>

Motivación / Contexto:
- ¿Qué problema existía o qué objetivo se busca cumplir?

Detalles de los cambios:
- Cambio 1: descripción técnica del ajuste.
- Cambio 2: archivos, modelos o servicios modificados.
- Cambio 3: pruebas o validaciones agregadas.

Impacto / Requisitos:
- Variables de entorno (.env), migraciones o dependencias necesarias.

Refs #TICKET
```

---

## 7. Lista de Verificación Rápida

- [ ] ¿Inicia con el **emoji adecuado**?
- [ ] ¿El título completo tiene **50 caracteres o menos**?
- [ ] ¿Existe una **línea en blanco** entre el título y el cuerpo?
- [ ] ¿El cuerpo explica detalladamente el **contexto** y la **solución técnica**?
- [ ] ¿Se incluyen referencias a issues o tickets si aplica?


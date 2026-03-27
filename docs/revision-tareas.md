# Revisión del código base: tareas propuestas

## 1) Tarea de corrección de error tipográfico
**Título sugerido:** Corregir texto con codificación dañada en comentarios de administración.

- **Problema detectado:** En `admin/class-admin.php` aparece el comentario `LocalizaciÃ³n base`, lo que evidencia un problema de codificación/acentuación.
- **Impacto:** Dificulta la lectura y deja señales de inconsistencia editorial en el código.
- **Trabajo propuesto:**
  - Corregir el comentario a `Localización base`.
  - Verificar si hay más cadenas con mojibake en el repositorio y normalizar codificación UTF-8.
- **Criterios de aceptación:**
  - No quedan ocurrencias de texto mojibake en comentarios y cadenas visibles del plugin.

## 2) Tarea de corrección de falla (bug)
**Título sugerido:** Cargar el text domain en runtime para habilitar traducciones.

- **Problema detectado:** Existe `load_textdomain()` en `includes/class-core.php`, pero no está enganchado a ningún hook.
- **Impacto:** Las traducciones en `languages/` pueden no cargarse nunca, afectando i18n.
- **Trabajo propuesto:**
  - Registrar `load_textdomain()` en un hook apropiado (por ejemplo `plugins_loaded` o durante `init` en el flujo de arranque).
  - Validar que el dominio `pcc-woootec-chile` cargue correctamente archivos `.mo`.
- **Criterios de aceptación:**
  - El text domain se carga en un entorno WordPress real y las cadenas traducibles responden al locale activo.

## 3) Tarea de corrección de discrepancia en comentarios/documentación
**Título sugerido:** Alinear README con la estructura real del plugin.

- **Problema detectado:** El `README.md` documenta carpetas/clases (`core/`, `class-hooks.php`, `pcc-woootec-moodle.php`) que no coinciden con el árbol actual (`includes/`, `public/class-frontend.php`, `pcc-woootec-chile.php`).
- **Impacto:** Onboarding más lento y mayor probabilidad de errores de mantenimiento.
- **Trabajo propuesto:**
  - Actualizar la sección “Estructura del Plugin” para reflejar rutas y nombres reales.
  - Ajustar ejemplos de instalación/uso para el nombre actual del plugin.
- **Criterios de aceptación:**
  - El árbol del README coincide con `rg --files` en el repositorio.

## 4) Tarea para mejorar una prueba
**Título sugerido:** Agregar pruebas automáticas para hooks críticos del core.

- **Problema detectado:** No se observa suite de tests automatizados en el repositorio.
- **Impacto:** Riesgo de regresiones en inicialización, hooks e integración básica.
- **Trabajo propuesto:**
  - Añadir una base de pruebas (PHPUnit + WP test framework).
  - Crear una prueba para verificar que `PCC_WooOTEC_Pro_Core::boot()` registra al menos los hooks `init` y `before_woocommerce_init`.
  - Crear una prueba que falle si `load_textdomain()` queda sin hook.
- **Criterios de aceptación:**
  - Ejecutar tests localmente y verificar casos positivos/negativos de registro de hooks.

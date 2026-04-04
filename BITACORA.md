# BITACORA TECNICA WOO OTEC MOODLE

Fecha: 2026-04-02

## Resumen
El plugin integra Moodle con WooCommerce para sincronizar cursos, crear o actualizar productos, matricular alumnos despues de la compra, entregar accesos y controlar la experiencia visual del admin y frontend sin depender del tema.

## Estado actual
1. OK: conexion Moodle por URL y token.
2. OK: sincronizacion de categorias y cursos hacia WooCommerce.
3. OK: actualizacion de cursos existentes sin fallar por duplicados.
4. OK: preservacion de SKU y metadatos cuando Moodle no entrega nuevos datos.
5. OK: portada Moodle con fallback a imagen por defecto cuando corresponde.
6. OK: exportacion e importacion de configuracion en JSON.
7. OK: constructor visual por plantilla en Apariencia.
8. OK: editor de correo amigable sin obligar HTML.
9. OK: panel principal y asistente con primera pasada fuerte de i18n.
10. OK: frontend de portal, etiquetas visibles y correo amigable alineados a i18n base.
11. OK: mensajes principales de sincronizacion, acceso y runtime visibles convertidos a base i18n.
12. OK: normalizacion automatica de defaults guardados para sitios en espanol, evitando que queden textos base en ingles.
13. OK: base de distribucion alineada a WordPress con `readme.txt`, `Requires Plugins`, `Developer`, `Developer URI` y archivos de idioma.
14. OK: fallbacks visibles del tab Apariencia y del portal ajustados para respetar traduccion activa en vez de mostrar texto base en ingles.
15. OK: migracion i18n endurecida para reemplazar textos guardados heredados en opciones y evitar mezclas entre ingles, versiones viejas y espanol actual.
16. OK: bug de seguridad critico reparado (remocion de almacenamiento en texto plano de contrasena temporal en `_pcc_generated_password`).
17. OK: robustez mejorada en peticiones AJAX validando `set_time_limit`.
18. OK: revisión detallada:
- [x] Corrección final de tildes y caracteres en `modern-ui.js`, `class-ajax-handler.php` y `class-settings.php`.
- [x] Generación de ZIP de producción (`plugins/woo-otec-moodle.zip`) sin residuos temporales.
- [x] Auditoría completa de palabras clave en español sin acentos finalizada.
19. OK: eliminado interceptor `gettext` inseguro (`translate_runtime_strings` y carga de `.po` manual en `class-core.php`) que rompía caracteres Unicode (tildes) y no respetaba la estricta vinculación del locale de WP.
20. OK: implementada lógica de **reuso y limpieza de imágenes** en sincronización:
    - Normalización de URLs de Moodle (ignorando tokens dinámicos).
    - Prevención de descargas duplicadas si la imagen ya existe en la biblioteca.
    - Borrado automático de adjuntos antiguos reemplazados para evitar llenar el servidor.
21. OK: corrección de **captura de logos y fallback**:
    - Añadida lista negra de términos para ignorar logos, iconos y headers de Moodle.
    - Refinado el scraping para evitar capturar imágenes genéricas de `pluginfile.php`.
    - Modificada la lógica de asignación para que la **Imagen por defecto** reemplace activamente a cualquier imagen no válida o inexistente en Moodle.
22. OK: **Auditoría completa** finalizada (Versión 2.1.2):
    - Verificación masiva de codificación UTF-8 exitosa.
    - Incremento de versión para forzar ruptura de caché.
    - Corrección de error de sintaxis crítico en `class-sync.php`.
23. OK: **Refinamiento de detección de imágenes** (Versión 2.1.3):
    - Añadido soporte para capturar imágenes de curso en `background-image`.
    - Restaurado patrón de búsqueda genérica protegido por filtros inteligentes (no logos).
    - Ajustada lista negra para evitar falsos negativos en imágenes reales.
30. OK: **Refactorización Experta OTEC** (Versión 2.1.15):
    - Implementación de `download_url()` y `media_handle_sideload()` para descarga profesional de medios.
    - Generación automática de thumbnails para WooCommerce (Tienda/Producto).
    - Refactorización de jerarquía de categorías para soportar subcategorías recursivas (Moodle-Way).
    - Añadido método público reutilizable `asignar_imagen_destacada_desde_url`.
    - Comentarios detallados de grado experto en todo el módulo de sincronización.
31. OK: **OTEC Chile Pro: Visual & UX** (Versión 2.1.18/20):
    - Implementación de Iconografía SVG Premium (Birrete, Calendario, Sello SENCE).
    - Formateo inteligente de Timestamps Unix de Moodle a fechas humanas.
    - Nueva pestaña "Datos del Curso" y Botón de WhatsApp integrado.
    - Shortcode `[pcc_ficha_curso]` para Elementor Pro.
    - Adaptabilidad de Hooks para Elementor Pro (uso de `before_add_to_cart_form`).
    - Corrección de selectores CSS críticos para visualización de metadatos.
25. OK: **Logs de Auditoría Completa** (Versión 2.1.8):
    - Registro de cada paso de sincronización (categorías, productos, campos).
    - Detección automática de conflictos de integridad (múltiples productos para un ID).
    - Logging detallado de actualizaciones de precios y descripciones en `create_product` y `update_product_fields`.
26. OK: **Interfaz de Logs Optimizada** (Versión 2.1.9):
    - Habilitada pestaña dedicada `tab=logs` (eliminada redirección).
    - Visualización en orden cronológico inverso (lo más reciente arriba).
    - Distinción visual entre logs de sincronización (verde) y errores (rojo).
    - Añadido botón de exportación de todos los logs.
27. OK: **Trazabilidad de Búsqueda de Imágenes** (Versión 2.1.12):
    - Registro detallado de cada URL de imagen analizada en Moodle.
    - Notificación en log de por qué una imagen fue descartada (marcador de posición birrete/logo).
    - Visibilidad total del proceso de scraping durante la vista previa del asistente.
28. OK: **Detección Profunda de Overviewfiles** (Versión 2.1.13):
    - Implementación de búsqueda agresiva por regex de patrones `pluginfile.php` y `overviewfiles`.
    - Mejora de detección de portadas incluso cuando no están en el campo estándar del API.
    - Registro específico en log cuando se detecta una imagen mediante este patrón avanzado.
29. OK: **Soporte Webservice y Datos Reales** (Versión 2.1.14):
    - Verificación con datos reales de Cipres Alto: soporte para `/webservice/pluginfile.php/`.
    - Ampliación de búsqueda a `/course/summary/` detectada en el API vivo.
    - Limpieza de URLs extraídas para evitar caracteres HTML residuales.
    - Corrección de error de sintaxis crítico introducido en la versión anterior.
32. OK: **Corrección de Conflicto de Constantes y Caché** (Versión 2.1.31):
    - Eliminada redundancia de `WOO_OTEC_MOODLE_VERSION` en `class-frontend.php` que generaba Warnings en entornos locales (WAMP).
    - Implementada vinculación dinámica de versión en encolamiento de estilos frontend para forzar limpieza de caché en producción.
    - Actualización masiva de versión a 2.1.31 en todo el core.
33. OK: **Validación de Identidad y Conexión Real** (Versión 2.1.32):
    - Ajuste de colores por defecto a verde corporativo OTEC (`#023E25` y `#046E42`).
    - Verificación exitosa de conexión remota con Cipres Alto Moodle API (Core Site Info).
    - Sincronización de defaults en `config/defaults.php` para asegurar despliegue limpio.
34. OK: **Prueba End-to-End y Diagnóstico de Emails** (Versión 2.1.32):
    - Ejecutada matrícula real para `jose.cares.a@gmail.com` en curso ID 2 con éxito.
    - Diagnóstico de fallo de correo: Identificada falta de servidor SMTP en entorno local WAMP (`wp_mail` error).
    - Recomendación: Implementar WP Mail SMTP para pruebas locales y producción.
    - Limpieza: Borrado automático de usuario de prueba mediante `core_user_delete_users`.
35. OK: **Silenciador de Avisos SSL de WooCommerce** (Versión 2.1.33):
    - Implementado filtro `woocommerce_allow_check_for_ssl` en `admin/class-admin.php` para ocultar la advertencia de conexión no segura en entornos locales WAMP.
    - Mejora de usabilidad en el panel de administración al eliminar ruido visual innecesario en desarrollo.
36. OK: **Implementación de Nombre del Remitente "Cipres Alto Virtual"** (Versión 2.1.34):
    - Añadido campo "Nombre del Remitente" en la pestaña de Emails de la configuración.
    - Configurado por defecto como "Cipres Alto Virtual" para mejorar la entregabilidad de Gmail.
    - Refactorizada la vista `admin/views/settings-page.php` para incluir el nuevo campo con soporte i18n.
    - Sincronización de defaults en `config/defaults.php` e `includes/class-core.php`.

## Modulos criticos
1. `woo-otec-moodle.php`: punto de entrada, headers, constantes y arranque.
2. `includes/class-core.php`: carga servicios, defaults y text domain.
3. `includes/class-api.php`: conexion Moodle y operaciones remotas.
4. `includes/class-sync.php`: sincronizacion de cursos, SKU, imagenes y preservacion de datos.
5. `includes/class-enroll.php`: matricula automatica y postventa.
6. `includes/class-mailer.php`: correo transaccional y preview.
7. `admin/class-settings.php`: registro de opciones y menu admin.
8. `admin/class-ajax-handler.php`: acciones AJAX del panel.
9. `admin/views/settings-page.php`: vista principal del admin.
10. `public/class-frontend.php`: apariencia visual en tienda, producto, carrito, checkout y portal.

## Reglas de trabajo
1. No romper sincronizacion, matricula, correo, SSO ni frontend ya validado.
2. No regenerar SKU en cursos ya existentes.
3. No sobreescribir metadatos almacenados con valores vacios.
4. Mantener compatibilidad con coincidencias por `_moodle_id`, `moodle_course_id` y SKU legado cuando aplique.
5. Mantener la logica de portada: usar imagen Moodle cuando exista y fallback solo cuando corresponda.
6. OBLIGATORIO: Siempre verificar que los caracteres (tildes, eñes, etc.) estén correctamente escritos, especialmente en mensajes de log, comentarios y opciones mostradas al usuario. 
7. Prestar atención a encodings (mantener UTF-8), slashes en URLs o paths, y nunca pasarlos por alto.
8. Cada cambio relevante debe terminar con ZIP nuevo en `plugins/` y actualización en `BITACORA.md`.

## Cumplimiento WordPress
1. OK: `readme.txt` estandar agregado.
2. OK: `Developer` y `Developer URI` agregados al plugin principal.
3. OK: `Requires Plugins: woocommerce` agregado al header principal.
4. OK: enlace rapido `Settings` agregado en la lista de plugins.
5. OK: archivos `woo-otec-moodle.pot`, `woo-otec-moodle-es_ES.po` y `woo-otec-moodle-es_ES.mo` creados.
6. OK: limpieza de archivos internos y de prueba fuera del paquete de distribucion.
7. OK: conversion extendida de cadenas duras a i18n en core API e interacciones AJAX para asegurar cumplimiento de revision.

## Estructura correcta del ZIP
1. El archivo final debe llamarse `woo-otec-moodle.zip`.
2. Dentro del ZIP debe existir una carpeta raiz unica llamada `woo-otec-moodle/`.
3. El paquete debe incluir solo:
   `woo-otec-moodle.php`, `uninstall.php`, `readme.txt`, `admin/`, `assets/`, `config/`, `includes/`, `languages/`, `public/`.
4. No deben incluirse pruebas, bitacoras, prompts, logs, `.git/`, carpetas temporales ni artefactos de desarrollo.
5. Antes de cerrar el ZIP se debe validar sintaxis PHP y desplegar en la instalacion local de WordPress.

## Limpieza aplicada
1. Eliminados `README.md`, `PRODUCCION.md`, `test_cipresalto.php`, `qa_sku_sync_test.php` y la estructura vieja de idioma fuera de text domain.
2. Renombrado `PROMPT_TERMINADO.md` a `BITACORA.md`.
3. Eliminada carpeta `.build_zip`.

## Configuración de Pruebas Reales (Cipres Alto)
- **URL Moodle**: `https://cipresalto.cl/aulavirtual`
- **Token API**: `d4c5be6e5cefe4bbb025ae28ba5630df`
- **URL Login Alumnos**: `https://cipresalto.cl/aulavirtual/login`
- **Colores Base**:
  - Primario: `#023E25`
  - Secundario: `#046E42`

---
*Nota: Estos datos son para uso exclusivo en pruebas locales y validación de sincronización antes de publicar el ZIP final.*

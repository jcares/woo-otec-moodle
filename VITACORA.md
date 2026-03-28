# VITACORA TECNICA WOO OTEC MOODLE

Fecha: 2026-03-28

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
12. OK: base de distribucion alineada a WordPress con `readme.txt`, `Requires Plugins`, `Developer`, `Developer URI` y archivos de idioma.

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
6. Cada cambio relevante debe terminar con ZIP nuevo en `plugins/` y despliegue en WordPress local.

## Cumplimiento WordPress
1. OK: `readme.txt` estandar agregado.
2. OK: `Developer` y `Developer URI` agregados al plugin principal.
3. OK: `Requires Plugins: woocommerce` agregado al header principal.
4. OK: enlace rapido `Settings` agregado en la lista de plugins.
5. OK: archivos `woo-otec-moodle.pot`, `woo-otec-moodle-es_ES.po` y `woo-otec-moodle-es_ES.mo` creados.
6. OK: limpieza de archivos internos y de prueba fuera del paquete de distribucion.
7. Pendiente: seguir convirtiendo el resto de cadenas duras del codigo a i18n para una cobertura mas completa.

## Estructura correcta del ZIP
1. El archivo final debe llamarse `woo-otec-moodle.zip`.
2. Dentro del ZIP debe existir una carpeta raiz unica llamada `woo-otec-moodle/`.
3. El paquete debe incluir solo:
   `woo-otec-moodle.php`, `uninstall.php`, `readme.txt`, `admin/`, `assets/`, `config/`, `includes/`, `languages/`, `public/`.
4. No deben incluirse pruebas, bitacoras, prompts, logs, `.git/`, carpetas temporales ni artefactos de desarrollo.
5. Antes de cerrar el ZIP se debe validar sintaxis PHP y desplegar en la instalacion local de WordPress.

## Limpieza aplicada
1. Eliminados `README.md`, `PRODUCCION.md`, `test_cipresalto.php`, `qa_sku_sync_test.php` y la estructura vieja de idioma fuera de text domain.
2. Renombrado `PROMPT_TERMINADO.md` a `VITACORA.md`.
3. Eliminada carpeta `.build_zip`.

## Siguiente foco
1. Seguir ampliando i18n en clases y vistas restantes.
2. Revisar logging y estandares de WordPress/WooCommerce para aproximar aun mas el plugin a revisiones de marketplace.

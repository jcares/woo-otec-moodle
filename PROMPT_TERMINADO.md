# PROMPT TERMINADO - BITACORA TECNICA WOO OTEC MOODLE

Fecha: 2026-03-28

## Resumen Ejecutivo
Se actualizaron las vistas del plugin y la logica de sincronizacion Moodle -> WooCommerce para dejar una experiencia mas limpia en admin, una sincronizacion mas robusta para cursos existentes y una presentacion visual mas consistente en correos, metadatos y paneles.

## Etapas en funcionamiento (OK)
1. ✅ Conexion Moodle por URL + token y consumo API.
2. ✅ Sincronizacion de categorias y cursos a WooCommerce.
3. ✅ Deteccion de cursos existentes por `_moodle_id`, `moodle_course_id` y compatibilidad con SKU legado.
4. ✅ Actualizacion de cursos existentes en vez de fallar por duplicados.
5. ✅ Sincronizacion de portada desde Moodle con fallback a imagen por defecto cuando corresponde.
6. ✅ Guia rapida con estados dinamicos visible solo en la pagina de sincronizacion.
7. ✅ Eliminacion de la grilla duplicada inferior del admin.
8. ✅ Editor de correo amigable sin necesidad de editar HTML, con colores y logo configurable.
9. ✅ Plantilla de metadatos modernizada, respetando campos activados y colores personalizados.
10. ✅ Saneo general de textos y codificacion en vistas criticas del admin y frontend.
11. ✅ SKU nuevo para cursos creados: formato `OTEC-YYMMID`.
12. ✅ Cursos ya existentes conservan su SKU y sus datos previos cuando Moodle no envia informacion nueva.
13. ✅ Opcion de exportar plugin removida del panel admin.
14. ✅ Nueva exportacion de configuracion actual en JSON con conexion Moodle, parametros, apariencia, correo, mappings y campos activos.
15. ✅ Nueva importacion de configuracion desde JSON para restaurar parametros en nuevas instalaciones.

## Etapas pendientes (POR CERRAR)
1. Pendiente: prueba visual final en WordPress admin con cursos reales ya sincronizados.
2. Pendiente: validacion visual final de cursos con imagen Moodle y cursos sin imagen.
3. Pendiente: prueba final de correo real desde configuracion usando logo automatico y logo manual.

## Bugs detectados y fix aplicados en esta ronda
1. ✅ Error por curso ya creado durante sincronizacion: corregido para actualizar en vez de rechazar.
2. ✅ Deteccion incompleta de coincidencias: corregida usando metas y compatibilidad con SKU legado.
3. ✅ Falta de logica de portada: corregida con multiples fuentes de imagen y fallback controlado.
4. ✅ Duplicidad visual de la guia rapida: corregida dejando una sola grilla en sincronizacion.
5. ✅ Textos con caracteres corruptos: saneados en archivos clave.
6. ✅ SKU antiguo para cursos nuevos: reemplazado por `OTEC-YYMMID`.
7. ✅ Sobrescritura de datos con valores vacios: corregida para conservar informacion existente.
8. ✅ Exportacion del plugin desde admin: reemplazada por exportacion de configuracion reutilizable para nuevas instalaciones.
9. ✅ Falta de restauracion rapida de parametros: corregida con importacion de configuracion JSON desde admin.

## Validacion realizada
1. ✅ Curso existente probado con resultado `updated`.
2. ✅ SKU existente preservado sin regeneracion.
3. ✅ Metadatos existentes preservados cuando Moodle envia datos vacios o incompletos.
4. ✅ Curso nuevo de prueba creado con SKU esperado `OTEC-YYMMID`.

## Modulos criticos del plugin
1. `woo-otec-moodle.php`: punto de entrada del plugin, define constantes, activacion y arranque del nucleo.
2. `includes/class-core.php`: nucleo principal, carga dependencias, opciones, hooks globales y servicios compartidos.
3. `includes/class-api.php`: capa de conexion con Moodle REST, manejo de errores, pruebas de conexion y operaciones de usuarios/cursos.
4. `includes/class-sync.php`: modulo mas sensible del negocio; sincroniza categorias y cursos, detecta existentes, preserva datos, genera SKU y gestiona imagenes.
5. `includes/class-enroll.php`: procesa ordenes WooCommerce, crea o reutiliza usuarios Moodle, matricula alumnos, agenda reintentos y envia correos.
6. `includes/class-mailer.php`: genera el correo final, resuelve plantilla amigable o HTML custom, logo, remitente y envio.
7. `includes/class-sso.php`: construye y guarda URLs de acceso para alumno y pedidos.
8. `admin/class-settings.php`: registra opciones del plugin y centraliza helpers del panel.
9. `admin/class-ajax-handler.php`: concentra AJAX de sincronizacion, vista previa, pruebas, export logs y generacion de ZIP.
10. `admin/views/settings-page.php`: vista principal del panel; contiene gran parte de la UX del plugin.
11. `public/class-frontend.php`: adapta la experiencia visual del curso, metadatos, shortcode de mis cursos y textos de tienda/carrito/checkout.
12. `config/defaults.php`: valores base de configuracion y defaults funcionales del plugin.

## Zonas de riesgo antes de modificar
1. Cambios en `includes/class-sync.php` pueden romper deteccion de cursos existentes, SKU, preservacion de metadatos, actualizacion de productos e imagenes.
2. Cambios en `includes/class-enroll.php` pueden romper la matricula automatica, reintentos, guardado de accesos y envio de correos.
3. Cambios en `includes/class-api.php` pueden afectar conexion Moodle, permisos de funciones webservice y lectura de datos.
4. Cambios en `admin/views/settings-page.php` o `assets/admin/js/modern-ui.js` pueden romper el flujo del panel y del sincronizador por pasos.
5. Cambios en `public/class-frontend.php` pueden afectar la plantilla personalizada de producto y la visualizacion de metadatos.
6. Cambios en opciones o defaults pueden alterar comportamiento ya validado aunque el codigo compile sin errores.
7. Cambios en el generador ZIP pueden producir paquetes incompletos o incluir archivos que no corresponden a distribucion.

## Reglas obligatorias para futuros cambios
1. No romper el flujo validado de conexion Moodle, sincronizacion, matricula, correo, SSO, frontend y panel admin.
2. Si se modifica sincronizacion, validar siempre cursos nuevos y cursos existentes.
3. No regenerar SKU de cursos ya creados.
4. No sobreescribir metadatos almacenados con valores vacios o incompletos provenientes de Moodle.
5. Mantener compatibilidad con coincidencias por `_moodle_id`, `moodle_course_id` y SKU legado cuando aplique.
6. Mantener la logica de portada: usar imagen Moodle cuando exista, conservar la actual si ya hay una, y usar fallback por defecto solo cuando corresponda.
7. Hacer cambios incrementales y compatibles con la estructura actual; evitar refactors amplios sin una razon tecnica clara.
8. Antes de cerrar cambios, revisar impacto en admin, sincronizacion y frontend aunque el ajuste sea visual.

## Regla de exportacion de configuracion
1. La opcion visible en admin debe ser exportar configuracion actual, no exportar el plugin.
2. El archivo exportado debe incluir conexion Moodle, parametros guardados, apariencia, correo, mappings y campos activos para reutilizar la configuracion en otra instalacion.
3. El formato recomendado de exportacion es JSON legible y portable.
4. Debe existir tambien la contraparte de importacion para restaurar esa configuracion en una nueva instalacion.
5. Si en el futuro se vuelve a generar ZIP del plugin, este debe incluir solo archivos runtime y excluir archivos de prueba, prompts, bitacoras y artefactos de desarrollo.

## Lineamientos para proximos cambios
1. Mantener la arquitectura actual del plugin sin romper el flujo operativo ya validado.
2. Revisar siempre impacto en sincronizacion de cursos existentes, preservacion de SKU, metadatos guardados e imagenes de portada.
3. Priorizar cambios incrementales y compatibles con la estructura actual.
4. Toda mejora futura debe conservar el comportamiento ya validado para cursos nuevos y cursos existentes.
5. Mantener disponible la exportacion e importacion de configuracion actual para facilitar nuevas instalaciones sin reconfiguracion manual.
6. En cada cambio relevante generar ZIP actualizado en la carpeta `plugins/`.
7. En cada cambio relevante actualizar el plugin en la instalacion local de WordPress para validacion.

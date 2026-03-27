# Estado de Produccion - Woo OTEC Moodle

Fecha: 2026-03-26

## Realizado
- Corregido contenedor de servicios para `logger` y carga de traducciones en `includes/class-core.php`.
- Ajustada la sincronizacion para guardar fechas como timestamp en `includes/class-sync.php`.
- Corregida la vista "Mis cursos" para soportar fechas en string o timestamp en `public/class-frontend.php` y `public/templates/my-courses.php`.
- Endurecida la generacion de ZIP para excluir `plugins` y `test_cipresalto.php` en `admin/class-ajax-handler.php`.
- Agregada deteccion de imagen de portada desde Moodle (overviewfiles/HTML del curso) con guardado en WP y fallback a imagen por defecto en `includes/class-sync.php`.
- Se agrego fallback de descarga y guardado en `uploads` cuando falla `media_handle_sideload` en `includes/class-sync.php`.
- Se fuerza la asignacion de imagen destacada usando `set_post_thumbnail` y `WC_Product::set_image_id` en `includes/class-sync.php`.

## Pendiente
- Probar sincronizacion completa en staging con un curso real y revisar logs.
- Verificar permisos de `core_enrol_get_enrolled_users` y `enrol_manual_enrol_users` en Moodle.
- Validar correo de acceso real (plantilla, remitente, SPF/DKIM).
- Revisar codificacion de textos (tildes) en toda la UI para evitar caracteres corruptos.

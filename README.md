# woo-otec-moodle
Plugin profesional que integra WooCommerce con Moodle para la gestión automatizada de cursos OTEC, inscripción de alumnos y sincronización académica en tiempo real.

🚀 Woo OTEC Moodle.

Integración profesional entre WooCommerce y Moodle para OTEC
Automatiza la venta, inscripción y gestión de cursos e-learning en una sola plataforma.

🧩 Descripción

Woo OTEC Moodle PRO es un plugin avanzado que conecta WooCommerce con Moodle, permitiendo vender cursos online y gestionar automáticamente la creación de usuarios, matriculación y sincronización académica.

Diseñado específicamente para OTEC, academias y plataformas e-learning, elimina procesos manuales y centraliza toda la operación en WordPress.

⚡ Características Principales

✔ Integración completa WooCommerce → Moodle
✔ Creación automática de usuarios en Moodle
✔ Matriculación automática en cursos
✔ Sincronización en tiempo real
✔ Wizard de configuración profesional (Setup inicial)
✔ Logs detallados de operaciones
✔ Manejo de roles (estudiantes, instructores)
✔ Configuración de precios por defecto
✔ Asignación automática de instructor
✔ Compatible con entornos productivos
✔ Arquitectura modular y escalable

🎯 Casos de Uso
OTEC que venden cursos online
Academias con Moodle autogestionado
Plataformas e-learning con WooCommerce
Venta de capacitaciones certificadas

🏗️ Estructura del Plugin
pcc-woootec-chile/
│
├── includes/
│   ├── class-core.php
│   ├── class-api.php
│   ├── class-logger.php
│   ├── class-sync.php
│   ├── class-enroll.php
│   └── class-cron.php
│
├── admin/
│   ├── class-admin.php
│   └── views/
│
├── public/
│   ├── class-frontend.php
│   └── templates/
│
├── config/
│   └── defaults.php
│
├── assets/
├── languages/
├── logs/
└── pcc-woootec-chile.php

🔌 Requisitos
WordPress 6.0+
WooCommerce 7.0+
Moodle 3.9+ (API REST habilitada)
PHP 8.1 o superior
Token de servicio web en Moodle

⚙️ Instalación
Subir el plugin a /wp-content/plugins/
Activar desde el panel de WordPress
Ir a Woo OTEC Moodle → Setup Wizard
Configurar:
URL de Moodle
Token API
Roles
Configuración base

🧙 Setup Wizard

El plugin incluye un asistente de configuración paso a paso:

Conexión con Moodle
Validación de API
Configuración de roles
Parámetros por defecto
Verificación final

🔄 Flujo de Funcionamiento
Cliente compra un curso en WooCommerce
Se crea automáticamente el usuario en Moodle
Se matricula en el curso correspondiente
Se registra la operación en logs
Acceso inmediato del alumno

🔐 Seguridad
Uso de API REST oficial de Moodle
Validación de credenciales
Sanitización de datos
Protección contra accesos directos
Logs auditables

📊 Logs y Monitoreo

El sistema incluye registro completo de eventos:

Creación de usuarios
Matriculaciones
Errores de API
Sincronizaciones

Ubicación:

/logs/

🧑‍🏫 Gestión de Cursos
Asociación de productos WooCommerce → cursos Moodle
Configuración de instructor por defecto
Control de precios base
Personalización por producto

🧪 Estado del Proyecto

🟢 Versión PRO – Lista para producción
🟢 Código modular y escalable
🟢 Preparado para comercialización

💼 Modelo Comercial

Este plugin está diseñado como solución premium tipo marketplace, ideal para:

Venta directa a OTEC
Implementaciones a medida
SaaS e-learning
Licenciamiento por cliente

🛠️ Roadmap
 Soporte multi-curso por producto
 Integración con certificados Moodle
 Panel de analítica
 Webhooks avanzados
 Integración con pagos recurrentes
 
🤝 Soporte

Soporte técnico disponible para:

Instalación
Configuración
Integración personalizada
Escalabilidad

📄 Licencia

Uso comercial permitido bajo licencia privada.

⭐ Autor

PCCurico
Desarrollo de soluciones e-learning y automatización para OTEC.

💡 Nota

Este plugin está diseñado para entornos profesionales.
Se recomienda implementación por personal técnico o desarrollador WordPress.

# Configurar el correo del sitio

El sitio usa SMTP estándar. Puede conectarse a Gmail o a cualquier proveedor que ofrezca SMTP; no hay una dependencia exclusiva de Gmail.

Configurá estas variables en el entorno de Apache/PHP:

| Variable | Valor |
| --- | --- |
| `GPUFIX_SMTP_HOST` | Servidor SMTP del proveedor |
| `GPUFIX_SMTP_PORT` | Puerto SMTP, normalmente el indicado por el proveedor |
| `GPUFIX_SMTP_SECURITY` | `tls`, `ssl` o `none`, según el proveedor |
| `GPUFIX_SMTP_USER` | Usuario SMTP; puede quedar vacío si el servidor no usa autenticación |
| `GPUFIX_SMTP_PASSWORD` | Contraseña o credencial SMTP |
| `GPUFIX_MAIL_FROM` | Dirección remitente autorizada por el proveedor |
| `GPUFIX_MAIL_FROM_NAME` | Nombre visible; opcional, por defecto `GPU FIX & STORE` |
| `GPUFIX_APP_URL` | URL pública del sitio, por ejemplo `https://tu-dominio.com`; en XAMPP puede ser `http://localhost/gpufix` |

No guardes una contraseña real en este archivo ni en el repositorio. Apache debe reiniciarse después de cambiar las variables para que PHP las reciba.

La función reutilizable `correo_enviar()` está en `correo.php`. El registro la usa para enviar confirmaciones. Cuando el sitio genere una factura, ese flujo podrá llamar la misma función con el correo del usuario y el contenido de la factura.

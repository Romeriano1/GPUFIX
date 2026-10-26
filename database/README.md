# Base local despues de hacer pull

En cada PC, con MySQL de XAMPP iniciado, ejecutar una vez `database/activar-sincronizacion.cmd` (doble clic). Importa `database/gpufix.sql` y activa los hooks de este repositorio.

Luego, cuando un pull incorpora commits, se reemplazan las tablas y los datos del SQL en MySQL local. Funciona con merge, fast-forward y rebase, tambien desde GitHub Desktop. Los cambios propios que no esten en el SQL se reemplazan. La base del hosting no se modifica.

Antes de importar se guarda una exportacion de la base anterior en `%LOCALAPPDATA%/GPUFIX/respaldos-db/`, fuera del proyecto y de GitHub. Si falla la importacion, se intenta restaurar ese respaldo. Los respaldos se conservan hasta que los borres.

Git no copia configuraciones locales al clonar: cada colaborador debe ejecutar el activador una vez. Si MySQL estaba apagado o el pull dice que ya estaba actualizado, reintentar manualmente con:

```powershell
C:\xampp\php\php.exe database/sincronizar.php
```

Para comprobar la conexion y el SQL sin reemplazar datos, agregar `--comprobar`. Para desactivar: `git config --local --unset core.hooksPath`.

Usa los mismos valores locales predeterminados que la pagina: `127.0.0.1:3306`, usuario `root`, clave vacia y base `if0_43068954_gpufix`. Si tu instalacion es distinta, configurar las variables de entorno `GPUFIX_DB_HOST`, `GPUFIX_DB_PORT`, `GPUFIX_DB_USER`, `GPUFIX_DB_PASSWORD` y `GPUFIX_DB_NAME`; tienen que estar disponibles tambien para GitHub Desktop. Solo se permiten servidores locales.

Los cambios hechos en MySQL no se exportan solos. Antes de compartirlos, hay que actualizar `database/gpufix.sql`, hacer commit y push.

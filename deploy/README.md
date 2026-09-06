# Guía de Despliegue de Workers en Producción

Esta guía describe la configuración y operación de los procesos workers de colas en entorno de producción para **ClinicaDB**.

---

## 1. Arquitectura de Colas y Workers

La aplicación utiliza tres colas de procesamiento asíncrono segregadas por tipo de carga:

| Cola | Conexión | Tipo de Carga | Worker de Producción | Tries | Timeout | Retry After |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `default` | `database` | Transaccional / Notificaciones / Correos | `php artisan queue:work database --queue=default` | 3 | 60s | 90s |
| `media` | `media` | Procesamiento y optimización de imágenes | `php artisan queue:work media --queue=media` | 3 | 180s | 300s |
| `backups` | `database_backups` | Respaldo cifrado y subida a R2 | `php artisan queue:work database_backups --queue=backups` | 1 | 1800s | 2400s |

### Justificación de la Separación
1. **Aislamiento de tareas pesadas**: Los respaldos de base de datos (`backups`) pueden tomar varios minutos. Si compartieran worker con `default`, una copia de seguridad bloquearía el envío inmediato de confirmaciones de citas o certificados médicos.
2. **Optimización de recursos**: La cola `media` realiza conversiones de imagen (WebP/AVIF) que consumen CPU. Su worker dedicado garantiza que la cola `default` responda sin latencia.
3. **Parámetros seguros**: Cada cola posee timeouts y tiempos de reintento (`retry_after`) ajustados matemáticamente para que `timeout < retry_after`, evitando reintentos prematuros.

---

## 2. Configuración con Supervisor (Linux)

1. Copiar el archivo de configuración a la carpeta de Supervisor:
   ```bash
   sudo cp deploy/supervisor/laravel-workers.conf /etc/supervisor/conf.d/
   ```

2. Ajustar las rutas en `/etc/supervisor/conf.d/laravel-workers.conf`:
   - Reemplazar `/var/www/clinica` por la ruta absoluta de la aplicación.
   - Reemplazar `user=www-data` por el usuario correspondiente (ej. `nginx`, `www-data` o `deploy`).

3. Cargar la nueva configuración e iniciar los procesos:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start laravel-workers:*
   ```

4. Verificar el estado de los procesos:
   ```bash
   sudo supervisorctl status
   ```

---

## 3. Procedimiento de Despliegue (Deploy)

Dado que `queue:work` es un proceso daemon persistente que mantiene en memoria el código de la aplicación:

1. **Reiniciar workers tras actualizar código**:
   Durante el script de despliegue (CI/CD o post-deploy hook), ejecutar:
   ```bash
   php artisan queue:restart
   ```
   Esto instruirá a los workers existentes a terminar su tarea actual de manera segura y reiniciarse cargando el nuevo código.

2. **Advertencia de Seguridad**:
   - **NO** colocar `queue:restart` en el scheduler (`routes/console.php`).
   - **NO** invocar `queue:restart` en requests HTTP.
   - Ejecutarlo únicamente durante el pipeline o paso de despliegue.

---

## 4. Entorno de Desarrollo Local

En desarrollo local, el script integrado de Composer atiende todas las colas de forma no-bloqueante:
```bash
composer run dev
```
El comando utiliza `queue:listen --queue=default,media,backups --tries=1` para procesar tareas sin necesidad de reiniciar manualmente los workers ante cambios de código.

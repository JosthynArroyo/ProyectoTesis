# Manejo de Imagenes Optimizadas

## Resumen
- Todas las cargas de imagen de perfil (`admin/doctor/paciente`), slides de bienvenida, fotos de doctores y comprobantes de pago (cuando son imagen) pasan por `App\Services\ImageOptimizer`.
- El sistema corrige orientacion EXIF, genera `WEBP` (obligatorio) y, si el entorno lo soporta, tambien `AVIF`.
- Se generan 3 variantes por imagen raster:
  - `thumb` (150x150, recorte centrado)
  - `medium` (ancho maximo 600)
  - `large` (ancho maximo 1200)
- SVG no se rasteriza: se guarda como SVG en `original/`.

## Estructura de almacenamiento
- Disco: `public` (`storage/app/public`)
- Ruta base:
  - `images/{folder}/thumb/{filename}.webp`
  - `images/{folder}/medium/{filename}.webp`
  - `images/{folder}/large/{filename}.webp`
  - `images/{folder}/original/{filename}.svg` (solo SVG)
- Ejemplos de carpetas usadas:
  - `users`, `doctors`, `patients`, `banners`, `uploads`
  - `payment-proofs/patients/{id}` (comprobantes de transferencia en imagen)

## Servicio de URL en vistas
- Clase: `App\Support\ImageUrl`
- Inyectada globalmente como `$imageUrl` desde `AppServiceProvider`.
- Uso recomendado:
  - `@php($img = $imageUrl->variants($path, 'doctors', 'doctor'))`
  - `src="{{ $img['thumb'] }}"`
  - `srcset="{{ $img['srcset'] }}"` (si existe)
- Incluye fallback por entidad con placeholders en `public/img/placeholders`.

## Migracion de imagenes existentes (batch)
- Comando:
  - `php artisan images:optimize-existing`
- Opciones:
  - `--dry-run` muestra que se procesaria sin escribir archivos.
  - `--folder=users` procesa solo una carpeta objetivo.
  - `--limit=100` limita cantidad de imagenes.
- El comando conserva referencias actuales en BD y genera variantes con el mismo nombre base.

## Cache de imagenes
- Se agrego cache largo para `/storage/images/*` en `public/.htaccess`:
  - `Cache-Control: public, max-age=31536000, immutable`
- Esto aplica en Apache con `mod_headers` + `mod_setenvif`.

## Requisitos del entorno
- Dependencia: `intervention/image` (v3).
- PHP debe soportar codificacion WebP (obligatorio).
- AVIF es opcional y se omite automaticamente si no esta soportado.
- Si falta el enlace publico de storage:
  - `php artisan storage:link`

## Si el servidor no soporta WEBP o AVIF
- WEBP:
  - Es obligatorio para esta implementacion.
  - Si no hay soporte de codificacion, las cargas raster fallaran en el proceso de optimizacion.
  - Solucion: habilitar GD/Imagick con WebP.
- AVIF:
  - Es opcional.
  - Si no hay soporte, el sistema continua con WEBP.
  - Puede forzarse desactivado con `IMAGE_GENERATE_AVIF=false`.

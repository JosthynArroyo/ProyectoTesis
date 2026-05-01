## Runtime recomendado para despliegue

El proyecto debe desplegarse con `PHP 8.3` hasta completar una validacion dedicada de compatibilidad sobre `PHP 8.4`.

Motivo:
- El arbol actual fija `dompdf/dompdf` `v2.0.8`.
- El arbol actual fija `endroid/qr-code` `5.1.0`.
- En `PHP 8.4` ambas dependencias emiten advertencias `deprecated` durante la suite.
- Existen ramas mayores mas nuevas (`dompdf` `3.1.5` y `endroid/qr-code` `6.0.9` al 2026-05-01), pero migrarlas sin una ronda dedicada de pruebas de PDF y QR introduciria un riesgo de regresion antes de despliegue.

Decision:
- Produccion recomendada: `PHP 8.3`.
- `composer.json` fija `config.platform.php` a `8.3.0` para resolver dependencias contra ese objetivo.
- La migracion a `PHP 8.4` queda pendiente hasta validar generacion de comprobantes, certificados, ordenes de cobro y QR con versiones compatibles de esas librerias.

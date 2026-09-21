# Pruebas

`probar.sh` levanta un WordPress desechable con Uncode y un plugin que carga Font
Awesome 6.5.1 —como lo hace WPVR—, instala Uncode Fix Icons desde su `.zip`, y mide
en un navegador el carácter que se dibuja de verdad en cada ícono.

```bash
UNCODE=/ruta/a/uncode.zip ./tests/probar.sh
```

**Uncode no viene incluido**: es un tema comercial. Usa tu propia copia (el `.zip`
que descargas de ThemeForest). Font Awesome Free 6.5.1 se descarga al momento desde
su paquete oficial.

Necesitas Docker con compose, `curl`, `unzip`, Node y Playwright
(`npm i -g playwright && npx playwright install chromium`).

## Qué comprueba

Primero mide **sin** el plugin, para que veas el bug, y luego **con** él:

| Ícono | Esperado con el plugin | |
|---|---|---|
| `fas fa-clock` | `\f017` | sin el plugin sale `\e072`, el papel tachado |
| `fas fa-sync` | `\f021` | |
| `fas fa-times` | `\f00d` | |
| `fab fa-facebook-f` | `\f39e` | |
| `fas fa-search` | `\f002` | no choca: igual con y sin el plugin |
| `fa fa-tiktok` | `\e92f` | ícono del tema: el plugin no lo toca |
| `fa fa-clock` | `\e072` | ícono del tema: el plugin no lo toca |

Además confirma que las reglas salieron de **calcular** con tus hojas, no de la tabla
de respaldo. Ese detalle importa: con la tabla de respaldo los íconos también salen
bien, así que medir solo los íconos no basta para saber que el motor funciona.

Para probar el orden inverso (Font Awesome cargando después de Uncode), agrega
`define('UFI_FA_DESPUES', true);` al `wp-config.php` del contenedor.

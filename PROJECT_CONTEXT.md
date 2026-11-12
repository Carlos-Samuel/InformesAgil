# Project Context — InformesVentas

> Documento de contexto del proyecto. Describe qué hace la aplicación, cómo está organizada,
> de dónde saca los datos y las reglas de negocio que no son obvias leyendo el código.
> Última revisión: 2026-11-11.

## 1. Qué es

Aplicación web interna de **informes de ventas e inventario** ("Informes", firmada como *CS Software*).
Corre en **XAMPP (Apache + PHP + MySQL/MariaDB)** en `C:\xampp\htdocs\Servidor\InformesVentas`.
Repositorio remoto: `github.com/Carlos-Samuel/InformesAgil` (rama `main`).

Combina dos fuentes de datos:

1. **MySQL** (base principal del ERP) → ventas, productos, vendedores, terceros, grupos, marcas, etc.
2. **Archivos DBF** (dBase / Visual FoxPro) del sistema de inventario → `KARMOVI.DBF` (movimientos de kardex)
   y `TIPMOVINV.DBF` (tipos de movimiento de inventario). Se leen directamente con un lector propio en PHP,
   sin ODBC ni extensiones.

Además usa una **segunda base MySQL de parametrización** para guardar el layout del dashboard.

No hay framework, router, ORM, autenticación ni build de frontend. Es PHP "plano": cada página es un
`.php` en la raíz y cada endpoint AJAX es un `.php` dentro de `controladores/`.

## 2. Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.x (usa `str_starts_with`, tipos `mixed`, propiedades tipadas) + `mysqli` con sentencias preparadas |
| Dependencias PHP | Composer: `phpoffice/phpspreadsheet ^4.1` (instalado en `vendor/`, versionado en git; actualmente no se usa en el código) |
| Frontend | jQuery 3.6, Bootstrap 5 (local 5.1.3 + bundle 5.3.2 por CDN), DataTables 1.13.8 (+ Buttons, FixedHeader, export Excel/PDF/Print), Select2 4.1, Chart.js, Gridstack 9.2.2, SweetAlert2, Noty, Font Awesome 6.5 |
| Plantilla | Restos de una plantilla comercial (carpeta `sass/`, `css/`, `js/` con muchos plugins no usados: leaflet, slick, mapas, etc.) |
| Idioma | Todo en español (UI, nombres de variables, comentarios) |

## 3. Estructura

```
InformesVentas/
├── .env                         # Config: rutas DBF + credenciales de las 2 BDs (¡versionado en git!)
├── index.php                    # Dashboard de gráficos (Gridstack + Chart.js)
├── informeVentas.php            # Informe de ventas detallado (línea por línea de factura)
├── informeVentasProductoConsolidado.php  # Ventas agrupadas por producto + existencias (DBF)
├── kardexProducto.php           # Kardex de un producto: existencia + movimientos, export CSV
├── existenciasTodas.php         # Existencias de TODOS los productos (una pasada por KARMOVI)
├── kardexCompleto.php           # Volcado completo de KARMOVI.DBF
├── karmovi.php / tipmovinv.php  # Visores crudos de los DBF (ocultos en el menú, comentados)
├── partes/                      # Includes comunes: head.php (CSS/meta), sidebar.php (menú), foot.php (JS)
├── scripts/                     # JS propio por página: dashboard.js, informeVentas.js, informeVentasProductoConsolidado.js
├── styles/                      # CSS propio por página
├── controladores/
│   ├── Connection.php                # Singleton mysqli → BD principal (DB_*)
│   ├── ConnectionParametrizacion.php # Singleton mysqli → BD de parametrización (DB_PARAM_*)
│   ├── EnvLoader.php                 # Lector de .env + resolución de rutas DBF
│   ├── DbfReader.php                 # Lector binario de DBF (dBase III/IV, VFP 0x30)
│   ├── TipMovInvLoader.php           # Mapa de signos TMICOD → +1 / -1 / 0
│   ├── guardarLayout.php / obtenerLayout.php / reiniciarLayout.php  # Persistencia del dashboard
│   ├── cargarLayout.php              # Vacío (0 líneas)
│   ├── datatable/                    # Endpoints JSON para las tablas
│   ├── graficos/                     # Endpoints JSON para los gráficos del dashboard
│   └── select2/                      # Endpoints JSON para los filtros (Select2 AJAX)
├── descargas/                   # .xlsx generados antiguos (abr-2025), versionados
├── js/, css/, fonts/, sass/, bootstrap-5.1.3-dist/, images/  # Assets de plantilla/librerías
└── vendor/                      # Composer (versionado)
```

Las páginas de kardex/existencias (`kardexProducto.php`, `existenciasTodas.php`, `kardexCompleto.php`,
`karmovi.php`, `tipmovinv.php`) llevan su CSS y JS **inline** dentro del propio `.php`; las de ventas y el
dashboard lo tienen separado en `scripts/` y `styles/`.

## 4. Configuración (`.env`)

Variables que usa el código (los valores reales están en `.env`, no se reproducen aquí):

| Variable | Uso |
|---|---|
| `DBF_DIR` | Carpeta que contiene los DBF |
| `DBF_KARMOVI` | Nombre del archivo de movimientos (p. ej. `KARMOVI.DBF`) |
| `DBF_TIPMOVINV` | Nombre del archivo de tipos de movimiento |
| `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` | BD principal del ERP |
| `DB_PARAM_HOST`, `DB_PARAM_USERNAME`, `DB_PARAM_PASSWORD`, `DB_PARAM_DATABASE` | BD de parametrización (layout del dashboard) |

- `EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI')` arma la ruta; si el nombre de archivo ya es
  absoluto, lo respeta; si `DBF_DIR` está vacío, resuelve relativo a la raíz del proyecto.
- `Connection` y `ConnectionParametrizacion` **no usan `EnvLoader`**: tienen su propio parser de `.env`
  duplicado. Ambos usan `set_charset("utf8")` y hacen `die()` si falla la conexión.
- Instalación (`instalacion.txt`): `composer require phpoffice/phpspreadsheet`.

## 5. Módulos funcionales

### 5.1 Dashboard (`index.php` + `scripts/dashboard.js`)
- 4 gráficos Chart.js dentro de un grid Gridstack arrastrable/redimensionable:
  - `comparativoVentasMesAnio` — ventas por mes, últimos 3 años (línea).
  - `comparativoVentasAnio` — ventas por año.
  - `comparativoVentasMesPrefijo` — ventas por mes y prefijo.
  - `comparativoVentasIngresoCosto` — ingreso / costo / utilidad por mes del año actual.
- Cada gráfico se define en el array `graficosConfig` (id del canvas, tipo, url en `controladores/graficos/`,
  función `procesarDatos`). Para añadir un gráfico: endpoint nuevo en `graficos/` + entrada en `graficosConfig`
  + `grid-stack-item` en `index.php`.
- Los gráficos se pueden ocultar y el layout se guarda en la tabla `dashboard_layout`
  (BD de parametrización; clave única `usuario_id + grafico_id`; columnas `pos_x, pos_y, ancho, alto, visible`).
- **No hay usuarios**: `usuario_id` está fijo en `1` (`guardarLayout.php` lo hardcodea; los otros leen
  `$_SESSION['usuario_id'] ?? 1`, y `obtenerLayout.php` ni siquiera llama a `session_start()`).

### 5.2 Informe de Ventas (`informeVentas.php`)
- Filtros: fecha inicio/fin (obligatorias), vendedor, empresa (requerida en el form), marca, prefijo, grupo, subgrupo.
- Todos los filtros son Select2 multiselección con AJAX a `controladores/select2/*.php`.
  **Cascada**: grupos dependen de las empresas elegidas (`empresa_id[]`), subgrupos de los grupos (`grupo_id[]`);
  cambiar empresa limpia grupo, cambiar grupo limpia subgrupo.
- `controladores/datatable/informeVentas.php` devuelve todo el resultado de una vez (paginación en cliente),
  una fila por línea de factura (`ventasdet`). Export a Excel/PDF/Print desde DataTables.

### 5.3 Ventas consolidado por producto (`informeVentasProductoConsolidado.php`)
- Mismos filtros que el informe de ventas, agrupado por producto (cantidad, valor unitario promedio ponderado,
  subtotal, grupo, subgrupo, marca, `prorefer` mostrado como "registro ICA").
- Añade la columna **existencias** calculada desde `KARMOVI.DBF` sólo para los `procod` del resultado
  (usa `DbfReader::scanRecords`, streaming). El JSON incluye un bloque `debug`.

### 5.4 Kardex por producto (`kardexProducto.php`)
- Busca un `PROCOD`, muestra existencia total y movimientos.
- `?format=csv&procod=...` descarga el kardex del producto en CSV (con BOM UTF-8 para Excel) y una columna
  extra `CANT_DESC_NEG` con la cantidad firmada.

### 5.5 Existencias de todos los productos (`existenciasTodas.php`)
- Una sola pasada por `KARMOVI.DBF` acumulando por `PROCOD`; devuelve `procod`, `existencia`, `movimientos`,
  ordenado con `strnatcasecmp`. Sube `set_time_limit(600)` y `memory_limit 1024M`.

### 5.6 Kardex completo (`kardexCompleto.php`)
- Devuelve **todo** `KARMOVI.DBF` como `{columns, data[][]}` (arrays indexados para aligerar el JSON).
  `memory_limit 2048M`.

### 5.7 Endpoints de diagnóstico (no enlazados en el menú)
- `controladores/datatable/compararExistencias.php?procod=...` — compara normalizaciones de PROCOD
  (HEX/longitud de bytes) entre el cálculo del consolidado y el del kardex.
- `controladores/datatable/compararExistenciasFecha.php?procod=...[&fechaCorte=][&fechaInicio=&fechaFin=]` —
  desglose de existencias por año/mes y muestra cruda de `KARFECHA`.
  Se crearon en abr-2026 para investigar descuadres entre informes.

## 6. Modelo de datos

### 6.1 MySQL principal (ERP) — tablas usadas
| Tabla | Alias típico | Campos relevantes |
|---|---|---|
| `ventas` | `ve` | `vtaid`, `vtafec`, `vtahor`, `vtanum`, `vtasubtot`, `prfid`, `venid`, `terid`, `ctoid` |
| `ventasdet` | `vedet` | `vtaid`, `proid`, `vtacant`, `vtavlruni`, `vtacosto` |
| `productos` | `prod` | `proid`, `procod`, `pronom`, `prorefer`, `grpid`, `subid`, `marid` |
| `prefijo` | `pre` | `prfid`, `prfcod`, `prfnom` |
| `vendedor` | `ven` | `venid`, `vencod`, `vennom`, `empid` |
| `terceros` | `ter` | `terid`, `ternit`, `ternom` |
| `ingrupos` / `insubgrupo` / `inmarca` | `gru` / `subgru` / `mar` | `grpid/grpnom/empid`, `subid/subnom`, `marid/marcod/marnom` |
| `centrocosto` | `cc` | `ctoid`, `ctonom` |
| `tbl_empresa` | `te` | `emprid`, `emprcod`, `emprnom` |

Nota: el filtro "empresa" en los informes se aplica sobre `ven.empid` (empresa del **vendedor**).

### 6.2 MySQL parametrización
- `dashboard_layout(usuario_id, grafico_id, pos_x, pos_y, ancho, alto, visible)`.

### 6.3 DBF (codificación CP1252 → UTF-8)
- **KARMOVI.DBF** (≈40 campos; los usados): `KARID, PROCOD, KARTIPO, KARDOCU, PRFCOD, SEDE, KARFECHA, KARHORA,
  BODCOD, KARCANT, KARVALOR, KARCOSTO, KARCONSE, KARESTADO, EMPCOD, PROUND, TMIAUX`.
- **TIPMOVINV.DBF**: `TMICOD, TMINOM, TMITIPO ('+' / '-'), TMINOAFECT (lógico), ...`.
- Enlace: `KARMOVI.KARTIPO` ↔ `TIPMOVINV.TMICOD`. Enlace con MySQL: `KARMOVI.PROCOD` ↔ `productos.procod`.

## 7. Reglas de negocio clave — cálculo de existencias

**Regla vigente** (la que usan *todos* los informes actuales: kardex por producto, existencias todas,
consolidado):

```php
$map  = TipMovInvLoader::loadSignMapVariant($pathTipMovInv, false); // ignora TMINOAFECT
$sign = $map[$kartipo] ?? -1;                                       // KARTIPO desconocido → RESTA
$existencia += $sign * (float)$row['KARCANT'];
```

- `TMITIPO = '+'` → +1, `'-'` → −1, otro valor → +1.
- **Se ignora `TMINOAFECT`** (los movimientos "no afectan" también suman/restan).
- **KARTIPO que no existe en TIPMOVINV → −1** (commit "Si no hay tipomovimiento se resta", 2026-04-13).
- No se filtra por fecha, bodega, sede ni `KARESTADO`: es la existencia histórica total.

⚠️ `TipMovInvLoader::loadSignMap()` y `signFor()` implementan una regla **distinta** (respetan `TMINOAFECT` → 0
y desconocido → +1); su docblock describe esa regla antigua. No son los que usan los informes actuales.
Si se crea un informe nuevo de existencias, usar la regla vigente de arriba para que cuadre con el kardex.

**Normalización de PROCOD**: kardex y existencias agrupan con `mb_strtolower(trim(...))` (case-insensitive);
el consolidado compara con `trim()` exacto contra `productos.procod`. Diferencias de mayúsculas o caracteres
invisibles pueden hacer que no cuadren (de ahí los endpoints de diagnóstico).

## 8. `DbfReader` — notas

- Lee la cabecera (nº registros, tamaño de cabecera y de registro, descriptores de campo) y decodifica tipos
  `C, N, F, L, D, T, I, Y, M` (memo devuelve sólo el nº de bloque). Fechas `D` → `YYYY-MM-DD`; `T` → `Y-m-d H:i:s`.
- Omite registros marcados como eliminados (`*` en el byte 0).
- Métodos: `readRecords($start, $length)`, `searchRecords`, `filterByField`, `findAllByField`,
  `scanRecords($callback)` (streaming, sin acumular en memoria — preferido para archivos grandes).
- ⚠️ `readRecords($start, $length)` cuenta `$length` registros **activos** (salta los borrados pero sigue
  leyendo). Al iterar en lotes `for ($i=0; $i<$total; $i+=$batch)` (como hacen `existenciasTodas`,
  `kardexCompleto` y `TipMovInvLoader`), si hay registros borrados los lotes se solapan y algunos registros se
  procesan dos veces. `scanRecords` no tiene este problema.
- Cada llamada abre y cierra el archivo; cada informe vuelve a leer el DBF completo (no hay caché).

## 9. Convenciones

- Endpoints: `header('Content-Type: application/json')`, leen `$_POST` (o `$_REQUEST`), responden
  `{data: [...]}` / `{success, data}` / `{error}`; muchos devuelven además `debug`.
- Filtros múltiples en SQL con la función `agregarFiltroMultiple(&$where, &$params, &$types, $col, $vals)`
  (duplicada en cada endpoint) → `IN (?, ?, ...)` con `bind_param`.
- Select2 espera `[{id, text, codigo?}]`; el JS muestra `codigo - text`.
- Rutas de include relativas (`'../Connection.php'`) en endpoints antiguos; `__DIR__` en los nuevos.
- Paginación siempre en cliente (DataTables recibe todo el dataset).
- Traducción de DataTables: `js/datatable/es-ES.json` (local) o la URL de CDN en las páginas DBF.
- Mensajes de commit cortos en español, todo directo sobre `main`.

## 10. Cómo ejecutar

1. Arrancar Apache y MySQL en XAMPP.
2. Tener `.env` con las rutas a los DBF accesibles desde el servidor y las credenciales de ambas BDs.
3. `composer install` si falta `vendor/` (ya está versionado).
4. Abrir `http://localhost/Servidor/InformesVentas/`.

No hay tests automatizados, linter ni pipeline de CI.

## 11. Puntos de atención / deuda técnica conocida

- `.env` con credenciales reales está **versionado en git** (`.gitignore` sólo excluye `.DS_Store`).
- Sin autenticación ni control de acceso; cualquiera con acceso a la URL ve los informes y los endpoints de diagnóstico.
- Parser de `.env` duplicado en `Connection` / `ConnectionParametrizacion` (no usan `EnvLoader`).
- Dos reglas de signos coexisten en `TipMovInvLoader` (ver §7).
- Solape de lotes con `readRecords` cuando hay registros borrados (ver §8).
- `comparativoVentasIngresoCosto` suma `vtavlruni` (valor unitario) y `vtacosto` sin multiplicar por cantidad.
- `partes/head.php` declara `<title>` dos veces; se cargan dos versiones de Bootstrap JS.
- Gran cantidad de assets de plantilla sin uso (`sass/`, leaflet, slick, mapas, varias copias de feather/bootstrap).
- `controladores/cargarLayout.php` vacío; `descargas/` con archivos generados antiguos versionados.
- `phpoffice/phpspreadsheet` instalado pero sin uso en el código actual (los exports se hacen en el navegador).

## 12. Historia breve

- **Jul–Ago 2025**: informe de ventas, filtros Select2, sticky headers, dashboard con Gridstack + gráficos.
- **Abr 2026**: integración con los DBF del inventario (lector propio), kardex por producto, existencias en el
  consolidado, varias iteraciones sobre la regla de cálculo y endpoints de diagnóstico para cuadrar existencias,
  vista de existencias de todos los productos y kardex completo, ajustes de rendimiento.

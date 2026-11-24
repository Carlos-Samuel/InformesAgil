<?php
/**
 * Devoluciones de venta (notas crédito, incluidas las electrónicas — prefijo NCVP).
 *
 * Tablas:
 *   tbl_devolucion          (d)  cabecera. docid → ventas.vtaid de la factura afectada.
 *   tbl_devolucion_detalle  (dd) líneas por producto.
 *
 * Reglas:
 *   - Sólo devoluciones de venta:  d.devtipo = 'FAV'  (COM = compras, RMC = remisiones).
 *   - Se excluyen las anuladas:    d.devestado = 0.
 *   - Se descuentan en el periodo de la FECHA DE LA DEVOLUCIÓN (d.devfec),
 *     no en el de la factura original.
 *   - Unidades devueltas: dd.devcant.
 *   - Valor devuelto de la línea: dd.devcant * dd.devvlr; si devcant = 0 la nota es
 *     sólo de valor (rebaja, ajuste de precio, …) y el valor es dd.devvlr.
 *     Con esta fórmula la suma de las líneas cuadra con d.devsubt.
 *   - Vendedor, empresa y prefijo se toman de la factura original (ventas v),
 *     para que los filtros de los informes se comporten igual que en las ventas.
 */

const DEV_CONDICION = "d.devtipo = 'FAV' AND d.devestado = 0";

const DEV_VALOR_LINEA = "(CASE WHEN dd.devcant = 0 THEN dd.devvlr ELSE dd.devcant * dd.devvlr END)";

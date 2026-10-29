<?php
// Formularios de edición compartidos por las secciones de administración.
function admin_etiqueta_dato(string $campo): string {
    $etiquetas = ['usuarios' => 'Usuarios', 'cliente' => 'Clientes', 'empleado' => 'Empleados', 'puesto' => 'Puestos', 'carrito' => 'Carritos', 'detalle_carrito' => 'Productos de los carritos', 'gpu' => 'Placas de video', 'stock_gpu' => 'Stock de placas', 'proveedor' => 'Proveedores', 'presupuesto' => 'Presupuestos', 'detalle_presupuesto' => 'Componentes de los presupuestos', 'gpu_cliente' => 'Placas de clientes', 'orden_reparacion' => 'Órdenes de reparación', 'diagnostico' => 'Diagnósticos', 'reparacion' => 'Trabajos de reparación', 'detalle_reparacion' => 'Componentes utilizados', 'gpu_ventas' => 'Ventas', 'detalle_venta' => 'Productos vendidos', 'factura' => 'Facturas', 'detalle_factura' => 'Conceptos facturados', 'envios' => 'Envíos', 'orden_compra' => 'Órdenes de compra', 'detalle_compra' => 'Productos comprados', 'componente' => 'Componentes', 'stock' => 'Stock de componentes', 'id_correo' => 'Usuario', 'id_cliente' => 'Cliente', 'id_empleado' => 'Empleado', 'id_tecnico' => 'Técnico', 'id_puesto' => 'Puesto', 'id_carrito' => 'Carrito', 'id_gpu' => 'Placa de video', 'id_gpu_cliente' => 'Placa del cliente', 'id_componente' => 'Componente', 'id_orden_reparacion' => 'Orden de reparación', 'id_reparacion' => 'Trabajo de reparación', 'id_presupuesto' => 'Presupuesto', 'id_gpu_ventas' => 'Venta', 'id_factura' => 'Factura', 'id_proveedor' => 'Proveedor', 'id_orden_compra' => 'Orden de compra', 'correo' => 'Correo electrónico', 'email' => 'Correo de contacto', 'contraseña' => 'Nueva contraseña', 'dni' => 'DNI', 'precio_especial' => 'Precio de venta', 'pagina' => 'Página del producto', 'imagen' => 'Enlace de la imagen', 'tarjeta_ultimos4' => 'Últimos cuatro dígitos', 'tarjeta_longitud' => 'Cantidad de dígitos de la tarjeta', 'tarjeta_dni' => 'DNI del titular', 'mano_obra' => 'Mano de obra'];
    return $etiquetas[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
}

function admin_edicion_seccion(string $tituloSeccion, array $tablasPermitidas, string $paginaOrigen): void {
    if (($_GET['modo'] ?? '') !== 'datos') return;
    global $conexion;
    admin_validar_csrf();
    if (!admin_es_cuenta_principal($conexion)) {
        http_response_code(403);
        admin_layout_inicio($tituloSeccion);
        echo '<div class="alert alert-danger">Esta edición está reservada a la cuenta principal de GPUFIX.</div>';
        admin_layout_fin();
        exit;
    }
function tablas_identificador(string $nombre): string { return '`' . str_replace('`', '``', $nombre) . '`'; }
function tablas_version(array $registro): string { return hash_hmac('sha256', serialize($registro), admin_csrf()); }
function tablas_consultar(mysqli $db, string $sql, array $valores = []): mysqli_stmt {
    $consulta = $db->prepare($sql);
    if ($valores) $consulta->bind_param(str_repeat('s', count($valores)), ...$valores);
    $consulta->execute();
    return $consulta;
}
function tablas_validar_campo(array $columna, $valor): void {
    if ($valor === null) {
        if ($columna['Null'] !== 'YES') throw new RuntimeException('El campo ' . $columna['Field'] . ' no admite NULL.');
        return;
    }
    $tipo = strtolower($columna['Type']);
    $campo = $columna['Field'];
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $tipo, $entero)) {
        if (!preg_match('/^-?\d+$/D', $valor)) throw new RuntimeException('Ingresá un número entero en ' . $campo . '.');
        $bits = ['tinyint'=>8,'smallint'=>16,'mediumint'=>24,'int'=>32,'bigint'=>64][$entero[1]];
        $sinSigno = strpos($tipo, 'unsigned') !== false;
        if (($sinSigno && str_starts_with($valor, '-')) || strlen(ltrim($valor, '-0')) > 20) throw new RuntimeException('Número fuera de rango en ' . $campo . '.');
        if ($bits < 64 && ((float)$valor < ($sinSigno ? 0 : -(2 ** ($bits-1))) || (float)$valor > ($sinSigno ? 2 ** $bits-1 : 2 ** ($bits-1)-1))) throw new RuntimeException('Número fuera de rango en ' . $campo . '.');
    } elseif (preg_match('/^decimal\((\d+),(\d+)\)/', $tipo, $decimal)) {
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $valor)) throw new RuntimeException('Ingresá un importe válido en ' . $campo . ', usando punto decimal.');
        $partes = explode('.', ltrim($valor, '-'));
        if (strlen(ltrim($partes[0], '0')) > (int)$decimal[1]-(int)$decimal[2] || strlen($partes[1] ?? '') > (int)$decimal[2]) throw new RuntimeException('El importe de ' . $campo . ' excede la precisión permitida.');
    } elseif (preg_match('/^(?:var)?char\((\d+)\)/', $tipo, $longitud) && mb_strlen($valor, 'UTF-8') > (int)$longitud[1]) {
        throw new RuntimeException('El campo ' . $campo . ' admite hasta ' . $longitud[1] . ' caracteres.');
    } elseif (in_array($tipo, ['date','datetime','timestamp'], true)) {
        $formato = $tipo === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
        $fecha = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
        if (!$fecha || $fecha->format($formato) !== $valor) throw new RuntimeException('Fecha inválida en ' . $campo . '. Usá ' . ($tipo === 'date' ? 'AAAA-MM-DD' : 'AAAA-MM-DD HH:MM:SS') . '.');
    }
}
$tabla = is_string($_GET['tabla'] ?? null) ? $_GET['tabla'] : $tablasPermitidas[0];
if (!in_array($tabla, $tablasPermitidas, true)) { http_response_code(404); exit('Tabla no disponible.'); }
$nombreTabla = tablas_identificador($tabla);
$columnas = $conexion->query('SHOW FULL COLUMNS FROM ' . $nombreTabla)->fetch_all(MYSQLI_ASSOC);
$clavePrimaria = '';
foreach ($columnas as $columna) if ($columna['Key'] === 'PRI') $clavePrimaria = $columna['Field'];
if ($clavePrimaria === '') throw new RuntimeException('La tabla necesita una clave primaria.');
$idRegistro = is_string($_GET['editar'] ?? null) && preg_match('/^[1-9][0-9]*$/D', $_GET['editar']) ? $_GET['editar'] : '';
$error = '';
$mensaje = $_SESSION['tablas_mensaje'] ?? '';
unset($_SESSION['tablas_mensaje']);
$registro = null;
$valoresFormulario = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($idRegistro === '') throw new RuntimeException('Seleccioná un registro para editar.');
        $conexion->begin_transaction();
        $consulta = tablas_consultar($conexion, 'SELECT * FROM ' . $nombreTabla . ' WHERE ' . tablas_identificador($clavePrimaria) . ' = ? FOR UPDATE', [$idRegistro]);
        $registro = $consulta->get_result()->fetch_assoc();
        if (!$registro) throw new RuntimeException('El registro ya no existe.');
        $version = $_POST['version'] ?? '';
        if (!is_string($version) || !hash_equals(tablas_version($registro), $version)) throw new RuntimeException('El registro cambió desde que lo abriste. Recargá antes de editarlo.');
        $campos = []; $valores = [];
        foreach ($columnas as $columna) {
            $campo = $columna['Field'];
            if ($campo === $clavePrimaria) continue;
            if (!isset($_POST['datos']) || !is_array($_POST['datos']) || !array_key_exists($campo, $_POST['datos']) || !is_string($_POST['datos'][$campo])) throw new RuntimeException('Falta el campo ' . $campo . '.');
            $valor = $_POST['datos'][$campo];
            if ($tabla === 'usuarios' && $campo === 'contraseña') {
                if ($valor === '') continue;
                if (strlen($valor) < 8 || strlen($valor) > 72) throw new RuntimeException('La contraseña debe tener entre 8 y 72 bytes.');
                $valor = password_hash($valor, PASSWORD_DEFAULT);
            } else {
                if (isset($_POST['nulos']) && is_array($_POST['nulos']) && ($_POST['nulos'][$campo] ?? '') === '1') $valor = null;
                tablas_validar_campo($columna, $valor);
            }
            if (in_array($campo, ['cantidad','precio_unitario','subtotal','total','precio_total','precio_especial','mano_obra'], true) && $valor !== null && (float)$valor < 0) throw new RuntimeException('El campo ' . $campo . ' no puede ser negativo.');
            if ($campo === 'dni' && $valor !== null && !preg_match('/^[0-9]{7,8}$/D', $valor)) throw new RuntimeException('El DNI debe tener 7 u 8 números.');
            if (in_array($campo, ['correo','email'], true) && $valor !== null && $valor !== '' && !filter_var($valor,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo no es válido.');
            if ($tabla === 'usuarios' && $registro['correo'] === 'gpufixstore@gmail.com' && $campo === 'correo' && $valor !== $registro['correo']) throw new RuntimeException('El correo de la cuenta principal está protegido.');
            if ($tabla === 'empleado' && in_array($campo, ['id_correo','id_puesto'], true)) {
                $principal = tablas_consultar($conexion,"SELECT id_correo FROM usuarios WHERE correo = 'gpufixstore@gmail.com'")->get_result()->fetch_assoc();
                if ($principal && (string)$registro['id_correo'] === (string)$principal['id_correo'] && (string)$valor !== (string)$registro[$campo]) throw new RuntimeException('El vínculo y el puesto de la cuenta principal están protegidos.');
            }
            if ($tabla === 'puesto' && $registro['nombre'] === 'Administrador' && $campo === 'nombre' && $valor !== 'Administrador') throw new RuntimeException('El puesto de la cuenta principal está protegido.');
            $campos[] = tablas_identificador($campo) . ' = ?'; $valores[] = $valor;
        }
        if ($campos) {
            $valores[] = $idRegistro;
            tablas_consultar($conexion, 'UPDATE ' . $nombreTabla . ' SET ' . implode(', ', $campos) . ' WHERE ' . tablas_identificador($clavePrimaria) . ' = ?', $valores);
        }
        $conexion->commit();
        $_SESSION['tablas_mensaje'] = 'Registro actualizado en ' . $tabla . '.';
        header('Location: ' . $paginaOrigen . '?modo=datos&tabla=' . rawurlencode($tabla) . '&editar=' . rawurlencode($idRegistro), true, 303); exit;
    } catch (Throwable $fallo) {
        $conexion->rollback();
        if ($fallo instanceof mysqli_sql_exception) {
            $error = match ((int)$fallo->getCode()) {1062=>'Ese valor ya existe en un campo o combinación que debe ser única.',1452=>'Una relación apunta a un registro que no existe.',1406,1264=>'Un valor excede el tamaño o rango de su columna.',default=>'No se pudieron guardar los datos. Revisá los valores y las relaciones.'};
            error_log('Edición de tabla ' . $tabla . ': código ' . $fallo->getCode());
        } else { $error = $fallo->getMessage(); }
        $valoresFormulario = is_array($_POST['datos'] ?? null) ? $_POST['datos'] : [];
    }
}
if ($idRegistro !== '') {
    $registro = tablas_consultar($conexion, 'SELECT * FROM ' . $nombreTabla . ' WHERE ' . tablas_identificador($clavePrimaria) . ' = ?', [$idRegistro])->get_result()->fetch_assoc();
    if (!$registro) $error = 'El registro seleccionado no existe.';
}
$relaciones = tablas_consultar($conexion, 'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$tabla])->get_result()->fetch_all(MYSQLI_ASSOC);
$relacionesPorCampo = array_column($relaciones,null,'COLUMN_NAME');
$buscar = is_string($_GET['buscar'] ?? null) ? mb_substr($_GET['buscar'],0,100,'UTF-8') : '';
$pagina = max(1,(int)($_GET['pagina'] ?? 1));
$condiciones=[]; $filtros=[];
if ($buscar !== '') foreach ($columnas as $columna) {
    if ($tabla === 'usuarios' && $columna['Field'] === 'contraseña') continue;
    $condiciones[] = 'CAST(' . tablas_identificador($columna['Field']) . ' AS CHAR) LIKE ?'; $filtros[] = '%' . $buscar . '%';
}
$where = $condiciones ? ' WHERE (' . implode(' OR ', $condiciones) . ')' : '';
$total = (int)tablas_consultar($conexion,'SELECT COUNT(*) FROM ' . $nombreTabla . $where,$filtros)->get_result()->fetch_row()[0];
$paginas = max(1,(int)ceil($total/25)); $pagina=min($pagina,$paginas); $desplazamiento=($pagina-1)*25;
$registros = tablas_consultar($conexion,'SELECT * FROM ' . $nombreTabla . $where . ' ORDER BY ' . tablas_identificador($clavePrimaria) . ' LIMIT 25 OFFSET ' . $desplazamiento,$filtros)->get_result()->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio($tituloSeccion);
?>
<h1 class="h3 mt-3">Editar datos de <?= admin_e($tituloSeccion) ?></h1>
<p class="text-secondary">Editá los registros de esta sección. Los identificadores principales permanecen fijos para conservar sus relaciones. Las modificaciones se guardan directamente en la base de datos.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<?php if ($mensaje): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<p><a href="<?= admin_e($paginaOrigen) ?>">← Volver a <?= admin_e($tituloSeccion) ?></a></p>
<section class="panel"><form method="get" class="row g-3 align-items-end"><input type="hidden" name="modo" value="datos">
<div class="col-md-5"><label class="form-label" for="tablaDatos">Datos de la sección</label><select class="form-select" id="tablaDatos" name="tabla"><?php foreach ($tablasPermitidas as $opcion): ?><option <?= $tabla === $opcion ? 'selected' : '' ?> value="<?= admin_e($opcion) ?>"><?= admin_e(admin_etiqueta_dato($opcion)) ?></option><?php endforeach; ?></select></div>
<div class="col-md-5"><label class="form-label" for="buscarDatos">Buscar en los registros</label><input class="form-control" id="buscarDatos" name="buscar" maxlength="100" value="<?= admin_e($buscar) ?>"></div>
<div class="col-md-2"><button class="btn btn-primary" type="submit">Ver registros</button></div></form></section>
<?php if ($registro): ?>
<section class="panel" id="editar-registro"><h2 class="h5">Editar <?= admin_e(admin_etiqueta_dato($tabla)) ?> #<?= admin_e($idRegistro) ?></h2>
<form method="post" action="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;tabla=<?= rawurlencode($tabla) ?>&amp;editar=<?= rawurlencode($idRegistro) ?>" class="row g-3">
<input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="version" value="<?= admin_e(tablas_version($registro)) ?>">
<?php foreach ($columnas as $indice=>$columna): $campo=$columna['Field']; $esClave=$campo===$clavePrimaria; $esSecreto=$tabla==='usuarios' && $campo==='contraseña'; $valor=$esSecreto ? '' : ($valoresFormulario[$campo] ?? $registro[$campo]); if ($valor!==null) $valor=is_scalar($valor) ? (string)$valor : ''; $esTexto=str_contains($columna['Type'],'text'); ?>
<div class="<?= $esTexto ? 'col-12' : 'col-md-6' ?>"><label class="form-label" for="campo-<?= $indice ?>"><?= admin_e(admin_etiqueta_dato($campo)) ?><?= $esClave ? ' · ID' : '' ?></label>
<?php if ($esTexto): ?><textarea class="form-control" id="campo-<?= $indice ?>" name="datos[<?= admin_e($campo) ?>]" rows="4"><?= admin_e($valor) ?></textarea>
<?php else: ?><input class="form-control" type="<?= $esSecreto ? 'password' : 'text' ?>" autocomplete="<?= $esSecreto ? 'new-password' : 'off' ?>" id="campo-<?= $indice ?>" name="datos[<?= admin_e($campo) ?>]" value="<?= admin_e($valor) ?>" <?= $esClave ? 'readonly' : '' ?>><?php endif; ?>
<div class="form-text"><?= $esSecreto ? ' · Dejá vacío para conservar la contraseña actual.' : '' ?><?php if(isset($relacionesPorCampo[$campo])): $relacion=$relacionesPorCampo[$campo]; ?> · Vinculado a <a href="<?= admin_e(admin_enlace_datos($relacion['REFERENCED_TABLE_NAME'])) ?>"><?= admin_e($relacion['REFERENCED_TABLE_NAME'] . '.' . $relacion['REFERENCED_COLUMN_NAME']) ?></a><?php endif; ?></div>
<?php if (!$esClave && $columna['Null']==='YES'): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="nulo-<?= $indice ?>" name="nulos[<?= admin_e($campo) ?>]" value="1" <?= ($error && isset($_POST['nulos'][$campo])) || (!$error && $valor===null) ? 'checked' : '' ?>><label class="form-check-label" for="nulo-<?= $indice ?>">Sin completar</label></div><?php endif; ?></div>
<?php endforeach; ?>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline-secondary" href="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;tabla=<?= rawurlencode($tabla) ?>">Cerrar edición</a></div>
</form></section><?php endif; ?>
<section class="panel"><h2 class="h5"><?= admin_e(admin_etiqueta_dato($tabla)) ?> · <?= $total ?> registros</h2>
<div class="table-responsive" tabindex="0" aria-label="Registros de <?= admin_e($tabla) ?>"><table class="table table-striped"><thead><tr><th>Acción</th><?php foreach ($columnas as $columna): ?><th scope="col"><?= admin_e(admin_etiqueta_dato($columna['Field'])) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($registros as $fila): ?><tr><td><a class="btn btn-sm btn-outline-primary" href="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;tabla=<?= rawurlencode($tabla) ?>&amp;editar=<?= rawurlencode($fila[$clavePrimaria]) ?>#editar-registro">Editar</a></td><?php foreach ($columnas as $columna): $campo=$columna['Field']; $contenido=$fila[$campo]; ?><td><?php if ($tabla==='usuarios' && $campo==='contraseña'): ?>Contraseña guardada<?php elseif ($contenido===null): ?><span class="text-secondary">Sin valor</span><?php else: ?><?= admin_e(mb_strimwidth((string)$contenido,0,160,'…','UTF-8')) ?><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$registros): ?><tr><td colspan="<?= count($columnas)+1 ?>">No hay registros para mostrar.</td></tr><?php endif; ?></tbody></table></div>
<nav aria-label="Páginas de registros" class="d-flex flex-wrap gap-3 align-items-center"><?php foreach (['Anterior'=>$pagina-1,'Siguiente'=>$pagina+1] as $etiqueta=>$numero): if ($numero<1 || $numero>$paginas) continue; ?><a href="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;<?= admin_e(http_build_query(['tabla'=>$tabla,'buscar'=>$buscar,'pagina'=>$numero])) ?>"><?= $etiqueta ?></a><?php endforeach; ?><span>Página <?= $pagina ?> de <?= $paginas ?></span></nav></section>
<?php admin_layout_fin(); exit; }

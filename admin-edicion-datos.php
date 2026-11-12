<?php
require_once __DIR__ . '/admin-relaciones.php';
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
if ($tabla === 'empleado') {
    $columnas = array_values(array_filter($columnas, static fn ($columna) => $columna['Field'] !== 'contraseña'));
}
$clavePrimaria = '';
foreach ($columnas as $columna) if ($columna['Key'] === 'PRI') $clavePrimaria = $columna['Field'];
if ($clavePrimaria === '') throw new RuntimeException('La tabla necesita una clave primaria.');
$columnaElegida = is_string($_GET['columna'] ?? null) ? $_GET['columna'] : '';
if ($columnaElegida !== '' && !in_array($columnaElegida, array_column($columnas, 'Field'), true)) { http_response_code(404); exit('Columna no disponible.'); }
$filtroColumna = $columnaElegida === '' ? '' : '&columna=' . rawurlencode($columnaElegida);

$idRegistro = is_string($_GET['editar'] ?? null) && preg_match('/^[1-9][0-9]*$/D', $_GET['editar']) ? $_GET['editar'] : '';
$crear = ($_GET['nuevo'] ?? '') === '1';
$eliminar = !$crear && ($_GET['operacion'] ?? '') === 'eliminar';
$operacion = $crear ? 'agregar' : ($eliminar ? 'eliminar' : 'editar');
$error = '';
$mensaje = $_SESSION['tablas_mensaje'] ?? '';
unset($_SESSION['tablas_mensaje']);
$registro = null;
$valoresFormulario = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$crear && $idRegistro === '') throw new RuntimeException('Seleccioná un registro para editar.');
        if ($eliminar !== (($_POST['accion'] ?? '') === 'eliminar')) throw new RuntimeException('La acción no corresponde a esta pantalla.');
        $conexion->begin_transaction();
        if (!$crear) {
        $consulta = tablas_consultar($conexion, 'SELECT * FROM ' . $nombreTabla . ' WHERE ' . tablas_identificador($clavePrimaria) . ' = ? FOR UPDATE', [$idRegistro]);
        $registro = $consulta->get_result()->fetch_assoc();
        if (!$registro) throw new RuntimeException('El registro ya no existe.');
        $version = $_POST['version'] ?? '';
        if (!is_string($version) || !hash_equals(tablas_version($registro), $version)) throw new RuntimeException('El registro cambió desde que lo abriste. Recargá antes de editarlo.');
        } else { $registro = []; }
        if (($_POST['accion'] ?? '') === 'eliminar') {
            if ($crear || ($_POST['confirmar_eliminar'] ?? '') !== '1') throw new RuntimeException('Confirmá la eliminación del registro.');
            if ($tabla === 'presupuesto') {
                require_once __DIR__ . '/consultas-panel.php';
                consultas_eliminar_presupuesto($conexion, (int) $idRegistro);
                $conexion->commit();
                $_SESSION['tablas_mensaje'] = 'Consulta y detalles del presupuesto eliminados.';
                header('Location: ' . $paginaOrigen . '?modo=datos&operacion=eliminar&tabla=' . rawurlencode($tabla) . $filtroColumna, true, 303);
                exit;
            }
            if (($tabla === 'empleado' && $registro['email'] === 'gpufixstore@gmail.com') || ($tabla === 'usuarios' && $registro['correo'] === 'gpufixstore@gmail.com') || ($tabla === 'empleado' && (int)$registro['id_empleado'] === (int)$_SESSION['empleado_id']) || ($tabla === 'puesto' && $registro['nombre'] === 'Administrador')) throw new RuntimeException('No se puede eliminar la cuenta principal, su empleado o su puesto desde el panel.');
            $dependencias = tablas_consultar($conexion, 'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?', [$tabla])->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($dependencias as $dependencia) {
                $cantidad = tablas_consultar($conexion, 'SELECT COUNT(*) FROM ' . tablas_identificador($dependencia['TABLE_NAME']) . ' WHERE ' . tablas_identificador($dependencia['COLUMN_NAME']) . ' = ?', [$registro[$dependencia['REFERENCED_COLUMN_NAME']]])->get_result()->fetch_row()[0];
                if ($cantidad) throw new RuntimeException('Primero editá o eliminá los ' . $cantidad . ' registros relacionados en ' . $dependencia['TABLE_NAME'] . ' (' . $dependencia['COLUMN_NAME'] . ').');
            }
            tablas_consultar($conexion, 'DELETE FROM ' . $nombreTabla . ' WHERE ' . tablas_identificador($clavePrimaria) . ' = ?', [$idRegistro]);
            $conexion->commit();
            $_SESSION['tablas_mensaje'] = 'Registro eliminado de ' . $tabla . '.';
            header('Location: ' . $paginaOrigen . '?modo=datos&operacion=eliminar&tabla=' . rawurlencode($tabla) . $filtroColumna, true, 303); exit;
        }
        $campos = []; $valores = [];
        foreach ($columnas as $columna) {
            $campo = $columna['Field'];
            if ($campo === $clavePrimaria) continue;
            if (!$crear && $columnaElegida !== '' && $campo !== $columnaElegida) continue;
            if (!isset($_POST['datos']) || !is_array($_POST['datos']) || !array_key_exists($campo, $_POST['datos']) || !is_string($_POST['datos'][$campo])) throw new RuntimeException('Falta el campo ' . $campo . '.');
            $valor = $_POST['datos'][$campo];
            if ($crear && isset($_POST['predeterminados'][$campo]) && $columna['Default'] !== null) continue;
            if ($tabla === 'usuarios' && $campo === 'contraseña') {
                if ($valor === '' && !$crear) continue;
                if (strlen($valor) < 8 || strlen($valor) > 72) throw new RuntimeException('La contraseña debe tener entre 8 y 72 bytes.');
                $valor = password_hash($valor, PASSWORD_DEFAULT);
            } else {
                if (isset($_POST['nulos']) && is_array($_POST['nulos']) && ($_POST['nulos'][$campo] ?? '') === '1') $valor = null;
                tablas_validar_campo($columna, $valor);
            }
            if ($tabla === 'empleado' && $campo === 'id_correo' && ($valor === null || trim($valor) === '')) throw new RuntimeException('Seleccioná el usuario con el que iniciará sesión el empleado.');
            if (in_array($tabla, ['usuarios', 'empleado'], true) && in_array($campo, ['nombre', 'apellido', 'dni', 'telefono'], true)) {
                if ($valor === null || trim($valor) === '') throw new RuntimeException('El campo ' . admin_etiqueta_dato($campo) . ' es obligatorio y no puede quedar vacío.');
                $valor = trim($valor);
            }
            if (in_array($campo, ['cantidad','precio_unitario','subtotal','total','precio_total','precio_especial','mano_obra'], true) && $valor !== null && (float)$valor < 0) throw new RuntimeException('El campo ' . $campo . ' no puede ser negativo.');
            $tipoDocumento = strtoupper(trim((string)($_POST['datos']['tipo_documento'] ?? $registro['tipo_documento'] ?? 'DNI')));
            if ($campo === 'dni' && $tipoDocumento === 'DNI' && $valor !== null && !preg_match('/^[0-9]{7,8}$/D', $valor)) throw new RuntimeException('El DNI debe tener 7 u 8 números.');
            if (in_array($tabla, ['usuarios','empleado'], true) && in_array($campo, ['nombre','apellido'], true) && !preg_match("/^[\\p{L}\\p{M}]+(?:[ '\x{2019}-][\\p{L}\\p{M}]+)*$/uD", $valor)) throw new RuntimeException('Nombre y apellido solo admiten letras, espacios, apóstrofes y guiones.');
            if (in_array($campo, ['correo','email'], true) && $valor !== null && $valor !== '' && !filter_var($valor,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo no es válido.');
            if (!$crear && $tabla === 'usuarios' && $registro['correo'] === 'gpufixstore@gmail.com' && $campo === 'correo' && $valor !== $registro['correo']) throw new RuntimeException('El correo de la cuenta principal está protegido.');
            if ($tabla === 'empleado' && $campo === 'email') {
                if ($valor === null || !filter_var($valor, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ingresá el correo del empleado.');
                $valor = strtolower(trim($valor));
                if (!$crear && $registro['email'] === 'gpufixstore@gmail.com' && $valor !== $registro['email']) throw new RuntimeException('El correo principal está protegido.');
            }
            if (!$crear && $tabla === 'puesto' && $registro['nombre'] === 'Administrador' && $campo === 'nombre' && $valor !== 'Administrador') throw new RuntimeException('El puesto de la cuenta principal está protegido.');
            $campos[] = tablas_identificador($campo) . ' = ?'; $valores[] = $valor;
        }
        if ($crear) {
            tablas_consultar($conexion, 'INSERT INTO ' . $nombreTabla . ' SET ' . implode(', ', $campos), $valores);
            $idRegistro = (string)$conexion->insert_id;
        } elseif ($campos) {
            $valores[] = $idRegistro;
            tablas_consultar($conexion, 'UPDATE ' . $nombreTabla . ' SET ' . implode(', ', $campos) . ' WHERE ' . tablas_identificador($clavePrimaria) . ' = ?', $valores);
        }
        if (in_array($tabla, ['stock', 'stock_gpu'], true)) {
            $estadoStock = tablas_consultar($conexion, 'SELECT cantidad,ubicacion FROM ' . $nombreTabla . ' WHERE ' . tablas_identificador($clavePrimaria) . '=?', [$idRegistro])->get_result()->fetch_assoc();
            if (trim((string)$estadoStock['ubicacion']) === '') throw new RuntimeException('Seleccioná la sucursal del stock.');
            $estadoAutomatico = (int)$estadoStock['cantidad'] > 0 ? 'Disponible' : 'Sin stock';
            tablas_consultar($conexion, 'UPDATE ' . $nombreTabla . ' SET estado=? WHERE ' . tablas_identificador($clavePrimaria) . '=?', [$estadoAutomatico, $idRegistro]);
        }
        admin_validar_relaciones($conexion, $tabla, $idRegistro);
        $conexion->commit();
        $_SESSION['tablas_mensaje'] = 'Registro ' . ($crear ? 'agregado' : 'actualizado') . ' en ' . $tabla . '.';
        header('Location: ' . $paginaOrigen . '?modo=datos&tabla=' . rawurlencode($tabla) . '&editar=' . rawurlencode($idRegistro) . $filtroColumna, true, 303); exit;
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
if ($crear) {
    $registro = [];
    foreach ($columnas as $columna) $registro[$columna['Field']] = $columna['Default'];
}
$relaciones = tablas_consultar($conexion, 'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$tabla])->get_result()->fetch_all(MYSQLI_ASSOC);
$relacionesPorCampo = array_column($relaciones,null,'COLUMN_NAME');
$vinculosObligatorios = ['presupuesto'=>['id_cliente'], 'empleado'=>['email','id_correo'], 'stock_gpu'=>['ubicacion'], 'stock'=>['ubicacion']];
$referenciasReparacion = [];
foreach ($relaciones as $relacion) {
    $destino = $relacion['REFERENCED_TABLE_NAME'];
    $idDestino = $relacion['REFERENCED_COLUMN_NAME'];
    $referenciasReparacion[$destino] = [];
    $filasRelacion = $conexion->query('SELECT * FROM ' . tablas_identificador($destino) . ' ORDER BY ' . tablas_identificador($idDestino))->fetch_all(MYSQLI_ASSOC);
    foreach ($filasRelacion as $filaRelacion) {
        $descripcion = [$destino . ' #' . $filaRelacion[$idDestino]];
        foreach (['nombre','apellido','correo','marca','modelo','numero_factura','estado','id_cliente','id_correo','id_gpu','id_orden_reparacion'] as $descriptivo) {
            if (isset($filaRelacion[$descriptivo])) $descripcion[] = $descriptivo . ': ' . $filaRelacion[$descriptivo];
        }
        $referenciasReparacion[$destino][$filaRelacion[$idDestino]] = implode(' · ', $descripcion);
    }
}
$consultasPersonas = [
    'cliente' => "SELECT c.id_cliente id, CONCAT('Cliente #',c.id_cliente,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM cliente c JOIN usuarios u ON u.id_correo=c.id_correo",
    'empleado' => "SELECT e.id_empleado id, CONCAT('Empleado #',e.id_empleado,' · ',e.nombre,' ',e.apellido,' · ',COALESCE(e.email,'')) etiqueta FROM empleado e",
    'gpu_ventas' => "SELECT v.id_gpu_ventas id, CONCAT('Venta #',v.id_gpu_ventas,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM gpu_ventas v JOIN cliente c ON c.id_cliente=v.id_cliente JOIN usuarios u ON u.id_correo=c.id_correo",
    'gpu_cliente' => "SELECT g.id_gpu_cliente id, CONCAT('Placa #',g.id_gpu_cliente,' · ',g.marca,' ',g.modelo,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM gpu_cliente g JOIN cliente c ON c.id_cliente=g.id_cliente JOIN usuarios u ON u.id_correo=c.id_correo",
    'orden_reparacion' => "SELECT o.id_orden_reparacion id, CONCAT('Orden #',o.id_orden_reparacion,' · ',g.marca,' ',g.modelo,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM orden_reparacion o JOIN gpu_cliente g ON g.id_gpu_cliente=o.id_gpu_cliente JOIN cliente c ON c.id_cliente=g.id_cliente JOIN usuarios u ON u.id_correo=c.id_correo",
    'reparacion' => "SELECT r.id_reparacion id, CONCAT('Reparación #',r.id_reparacion,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM reparacion r JOIN orden_reparacion o ON o.id_orden_reparacion=r.id_orden_reparacion JOIN gpu_cliente g ON g.id_gpu_cliente=o.id_gpu_cliente JOIN cliente c ON c.id_cliente=g.id_cliente JOIN usuarios u ON u.id_correo=c.id_correo",
    'presupuesto' => "SELECT p.id_presupuesto id, CONCAT('Presupuesto #',p.id_presupuesto,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM presupuesto p JOIN cliente c ON c.id_cliente=p.id_cliente JOIN usuarios u ON u.id_correo=c.id_correo",
    'factura' => "SELECT f.id_factura id, CONCAT('Factura ',f.numero_factura,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM factura f JOIN cliente c ON c.id_cliente=f.id_cliente JOIN usuarios u ON u.id_correo=c.id_correo",
    'carrito' => "SELECT ca.id_carrito id, CONCAT('Carrito #',ca.id_carrito,' · ',u.nombre,' ',u.apellido,' · ',u.correo) etiqueta FROM carrito ca JOIN cliente c ON c.id_cliente=ca.id_cliente JOIN usuarios u ON u.id_correo=c.id_correo",
    'orden_compra' => "SELECT o.id_orden_compra id, CONCAT('Compra #',o.id_orden_compra,' · ',p.razon_social,' · Responsable: ',e.nombre,' ',e.apellido,' · ',COALESCE(e.email,'')) etiqueta FROM orden_compra o JOIN proveedor p ON p.id_proveedor=o.id_proveedor JOIN empleado e ON e.id_empleado=o.id_empleado_recepcion"
];
foreach ($consultasPersonas as $destino => $sqlPersonas) {
    if (isset($referenciasReparacion[$destino])) {
        foreach ($conexion->query($sqlPersonas)->fetch_all(MYSQLI_ASSOC) as $persona) {
            $referenciasReparacion[$destino][$persona['id']] = $persona['etiqueta'];
        }
    }
}
if ($paginaOrigen === 'admin-tablas.php?tabla=orden_reparacion&vista=acciones') {
    $consultasReferencias = [
        'cliente' => "SELECT c.id_cliente AS id, CONCAT('Cliente #', c.id_cliente, ' · ', u.nombre, ' ', u.apellido, ' · ', u.correo) AS etiqueta FROM cliente c INNER JOIN usuarios u ON u.id_correo = c.id_correo ORDER BY u.nombre, u.apellido",
        'gpu_cliente' => "SELECT g.id_gpu_cliente AS id, CONCAT('Placa #', g.id_gpu_cliente, ' · ', g.marca, ' ', g.modelo, ' · Serie: ', COALESCE(g.numero_serie, ''), ' · Cliente: ', u.nombre, ' ', u.apellido, ' · ', u.correo) AS etiqueta FROM gpu_cliente g INNER JOIN cliente c ON c.id_cliente = g.id_cliente INNER JOIN usuarios u ON u.id_correo = c.id_correo ORDER BY u.nombre, u.apellido, g.id_gpu_cliente",
        'orden_reparacion' => "SELECT o.id_orden_reparacion AS id, CONCAT('Orden #', o.id_orden_reparacion, ' · ', g.marca, ' ', g.modelo, ' · Cliente: ', u.nombre, ' ', u.apellido, ' · ', u.correo) AS etiqueta FROM orden_reparacion o INNER JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente INNER JOIN cliente c ON c.id_cliente = g.id_cliente INNER JOIN usuarios u ON u.id_correo = c.id_correo ORDER BY o.id_orden_reparacion DESC",
        'reparacion' => "SELECT r.id_reparacion AS id, CONCAT('Trabajo #', r.id_reparacion, ' · Orden #', o.id_orden_reparacion, ' · ', g.marca, ' ', g.modelo, ' · Cliente: ', u.nombre, ' ', u.apellido, ' · ', u.correo) AS etiqueta FROM reparacion r INNER JOIN orden_reparacion o ON o.id_orden_reparacion = r.id_orden_reparacion INNER JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente INNER JOIN cliente c ON c.id_cliente = g.id_cliente INNER JOIN usuarios u ON u.id_correo = c.id_correo ORDER BY r.id_reparacion DESC"
    ];
    foreach ($consultasReferencias as $tablaReferencia => $sqlReferencia) {
        $referenciasReparacion[$tablaReferencia] = array_column($conexion->query($sqlReferencia)->fetch_all(MYSQLI_ASSOC), 'etiqueta', 'id');
    }
}
$buscar = is_string($_GET['buscar'] ?? null) ? mb_substr($_GET['buscar'],0,100,'UTF-8') : '';
$pagina = max(1,(int)($_GET['pagina'] ?? 1));
$condiciones=[]; $filtros=[];
if ($buscar !== '') foreach ($columnas as $columna) {
    if ($columna['Field'] === 'contraseña') continue;
    if ($columnaElegida !== '' && $columna['Field'] !== $columnaElegida) continue;
    $condiciones[] = 'CAST(' . tablas_identificador($columna['Field']) . ' AS CHAR) LIKE ?'; $filtros[] = '%' . $buscar . '%';
}
$where = $condiciones ? ' WHERE (' . implode(' OR ', $condiciones) . ')' : '';
$total = (int)tablas_consultar($conexion,'SELECT COUNT(*) FROM ' . $nombreTabla . $where,$filtros)->get_result()->fetch_row()[0];
$paginas = max(1,(int)ceil($total/25)); $pagina=min($pagina,$paginas); $desplazamiento=($pagina-1)*25;
$registros = tablas_consultar($conexion,'SELECT * FROM ' . $nombreTabla . $where . ' ORDER BY ' . tablas_identificador($clavePrimaria) . ' LIMIT 25 OFFSET ' . $desplazamiento,$filtros)->get_result()->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio($tituloSeccion);
if ($paginaOrigen === 'admin-tablas.php') { admin_menu_tablas($tabla); admin_tabs_tabla($tabla); }
$columnasVisibles = array_values(array_filter($columnas, static fn ($columna) => $columnaElegida === '' || $columna['Field'] === $columnaElegida || $columna['Field'] === $clavePrimaria));
$soloLectura = $columnaElegida === $clavePrimaria;

?>
<h1 class="h3 mt-3"><?= admin_e($tituloSeccion) ?></h1>
<?php if (in_array($tabla, ['presupuesto', 'detalle_presupuesto'], true)): ?><p><a class="btn btn-primary" href="admin-tablas.php?tabla=presupuesto&amp;vista=acciones<?= $tabla === 'presupuesto' && $idRegistro !== '' ? '#solicitud-p-' . (int)$idRegistro : '' ?>">Ver presupuestos y responder consultas</a></p><?php endif; ?>
<?php if ($tabla === 'empleado'): ?><p>El empleado inicia sesión con el correo y la contraseña del usuario vinculado. La contraseña se administra en Usuarios.</p><?php endif; ?>
<form method="get" class="panel d-flex flex-wrap gap-3 align-items-end">
<input type="hidden" name="tabla" value="<?= admin_e($tabla) ?>">
<label class="flex-grow-1" for="elegirColumna">Columna<select class="form-select" id="elegirColumna" name="columna" required>
<option value="">Seleccionar columna</option>
<?php foreach ($columnas as $opcion): ?><option value="<?= admin_e($opcion['Field']) ?>" <?= $columnaElegida === $opcion['Field'] ? 'selected' : '' ?>><?= admin_e(admin_etiqueta_dato($opcion['Field']) . ' · ' . $opcion['Field']) ?></option><?php endforeach; ?>
</select></label><button class="btn btn-primary">Ver columna</button></form>
<?php if ($columnaElegida === '' && !$crear && !$registro): ?>
<p>Seleccioná una columna para mostrar sus valores, o agregá un registro completo.</p><a class="btn btn-primary" href="admin-tablas.php?tabla=<?= rawurlencode($tabla) ?>&amp;nuevo=1">Agregar registro</a>
<?php if ($paginaOrigen === 'admin-tablas.php') echo '</div></div>'; admin_layout_fin(); exit; endif; ?>
<?php if ($soloLectura): ?><p>Este campo se conserva como identificador del registro y se muestra para consulta.</p><?php endif; ?>
<p class="text-secondary">Agregá, editá o eliminá registros. Los ID se generan automáticamente y se conservan al editar. Esta edición actúa directamente sobre las tablas: no recalcula ventas ni stock de otras tablas ni envía correos.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<?php if ($mensaje): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<p><a href="<?= admin_e($paginaOrigen) ?>">← <?= $paginaOrigen === 'admin-tablas.php' ? 'Ver todas las secciones' : 'Volver a ' . admin_e($tituloSeccion) ?></a></p>
<?php if (!$crear): ?><section class="panel"><form method="get" class="row g-3 align-items-end"><input type="hidden" name="modo" value="datos"><input type="hidden" name="operacion" value="<?= admin_e($operacion) ?>"><input type="hidden" name="columna" value="<?= admin_e($columnaElegida) ?>">
<?php if ($paginaOrigen === 'admin-tablas.php'): ?><input type="hidden" name="tabla" value="<?= admin_e($tabla) ?>"><?php else: ?>
<div class="col-md-5"><label class="form-label" for="tablaDatos">Tabla</label><select class="form-select" id="tablaDatos" name="tabla"><?php foreach ($tablasPermitidas as $opcion): ?><option <?= $tabla === $opcion ? 'selected' : '' ?> value="<?= admin_e($opcion) ?>"><?= admin_e($opcion . " · " . admin_etiqueta_dato($opcion)) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
<div class="<?= $paginaOrigen === 'admin-tablas.php' ? 'col-md' : 'col-md-5' ?>"><label class="form-label" for="buscarDatos">Buscar en los registros</label><input class="form-control" id="buscarDatos" name="buscar" maxlength="100" value="<?= admin_e($buscar) ?>"></div>
<div class="col-md-auto"><button class="btn btn-primary" type="submit">Buscar</button></div></form></section><?php endif; ?>
<nav class="tablas-acciones d-flex flex-wrap gap-2 mb-3" aria-label="Acciones de la tabla">
<?php foreach (['agregar'=>'Agregar','editar'=>'Editar','eliminar'=>'Eliminar'] as $accionTabla=>$etiquetaTabla): ?>
<a class="btn <?= $accionTabla === 'eliminar' ? ($eliminar ? 'btn-danger' : 'btn-outline-danger') : ($operacion === $accionTabla ? 'btn-primary' : 'btn-outline-secondary') ?>" href="<?= admin_e($paginaOrigen) ?>?tabla=<?= rawurlencode($tabla) ?>&amp;modo=datos&amp;<?= $accionTabla === 'agregar' ? 'nuevo=1' : 'operacion=' . $accionTabla ?><?= admin_e($accionTabla === 'agregar' ? '' : $filtroColumna) ?>"><?= $etiquetaTabla ?></a>
<?php endforeach; ?></nav>
<?php if ($tabla === 'gpu_ventas'): ?><p><a class="btn btn-outline-primary" href="admin-comprobantes.php<?= $idRegistro !== '' ? '?venta=' . (int)$idRegistro : '' ?>">Ver o agregar comprobantes de transferencia</a></p><?php endif; ?>
<?php if ($registro): ?>
<section class="panel" id="editar-registro"><h2 class="h5"><?= ucfirst($operacion) ?> · <?= admin_e(admin_etiqueta_dato($tabla)) ?><?= $idRegistro !== '' ? ' #' . admin_e($idRegistro) : '' ?></h2>
<?php if (isset($referenciasReparacion[$tabla][$idRegistro])): ?><p class="border rounded p-3 bg-light"><?= admin_e($referenciasReparacion[$tabla][$idRegistro]) ?></p><?php endif; ?>
<?php if (!$eliminar && !$soloLectura): ?><form method="post" action="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;tabla=<?= rawurlencode($tabla) ?>&amp;<?= $crear ? "nuevo=1" : "editar=" . rawurlencode($idRegistro) ?><?= admin_e($crear ? '' : $filtroColumna) ?>" class="row g-3">
<input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="version" value="<?= admin_e(tablas_version($registro)) ?>">
<?php foreach ($columnas as $indice=>$columna): $campo=$columna['Field']; if ($crear && $campo === $clavePrimaria) continue; if (!$crear && $columnaElegida !== '' && $campo !== $columnaElegida) continue; $esClave=$campo===$clavePrimaria; $esSecreto=$tabla === 'usuarios' && $campo==='contraseña'; $valor=$esSecreto ? '' : ($valoresFormulario[$campo] ?? $registro[$campo]); if ($valor!==null) $valor=is_scalar($valor) ? (string)$valor : ''; $esTexto=str_contains($columna['Type'],'text'); $esObligatorioCuenta=(in_array($tabla, ['usuarios','empleado'], true) && in_array($campo, ['nombre','apellido','dni','telefono'], true)) || in_array($campo, $vinculosObligatorios[$tabla] ?? [], true); ?>
<div class="<?= $esTexto ? 'col-12' : 'col-md-6' ?>"><label class="form-label" for="campo-<?= $indice ?>"><?= admin_e($esSecreto && $crear ? 'Contraseña' : admin_etiqueta_dato($campo)) ?><?= $esClave ? ' · ID' : '' ?></label>
<?php $opcionesRelacion = isset($relacionesPorCampo[$campo]) ? ($referenciasReparacion[$relacionesPorCampo[$campo]['REFERENCED_TABLE_NAME']] ?? null) : null; ?>
<?php if (!$esClave && $opcionesRelacion !== null): ?>
<select class="form-select" id="campo-<?= $indice ?>" name="datos[<?= admin_e($campo) ?>]" <?= $esObligatorioCuenta || $columna['Null'] === 'NO' ? 'required' : '' ?>>
    <option value="">Seleccionar <?= admin_e(admin_etiqueta_dato($campo)) ?></option>
    <?php foreach ($opcionesRelacion as $idOpcion => $etiquetaOpcion): ?><option value="<?= (int) $idOpcion ?>" <?= (string) $valor === (string) $idOpcion ? 'selected' : '' ?>><?= admin_e($etiquetaOpcion) ?></option><?php endforeach; ?>
</select>
<?php elseif ($esTexto): ?><textarea class="form-control" id="campo-<?= $indice ?>" name="datos[<?= admin_e($campo) ?>]" rows="4"><?= admin_e($valor) ?></textarea>
<?php else: ?><input class="form-control" type="<?= $esSecreto ? 'password' : 'text' ?>" autocomplete="<?= $esSecreto ? 'new-password' : 'off' ?>" id="campo-<?= $indice ?>" name="datos[<?= admin_e($campo) ?>]" value="<?= admin_e($valor) ?>" <?= $esClave ? 'readonly' : '' ?> <?= $esObligatorioCuenta ? 'required' : '' ?>><?php endif; ?>
<?php if ($campo === 'imagen' && is_string($valor) && preg_match('~^https?://~i', $valor)): ?><a href="<?= admin_e($valor) ?>" target="_blank" rel="noopener noreferrer"><img src="<?= admin_e($valor) ?>" alt="Imagen del producto" loading="lazy" class="img-thumbnail mt-2" style="width:160px;height:120px;object-fit:contain"></a><?php endif; ?>
<div class="form-text"><?= admin_e($columna['Type']) ?><?= $esSecreto ? ' · Al editar, dejá vacío para conservar la contraseña. Al crear, ingresá al menos 8 caracteres.' : '' ?><?php if(isset($relacionesPorCampo[$campo])): $relacion=$relacionesPorCampo[$campo]; ?> · Vinculado a <a href="<?= admin_e(admin_enlace_datos($relacion['REFERENCED_TABLE_NAME'])) ?>"><?= admin_e($relacion['REFERENCED_TABLE_NAME'] . '.' . $relacion['REFERENCED_COLUMN_NAME']) ?></a><?php endif; ?></div>
<?php if ($crear && $columna['Default'] !== null): ?><label class="d-block"><input type="checkbox" name="predeterminados[<?= admin_e($campo) ?>]" value="1" <?= !$error || isset($_POST['predeterminados'][$campo]) ? "checked" : "" ?>> Usar valor predeterminado (<?= admin_e($columna['Default']) ?>)</label><?php endif; ?>
<?php if (!$esClave && !$esObligatorioCuenta && $columna['Null']==='YES'): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="nulo-<?= $indice ?>" name="nulos[<?= admin_e($campo) ?>]" value="1" <?= ($error && isset($_POST['nulos'][$campo])) || (!$error && $valor===null) ? 'checked' : '' ?>><label class="form-check-label" for="nulo-<?= $indice ?>">Sin completar</label></div><?php endif; ?></div>
<?php endforeach; ?>
<div class="col-12 d-flex flex-wrap gap-2 tablas-guardar"><button class="btn btn-primary" type="submit"><?= $crear ? 'Agregar registro' : 'Guardar cambios' ?></button><a class="btn btn-outline-secondary" href="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;tabla=<?= rawurlencode($tabla) ?><?= admin_e($filtroColumna) ?>">Cancelar</a></div>
</form><?php endif; ?>
<?php if ($eliminar): ?><dl class="row"><?php foreach ($registro as $campoResumen=>$valorResumen): if ($campoResumen === 'contraseña') continue; ?><dt class="col-sm-4"><?= admin_e($campoResumen) ?></dt><dd class="col-sm-8"><?= admin_e($valorResumen ?? 'Sin valor') ?></dd><?php endforeach; ?></dl><form method="post" class="mt-4 border-top pt-3 tablas-confirmar-baja">
<input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="version" value="<?= admin_e(tablas_version($registro)) ?>"><input type="hidden" name="accion" value="eliminar">
<label><input type="checkbox" name="confirmar_eliminar" value="1" required> <?= $tabla === 'presupuesto' ? 'Confirmo eliminar todos los registros de esta consulta y los detalles de su presupuesto. Si su orden antigua solo corresponde a esta consulta, también se eliminará.' : 'Confirmo eliminar este registro de ' . admin_e($tabla) . '.' ?></label>
<button class="btn btn-danger" type="submit">Eliminar registro</button></form><?php endif; ?>
</section><?php endif; ?>
<?php if (!$crear): ?><section class="panel"><h2 class="h5"><?= admin_e(admin_etiqueta_dato($tabla)) ?> · <?= $total ?> registros</h2>
<div class="table-responsive" tabindex="0" aria-label="Registros de <?= admin_e($tabla) ?>"><table class="table table-striped"><thead><tr><th>Acción</th><?php foreach ($columnasVisibles as $columna): ?><th scope="col"><?= admin_e(admin_etiqueta_dato($columna['Field'])) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($registros as $fila): ?><tr><td><?php if (!$soloLectura || $eliminar): ?><a class="btn btn-sm <?= $eliminar ? 'btn-outline-danger' : 'btn-outline-primary' ?>" href="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;tabla=<?= rawurlencode($tabla) ?>&amp;operacion=<?= admin_e($operacion) ?>&amp;editar=<?= rawurlencode($fila[$clavePrimaria]) ?><?= admin_e($filtroColumna) ?>#editar-registro"><?= $eliminar ? "Eliminar registro" : "Editar valor" ?></a><?php else: ?>ID<?php endif; ?></td><?php foreach ($columnasVisibles as $columna): $campo=$columna['Field']; $contenido=$fila[$campo]; $etiquetaRelacion = isset($relacionesPorCampo[$campo]) ? ($referenciasReparacion[$relacionesPorCampo[$campo]['REFERENCED_TABLE_NAME']][$contenido ?? ''] ?? null) : null; ?><td><?php if ($tabla === 'usuarios' && $campo==='contraseña'): ?>Contraseña guardada<?php elseif ($campo==='imagen' && is_string($contenido) && preg_match('~^https?://~i', $contenido)): ?><a href="<?= admin_e($contenido) ?>" target="_blank" rel="noopener noreferrer"><img src="<?= admin_e($contenido) ?>" alt="Imagen del producto" loading="lazy" class="img-thumbnail" style="width:100px;height:80px;object-fit:contain"></a><?php elseif ($contenido===null): ?><span class="text-secondary">Sin valor</span><?php elseif ($etiquetaRelacion !== null): ?><?= admin_e($etiquetaRelacion) ?><?php else: ?><?= admin_e(mb_strimwidth((string)$contenido,0,160,'…','UTF-8')) ?><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$registros): ?><tr><td colspan="<?= count($columnasVisibles)+1 ?>">No hay registros para mostrar.</td></tr><?php endif; ?></tbody></table></div>
<nav aria-label="Páginas de registros" class="d-flex flex-wrap gap-3 align-items-center"><?php foreach (['Anterior'=>$pagina-1,'Siguiente'=>$pagina+1] as $etiqueta=>$numero): if ($numero<1 || $numero>$paginas) continue; ?><a href="<?= admin_e($paginaOrigen) ?>?modo=datos&amp;<?= admin_e(http_build_query(['tabla'=>$tabla,'buscar'=>$buscar,'pagina'=>$numero,'operacion'=>$operacion,'columna'=>$columnaElegida])) ?>"><?= $etiqueta ?></a><?php endforeach; ?><span>Página <?= $pagina ?> de <?= $paginas ?></span></nav></section>
<?php endif; if ($paginaOrigen === 'admin-tablas.php') echo '</div></div>'; admin_layout_fin(); exit; }

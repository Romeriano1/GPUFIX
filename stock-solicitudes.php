<?php
require_once __DIR__ . '/sucursales.php';

function solicitud_estado_stock(array $lineas, bool $validada): string
{
    $solicitado = array_sum(array_column($lineas, 'cantidad_solicitada'));
    $enviado = array_sum(array_column($lineas, 'cantidad_enviada'));
    $recibido = array_sum(array_column($lineas, 'cantidad_recibida'));
    if ($solicitado > 0 && $recibido === $solicitado) return 'Completada';
    if ($enviado > $recibido) return 'En camino';
    if ($recibido > 0) return 'Parcialmente recibida';
    return $validada ? 'Validada' : 'Pendiente';
}

function solicitud_validar_lineas(array $productos, array $cantidades): array
{
    if (!$productos || count($productos) > 50 || count($productos) !== count($cantidades)) throw new RuntimeException('Agregá entre 1 y 50 productos.');
    $lineas = []; $cantidades = array_values($cantidades);
    foreach (array_values($productos) as $i => $producto) {
        if (!is_string($producto) || !preg_match('/^(gpu|componente):([1-9][0-9]{0,9})$/D', $producto, $m)) throw new RuntimeException('Seleccioná un producto válido en cada fila.');
        $cantidad = filter_var($cantidades[$i], FILTER_VALIDATE_INT);
        if ($cantidad === false || $cantidad < ($m[1] === 'gpu' ? 2 : 1) || $cantidad > ($m[1] === 'gpu' ? 10 : 1000000)) throw new RuntimeException($m[1] === 'gpu' ? 'Pedí entre 2 y 10 placas por producto.' : 'La cantidad de componentes debe ser un entero positivo.');
        if (isset($lineas[$producto])) throw new RuntimeException('Unificá las cantidades del producto repetido.');
        $lineas[$producto] = ['tipo'=>$m[1], 'id'=>(int)$m[2], 'cantidad'=>$cantidad];
    }
    uasort($lineas, static fn($a,$b) => strcmp($a['tipo'],$b['tipo']) ?: $a['id'] <=> $b['id']);
    return array_values($lineas);
}

// El llamador mantiene una transacción. Se bloquea el producto antes que sus existencias.
function solicitud_mover_stock(mysqli $db, string $tipo, int $id, int $sucursal, int $delta): void
{
    if (!in_array($tipo, ['gpu','componente'], true)) throw new RuntimeException('Tipo de producto no válido.');
    $tablaProducto = $tipo === 'gpu' ? 'gpu' : 'componente'; $campo = $tipo === 'gpu' ? 'id_gpu' : 'id_componente';
    $tabla = $tipo === 'gpu' ? 'stock_gpu' : 'stock'; $clave = $tipo === 'gpu' ? 'id_stock_gpu' : 'id_stock';
    $q = $db->prepare("SELECT $campo FROM $tablaProducto WHERE $campo=? FOR UPDATE"); $q->bind_param('i',$id); $q->execute(); $existe = $q->get_result()->fetch_row(); $q->close();
    if (!$existe) throw new RuntimeException('El producto ya no existe.');
    $q = $db->prepare("SELECT $clave AS id,cantidad FROM $tabla WHERE $campo=? AND id_sucursal=? ORDER BY $clave FOR UPDATE"); $q->bind_param('ii',$id,$sucursal); $q->execute(); $stocks = $q->get_result()->fetch_all(MYSQLI_ASSOC); $q->close();
    $total = array_sum(array_column($stocks,'cantidad'));
    if ($total + $delta < 0) throw new RuntimeException('No alcanza el stock del depósito para las cantidades indicadas. Podés enviar una cantidad menor.');
    if ($total + $delta > 2147483647) throw new RuntimeException('La cantidad resultante supera el límite de stock.');
    if ($delta > 0) {
        if ($stocks) { $q = $db->prepare("UPDATE $tabla SET cantidad=cantidad+?,estado='Disponible' WHERE $clave=?"); $q->bind_param('ii',$delta,$stocks[0]['id']); }
        else { $q = $db->prepare("INSERT INTO $tabla($campo,id_sucursal,cantidad,estado) VALUES(?,?,?,'Disponible')"); $q->bind_param('iii',$id,$sucursal,$delta); }
        $q->execute(); $q->close(); return;
    }
    $pendiente = -$delta;
    foreach ($stocks as $stock) {
        $descuento = min($pendiente, max(0,(int)$stock['cantidad'])); if (!$descuento) continue;
        $restante = (int)$stock['cantidad']-$descuento; $estado = $restante > 0 ? 'Disponible' : 'Sin stock';
        $q = $db->prepare("UPDATE $tabla SET cantidad=?,estado=? WHERE $clave=?"); $q->bind_param('isi',$restante,$estado,$stock['id']); $q->execute(); $q->close();
        $pendiente -= $descuento; if (!$pendiente) break;
    }
}

function solicitud_crear_stock(mysqli $db, int $sucursal, array $productos, array $cantidades, string $observaciones): int
{
    if (!admin_puede_pedir_stock($db)) throw new RuntimeException('Solo el Jefe Administrativo y administración pueden solicitar stock.');
    admin_validar_sucursal_empleado($db,(string)$sucursal);
    if ($sucursal === sucursal_id_deposito()) throw new RuntimeException('Los pedidos de reposición corresponden a una sucursal.');
    if (mb_strlen($observaciones,'UTF-8') > 3000) throw new RuntimeException('Las observaciones admiten hasta 3000 caracteres.');
    $lineas = solicitud_validar_lineas($productos,$cantidades); $empleado = (int)$_SESSION['empleado_id'];
    $db->begin_transaction();
    try {
        $q=$db->prepare('INSERT INTO solicitud_stock(id_sucursal,id_empleado,observaciones) VALUES(?,?,?)');$q->bind_param('iis',$sucursal,$empleado,$observaciones);$q->execute();$id=(int)$db->insert_id;$q->close();
        foreach($lineas as $linea){
            $tabla=$linea['tipo']==='gpu'?'gpu':'componente';$campo=$linea['tipo']==='gpu'?'id_gpu':'id_componente';
            $q=$db->prepare("SELECT $campo FROM $tabla WHERE $campo=? LOCK IN SHARE MODE");$q->bind_param('i',$linea['id']);$q->execute();$existe=$q->get_result()->fetch_row();$q->close();if(!$existe)throw new RuntimeException('Uno de los productos ya no existe.');
            $gpu=$linea['tipo']==='gpu'?$linea['id']:null;$componente=$linea['tipo']==='componente'?$linea['id']:null;
            $q=$db->prepare('INSERT INTO detalle_solicitud_stock(id_solicitud_stock,id_gpu,id_componente,cantidad_solicitada) VALUES(?,?,?,?)');$q->bind_param('iiii',$id,$gpu,$componente,$linea['cantidad']);$q->execute();$q->close();
        }
        $db->commit();return $id;
    } catch(Throwable $error){$db->rollback();throw $error;}
}

function solicitud_procesar_stock(mysqli $db, int $id, string $accion, int $version, array $cantidades): void
{
    if (!in_array($accion,['validar','despachar','recibir'],true))throw new RuntimeException('Acción no válida.');
    if ($accion==='validar' && !admin_es_compras_deposito($db))throw new RuntimeException('Solo Compras y Stock puede validar los pedidos.');
    if ($accion==='despachar' && !admin_es_logistica_deposito($db))throw new RuntimeException('Solo Logística puede despachar.');
    if ($accion==='recibir' && !admin_puede_pedir_stock($db))throw new RuntimeException('Solo el Jefe Administrativo puede confirmar el ingreso de su sucursal.');
    $db->begin_transaction();
    try {
        $q=$db->prepare('SELECT * FROM solicitud_stock WHERE id_solicitud_stock=? FOR UPDATE');$q->bind_param('i',$id);$q->execute();$pedido=$q->get_result()->fetch_assoc();$q->close();
        if(!$pedido || (int)$pedido['version']!==$version)throw new RuntimeException('El pedido cambió o ya se procesó esta acción. Recargá la página.');
        if($pedido['estado']==='Completada')throw new RuntimeException('El pedido ya está completo.');
        if($accion==='recibir')admin_validar_sucursal_empleado($db,(string)$pedido['id_sucursal']);
        if($accion==='despachar' && $pedido['id_empleado_validacion']===null)throw new RuntimeException('Compras y Stock debe validar el pedido antes del despacho.');
        $q=$db->prepare('SELECT * FROM detalle_solicitud_stock WHERE id_solicitud_stock=? ORDER BY id_componente,id_gpu,id_detalle_solicitud_stock FOR UPDATE');$q->bind_param('i',$id);$q->execute();$lineas=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
        $ids=array_map('strval',array_column($lineas,'id_detalle_solicitud_stock'));
        foreach($cantidades as $clave=>$valor)if(!in_array((string)$clave,$ids,true))throw new RuntimeException('Una línea no pertenece a este pedido.');
        if($accion==='validar') {
            $disponible=false;$deposito=sucursal_id_deposito();
            foreach($lineas as $linea) {
                if((int)$linea['cantidad_enviada'] >= (int)$linea['cantidad_solicitada'])continue;
                $tabla=$linea['id_gpu']!==null?'stock_gpu':'stock';$campo=$linea['id_gpu']!==null?'id_gpu':'id_componente';$producto=(int)($linea['id_gpu']??$linea['id_componente']);
                $q=$db->prepare("SELECT COALESCE(SUM(cantidad),0) FROM $tabla WHERE $campo=? AND id_sucursal=?");$q->bind_param('ii',$producto,$deposito);$q->execute();$total=(int)$q->get_result()->fetch_row()[0];$q->close();
                if($total>0){$disponible=true;break;}
            }
            if(!$disponible)throw new RuntimeException('Todavía no hay stock para este pedido en el depósito. Primero registrá el ingreso desde proveedores.');
        }
        $unidades=0;
        if($accion!=='validar')foreach($lineas as &$linea){
            $n=filter_var($cantidades[$linea['id_detalle_solicitud_stock']]??0,FILTER_VALIDATE_INT);
            $limite=$accion==='despachar'?(int)$linea['cantidad_solicitada']-(int)$linea['cantidad_enviada']:(int)$linea['cantidad_enviada']-(int)$linea['cantidad_recibida'];
            if($n===false||$n<0||$n>$limite)throw new RuntimeException('Revisá las cantidades: no pueden superar lo pendiente de enviar o recibir.');
            if(!$n)continue;
            $tipo=$linea['id_gpu']!==null?'gpu':'componente';$idProducto=(int)($linea['id_gpu']??$linea['id_componente']);
            solicitud_mover_stock($db,$tipo,$idProducto,$accion==='despachar'?sucursal_id_deposito():(int)$pedido['id_sucursal'],$accion==='despachar'?-$n:$n);
            $campo=$accion==='despachar'?'cantidad_enviada':'cantidad_recibida';$linea[$campo]=(int)$linea[$campo]+$n;
            $q=$db->prepare("UPDATE detalle_solicitud_stock SET $campo=? WHERE id_detalle_solicitud_stock=?");$q->bind_param('ii',$linea[$campo],$linea['id_detalle_solicitud_stock']);$q->execute();$q->close();$unidades+=$n;
        } unset($linea);
        if($accion!=='validar'&&!$unidades)throw new RuntimeException('Indicá al menos una unidad para esta acción.');
        $empleado=(int)$_SESSION['empleado_id'];$estado=solicitud_estado_stock($lineas,$accion==='validar'||$pedido['id_empleado_validacion']!==null);
        $campos=['validar'=>['id_empleado_validacion','fecha_validacion'],'despachar'=>['id_empleado_despacho','fecha_despacho'],'recibir'=>['id_empleado_recepcion','fecha_recepcion']];[$campoEmpleado,$campoFecha]=$campos[$accion];
        $q=$db->prepare("UPDATE solicitud_stock SET estado=?,version=version+1,$campoEmpleado=?,$campoFecha=NOW() WHERE id_solicitud_stock=?");$q->bind_param('sii',$estado,$empleado,$id);$q->execute();$q->close();$db->commit();
    } catch(Throwable $error){$db->rollback();throw $error;}
}

function solicitudes_inventario_deposito(mysqli $db): array
{
    $deposito=sucursal_id_deposito(); $inventario=[];
    foreach(['gpu'=>['gpu','id_gpu','stock_gpu',"CONCAT(p.marca,' ',p.modelo)"],'componente'=>['componente','id_componente','stock','p.nombre']] as $tipo=>[$producto,$campo,$stock,$nombre]) {
        foreach($db->query("SELECT p.$campo id,$nombre nombre,COALESCE(SUM(s.cantidad),0) cantidad FROM $producto p LEFT JOIN $stock s ON s.$campo=p.$campo AND s.id_sucursal=$deposito GROUP BY p.$campo,$nombre ORDER BY nombre") as $fila) $inventario[$tipo.':'.$fila['id']]=['tipo'=>$tipo,'id'=>(int)$fila['id'],'nombre'=>$fila['nombre'],'cantidad'=>(int)$fila['cantidad']];
    }
    return $inventario;
}

function solicitudes_panel_post(mysqli $db, string $destino): string
{
    if($_SERVER['REQUEST_METHOD']!=='POST')return '';
    admin_validar_csrf();
    try {
        $accion=is_string($_POST['accion']??null)?$_POST['accion']:'';
        if($accion==='crear_solicitud') {
            $token=is_string($_POST['token_solicitud']??null)?$_POST['token_solicitud']:'';
            if(!hash_equals($_SESSION['token_solicitud_stock']??'', $token)||$token==='')throw new RuntimeException('La solicitud ya fue enviada o el formulario venció. Revisá los pedidos antes de repetirla.');
            $id=solicitud_crear_stock($db,(int)($_POST['id_sucursal']??0),is_array($_POST['producto']??null)?$_POST['producto']:[],is_array($_POST['cantidad']??null)?$_POST['cantidad']:[],is_string($_POST['observaciones']??null)?trim($_POST['observaciones']):'');
            $_SESSION['token_solicitud_stock']=bin2hex(random_bytes(32));
        } else {
            $id=filter_var($_POST['id_solicitud_stock']??0,FILTER_VALIDATE_INT)?:0;
            $version=filter_var($_POST['version']??null,FILTER_VALIDATE_INT);
            if($version===false||$version===null)throw new RuntimeException('Recargá el pedido antes de continuar.');
            solicitud_procesar_stock($db,$id,$accion,$version,is_array($_POST['unidades']??null)?$_POST['unidades']:[]);
        }
        $_SESSION['mensaje_solicitud_stock']='Operación registrada. El stock y los faltantes están actualizados.';
        header('Location: '.$destino.'#solicitud-'.$id,true,303);exit;
    } catch(Throwable $error) {
        error_log('Solicitudes de stock: '.$error->getMessage());
        return $error instanceof mysqli_sql_exception?'No pudimos registrar la operación. No se modificó el stock. Recargá e intentá nuevamente.':$error->getMessage();
    }
}

function solicitudes_render_lista(mysqli $db, string $destino): void
{
    $verTodas=admin_acceso_todas_sucursales($db)||admin_es_compras_deposito($db)||admin_es_logistica_deposito($db);
    $alcance=$verTodas?'':' WHERE p.id_sucursal='.(int)admin_sucursal_empleado($db);
    $pedidos=$db->query('SELECT p.*,s.nombre sucursal,e.nombre,e.apellido FROM solicitud_stock p JOIN sucursales s ON s.id_sucursal=p.id_sucursal JOIN empleado e ON e.id_empleado=p.id_empleado'.$alcance.' ORDER BY p.id_solicitud_stock DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
    $lineasPorPedido=[];$inventario=solicitudes_inventario_deposito($db);
    if($pedidos) {
        $ids=implode(',',array_map('intval',array_column($pedidos,'id_solicitud_stock')));
        foreach($db->query("SELECT d.*,COALESCE(CONCAT(g.marca,' ',g.modelo),c.nombre) nombre FROM detalle_solicitud_stock d LEFT JOIN gpu g ON g.id_gpu=d.id_gpu LEFT JOIN componente c ON c.id_componente=d.id_componente WHERE d.id_solicitud_stock IN ($ids) ORDER BY d.id_detalle_solicitud_stock") as $linea)$lineasPorPedido[$linea['id_solicitud_stock']][]=$linea;
    }
    if(!$pedidos)echo '<p class="text-secondary">Todavía no hay solicitudes de stock.</p>';
    foreach($pedidos as $pedido) {
        $id=(int)$pedido['id_solicitud_stock'];$lineas=$lineasPorPedido[$id]??[];
        $recibir=admin_puede_pedir_stock($db)&&(admin_acceso_todas_sucursales($db)||(int)$pedido['id_sucursal']===admin_sucursal_empleado($db));
        $despachar=admin_es_logistica_deposito($db)&&$pedido['id_empleado_validacion']!==null;
        $cerrado=$pedido['estado']==='Completada';
        echo '<article class="panel" id="solicitud-'.$id.'"><div class="d-flex flex-wrap justify-content-between gap-2"><h2 class="h5">Pedido #'.$id.' · '.admin_e($pedido['sucursal']).'</h2><span class="badge text-bg-secondary">'.admin_e($pedido['estado']).'</span></div>';
        echo '<p class="small text-secondary">Solicitó '.admin_e($pedido['nombre'].' '.$pedido['apellido']).' · '.admin_e($pedido['fecha']).'</p>';
        if($pedido['observaciones'])echo '<p>'.nl2br(admin_e($pedido['observaciones'])).'</p>';
        echo '<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Producto</th><th>Pedido</th><th>Enviado</th><th>Recibido</th><th>Faltantes</th><th>En camino</th>';
        if($verTodas)echo '<th>En depósito</th>';
        if($despachar&&!$cerrado)echo '<th>Enviar ahora</th>';
        if($recibir&&!$cerrado)echo '<th>Recibido ahora</th>';
        echo '</tr></thead><tbody>';
        foreach($lineas as $linea) {
            $idLinea=(int)$linea['id_detalle_solicitud_stock'];$clave=$linea['id_gpu']!==null?'gpu:'.$linea['id_gpu']:'componente:'.$linea['id_componente'];$enDeposito=$inventario[$clave]['cantidad']??0;
            $faltan=(int)$linea['cantidad_solicitada']-(int)$linea['cantidad_recibida'];$viajando=(int)$linea['cantidad_enviada']-(int)$linea['cantidad_recibida'];$enviar=(int)$linea['cantidad_solicitada']-(int)$linea['cantidad_enviada'];
            echo '<tr><th scope="row">'.admin_e($linea['nombre']).'</th><td>'.(int)$linea['cantidad_solicitada'].'</td><td>'.(int)$linea['cantidad_enviada'].'</td><td>'.(int)$linea['cantidad_recibida'].'</td><td>'.$faltan.'</td><td>'.$viajando.'</td>';
            if($verTodas)echo '<td>'.$enDeposito.'</td>';
            if($despachar&&!$cerrado)echo '<td><input class="form-control" style="min-width:90px" type="number" min="0" max="'.max(0,min($enviar,$enDeposito)).'" value="0" name="unidades['.$idLinea.']" form="despachar-'.$id.'" aria-label="Enviar '.admin_e($linea['nombre']).'" required></td>';
            if($recibir&&!$cerrado)echo '<td><input class="form-control" style="min-width:90px" type="number" min="0" max="'.$viajando.'" value="0" name="unidades['.$idLinea.']" form="recibir-'.$id.'" aria-label="Recibir '.admin_e($linea['nombre']).'" required></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div><div class="d-flex flex-wrap gap-2">';
        $acciones=[];
        if(!$cerrado&&admin_es_compras_deposito($db)&&$pedido['id_empleado_validacion']===null)$acciones['validar']='Validar pedido para despacho';
        if(!$cerrado&&$despachar)$acciones['despachar']='Despachar cantidades indicadas';
        if(!$cerrado&&$recibir)$acciones['recibir']='Confirmar ingreso a la sucursal';
        foreach($acciones as $accion=>$texto)echo '<form method="post" action="'.admin_e($destino).'" id="'.$accion.'-'.$id.'"><input type="hidden" name="admin_csrf" value="'.admin_e(admin_csrf()).'"><input type="hidden" name="accion" value="'.$accion.'"><input type="hidden" name="id_solicitud_stock" value="'.$id.'"><input type="hidden" name="version" value="'.(int)$pedido['version'].'"><button class="btn btn-primary">'.admin_e($texto).'</button></form>';
        echo '</div>';
        if($pedido['fecha_validacion'])echo '<p class="small text-secondary mt-3 mb-0">Validado: '.admin_e($pedido['fecha_validacion']).'</p>';
        if($pedido['fecha_despacho'])echo '<p class="small text-secondary mb-0">Último despacho: '.admin_e($pedido['fecha_despacho']).'</p>';
        if($pedido['fecha_recepcion'])echo '<p class="small text-secondary mb-0">Último ingreso a sucursal: '.admin_e($pedido['fecha_recepcion']).'</p>';
        echo '</article>';
    }
}

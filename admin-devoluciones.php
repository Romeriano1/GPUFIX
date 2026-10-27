<?php
require_once __DIR__.'/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__.'/conexion.php';
$mensaje='';$tipo='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
    admin_validar_csrf();
    $id=(int)($_POST['id_gpu_ventas']??0);
    try {
        if($id<1) throw new RuntimeException('Elegí una venta válida.');
        $conexion->begin_transaction();
        $stmt=$conexion->prepare("SELECT estado FROM gpu_ventas WHERE id_gpu_ventas=? FOR UPDATE");
        $stmt->bind_param('i',$id);$stmt->execute();$stmt->bind_result($estadoActual);$existe=$stmt->fetch();$stmt->close();
        if(!$existe) throw new RuntimeException('No encontramos esa venta.');
        if(stripos((string)$estadoActual, 'devuelto') !== false || stripos((string)$estadoActual, 'reembolsado') !== false) throw new RuntimeException('Esta venta ya figura como devuelta/reembolsada.');
        $stmt=$conexion->prepare("SELECT id_gpu,cantidad FROM detalle_venta WHERE id_gpu_ventas=?");
        $stmt->bind_param('i',$id);$stmt->execute();$stmt->bind_result($idGpu,$cantidad);$detalles=[];
        while($stmt->fetch()) $detalles[]=['id_gpu'=>(int)$idGpu,'cantidad'=>(int)$cantidad];$stmt->close();
        if (count($detalles) === 0) throw new RuntimeException('Esta venta no tiene productos asociados en detalle_venta; no se registró la devolución para evitar inconsistencias.');
        $estadoNuevo='Devuelto (reembolso a gestionar)';
        $stmt=$conexion->prepare("UPDATE gpu_ventas SET estado=? WHERE id_gpu_ventas=?");$stmt->bind_param('si',$estadoNuevo,$id);$stmt->execute();$stmt->close();
        foreach($detalles as $d){
            $stmt=$conexion->prepare("SELECT id_stock_gpu FROM stock_gpu WHERE id_gpu=? ORDER BY id_stock_gpu LIMIT 1");$stmt->bind_param('i',$d['id_gpu']);$stmt->execute();$stmt->bind_result($stockId);$hay=$stmt->fetch();$stmt->close();
            if($hay){$stmt=$conexion->prepare("UPDATE stock_gpu SET cantidad=cantidad+?, estado='Disponible' WHERE id_stock_gpu=?");$stmt->bind_param('ii',$d['cantidad'],$stockId);$stmt->execute();$stmt->close();}
            else {$ubicacion='A';$estadoStock='Disponible';$stmt=$conexion->prepare("INSERT INTO stock_gpu(id_gpu,estado,ubicacion,cantidad) VALUES(?,?,?,?)");$stmt->bind_param('issi',$d['id_gpu'],$estadoStock,$ubicacion,$d['cantidad']);$stmt->execute();$stmt->close();}
        }
        $conexion->commit();
        $mensaje='Devolución registrada. Si corresponde un reembolso monetario, todavía debe realizarse desde el medio de pago utilizado.';
    }catch(Throwable $e){
        try{$conexion->rollback();}catch(Throwable $ignored){}
        $mensaje=$e instanceof RuntimeException?$e->getMessage():'No se pudo registrar la devolución.';
        $tipo='danger';error_log('Admin devoluciones: '.$e->getMessage());
    }
}
$ventas=$conexion->query("SELECT v.id_gpu_ventas,v.fecha_venta,v.precio_total,v.estado,COALESCE(u.correo,'Cliente no disponible') correo FROM gpu_ventas v LEFT JOIN cliente c ON c.id_cliente=v.id_cliente LEFT JOIN usuarios u ON u.id_correo=c.id_correo ORDER BY v.fecha_venta DESC");
admin_layout_inicio('Devoluciones');
?>
<h1 class="h3 mt-3">Devoluciones</h1><p class="text-secondary">Permite dejar registrada una devolución y reintegrar las unidades al inventario a partir del detalle de la venta.</p>
<div class="alert alert-warning"><strong>Importante:</strong> esta acción no devuelve dinero automáticamente. El proyecto no contiene una integración de pagos para ejecutar reembolsos reales; el estado se registra para que el administrador gestione el reintegro por el medio de pago correspondiente.</div>
<?php if($mensaje): ?><div class="alert alert-<?=$tipo?>"><?=admin_e($mensaje)?></div><?php endif; ?>
<div class="panel"><h2 class="h5">Ventas existentes</h2><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Venta</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
<?php if($ventas->num_rows===0): ?><tr><td colspan="6" class="text-center text-secondary py-4">No hay ventas registradas para devolver. Primero debe existir una operación en gpu_ventas.</td></tr><?php else: while($v=$ventas->fetch_assoc()): $cerrada=(stripos((string)$v['estado'],'devuelto')!==false || stripos((string)$v['estado'],'reembolsado')!==false); ?><tr><td>#<?=(int)$v['id_gpu_ventas']?></td><td><?=admin_e($v['fecha_venta'])?></td><td><?=admin_e($v['correo'])?></td><td>$ <?=number_format((float)$v['precio_total'],2,',','.')?></td><td><?=admin_e($v['estado']?:'Sin estado')?></td><td><?php if($cerrada): ?><span class="badge text-bg-secondary">Ya procesada</span><?php else: ?><form method="post" onsubmit="return confirm('¿Registrar la devolución y reintegrar las unidades al stock?');"><input type="hidden" name="admin_csrf" value="<?=admin_csrf()?>"><input type="hidden" name="id_gpu_ventas" value="<?=(int)$v['id_gpu_ventas']?>"><button class="btn btn-sm btn-outline-danger">Registrar devolución</button></form><?php endif; ?></td></tr><?php endwhile; endif; ?>
</tbody></table></div></div>
<?php $conexion->close(); admin_layout_fin(); ?>

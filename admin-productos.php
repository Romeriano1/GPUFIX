<?php
require_once __DIR__.'/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__.'/conexion.php';
$mensaje=''; $tipo='success';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    admin_validar_csrf();
    $accion=(string)($_POST['accion']??'');
    try {
        if ($accion==='agregar') {
            $marca=trim((string)($_POST['marca']??'')); $modelo=trim((string)($_POST['modelo']??''));
            $precio=trim((string)($_POST['precio']??'')); $especificaciones=trim((string)($_POST['especificaciones']??''));
            $imagen=trim((string)($_POST['imagen']??'')); $cantidad=filter_var($_POST['cantidad']??0,FILTER_VALIDATE_INT);
            if ($marca==='' || $modelo==='' || $precio==='' || !is_numeric($precio) || (float)$precio<0 || $cantidad===false || $cantidad<0) throw new RuntimeException('Completá marca, modelo, precio y una cantidad válida.');
            $precioFloat=(float)$precio;
            $stmt=$conexion->prepare("INSERT INTO gpu (marca,modelo,precio_especial,especificaciones,imagen) VALUES (?,?,?,?,?)");
            $stmt->bind_param('ssdss',$marca,$modelo,$precioFloat,$especificaciones,$imagen); $stmt->execute(); $id=(int)$conexion->insert_id; $stmt->close();
            $estado='Disponible'; $ubicacion='A';
            $stmt=$conexion->prepare("INSERT INTO stock_gpu (id_gpu,estado,ubicacion,cantidad) VALUES (?,?,?,?)");
            $stmt->bind_param('issi',$id,$estado,$ubicacion,$cantidad); $stmt->execute(); $stmt->close();
            $mensaje='Producto agregado correctamente.';
        } elseif ($accion==='editar') {
            $id=(int)($_POST['id_gpu']??0); $precio=trim((string)($_POST['precio']??'')); $cantidad=filter_var($_POST['cantidad']??null,FILTER_VALIDATE_INT);
            if($id<1 || $precio==='' || !is_numeric($precio) || (float)$precio<0 || $cantidad===false || $cantidad<0) throw new RuntimeException('Revisá el precio y el stock ingresados.');
            $precioFloat=(float)$precio;
            $conexion->begin_transaction();
            $stmt=$conexion->prepare("UPDATE gpu SET precio_especial=? WHERE id_gpu=?"); $stmt->bind_param('di',$precioFloat,$id); $stmt->execute(); $stmt->close();
            $stmt=$conexion->prepare("SELECT id_stock_gpu FROM stock_gpu WHERE id_gpu=? ORDER BY id_stock_gpu LIMIT 1"); $stmt->bind_param('i',$id); $stmt->execute(); $stmt->bind_result($stockId); $hayStock=$stmt->fetch(); $stmt->close();
            if($hayStock){ $stmt=$conexion->prepare("UPDATE stock_gpu SET cantidad=?,estado=? WHERE id_stock_gpu=?"); $estado=$cantidad>0?'Disponible':'Sin stock'; $stmt->bind_param('isi',$cantidad,$estado,$stockId); $stmt->execute(); $stmt->close(); $stmt=$conexion->prepare("UPDATE stock_gpu SET cantidad=0 WHERE id_gpu=? AND id_stock_gpu<>?"); $stmt->bind_param('ii',$id,$stockId); $stmt->execute(); $stmt->close(); }
            else { $estado=$cantidad>0?'Disponible':'Sin stock'; $ubicacion='A'; $stmt=$conexion->prepare("INSERT INTO stock_gpu(id_gpu,estado,ubicacion,cantidad) VALUES(?,?,?,?)"); $stmt->bind_param('issi',$id,$estado,$ubicacion,$cantidad); $stmt->execute(); $stmt->close(); }
            $conexion->commit(); $mensaje='Precio y stock actualizados.';
        }
    } catch(Throwable $e) {
        try{$conexion->rollback();}catch(Throwable $ignored){}
        $mensaje=$e instanceof RuntimeException?$e->getMessage():'No se pudo guardar el cambio. Revisá los datos y la conexión.';
        $tipo='danger'; error_log('Admin productos: '.$e->getMessage());
    }
}
$res=$conexion->query("SELECT g.id_gpu,g.marca,g.modelo,g.precio_especial,g.imagen,COALESCE(SUM(s.cantidad),0) cantidad FROM gpu g LEFT JOIN stock_gpu s ON s.id_gpu=g.id_gpu GROUP BY g.id_gpu,g.marca,g.modelo,g.precio_especial,g.imagen ORDER BY g.id_gpu DESC");
admin_layout_inicio('Productos y stock');
?>
<h1 class="h3 mt-3">Productos y stock</h1><p class="text-secondary">Podés agregar una GPU nueva o cambiar el precio y la cantidad disponible de un producto existente.</p>
<?php if($mensaje): ?><div class="alert alert-<?=$tipo?>"><?=admin_e($mensaje)?></div><?php endif; ?>
<div class="panel"><h2 class="h5 mb-3">Agregar producto</h2><form method="post" class="row g-3"><input type="hidden" name="admin_csrf" value="<?=admin_csrf()?>"><input type="hidden" name="accion" value="agregar">
<div class="col-md-4"><label class="form-label">Marca *</label><input class="form-control" name="marca" required maxlength="100"></div><div class="col-md-4"><label class="form-label">Modelo *</label><input class="form-control" name="modelo" required maxlength="150"></div><div class="col-md-4"><label class="form-label">Precio *</label><input class="form-control" name="precio" type="number" min="0" step="0.01" required></div>
<div class="col-md-6"><label class="form-label">Imagen (ruta o URL)</label><input class="form-control" name="imagen" maxlength="500" placeholder="imagenes/producto.jpg"></div><div class="col-md-3"><label class="form-label">Stock inicial *</label><input class="form-control" name="cantidad" type="number" min="0" step="1" value="0" required></div><div class="col-md-3"><label class="form-label">Especificaciones</label><input class="form-control" name="especificaciones"></div><div class="col-12"><button class="btn btn-primary">Agregar producto</button></div></form></div>
<div class="panel"><h2 class="h5 mb-3">Catálogo actual</h2><div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Guardar cambios</th></tr></thead><tbody>
<?php while($p=$res->fetch_assoc()): ?><tr><td><?= (int)$p['id_gpu']?></td><td><strong><?=admin_e($p['marca'].' '.$p['modelo'])?></strong></td><td colspan="3"><form method="post" class="row g-2 align-items-center"><input type="hidden" name="admin_csrf" value="<?=admin_csrf()?>"><input type="hidden" name="accion" value="editar"><input type="hidden" name="id_gpu" value="<?=(int)$p['id_gpu']?>"><div class="col-md-4"><label class="visually-hidden">Precio</label><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="precio" value="<?=admin_e($p['precio_especial'])?>" required></div><div class="col-md-3"><label class="visually-hidden">Stock</label><input class="form-control form-control-sm" type="number" min="0" step="1" name="cantidad" value="<?=(int)$p['cantidad']?>" required></div><div class="col-md-3"><button class="btn btn-sm btn-outline-primary">Guardar</button></div></form></td></tr><?php endwhile; ?>
</tbody></table></div><p class="small-muted mb-0">Para no alterar el catálogo existente, esta pantalla no elimina productos. El campo de imagen admite la ruta o URL que usa tu sitio.</p></div>
<?php $conexion->close(); admin_layout_fin(); ?>

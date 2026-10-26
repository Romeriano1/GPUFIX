
<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/conexion.php';

admin_validar_csrf();

$mensaje = '';
$error = '';

$estados = [
    'Pendiente de presupuesto',
    'Pendiente',
    'En diagnóstico',
    'Presupuestada',
    'En reparación',
    'Finalizada',
    'Entregada',
    'Cancelada'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idOrden = filter_input(INPUT_POST, 'id_orden_reparacion', FILTER_VALIDATE_INT);
    $estado = trim((string)($_POST['estado'] ?? ''));

    if (!$idOrden || !in_array($estado, $estados, true)) {
        $error = 'Los datos enviados no son válidos.';
    } else {
        $stmt = $conexion->prepare(
            'UPDATE orden_reparacion
             SET estado = ?
             WHERE id_orden_reparacion = ?'
        );

        if ($stmt) {
            $stmt->bind_param('si', $estado, $idOrden);

            if ($stmt->execute()) {
                $mensaje = 'El estado de la orden se actualizó correctamente.';
            } else {
                $error = 'No se pudo actualizar el estado.';
            }

            $stmt->close();
        } else {
            $error = 'No se pudo preparar la actualización.';
        }
    }
}

$sql = "
    SELECT
        o.id_orden_reparacion,
        o.fecha_ingreso,
        o.motivo,
        o.estado,
        o.fecha_egreso,
        g.marca,
        g.modelo,
        g.numero_serie,
        u.nombre AS nombre_cliente,
        u.apellido AS apellido_cliente
    FROM orden_reparacion o
    INNER JOIN gpu_cliente g
        ON o.id_gpu_cliente = g.id_gpu_cliente
    INNER JOIN cliente c
        ON g.id_cliente = c.id_cliente
    INNER JOIN usuarios u
        ON c.id_correo = u.id_correo
    ORDER BY o.fecha_ingreso DESC
";

$resultado = $conexion->query($sql);

admin_layout_inicio('Reparaciones');
?>

<h1 class="h3 mt-3">Órdenes de reparación</h1>
<p class="text-secondary">
    Consultá las placas ingresadas y actualizá el estado de cada reparación.
</p>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success">
        <?= admin_e($mensaje) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger">
        <?= admin_e($error) ?>
    </div>
<?php endif; ?>

<div class="panel">
    <?php if (!$resultado): ?>
        <div class="alert alert-danger mb-0">
            No se pudieron cargar las órdenes. Revisá la estructura de la base de datos.
        </div>

    <?php elseif ($resultado->num_rows === 0): ?>
        <p class="mb-0">Todavía no hay órdenes de reparación registradas.</p>

    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>N.º orden</th>
                        <th>Cliente</th>
                        <th>Placa</th>
                        <th>Ingreso</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Actualizar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($orden = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td>
                                #<?= (int)$orden['id_orden_reparacion'] ?>
                            </td>

                            <td>
                                <?= admin_e(
                                    $orden['nombre_cliente'] . ' ' .
                                    $orden['apellido_cliente']
                                ) ?>
                            </td>

                            <td>
                                <?= admin_e($orden['marca'] . ' ' . $orden['modelo']) ?>
                                <div class="small-muted">
                                    Serie: <?= admin_e($orden['numero_serie']) ?>
                                </div>
                            </td>

                            <td>
                                <?= admin_e($orden['fecha_ingreso']) ?>
                            </td>

                            <td>
                                <?= admin_e($orden['motivo'] ?? '') ?>
                            </td>

                            <td>
                                <?= admin_e($orden['estado'] ?: 'Sin estado') ?>
                            </td>

                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input
                                        type="hidden"
                                        name="admin_csrf"
                                        value="<?= admin_e(admin_csrf()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id_orden_reparacion"
                                        value="<?= (int)$orden['id_orden_reparacion'] ?>"
                                    >

                                    <select
                                        name="estado"
                                        class="form-select form-select-sm"
                                        required
                                    >
                                        <?php foreach ($estados as $opcion): ?>
                                            <option
                                                value="<?= admin_e($opcion) ?>"
                                                <?= $orden['estado'] === $opcion ? 'selected' : '' ?>
                                            >
                                                <?= admin_e($opcion) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-sm"
                                    >
                                        Guardar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
if ($resultado instanceof mysqli_result) {
    $resultado->free();
}

$conexion->close();
admin_layout_fin();
?>

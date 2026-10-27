

<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/correo-config.php';

admin_validar_csrf();

$mensaje = '';
$error = '';

$estadosPresupuesto = [
    'Pendiente',
    'Aprobado',
    'Rechazado'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string)($_POST['accion'] ?? '');

    // Crear presupuesto
    if ($accion === 'crear') {
        $idOrden = filter_input(
            INPUT_POST,
            'id_orden_reparacion',
            FILTER_VALIDATE_INT
        );

        $manoObraTexto = trim((string)($_POST['mano_obra'] ?? ''));
        $manoObraTexto = str_replace(',', '.', $manoObraTexto);
        $estado = trim((string)($_POST['estado'] ?? ''));

        if (
            !$idOrden ||
            $manoObraTexto === '' ||
            !is_numeric($manoObraTexto) ||
            (float)$manoObraTexto <= 0 ||
            !in_array($estado, $estadosPresupuesto, true)
        ) {
            $error = 'Completá los datos correctamente. La mano de obra debe ser mayor a cero.';
        } else {
            $manoObra = (float)$manoObraTexto;

            $stmt = $conexion->prepare(
                'SELECT id_orden_reparacion
                 FROM orden_reparacion
                 WHERE id_orden_reparacion = ?'
            );

            if ($stmt) {
                $stmt->bind_param('i', $idOrden);
                $stmt->execute();
                $ordenExiste = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if (!$ordenExiste) {
                    $error = 'La orden seleccionada no existe.';
                } else {
                    $stmt = $conexion->prepare(
                        'SELECT id_presupuesto
                         FROM presupuesto
                         WHERE id_orden_reparacion = ?'
                    );

                    if ($stmt) {
                        $stmt->bind_param('i', $idOrden);
                        $stmt->execute();
                        $yaTienePresupuesto =
                            $stmt->get_result()->num_rows > 0;
                        $stmt->close();

                        if ($yaTienePresupuesto) {
                            $error = 'Esta orden ya tiene un presupuesto registrado.';
                        } else {
                            $stmt = $conexion->prepare(
                                'INSERT INTO presupuesto
                                    (id_orden_reparacion, fecha_emision,
                                     mano_obra, total, estado)
                                 VALUES (?, NOW(), ?, ?, ?)'
                            );

                            if ($stmt) {
                                $total = $manoObra;
                                $stmt->bind_param(
                                    'idds',
                                    $idOrden,
                                    $manoObra,
                                    $total,
                                    $estado
                                );

                                if ($stmt->execute()) {
                                    $stmt->close();

                                    $stmt = $conexion->prepare(
                                        "UPDATE orden_reparacion
                                         SET estado = 'Presupuestada'
                                         WHERE id_orden_reparacion = ?"
                                    );

                                    if ($stmt) {
                                        $stmt->bind_param('i', $idOrden);
                                        $stmt->execute();
                                        $stmt->close();
                                    }

                                    $mensaje =
                                        'El presupuesto se registró correctamente.';
                                } else {
                                    $error =
                                        'No se pudo registrar el presupuesto.';
                                    $stmt->close();
                                }
                            } else {
                                $error =
                                    'No se pudo preparar el registro del presupuesto.';
                            }
                        }
                    } else {
                        $error =
                            'No se pudo verificar si la orden ya tiene presupuesto.';
                    }
                }
            } else {
                $error = 'No se pudo verificar la orden seleccionada.';
            }
        }
    }

    // Actualizar estado del presupuesto
    elseif ($accion === 'actualizar_estado') {
        $idPresupuesto = filter_input(
            INPUT_POST,
            'id_presupuesto',
            FILTER_VALIDATE_INT
        );

        $estado = trim((string)($_POST['estado'] ?? ''));

        if (
            !$idPresupuesto ||
            !in_array($estado, $estadosPresupuesto, true)
        ) {
            $error = 'Los datos enviados no son válidos.';
        } else {
            $stmt = $conexion->prepare(
                'UPDATE presupuesto
                 SET estado = ?
                 WHERE id_presupuesto = ?'
            );

            if ($stmt) {
                $stmt->bind_param('si', $estado, $idPresupuesto);

                if ($stmt->execute()) {
                    $mensaje =
                        'El estado del presupuesto se actualizó correctamente.';
                } else {
                    $error =
                        'No se pudo actualizar el estado del presupuesto.';
                }

                $stmt->close();
            } else {
                $error =
                    'No se pudo preparar la actualización.';
            }
        }
    }

    // Enviar presupuesto por correo electrónico
    elseif ($accion === 'enviar') {
        $idPresupuesto = filter_input(
            INPUT_POST,
            'id_presupuesto',
            FILTER_VALIDATE_INT
        );

        if (!$idPresupuesto) {
            $error = 'El presupuesto seleccionado no es válido.';
        } else {
            $stmt = $conexion->prepare(
                "SELECT
                    p.id_presupuesto,
                    p.id_orden_reparacion,
                    p.fecha_emision,
                    p.mano_obra,
                    p.total,
                    p.estado,
                    g.marca,
                    g.modelo,
                    g.numero_serie,
                    u.nombre AS nombre_cliente,
                    u.apellido AS apellido_cliente,
                    u.correo AS correo_cliente
                 FROM presupuesto p
                 INNER JOIN orden_reparacion o
                    ON p.id_orden_reparacion = o.id_orden_reparacion
                 INNER JOIN gpu_cliente g
                    ON o.id_gpu_cliente = g.id_gpu_cliente
                 INNER JOIN cliente c
                    ON g.id_cliente = c.id_cliente
                 INNER JOIN usuarios u
                    ON c.id_correo = u.id_correo
                 WHERE p.id_presupuesto = ?"
            );

            if ($stmt) {
                $stmt->bind_param('i', $idPresupuesto);
                $stmt->execute();
                $resultadoEnvio = $stmt->get_result();
                $presupuestoEnvio = $resultadoEnvio->fetch_assoc();
                $stmt->close();

                if (!$presupuestoEnvio) {
                    $error = 'No se encontró el presupuesto.';
                } else {
                    $destinatario = trim(
                        (string)$presupuestoEnvio['correo_cliente']
                    );

                    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                        $error =
                            'El cliente no tiene un correo válido registrado.';
                    } else {
                        $nombreCliente =
                            $presupuestoEnvio['nombre_cliente'] . ' ' .
                            $presupuestoEnvio['apellido_cliente'];

                        $numeroPresupuesto =
                            (int)$presupuestoEnvio['id_presupuesto'];

                        $numeroOrden =
                            (int)$presupuestoEnvio['id_orden_reparacion'];

                        $placa =
                            $presupuestoEnvio['marca'] . ' ' .
                            $presupuestoEnvio['modelo'];

                        $manoObra = number_format(
                            (float)$presupuestoEnvio['mano_obra'],
                            2,
                            ',',
                            '.'
                        );

                        $total = number_format(
                            (float)$presupuestoEnvio['total'],
                            2,
                            ',',
                            '.'
                        );

                        $fecha = $presupuestoEnvio['fecha_emision'];
                        $estadoPresupuesto = $presupuestoEnvio['estado'];

                        $asunto =
                            'Presupuesto de reparación GPUFIX #' .
                            $numeroPresupuesto;

                        $cuerpo =
                            "Hola {$nombreCliente},\n\n" .
                            "Te enviamos los datos del presupuesto de reparación de tu placa gráfica.\n\n" .
                            "Número de presupuesto: #{$numeroPresupuesto}\n" .
                            "Orden de reparación: #{$numeroOrden}\n" .
                            "Placa gráfica: {$placa}\n" .
                            "Número de serie: {$presupuestoEnvio['numero_serie']}\n" .
                            "Fecha de emisión: {$fecha}\n" .
                            "Mano de obra: \${$manoObra}\n" .
                            "Total: \${$total}\n" .
                            "Estado del presupuesto: {$estadoPresupuesto}\n\n" .
                            "Para consultar o confirmar el presupuesto, comunicate con nuestro equipo.\n\n" .
                            "Saludos,\n" .
                            "Equipo GPUFIX";

                        if (
                            gpufix_enviar_correo(
                                $destinatario,
                                $asunto,
                                $cuerpo
                            )
                        ) {
                            $mensaje =
                                'El presupuesto #' . $numeroPresupuesto .
                                ' se envió correctamente a ' . $destinatario . '.';
                        } else {
                            $error =
                                'No se pudo enviar el correo. Revisá la configuración de Gmail y los registros de errores de PHP.';
                        }
                    }
                }
            } else {
                $error =
                    'No se pudo preparar la información del presupuesto.';
            }
        }
    }
}

// Órdenes que todavía no tienen presupuesto
$sqlOrdenes = "
    SELECT
        o.id_orden_reparacion,
        o.fecha_ingreso,
        o.estado,
        g.marca,
        g.modelo,
        g.numero_serie,
        u.nombre AS nombre_cliente,
        u.apellido AS apellido_cliente,
        u.correo AS correo_cliente
    FROM orden_reparacion o
    INNER JOIN gpu_cliente g
        ON o.id_gpu_cliente = g.id_gpu_cliente
    INNER JOIN cliente c
        ON g.id_cliente = c.id_cliente
    INNER JOIN usuarios u
        ON c.id_correo = u.id_correo
    LEFT JOIN presupuesto p
        ON o.id_orden_reparacion = p.id_orden_reparacion
    WHERE p.id_presupuesto IS NULL
    ORDER BY o.fecha_ingreso DESC
";

$ordenes = $conexion->query($sqlOrdenes);

// Presupuestos registrados
$sqlPresupuestos = "
    SELECT
        p.id_presupuesto,
        p.id_orden_reparacion,
        p.fecha_emision,
        p.mano_obra,
        p.total,
        p.estado,
        g.marca,
        g.modelo,
        g.numero_serie,
        u.nombre AS nombre_cliente,
        u.apellido AS apellido_cliente,
        u.correo AS correo_cliente
    FROM presupuesto p
    INNER JOIN orden_reparacion o
        ON p.id_orden_reparacion = o.id_orden_reparacion
    INNER JOIN gpu_cliente g
        ON o.id_gpu_cliente = g.id_gpu_cliente
    INNER JOIN cliente c
        ON g.id_cliente = c.id_cliente
    INNER JOIN usuarios u
        ON c.id_correo = u.id_correo
    ORDER BY p.fecha_emision DESC
";

$presupuestos = $conexion->query($sqlPresupuestos);

admin_layout_inicio('Presupuestos');
?>

<h1 class="h3 mt-3">Presupuestos</h1>

<p class="text-secondary">
    Registrá presupuestos, administrá sus estados y envialos por correo electrónico.
    El importe corresponde únicamente a la mano de obra.
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
    <h2 class="h5 mb-3">Crear presupuesto</h2>

    <?php if (!$ordenes): ?>
        <div class="alert alert-danger mb-0">
            No se pudieron cargar las órdenes disponibles.
        </div>

    <?php elseif ($ordenes->num_rows === 0): ?>
        <p class="mb-0">
            No hay órdenes pendientes de presupuesto.
        </p>

    <?php else: ?>
        <form method="post">
            <input
                type="hidden"
                name="admin_csrf"
                value="<?= admin_e(admin_csrf()) ?>"
            >
            <input type="hidden" name="accion" value="crear">

            <div class="mb-3">
                <label for="id_orden_reparacion" class="form-label">
                    Orden de reparación
                </label>

                <select
                    name="id_orden_reparacion"
                    id="id_orden_reparacion"
                    class="form-select"
                    required
                >
                    <option value="">Seleccioná una orden</option>

                    <?php while ($orden = $ordenes->fetch_assoc()): ?>
                        <option value="<?= (int)$orden['id_orden_reparacion'] ?>">
                            <?= '#' . (int)$orden['id_orden_reparacion'] ?>
                            -
                            <?= admin_e(
                                $orden['nombre_cliente'] . ' ' .
                                $orden['apellido_cliente']
                            ) ?>
                            -
                            <?= admin_e($orden['marca'] . ' ' . $orden['modelo']) ?>
                            (Serie: <?= admin_e($orden['numero_serie']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="mano_obra" class="form-label">
                    Mano de obra ($)
                </label>
                <input
                    type="number"
                    name="mano_obra"
                    id="mano_obra"
                    class="form-control"
                    min="0.01"
                    step="0.01"
                    placeholder="Ej.: 25000"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="estado" class="form-label">
                    Estado inicial
                </label>
                <select
                    name="estado"
                    id="estado"
                    class="form-select"
                    required
                >
                    <?php foreach ($estadosPresupuesto as $estado): ?>
                        <option value="<?= admin_e($estado) ?>">
                            <?= admin_e($estado) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                Registrar presupuesto
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="panel">
    <h2 class="h5 mb-3">Presupuestos registrados</h2>

    <?php if (!$presupuestos): ?>
        <div class="alert alert-danger mb-0">
            No se pudieron cargar los presupuestos.
        </div>

    <?php elseif ($presupuestos->num_rows === 0): ?>
        <p class="mb-0">
            Todavía no hay presupuestos registrados.
        </p>

    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>N.º presupuesto</th>
                        <th>Orden</th>
                        <th>Cliente</th>
                        <th>Placa</th>
                        <th>Fecha</th>
                        <th>Mano de obra</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Actualizar</th>
                        <th>Enviar</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($p = $presupuestos->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int)$p['id_presupuesto'] ?></td>

                            <td>#<?= (int)$p['id_orden_reparacion'] ?></td>

                            <td>
                                <?= admin_e(
                                    $p['nombre_cliente'] . ' ' .
                                    $p['apellido_cliente']
                                ) ?>
                                <div class="small-muted">
                                    <?= admin_e($p['correo_cliente']) ?>
                                </div>
                            </td>

                            <td>
                                <?= admin_e($p['marca'] . ' ' . $p['modelo']) ?>
                                <div class="small-muted">
                                    Serie: <?= admin_e($p['numero_serie']) ?>
                                </div>
                            </td>

                            <td><?= admin_e($p['fecha_emision']) ?></td>

                            <td>
                                $<?= number_format(
                                    (float)$p['mano_obra'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td>
                                $<?= number_format(
                                    (float)$p['total'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td><?= admin_e($p['estado'] ?: 'Sin estado') ?></td>

                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input
                                        type="hidden"
                                        name="admin_csrf"
                                        value="<?= admin_e(admin_csrf()) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="actualizar_estado"
                                    >
                                    <input
                                        type="hidden"
                                        name="id_presupuesto"
                                        value="<?= (int)$p['id_presupuesto'] ?>"
                                    >

                                    <select
                                        name="estado"
                                        class="form-select form-select-sm"
                                        required
                                    >
                                        <?php foreach ($estadosPresupuesto as $opcion): ?>
                                            <option
                                                value="<?= admin_e($opcion) ?>"
                                                <?= $p['estado'] === $opcion ? 'selected' : '' ?>
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

                            <td>
                                <form
                                    method="post"
                                    onsubmit="return confirm('¿Querés enviar este presupuesto por correo electrónico al cliente?');"
                                >
                                    <input
                                        type="hidden"
                                        name="admin_csrf"
                                        value="<?= admin_e(admin_csrf()) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="enviar"
                                    >
                                    <input
                                        type="hidden"
                                        name="id_presupuesto"
                                        value="<?= (int)$p['id_presupuesto'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-success btn-sm"
                                    >
                                        Enviar presupuesto
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
if ($ordenes instanceof mysqli_result) {
    $ordenes->free();
}

if ($presupuestos instanceof mysqli_result) {
    $presupuestos->free();
}

$conexion->close();
admin_layout_fin();
?>

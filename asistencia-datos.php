<?php
function asistencia_leer(string $directorio): array
{
    $archivo = $directorio . '/registros.php';
    if (!is_file($archivo)) return [];
    $contenido = file_get_contents($archivo);
    if ($contenido === false) throw new RuntimeException('No se pudo leer el registro de asistencia.');
    $contenido = str_replace("\r\n", "\n", $contenido);
    $prefijo = "<?php exit; ?>\n";
    if (!str_starts_with($contenido, $prefijo)) throw new RuntimeException('El archivo de asistencia no tiene el formato esperado.');
    $registros = json_decode(substr($contenido, strlen($prefijo)), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($registros)) throw new RuntimeException('El registro de asistencia no es válido.');
    return $registros;
}

function asistencia_version(?array $registro): string
{
    return hash('sha256', serialize($registro));
}

function asistencia_guardar(string $directorio, string $clave, ?array $registro, string $version): void
{
    $candado = fopen($directorio . '/registros.lock', 'c');
    if (!$candado) throw new RuntimeException('No se pudo abrir el archivo de asistencia para guardar.');
    try {
        if (!flock($candado, LOCK_EX)) throw new RuntimeException('No se pudo bloquear el registro de asistencia.');
        $registros = asistencia_leer($directorio);
        if (!hash_equals(asistencia_version($registros[$clave] ?? null), $version)) {
            throw new RuntimeException('Esta asistencia cambió desde que abriste el formulario. Recargá antes de guardar.');
        }
        if ($registro === null) unset($registros[$clave]);
        else $registros[$clave] = $registro;
        $contenido = "<?php exit; ?>\n" . json_encode((object) $registros, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $temporal = tempnam($directorio, '.asistencia-');
        if ($temporal === false) throw new RuntimeException('No se pudo preparar el registro de asistencia.');
        try {
            if (file_put_contents($temporal, $contenido) !== strlen($contenido)
                || !rename($temporal, $directorio . '/registros.php')) {
                throw new RuntimeException('No se pudo guardar la asistencia. Revisá los permisos de escritura de la carpeta.');
            }
        } finally {
            if (is_file($temporal)) unlink($temporal);
        }
    } finally {
        flock($candado, LOCK_UN);
        fclose($candado);
    }
}

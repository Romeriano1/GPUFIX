<?php
// Herramienta local de Git: nunca se ejecuta desde la pagina web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function ejecutarSincronizacion(array $comando, ?string $entrada = null, ?array $entorno = null): string
{
    $salidaTemporal = tmpfile();
    $errorTemporal = tmpfile();
    if ($salidaTemporal === false || $errorTemporal === false) {
        throw new RuntimeException('No se pudieron preparar los archivos temporales.');
    }
    $descriptores = [
        0 => $entrada === null ? ['pipe', 'r'] : ['file', $entrada, 'r'],
        1 => $salidaTemporal,
        2 => $errorTemporal,
    ];
    $proceso = proc_open($comando, $descriptores, $tuberias, null, $entorno, ['bypass_shell' => true, 'create_no_window' => true]);
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo iniciar ' . basename($comando[0]) . '.');
    }
    if ($entrada === null) fclose($tuberias[0]);
    $resultado = proc_close($proceso);
    rewind($salidaTemporal);
    $salida = stream_get_contents($salidaTemporal);
    fclose($salidaTemporal);
    fclose($errorTemporal);
    if ($resultado !== 0) {
        // No mostrar consultas o credenciales contenidas en errores del cliente SQL.
        throw new RuntimeException(basename($comando[0]) . ' fallo. Revisa que MySQL este iniciado y las variables GPUFIX_DB_* sean correctas.');
    }
    return trim($salida);
}

try {
    $raiz = dirname(__DIR__);
    chdir($raiz);
    $modo = $argv[1] ?? '';
    if (!in_array($modo, ['', '--instalar', '--comprobar'], true)) {
        throw new RuntimeException('Uso: php database/sincronizar.php [--instalar|--comprobar]');
    }
    if (trim(ejecutarSincronizacion(['git', 'rev-parse', '--show-toplevel'])) !== str_replace('\\', '/', $raiz)) {
        throw new RuntimeException('Ejecuta esta herramienta desde el repositorio GPUFIX.');
    }
    if ($modo === '--instalar') {
        $configuracionGit = ejecutarSincronizacion(['git', 'config', '--list']);
        preg_match_all('/^core\.hookspath=(.+)$/mi', $configuracionGit, $coincidencias);
        $hooksConfigurados = $coincidencias[1] ? trim(end($coincidencias[1])) : '';
        if ($hooksConfigurados !== '' && $hooksConfigurados !== '.githooks') {
            throw new RuntimeException('Ya existe otra configuracion de hooks. No se reemplazo.');
        }
        foreach ($hooksConfigurados === '.githooks' ? [] : ['post-merge', 'post-rewrite'] as $hook) {
            $hookAnterior = ejecutarSincronizacion(['git', 'rev-parse', '--git-path', 'hooks/' . $hook]);
            if (is_file($hookAnterior)) {
                throw new RuntimeException('Ya existe el hook ' . $hook . '. No se reemplazo.');
            }
        }
    }

    $leerEntorno = static function (string $clave, string $predeterminado): string {
        $valor = getenv($clave);
        return $valor !== false && $valor !== '' ? $valor : $predeterminado;
    };
    $servidor = $leerEntorno('GPUFIX_DB_HOST', '127.0.0.1');
    $usuario = $leerEntorno('GPUFIX_DB_USER', 'root');
    $contrasena = $leerEntorno('GPUFIX_DB_PASSWORD', '');
    $baseDatos = $leerEntorno('GPUFIX_DB_NAME', 'if0_43068954_gpufix');
    $puerto = $leerEntorno('GPUFIX_DB_PORT', '3306');
    if (!in_array($servidor, ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('La sincronizacion solo permite MySQL local. No se modifico el hosting.');
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $baseDatos) || !ctype_digit($puerto) || (int) $puerto < 1 || (int) $puerto > 65535) {
        throw new RuntimeException('El nombre de base o el puerto local no es valido.');
    }
    $carpetaMysql = dirname(dirname(PHP_BINARY)) . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin';
    $sufijo = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
    $mysql = is_file($carpetaMysql . '/mysql' . $sufijo) ? $carpetaMysql . '/mysql' . $sufijo : 'mysql';
    $mysqldump = is_file($carpetaMysql . '/mysqldump' . $sufijo) ? $carpetaMysql . '/mysqldump' . $sufijo : 'mysqldump';
    $sql = __DIR__ . '/gpufix.sql';
    $contenidoSql = file_get_contents($sql);
    if ($contenidoSql === false || !str_contains($contenidoSql, '-- Dump completed')
        || !preg_match('/^CREATE TABLE `usuarios`/m', $contenidoSql)
        || preg_match('/^\s*(?:USE\s|CREATE\s+DATABASE\s)/mi', $contenidoSql)) {
        throw new RuntimeException('gpufix.sql no es una exportacion completa compatible. No se modifico la base.');
    }
    // La clave solo se entrega al proceso hijo, nunca como argumento ni dentro del repositorio.
    $entorno = getenv();
    $entorno['MYSQL_PWD'] = $contrasena;
    $opciones = ['--host=' . $servidor, '--port=' . $puerto, '--user=' . $usuario, '--default-character-set=utf8mb4'];
    $consulta = static fn (string $sql) => ejecutarSincronizacion(array_merge([$mysql], $opciones, ['--batch', '--skip-column-names', '--execute=' . $sql]), null, $entorno);
    $existe = $consulta("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '" . $baseDatos . "'") === '1';
    if ($modo === '--comprobar') {
        echo 'GPUFIX: SQL completo y conexion MySQL local correcta. Base ' . $baseDatos . ($existe ? ' existente.' : ' por crear.') . PHP_EOL;
        exit(0);
    }

    $carpetaPrivada = $leerEntorno('LOCALAPPDATA', sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'GPUFIX' . DIRECTORY_SEPARATOR
        . 'respaldos-db' . DIRECTORY_SEPARATOR . substr(hash('sha256', $raiz), 0, 12);
    if (!is_dir($carpetaPrivada) && !mkdir($carpetaPrivada, 0700, true) && !is_dir($carpetaPrivada)) {
        throw new RuntimeException('No se pudo crear la carpeta privada de respaldos.');
    }
    $candado = fopen($carpetaPrivada . '/sincronizacion.lock', 'c');
    if ($candado === false || !flock($candado, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Ya hay otra sincronizacion en curso.');
    }
    $respaldo = null;
    if ($existe) {
        $respaldo = $carpetaPrivada . '/' . $baseDatos . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
        ejecutarSincronizacion(array_merge([$mysqldump], $opciones, ['--single-transaction', '--skip-lock-tables', '--hex-blob', '--result-file=' . $respaldo, $baseDatos]), null, $entorno);
        if (!is_file($respaldo) || filesize($respaldo) === 0) {
            throw new RuntimeException('El respaldo esta vacio. No se modifico la base.');
        }
        echo 'GPUFIX: respaldo anterior guardado en ' . $respaldo . PHP_EOL;
    } else {
        $consulta('CREATE DATABASE `' . $baseDatos . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }
    try {
        ejecutarSincronizacion(array_merge([$mysql], $opciones, [$baseDatos]), $sql, $entorno);
    } catch (Throwable $errorImportacion) {
        if ($respaldo !== null) {
            try {
                ejecutarSincronizacion(array_merge([$mysql], $opciones, [$baseDatos]), $respaldo, $entorno);
            } catch (RuntimeException $errorRestauracion) {
                throw new RuntimeException('La importacion y la restauracion fallaron. Respaldo: ' . $respaldo);
            }
            throw new RuntimeException('La importacion fallo; se restauro el respaldo anterior. Respaldo: ' . $respaldo);
        }
        throw $errorImportacion;
    }
    if ($modo === '--instalar') {
        ejecutarSincronizacion(['git', 'config', '--local', 'core.hooksPath', '.githooks']);
        echo 'GPUFIX: sincronizacion activada en esta PC.' . PHP_EOL;
    }
    echo 'GPUFIX: base local ' . $baseDatos . ' actualizada desde database/gpufix.sql.' . PHP_EOL;
    echo 'Los pulls sin commits nuevos no ejecutan hooks; para reimportar usa php database/sincronizar.php.' . PHP_EOL;
    flock($candado, LOCK_UN);
    fclose($candado);
} catch (Throwable $error) {
    fwrite(STDERR, 'GPUFIX: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

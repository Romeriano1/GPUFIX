<?php
/**
 * Envío SMTP reutilizable. Configuración por variables de entorno:
 * GPUFIX_SMTP_HOST, GPUFIX_SMTP_PORT, GPUFIX_SMTP_SECURITY (tls|ssl|none),
 * GPUFIX_SMTP_USER, GPUFIX_SMTP_PASSWORD, GPUFIX_MAIL_FROM,
 * GPUFIX_MAIL_FROM_NAME y GPUFIX_APP_URL.
 */
function correo_enviar($destinatario, $asunto, $html, $texto = "")
{
    $configuracion = correo_obtener_configuracion();
    $host = trim((string) $configuracion["host"]);
    $puerto = (int) $configuracion["port"];
    $seguridad = strtolower(trim((string) $configuracion["security"]));
    $usuario = trim((string) $configuracion["user"]);
    $clave = trim(str_replace(" ", "", (string) $configuracion["password"]));
    $remitente = trim((string) $configuracion["from"]);
    $nombreRemitente = trim((string) $configuracion["from_name"]);

    if ($host === "" || $puerto < 1 || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)
        || !filter_var($remitente, FILTER_VALIDATE_EMAIL) || !in_array($seguridad, ["tls", "ssl", "none"], true)) {
        error_log("Correo no enviado: falta configuración SMTP válida.");
        return false;
    }

    $transporte = $seguridad === "ssl" ? "ssl://" : "tcp://";
    $socket = @stream_socket_client($transporte . $host . ":" . $puerto, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log("No se pudo conectar al servidor SMTP (" . $errno . ").");
        return false;
    }
    stream_set_timeout($socket, 15);

    try {
        correo_smtp_respuesta($socket, [220]);
        correo_smtp_comando($socket, "EHLO gpufix.local", [250]);

        if ($seguridad === "tls") {
            correo_smtp_comando($socket, "STARTTLS", [220]);
            $cifrado = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cifrado !== true) {
                throw new RuntimeException("No se pudo iniciar TLS.");
            }
            correo_smtp_comando($socket, "EHLO gpufix.local", [250]);
        }

        if ($usuario !== "") {
            correo_smtp_comando($socket, "AUTH LOGIN", [334]);
            correo_smtp_comando($socket, base64_encode($usuario), [334]);
            correo_smtp_comando($socket, base64_encode($clave), [235]);
        }

        correo_smtp_comando($socket, "MAIL FROM:<" . $remitente . ">", [250]);
        correo_smtp_comando($socket, "RCPT TO:<" . $destinatario . ">", [250, 251]);
        correo_smtp_comando($socket, "DATA", [354]);

        $texto = $texto !== "" ? $texto : trim(strip_tags(str_ireplace(["<br>", "<br/>", "<br />", "</p>"], "\n", $html)));
        $limite = "=_GPUFIX_" . bin2hex(random_bytes(12));
        $nombreSeguro = correo_cabecera($nombreRemitente);
        $asuntoSeguro = "=?UTF-8?B?" . base64_encode(correo_cabecera($asunto)) . "?=";
        $cabeceras = [
            "From: =?UTF-8?B?" . base64_encode($nombreSeguro) . "?= <" . $remitente . ">",
            "To: <" . $destinatario . ">",
            "Subject: " . $asuntoSeguro,
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"" . $limite . "\""
        ];
        $mensaje = implode("\r\n", $cabeceras) . "\r\n\r\n";
        $mensaje .= "--" . $limite . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto));
        $mensaje .= "--" . $limite . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html));
        $mensaje .= "--" . $limite . "--\r\n";
        $mensaje = preg_replace('/(^|\r\n)\./', '$1..', $mensaje);
        fwrite($socket, $mensaje . ".\r\n");
        correo_smtp_respuesta($socket, [250]);
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    } catch (Throwable $error) {
        error_log("Error de envío SMTP: " . $error->getMessage());
        fclose($socket);
        return false;
    }
}

function correo_smtp_comando($socket, $comando, $codigosPermitidos)
{
    if (fwrite($socket, $comando . "\r\n") === false) {
        throw new RuntimeException("No se pudo enviar un comando SMTP.");
    }
    return correo_smtp_respuesta($socket, $codigosPermitidos);
}

function correo_smtp_respuesta($socket, $codigosPermitidos)
{
    $respuesta = "";
    do {
        $linea = fgets($socket, 515);
        if ($linea === false) {
            throw new RuntimeException("El servidor SMTP cerró la conexión.");
        }
        $respuesta .= $linea;
    } while (isset($linea[3]) && $linea[3] === "-");

    $codigo = (int) substr($respuesta, 0, 3);
    if (!in_array($codigo, $codigosPermitidos, true)) {
        throw new RuntimeException("El servidor SMTP respondió con código " . $codigo . ".");
    }
    return $respuesta;
}

function correo_cabecera($valor)
{
    return trim(str_replace(["\r", "\n"], "", (string) $valor));
}

function correo_obtener_configuracion()
{
    $local = [];
    $archivoLocal = __DIR__ . "/correo-config.local.php";
    if (is_file($archivoLocal)) {
        if (!defined("GPUFIX_CARGA_CONFIG_CORREO")) {
            define("GPUFIX_CARGA_CONFIG_CORREO", true);
        }
        $resultado = require $archivoLocal;
        if (is_array($resultado)) {
            $local = $resultado;
        }
    }

    $leer = static function ($variable, $clave, $predeterminado = "") use ($local) {
        $valorEntorno = getenv($variable);
        if ($valorEntorno !== false && $valorEntorno !== "") {
            return $valorEntorno;
        }
        return $local[$clave] ?? $predeterminado;
    };

    return [
        "host" => $leer("GPUFIX_SMTP_HOST", "host"),
        "port" => $leer("GPUFIX_SMTP_PORT", "port", 587),
        "security" => $leer("GPUFIX_SMTP_SECURITY", "security", "tls"),
        "user" => $leer("GPUFIX_SMTP_USER", "user"),
        "password" => $leer("GPUFIX_SMTP_PASSWORD", "password"),
        "from" => $leer("GPUFIX_MAIL_FROM", "from"),
        "from_name" => $leer("GPUFIX_MAIL_FROM_NAME", "from_name", "GPU FIX & STORE"),
        "app_url" => $leer("GPUFIX_APP_URL", "app_url")
    ];
}

function correo_url_aplicacion()
{
    $configuracion = correo_obtener_configuracion();
    $configurada = rtrim(trim((string) $configuracion["app_url"]), "/");
    $hostActual = strtolower((string) ($_SERVER["HTTP_HOST"] ?? "localhost"));
    $hostActual = preg_replace('/:\\d+$/', "", $hostActual);
    $hostConfigurado = strtolower((string) (parse_url($configurada, PHP_URL_HOST) ?: ""));
    $hostConfiguradoLocal = in_array($hostConfigurado, ["localhost", "127.0.0.1"], true);
    $hostActualLocal = in_array($hostActual, ["localhost", "127.0.0.1"], true);

    // No permitir que una configuración local genere enlaces localhost en el hosting.
    if ($configurada !== "" && (!$hostConfiguradoLocal || $hostActualLocal)) {
        return $configurada;
    }

    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', "", $hostActual);
    $https = !$hostActualLocal
        || (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")) === "https";
    $base = rtrim(str_replace("\\", "/", dirname((string) ($_SERVER["SCRIPT_NAME"] ?? "/"))), "/");
    return ($https ? "https" : "http") . "://" . $host . ($base === "" ? "" : $base);
}

function correo_enviar_confirmacion($destinatario, $nombre, $token)
{
    $url = correo_url_aplicacion() . "/confirmar-correo.php?token=" . rawurlencode($token);
    $nombreSeguro = htmlspecialchars((string) $nombre, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $urlSeguro = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $html = "<p>Hola " . $nombreSeguro . ",</p><p>Confirmá tu correo para activar tu cuenta de GPU FIX &amp; STORE:</p><p><a href=\"" . $urlSeguro . "\">Confirmar mi correo</a></p><p>El enlace vence en 24 horas. Si no creaste esta cuenta, podés ignorar este mensaje.</p>";
    $texto = "Hola " . $nombre . ",\n\nConfirmá tu correo para activar tu cuenta: " . $url . "\n\nEl enlace vence en 24 horas. Si no creaste esta cuenta, podés ignorar este mensaje.";
    return correo_enviar($destinatario, "Confirmá tu correo - GPU FIX & STORE", $html, $texto);
}

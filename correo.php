<?php
/**
 * Envío SMTP reutilizable. Configuración por variables de entorno:
 * GPUFIX_SMTP_HOST, GPUFIX_SMTP_PORT, GPUFIX_SMTP_SECURITY (tls|ssl|none),
 * GPUFIX_SMTP_USER, GPUFIX_SMTP_PASSWORD, GPUFIX_MAIL_FROM,
 * GPUFIX_MAIL_FROM_NAME y GPUFIX_APP_URL.
 */
function correo_enviar($destinatario, $asunto, $html, $texto = "")
{
    $host = trim((string) getenv("GPUFIX_SMTP_HOST"));
    $puerto = (int) (getenv("GPUFIX_SMTP_PORT") ?: 587);
    $seguridad = strtolower(trim((string) (getenv("GPUFIX_SMTP_SECURITY") ?: "tls")));
    $usuario = (string) getenv("GPUFIX_SMTP_USER");
    $clave = (string) getenv("GPUFIX_SMTP_PASSWORD");
    $remitente = trim((string) getenv("GPUFIX_MAIL_FROM"));
    $nombreRemitente = trim((string) (getenv("GPUFIX_MAIL_FROM_NAME") ?: "GPU FIX & STORE"));

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

function correo_url_aplicacion()
{
    $configurada = rtrim(trim((string) getenv("GPUFIX_APP_URL")), "/");
    if ($configurada !== "") {
        return $configurada;
    }

    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', "", (string) ($_SERVER["HTTP_HOST"] ?? "localhost"));
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
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

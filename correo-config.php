
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function gpufix_enviar_correo(
    string $destinatario,
    string $asunto,
    string $mensaje
): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // Reemplazá estos valores por los tuyos.
        $mail->Username = 'lucasrecalde202@gmail.com';
        $mail->Password = 'uvjg aqjh zyjz fxki';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            'TU_CORREO@gmail.com',
            'GPUFIX'
        );

        $mail->addAddress($destinatario);
        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body = $mensaje;

        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log('Error al enviar correo GPUFIX: ' . $mail->ErrorInfo);
        return false;
    }
}

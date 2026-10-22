-- Ejecutar una sola vez después de migracion-confirmacion-correo.sql.
-- Los usuarios confirmados permanecen en usuarios.
-- Los registros no confirmados pasan a registro_pendiente y dejan de existir en usuarios/cliente.

CREATE TABLE IF NOT EXISTS registro_pendiente (
    id_pendiente INT(11) NOT NULL AUTO_INCREMENT,
    correo VARCHAR(150) NOT NULL,
    `contraseña` VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    dni VARCHAR(20) DEFAULT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    token_verificacion CHAR(64) DEFAULT NULL,
    token_expira DATETIME DEFAULT NULL,
    PRIMARY KEY (id_pendiente),
    UNIQUE KEY uq_registro_pendiente_correo (correo),
    UNIQUE KEY uq_registro_pendiente_token (token_verificacion),
    KEY idx_registro_pendiente_expira (token_expira)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

START TRANSACTION;

INSERT INTO registro_pendiente
    (correo, `contraseña`, nombre, apellido, dni, telefono, token_verificacion, token_expira)
SELECT
    correo, `contraseña`, nombre, apellido, dni, telefono, token_verificacion, token_expira
FROM usuarios
WHERE correo_verificado = 0
ON DUPLICATE KEY UPDATE
    `contraseña` = VALUES(`contraseña`),
    nombre = VALUES(nombre),
    apellido = VALUES(apellido),
    dni = VALUES(dni),
    telefono = VALUES(telefono),
    token_verificacion = VALUES(token_verificacion),
    token_expira = VALUES(token_expira);

DELETE cliente
FROM cliente
INNER JOIN usuarios ON usuarios.id_correo = cliente.id_correo
WHERE usuarios.correo_verificado = 0;

DELETE FROM usuarios
WHERE correo_verificado = 0;

COMMIT;

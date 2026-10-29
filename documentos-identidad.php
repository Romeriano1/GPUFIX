<?php
function documento_tipos(): array { return ['DNI', 'LI', 'LC', 'LE', 'Cédula', 'Pasaporte']; }
function documento_tipo_formulario(array $datos): string {
    $tipo = is_string($datos['tipo_documento'] ?? null) ? trim($datos['tipo_documento']) : 'DNI';
    if ($tipo === 'Otro') $tipo = is_string($datos['tipo_documento_otro'] ?? null) ? trim($datos['tipo_documento_otro']) : '';
    return $tipo;
}
function documento_tipo_valido(string $tipo): bool {
    return mb_strlen($tipo, 'UTF-8') >= 1 && mb_strlen($tipo, 'UTF-8') <= 40 && preg_match('/^[\p{L}\p{N} .\/-]+$/uD', $tipo);
}
function documento_selector(string $tipo, string $clase = 'login-campo'): void {
    $escapar = static fn($texto) => htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    $esOtro = !in_array($tipo, documento_tipos(), true);
    echo '<div class="'.$escapar($clase).'"><label for="tipoDocumento">Tipo de documento</label><select id="tipoDocumento" class="form-select" name="tipo_documento" required>';
    foreach (array_merge(documento_tipos(), ['Otro']) as $opcion) echo '<option value="'.$escapar($opcion).'" '.(($esOtro ? 'Otro' : $tipo)===$opcion ? 'selected' : '').'>'.$escapar($opcion).'</option>';
    echo '</select><div class="mt-2" id="grupoTipoDocumentoOtro" '.(!$esOtro ? 'hidden' : '').'><label for="tipoDocumentoOtro">Nombre del documento</label><input class="form-control" type="text" name="tipo_documento_otro" id="tipoDocumentoOtro" maxlength="40" value="'.$escapar($esOtro ? $tipo : '').'" '.($esOtro ? 'required' : 'disabled').'></div></div>';
}

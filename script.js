document.querySelectorAll("[data-validacion]").forEach(function(campo) {
    function validarCampo() {
        campo.setCustomValidity("");
        if (campo.dataset.validacion === "dni" && !/^[0-9]{7,8}$/.test(campo.value)) {
            campo.setCustomValidity("El DNI no es válido. Ingresá 7 u 8 números, sin puntos ni espacios.");
        } else if (campo.dataset.validacion === "correo" && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(campo.value) || campo.validity.typeMismatch)) {
            campo.setCustomValidity("El correo electrónico no es válido. Ingresá una dirección con @ y un dominio válido.");
        }
    }
    campo.addEventListener("input", validarCampo);
    campo.addEventListener("invalid", validarCampo);
    validarCampo();
});

const themeButton = document.getElementById("themeButton");

if (localStorage.getItem("tema") === "claro") {
    document.body.classList.add("light-theme");

    if (themeButton) {
        themeButton.textContent = "🌙";
    }
}

if (themeButton) {
    themeButton.addEventListener("click", function() {
        document.body.classList.toggle("light-theme");

        if (document.body.classList.contains("light-theme")) {
            themeButton.textContent = "🌙";
            localStorage.setItem("tema", "claro");
        } else {
            themeButton.textContent = "☀️";
            localStorage.setItem("tema", "oscuro");
        }
    });
}

const cuentaButton = document.getElementById("cuentaButton");
const cuentaMenu = document.getElementById("cuentaMenu");

if (cuentaButton && cuentaMenu) {
    cuentaButton.addEventListener("click", function(event) {
        event.stopPropagation();
        const abierto = cuentaMenu.classList.toggle("visible");
        cuentaButton.setAttribute("aria-expanded", String(abierto));
    });

    document.addEventListener("click", function(event) {
        if (!event.target.closest(".cuenta-contenedor")) {
            cuentaMenu.classList.remove("visible");
            cuentaButton.setAttribute("aria-expanded", "false");
        }
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            cuentaMenu.classList.remove("visible");
            cuentaButton.setAttribute("aria-expanded", "false");
            cuentaButton.focus();
        }
    });
}

const formularioPago = document.getElementById("formularioPago");
if (formularioPago) {
    const numeroTarjeta = document.getElementById("numeroTarjeta");
    const marcaTarjeta = document.getElementById("marcaTarjeta");
    const marcaEnviada = document.getElementById("marcaTarjetaEnviada");
    const ultimosEnviados = document.getElementById("ultimosTarjetaEnviados");
    const longitudEnviada = document.getElementById("longitudTarjetaEnviada");
    const comprobante = document.getElementById("comprobanteTransferencia");
    const titularTarjeta = document.getElementById("titularTarjeta");
    const vencimientoTarjeta = document.getElementById("vencimientoTarjeta");
    const ccvTarjeta = document.getElementById("ccvTarjeta");
    const dniTarjeta = document.getElementById("dniTarjeta");
    const datosTarjeta = [titularTarjeta, vencimientoTarjeta, ccvTarjeta, dniTarjeta];

    function detectarMarcaTarjeta(numero) {
        if (numero.startsWith("4")) return "Visa";
        if (/^5[1-5]/.test(numero)) return "Mastercard";
        const primerosSeis = Number(numero.slice(0, 6));
        if (numero.length >= 6 && primerosSeis >= 222100 && primerosSeis <= 272099) {
            return "Mastercard";
        }
        return "";
    }

    function validarTarjeta() {
        marcaEnviada.value = "";
        if (ultimosEnviados) ultimosEnviados.value = "";
        if (longitudEnviada) longitudEnviada.value = "";
        numeroTarjeta.setCustomValidity("");
        if (numeroTarjeta.disabled) return true;
        const numero = numeroTarjeta.value.replace(/\D/g, "");
        const marca = detectarMarcaTarjeta(numero);
        marcaTarjeta.textContent = marca ? marca + " detectada" : "Ingresá un número Visa o Mastercard.";
        const longitudValida = marca === "Visa" ? [13, 16, 19].includes(numero.length) : numero.length === 16;
        if (!marca) {
            numeroTarjeta.setCustomValidity("Solo se admiten tarjetas Visa o Mastercard.");
        } else if (!longitudValida) {
            numeroTarjeta.setCustomValidity("Completá el número de la tarjeta.");
        } else {
            marcaEnviada.value = marca;
            if (ultimosEnviados) ultimosEnviados.value = numero.slice(-4);
            if (longitudEnviada) longitudEnviada.value = String(numero.length);
            marcaTarjeta.textContent = marca + " detectada · formato válido";
        }
        return numeroTarjeta.validity.valid;
    }

    function validarDatosTarjeta() {
        datosTarjeta.forEach(function(campo) { campo.setCustomValidity(""); });
        if (numeroTarjeta.disabled) return true;
        if (titularTarjeta.value.trim().length < 3) {
            titularTarjeta.setCustomValidity("Ingresá el nombre del titular de la tarjeta.");
        }
        const fecha = /^(\d{2})\/(\d{2})$/.exec(vencimientoTarjeta.value);
        const hoy = new Date();
        const mes = fecha ? Number(fecha[1]) : 0;
        const anio = fecha ? 2000 + Number(fecha[2]) : 0;
        if (!fecha || mes < 1 || mes > 12) {
            vencimientoTarjeta.setCustomValidity("Ingresá un vencimiento válido en formato MM/AA.");
        } else if (anio < hoy.getFullYear() || (anio === hoy.getFullYear() && mes < hoy.getMonth() + 1)) {
            vencimientoTarjeta.setCustomValidity("La tarjeta está vencida.");
        }
        if (!/^\d{3}$/.test(ccvTarjeta.value)) ccvTarjeta.setCustomValidity("El CCV debe tener 3 números.");
        if (!/^\d{7,8}$/.test(dniTarjeta.value)) dniTarjeta.setCustomValidity("El DNI debe tener 7 u 8 números.");
        return datosTarjeta.every(function(campo) { return campo.validity.valid; });
    }

    function actualizarMetodoPago() {
        const esTarjeta = formularioPago.querySelector('input[name="metodo_pago"]:checked').value === "tarjeta";
        document.getElementById("pagoTarjeta").hidden = !esTarjeta;
        document.getElementById("pagoTransferencia").hidden = esTarjeta;
        numeroTarjeta.disabled = !esTarjeta;
        numeroTarjeta.required = esTarjeta;
        [ultimosEnviados, longitudEnviada].forEach(function(campo) {
            if (campo) campo.disabled = !esTarjeta;
        });
        datosTarjeta.forEach(function(campo) {
            campo.disabled = !esTarjeta;
            campo.required = esTarjeta;
        });
        comprobante.disabled = esTarjeta;
        comprobante.required = !esTarjeta;
        validarTarjeta();
        validarDatosTarjeta();
    }

    formularioPago.querySelectorAll('input[name="metodo_pago"]').forEach(function(opcion) {
        opcion.addEventListener("change", actualizarMetodoPago);
    });
    numeroTarjeta.addEventListener("input", function() {
        const digitos = numeroTarjeta.value.replace(/\D/g, "").slice(0, 19);
        numeroTarjeta.value = digitos.replace(/(.{4})/g, "$1 ").trim();
        validarTarjeta();
    });
    datosTarjeta.forEach(function(campo) { campo.addEventListener("input", validarDatosTarjeta); });
    vencimientoTarjeta.addEventListener("input", function() {
        const digitos = vencimientoTarjeta.value.replace(/\D/g, "").slice(0, 4);
        vencimientoTarjeta.value = digitos.length > 2 ? digitos.slice(0, 2) + "/" + digitos.slice(2) : digitos;
        validarDatosTarjeta();
    });
    [ccvTarjeta, dniTarjeta].forEach(function(campo) {
        campo.addEventListener("input", function() {
            campo.value = campo.value.replace(/\D/g, "").slice(0, campo.maxLength);
            validarDatosTarjeta();
        });
    });
    comprobante.addEventListener("change", function() {
        comprobante.setCustomValidity(comprobante.files[0] && comprobante.files[0].size > 2 * 1024 * 1024
            ? "El comprobante debe pesar como máximo 2 MB." : "");
    });
    let pagoEnviado = false;
    const botonConfirmarPago = formularioPago.querySelector('button[type="submit"]');
    formularioPago.addEventListener("submit", function(evento) {
        if (pagoEnviado) {
            evento.preventDefault();
            return;
        }
        if (!validarTarjeta()) {
            evento.preventDefault();
            numeroTarjeta.reportValidity();
        } else if (!validarDatosTarjeta()) {
            evento.preventDefault();
            formularioPago.reportValidity();
        } else {
            pagoEnviado = true;
            botonConfirmarPago.disabled = true;
            botonConfirmarPago.textContent = "Confirmando…";
        }
    });
    window.addEventListener("pageshow", function() {
        if (pagoEnviado) {
            pagoEnviado = false;
            botonConfirmarPago.disabled = false;
            botonConfirmarPago.textContent = "Confirmar";
        }
    });
    actualizarMetodoPago();
}

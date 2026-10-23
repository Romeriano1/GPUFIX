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

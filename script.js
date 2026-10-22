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

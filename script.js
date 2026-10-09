const themeButton = document.getElementById("themeButton");
const cuentaButton = document.getElementById("cuentaButton");
const cuentaDropdown = document.getElementById("cuentaDropdown");

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

if (cuentaButton && cuentaDropdown) {
    cuentaButton.addEventListener("click", function(event) {
        event.stopPropagation();

        const abierto = cuentaDropdown.classList.toggle("abierto");

        cuentaButton.setAttribute("aria-expanded", abierto ? "true" : "false");
    });

    document.addEventListener("click", function(event) {
        if (!cuentaDropdown.contains(event.target) && !cuentaButton.contains(event.target)) {
            cuentaDropdown.classList.remove("abierto");
            cuentaButton.setAttribute("aria-expanded", "false");
        }
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            cuentaDropdown.classList.remove("abierto");
            cuentaButton.setAttribute("aria-expanded", "false");
        }
    });
}

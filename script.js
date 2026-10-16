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

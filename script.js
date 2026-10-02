let fotos3090 = [
    "img/3090.jpg",
    "img/3090-2.jpg"
];

let fotos2080 = [
    "img/2080.jpg",
    "img/2080-2.jpg"
];

let posicion3090 = 0;
let posicion2080 = 0;

function cambiarFoto(gpu) {

    if (gpu === "3090") {
        posicion3090++;

        if (posicion3090 >= fotos3090.length) {
            posicion3090 = 0;
        }

        document.querySelectorAll(".producto img")[0].src = fotos3090[posicion3090];
    }

    if (gpu === "2080") {
        posicion2080++;

        if (posicion2080 >= fotos2080.length) {
            posicion2080 = 0;
        }

        document.querySelectorAll(".producto img")[1].src = fotos2080[posicion2080];
    }
}


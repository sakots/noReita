function fixchicken() {
    document.addEventListener('dblclick', function(e){ e.preventDefault()}, { passive: false });
    document.querySelector('#chickenpaint-parent').addEventListener('contextmenu', function (e){
        e.preventDefault();
        e.stopPropagation();
    }, { passive: false });
    }
window.addEventListener('DOMContentLoaded',fixchicken,false);

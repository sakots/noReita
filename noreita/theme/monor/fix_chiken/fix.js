function fixchicken() {
    document.addEventListener('dblclick', function(e){ e.preventDefault()}, { passive: false });
    const chicken=document.querySelector('#chickenpaint-parent');
    chicken.addEventListener('contextmenu', function (e){
        e.preventDefault();
        e.stopPropagation();
    }, { passive: false });
}
window.addEventListener('DOMContentLoaded',fixchicken,false);
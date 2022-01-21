function fixneo() {
    if(screen.width>767){//iPad以上の横幅の時は、NEOの網目のところでtouchmoveしない。
        console.log(screen.width);
        document.querySelector('#NEO').addEventListener('touchmove', function (e){
            e.preventDefault();
            e.stopPropagation();
        }, { passive: false });
    }
}
window.addEventListener('DOMContentLoaded',fixneo,false);

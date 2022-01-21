var colorIdx = GetCookie("_nee-ex_colorIdx");
switch (Number(colorIdx)) {
    case 1:
        document.getElementById("css1").removeAttribute("disabled");
        break;
    case 2:
        document.getElementById("css2").removeAttribute("disabled");
        break;
    case 3:
        document.getElementById("css3").removeAttribute("disabled");
        break;
    case 4:
        document.getElementById("css4").removeAttribute("disabled");
        break;
    case 5:
        document.getElementById("css5").removeAttribute("disabled");
        break;
    case 6:
        document.getElementById("css6").removeAttribute("disabled");
        break;
    case 7:
        document.getElementById("css7").removeAttribute("disabled");
        break;
    case 8:
        document.getElementById("css8").removeAttribute("disabled");
        break;
}
function SetCss(obj){
    var idx = obj.selectedIndex;
    SetCookie("_nee-ex_colorIdx",idx);
    window.location.reload();
}
function GetCookie(key){
    var tmp = document.cookie + ";";
    var tmp1 = tmp.indexOf(key, 0);
    if(tmp1 != -1){
        tmp = tmp.substring(tmp1, tmp.length);
        var start = tmp.indexOf("=", 0) + 1;
        var end = tmp.indexOf(";", start);
        return(decodeURIComponent(tmp.substring(start,end)));
    }
    return("");
}
function SetCookie(key, val){
    document.cookie = key + "=" + encodeURIComponent(val) + ";max-age=31536000;";
}

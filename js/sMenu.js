function openSubMenu(idSuffix) {
  var submenu_items = document.getElementsByClassName("subMenu" + idSuffix);
  for(var i=0; i<submenu_items.length; i++) {  
    if (submenu_items[i].className.indexOf("w3-show") == -1) {
      submenu_items[i].className += " w3-show";
    } else {
      submenu_items[i].className = submenu_items[i].className.replace(" w3-show", "");
    }
  }
}

// Open and close sidebar
function w3_open() {
  document.getElementById("menuSidebar").style.display = "block";
  document.getElementById("menuOverlay").style.display = "block";
}
 
function w3_close() {
  document.getElementById("menuSidebar").style.display = "none";
  document.getElementById("menuOverlay").style.display = "none";
}

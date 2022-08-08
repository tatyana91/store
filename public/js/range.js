function getVals(){
  // Get slider values
  var parent = this.parentNode;
  var slides = parent.getElementsByTagName("input");
    var slide1 = parseFloat( slides[0].value );
    var slide2 = parseFloat( slides[1].value );
  if( slide1 > slide2 ){ var tmp = slide2; slide2 = slide1; slide1 = tmp; }

  var displayElement1 = parent.getElementsByClassName("rangeValue")[0];
  var displayElement2 = parent.getElementsByClassName("rangeValue")[1];
      displayElement1.innerHTML = slide1;
      displayElement2.innerHTML = slide2;
}

window.onload = function(){
  var sliderSections = document.getElementsByClassName("range-slider");
      for( var x = 0; x < sliderSections.length; x++ ){
        var sliders = sliderSections[x].getElementsByTagName("input");
        for( var y = 0; y < sliders.length; y++ ){
          if( sliders[y].type ==="range" ){
            sliders[y].oninput = getVals;
            sliders[y].oninput();
          }
        }
      }
}

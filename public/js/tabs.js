var tablinks = document.querySelectorAll('.tab-link');
var tabs = document.querySelectorAll('.tab');

tablinks.forEach(function(tablink){
  var i = tablink.dataset.tab;
  tablink.addEventListener('click', function() {
    tablinks.forEach(function(all){
      all.dataset.tab === i ? all.classList.add('active') : all.classList.remove('active')
    })
    tabs.forEach(function(tab){
      tab.dataset.tabPane === i ? tab.classList.add('active') : tab.classList.remove('active')
    })
  })
})

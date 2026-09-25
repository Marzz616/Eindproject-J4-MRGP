function toonTab(naam, knop) {
    document.querySelectorAll('section.tabblad').forEach(function (s) {
        s.classList.remove('actief');
    });
    document.querySelectorAll('nav.tabs button').forEach(function (b) {
        b.classList.remove('actief');
    });
    document.getElementById('tab-' + naam).classList.add('actief');
    knop.classList.add('actief');
}

// Detailpaneel tonen/verbergen (voor "Bekijken" en "Aanpassen")
function togglePaneel(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = (el.style.display === 'block') ? 'none' : 'block';
}
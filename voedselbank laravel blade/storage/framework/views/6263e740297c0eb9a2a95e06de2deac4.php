<?php $__env->startSection('title', 'Rapportages & statistieken'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  .verdeelrij { display: grid; grid-template-columns: 1fr 78px 52px; gap: 12px; align-items: center;
                padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 14px; }
  .verdeelrij:last-child { border-bottom: 0; }
  .minibalk { height: 8px; border-radius: 999px; background: var(--grey-soft); overflow: hidden; }
  .minibalk > span { display: block; height: 100%; background: var(--green); }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app" id="app"></div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(async function () {
  const ik = await start({
    slug: 'rapportages',
    titel: 'Rapportages & statistieken',
    onder: 'Analyses over uitgiftevolumes, voorraadverloop en operationele efficiëntie',
  });
  if (!ik) { return; }

  let d;
  try { d = await api('rapportages.php?actie=overzicht'); }
  catch (e) { foutMelden(e); return; }

  const groei = d.pakketten.groei;
  const verspilling = d.verspilling;
  const haalt = verspilling.percentage <= verspilling.doel;

  document.getElementById('inhoud').innerHTML = `
    <div class="zoekbalk nietprint">
      <div style="flex:1;color:var(--ink-2);font-size:14px">
        Alle cijfers komen rechtstreeks uit de database en zijn te downloaden als CSV voor Excel.
      </div>
      <a class="knop primair" href="/maandoverzicht">Maandoverzichten</a>
      <button class="knop" onclick="window.print()">Rapport afdrukken</button>
    </div>

    <div class="rooster k3" style="margin-bottom:18px">
      <div class="card stat groen">
        <div class="label">Totaal uitgegeven pakketten (dit jaar)</div>
        <div class="waarde">${getal(d.pakketten.ytd)}</div>
        <div class="voet">${groei === null
          ? 'Geen vergelijkbare cijfers van vorig jaar'
          : (groei >= 0 ? '↑ ' : '↓ ') + Math.abs(groei) + '% t.o.v. vorig jaar (' + getal(d.pakketten.vorigJaar) + ')'}</div>
      </div>
      <div class="card stat">
        <div class="label">Gemiddelde pakketwaarde</div>
        <div class="waarde">${geld(d.waarde.gemiddeld)}</div>
        <div class="voet">berekend op winkelwaarde · totaal ${geld(d.waarde.totaal)} uitgedeeld</div>
      </div>
      <div class="card stat ${haalt ? 'groen' : 'rood'}">
        <div class="label">Verspillingspercentage</div>
        <div class="waarde">${verspilling.percentage}%</div>
        <div class="voet">doelstelling is &lt; ${verspilling.doel}% · ${verspilling.nuttig}% nuttig besteed</div>
      </div>
    </div>

    <div class="rooster k2" style="margin-bottom:18px">
      <div class="card vul">
        <div class="kop"><h2>Wekelijkse uitgifte</h2>
          <span class="rechts nietprint">
            <button class="knop klein" data-csv="weken">CSV</button></span></div>
        <div class="body">${staafdiagram(d.weken)}</div>
      </div>

      <div class="card">
        <div class="kop"><h2>Voorraadverloop (laatste 30 dagen)</h2>
          <span class="rechts nietprint">
            <button class="knop klein" data-csv="verloop">CSV</button></span></div>
        <div class="body">
          ${lijngrafiek(d.verloop, { label: 'Voorraadverloop' })}
          <div class="horizontaal" style="justify-content:space-between;color:var(--ink-3);font-size:12.5px;margin-top:6px">
            <span>${datumKort(d.verloop[0].datum)}</span>
            <span class="mono">nu ${getal(d.verloop[d.verloop.length - 1].stand)} stuks</span>
            <span>${datumKort(d.verloop[d.verloop.length - 1].datum)}</span>
          </div>
          <div style="font-size:12px;color:var(--ink-3);margin-top:8px">
            Teruggerekend uit de geboekte leveringen, uitgegeven pakketten en afgevoerde producten.
          </div>
        </div>
      </div>
    </div>

    <div class="rooster k2">
      <div class="card">
        <div class="kop"><h2>Bestemming producten &amp; verspilling</h2>
          <span class="rechts nietprint">
            <button class="knop klein" data-csv="bestemming">CSV</button></span></div>
        <div class="body">
          ${d.bestemming.map(b => `
            <div class="verdeelrij">
              <div>${esc(b.label)}</div>
              <div class="minibalk"><span style="width:${b.percentage}%"></span></div>
              <div class="mono r" style="text-align:right">${b.percentage}%</div>
            </div>`).join('')}
          <div style="margin-top:12px;font-size:13px;color:var(--ink-3)">
            Gebaseerd op ${getal(d.bestemming.reduce((s, b) => s + b.aantal, 0))} geregistreerde stuks.
          </div>
        </div>
      </div>

      <div class="card">
        <div class="kop"><h2>Gezinsgrootte-verdeling</h2>
          <span class="rechts nietprint">
            <button class="knop klein" data-csv="verdeling">CSV</button></span></div>
        <div class="body">
          ${d.verdeling.length ? d.verdeling.map(v => `
            <div class="verdeelrij">
              <div>${esc(v.groep)}</div>
              <div class="minibalk"><span style="width:${v.percentage}%"></span></div>
              <div class="mono" style="text-align:right">${v.aantal} <span style="color:var(--ink-3)">(${v.percentage}%)</span></div>
            </div>`).join('') : '<div class="leeg">Nog geen actieve klanten.</div>'}
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:18px">
      <div class="kop"><h2>Meest uitgedeelde producten</h2>
        <span class="rechts nietprint">
          <button class="knop klein" data-csv="topproducten">CSV</button></span></div>
      ${d.topProducten.length ? `
      <table class="lijst">
        <thead><tr><th>Product</th><th>Categorie</th><th class="r">Aantal uitgegeven</th></tr></thead>
        <tbody>${d.topProducten.map(p => `<tr>
          <td class="sterk">${esc(p.naam)}</td>
          <td><span class="badge grijs">${esc(p.categorie)}</span></td>
          <td class="r mono">${getal(p.aantal)}</td></tr>`).join('')}</tbody>
      </table>` : '<div class="leeg">Nog geen pakketten uitgegeven.</div>'}
    </div>`;

  document.querySelectorAll('[data-csv]').forEach(b => b.onclick = () => {
    location.href = 'api/rapportages.php?actie=export&rapport=' + b.dataset.csv;
  });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\guill\Herd\voedselbank-2\resources\views/pages/rapportages.blade.php ENDPATH**/ ?>
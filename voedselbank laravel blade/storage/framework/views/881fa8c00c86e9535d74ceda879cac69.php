<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  /* takenlijst: wat er voor deze rol openstaat */
  .taken { display: grid; gap: 8px; margin-bottom: 18px; }
  .taak { display: flex; align-items: center; gap: 11px; background: #fff; border: 1px solid var(--line-2);
          border-left: 4px solid var(--line-2); border-radius: 10px; padding: 11px 15px; }
  .taak.amber { border-left-color: var(--amber); }
  .taak.rood  { border-left-color: var(--red); }
  .taak.groen { border-left-color: var(--green); }
  .taak span  { flex: 1; }
  .taak a     { font-weight: 600; color: var(--green); text-decoration: none; white-space: nowrap; }
  .taak a:hover { text-decoration: underline; }
  .card.stat .tekst { font-family: 'Zilla Slab', serif; font-size: 23px; font-weight: 700;
                      line-height: 1.2; margin: 2px 0 4px; }
  /* Het aantal cijferkaarten verschilt per rol, dus laten we ze zelf uitvullen. */
  .kaarten { display: grid; gap: 18px; margin-bottom: 18px;
             grid-template-columns: repeat(auto-fit, minmax(232px, 1fr)); }
  .card.stat .voet a { color: inherit; text-decoration: none; }
  .card.stat .voet a:hover { text-decoration: underline; }
  .lijstje a { text-decoration: none; }
  .lijstje a:hover { text-decoration: underline; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app" id="app"></div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(async function () {
  const ik = await start({
    slug: 'dashboard',
    titel: 'Dashboard',
    onder: 'Operationele status van Voedselbank Maaskantje',
  });
  if (!ik) { return; }

  const inhoud = document.getElementById('inhoud');

  let d;
  try {
    d = await api('dashboard.php');
  } catch (e) {
    inhoud.innerHTML = '<div class="melding rood"><div>Het dashboard kon niet worden geladen.</div></div>';
    return;
  }

  // De ondertitel vertelt waarom dit dashboard er zo uitziet voor deze rol.
  const onder = document.querySelector('.topbar .onder');
  if (onder && d.intro) { onder.textContent = d.intro; }

  /* ------------------------------------------------ cijferkaarten */
  const kaart = (k) => {
    const voet = k.link
      ? `<div class="voet"><a href="${k.link}" style="color:inherit">${esc(k.voet)}</a></div>`
      : `<div class="voet">${esc(k.voet)}</div>`;
    const waarde = k.tekst !== undefined
      ? `<div class="tekst">${esc(k.tekst)}</div>`
      : `<div class="waarde">${getal(k.waarde)}</div>`;
    return `<div class="card stat ${esc(k.kleur || '')}">
      <div class="label">${esc(k.label)}</div>${waarde}${voet}</div>`;
  };

  /* ------------------------------------------------ lijstblok */
  const rij = (r) => {
    const rechts = [];
    if (r.waarde) { rechts.push(`<div class="mono" style="font-weight:500">${esc(r.waarde)}</div>`); }
    // r.rechts staat al als pil achter de naam zodra er een kleur bij hoort;
    // dan hoeft hij er rechts niet nog eens te staan.
    if (r.datum)  { rechts.push(`<div style="color:var(--ink-3);font-size:12.5px">${datumKort(r.datum)}</div>`); }
    else if (r.rechts && !r.kleur) { rechts.push(`<div style="color:var(--ink-3);font-size:12.5px">${esc(r.rechts)}</div>`); }

    const naam = r.link
      ? `<a href="${r.link}" style="color:inherit;text-decoration:none">${esc(r.naam)}</a>`
      : esc(r.naam);

    return `<li>
      <div class="stapel" style="flex:1">
        <div style="font-weight:600">${naam}
          ${r.kleur && r.rechts ? `<span class="pil2 ${r.kleur === 'rood' ? 'nee' : (r.kleur === 'groen' ? 'ok' : 'wacht')}"
             style="margin-left:7px">${esc(r.rechts)}</span>` : ''}</div>
        <div style="color:var(--ink-3);font-size:12.5px">${esc(r.onder || '')}</div>
      </div>
      <div class="stapel" style="text-align:right">${rechts.join('')}</div>
    </li>`;
  };

  const blok = (b) => {
    if (b.soort === 'grafiek') {
      return `<div class="card vul">
        <div class="kop"><h2>${esc(b.titel)}</h2><span class="tel rechts">${esc(b.tel || '')}</span></div>
        <div class="body">${staafdiagram(b.weken)}</div>
      </div>`;
    }
    return `<div class="card">
      <div class="kop"><h2>${esc(b.titel)}</h2><span class="tel rechts">${esc(b.tel || '')}</span></div>
      <div class="body" style="padding:6px 20px 14px">
        ${b.rijen.length ? '<ul class="lijstje">' + b.rijen.map(rij).join('') + '</ul>'
                         : '<div class="leeg">' + esc(b.leeg) + '</div>'}
      </div>
    </div>`;
  };

  /* ------------------------------------------------ pagina opbouwen */
  const taken = d.taken.length
    ? `<div class="taken">${d.taken.map(t => `<div class="taak ${esc(t.kleur || '')}">
         <span>${esc(t.tekst)}</span>
         <a href="${t.link}">Bekijken →</a></div>`).join('')}</div>`
    : `<div class="melding groen" style="margin-bottom:18px"><div>
         Er staat niets open voor jou. Mooi werk.</div></div>`;

  // De grafiek is breed, de lijsten smal: samen in de 2-op-1-indeling.
  const grafiek = d.blokken.filter(b => b.soort === 'grafiek');
  const lijsten = d.blokken.filter(b => b.soort !== 'grafiek');

  let onderkant = '';
  if (grafiek.length) {
    onderkant += `<div class="rooster k21">${blok(grafiek[0])}${lijsten.length ? blok(lijsten[0]) : ''}</div>`;
    const rest = lijsten.slice(1);
    if (rest.length) { onderkant += `<div class="rooster k2" style="margin-top:18px">${rest.map(blok).join('')}</div>`; }
  } else if (lijsten.length) {
    onderkant += `<div class="rooster k2">${lijsten.map(blok).join('')}</div>`;
  }

  inhoud.innerHTML = `
    ${taken}
    ${d.kaarten.length ? `<div class="kaarten">${d.kaarten.map(kaart).join('')}</div>` : ''}
    ${onderkant}`;
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\guill\Herd\voedselbank-2\resources\views/pages/dashboard.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', 'Inloggen'); ?>

<?php $__env->startSection('body'); ?>
<div class="login">
  <div class="doos">
    <div class="merk">
      <div class="logo" style="width:44px;height:44px;border-radius:12px;background:#2E6B4A;color:#fff;font-family:'Zilla Slab',serif;font-weight:700;display:grid;place-items:center;font-size:17px;">VB</div>
      <div>
        <div style="font-family:'Zilla Slab',serif;font-weight:700;font-size:17px;line-height:1.1;">Voedselbank Maaskantje</div>
        <div style="font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#8E9282;font-weight:600;margin-top:3px;">Back-office</div>
      </div>
    </div>

    <h1>Inloggen</h1>
    <div class="onder">Log in met je medewerkersaccount om verder te gaan.</div>

    <div id="fout" class="melding rood verborgen"><div id="foutTekst"></div></div>

    <form id="vorm" novalidate>
      <div class="veld">
        <label for="gebruikersnaam">Gebruikersnaam of e-mailadres</label>
        <input type="text" id="gebruikersnaam" name="gebruikersnaam" autocomplete="username" autofocus required>
      </div>
      <div class="veld">
        <label for="wachtwoord">Wachtwoord</label>
        <input type="password" id="wachtwoord" name="wachtwoord" autocomplete="current-password" required>
      </div>
      <button class="knop primair groot volle" type="submit" id="verstuur">Inloggen</button>
    </form>

    <p class="geenaccount">
      Nog geen account? Vraag de directie om er een voor je aan te maken.
    </p>

    <div class="demo">
      <b>Testaccounts</b> (wachtwoord voor alle accounts: <span class="mono">voedselbank</span>)
      <table>
        <tr><td class="mono">p.abraham</td><td>Directie</td></tr>
        <tr><td class="mono">m.snel</td><td>Administratie</td></tr>
        <tr><td class="mono">r.veenstra</td><td>Magazijnmedewerker</td></tr>
        <tr><td class="mono">a.degroot</td><td>Vrijwilliger</td></tr>
        <tr><td class="mono">k.degraaf</td><td>Vrijwilliger (geblokkeerd)</td></tr>
      </table>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(async function () {
  // al ingelogd? dan meteen door
  try {
    const r = await api('auth.php?actie=ik');
    if (r.ingelogd) { location.replace(startpagina(r.gebruiker)); return; }
  } catch (e) { /* niet ingelogd, gewoon het formulier tonen */ }

  const vorm = document.getElementById('vorm');
  const knop = document.getElementById('verstuur');
  const balk = document.getElementById('fout');
  const tekst = document.getElementById('foutTekst');

  vorm.addEventListener('submit', async (e) => {
    e.preventDefault();
    balk.classList.add('verborgen');
    knop.disabled = true;
    knop.textContent = 'Bezig…';

    try {
      const r = await api('auth.php?actie=login', {
        body: {
          gebruikersnaam: vorm.gebruikersnaam.value.trim(),
          wachtwoord: vorm.wachtwoord.value,
        },
      });
      const terug = new URLSearchParams(location.search).get('terug');
      const mag = terug && r.gebruiker.paginas.some(p => terug.startsWith(p.replace('dashboard', 'index')));
      location.replace(mag ? terug : startpagina(r.gebruiker));
    } catch (err) {
      const d = (err && err.data) || {};
      tekst.innerHTML = '<b>' + esc(d.fout || 'Inloggen is niet gelukt.') + '</b>';
      balk.classList.remove('verborgen');
      vorm.wachtwoord.value = '';
      vorm.wachtwoord.focus();
    } finally {
      knop.disabled = false;
      knop.textContent = 'Inloggen';
    }
  });
})();

/** Waar komt iemand terecht na het inloggen? Afhankelijk van zijn rechten. */
function startpagina(g) {
  const volgorde = [
    ['dashboard', '/'],
    ['pakketten', '/pakketten'],
    ['uitgifte', '/uitgifte'],
    ['producten', '/producten'],
    ['leveranciers', '/leveranciers'],
    ['klanten', '/klanten'],
    ['gebruikers', '/gebruikers'],
    ['account', '/account'],
  ];
  for (const [slug, href] of volgorde) {
    if (g.paginas.includes(slug)) { return href; }
  }
  return '/account';
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\guill\Herd\voedselbank-2\resources\views/pages/login.blade.php ENDPATH**/ ?>
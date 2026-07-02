<?php
function top_menu(array $user): void {
    /* Anti-FOUC: apply saved theme before first paint */
    echo '<script>(function(){var t=localStorage.getItem("gp-theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})()</script>' . "\n";
    $cur  = basename($_SERVER['SCRIPT_NAME']);
    $role = $user['ruolo'] ?? '';
    $nome = htmlspecialchars($user['nome'] ?: $user['username']);

    $navPdo      = db();
    $navSett     = get_settings($navPdo);
    $navNomeSala = $navSett['nome_sala'] ?? (config()['nome_sala'] ?? '');
    $modAssistenze    = ($navSett['modulo_assistenze']  ?? '1') === '1';
    $modPrestiti      = ($navSett['modulo_prestiti']    ?? '1') === '1';
    $modDocumenti     = ($navSett['modulo_documenti']   ?? '1') === '1';
    $modContatti      = ($navSett['modulo_contatti']    ?? '1') === '1';
    $mobGiornalerioOk = ($navSett['mobile_giornaliero'] ?? '0') === '1';

    $ico = [
        'dashboard'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        'giornaliero' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        'settimanale' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
        'mensile'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 4-6"/></svg>',
        'annuale'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 16l2-2 3 2 3-3"/></svg>',
        'awp'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
        'turni'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>',
        'ticket'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg>',
        'prestiti'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M12 14h4M12 14l2-2M12 14l2 2"/></svg>',
        'onboarding'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>',
        'macchine'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M7 8h2M7 11h5"/></svg>',
        'utenti'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'audit'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'documenti'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
        'impostazioni'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>',
        'fornitori'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'contatti'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'profilo'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'revisore'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
    ];

    $isRevisore = $role === 'revisore';

    $cassaItems = $isRevisore
        ? [
            'cassa/settimanale.php' => ['label' => 'Settimanale', 'ico' => 'settimanale'],
            'cassa/mensile.php'     => ['label' => 'Mensile',     'ico' => 'mensile'],
            'cassa/annuale.php'     => ['label' => 'Annuale',     'ico' => 'annuale'],
          ]
        : [
            'cassa/giornaliero.php' => ['label' => 'Giornaliero', 'ico' => 'giornaliero'],
            'cassa/settimanale.php' => ['label' => 'Settimanale', 'ico' => 'settimanale'],
            'cassa/mensile.php'     => ['label' => 'Mensile',     'ico' => 'mensile'],
            'cassa/annuale.php'     => ['label' => 'Annuale',     'ico' => 'annuale'],
          ];

    $salaItems = [];
    if (!$isRevisore) {
        $salaItems = [
            'sala/awp.php'   => ['label' => 'AWP',   'ico' => 'awp'],
            'sala/turni.php' => ['label' => 'Turni', 'ico' => 'turni'],
        ];
        if ($modAssistenze) $salaItems['sala/ticket.php']    = ['label' => 'Assistenze', 'ico' => 'ticket'];
        if ($modPrestiti)   $salaItems['sala/prestiti.php']  = ['label' => 'Prestiti',   'ico' => 'prestiti'];
        if ($modDocumenti)  $salaItems['sala/documenti.php'] = ['label' => 'Documenti',  'ico' => 'documenti'];
        if ($modContatti)   $salaItems['sala/contatti.php']  = ['label' => 'Contatti',   'ico' => 'contatti'];
    }

    $adminItems = ($role === 'responsabile') ? [
        'account/admin/macchine.php'     => ['label' => 'Macchine',     'ico' => 'macchine'],
        'account/admin/utenti.php'       => ['label' => 'Utenti',       'ico' => 'utenti'],
        'account/admin/impostazioni.php' => ['label' => 'Impostazioni', 'ico' => 'impostazioni'],
        'account/admin/audit.php'        => ['label' => 'Audit',        'ico' => 'audit'],
    ] : [];

    $renderLink = function(string $file, array $item) use ($cur, $ico): void {
        $active = (basename($file) === $cur);
        $cls    = 'sn-link' . ($active ? ' active' : '');
        $aria   = $active ? ' aria-current="page"' : '';
        echo '<a class="' . $cls . '" href="' . base_url($file) . '"' . $aria . ' title="' . htmlspecialchars($item['label']) . '">';
        echo '<span class="sn-ico" aria-hidden="true">' . $ico[$item['ico']] . '</span>';
        echo '<span class="sn-lbl">' . htmlspecialchars($item['label']) . '</span>';
        echo '</a>';
    };

    $foto        = $user['foto'] ?? null;
    $rawName     = $user['nome'] ?: $user['username'];
    $initial     = avatar_initials($rawName);
    $avatarStyle = avatar_style($rawName);
    $salaWords    = array_filter(array_slice(preg_split('/\s+/', trim($navNomeSala)), 0, 2));
    $salaInitials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1, 'UTF-8'), $salaWords)), 'UTF-8') ?: 'CS';
    $navLogoPath  = $navSett['logo_path'] ?? null;
    $navLogoUrl   = $navLogoPath ? base_url('account/uploads/sala/' . rawurlencode($navLogoPath)) : null;
?>
<link rel="stylesheet" href="<?= asset_url('assets/css/ob-banners.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/tour.css') ?>">
<aside class="sidebar" id="sidebar" data-role="<?= htmlspecialchars($role) ?>" aria-label="Navigazione principale">

  <div class="sb-head">
    <?php if ($navLogoUrl): ?>
      <img src="<?= htmlspecialchars($navLogoUrl) ?>" class="sb-logo-img" alt="<?= htmlspecialchars($navNomeSala) ?>">
    <?php else: ?>
      <span class="sb-logo" aria-hidden="true"><?= htmlspecialchars($salaInitials) ?></span>
    <?php endif; ?>
    <span class="sb-title"><?= htmlspecialchars($navNomeSala ?: 'Sala') ?></span>
    <button class="sb-collapse-btn" id="sb-collapse" type="button" title="Comprimi/Espandi menu" aria-label="Comprimi menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
    </button>
  </div>

  <nav class="sb-nav" aria-label="Menu principale">

    <!-- Dashboard -->
    <div class="sn-group">
      <?php $renderLink('account/dashboard.php', ['label' => 'Dashboard', 'ico' => $isRevisore ? 'revisore' : 'dashboard']); ?>
    </div>

    <div class="sn-group">
      <span class="sn-cat"><?= $isRevisore ? 'Report' : 'Cassa' ?></span>
      <?php foreach ($cassaItems as $file => $item): ?>
        <?php $renderLink($file, $item); ?>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($salaItems)): ?>
    <div class="sn-group">
      <span class="sn-cat">Sala</span>
      <?php foreach ($salaItems as $file => $item): ?>
        <?php $renderLink($file, $item); ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($adminItems)): ?>
    <div class="sn-group">
      <span class="sn-cat">Admin</span>
      <?php foreach ($adminItems as $file => $item): ?>
        <?php $renderLink($file, $item); ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </nav>

  <div class="sb-util">
    <?php $renderLink('docs/onboarding.php', ['label' => 'Guida', 'ico' => 'onboarding']); ?>
  </div>

  <div class="sb-foot">
    <a href="<?= base_url('account/profilo.php') ?>" class="sf-avatar-link" title="Profilo">
      <?php if ($foto): ?>
        <img src="<?= base_url('account/uploads/profili/') . htmlspecialchars($foto) ?>" class="sf-avatar sf-avatar-img" alt="Foto profilo">
      <?php else: ?>
        <span class="sf-avatar" aria-hidden="true" style="<?= $avatarStyle ?>"><?= $initial ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= base_url('account/profilo.php') ?>" class="sf-name" title="Profilo"><?= $nome ?></a>
    <button class="sf-theme" id="theme-toggle" type="button" aria-label="Cambia tema chiaro/scuro" title="Tema">
      <svg class="th-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <svg class="th-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
    </button>
    <a href="<?= base_url('account/logout.php') ?>" class="sf-exit" aria-label="Esci" title="Esci">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>

</aside>

<!-- Hamburger mobile -->
<button class="sb-toggle" id="sb-toggle" type="button" aria-expanded="false" aria-controls="sidebar" aria-label="Apri menu">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
</button>
<div class="sb-overlay" id="sb-overlay" aria-hidden="true" role="presentation"></div>

<?php if (!$isRevisore):
    $dashUrl    = base_url('account/dashboard.php');
    $dashActive = $cur === 'dashboard.php';
    $cassaActive  = $cur === 'giornaliero.php';
    $turniActive  = $cur === 'turni.php';
    $docActive    = $cur === 'documenti.php';
?>
<nav class="mob-tab-bar" aria-label="Navigazione rapida">
  <a class="mob-tab<?= $dashActive  ? ' mob-tab-on' : '' ?>" href="<?= $dashUrl ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    <span>Home</span>
  </a>
  <?php if ($mobGiornalerioOk): ?>
  <a class="mob-tab<?= $cassaActive ? ' mob-tab-on' : '' ?>" href="<?= base_url('cassa/giornaliero.php') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    <span>Cassa</span>
  </a>
  <?php endif; ?>
  <a class="mob-tab<?= $turniActive ? ' mob-tab-on' : '' ?>" href="<?= base_url('sala/turni.php') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
    <span>Turni</span>
  </a>
  <?php if ($modDocumenti): ?>
  <a class="mob-tab<?= $docActive   ? ' mob-tab-on' : '' ?>" href="<?= base_url('sala/documenti.php') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
    <span>Documenti</span>
  </a>
  <?php endif; ?>
</nav>
<?php endif; ?>

<script src="<?= asset_url('assets/js/sidebar.js') ?>"></script>
<script>window.GP_BASE='<?= addslashes(base_url()) ?>';window.GP_ROLE='<?= addslashes($role) ?>';window.GP_SALA='<?= addslashes($navNomeSala) ?>';</script>
<script src="<?= asset_url('assets/js/ob-banners.js') ?>" defer></script>
<script src="<?= asset_url('assets/js/toast.js') ?>" defer></script>
<script src="<?= asset_url('assets/js/tour.js') ?>" defer></script>
<script>(function(){
  var sn='<?= addslashes($navNomeSala) ?>';
  if(sn && document.title && document.title.indexOf(sn)===-1) document.title=document.title+' · '+sn;
  if('serviceWorker' in navigator)navigator.serviceWorker.register('<?= addslashes(base_url('sw.js')) ?>');
  var btn = document.getElementById('theme-toggle');
  if (btn) btn.addEventListener('click', function() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('gp-theme', 'light');
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('gp-theme', 'dark');
    }
  });
})()</script>
<?php
// Brand accent CSS override
$brandAccent = $navSett['brand_accent'] ?? null;
if ($brandAccent && preg_match('/^#[0-9a-fA-F]{6}$/', $brandAccent)) {
    $bvars = brand_derive($brandAccent);
    echo '<style>:root{';
    foreach ($bvars as $prop => $val) echo htmlspecialchars($prop) . ':' . htmlspecialchars($val) . ';';
    echo '}</style>' . "\n";
}
}


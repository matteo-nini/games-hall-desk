<?php
function top_menu(array $user): void {
    $cur  = basename($_SERVER['SCRIPT_NAME']);
    $role = $user['ruolo'] ?? '';
    $nome = htmlspecialchars($user['nome'] ?: $user['username']);

    $ico = [
        'giornaliero' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        'settimanale' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
        'mensile'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 4-6"/></svg>',
        'awp'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
        'ticket'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg>',
        'prestiti'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
        'onboarding'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>',
        'macchine'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
        'utenti'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'audit'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    ];

    $mainItems = [
        'giornaliero.php' => ['label' => 'Giornaliero', 'ico' => 'giornaliero'],
        'settimanale.php' => ['label' => 'Settimanale', 'ico' => 'settimanale'],
        'mensile.php'     => ['label' => 'Mensile',     'ico' => 'mensile'],
        'awp.php'         => ['label' => 'AWP',         'ico' => 'awp'],
        'ticket.php'      => ['label' => 'Ticket',      'ico' => 'ticket'],
        'prestiti.php'    => ['label' => 'Prestiti',    'ico' => 'prestiti'],
        'onboarding.php'  => ['label' => 'Guida',       'ico' => 'onboarding'],
    ];

    $adminItems = ($role === 'responsabile') ? [
        'macchine.php' => ['label' => 'Macchine', 'ico' => 'macchine'],
        'utenti.php'   => ['label' => 'Utenti',   'ico' => 'utenti'],
        'audit.php'    => ['label' => 'Audit',    'ico' => 'audit'],
    ] : [];

    $renderLink = function(string $file, array $item) use ($cur, $ico): void {
        $active = ($file === $cur);
        $cls    = 'sn-link' . ($active ? ' active' : '');
        $aria   = $active ? ' aria-current="page"' : '';
        echo '<a class="' . $cls . '" href="' . $file . '"' . $aria . '>';
        echo '<span class="sn-ico" aria-hidden="true">' . $ico[$item['ico']] . '</span>';
        echo '<span class="sn-lbl">' . htmlspecialchars($item['label']) . '</span>';
        echo '</a>';
    };

    $initial = mb_strtoupper(mb_substr($user['nome'] ?: $user['username'], 0, 1, 'UTF-8'), 'UTF-8');
?>
<aside class="sidebar" id="sidebar" aria-label="Navigazione principale">
  <div class="sb-head">
    <span class="sb-logo" aria-hidden="true">GP</span>
    <span class="sb-title">Games Palace</span>
  </div>
  <nav class="sb-nav" aria-label="Menu principale">
    <?php foreach ($mainItems as $file => $item): ?>
      <?php $renderLink($file, $item); ?>
    <?php endforeach; ?>
    <?php if (!empty($adminItems)): ?>
      <div class="sn-sep" role="separator" aria-hidden="true"></div>
      <?php foreach ($adminItems as $file => $item): ?>
        <?php $renderLink($file, $item); ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </nav>
  <div class="sb-foot">
    <span class="sf-avatar" aria-hidden="true"><?= $initial ?></span>
    <span class="sf-name"><?= $nome ?></span>
    <a href="logout.php" class="sf-exit" aria-label="Esci dall&#39;applicazione" title="Esci">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>
<button class="sb-toggle" id="sb-toggle" type="button" aria-expanded="false" aria-controls="sidebar" aria-label="Apri menu di navigazione">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
</button>
<div class="sb-overlay" id="sb-overlay" aria-hidden="true" role="presentation"></div>
<script>
(function(){
  var t=document.getElementById('sb-toggle'),
      s=document.getElementById('sidebar'),
      o=document.getElementById('sb-overlay');
  if(!t||!s||!o)return;
  function openSb(){s.classList.add('open');o.classList.add('open');t.setAttribute('aria-expanded','true');document.body.classList.add('sb-open');}
  function closeSb(){s.classList.remove('open');o.classList.remove('open');t.setAttribute('aria-expanded','false');document.body.classList.remove('sb-open');}
  t.addEventListener('click',function(){s.classList.contains('open')?closeSb():openSb();});
  o.addEventListener('click',closeSb);
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&s.classList.contains('open')){closeSb();t.focus();}});
})();
</script>
<?php
}

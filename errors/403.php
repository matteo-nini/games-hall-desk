<?php
require_once __DIR__ . '/../includes/lib.php';
http_response_code(403);
$_errNomeSala = config()['nome_sala'] ?? 'Cassa Sala';
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>403 — Accesso negato</title>
<link rel="stylesheet" href="../assets/css/core.css">
<link rel="stylesheet" href="../assets/css/error.css">
</head><body>
<div class="err-wrap">
  <div class="err-icon" aria-hidden="true">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
  </div>
  <p class="err-code">403</p>
  <h1 class="err-title">Accesso negato</h1>
  <p class="err-desc">Non hai i permessi per accedere a questa pagina. Se ritieni sia un errore, contatta il responsabile.</p>
  <div class="err-actions">
    <a href="javascript:history.back()" class="err-back err-back-ghost">← Indietro</a>
    <a href="../index.php" class="err-back">Vai alla home</a>
  </div>
  <p class="err-brand"><?= htmlspecialchars($_errNomeSala) ?> · Gestione cassa</p>
</div>
</body></html>

<?php
require_once __DIR__ . '/../includes/lib.php';
http_response_code(404);
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 — Pagina non trovata</title>
<link rel="stylesheet" href="<?= base_url('assets/css/core.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/error.css') ?>">
</head><body>
<div class="err-wrap">
  <div class="err-icon" aria-hidden="true">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/><path d="M11 8v3M11 14h.01"/></svg>
  </div>
  <p class="err-code">404</p>
  <h1 class="err-title">Pagina non trovata</h1>
  <p class="err-desc">La pagina che cerchi non esiste o è stata spostata. Controlla l'indirizzo o torna alla home.</p>
  <div class="err-actions">
    <a href="javascript:history.back()" class="err-back err-back-ghost">&#8592; Indietro</a>
    <a href="<?= base_url('index.php') ?>" class="err-back">Vai alla home</a>
  </div>
  <p class="err-brand">Games Palace · Gestione cassa</p>
</div>
</body></html>

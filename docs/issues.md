# Issue log — GestHall Suite

Report generato dall'audit automatico del codebase (2026-07-02, 37 agenti).
Classificato per categoria e priorità. Le issue risolte vanno marcate con ✅.

---

## Sicurezza

| ID | Priorità | Titolo | File |
|----|----------|--------|------|
| S-02 | 🔴 ALTA | `rate_limit_check()` ritorna `false` su eccezione DB (fail-open) | `includes/auth.php:80-82` |
| S-03 | 🔴 ALTA | User enumeration via reset password (messaggio differente se email mancante) | `account/reset_password.php` |
| S-04 | 🟡 MEDIA | Password assistenza salvata in chiaro nella tabella `impostazioni` | `account/admin/impostazioni.php` |
| S-05 | 🟡 MEDIA | `$_SERVER['REMOTE_ADDR']` non validato come IP | `account/dashboard.php:51` |
| S-06 | 🟡 MEDIA | Valore JS interpolato senza `json_encode` in `confirm()` | `account/dashboard.php:~533` |
| S-07 | 🟡 MEDIA | `cookie_secure` non auto-rilevato da HTTPS | `includes/auth.php:22-28` |
| S-08 | 🔵 BASSA | Nessun MIME type check lato server su upload foto profilo | `account/profilo.php` |
| S-09 | 🔵 BASSA | Falso ok (`?ok=1`) su email malformata in impostazioni | `account/admin/impostazioni.php` |
| S-10 | 🔵 BASSA | Nessun SRI su `<script>` Chart.js da CDN | template dashboard |

### Fix S-02 — rate_limit fail-closed
```php
// includes/auth.php:80-82
} catch (Throwable) {
    return true; // fail-closed: in caso di dubbio blocca
}
```

### Fix S-03 — User enumeration reset
```php
// Rimuovere il branch elseif con messaggio differente; unificare con risposta generica:
if ($u && !empty($u['email'])) {
    mail_reset_password($pdo, (int)$u['id'], $u['email'], $sett, $cfg);
    audit('password_reset_richiesto', 'utenti', (int)$u['id'], $username);
}
if (!$err) $sent = true; // sempre true se username non vuoto
```

### Fix S-05 — IP validation
```php
$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
```

### Fix S-07 — cookie_secure auto
```php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
```

### Fix S-08 — MIME check upload profilo
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) {
    $err = 'Formato immagine non supportato.';
}
```

---

## Performance

| ID | Priorità | Titolo | File |
|----|----------|--------|------|
| P-01 | 🔴 ALTA | Loop `riepilogo_giornata()` per mese genera 200+ query SQL | `cassa/mensile.php`, `utils/export.php` |
| P-02 | 🔴 ALTA | Subquery correlate N×4 per statistiche operatori | `account/dashboard.php:148-157` |
| P-03 | 🟡 MEDIA | Cache statica `get_settings()` stale dopo scritture nella stessa request | `includes/lib.php:15-25` |
| P-04 | 🟡 MEDIA | DDL `CREATE TABLE IF NOT EXISTS` eseguita ad ogni login | `includes/auth.php:70-75` |
| P-05 | 🟡 MEDIA | `current_user()` fa SELECT ad ogni chiamata senza memoizzazione | `includes/auth.php:31-37` |
| P-06 | 🔵 BASSA | `ALTER TABLE` auto-migrazione eseguita ad ogni page load | `sala/macchine.php`, `account/profilo.php` |
| P-07 | 🔵 BASSA | Export CSV audit_log senza streaming (fetchAll in memoria) | `utils/audit.php` |
| P-08 | 🔵 BASSA | Indice mancante su `login_attempts(ip, attempted_at)` | `install/schema.sql` |

### Fix P-01 — Query aggregata per mese
```sql
SELECT g.data,
       COALESCE(SUM(s.importo),0) AS scass,
       COALESCE(SUM(t.bancomat),0) AS bancomat,
       COALESCE(SUM(tk.importo),0) AS ticket
FROM giornate g
LEFT JOIN turni t ON t.giornata_id = g.id
LEFT JOIN scassettamenti s ON s.turno_id = t.id
LEFT JOIN ticket tk ON tk.turno_id = t.id
WHERE g.data BETWEEN ? AND ?
GROUP BY g.data
```

### Fix P-04 — Rimuovere DDL da auth.php
La tabella `login_attempts` è già in `install/schema.sql`. Rimuovere le righe
70-75 di `auth.php`. Aggiungere l'indice al schema:
```sql
INDEX idx_ip_time (ip, attempted_at)
```

### Fix P-05 — Memoizzare current_user()
```php
function current_user(): ?array {
    static $memo = 'unset';
    if ($memo !== 'unset') return $memo;
    start_session();
    if (empty($_SESSION['uid'])) { $memo = null; return null; }
    $st = db()->prepare('SELECT * FROM utenti WHERE id = ? AND attivo = 1');
    $st->execute([$_SESSION['uid']]);
    $memo = $st->fetch() ?: null;
    return $memo;
}
```

---

## Qualità / Logica

| ID | Priorità | Titolo | File |
|----|----------|--------|------|
| Q-01 | 🔴 ALTA | `riepilogo_giornata()` con `LIMIT 1` ignora il turno mattino | `includes/lib.php:178-180` |
| Q-02 | 🟡 MEDIA | `conferma_ritiro` silenzia duplicate key e ritorna `?ok=confermato` | `account/dashboard.php:49-53` |
| Q-03 | 🟡 MEDIA | Soglia arrotondamento versamento (2.0) non documentata | `includes/lib.php` |
| Q-04 | 🟡 MEDIA | `catch` vuoto in `sync_contact_utente()` senza log | `includes/lib.php` |
| Q-05 | 🟡 MEDIA | UNIQUE su `turni_programmati` troppo restrittivo (blocca stesso operatore in mesi diversi) | `install/schema.sql` |
| Q-06 | 🟡 MEDIA | FK `versamenti_confermati.giornata_id` senza `ON DELETE CASCADE` | `install/schema.sql` |
| Q-07 | 🟡 MEDIA | Valore ENUM fornitore macchine non sincronizzato con tabella `fornitori` | `install/schema.sql` |
| Q-08 | 🟡 MEDIA | Nessuna pulizia automatica dei token `password_reset` scaduti | `account/reset_password.php` |
| Q-09 | 🔵 BASSA | `refill_awp.n_macchina` senza FK verso `macchine` | `install/schema.sql` |
| Q-10 | 🔵 BASSA | Timezone Europe/Rome duplicata nel `<select>` di impostazioni | `account/admin/impostazioni.php` |
| Q-11 | 🔵 BASSA | `mail_chiusura_giornata` non usa `_mail_header_html` (nessun logo) | `includes/mail/mailer.php:198-202` |
| Q-12 | 🔵 BASSA | `_mail_send()` sopprime errori con `@mail(...)` senza log | `includes/mail/mailer.php:58` |
| Q-13 | 🔵 BASSA | Token reset: UPDATE + INSERT senza transazione | `includes/mail/mailer.php:67-73` |
| Q-14 | 🔵 BASSA | Handler `azione=prezzi` legacy ancora attivo ma non esposto nell'UI | `account/admin/impostazioni.php:94-100` |
| Q-15 | 🔵 BASSA | Export XLSX annuale ignora filtro operatore nel link | `cassa/annuale.php` |

### Fix Q-01 — riepilogo_giornata senza LIMIT
```php
// includes/lib.php:178-180 — rimuovere LIMIT 1
$ts = $pdo->prepare('SELECT * FROM turni WHERE giornata_id=? ORDER BY numero');
```

### Fix Q-02 — conferma_ritiro duplicate key
```php
try {
    $pdo->prepare('INSERT INTO versamenti_confermati ...')->execute([...]);
    audit(...);
    header('Location: dashboard.php?ok=confermato'); exit;
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // duplicate key
        header('Location: dashboard.php?err=gia_confermato'); exit;
    }
    throw $e;
}
```

### Fix Q-12 — log errori mail
```php
$ok = mail($to, $subject, $body, $headers);
if (!$ok) error_log('[mailer] mail() fallita per ' . $to);
```

### Fix Q-13 — transazione token reset
```php
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE password_reset SET usato=1 WHERE utente_id=? AND usato=0')->execute([$uid]);
    $pdo->prepare('INSERT INTO password_reset (utente_id, token, scade_il) VALUES (?,?,?)')->execute([$uid, $token, $scade]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## Manutenibilità

| ID | Priorità | Titolo | File |
|----|----------|--------|------|
| M-01 | 🟡 MEDIA | Chiavi brand (`brand_accent`, `logo_path`, `mail_from`) assenti dal seed `impostazioni` | `install/schema.sql` |
| M-02 | 🟡 MEDIA | `$compat[$i]` per `i > 2` produce undefined key (logica opaca) | `includes/lib.php:48-49` |
| M-03 | 🟡 MEDIA | COLLATE misto tra tabelle (rischio `Illegal mix of collations` in JOIN) | `install/schema.sql` |
| M-04 | 🔵 BASSA | Nessun indice su `audit_log(creato_il)` per query di paginazione | `install/schema.sql` |
| M-05 | 🔵 BASSA | Filename foto profilo enumerabile (`{uid}_{timestamp}.{ext}`) | `account/profilo.php` |
| M-06 | 🔵 BASSA | Directory `uploads/profili/` accessibile via HTTP senza autenticazione | filesystem |
| M-07 | 🔵 BASSA | Canvas profilo esporta sempre PNG anche su JPEG originale | `assets/js/profilo.js` |

### Fix M-01 — Seed impostazioni
```sql
INSERT IGNORE INTO impostazioni (chiave, valore) VALUES
  ('brand_accent', ''),
  ('logo_path',    ''),
  ('mail_from',    '');
```

### Fix M-03 — COLLATE uniforme
```sql
ALTER DATABASE nome_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Fix M-06 — Blocco accesso diretto foto profilo
```apache
# account/uploads/profili/.htaccess
Deny from all
```
Servire le immagini tramite un endpoint PHP autenticato.

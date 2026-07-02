<?php
/**
 * Funzioni centralizzate per l'invio di email HTML.
 * Richiede lib.php già caricato (base_url, arrotonda_versamento).
 */

function _mail_scheme_host(): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return [$scheme, $host];
}

function _mail_abs_url(string $path): string {
    [$s, $h] = _mail_scheme_host();
    return $s . '://' . $h . base_url($path);
}

function _mail_header_html(string $titolo, array $sett, array $cfg): string {
    $hE       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $accent   = ($sett['brand_accent'] ?? '') ?: '#111827';
    $nomeSala = $cfg['nome_sala'] ?? 'Cassa Sala';
    $logoPath = $sett['logo_path'] ?? null;
    $logoUrl  = $logoPath ? _mail_abs_url('account/uploads/sala/' . rawurlencode($logoPath)) : null;

    $h = '<div style="background:' . $accent . ';padding:24px 28px">';
    if ($logoUrl) {
        $h .= '<img src="' . $hE($logoUrl) . '" alt="' . $hE($nomeSala)
            . '" style="height:44px;width:auto;display:block;margin-bottom:10px">';
        $h .= '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">' . $hE($titolo) . '</h1>';
    } else {
        $h .= '<p style="margin:0 0 3px;color:rgba(255,255,255,.65);font-size:11px;letter-spacing:.08em;text-transform:uppercase">'
            . $hE($nomeSala) . '</p>';
        $h .= '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700">' . $hE($titolo) . '</h1>';
    }
    $h .= '</div>';
    return $h;
}

function _mail_footer_html(array $cfg): string {
    $nomeSala = $cfg['nome_sala'] ?? 'Cassa Sala';
    return '<div style="padding:14px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af">'
         . htmlspecialchars($nomeSala, ENT_QUOTES) . ' &middot; sistema gestione cassa VLT/AWP'
         . '</div>';
}

function _mail_wrap(string $hdr, string $content, string $footer): string {
    return '<!doctype html><html lang="it"><head><meta charset="utf-8">'
         . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
         . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif">'
         . '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb">'
         . $hdr . $content . $footer
         . '</div></body></html>';
}

function _mail_send(string $to, string $subject, string $body, array $sett): void {
    $from    = ($sett['mail_from'] ?? '') ?: 'noreply@cassasala.it';
    $headers = 'From: ' . $from . "\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    $ok = mail($to, $subject, $body, $headers);
    if (!$ok) error_log('[mailer] mail() fallita — to=' . $to . ' subject=' . $subject);
}

// ─── Email specifiche ────────────────────────────────────────────────────────

/**
 * Genera un token di reset password (1 ora) e spedisce l'email all'utente.
 */
function mail_reset_password(PDO $pdo, int $uid, string $email, array $sett, array $cfg): void {
    // Pulizia opportunistica dei token scaduti (Q-08)
    $pdo->exec('DELETE FROM password_reset WHERE scade_il < NOW()');

    $token = bin2hex(random_bytes(32));
    $scade = date('Y-m-d H:i:s', time() + 3600);

    // Transazione: invalida i vecchi token e inserisce il nuovo atomicamente (Q-13)
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE password_reset SET usato=1 WHERE utente_id=? AND usato=0')->execute([$uid]);
        $pdo->prepare('INSERT INTO password_reset (utente_id, token, scade_il) VALUES (?,?,?)')->execute([$uid, $token, $scade]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $hE       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $resetUrl = _mail_abs_url('account/reset_confirm.php') . '?token=' . urlencode($token);
    $nomeSala = $cfg['nome_sala'] ?? 'Cassa Sala';
    $accent   = ($sett['brand_accent'] ?? '') ?: '#111827';

    $hdr     = _mail_header_html('Reset password', $sett, $cfg);
    $content = '<div style="padding:28px 28px 20px">'
             . '<p style="margin:0 0 14px;font-size:15px;color:#111827">Ciao,</p>'
             . '<p style="margin:0 0 24px;font-size:14px;color:#374151;line-height:1.7">'
             . 'Hai richiesto il reset della password su <strong>' . $hE($nomeSala) . '</strong>.<br>'
             . 'Clicca il pulsante qui sotto per sceglierne una nuova. Il link &egrave; valido per <strong>1&nbsp;ora</strong>.'
             . '</p>'
             . '<div style="text-align:center;margin:28px 0">'
             . '<a href="' . $hE($resetUrl) . '" style="display:inline-block;background:' . $accent
             . ';color:#fff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 32px;border-radius:8px">'
             . 'Reimposta la password &rarr;</a>'
             . '</div>'
             . '<p style="margin:0 0 6px;font-size:12px;color:#9ca3af">Se il pulsante non funziona, copia questo link nel browser:</p>'
             . '<p style="margin:0 0 24px;font-size:11px;color:#6b7280;word-break:break-all">' . $hE($resetUrl) . '</p>'
             . '<div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:12px 14px">'
             . '<p style="margin:0;font-size:12px;color:#854d0e;line-height:1.5">'
             . '&#9888;&nbsp; Se non hai richiesto tu questo reset, ignora questa email. La tua password rimarr&agrave; invariata.'
             . '</p></div>'
             . '</div>';

    $body = _mail_wrap($hdr, $content, _mail_footer_html($cfg));
    _mail_send($email, "Reset password \xe2\x80\x94 $nomeSala", $body, $sett);
}

/**
 * Invia email di benvenuto con link per impostare la password (account appena creato).
 * Token valido per 24 ore.
 */
function mail_nuovo_account(PDO $pdo, int $uid, string $email, string $nome, array $sett, array $cfg): void {
    $token = bin2hex(random_bytes(32));
    $scade = date('Y-m-d H:i:s', time() + 86400);

    // Transazione: invalida eventuali token precedenti e inserisce il nuovo atomicamente (Q-13)
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE password_reset SET usato=1 WHERE utente_id=? AND usato=0')->execute([$uid]);
        $pdo->prepare('INSERT INTO password_reset (utente_id, token, scade_il) VALUES (?,?,?)')->execute([$uid, $token, $scade]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $hE       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $setUrl   = _mail_abs_url('account/reset_confirm.php') . '?token=' . urlencode($token);
    $nomeSala = $cfg['nome_sala'] ?? 'Cassa Sala';
    $accent   = ($sett['brand_accent'] ?? '') ?: '#111827';
    $saluto   = $nome ? ', ' . $hE($nome) : '';

    $hdr     = _mail_header_html('Benvenuto in ' . $nomeSala, $sett, $cfg);
    $content = '<div style="padding:28px 28px 20px">'
             . '<p style="margin:0 0 14px;font-size:15px;color:#111827">Ciao' . $saluto . ',</p>'
             . '<p style="margin:0 0 24px;font-size:14px;color:#374151;line-height:1.7">'
             . '&Egrave; stato creato un account per te su <strong>' . $hE($nomeSala) . '</strong>.<br>'
             . 'Clicca il pulsante qui sotto per impostare la tua password e accedere all&rsquo;app.'
             . ' Il link &egrave; valido per <strong>24&nbsp;ore</strong>.'
             . '</p>'
             . '<div style="text-align:center;margin:28px 0">'
             . '<a href="' . $hE($setUrl) . '" style="display:inline-block;background:' . $accent
             . ';color:#fff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 32px;border-radius:8px">'
             . 'Imposta la tua password &rarr;</a>'
             . '</div>'
             . '<p style="margin:0 0 6px;font-size:12px;color:#9ca3af">Se il pulsante non funziona, copia questo link nel browser:</p>'
             . '<p style="margin:0 0 24px;font-size:11px;color:#6b7280;word-break:break-all">' . $hE($setUrl) . '</p>'
             . '<div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:12px 14px">'
             . '<p style="margin:0;font-size:12px;color:#854d0e;line-height:1.5">'
             . '&#9888;&nbsp; Se non ti aspettavi questa email, contatta il responsabile della sala.'
             . '</p></div>'
             . '</div>';

    $body = _mail_wrap($hdr, $content, _mail_footer_html($cfg));
    _mail_send($email, "Benvenuto \xe2\x80\x94 imposta la tua password su $nomeSala", $body, $sett);
}

/**
 * Invia notifica di avvenuto cambio password all'utente.
 */
function mail_cambio_password(string $email, string $nome, string $ip, array $sett, array $cfg): void {
    $hE       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $nomeSala = $cfg['nome_sala'] ?? 'Cassa Sala';
    $ora      = date('d/m/Y \a\l\l\e H:i');
    $saluto   = $nome ? ', ' . $hE($nome) : '';

    $hdr     = _mail_header_html('Password modificata', $sett, $cfg);
    $content = '<div style="padding:28px 28px 20px">'
             . '<p style="margin:0 0 14px;font-size:15px;color:#111827">Ciao' . $saluto . ',</p>'
             . '<p style="margin:0 0 16px;font-size:14px;color:#374151;line-height:1.7">'
             . 'La tua password su <strong>' . $hE($nomeSala) . '</strong> &egrave; stata modificata con successo'
             . ' il <strong>' . $ora . '</strong>.'
             . '</p>'
             . '<div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:12px 14px">'
             . '<p style="margin:0;font-size:12px;color:#854d0e;line-height:1.5">'
             . '&#9888;&nbsp; Se non sei stato tu, contatta immediatamente il responsabile della sala.'
             . ($ip ? '<br><span style="color:#92400e;font-size:11px">IP: ' . $hE($ip) . '</span>' : '')
             . '</p></div>'
             . '</div>';

    $body = _mail_wrap($hdr, $content, _mail_footer_html($cfg));
    _mail_send($email, "Password modificata \xe2\x80\x94 $nomeSala", $body, $sett);
}

/**
 * Invia il riepilogo versamento ai revisori in copia.
 *
 * @param array  $revs      Righe (nome, email) dei revisori da notificare
 * @param array  $tot       Associativo: scass, bancomat, ticket
 * @param float  $mailVers  Versamento netto già arrotondato
 * @param string $data      Data giornata (Y-m-d)
 * @param string $nomeOp    Nome operatore che ha chiuso
 * @param string $appUrl    URL assoluto della dashboard
 */
function mail_chiusura_giornata(
    array $revs, array $tot, float $mailVers,
    string $data, string $nomeOp, string $appUrl,
    array $sett, array $cfg
): void {
    if (empty($revs)) return;

    $hE        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $nomeSala  = $cfg['nome_sala'] ?? 'Cassa Sala';
    $dataFmt   = date('d/m/Y', strtotime($data));
    $chiusaOra = date('H:i');

    $hdr = _mail_header_html('Riepilogo versamento', $sett, $cfg);

    $content = '<div style="padding:24px">'
             . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
             . '<tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Data</td>'
             . '<td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:600">' . $dataFmt . '</td></tr>'
             . '<tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Scassettamenti</td>'
             . '<td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right">' . number_format((float)$tot['scass'], 2, ',', '.') . ' &euro;</td></tr>'
             . '<tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Bancomat</td>'
             . '<td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;color:#dc2626">&minus; ' . number_format((float)$tot['bancomat'], 2, ',', '.') . ' &euro;</td></tr>'
             . '<tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6;color:#6b7280">Ticket pagati</td>'
             . '<td style="padding:8px 0;border-bottom:1px solid #f3f4f6;text-align:right;color:#dc2626">&minus; ' . number_format((float)$tot['ticket'], 2, ',', '.') . ' &euro;</td></tr>'
             . '<tr><td style="padding:14px 0 0;font-weight:700;font-size:16px">Versamento netto</td>'
             . '<td style="padding:14px 0 0;text-align:right;font-weight:700;font-size:20px;color:#059669">' . number_format($mailVers, 2, ',', '.') . ' &euro;</td></tr>'
             . '</table>'
             . '<p style="margin:20px 0 4px;font-size:12px;color:#6b7280">Chiusa da <strong>' . $hE($nomeOp) . '</strong> oggi alle ' . $chiusaOra . '.</p>'
             . '<p style="margin:0;font-size:12px;color:#6b7280">Accedi all&rsquo;app per confermare il ritiro del versamento.</p>'
             . '<a href="' . $hE($appUrl) . '" style="display:inline-block;margin-top:18px;background:' . $accent
             . ';color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600">Vai alla dashboard &rarr;</a>'
             . '</div>';

    $subject = "Versamento del $dataFmt \xe2\x80\x94 $nomeSala";
    $body    = _mail_wrap($hdr, $content, _mail_footer_html($cfg));

    foreach ($revs as $rev) {
        _mail_send($rev['email'], $subject, $body, $sett);
    }
}

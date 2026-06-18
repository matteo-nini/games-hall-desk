<?php
function top_menu(array $user): void {
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $items = ['giornaliero.php'=>'Giornaliero','settimanale.php'=>'Settimanale','mensile.php'=>'Mensile','awp.php'=>'AWP','ticket.php'=>'Ticket Assistenza','prestiti.php'=>'Prestiti','onboarding.php'=>'Guida'];
    if (($user['ruolo'] ?? '') === 'responsabile') {
        $items += ['macchine.php'=>'Macchine','utenti.php'=>'Utenti','audit.php'=>'Audit'];
    }
    echo '<nav class="menu">';
    foreach ($items as $f => $l) {
        $cls = ($f === $cur) ? ' class="active"' : '';
        echo '<a'.$cls.' href="'.$f.'">'.htmlspecialchars($l).'</a>';
    }
    $nome = htmlspecialchars($user['nome'] ?: $user['username']);
    echo '<a class="right" href="logout.php">Esci · '.$nome.'</a>';
    echo '</nav>';
}

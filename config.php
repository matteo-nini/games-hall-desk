<?php
// =====================================================================
//  Configurazione — modifica questi valori per il tuo ambiente.
//  In LOCALE (XAMPP/Laragon) tipicamente: host 127.0.0.1, user root, pass ''.
//  Su SITEGROUND: usa i dati del DB creato dal pannello (host, nome, user, pass).
//  Suggerimento: tieni due copie di questo file (locale/produzione) fuori dal
//  controllo versione, oppure usa variabili d'ambiente.
// =====================================================================

return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'cassa_sala',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    // Nome sala mostrato nell'intestazione
    'nome_sala' => 'Sala VLT',
    // Numero di righe refill AWP mostrate per turno
    'refill_rows' => 10,
    // Tolleranza (euro) entro cui il totale in cassa è considerato = fondo cassa
    // (gli arrotondamenti dei ticket a multipli di 5 sono normali). Oltre: da verificare.
    'tolleranza' => 5.0,
];

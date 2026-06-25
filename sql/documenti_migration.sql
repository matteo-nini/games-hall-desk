-- Migration: modulo Documenti
-- Eseguire una volta sola sul database dell'applicazione.

CREATE TABLE IF NOT EXISTS documenti (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(120) NOT NULL,
    descrizione VARCHAR(255) DEFAULT NULL,
    filename    VARCHAR(120) NOT NULL,
    mime        VARCHAR(80)  NOT NULL DEFAULT 'application/octet-stream',
    ordine      INT          NOT NULL DEFAULT 0,
    visibile    TINYINT(1)   NOT NULL DEFAULT 1,
    caricato_da INT          DEFAULT NULL,
    caricato_il DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caricato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

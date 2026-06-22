-- =====================================================================
--  005 — Profilo utente (foto) + tabella impostazioni generali
--  Eseguire dopo 004. MySQL 5.7 compatible.
-- =====================================================================

-- Foto profilo per gli utenti (percorso relativo file)
ALTER TABLE utenti ADD COLUMN foto VARCHAR(255) DEFAULT NULL;

-- Impostazioni generali chiave/valore
CREATE TABLE IF NOT EXISTS impostazioni (
  chiave        VARCHAR(60)  NOT NULL PRIMARY KEY,
  valore        VARCHAR(255) NOT NULL DEFAULT '',
  aggiornato_il DATETIME     DEFAULT NULL ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO impostazioni (chiave, valore) VALUES
  ('turno_mattino_inizio',      '13:00'),
  ('turno_mattino_fine',        '19:00'),
  ('turno_sera_inizio',         '19:00'),
  ('turno_sera_fine',           '01:00'),
  ('operatori_modifica_turni',  '1');

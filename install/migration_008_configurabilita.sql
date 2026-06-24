-- =====================================================================
--  Migration 008 — Configurabilità: fornitori, turni, timezone
--  Applicare su installazioni esistenti già in produzione.
--  Sicuro da eseguire più volte (CREATE IF NOT EXISTS / INSERT IGNORE).
-- =====================================================================

-- Tabella fornitori (sostituisce l'ENUM hardcoded)
CREATE TABLE IF NOT EXISTS fornitori (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  nome   VARCHAR(50)  NOT NULL UNIQUE,
  ordine INT          NOT NULL DEFAULT 0,
  attiva TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO fornitori (nome, ordine) VALUES
  ('NOVO',     1),
  ('INSPIRED', 2),
  ('SPIELO',   3);

-- Nuove chiavi impostazioni per turni configurabili
INSERT IGNORE INTO impostazioni (chiave, valore) VALUES
  ('num_turni',    '2'),
  ('turno_1_nome', 'Mattino'),
  ('turno_2_nome', 'Sera'),
  ('turno_3_nome', 'Notte'),
  ('turno_1_inizio', '13:00'),
  ('turno_1_fine',   '19:00'),
  ('turno_2_inizio', '19:00'),
  ('turno_2_fine',   '01:00'),
  ('turno_3_inizio', '01:00'),
  ('turno_3_fine',   '09:00'),
  ('turno_edit_libero', '1');

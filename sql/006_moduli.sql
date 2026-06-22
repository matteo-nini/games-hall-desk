-- Moduli: abilitazione/disabilitazione sezioni Assistenze e Prestiti
-- Requires: 005_profilo_impostazioni.sql already applied

INSERT IGNORE INTO impostazioni (chiave, valore) VALUES
  ('modulo_assistenze', '1'),
  ('modulo_prestiti',   '1');

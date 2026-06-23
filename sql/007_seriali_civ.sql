-- Aggiunge campi seriale e CIV alla tabella macchine
ALTER TABLE macchine
  ADD COLUMN IF NOT EXISTS seriale VARCHAR(100) NULL AFTER fornitore,
  ADD COLUMN IF NOT EXISTS civ     VARCHAR(100) NULL AFTER seriale;

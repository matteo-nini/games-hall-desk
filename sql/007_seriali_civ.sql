-- Aggiunge campi seriale e CIV alla tabella macchine
ALTER TABLE macchine
  ADD COLUMN seriale VARCHAR(100) NULL AFTER fornitore,
  ADD COLUMN civ     VARCHAR(100) NULL AFTER seriale;

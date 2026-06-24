-- Import prestiti da Excel
-- Generato il: 2026-06-25

START TRANSACTION;

-- Persone
INSERT INTO prestiti_persone (nome, saldo_iniziale, note) VALUES
  ('Franco', 150, NULL),
  ('Alessio', 250, '150 messi dal bar'),
  ('Pierpaolo', 0, NULL),
  ('Luiz', 300, NULL),
  ('Andrea', 0, NULL),
  ('Gabriele', 0, NULL),
  ('Leonardo', 0, NULL),
  ('Matteo', 0, NULL),
  ('Juan', 0, NULL),
  ('Luca', 0, NULL),
  ('Bionda', 0, NULL);

-- Variabili persona_id
SET @franco = (SELECT id FROM prestiti_persone WHERE nome = 'Franco');
SET @alessio = (SELECT id FROM prestiti_persone WHERE nome = 'Alessio');
SET @pierpaolo = (SELECT id FROM prestiti_persone WHERE nome = 'Pierpaolo');
SET @luiz = (SELECT id FROM prestiti_persone WHERE nome = 'Luiz');
SET @andrea = (SELECT id FROM prestiti_persone WHERE nome = 'Andrea');
SET @gabriele = (SELECT id FROM prestiti_persone WHERE nome = 'Gabriele');
SET @leonardo = (SELECT id FROM prestiti_persone WHERE nome = 'Leonardo');
SET @matteo = (SELECT id FROM prestiti_persone WHERE nome = 'Matteo');
SET @juan = (SELECT id FROM prestiti_persone WHERE nome = 'Juan');
SET @luca = (SELECT id FROM prestiti_persone WHERE nome = 'Luca');
SET @bionda = (SELECT id FROM prestiti_persone WHERE nome = 'Bionda');

-- Movimenti (93 righe)
INSERT INTO prestiti_movimenti (data, persona_id, tipo, quantita, note, creato_da, creato_il) VALUES
  ('2026-01-05', @franco, 'rientro', 100, '50 dare', NULL, '2026-01-05 12:00:00'),
  ('2026-01-09', @franco, 'prestito', 100, '159 dare', NULL, '2026-01-09 12:00:00'),
  ('2026-01-12', @franco, 'prestito', 100, '250 dare', NULL, '2026-01-12 12:00:00'),
  ('2026-01-14', @franco, 'rientro', 150, '100 dare', NULL, '2026-01-14 12:00:00'),
  ('2026-01-14', @franco, 'prestito', 150, '250 dare', NULL, '2026-01-14 12:00:00'),
  ('2026-01-15', @alessio, 'rientro', 50, NULL, NULL, '2026-01-15 12:00:00'),
  ('2026-01-17', @franco, 'rientro', 50, '200 dare', NULL, '2026-01-17 12:00:00'),
  ('2026-01-17', @pierpaolo, 'prestito', 200, NULL, NULL, '2026-01-17 12:00:00'),
  ('2026-01-23', @franco, 'prestito', 100, '300 dare', NULL, '2026-01-23 12:00:00'),
  ('2026-01-24', @franco, 'rientro', 100, '200 dare', NULL, '2026-01-24 12:00:00'),
  ('2026-01-29', @franco, 'prestito', 100, '300 dare', NULL, '2026-01-29 12:00:00'),
  ('2026-02-03', @franco, 'rientro', 150, '150 dare', NULL, '2026-02-03 12:00:00'),
  ('2026-02-09', @franco, 'rientro', 250, 'fuori 150', NULL, '2026-02-09 12:00:00'),
  ('2026-02-10', @franco, 'prestito', 50, NULL, NULL, '2026-02-10 12:00:00'),
  ('2026-02-13', @franco, 'prestito', 50, '250 DARE', NULL, '2026-02-13 12:00:00'),
  ('2026-02-16', @franco, 'rientro', 50, '200 dare', NULL, '2026-02-16 12:00:00'),
  ('2026-02-19', @franco, 'prestito', 50, '250 dare', NULL, '2026-02-19 12:00:00'),
  ('2026-02-19', @pierpaolo, 'prestito', 1000, '1200 dare', NULL, '2026-02-19 12:00:00'),
  ('2026-02-26', @franco, 'prestito', 50, '300 DARE', NULL, '2026-02-26 12:00:00'),
  ('2026-03-06', @franco, 'rientro', 250, '50 dare', NULL, '2026-03-06 12:00:00'),
  ('2026-03-07', @franco, 'prestito', 200, '250 dare', NULL, '2026-03-07 12:00:00'),
  ('2026-03-09', @franco, 'rientro', 250, 'pari', NULL, '2026-03-09 12:00:00'),
  ('2026-03-10', @luiz, 'prestito', 50, NULL, NULL, '2026-03-10 12:00:00'),
  ('2026-03-11', @gabriele, 'prestito', 20, NULL, NULL, '2026-03-11 12:00:00'),
  ('2026-03-12', @luiz, 'rientro', 50, NULL, NULL, '2026-03-12 12:00:00'),
  ('2026-03-12', @gabriele, 'rientro', 40, NULL, NULL, '2026-03-12 12:00:00'),
  ('2026-03-17', @leonardo, 'prestito', 100, NULL, NULL, '2026-03-17 12:00:00'),
  ('2026-03-17', @leonardo, 'rientro', 100, NULL, NULL, '2026-03-17 12:00:00'),
  ('2026-03-20', @franco, 'prestito', 250, NULL, NULL, '2026-03-20 12:00:00'),
  ('2026-03-20', @franco, 'rientro', 100, NULL, NULL, '2026-03-20 12:00:00'),
  ('2026-03-23', @franco, 'prestito', 100, NULL, NULL, '2026-03-23 12:00:00'),
  ('2026-03-23', @franco, 'rientro', 150, 'fuori 100', NULL, '2026-03-23 12:00:00'),
  ('2026-03-24', @luiz, 'prestito', 50, NULL, NULL, '2026-03-24 12:00:00'),
  ('2026-03-26', @luiz, 'rientro', 50, NULL, NULL, '2026-03-26 12:00:00'),
  ('2026-03-26', @franco, 'prestito', 150, 'fuori 250', NULL, '2026-03-26 12:00:00'),
  ('2026-03-28', @gabriele, 'prestito', 50, NULL, NULL, '2026-03-28 12:00:00'),
  ('2026-03-31', @franco, 'prestito', 50, 'fuori 300', NULL, '2026-03-31 12:00:00'),
  ('2026-03-31', @andrea, 'prestito', 300, NULL, NULL, '2026-03-31 12:00:00'),
  ('2026-04-01', @franco, 'prestito', 100, 'fuori 400', NULL, '2026-04-01 12:00:00'),
  ('2026-04-01', @franco, 'rientro', 150, 'fuori 250', NULL, '2026-04-01 12:00:00'),
  ('2026-04-10', @franco, 'prestito', 50, 'fuori 300', NULL, '2026-04-10 12:00:00'),
  ('2026-04-14', @luiz, 'prestito', 50, NULL, NULL, '2026-04-14 12:00:00'),
  ('2026-04-17', @franco, 'rientro', 100, 'fuori 200', NULL, '2026-04-17 12:00:00'),
  ('2026-04-19', @juan, 'prestito', 10, NULL, NULL, '2026-04-19 12:00:00'),
  ('2026-04-20', @franco, 'prestito', 100, 'fuori 300', NULL, '2026-04-20 12:00:00'),
  ('2026-04-23', @franco, 'prestito', 50, 'fuori 350', NULL, '2026-04-23 12:00:00'),
  ('2026-04-27', @matteo, 'prestito', 20, NULL, NULL, '2026-04-27 12:00:00'),
  ('2026-04-28', @juan, 'rientro', 10, NULL, NULL, '2026-04-28 12:00:00'),
  ('2026-04-28', @matteo, 'rientro', 20, NULL, NULL, '2026-04-28 12:00:00'),
  ('2026-05-04', @franco, 'rientro', 50, 'fuori 300', NULL, '2026-05-04 12:00:00'),
  ('2026-05-05', @luiz, 'rientro', 50, NULL, NULL, '2026-05-05 12:00:00'),
  ('2026-05-05', @luiz, 'prestito', 50, NULL, NULL, '2026-05-05 12:00:00'),
  ('2026-05-05', @pierpaolo, 'prestito', 15, NULL, NULL, '2026-05-05 12:00:00'),
  ('2026-05-06', @luiz, 'rientro', 50, NULL, NULL, '2026-05-06 12:00:00'),
  ('2026-05-08', @andrea, 'prestito', 420, NULL, NULL, '2026-05-08 12:00:00'),
  ('2026-05-08', @franco, 'prestito', 100, 'fuori 400', NULL, '2026-05-08 12:00:00'),
  ('2026-05-11', @franco, 'rientro', 100, 'fuori 50', NULL, '2026-05-11 12:00:00'),
  ('2026-05-11', @gabriele, 'prestito', 100, NULL, NULL, '2026-05-11 12:00:00'),
  ('2026-05-12', @franco, 'prestito', 100, 'fuori 150', NULL, '2026-05-12 12:00:00'),
  ('2026-05-12', @luiz, 'prestito', 50, NULL, NULL, '2026-05-12 12:00:00'),
  ('2026-05-13', @franco, 'prestito', 50, 'fuori 200', NULL, '2026-05-13 12:00:00'),
  ('2026-05-13', @luiz, 'rientro', 50, NULL, NULL, '2026-05-13 12:00:00'),
  ('2026-05-14', @luiz, 'prestito', 50, NULL, NULL, '2026-05-14 12:00:00'),
  ('2026-05-14', @franco, 'rientro', 250, NULL, NULL, '2026-05-14 12:00:00'),
  ('2026-05-14', @pierpaolo, 'prestito', 120, NULL, NULL, '2026-05-14 12:00:00'),
  ('2026-05-15', @pierpaolo, 'rientro', 135, NULL, NULL, '2026-05-15 12:00:00'),
  ('2026-05-15', @franco, 'prestito', 150, 'fuori 350', NULL, '2026-05-15 12:00:00'),
  ('2026-05-15', @franco, 'rientro', 100, 'fuori 250', NULL, '2026-05-15 12:00:00'),
  ('2026-05-16', @franco, 'rientro', 50, 'fuori 200', NULL, '2026-05-16 12:00:00'),
  ('2026-05-17', @luca, 'prestito', 100, NULL, NULL, '2026-05-17 12:00:00'),
  ('2026-05-18', @luiz, 'prestito', 50, NULL, NULL, '2026-05-18 12:00:00'),
  ('2026-05-18', @luca, 'rientro', 100, NULL, NULL, '2026-05-18 12:00:00'),
  ('2026-05-19', @franco, 'prestito', 300, 'fuori 500', NULL, '2026-05-19 12:00:00'),
  ('2026-05-19', @luiz, 'rientro', 50, NULL, NULL, '2026-05-19 12:00:00'),
  ('2026-05-19', @matteo, 'prestito', 10, NULL, NULL, '2026-05-19 12:00:00'),
  ('2026-05-20', @matteo, 'rientro', 10, NULL, NULL, '2026-05-20 12:00:00'),
  ('2026-05-20', @luiz, 'prestito', 50, NULL, NULL, '2026-05-20 12:00:00'),
  ('2026-05-20', @franco, 'rientro', 200, NULL, NULL, '2026-05-20 12:00:00'),
  ('2026-05-22', @franco, 'prestito', 100, NULL, NULL, '2026-05-22 12:00:00'),
  ('2026-05-22', @franco, 'rientro', 150, 'PARI', NULL, '2026-05-22 12:00:00'),
  ('2026-05-23', @franco, 'prestito', 250, NULL, NULL, '2026-05-23 12:00:00'),
  ('2026-05-23', @bionda, 'prestito', 150, NULL, NULL, '2026-05-23 12:00:00'),
  ('2026-05-25', @franco, 'rientro', 50, NULL, NULL, '2026-05-25 12:00:00'),
  ('2026-05-26', @franco, 'prestito', 50, NULL, NULL, '2026-05-26 12:00:00'),
  ('2026-05-27', @bionda, 'rientro', 150, 'PARI', NULL, '2026-05-27 12:00:00'),
  ('2026-06-01', @franco, 'rientro', 150, NULL, NULL, '2026-06-01 12:00:00'),
  ('2026-06-05', @franco, 'prestito', 100, NULL, NULL, '2026-06-05 12:00:00'),
  ('2026-06-05', @franco, 'prestito', 150, NULL, NULL, '2026-06-05 12:00:00'),
  ('2026-06-05', @franco, 'rientro', 100, NULL, NULL, '2026-06-05 12:00:00'),
  ('2026-06-08', @gabriele, 'prestito', 100, NULL, NULL, '2026-06-08 12:00:00'),
  ('2026-06-10', @franco, 'rientro', 350, 'IN CREDITO DI 100', NULL, '2026-06-10 12:00:00'),
  ('2026-06-15', @franco, 'prestito', 100, NULL, NULL, '2026-06-15 12:00:00'),
  ('2026-06-15', @franco, 'prestito', 100, NULL, NULL, '2026-06-15 12:00:00');

COMMIT;

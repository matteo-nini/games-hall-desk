-- Registro prestiti e rientri
CREATE TABLE IF NOT EXISTS prestiti_persone (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(100) NOT NULL UNIQUE,
  saldo_iniziale DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- saldo pregresso (colonna "STATO PASSATO" nel foglio Excel)
  note           VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prestiti_movimenti (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  data       DATE         NOT NULL,
  persona_id INT          NOT NULL,
  tipo       ENUM('prestito','rientro') NOT NULL,
  quantita   DECIMAL(10,2) NOT NULL,
  note       VARCHAR(255) DEFAULT NULL,
  creato_da  INT          DEFAULT NULL,
  creato_il  DATETIME     NOT NULL DEFAULT NOW(),
  FOREIGN KEY (persona_id) REFERENCES prestiti_persone(id) ON DELETE CASCADE,
  FOREIGN KEY (creato_da)  REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Persone precaricate dal foglio Excel (saldi iniziali già presenti)
INSERT IGNORE INTO prestiti_persone (nome, saldo_iniziale) VALUES
  ('Franco',     150.00),
  ('Alessio',    250.00),
  ('Pierpaolo',    0.00),
  ('Luiz',       300.00),
  ('Andrea',       0.00),
  ('Gabriele',     0.00),
  ('Leonardo',     0.00),
  ('Matteo',       0.00),
  ('Juan',         0.00),
  ('Luca',         0.00),
  ('Bionda',       0.00);

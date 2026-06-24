-- =====================================================================
--  004 — Turni programmati, prezzi turni, timestamp inizio turno
--  Eseguire in ordine. MySQL 8.x richiesto per ADD COLUMN IF NOT EXISTS.
-- =====================================================================

-- Pianificazione mensile: chi lavora quale turno in quale giorno
CREATE TABLE IF NOT EXISTS turni_programmati (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  data         DATE    NOT NULL,
  numero       TINYINT NOT NULL COMMENT '1=mattino 13-19  2=sera 19-01',
  operatore_id INT     NOT NULL,
  creato_da    INT     DEFAULT NULL,
  creato_il    DATETIME NOT NULL DEFAULT NOW(),
  UNIQUE  KEY uq_data_numero   (data, numero),
  KEY         idx_op_data      (operatore_id, data),
  FOREIGN KEY (operatore_id) REFERENCES utenti(id) ON DELETE CASCADE,
  FOREIGN KEY (creato_da)    REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prezzi turni (configurabili dal responsabile)
CREATE TABLE IF NOT EXISTS prezzi_turni (
  nome    ENUM('mattino','sera') NOT NULL PRIMARY KEY,
  prezzo  DECIMAL(8,2)          NOT NULL DEFAULT 0.00,
  aggiornato_il DATETIME        DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO prezzi_turni (nome, prezzo) VALUES
  ('mattino', 60.00),
  ('sera',    70.00);

-- Timestamp di inizio turno reale (per rilevare turni sovrapposti)
ALTER TABLE turni ADD COLUMN DATETIME DEFAULT NULL;

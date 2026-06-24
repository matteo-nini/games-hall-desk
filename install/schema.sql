-- =====================================================================
--  Schema database completo — installa in un unico passaggio.
--  MySQL 8 / MariaDB 10.6+ — InnoDB — utf8mb4
--  Generato il: 2026-06-24
-- =====================================================================
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ---------- Utenti ----------
CREATE TABLE IF NOT EXISTS utenti (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nome          VARCHAR(100) DEFAULT NULL,
  ruolo         ENUM('operatore','responsabile') NOT NULL DEFAULT 'operatore',
  attivo        TINYINT(1)   NOT NULL DEFAULT 1,
  creato_il     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  foto          VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Macchine ----------
CREATE TABLE IF NOT EXISTS macchine (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  codice    VARCHAR(40)  NOT NULL UNIQUE,
  tipo      ENUM('VLT','AWP') NOT NULL DEFAULT 'VLT',
  fornitore ENUM('NOVO','INSPIRED','SPIELO','ALTRO') NOT NULL,
  seriale   VARCHAR(100) DEFAULT NULL,
  civ       VARCHAR(100) DEFAULT NULL,
  ordine    INT          NOT NULL DEFAULT 0,
  attiva    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Giornate ----------
CREATE TABLE IF NOT EXISTS giornate (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  data      DATE NOT NULL UNIQUE,
  stato     ENUM('aperta','chiusa') NOT NULL DEFAULT 'aperta',
  chiusa_da INT  DEFAULT NULL,
  chiusa_il TIMESTAMP DEFAULT NULL,
  note      VARCHAR(255) DEFAULT NULL,
  CONSTRAINT fk_giornate_utente FOREIGN KEY (chiusa_da) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Turni ----------
CREATE TABLE IF NOT EXISTS turni (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  giornata_id  INT          NOT NULL,
  numero       TINYINT      NOT NULL,
  operatore_id INT          DEFAULT NULL,
  fondo_cassa  DECIMAL(10,2) NOT NULL DEFAULT 0,
  monete       DECIMAL(10,2) NOT NULL DEFAULT 0,
  bancomat     DECIMAL(10,2) NOT NULL DEFAULT 0,
  differenze   DECIMAL(10,2) NOT NULL DEFAULT 0,
  ii_cassa     DECIMAL(10,2) NOT NULL DEFAULT 0,
  rientri      DECIMAL(10,2) NOT NULL DEFAULT 0,
  note         TEXT          DEFAULT NULL,
  iniziato_il  DATETIME      DEFAULT NULL,
  UNIQUE KEY uq_turno (giornata_id, numero),
  CONSTRAINT fk_turni_giornata  FOREIGN KEY (giornata_id)  REFERENCES giornate(id) ON DELETE CASCADE,
  CONSTRAINT fk_turni_operatore FOREIGN KEY (operatore_id) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Conteggio contanti ----------
CREATE TABLE IF NOT EXISTS contanti (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  turno_id INT NOT NULL,
  taglio   INT NOT NULL,
  pezzi    INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_contanti (turno_id, taglio),
  CONSTRAINT fk_contanti_turno FOREIGN KEY (turno_id) REFERENCES turni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Scassettamenti VLT ----------
CREATE TABLE IF NOT EXISTS scassettamenti (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  turno_id    INT           NOT NULL,
  macchina_id INT           NOT NULL,
  importo     DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_scass (turno_id, macchina_id),
  CONSTRAINT fk_scass_turno    FOREIGN KEY (turno_id)    REFERENCES turni(id)    ON DELETE CASCADE,
  CONSTRAINT fk_scass_macchina FOREIGN KEY (macchina_id) REFERENCES macchine(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Refill AWP ----------
CREATE TABLE IF NOT EXISTS refill_awp (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  turno_id   INT           NOT NULL,
  n_macchina VARCHAR(20)   DEFAULT NULL,
  euro       DECIMAL(10,2) NOT NULL DEFAULT 0,
  ora        TIME          DEFAULT NULL,
  CONSTRAINT fk_refill_turno FOREIGN KEY (turno_id) REFERENCES turni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Ticket vincite ----------
CREATE TABLE IF NOT EXISTS ticket (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  turno_id  INT           NOT NULL,
  fornitore ENUM('NOVO','INSPIRED','SPIELO') NOT NULL,
  importo   DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_ticket (turno_id, fornitore),
  CONSTRAINT fk_ticket_turno FOREIGN KEY (turno_id) REFERENCES turni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Bet/Win ----------
CREATE TABLE IF NOT EXISTS snai_betwin (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  data      DATE          NOT NULL,
  fornitore ENUM('NOVO','INSPIRED','SPIELO') NOT NULL,
  giocato   DECIMAL(12,2) NOT NULL DEFAULT 0,
  pagato    DECIMAL(12,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_betwin (data, fornitore)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Audit log ----------
CREATE TABLE IF NOT EXISTS audit_log (
  id        BIGINT AUTO_INCREMENT PRIMARY KEY,
  utente_id INT          DEFAULT NULL,
  azione    VARCHAR(50)  NOT NULL,
  entita    VARCHAR(50)  DEFAULT NULL,
  entita_id INT          DEFAULT NULL,
  dettaglio TEXT         DEFAULT NULL,
  ip        VARCHAR(45)  DEFAULT NULL,
  creato_il TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_utente FOREIGN KEY (utente_id) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Ticket assistenza ----------
CREATE TABLE IF NOT EXISTS ticket_assistenza (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  data_apertura DATE         NOT NULL,
  macchina      VARCHAR(50)  NOT NULL,
  problema      TEXT         NOT NULL,
  id_ticket     VARCHAR(50)  DEFAULT NULL,
  risoluzione   VARCHAR(500) DEFAULT NULL,
  data_chiusura DATE         DEFAULT NULL,
  stato         ENUM('aperto','risolto') NOT NULL DEFAULT 'aperto',
  creato_da     INT          DEFAULT NULL,
  creato_il     DATETIME     NOT NULL DEFAULT NOW(),
  FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Prestiti ----------
CREATE TABLE IF NOT EXISTS prestiti_persone (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(100)  NOT NULL UNIQUE,
  saldo_iniziale DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  note           VARCHAR(255)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prestiti_movimenti (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  data       DATE          NOT NULL,
  persona_id INT           NOT NULL,
  tipo       ENUM('prestito','rientro') NOT NULL,
  quantita   DECIMAL(10,2) NOT NULL,
  note       VARCHAR(255)  DEFAULT NULL,
  creato_da  INT           DEFAULT NULL,
  creato_il  DATETIME      NOT NULL DEFAULT NOW(),
  FOREIGN KEY (persona_id) REFERENCES prestiti_persone(id) ON DELETE CASCADE,
  FOREIGN KEY (creato_da)  REFERENCES utenti(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Turni programmati ----------
CREATE TABLE IF NOT EXISTS turni_programmati (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  data         DATE     NOT NULL,
  numero       TINYINT  NOT NULL,
  operatore_id INT      NOT NULL,
  creato_da    INT      DEFAULT NULL,
  creato_il    DATETIME NOT NULL DEFAULT NOW(),
  UNIQUE KEY uq_data_numero (data, numero),
  KEY idx_op_data (operatore_id, data),
  FOREIGN KEY (operatore_id) REFERENCES utenti(id) ON DELETE CASCADE,
  FOREIGN KEY (creato_da)    REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Prezzi turni ----------
CREATE TABLE IF NOT EXISTS prezzi_turni (
  nome          ENUM('mattino','sera') NOT NULL PRIMARY KEY,
  prezzo        DECIMAL(8,2)           NOT NULL DEFAULT 0.00,
  aggiornato_il DATETIME               DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO prezzi_turni (nome, prezzo) VALUES
  ('mattino', 0.00),
  ('sera',    0.00);

-- ---------- Login attempts (rate limiting) ----------
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45) NOT NULL,
  attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Impostazioni ----------
CREATE TABLE IF NOT EXISTS impostazioni (
  chiave        VARCHAR(60)  NOT NULL PRIMARY KEY,
  valore        VARCHAR(255) NOT NULL DEFAULT '',
  aggiornato_il DATETIME     DEFAULT NULL ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO impostazioni (chiave, valore) VALUES
  ('turno_mattino_inizio',       '13:00'),
  ('turno_mattino_fine',         '19:00'),
  ('turno_sera_inizio',          '19:00'),
  ('turno_sera_fine',            '01:00'),
  ('operatori_modifica_turni',   '1'),
  ('turno_edit_libero',          '1'),
  ('modulo_assistenze',          '1'),
  ('modulo_prestiti',            '1');

SET foreign_key_checks = 1;

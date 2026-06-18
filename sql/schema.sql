-- =====================================================================
--  CASSA SALA VLT/AWP — schema database (MySQL 5.7+ / 8.x, InnoDB)
--  Importabile da phpMyAdmin (locale o SiteGround) o da riga di comando.
--  Crea il database prima, poi importa questo file dentro di esso.
-- =====================================================================
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ---------- Utenti ----------
CREATE TABLE IF NOT EXISTS utenti (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nome          VARCHAR(100) NULL,
  ruolo         ENUM('operatore','responsabile') NOT NULL DEFAULT 'operatore',
  attivo        TINYINT(1)   NOT NULL DEFAULT 1,
  creato_il     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Macchine (parco VLT; le AWP del refill si inseriscono a mano) ----------
CREATE TABLE IF NOT EXISTS macchine (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  codice     VARCHAR(40) NOT NULL UNIQUE,
  tipo       ENUM('VLT','AWP') NOT NULL DEFAULT 'VLT',
  fornitore  ENUM('NOVO','INSPIRED','SPIELO','ALTRO') NOT NULL,
  ordine     INT NOT NULL DEFAULT 0,
  attiva     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Giornate ----------
CREATE TABLE IF NOT EXISTS giornate (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  data       DATE NOT NULL UNIQUE,
  stato      ENUM('aperta','chiusa') NOT NULL DEFAULT 'aperta',
  chiusa_da  INT NULL,
  chiusa_il  TIMESTAMP NULL,
  note       VARCHAR(255) NULL,
  CONSTRAINT fk_giornate_utente FOREIGN KEY (chiusa_da) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Turni (1 = mattino, 2 = sera) ----------
CREATE TABLE IF NOT EXISTS turni (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  giornata_id  INT NOT NULL,
  numero       TINYINT NOT NULL,
  operatore_id INT NULL,
  fondo_cassa  DECIMAL(10,2) NOT NULL DEFAULT 0,
  monete       DECIMAL(10,2) NOT NULL DEFAULT 0,
  bancomat     DECIMAL(10,2) NOT NULL DEFAULT 0,
  differenze   DECIMAL(10,2) NOT NULL DEFAULT 0,
  ii_cassa     DECIMAL(10,2) NOT NULL DEFAULT 0,
  rientri      DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_turno (giornata_id, numero),
  CONSTRAINT fk_turni_giornata  FOREIGN KEY (giornata_id) REFERENCES giornate(id) ON DELETE CASCADE,
  CONSTRAINT fk_turni_operatore FOREIGN KEY (operatore_id) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Conteggio contanti (per taglio) ----------
CREATE TABLE IF NOT EXISTS contanti (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  turno_id INT NOT NULL,
  taglio   INT NOT NULL,
  pezzi    INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_contanti (turno_id, taglio),
  CONSTRAINT fk_contanti_turno FOREIGN KEY (turno_id) REFERENCES turni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Scassettamenti VLT (per macchina) ----------
CREATE TABLE IF NOT EXISTS scassettamenti (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  turno_id    INT NOT NULL,
  macchina_id INT NOT NULL,
  importo     DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_scass (turno_id, macchina_id),
  CONSTRAINT fk_scass_turno    FOREIGN KEY (turno_id) REFERENCES turni(id) ON DELETE CASCADE,
  CONSTRAINT fk_scass_macchina FOREIGN KEY (macchina_id) REFERENCES macchine(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Refill AWP (lista: n. macchina, euro, ora) ----------
CREATE TABLE IF NOT EXISTS refill_awp (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  turno_id   INT NOT NULL,
  n_macchina VARCHAR(20) NULL,
  euro       DECIMAL(10,2) NOT NULL DEFAULT 0,
  ora        TIME NULL,
  CONSTRAINT fk_refill_turno FOREIGN KEY (turno_id) REFERENCES turni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Ticket pagati (vincite) per fornitore ----------
CREATE TABLE IF NOT EXISTS ticket (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  turno_id  INT NOT NULL,
  fornitore ENUM('NOVO','INSPIRED','SPIELO') NOT NULL,
  importo   DECIMAL(10,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_ticket (turno_id, fornitore),
  CONSTRAINT fk_ticket_turno FOREIGN KEY (turno_id) REFERENCES turni(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Bet/Win SNAI (per giorno e fornitore) ----------
CREATE TABLE IF NOT EXISTS snai_betwin (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  data      DATE NOT NULL,
  fornitore ENUM('NOVO','INSPIRED','SPIELO') NOT NULL,
  giocato   DECIMAL(12,2) NOT NULL DEFAULT 0,
  pagato    DECIMAL(12,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_betwin (data, fornitore)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Audit log (tracciabilita modifiche) ----------
CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  utente_id  INT NULL,
  azione     VARCHAR(50) NOT NULL,
  entita     VARCHAR(50) NULL,
  entita_id  INT NULL,
  dettaglio  TEXT NULL,
  ip         VARCHAR(45) NULL,
  creato_il  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_utente FOREIGN KEY (utente_id) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;

-- ---------- Parco macchine VLT (seed) ----------
INSERT IGNORE INTO macchine (codice, tipo, fornitore, ordine) VALUES
('NOVO 31','VLT','NOVO',1),('NOVO 74','VLT','NOVO',2),('NOVO 81','VLT','NOVO',3),
('NOVO 46','VLT','NOVO',4),('NOVO 44','VLT','NOVO',5),('NOVO 76','VLT','NOVO',6),
('NOVO 12','VLT','NOVO',7),('NOVO 52','VLT','NOVO',8),('NOVO 03','VLT','NOVO',9),
('NOVO 37','VLT','NOVO',10),
('INSPIRED 106','VLT','INSPIRED',11),('INSPIRED 107','VLT','INSPIRED',12),
('INSPIRED 108','VLT','INSPIRED',13),('INSPIRED 109','VLT','INSPIRED',14),
('INSPIRED 110','VLT','INSPIRED',15),
('SPIELO 1','VLT','SPIELO',16),('SPIELO 2','VLT','SPIELO',17);

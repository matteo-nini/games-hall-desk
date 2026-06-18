-- Ticket di assistenza tecnica per macchine VLT/AWP
CREATE TABLE IF NOT EXISTS ticket_assistenza (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  data_apertura DATE        NOT NULL,
  macchina      VARCHAR(50) NOT NULL,
  problema      TEXT        NOT NULL,
  id_ticket     VARCHAR(50) DEFAULT NULL,   -- es. CAS-7697007
  risoluzione   VARCHAR(500) DEFAULT NULL,
  data_chiusura DATE        DEFAULT NULL,
  stato         ENUM('aperto','risolto') NOT NULL DEFAULT 'aperto',
  creato_da     INT         DEFAULT NULL,
  creato_il     DATETIME    NOT NULL DEFAULT NOW(),
  FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

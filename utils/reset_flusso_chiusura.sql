-- 1. Riapre le giornate (reset stato + campi chiusura)
UPDATE giornate
SET stato = 'aperta',
    chiusa_da = NULL,
    chiusa_il = NULL
WHERE data BETWEEN '2026-01-01' AND '2026-06-30';

-- 2. Elimina le conferme ritiro dei revisori per quelle giornate
DELETE vc
FROM versamenti_confermati vc
INNER JOIN giornate g ON g.id = vc.giornata_id
WHERE g.data BETWEEN '2026-01-01' AND '2026-06-30';
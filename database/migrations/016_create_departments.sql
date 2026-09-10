-- Departamentos da empresa que pedem assistência técnica ao TI.
--
-- Este quadro é informativo, para uso interno da equipa: nada do que aqui
-- fica entra no relatório semanal.
--
-- Um departamento desativado sai do quadro de registo, mas os votos que já
-- tinha continuam a contar no histórico — a contagem passada não se reescreve.
CREATE TABLE IF NOT EXISTS `departments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`       VARCHAR(80) NOT NULL,
    `slug`       VARCHAR(100) NOT NULL,
    `cor_hex`    VARCHAR(7) NOT NULL DEFAULT '#1F3864',
    `ativo`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_departments_slug` (`slug`),
    KEY `idx_departments_ativo` (`ativo`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

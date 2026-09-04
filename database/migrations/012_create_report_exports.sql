-- Registo de cada ficheiro gerado a partir de um relatório.
-- Nunca há substituição: cada geração acrescenta uma linha nova, com o
-- caminho do ficheiro e o resumo SHA-256 do seu conteúdo.
CREATE TABLE IF NOT EXISTS `report_exports` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id`       INT UNSIGNED NOT NULL,
    `formato`         ENUM('docx', 'pdf') NOT NULL DEFAULT 'docx',
    `caminho_arquivo` VARCHAR(500) NOT NULL,
    `hash`            CHAR(64) NOT NULL,
    `gerado_por`      INT UNSIGNED DEFAULT NULL,
    `gerado_em`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_exports_report` (`report_id`, `gerado_em`),
    CONSTRAINT `fk_exports_report`
        FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_exports_gerado_por`
        FOREIGN KEY (`gerado_por`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Ficheiros .docx gerados a partir de um relatório de alteração.
--
-- Como no relatório semanal, nunca há substituição: cada geração acrescenta
-- uma linha nova. Aqui guarda-se também a versão do relatório que deu origem
-- ao ficheiro — sem isso, um .docx antigo seria indistinguível de um recente.
CREATE TABLE IF NOT EXISTS `change_report_exports` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `change_report_id` INT UNSIGNED NOT NULL,
    `versao`           INT UNSIGNED NOT NULL DEFAULT 1,
    `caminho_arquivo`  VARCHAR(500) NOT NULL,
    `hash`             CHAR(64) NOT NULL,
    `gerado_por`       INT UNSIGNED DEFAULT NULL,
    `gerado_em`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_change_exports_relatorio` (`change_report_id`, `gerado_em`),
    CONSTRAINT `fk_change_exports_relatorio`
        FOREIGN KEY (`change_report_id`) REFERENCES `change_reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_change_exports_user`
        FOREIGN KEY (`gerado_por`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

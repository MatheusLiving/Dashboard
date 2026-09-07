-- Registo dos envios do relatório por correio eletrónico.
--
-- Guarda quem enviou, para quem e qual a versão do ficheiro anexada, para que
-- seja possível responder mais tarde à pergunta «isto chegou a ser enviado?».
CREATE TABLE IF NOT EXISTS `report_emails` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id`      INT UNSIGNED NOT NULL,
    `export_id`      INT UNSIGNED DEFAULT NULL,
    `destinatarios`  VARCHAR(1000) NOT NULL,
    `assunto`        VARCHAR(255) NOT NULL,
    `mensagem`       TEXT DEFAULT NULL,
    `enviado_por`    INT UNSIGNED DEFAULT NULL,
    `enviado_em`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_report_emails_report` (`report_id`, `enviado_em`),
    CONSTRAINT `fk_report_emails_report`
        FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- O envio fica registado mesmo que a versão do ficheiro seja removida.
    CONSTRAINT `fk_report_emails_export`
        FOREIGN KEY (`export_id`) REFERENCES `report_exports` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_report_emails_user`
        FOREIGN KEY (`enviado_por`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

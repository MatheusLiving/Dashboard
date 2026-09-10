-- Envios do relatório de alteração por correio eletrónico.
--
-- É por aqui que o documento segue para aprovação, por isso o registo guarda
-- a versão enviada: quando alguém responder «aprovado», tem de ser possível
-- saber exatamente o que essa pessoa leu.
CREATE TABLE IF NOT EXISTS `change_report_emails` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `change_report_id` INT UNSIGNED NOT NULL,
    `export_id`        INT UNSIGNED DEFAULT NULL,
    `versao`           INT UNSIGNED NOT NULL DEFAULT 1,
    `destinatarios`    VARCHAR(1000) NOT NULL,
    `assunto`          VARCHAR(255) NOT NULL,
    `mensagem`         TEXT DEFAULT NULL,
    `enviado_por`      INT UNSIGNED DEFAULT NULL,
    `enviado_em`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_change_emails_relatorio` (`change_report_id`, `enviado_em`),
    CONSTRAINT `fk_change_emails_relatorio`
        FOREIGN KEY (`change_report_id`) REFERENCES `change_reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- O envio fica registado mesmo que a versão do ficheiro seja removida.
    CONSTRAINT `fk_change_emails_export`
        FOREIGN KEY (`export_id`) REFERENCES `change_report_exports` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_change_emails_user`
        FOREIGN KEY (`enviado_por`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

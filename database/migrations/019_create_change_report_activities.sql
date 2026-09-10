-- Linhas da secção 2 do relatório de alteração: o acompanhamento do
-- desenvolvimento, atualizado ao longo do trabalho.
--
-- O texto é copiado, tal como no relatório semanal: uma linha escrita hoje
-- descreve o que se passou hoje e não muda porque a tarefa mudou de estado
-- na semana seguinte.
CREATE TABLE IF NOT EXISTS `change_report_activities` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `change_report_id` INT UNSIGNED NOT NULL,
    `ordem`            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `data`             DATE DEFAULT NULL,
    `estado`           VARCHAR(80) DEFAULT NULL,
    `descricao`        VARCHAR(500) DEFAULT NULL,
    `responsavel`      VARCHAR(120) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_change_activities_relatorio` (`change_report_id`, `ordem`),
    CONSTRAINT `fk_change_activities_relatorio`
        FOREIGN KEY (`change_report_id`) REFERENCES `change_reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

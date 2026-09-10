-- Histórico do relatório de alteração.
--
-- É o coração deste documento: como sai para aprovação e pode voltar com
-- alterações pedidas, tem de ser sempre possível responder à pergunta «o que
-- é que estava escrito quando isto foi aprovado?».
--
-- Cada linha guarda o conteúdo **completo** do relatório em `conteudo_json`,
-- incluindo as linhas de acompanhamento. É uma cópia, não uma referência:
-- alterar o relatório depois não toca em nenhuma versão já gravada.
--
-- `nota` guarda a justificação de quem pede alterações ou aprova — o motivo
-- de uma versão existir é tão importante como o seu conteúdo.
CREATE TABLE IF NOT EXISTS `change_report_versions` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `change_report_id` INT UNSIGNED NOT NULL,
    `versao`           INT UNSIGNED NOT NULL,
    `motivo`           ENUM('criacao', 'gravacao', 'envio_aprovacao', 'aprovacao', 'alteracoes_pedidas', 'reabertura')
                       NOT NULL DEFAULT 'gravacao',
    `estado`           ENUM('rascunho', 'em_aprovacao', 'aprovado', 'alteracoes_pedidas')
                       NOT NULL DEFAULT 'rascunho',
    `nota`             TEXT DEFAULT NULL,
    `conteudo_json`    LONGTEXT NOT NULL,
    `criado_por`       INT UNSIGNED DEFAULT NULL,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_change_versions` (`change_report_id`, `versao`),
    KEY `idx_change_versions_relatorio` (`change_report_id`, `created_at`),
    CONSTRAINT `fk_change_versions_relatorio`
        FOREIGN KEY (`change_report_id`) REFERENCES `change_reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_change_versions_user`
        FOREIGN KEY (`criado_por`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

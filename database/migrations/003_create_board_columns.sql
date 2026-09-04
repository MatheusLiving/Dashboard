-- Colunas do quadro Kanban, configuráveis pelo administrador.
-- `is_concluida` marca a coluna terminal: ao arrastar um cartão para lá, o
-- sistema preenche automaticamente a data de conclusão da tarefa.
CREATE TABLE IF NOT EXISTS `board_columns` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`         VARCHAR(80) NOT NULL,
    `ordem`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `cor`          VARCHAR(7) NOT NULL DEFAULT '#64748B',
    `is_concluida` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_board_columns_ordem` (`ordem`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

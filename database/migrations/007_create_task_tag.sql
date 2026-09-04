-- Associação entre tarefas e etiquetas (relação de muitos para muitos).
CREATE TABLE IF NOT EXISTS `task_tag` (
    `task_id` INT UNSIGNED NOT NULL,
    `tag_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`task_id`, `tag_id`),
    KEY `idx_task_tag_tag` (`tag_id`),
    CONSTRAINT `fk_task_tag_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_task_tag_tag`
        FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

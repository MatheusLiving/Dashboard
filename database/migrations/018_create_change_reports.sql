-- Relatório de Pedido e Acompanhamento de Alteração de Software.
--
-- Um por unidade de trabalho: abre-se ao criar um projeto ou uma tarefa de
-- desenvolvimento, acompanha o trabalho e segue para aprovação. Ao contrário
-- do relatório semanal, este documento vive num ciclo: pode voltar da
-- aprovação com alterações pedidas e ser corrigido as vezes que forem
-- precisas. O histórico fica em `change_report_versions`.
--
-- `cliente_projeto` e `sistema_afetado` são cópias de texto, não referências:
-- um relatório enviado para aprovação não pode mudar por o projeto ter sido
-- renomeado entretanto. As ligações a `projects` e `tasks` servem apenas para
-- rastreabilidade e são anuladas com ON DELETE SET NULL.
CREATE TABLE IF NOT EXISTS `change_reports` (
    `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referencia`              VARCHAR(30) DEFAULT NULL,
    `user_id`                 INT UNSIGNED DEFAULT NULL,
    `project_id`              INT UNSIGNED DEFAULT NULL,
    `task_id`                 INT UNSIGNED DEFAULT NULL,
    `origem`                  ENUM('projeto', 'tarefa', 'manual') NOT NULL DEFAULT 'manual',

    -- Cabeçalho do documento
    `data_abertura`           DATE NOT NULL,
    `solicitado_por`          VARCHAR(200) DEFAULT NULL,
    `cliente_projeto`         VARCHAR(200) DEFAULT NULL,
    `sistema_afetado`         VARCHAR(200) DEFAULT NULL,
    `tipo`                    ENUM('bug', 'correcao', 'funcionalidade', 'melhoria', 'configuracao')
                              NOT NULL DEFAULT 'funcionalidade',
    `prioridade`              ENUM('baixa', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    `data_prevista`           DATE DEFAULT NULL,

    -- Secção 1 — Descrição do pedido
    `desc_problema`           TEXT DEFAULT NULL,
    `objetivo`                TEXT DEFAULT NULL,
    `ambito`                  TEXT DEFAULT NULL,
    `modulos`                 TEXT DEFAULT NULL,
    `impacto_riscos`          TEXT DEFAULT NULL,
    `estimativa`              TEXT DEFAULT NULL,
    `programadores`           TEXT DEFAULT NULL,

    -- Secção 2.1 — Desvios ao pedido inicial
    `desvios`                 TEXT DEFAULT NULL,

    -- Secção 3 — Testes e validação
    `testes_realizados`       TEXT DEFAULT NULL,
    `resultados`              TEXT DEFAULT NULL,
    `validado_por`            TEXT DEFAULT NULL,

    -- Secção 4 — Relatório final de execução
    `resumo_execucao`         TEXT DEFAULT NULL,
    `diferencas`              TEXT DEFAULT NULL,
    `notas_cliente`           TEXT DEFAULT NULL,
    `commits`                 TEXT DEFAULT NULL,
    `conclusao_programadores` TEXT DEFAULT NULL,

    -- Secção 5 — Entrega
    `entrega`                 TEXT DEFAULT NULL,

    `estado`                  ENUM('rascunho', 'em_aprovacao', 'aprovado', 'alteracoes_pedidas')
                              NOT NULL DEFAULT 'rascunho',
    `versao`                  INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_change_reports_referencia` (`referencia`),
    KEY `idx_change_reports_estado` (`estado`),
    KEY `idx_change_reports_projeto` (`project_id`),
    KEY `idx_change_reports_tarefa` (`task_id`),
    CONSTRAINT `fk_change_reports_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_change_reports_projeto`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_change_reports_tarefa`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

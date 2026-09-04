<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Cálculo de semanas segundo a norma ISO-8601.
 *
 * Na ISO-8601 a semana começa à segunda-feira e a semana 1 é aquela que
 * contém a primeira quinta-feira do ano. Todo o sistema — pré-preenchimento
 * do relatório, nomes de ficheiro, filtros — usa exclusivamente estas regras.
 */
final class Semana
{
    /**
     * Fuso horário da aplicação.
     */
    public static function fuso(): DateTimeZone
    {
        return new DateTimeZone((string) Config::get('app.fuso', 'Europe/Lisbon'));
    }

    /**
     * Data e hora atuais no fuso da aplicação.
     */
    public static function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::fuso());
    }

    /**
     * Ano e número da semana ISO correspondentes a uma data.
     *
     * Atenção: o ano ISO pode diferir do ano civil no início e no fim do ano
     * (31 de dezembro pode pertencer à semana 1 do ano seguinte).
     *
     * @return array{ano: int, semana: int}
     */
    public static function deData(DateTimeImmutable $data): array
    {
        return [
            'ano'    => (int) $data->format('o'),
            'semana' => (int) $data->format('W'),
        ];
    }

    /**
     * Ano e número da semana ISO da semana corrente.
     *
     * @return array{ano: int, semana: int}
     */
    public static function corrente(): array
    {
        return self::deData(self::agora());
    }

    /**
     * Segunda-feira de uma semana ISO, às 00:00.
     */
    public static function inicio(int $ano, int $semana): DateTimeImmutable
    {
        self::validar($ano, $semana);

        return (new DateTimeImmutable('now', self::fuso()))
            ->setISODate($ano, $semana, 1)
            ->setTime(0, 0, 0);
    }

    /**
     * Domingo de uma semana ISO, às 23:59:59.
     */
    public static function fim(int $ano, int $semana): DateTimeImmutable
    {
        self::validar($ano, $semana);

        return (new DateTimeImmutable('now', self::fuso()))
            ->setISODate($ano, $semana, 7)
            ->setTime(23, 59, 59);
    }

    /**
     * Intervalo da semana em datas simples (AAAA-MM-DD), pronto para SQL.
     *
     * @return array{inicio: string, fim: string}
     */
    public static function intervalo(int $ano, int $semana): array
    {
        return [
            'inicio' => self::inicio($ano, $semana)->format('Y-m-d'),
            'fim'    => self::fim($ano, $semana)->format('Y-m-d'),
        ];
    }

    /**
     * Número de semanas ISO de um ano (52 ou 53).
     */
    public static function semanasNoAno(int $ano): int
    {
        // 28 de dezembro pertence sempre à última semana ISO do ano.
        $ultima = (new DateTimeImmutable('now', self::fuso()))
            ->setDate($ano, 12, 28)
            ->setTime(12, 0, 0);

        return (int) $ultima->format('W');
    }

    /**
     * Rótulo legível da semana, como aparece no campo "Semana / Período".
     * Exemplo: "S36/2026 (31/08/2026 a 06/09/2026)".
     */
    public static function rotulo(int $ano, int $semana): string
    {
        return sprintf(
            'S%02d/%d (%s a %s)',
            $semana,
            $ano,
            self::inicio($ano, $semana)->format('d/m/Y'),
            self::fim($ano, $semana)->format('d/m/Y')
        );
    }

    /**
     * Rótulo curto da semana, para listagens: "S36/2026".
     */
    public static function rotuloCurto(int $ano, int $semana): string
    {
        return sprintf('S%02d/%d', $semana, $ano);
    }

    /**
     * Semana anterior a uma dada semana ISO.
     *
     * @return array{ano: int, semana: int}
     */
    public static function anterior(int $ano, int $semana): array
    {
        return self::deData(self::inicio($ano, $semana)->modify('-7 days'));
    }

    /**
     * Semana seguinte a uma dada semana ISO.
     *
     * @return array{ano: int, semana: int}
     */
    public static function seguinte(int $ano, int $semana): array
    {
        return self::deData(self::inicio($ano, $semana)->modify('+7 days'));
    }

    /**
     * Lista de semanas recentes, da mais recente para a mais antiga.
     * Serve para preencher o seletor de semana do formulário do relatório.
     *
     * @return list<array{ano: int, semana: int, rotulo: string}>
     */
    public static function recentes(int $quantidade = 12): array
    {
        $atual = self::corrente();
        $lista = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $lista[] = [
                'ano'    => $atual['ano'],
                'semana' => $atual['semana'],
                'rotulo' => self::rotulo($atual['ano'], $atual['semana']),
            ];

            $atual = self::anterior($atual['ano'], $atual['semana']);
        }

        return $lista;
    }

    /**
     * Converte minutos no formato usado na coluna "Tempo dedicado": "2h 30m".
     */
    public static function minutosParaTexto(?int $minutos): string
    {
        if ($minutos === null || $minutos <= 0) {
            return '—';
        }

        $horas   = intdiv($minutos, 60);
        $restantes = $minutos % 60;

        if ($horas === 0) {
            return sprintf('%dm', $restantes);
        }

        if ($restantes === 0) {
            return sprintf('%dh', $horas);
        }

        return sprintf('%dh %02dm', $horas, $restantes);
    }

    /**
     * Rejeita anos e semanas fora dos limites da norma.
     */
    private static function validar(int $ano, int $semana): void
    {
        if ($ano < 2000 || $ano > 2100) {
            throw new InvalidArgumentException(sprintf('Ano fora do intervalo suportado: %d', $ano));
        }

        if ($semana < 1 || $semana > self::semanasNoAno($ano)) {
            throw new InvalidArgumentException(
                sprintf('A semana %d não existe no ano %d.', $semana, $ano)
            );
        }
    }
}

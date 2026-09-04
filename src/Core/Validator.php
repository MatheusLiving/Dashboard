<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Validação de dados no servidor.
 *
 * A validação feita em JavaScript é apenas comodidade para o utilizador; esta
 * é a que conta. Nenhum controlador escreve em base de dados sem passar por aqui.
 */
final class Validator
{
    /** @var array<string, mixed> Dados a validar. */
    private array $dados;

    /** @var array<string, string> Primeiro erro encontrado por campo. */
    private array $erros = [];

    /** @var array<string, string> Nomes legíveis dos campos, para as mensagens. */
    private array $rotulos = [];

    /**
     * @param array<string, mixed> $dados
     */
    public function __construct(array $dados)
    {
        $this->dados = $dados;
    }

    /**
     * @param array<string, mixed> $dados
     */
    public static function para(array $dados): self
    {
        return new self($dados);
    }

    /**
     * Define o nome legível de um campo, usado nas mensagens de erro.
     */
    public function rotulo(string $campo, string $rotulo): self
    {
        $this->rotulos[$campo] = $rotulo;

        return $this;
    }

    /**
     * @param array<string, string> $rotulos
     */
    public function rotulos(array $rotulos): self
    {
        $this->rotulos = array_merge($this->rotulos, $rotulos);

        return $this;
    }

    /** Campo de preenchimento obrigatório. */
    public function obrigatorio(string $campo): self
    {
        $valor = $this->valor($campo);

        if ($valor === null || (is_string($valor) && trim($valor) === '') || (is_array($valor) && $valor === [])) {
            $this->erro($campo, sprintf('O campo %s é obrigatório.', $this->nome($campo)));
        }

        return $this;
    }

    /** Comprimento mínimo de uma cadeia de caracteres. */
    public function minimo(string $campo, int $minimo): self
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && $valor !== '' && mb_strlen($valor) < $minimo) {
            $this->erro($campo, sprintf('O campo %s deve ter pelo menos %d caracteres.', $this->nome($campo), $minimo));
        }

        return $this;
    }

    /** Comprimento máximo de uma cadeia de caracteres. */
    public function maximo(string $campo, int $maximo): self
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && mb_strlen($valor) > $maximo) {
            $this->erro($campo, sprintf('O campo %s não pode exceder %d caracteres.', $this->nome($campo), $maximo));
        }

        return $this;
    }

    /** Endereço de correio eletrónico válido. */
    public function email(string $campo): self
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && $valor !== '' && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $this->erro($campo, sprintf('O campo %s deve conter um endereço de correio válido.', $this->nome($campo)));
        }

        return $this;
    }

    /** Valor inteiro, opcionalmente dentro de um intervalo. */
    public function inteiro(string $campo, ?int $min = null, ?int $max = null): self
    {
        $valor = $this->valor($campo);

        if ($valor === null || $valor === '') {
            return $this;
        }

        if (!is_numeric($valor) || (string) (int) $valor !== (string) $valor) {
            $this->erro($campo, sprintf('O campo %s deve ser um número inteiro.', $this->nome($campo)));

            return $this;
        }

        $numero = (int) $valor;

        if ($min !== null && $numero < $min) {
            $this->erro($campo, sprintf('O campo %s não pode ser inferior a %d.', $this->nome($campo), $min));
        }

        if ($max !== null && $numero > $max) {
            $this->erro($campo, sprintf('O campo %s não pode ser superior a %d.', $this->nome($campo), $max));
        }

        return $this;
    }

    /** Valor pertencente a uma lista fechada de opções. */
    public function em(string $campo, array $opcoes): self
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !in_array($valor, $opcoes, true)) {
            $this->erro($campo, sprintf('O valor indicado para %s não é válido.', $this->nome($campo)));
        }

        return $this;
    }

    /** Data no formato AAAA-MM-DD. */
    public function data(string $campo): self
    {
        $valor = $this->valor($campo);

        if ($valor === null || $valor === '') {
            return $this;
        }

        $data = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $valor);

        if ($data === false || $data->format('Y-m-d') !== $valor) {
            $this->erro($campo, sprintf('O campo %s deve conter uma data válida (AAAA-MM-DD).', $this->nome($campo)));
        }

        return $this;
    }

    /** Cor em notação hexadecimal (#RGB ou #RRGGBB). */
    public function corHex(string $campo): self
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && $valor !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $valor) !== 1) {
            $this->erro($campo, sprintf('O campo %s deve conter uma cor hexadecimal (ex.: #1F3864).', $this->nome($campo)));
        }

        return $this;
    }

    /** Dois campos com o mesmo valor (confirmação de palavra-passe). */
    public function confirmado(string $campo, string $campoConfirmacao): self
    {
        if ($this->valor($campo) !== $this->valor($campoConfirmacao)) {
            $this->erro($campoConfirmacao, sprintf('A confirmação de %s não coincide.', $this->nome($campo)));
        }

        return $this;
    }

    /** Regra livre: falha quando o resultado do teste é falso. */
    public function regra(string $campo, bool $condicao, string $mensagem): self
    {
        if (!$condicao) {
            $this->erro($campo, $mensagem);
        }

        return $this;
    }

    /** Indica se a validação passou. */
    public function passou(): bool
    {
        return $this->erros === [];
    }

    /** Indica se a validação falhou. */
    public function falhou(): bool
    {
        return !$this->passou();
    }

    /**
     * @return array<string, string>
     */
    public function erros(): array
    {
        return $this->erros;
    }

    /**
     * Primeira mensagem de erro, ou null se não houver nenhuma.
     */
    public function primeiroErro(): ?string
    {
        foreach ($this->erros as $mensagem) {
            return $mensagem;
        }

        return null;
    }

    /**
     * Valor bruto de um campo.
     */
    private function valor(string $campo): mixed
    {
        $valor = $this->dados[$campo] ?? null;

        return is_string($valor) ? trim($valor) : $valor;
    }

    /**
     * Nome legível do campo.
     */
    private function nome(string $campo): string
    {
        return $this->rotulos[$campo] ?? str_replace('_', ' ', $campo);
    }

    /**
     * Regista o primeiro erro de cada campo — não acumulamos vários.
     */
    private function erro(string $campo, string $mensagem): void
    {
        if (!isset($this->erros[$campo])) {
            $this->erros[$campo] = $mensagem;
        }
    }
}

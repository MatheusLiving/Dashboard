<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Consulta do registo de auditoria, reservada a administradores.
 */
final class AuditController extends Controller
{
    public function index(): void
    {
        $filtros = $this->filtros();
        $pagina  = max(1, Request::queryInt('pagina') ?? 1);

        $total   = AuditLog::total($filtros);
        $paginas = max(1, (int) ceil($total / AuditLog::POR_PAGINA));

        // Um número de página acima do total volta à última existente.
        $pagina = min($pagina, $paginas);

        $this->ver('auditoria/index', [
            'registos'     => AuditLog::listar($filtros, $pagina),
            'filtros'      => $filtros,
            'temFiltros'   => array_filter($filtros, static fn (mixed $v): bool => $v !== null) !== [],
            'utilizadores' => User::todos(false),
            'entidades'    => AuditLog::entidades(),
            'acoes'        => AuditLog::acoes(),
            'resumo'       => AuditLog::resumoPorAcao($filtros),
            'total'        => $total,
            'pagina'       => $pagina,
            'paginas'      => $paginas,
            'rotulosAcao'  => AuditLog::rotulosAcao(),
            'rotulosEnt'   => AuditLog::rotulosEntidade(),
        ]);
    }

    /**
     * Filtros aceites: entidade, ação, utilizador e período.
     *
     * @return array{entidade: ?string, acao: ?string, user_id: ?int, de: ?string, ate: ?string}
     */
    private function filtros(): array
    {
        $entidade = Request::query('entidade');
        $acao     = Request::query('acao');

        return [
            // Só se aceitam valores que existam de facto no registo.
            'entidade' => $entidade !== null && in_array($entidade, AuditLog::entidades(), true) ? $entidade : null,
            'acao'     => $acao !== null && in_array($acao, AuditLog::acoes(), true) ? $acao : null,
            'user_id'  => Request::queryInt('utilizador'),
            'de'       => $this->data(Request::query('de')),
            'ate'      => $this->data(Request::query('ate')),
        ];
    }

    /**
     * Aceita apenas datas no formato AAAA-MM-DD.
     */
    private function data(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return ($data !== false && $data->format('Y-m-d') === $valor) ? $valor : null;
    }
}

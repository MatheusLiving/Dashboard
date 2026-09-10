<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Semana;
use App\Models\Setting;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use RuntimeException;

/**
 * Envio do relatório semanal por correio eletrónico, com o .docx em anexo.
 *
 * A configuração vem toda do .env. Quando `MAIL_ENABLED` está desligado, a
 * funcionalidade desaparece da interface em vez de falhar no momento do envio.
 */
final class Mailer
{
    /** Número máximo de destinatários por envio. */
    public const MAX_DESTINATARIOS = 10;

    /**
     * Indica se o envio está ligado e minimamente configurado.
     */
    public static function ativo(): bool
    {
        return (bool) Config::get('mail.ativo', false)
            && (string) Config::get('mail.host', '') !== '';
    }

    /**
     * Envia um relatório com o ficheiro em anexo.
     *
     * @param array<string, mixed> $relatorio  Linha de `reports` com o colaborador
     * @param list<string>         $destinatarios
     * @param string               $anexo      Caminho absoluto do .docx
     * @return list<string> Endereços que o servidor aceitou
     */
    public function enviarRelatorio(
        array $relatorio,
        array $destinatarios,
        string $anexo,
        string $assunto,
        string $mensagem,
        string $remetenteNome,
        string $remetenteEmail
    ): array {
        if (!self::ativo()) {
            throw new RuntimeException('O envio de correio está desligado na configuração.');
        }

        if ($destinatarios === []) {
            throw new RuntimeException('Indique pelo menos um destinatário.');
        }

        if (!is_file($anexo) || !is_readable($anexo)) {
            throw new RuntimeException('O ficheiro do relatório não foi encontrado.');
        }

        return $this->enviar(
            $destinatarios,
            $anexo,
            $assunto,
            $this->corpoHtml($relatorio, $mensagem, $remetenteNome),
            $this->corpoTexto($relatorio, $mensagem, $remetenteNome),
            $remetenteNome,
            $remetenteEmail
        );
    }

    /**
     * Envia um documento qualquer com o ficheiro em anexo.
     *
     * Serve o Relatório de Alteração de Software, que segue para aprovação e
     * precisa de um corpo próprio: o que interessa a quem aprova é a
     * referência, a versão e o que mudou desde a última vez.
     *
     * @param list<string>          $destinatarios
     * @param array<string, string> $detalhes  Linhas de identificação (rótulo => valor)
     * @return list<string> Endereços que o servidor aceitou
     */
    public function enviarDocumento(
        array $destinatarios,
        string $anexo,
        string $assunto,
        string $introducao,
        array $detalhes,
        string $mensagem,
        string $remetenteNome,
        string $remetenteEmail
    ): array {
        if (!self::ativo()) {
            throw new RuntimeException('O envio de correio está desligado na configuração.');
        }

        if ($destinatarios === []) {
            throw new RuntimeException('Indique pelo menos um destinatário.');
        }

        if (!is_file($anexo) || !is_readable($anexo)) {
            throw new RuntimeException('O ficheiro do relatório não foi encontrado.');
        }

        return $this->enviar(
            $destinatarios,
            $anexo,
            $assunto,
            $this->corpoHtmlGenerico($introducao, $detalhes, $mensagem, $remetenteNome),
            $this->corpoTextoGenerico($introducao, $detalhes, $mensagem, $remetenteNome),
            $remetenteNome,
            $remetenteEmail
        );
    }

    /**
     * Entrega a mensagem ao servidor SMTP.
     *
     * @param list<string> $destinatarios
     * @return list<string>
     */
    private function enviar(
        array $destinatarios,
        string $anexo,
        string $assunto,
        string $html,
        string $texto,
        string $remetenteNome,
        string $remetenteEmail
    ): array {
        $mail = new PHPMailer(true);

        try {
            $this->configurarTransporte($mail);

            $mail->setFrom(
                (string) Config::get('mail.de'),
                (string) Config::get('mail.de_nome')
            );

            // As respostas voltam para quem enviou, não para a caixa da aplicação.
            $responder = (string) Config::get('mail.responder', '');

            if ($responder !== '') {
                $mail->addReplyTo($responder);
            } elseif (filter_var($remetenteEmail, FILTER_VALIDATE_EMAIL) !== false) {
                $mail->addReplyTo($remetenteEmail, $remetenteNome);
            }

            foreach ($destinatarios as $destinatario) {
                $mail->addAddress($destinatario);
            }

            $mail->addAttachment($anexo, basename($anexo));

            $mail->CharSet  = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->Subject  = $assunto;
            $mail->isHTML(true);
            $mail->Body     = $html;
            $mail->AltBody  = $texto;

            $mail->send();
        } catch (PHPMailerException $e) {
            // A mensagem do PHPMailer é mais útil do que a da exceção genérica.
            throw new RuntimeException(
                'Falha no envio: ' . ($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage()),
                0,
                $e
            );
        }

        return $destinatarios;
    }

    /**
     * Corpo em HTML de um documento genérico.
     *
     * @param array<string, string> $detalhes
     */
    private function corpoHtmlGenerico(
        string $introducao,
        array $detalhes,
        string $mensagem,
        string $remetenteNome
    ): string {
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $nota = trim($mensagem) === ''
            ? ''
            : '<p style="margin:0 0 16px;white-space:pre-line">' . nl2br($e($mensagem), false) . '</p>';

        $linhas = '';

        foreach ($detalhes as $rotulo => $valor) {
            $linhas .= '<tr>'
                . '<td style="padding:4px 16px 4px 0;color:#64748b">' . $e($rotulo) . '</td>'
                . '<td style="padding:4px 0">' . $e($valor === '' ? '—' : $valor) . '</td>'
                . '</tr>';
        }

        return <<<HTML
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
                    font-size:14px;color:#1e293b;line-height:1.6;max-width:600px">
            <p style="margin:0 0 16px">Boa tarde,</p>
            {$nota}
            <p style="margin:0 0 16px">{$e($introducao)}</p>
            <table style="border-collapse:collapse;margin:0 0 20px">{$linhas}</table>
            <p style="margin:0 0 4px">Com os melhores cumprimentos,</p>
            <p style="margin:0;color:#1F3864"><strong>{$e($remetenteNome)}</strong></p>
            <p style="margin:24px 0 0;padding-top:12px;border-top:1px solid #e2e8f0;
                      font-size:12px;color:#94a3b8">
                Mensagem enviada automaticamente por {$e(Setting::departamento())}.
            </p>
        </div>
        HTML;
    }

    /**
     * Corpo em texto simples de um documento genérico.
     *
     * @param array<string, string> $detalhes
     */
    private function corpoTextoGenerico(
        string $introducao,
        array $detalhes,
        string $mensagem,
        string $remetenteNome
    ): string {
        $linhas = ['Boa tarde,', ''];

        if (trim($mensagem) !== '') {
            $linhas[] = trim($mensagem);
            $linhas[] = '';
        }

        $linhas[] = $introducao;
        $linhas[] = '';

        foreach ($detalhes as $rotulo => $valor) {
            $linhas[] = $rotulo . ': ' . ($valor === '' ? '—' : $valor);
        }

        $linhas[] = '';
        $linhas[] = 'Com os melhores cumprimentos,';
        $linhas[] = $remetenteNome;
        $linhas[] = '';
        $linhas[] = '--';
        $linhas[] = 'Mensagem enviada automaticamente por ' . Setting::departamento() . '.';

        return implode("\n", $linhas);
    }

    /**
     * Configura o transporte SMTP a partir do .env.
     */
    private function configurarTransporte(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host    = (string) Config::get('mail.host');
        $mail->Port    = (int) Config::get('mail.porta', 1025);
        $mail->Timeout = 15;

        $utilizador = (string) Config::get('mail.utilizador', '');

        // Sem utilizador configurado não se tenta autenticar: servidores de
        // desenvolvimento como o Mailpit recusam a autenticação.
        if ($utilizador !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $utilizador;
            $mail->Password = (string) Config::get('mail.password', '');
        } else {
            $mail->SMTPAuth = false;
        }

        $cifra = (string) Config::get('mail.cifra', '');

        $mail->SMTPSecure = match ($cifra) {
            'tls'   => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl'   => PHPMailer::ENCRYPTION_SMTPS,
            default => '',
        };

        if ($cifra === '') {
            $mail->SMTPAutoTLS = false;
        }

        if (Config::debug()) {
            $mail->SMTPDebug   = SMTP::DEBUG_OFF;
            $mail->Debugoutput = static function (string $linha): void {
                error_log('SMTP: ' . trim($linha));
            };
        }
    }

    /**
     * Assunto proposto para o envio.
     *
     * @param array<string, mixed> $relatorio
     */
    public static function assuntoPorOmissao(array $relatorio): string
    {
        return sprintf(
            '%s — Relatório Semanal %s — %s',
            Setting::departamento(),
            Semana::rotuloCurto((int) $relatorio['ano'], (int) $relatorio['numero_semana']),
            (string) ($relatorio['colaborador'] ?? '')
        );
    }

    /**
     * Corpo da mensagem em HTML.
     *
     * @param array<string, mixed> $relatorio
     */
    private function corpoHtml(array $relatorio, string $mensagem, string $remetenteNome): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $periodo = sprintf(
            '%s a %s',
            date('d/m/Y', strtotime((string) $relatorio['semana_inicio'])),
            date('d/m/Y', strtotime((string) $relatorio['semana_fim']))
        );

        $nota = trim($mensagem) === ''
            ? ''
            : '<p style="margin:0 0 16px;white-space:pre-line">' . nl2br($e($mensagem), false) . '</p>';

        return <<<HTML
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
                    font-size:14px;color:#1e293b;line-height:1.6;max-width:600px">
            <p style="margin:0 0 16px">Boa tarde,</p>
            {$nota}
            <p style="margin:0 0 16px">
                Segue em anexo o Relatório Semanal de Atividade referente à semana
                <strong>{$e(Semana::rotuloCurto((int) $relatorio['ano'], (int) $relatorio['numero_semana']))}</strong>.
            </p>
            <table style="border-collapse:collapse;margin:0 0 20px">
                <tr>
                    <td style="padding:4px 16px 4px 0;color:#64748b">Colaborador</td>
                    <td style="padding:4px 0"><strong>{$e($relatorio['colaborador'] ?? '')}</strong></td>
                </tr>
                <tr>
                    <td style="padding:4px 16px 4px 0;color:#64748b">Função / Cargo</td>
                    <td style="padding:4px 0">{$e($relatorio['funcao_cargo'] ?: '—')}</td>
                </tr>
                <tr>
                    <td style="padding:4px 16px 4px 0;color:#64748b">Período</td>
                    <td style="padding:4px 0">{$e($periodo)}</td>
                </tr>
            </table>
            <p style="margin:0 0 4px">Com os melhores cumprimentos,</p>
            <p style="margin:0;color:#1F3864"><strong>{$e($remetenteNome)}</strong></p>
            <p style="margin:24px 0 0;padding-top:12px;border-top:1px solid #e2e8f0;
                      font-size:12px;color:#94a3b8">
                Mensagem enviada automaticamente por {$e(Setting::departamento())}.
            </p>
        </div>
        HTML;
    }

    /**
     * Corpo alternativo em texto simples, para clientes que não mostram HTML.
     *
     * @param array<string, mixed> $relatorio
     */
    private function corpoTexto(array $relatorio, string $mensagem, string $remetenteNome): string
    {
        $linhas = ['Boa tarde,', ''];

        if (trim($mensagem) !== '') {
            $linhas[] = trim($mensagem);
            $linhas[] = '';
        }

        $linhas[] = sprintf(
            'Segue em anexo o Relatório Semanal de Atividade referente à semana %s.',
            Semana::rotuloCurto((int) $relatorio['ano'], (int) $relatorio['numero_semana'])
        );
        $linhas[] = '';
        $linhas[] = 'Colaborador: ' . ($relatorio['colaborador'] ?? '');
        $linhas[] = 'Função / Cargo: ' . ($relatorio['funcao_cargo'] ?: '—');
        $linhas[] = sprintf(
            'Período: %s a %s',
            date('d/m/Y', strtotime((string) $relatorio['semana_inicio'])),
            date('d/m/Y', strtotime((string) $relatorio['semana_fim']))
        );
        $linhas[] = '';
        $linhas[] = 'Com os melhores cumprimentos,';
        $linhas[] = $remetenteNome;
        $linhas[] = '';
        $linhas[] = '--';
        $linhas[] = 'Mensagem enviada automaticamente por ' . Setting::departamento() . '.';

        return implode("\n", $linhas);
    }

    /**
     * Separa e valida uma lista de endereços escrita pelo utilizador.
     *
     * Aceita vírgulas, ponto e vírgula e quebras de linha como separadores.
     *
     * @return array{validos: list<string>, invalidos: list<string>}
     */
    public static function separarEnderecos(string $bruto): array
    {
        $partes = preg_split('/[,;\s]+/', trim($bruto)) ?: [];

        $validos   = [];
        $invalidos = [];

        foreach ($partes as $parte) {
            $parte = trim($parte);

            if ($parte === '') {
                continue;
            }

            if (filter_var($parte, FILTER_VALIDATE_EMAIL) !== false) {
                $validos[] = mb_strtolower($parte);
            } else {
                $invalidos[] = $parte;
            }
        }

        return [
            'validos'   => array_values(array_unique($validos)),
            'invalidos' => $invalidos,
        ];
    }
}

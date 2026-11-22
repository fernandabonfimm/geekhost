<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/logger.php';

final class Mailer
{
    public static function send(string $to, string $subject, string $html, bool $archive = true): bool
    {
        geekhost_load_env();
        $to = trim($to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $from = (string) env('GEEKHOST_CONTACT_EMAIL', 'contato@geekhost.com.br');
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: GeekHost <' . $from . '>',
            'Reply-To: ' . $from,
        ];

        $ok = @mail($to, $encodedSubject, $html, implode("\r\n", $headers));
        if ($archive) {
            self::archive($to, $subject, $html, $ok);
        }
        if (!$ok) {
            geekhost_log('mail_failed', ['to' => $to, 'subject' => $subject]);
        }
        return $ok;
    }

    public static function billingHtml(string $headline, string $intro, string $amount, string $due, string $payUrl, string $tone = 'remind'): string
    {
        $button = $tone === 'cancel' ? 'Ver o que aconteceu' : 'Pagar agora';
        $accent = $tone === 'cancel' ? '#1a1e24' : '#01e152';
        $ink = $tone === 'cancel' ? '#ffffff' : '#1a1e24';
        $safeUrl = htmlspecialchars($payUrl, ENT_QUOTES, 'UTF-8');
        $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
        $safeIntro = $intro;
        $safeAmount = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
        $safeDue = htmlspecialchars($due, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html lang="pt-BR"><body style="margin:0;background:#f3f4f5;font-family:Arial,sans-serif;color:#1a1e24;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f5;padding:28px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;">'
            . '<tr><td style="background:#1a1e24;padding:28px 32px;">'
            . '<div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:.02em;">GeekHost<span style="color:#01e152;">.</span></div>'
            . '<p style="margin:8px 0 0;color:rgba(255,255,255,.72);font-size:13px;">Hospedagem com suporte humano</p>'
            . '</td></tr>'
            . '<tr><td style="padding:32px;">'
            . '<h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;">' . $safeHeadline . '</h1>'
            . '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#3b4148;">' . $safeIntro . '</p>'
            . '<table role="presentation" width="100%" style="background:#f6f7f8;border-radius:12px;margin:0 0 22px;"><tr><td style="padding:16px 18px;">'
            . '<div style="font-size:12px;color:#697078;">Valor</div><div style="font-size:22px;font-weight:800;">' . $safeAmount . '</div>'
            . '<div style="margin-top:8px;font-size:13px;color:#697078;">Vencimento: ' . $safeDue . '</div>'
            . '</td></tr></table>'
            . '<a href="' . $safeUrl . '" style="display:inline-block;background:' . $accent . ';color:' . $ink . ';text-decoration:none;font-weight:800;padding:14px 22px;border-radius:12px;">' . $button . '</a>'
            . '<p style="margin:22px 0 0;font-size:12px;line-height:1.5;color:#697078;">Se o botão não abrir, copie este endereço:<br><span style="word-break:break-all;">' . $safeUrl . '</span></p>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 32px 26px;background:#f6f7f8;font-size:12px;color:#697078;">FBS Tecnologia · CNPJ 61.171.635/0001-46<br>Rua Pais Leme, 215, Conj. 1713, Pinheiros, São Paulo/SP</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function archive(string $to, string $subject, string $html, bool $sent): void
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = gmdate('Ymd-His') . '-' . substr(sha1($to . $subject), 0, 8) . '.html';
        $note = "<!-- to={$to} subject={$subject} sent=" . ($sent ? '1' : '0') . " -->\n";
        @file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $note . $html);
    }
}

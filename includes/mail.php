<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send an email using SMTP configuration from $config['smtp'].
 *
 * Expected $message keys:
 * - to: array of recipient email addresses (strings) or [['email' => ..., 'name' => ...], ...]
 * - subject: string
 * - html: HTML body (string)
 * - text: optional plaintext body fallback (string)
 * - reply_to: optional ['email' => ..., 'name' => ...] or string email
 *
 * Returns:
 * - success: bool
 * - error: string|null
 * - smtp_debug: array
 * - smtp_last_response: string|null
 * - message_id: string|null
 */
function send_email(array $message): array
{
    $config = $GLOBALS['config']['smtp'] ?? [];

    if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
        return [
            'success' => false,
            'error' => 'SMTP configuration is incomplete.',
            'smtp_debug' => [],
            'smtp_last_response' => null,
            'message_id' => null,
        ];
    }

    $mail = new PHPMailer(true);
    $verbose = !empty($GLOBALS['config']['email_verbose']);
    $debugLines = [];

    try {
        if ($verbose) {
            // Capture SMTP dialogue for easier troubleshooting (redacts AUTH-like lines).
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) use (&$debugLines) {
                $clean = trim((string)$str);
                if ($clean === '') {
                    return;
                }
                // PHPMailer already redacts credentials; keep server error messages verbatim.
                if (stripos($clean, '[credentials hidden]') !== false) {
                    return;
                }
                $debugLines[] = [
                    'level' => (int)$level,
                    'text' => $clean,
                ];
            };
        }

        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'] ?? 587;

        // Prevent hanging requests on broken SMTP/TLS negotiation.
        // Keep it conservative; failures will still be logged.
        $smtpTimeoutSeconds = 30;
        if (property_exists($mail, 'Timeout')) {
            $mail->Timeout = $smtpTimeoutSeconds;
        }
        if (property_exists($mail, 'SMTPTimeout')) {
            $mail->SMTPTimeout = $smtpTimeoutSeconds;
        }

        $encryption = $config['encryption'] ?? 'tls';
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->SMTPAuth = true;
        if (!empty($config['auth_type'])) {
            // Common accepted values are LOGIN / PLAIN.
            $authType = strtoupper(trim((string)$config['auth_type']));
            if (in_array($authType, ['LOGIN', 'PLAIN'], true)) {
                $mail->AuthType = $authType;
            }
        }
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];

        $fromAddress = $config['from_address'] ?? $config['username'];
        $fromName = $config['from_name'] ?? 'simple-twig-site';
        $mail->setFrom($fromAddress, $fromName);

        // Recipients
        $recipients = $message['to'] ?? [];
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                if (!empty($recipient['email'])) {
                    $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
                }
            } else {
                $mail->addAddress($recipient);
            }
        }

        // Reply-To
        if (!empty($message['reply_to'])) {
            if (is_array($message['reply_to'])) {
                if (!empty($message['reply_to']['email'])) {
                    $mail->addReplyTo($message['reply_to']['email'], $message['reply_to']['name'] ?? '');
                }
            } else {
                $mail->addReplyTo($message['reply_to']);
            }
        }

        $mail->Subject = $message['subject'] ?? '';
        // Ensure proper UTF-8 encoding for non-ASCII subjects (Hungarian, Chinese, etc.)
        $mail->CharSet = 'UTF-8';
        // Use base64 for Subject encoding when needed.
        $mail->Encoding = 'base64';

        $htmlBody = $message['html'] ?? '';
        $textBody = $message['text'] ?? null;

        if ($htmlBody !== '') {
            $mail->isHTML(true);
            // Explicitly set content type/charset for HTML
            $mail->ContentType = 'text/html; charset=UTF-8';
            $mail->Body = $htmlBody;
            if (!empty($textBody)) {
                $mail->AltBody = $textBody;
            }
        } else {
            $mail->isHTML(false);
            $mail->Body = $textBody ?? '';
        }

        $mail->send();

        $smtpLastResponse = null;
        $smtpInstance = $mail->getSMTPInstance();
        if ($smtpInstance && method_exists($smtpInstance, 'getLastResponse')) {
            $smtpLastResponse = $smtpInstance->getLastResponse();
        }

        return [
            'success' => true,
            'error' => null,
            'smtp_debug' => $debugLines,
            'smtp_last_response' => $smtpLastResponse,
            'message_id' => method_exists($mail, 'getLastMessageID') ? $mail->getLastMessageID() : null,
        ];
    } catch (PHPMailerException $e) {
        $smtpLastResponse = null;
        $smtpInstance = $mail->getSMTPInstance();
        if ($smtpInstance && method_exists($smtpInstance, 'getLastResponse')) {
            $smtpLastResponse = $smtpInstance->getLastResponse();
        }

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'smtp_debug' => $debugLines,
            'smtp_last_response' => $smtpLastResponse,
            'message_id' => method_exists($mail, 'getLastMessageID') ? $mail->getLastMessageID() : null,
        ];
    }
}

/**
 * Render an email body from a hidden post template using Twig-style placeholders.
 *
 * - $stub: post stub to load (content type: post)
 * - $data: associative array of variables available in the template
 *
 * The post body is treated as a Twig template and rendered against $data.
 * If the post frontmatter contains a "subject" field, it will be returned as subject.
 */
function render_email_from_post_template(string $stub, array $data): array
{
    // Ensure content functions are available
    if (!function_exists('get_content_by_stub')) {
        require_once BASE_PATH . '/includes/content/core.php';
    }
    if (!function_exists('_content_process_markdown')) {
        require_once BASE_PATH . '/includes/content/processor.php';
    }

    $post = get_content_by_stub('post', $stub, true);
    if (!$post) {
        return [
            'subject' => null,
            'html' => null,
        ];
    }

    // Extract subject from metadata if available
    $subject = null;
    if (!empty($post['subject'])) {
        $subject = $post['subject'];
    } elseif (!empty($post['title'])) {
        $subject = $post['title'];
    }

    $content = $post['content'] ?? '';
    if ($content === '') {
        return [
            'subject' => $subject,
            'html' => null,
        ];
    }

    // Use Twig to treat the post body as a template with placeholders
    /** @var \Twig\Environment $twig */
    $twig = $GLOBALS['twig'];
    $template = $twig->createTemplate($content);
    $html = $template->render($data);

    return [
        'subject' => $subject,
        'html' => $html,
    ];
}


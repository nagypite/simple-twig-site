<?php

/**
 * Get full configuration for all contact forms.
 *
 * @return array
 */
function get_contact_forms_config(): array
{
    return $GLOBALS['config']['contact_forms'] ?? [];
}

/**
 * Get configuration for a specific contact form type.
 *
 * @param string $type
 * @return array|null
 */
function get_contact_form_config(string $type): ?array
{
    $forms = get_contact_forms_config();
    return $forms[$type] ?? null;
}

/**
 * Sanitize an email (or any string) for use in a log filename.
 *
 * @param string|null $email
 * @return string
 */
function contact_sanitize_email_for_filename(?string $email): string
{
    if ($email === null || $email === '') {
        return 'no-email';
    }
    $s = str_replace('@', '-at-', $email);
    $s = preg_replace('/[^a-zA-Z0-9._-]/', '_', $s);
    return $s !== '' ? $s : 'no-email';
}

/**
 * Build a plain-text email body from normalized form data (for multipart/alternative).
 *
 * @param array $normalized
 * @param array $config contact form config with 'fields' and 'consents'
 * @return string
 */
function contact_build_plain_text_body(array $normalized, array $config): string
{
    $fields = $config['fields'] ?? [];
    $consents = $config['consents'] ?? [];
    $lines = ["Új tagsági jelentkezés érkezett a weboldalról.", ""];

    foreach ($fields as $name => $fieldConfig) {
        $label = $fieldConfig['label'] ?? $name;
        $value = $normalized[$name] ?? '';
        if ((string) $value !== '') {
            $lines[] = $label . ': ' . trim(str_replace(["\r\n", "\r"], "\n", $value));
            $lines[] = '';
        }
    }

    foreach ($consents as $name => $consentConfig) {
        $label = $consentConfig['label'] ?? $name;
        $lines[] = $label . ': ' . (!empty($normalized[$name]) ? 'Elfogadva' : '–');
        $lines[] = '';
    }

    $lines[] = "Ez az üzenet a tagsági jelentkezési űrlapból lett automatikusan elküldve.";
    return implode("\n", $lines);
}

/**
 * Find contact form type by path (resolved menu path).
 *
 * @param string $path
 * @return string|null
 */
function get_contact_form_type_for_path(string $path): ?string
{
    $forms = get_contact_forms_config();
    foreach ($forms as $type => $config) {
        if (!empty($config['path']) && $config['path'] === $path) {
            return $type;
        }
    }
    return null;
}

/**
 * Handle a contact form submission for a given type.
 *
 * Returns an array:
 * - success: bool
 * - errors: array (field => message)
 * - spam: bool
 * - email_error: string|null
 * - redirect_path: string|null
 * - data: normalized field data for re-populating the form
 */
function handle_contact_form(string $type, array $request): array
{
    $config = get_contact_form_config($type);
    if (!$config) {
        return [
            'success' => false,
            'errors' => ['form' => 'Ismeretlen űrlap.'],
            'spam' => false,
            'email_error' => null,
            'redirect_path' => null,
            'data' => [],
        ];
    }

    $fields = $config['fields'] ?? [];
    $consents = $config['consents'] ?? [];
    $honeypotField = $config['honeypot_field'] ?? null;
    $minFillSeconds = isset($config['min_fill_seconds']) ? (int)$config['min_fill_seconds'] : 5;
    $maxFillSeconds = isset($config['max_fill_seconds']) ? (int)$config['max_fill_seconds'] : 1800;

    $normalized = [];
    $errors = [];
    $isSpam = false;

    // Timing protection using session
    $startTime = null;
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['contact_form_start'][$type])) {
        $startTime = (int)$_SESSION['contact_form_start'][$type];
    }
    $now = time();
    if ($startTime) {
        $duration = $now - $startTime;
        if ($duration < $minFillSeconds || $duration > $maxFillSeconds) {
            $isSpam = true;
        }
    }

    // Honeypot field
    if ($honeypotField && !empty($request[$honeypotField])) {
        $isSpam = true;
    }

    // Normalize and validate fields
    foreach ($fields as $name => $fieldConfig) {
        $value = trim((string)($request[$name] ?? ''));
        $normalized[$name] = $value;

        $required = !empty($fieldConfig['required']);
        $typeHint = $fieldConfig['type'] ?? 'string';

        if ($required && $value === '') {
            $errors[$name] = 'Kötelező mező.';
            continue;
        }

        if ($value !== '' && $typeHint === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Érvényes e-mail címet adj meg.';
            }
        }
    }

    // Validate consents (checkboxes)
    foreach ($consents as $name => $consentConfig) {
        $checked = !empty($request[$name]);
        $normalized[$name] = $checked ? '1' : '0';

        if (!empty($consentConfig['required']) && !$checked) {
            $errors[$name] = 'A nyilatkozat elfogadása kötelező.';
        }
    }

    // Show a single top-level message when any validation failed (so the user sees why they were sent back)
    if (!empty($errors) && !isset($errors['form'])) {
        $errors['form'] = 'Kérjük, töltse ki az összes kötelező mezőt, és fogadja el a nyilatkozatokat.';
    }

    // Always clear the start time after one submission attempt
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['contact_form_start'][$type]);
    }

    $emailError = null;

    // If we detected spam, we do not send email but respond with generic error
    if ($isSpam) {
        $errors['form'] = 'A beküldés nem feldolgozható. Kérjük, próbáld újra.';
    }

    $success = empty($errors) && !$isSpam;

    // Prepare logging directory
    $logDirName = $config['log_dir'] ?? 'applications';
    $logDir = LOG_PATH . '/' . $logDirName;
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Base filename: timestamp + sanitized email + uniqid (used for both .json and .html)
    $replyToField = $config['reply_to_field'] ?? null;
    $emailForFilename = $replyToField && isset($normalized[$replyToField])
        ? $normalized[$replyToField]
        : $type;
    $logBase = date('Ymd-His') . '-' . contact_sanitize_email_for_filename($emailForFilename) . '-' . uniqid('', true);

    // Log entry data
    $logEntry = [
        'type' => $type,
        'timestamp' => date('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'data' => $normalized,
        'is_spam' => $isSpam,
        'validation_errors' => $errors,
        'email' => [
            'attempted' => false,
            'success' => false,
            'error' => null,
        ],
    ];

    $emailSubject = null;
    $emailHtml = null;

    if ($success) {
        // Prepare email
        $templateStub = $config['email_template_stub'] ?? null;
        $emailSubject = $config['email_subject'] ?? 'Új kapcsolatfelvétel';

        if ($templateStub) {
            $templateData = array_merge($normalized, [
                'siteurl' => rtrim($GLOBALS['config']['siteurl'] ?? '', '/'),
            ]);
            $rendered = render_email_from_post_template($templateStub, $templateData);
            if (!empty($rendered['subject'])) {
                $emailSubject = $rendered['subject'];
            }
            if (!empty($rendered['html'])) {
                $emailHtml = $rendered['html'];
            }
        }

        if ($emailHtml === null) {
            // Fallback plain HTML if no template rendered
            $lines = [];
            foreach ($normalized as $k => $v) {
                $lines[] = htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . ': ' . nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
            }
            $emailHtml = implode("<br>\n", $lines);
        }

        // Resolve recipients from config path (e.g. "membership.recipients")
        $recipients = [];
        if (!empty($config['recipient_config_key'])) {
            $parts = explode('.', $config['recipient_config_key']);
            $cfg = $GLOBALS['config'];
            foreach ($parts as $part) {
                if (!is_array($cfg) || !array_key_exists($part, $cfg)) {
                    $cfg = null;
                    break;
                }
                $cfg = $cfg[$part];
            }
            if (is_array($cfg)) {
                $recipients = $cfg;
            }
        } elseif (!empty($config['recipients']) && is_array($config['recipients'])) {
            $recipients = $config['recipients'];
        }

        $replyTo = null;
        if (!empty($config['reply_to_field']) && !empty($normalized[$config['reply_to_field']])) {
            $replyTo = $normalized[$config['reply_to_field']];
        }

        $emailText = contact_build_plain_text_body($normalized, $config);

        $emailDebug = !empty($GLOBALS['config']['email_debug']);
        $smtpConf = $GLOBALS['config']['smtp'] ?? [];
        $logEntry['email']['to'] = $recipients;
        $logEntry['email']['from'] = [
            'address' => $smtpConf['from_address'] ?? ($smtpConf['username'] ?? null),
            'name' => $smtpConf['from_name'] ?? 'simple-twig-site',
        ];
        $logEntry['email']['subject'] = $emailSubject;
        $logEntry['email']['reply_to'] = $replyTo;
        $logEntry['email']['smtp_verbose'] = !empty($GLOBALS['config']['email_verbose']);

        if ($emailDebug) {
            $logEntry['email']['mode'] = 'debug_skip';
            $sendResult = ['success' => true, 'error' => null];
            $logEntry['email']['debug_skip'] = true;
        } else {
            $logEntry['email']['mode'] = 'send';
            if (empty($recipients)) {
                $sendResult = [
                    'success' => false,
                    'error' => 'No recipients configured for this contact form.',
                    'smtp_debug' => [],
                    'smtp_last_response' => null,
                    'message_id' => null,
                ];
            } else {
                $sendResult = send_email([
                    'to' => $recipients,
                    'subject' => $emailSubject,
                    'html' => $emailHtml,
                    'text' => $emailText,
                    'reply_to' => $replyTo,
                ]);
            }
        }

        $logEntry['email']['attempted'] = true;
        $logEntry['email']['success'] = $sendResult['success'];
        $logEntry['email']['error'] = $sendResult['error'];
        $logEntry['email']['smtp_last_response'] = $sendResult['smtp_last_response'] ?? null;
        $logEntry['email']['smtp_debug'] = $sendResult['smtp_debug'] ?? [];
        $logEntry['email']['message_id'] = $sendResult['message_id'] ?? null;

        if (!$sendResult['success']) {
            $success = false;
            $emailError = $sendResult['error'] ?? 'Ismeretlen hiba történt az e-mail küldésekor.';
            $errors['form'] = 'Nem sikerült elküldeni az üzenetet. Kérjük, próbáld meg később újra.';
        }
    }

    // Write log file (timestamp + sanitized email in filename)
    $logFile = $logDir . '/' . $logBase . '.json';
    @file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Save outgoing mail to file when we built one (same base name, .html)
    if ($emailHtml !== null) {
        $mailFile = $logDir . '/' . $logBase . '.html';
        $mailContent = "<!-- Subject: " . str_replace(['<!--', '-->'], '', $emailSubject ?? '') . " -->\n"
            . "<!-- Sent: " . date('c') . " -->\n\n"
            . $emailHtml;
        @file_put_contents($mailFile, $mailContent);
    }

    $redirectPath = null;
    if ($success && !empty($config['success_redirect'])) {
        $redirectPath = $config['success_redirect'];
    }

    return [
        'success' => $success,
        'errors' => $errors,
        'spam' => $isSpam,
        'email_error' => $emailError,
        'redirect_path' => $redirectPath,
        'data' => $normalized,
    ];
}


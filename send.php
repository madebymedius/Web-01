<?php
declare(strict_types=1);

const RECIPIENT = 'info@curuvija-ausbaugmbh.de';
const SENDER = 'noreply@curuvija-ausbaugmbh.de';
const SUBJECT_PREFIX = 'Anfrage über die Website';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function respond(bool $ok, bool $json): never
{
    http_response_code($ok ? 200 : 422);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok]);
    } else {
        $back = strtok($_SERVER['HTTP_REFERER'] ?? '/', '#') ?: '/';
        header('Location: ' . $back . '#' . ($ok ? 'poslato' : 'greska'), true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, $wantsJson);
}

if (trim((string)($_POST['website'] ?? '')) !== '') {
    respond(true, $wantsJson);
}

$clean = static fn(string $key, int $max): string => mb_substr(trim(str_replace(["\r", "\0"], '', (string)($_POST[$key] ?? ''))), 0, $max);

$name = $clean('name', 160);
$email = $clean('email', 190);
$phone = $clean('phone', 60);
$message = $clean('message', 5000);
$page = $clean('page', 200);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($_POST['privacy'])) {
    respond(false, $wantsJson);
}

$oneLine = static fn(string $v): string => preg_replace('/[\n\t]+/', ' ', $v);

$body = implode("\n", [
    'Name: ' . $oneLine($name),
    'E-Mail: ' . $email,
    'Telefon: ' . $oneLine($phone),
    'Seite: ' . $oneLine($page),
    '',
    $message !== '' ? $message : 'Bitte um Rückruf.',
]);

$headers = [
    'From' => SENDER,
    'Reply-To' => $email,
    'Content-Type' => 'text/plain; charset=UTF-8',
    'X-Mailer' => 'PHP',
];

$title = SUBJECT_PREFIX . ': ' . $oneLine($name);
$sent = mail(RECIPIENT, '=?UTF-8?B?' . base64_encode($title) . '?=', $body, $headers);

respond($sent, $wantsJson);

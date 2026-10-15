<?php
// send.php - priima kontaktų formos duomenis ir siunčia el. laišką.
// Įkelkite šalia index.html. Reikia PHP su veikiančia mail() funkcija.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const TO_EMAIL   = 'info@ai4design.lt';      // kur gausite žinutes
const FROM_EMAIL = 'noreply@ai4design.lt';   // PRIVALO būti jūsų domeno adresas (kitaip laiškai krenta į šlamštą)

function out($ok, $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode($error ? ['ok' => $ok, 'error' => $error] : ['ok' => $ok], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Netinkamas metodas.', 405);

$d = json_decode(file_get_contents('php://input'), true);
if (!is_array($d)) out(false, 'Neteisingi duomenys.', 400);

// Paslėptas laukelis: žmonės jo neužpildo, robotai užpildo. Apsimetame, kad pavyko.
if (!empty($d['website'])) out(true);

$name    = trim((string)($d['name'] ?? ''));
$email   = trim((string)($d['email'] ?? ''));
$message = trim((string)($d['message'] ?? ''));

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, 'Užpildykite visus laukus ir įveskite teisingą el. pašto adresą.', 422);
}
if (mb_strlen($name) > 100 || mb_strlen($email) > 150 || mb_strlen($message) > 5000) {
    out(false, 'Per ilgas tekstas.', 422);
}
// Apsauga nuo antraščių injekcijos
if (preg_match('/[\r\n]/', $name . $email)) out(false, 'Neteisingi duomenys.', 422);

// Paprastas apribojimas: viena žinutė per 30 s iš to paties IP
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'x';
$lock = sys_get_temp_dir() . '/ai4d_' . md5($ip);
if (is_file($lock) && time() - filemtime($lock) < 30) out(false, 'Palaukite kelias sekundes ir bandykite dar kartą.', 429);
touch($lock);

$subject = '=?UTF-8?B?' . base64_encode('Nauja žinutė iš ai4design.lt') . '?=';
$body    = "Vardas: $name\nEl. paštas: $email\n\nŽinutė:\n$message\n";
$headers = [
    'From: AI 4 DESIGN <' . FROM_EMAIL . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];
$name = str_replace(['<', '>', '"'], '', $name);
$headers[1] = 'Reply-To: ' . $name . ' <' . $email . '>';

$sent = mail(TO_EMAIL, $subject, $body, implode("\r\n", $headers), '-f' . FROM_EMAIL);
if (!$sent) out(false, 'Nepavyko išsiųsti. Rašykite adresu ' . TO_EMAIL . '.', 500);
out(true);

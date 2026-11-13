<?php

require_once __DIR__ . '/../management/email-settings.php';
require_once __DIR__ . '/../management/thingsboard-client.php';

function notificationDay(): string
{
	return (new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
}

function notificationRequest(string $path, string $token): array
{
	$response = thingsBoardManagementRequest('GET', $path, null, $token);
	if ($response['status'] !== 200 || !is_array($response['body'])) {
		$detail = $response['body']['message'] ?? $response['body']['error'] ?? null;
		$detail = is_string($detail) ? trim(preg_replace('/\s+/', ' ', strip_tags($detail))) : '';
		throw new RuntimeException('ThingsBoard GET ' . $path . ' fehlgeschlagen (HTTP ' . $response['status'] . ')' . ($detail !== '' ? ': ' . mb_substr($detail, 0, 500) : '.'));
	}
	return $response['body'];
}

function notificationUsers(string $token): array
{
	$users = [];
	$page = 0;
	do {
		// The CE API lists user summaries; fetch each profile for authority/customer filtering.
		$result = notificationRequest('/users/info?pageSize=100&page=' . $page . '&sortProperty=createdTime&sortOrder=ASC', $token);
		if (!is_array($result['data'] ?? null) || !is_bool($result['hasNext'] ?? null)) {
			throw new RuntimeException('Ungültige ThingsBoard-Nutzerliste.');
		}
		foreach ($result['data'] as $user) {
			$id = $user['id']['id'] ?? null;
			if (!is_string($id) || $id === '') throw new RuntimeException('Ungültige ThingsBoard-Nutzer-ID.');
			$user = notificationRequest('/user/' . rawurlencode($id), $token);
			$authority = $user['authority'] ?? '';
			if (is_string($id) && ($authority === 'TENANT_ADMIN' || ($authority === 'CUSTOMER_USER'
				&& ($user['customerId']['id'] ?? '') === WASSERKARTE_CUSTOMER_ID))) {
				$users[$id] = $user;
			}
		}
		$page++;
	} while ($result['hasNext']);
	return $users;
}

function notificationSettings(string $userId, string $token): ?array
{
	$attributes = notificationRequest('/plugins/telemetry/USER/' . rawurlencode($userId) . '/values/attributes/SERVER_SCOPE?keys=wasserkarte_email_settings', $token);
	foreach ($attributes as $attribute) {
		if (($attribute['key'] ?? '') !== 'wasserkarte_email_settings') continue;
		$settings = managementEmailSettingsFromValue($attribute['value'] ?? null);
		if ($settings === null) throw new RuntimeException('Ungültige Benachrichtigungseinstellungen.');
		return $settings;
	}
	return null;
}

function notificationData(bool $requireFresh): array
{
	$devices = json_decode((string) @file_get_contents(CACHE_FILE_DEVICES), true);
	$telemetry = json_decode((string) @file_get_contents(CACHE_FILE_ALLTELEMETRY), true);
	if (!is_array($devices['devices'] ?? null) || !is_array($telemetry['devices'] ?? null)) {
		throw new RuntimeException('Gerätedaten oder Tagesmittelwerte fehlen.');
	}
	$midnight = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->getTimestamp();
	if ($requireFresh && (int) filemtime(CACHE_FILE_ALLTELEMETRY) < $midnight) {
		throw new RuntimeException('Tagesmittelwerte wurden heute noch nicht aktualisiert.');
	}
	return [$devices['devices'], $telemetry['devices']];
}

function notificationRows(array $settings, array $devices, array $telemetry, string $day): array
{
	$selected = array_flip($settings['locations']);
	$rows = [];
	foreach ($devices as $device) {
		$id = $device['id'] ?? '';
		if (!isset($selected[$id])) continue;
		$series = $telemetry[$id] ?? [];
		$values = [];
		foreach (array_reverse($series['data'] ?? []) as $row) {
			// Daily cache rows use UTC midnight as the calendar-day label.
			if (!is_numeric($row[0] ?? null) || gmdate('Y-m-d', (int) ($row[0] / 1000)) !== $day) continue;
			foreach ($series['schema'] ?? [] as $index => $key) {
				$value = $row[$index] ?? null;
				$values[$key] = is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
			}
			break;
		}
		$nfk = $values['nfk_avg'] ?? null;
		if ($settings['threshold'] !== 'always' && ($nfk === null || $nfk >= $settings['threshold'])) continue;
		$rows[] = ['device' => $device, 'values' => $values];
	}
	usort($rows, static function ($a, $b): int {
		$name = static function (array $row): string {
			$device = $row['device'];
			return strtr(mb_strtolower((string) ($device['attributes']['Anzeigename'] ?? $device['name'] ?? $device['id']), 'UTF-8'), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
		};
		return strcasecmp($name($a), $name($b));
	});
	return $rows;
}

function notificationLevel(?float $value): string
{
	if ($value === null) return 'Keine Messwerte';
	if ($value <= 0) return 'Kein Wasser verfügbar';
	if ($value < 30) return 'Sehr trocken';
	if ($value < 50) return 'Leicht trocken';
	if ($value < 90) return 'Optimal';
	if ($value < 110) return 'Nass';
	return 'Sehr nass';
}

function notificationColor(?float $value): string
{
	if ($value === null) return '#eeeeee';
	// Same interpolated standard palette as js/datamodel.js.
	$stops = [[0, 'ff8e70'], [10, 'ffcf4c'], [40, 'feff4c'], [70, '9ff24c'], [100, '4ce6cc'], [120, '55c9f0']];
	if ($value <= 0) return '#' . $stops[0][1];
	if ($value >= 120) return '#' . $stops[5][1];
	for ($i = 1; $i < count($stops); $i++) {
		if ($value > $stops[$i][0]) continue;
		[$low, $start] = $stops[$i - 1];
		[$high, $end] = $stops[$i];
		$color = '#';
		for ($channel = 0; $channel < 6; $channel += 2) {
			$a = hexdec(substr($start, $channel, 2));
			$b = hexdec(substr($end, $channel, 2));
			$color .= sprintf('%02x', (int) round($a + ($b - $a) * ($value - $low) / ($high - $low)));
		}
		return $color;
	}
	return '#eeeeee';
}

function notificationDarkenColor(string $color, float $amount = 0.10): string
{
	$darkened = '#';
	for ($channel = 1; $channel < 7; $channel += 2) {
		$darkened .= sprintf('%02x', (int) round(hexdec(substr($color, $channel, 2)) * (1 - $amount)));
	}
	return $darkened;
}

function notificationEscape(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function notificationNumber(?float $value, int $decimals = 1): string
{
	return $value === null ? '–' : number_format($value, $decimals, ',', '.');
}

function notificationMessage(array $rows, string $day, $threshold, string $style = 'detailed'): array
{
	$reportDay = new DateTimeImmutable($day);
	$months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
	$date = $reportDay->format('j') . '. ' . $months[(int) $reportDay->format('n') - 1] . ' ' . $reportDay->format('Y');
	$title = 'Wasserkarte Bericht ' . $date;
	$selection = $threshold === 'always' ? '' : 'Ausgewählte Standorte ab ' . [0 => 'Kein Wasser verfügbar', 30 => 'Sehr Trocken', 50 => 'Leicht Trocken'][$threshold] . ' (Ø nFK < ' . $threshold . '%)';
	$text = $title . "\n\n" . ($selection !== '' ? $selection . "\n\n" : '');
	if ($style === 'compact') $text .= "Modellierte Durchschnittswerte Oberboden 1m² bis 60cm Tiefe\n\n";
	$baseUrl = defined('WASSERKARTE_URL') ? rtrim(WASSERKARTE_URL, '/') : '';
	$html = '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><body style="margin:0;background:#fff;color:#111;font-family:Arial,sans-serif;font-size:11px"><table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:20px 8px"><table width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#fff"><tr><td style="padding:24px 0"><h1 style="font-size:18px;font-weight:700;margin:0 0 6px;text-align:center;color:#000"><img src="' . notificationEscape($baseUrl . '/img/tropfen.png') . '" width="15" height="24" alt="" style="width:15px;height:24px;vertical-align:middle;margin-right:8px;position:relative;top:-2px;opacity:0.8">Wasserkarte Bericht ' . $date . '</h1>' . ($selection !== '' || $style === 'compact' ? '<p style="margin:0 0 12px;font-size:11px;line-height:18px;text-align:center;color:rgba(0,0,0,0.6)">' . notificationEscape($selection) . ($style === 'compact' ? ($selection !== '' ? '<br>' : '') . 'Modellierte Durchschnittswerte Oberboden 1m² bis 60cm Tiefe' : '') . '</p>' : '');
	if (!$rows) {
		$note = 'Für diese Auswahl liegen keine passenden Tagesmittelwerte vor.';
		$html .= '<p style="font-size:11px;line-height:18px;color:rgba(0,0,0,0.6)">' . $note . '</p>';
		$text .= $note . "\n";
	}
	if ($style === 'compact' && $rows) {
		$html .= '<table align="center" cellpadding="4" cellspacing="0" style="width:auto;max-width:100%;margin:44px auto 0;font-size:11px;text-align:left;line-height:18px;border-collapse:separate;border-spacing:0">';
	}
	foreach (array_values($rows) as $rowIndex => $row) {
		$device = $row['device'];
		$values = $row['values'];
		$name = (string) ($device['attributes']['Anzeigename'] ?? $device['name'] ?? $device['id']);
		$nfk = $values['nfk_avg'] ?? null;
		$nfk = $nfk === null ? null : max(0, $nfk);
		$volAvg = notificationNumber($values['vol_avg'] ?? null);
		$level = notificationLevel($nfk);
		$url = $baseUrl . '/standort/' . rawurlencode((string) ($device['name'] ?? ''));
		if ($style === 'compact') {
			$cellStyle = 'background:' . notificationColor($nfk) . ';padding:7px 7px;text-align:left;white-space:nowrap;';
			$topRadius = $rowIndex === 0 ? '6px' : '0';
			$bottomRadius = $rowIndex === count($rows) - 1 ? '6px' : '0';
			$html .= '<tr><td style="' . $cellStyle . 'border-radius:' . $topRadius . ' 0 0 ' . $bottomRadius . ';padding-left:10px;padding-right:12px;font-size:12px;font-weight:bold;color:#000;white-space:nowrap"><a style="display:block;color:inherit;text-decoration:none" href="' . notificationEscape($url) . '">' . notificationEscape($name) . '</a></td>';
			$html .= '<td style="' . $cellStyle . 'color:rgba(0,0,0,0.6);padding-right:12px;font-size:11px">' . $level . '</td>';
			$html .= '<td style="' . $cellStyle . 'text-align:right;color:rgba(0,0,0,0.6);white-space:nowrap">' . $volAvg . ' Vol. %</td>';
			$html .= '<td style="' . $cellStyle . 'border-radius:0 ' . $topRadius . ' ' . $bottomRadius . ' 0;padding-right:10px;text-align:right;white-space:nowrap"><strong>' . notificationNumber($nfk, 0) . '</strong> <span style="color:rgba(0,0,0,0.6)">nFK %</span></td></tr>';
			$text .= $name . ': ' . $level . ' · Ø ' . $volAvg . ' Vol. % · Ø ' . notificationNumber($nfk, 0) . " nFK %\n" . $url . "\n\n";
			continue;
		}
		$html .= '<h2 style="font-size:16px;margin:44px 0 2px;text-align:center"><a style="color:#111;text-decoration:none" href="' . notificationEscape($url) . '">' . notificationEscape($name) . '</a></h2>';
		$text .= $name . ': ' . $level . ' · Ø ' . $volAvg . ' Vol. % · Ø ' . notificationNumber($nfk, 0) . " nFK %\n";
		$html .= '<table width="332" align="center" cellpadding="4" cellspacing="0" style="width:332px;max-width:100%;margin:0 auto;font-size:11px;text-align:left;line-height:18px;border-collapse:separate;border-spacing:0">';
		$html .= '<tr><td style="padding:4px 4px 2px;text-align:right;color:rgba(0,0,0,0.6);white-space:nowrap">Ø</td><td style="padding:4px 4px 2px;text-align:left;font-size:11px;font-weight:bold;letter-spacing:0.03em;text-transform:uppercase;color:' . notificationDarkenColor(notificationColor($nfk)) . '">' . mb_strtoupper($level, 'UTF-8') . '</td><td style="padding:4px 4px 2px;text-align:right;color:rgba(0,0,0,0.6);white-space:nowrap">' . $volAvg . ' Vol. %</td><td style="padding:4px 4px 2px;text-align:right;white-space:nowrap"><strong>' . notificationNumber($nfk, 0) . '</strong> <span style="color:rgba(0,0,0,0.6)">nFK %</span></td></tr>';
		$depths = array_values(array_filter([10, 30, 60, 80], static fn ($depth) =>
			array_key_exists('Bodenfeuchte_' . $depth . 'cm', $values) || array_key_exists('nfk_' . $depth . 'cm', $values)));
		foreach ($depths as $index => $depth) {
			$volKey = 'Bodenfeuchte_' . $depth . 'cm';
			$nfkKey = 'nfk_' . $depth . 'cm';
			$depthNfk = $values[$nfkKey] ?? null;
			$depthNfk = $depthNfk === null ? null : max(0, $depthNfk);
			$vol = notificationNumber($values[$volKey] ?? null);
			$nfkText = notificationNumber($depthNfk, 0);
			$cellStyle = 'background:' . notificationColor($depthNfk) . ';';
			$topRadius = $index === 0 ? '6px' : '0';
			$bottomRadius = $index === count($depths) - 1 ? '6px' : '0';
			$html .= '<tr><td style="' . $cellStyle . 'border-radius:' . $topRadius . ' 0 0 ' . $bottomRadius . ';text-align:right;color:rgba(0,0,0,0.6);white-space:nowrap">' . $depth . ' cm</td>';
			$html .= '<td style="' . $cellStyle . 'color:rgba(0,0,0,0.6);text-align:left;font-size:11px">' . notificationLevel($depthNfk) . '</td>';
			$html .= '<td style="' . $cellStyle . 'text-align:right;color:rgba(0,0,0,0.6);white-space:nowrap">' . $vol . ' Vol. %</td>';
			$html .= '<td style="' . $cellStyle . 'border-radius:0 ' . $topRadius . ' ' . $bottomRadius . ' 0;text-align:right;white-space:nowrap"><strong>' . $nfkText . '</strong> <span style="color:rgba(0,0,0,0.6)">nFK %</span></td></tr>';
			$text .= "  {$depth} cm: {$vol} Vol. %, {$nfkText} nFK %\n";
		}
		$html .= '</table>';
		$text .= $url . "\n\n";
	}
	if ($style === 'compact' && $rows) $html .= '</table>';
	$html .= '<p style="margin-top:56px;font-size:11px;line-height:18px;text-align:center;color:rgba(0,0,0,0.6)">Benachrichtigungen können im Account-Menü der <a href="http://wasserkarte.org" style="color:#1b6bb8">Wasserkarte</a><br>geändert oder deaktiviert werden. Alle Angaben ohne Gewähr.</p></td></tr></table></td></tr></table></body></html>';
	$text .= "Alle Angaben ohne Gewähr.\n";
	return ['subject' => $title, 'html' => $html, 'text' => $text];
}

function notificationSend(string $email, array $message): void
{
	if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !defined('MAIL_FROM') || !filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
		throw new RuntimeException('Absender oder Empfänger ist ungültig.');
	}
	$boundary = 'wasserkarte_' . bin2hex(random_bytes(16));
	$headers = 'From: Wasserkarte <' . MAIL_FROM . ">\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"";
	$body = '';
	foreach (['plain' => 'text', 'html' => 'html'] as $type => $key) {
		$body .= "--{$boundary}\r\nContent-Type: text/{$type}; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($message[$key]), 76, "\r\n");
	}
	$body .= "--{$boundary}--\r\n";
	if (!mail($email, '=?UTF-8?B?' . base64_encode($message['subject']) . '?=', $body, $headers)) {
		throw new RuntimeException('Die E-Mail konnte nicht an den Mailserver übergeben werden.');
	}
}

function notificationWithLock(string $key, callable $callback)
{
	$directory = defined('NOTIFICATION_STORAGE_DIR') ? NOTIFICATION_STORAGE_DIR : __DIR__ . '/../storage/notifications';
	if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Versandprotokoll nicht verfügbar.');
	$handle = fopen($directory . '/' . hash('sha256', $key) . '.json', 'c+');
	if ($handle === false) throw new RuntimeException('Versandprotokoll nicht verfügbar.');
	chmod($directory . '/' . hash('sha256', $key) . '.json', 0600);
	try {
		if (!flock($handle, LOCK_EX)) throw new RuntimeException('Versandprotokoll konnte nicht gesperrt werden.');
		$contents = stream_get_contents($handle);
		$record = $contents === '' ? [] : json_decode($contents, true);
		if (!is_array($record)) throw new RuntimeException('Versandprotokoll ist beschädigt.');
		$write = static function (array $record) use ($handle): void {
			$json = json_encode($record, JSON_THROW_ON_ERROR);
			rewind($handle);
			if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('Versandprotokoll konnte nicht geschrieben werden.');
			if (function_exists('fsync')) fsync($handle);
		};
		return $callback($record, $write);
	} finally {
		flock($handle, LOCK_UN);
		fclose($handle);
	}
}

function notificationTest(string $token, string $userId, array $settings): void
{
	notificationWithLock('test:' . $userId, static function ($record, $write) use ($token, $userId, $settings): void {
		if (($record['time'] ?? 0) > time() - 60) throw new RuntimeException('Bitte eine Minute vor der nächsten Test-E-Mail warten.');
		$write(['time' => time()]);
		// Exercise the same recipient lookup as cron, but send only to this account.
		$users = notificationUsers($token);
		$profile = $users[$userId] ?? null;
		if ($profile === null) throw new RuntimeException('Der angemeldete Account fehlt in der ThingsBoard-Empfängerliste.');
		notificationSettings($userId, $token);
		[$devices, $telemetry] = notificationData(false);
		$day = notificationDay();
		$rows = notificationRows($settings, $devices, $telemetry, $day);
		notificationSend((string) ($profile['email'] ?? ''), notificationMessage($rows, $day, $settings['threshold'], $settings['style']));
	});
}

function notificationDaily(string $token): array
{
	[$devices, $telemetry] = notificationData(true);
	$day = notificationDay();
	$result = ['sent' => 0, 'errors' => 0];
	foreach (notificationUsers($token) as $id => $user) {
		try {
			$settings = notificationSettings($id, $token);
			if (!$settings || !$settings['enabled'] || !$settings['locations']) continue;
			$rows = notificationRows($settings, $devices, $telemetry, $day);
			if (!$rows) continue;
			$sent = notificationWithLock('daily:' . $id . ':' . $day, static function ($record, $write) use ($user, $rows, $day, $settings): bool {
				if (($record['status'] ?? '') === 'sent') return false;
				if (($record['status'] ?? '') === 'sending') throw new RuntimeException('Vorheriger Versandstatus unklar; manuelle Prüfung erforderlich.');
				$message = notificationMessage($rows, $day, $settings['threshold'], $settings['style']);
				$write(['status' => 'sending', 'time' => time()]);
				try {
					notificationSend((string) ($user['email'] ?? ''), $message);
				} catch (Throwable $error) {
					$write(['status' => 'failed', 'time' => time()]);
					throw $error;
				}
				$write(['status' => 'sent', 'time' => time()]);
				return true;
			});
			if ($sent) $result['sent']++;
		} catch (Throwable $error) {
			$result['errors']++;
			error_log('Benachrichtigung für ' . $id . ': ' . $error->getMessage());
		}
	}
	return $result;
}

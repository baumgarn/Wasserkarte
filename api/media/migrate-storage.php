<?php

// Einmalige Migration; über HTTP niemals ausführbar.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

function migrateMediaStorage(string $source, string $target): bool
{
	if (!file_exists($source) && !is_link($source)) return false;
	if (!is_dir($source) || is_link($source)) {
		throw new RuntimeException('Der alte Medienspeicher muss ein reguläres Verzeichnis sein.');
	}
	if (file_exists($target) || is_link($target)) {
		throw new RuntimeException('Der neue Medienspeicher existiert bereits. Keine Daten wurden überschrieben; bitte beide Verzeichnisse prüfen.');
	}
	if (!is_dir(dirname($target))) {
		throw new RuntimeException('Das übergeordnete Zielverzeichnis muss bereits existieren.');
	}
	// Ganzes Verzeichnis übernehmen: Metadaten, Varianten, lokale Uploads und Zugriffsschutz.
	// Kein Kopieren/Löschen als Fallback: bei einem Fehler bleibt die Quelle erhalten.
	if (!@rename($source, $target)) {
		throw new RuntimeException('Medienspeicher konnte nicht verschoben werden. Rechte und Dateisystem prüfen; die Quelle bleibt erhalten.');
	}
	return true;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	try {
		require_once __DIR__ . '/../config.php';
		$source = CACHE_DIR . '/media';
		$target = defined('MEDIA_STORAGE_DIR') ? MEDIA_STORAGE_DIR : dirname(__DIR__) . '/storage';
		$moved = migrateMediaStorage($source, $target);
		echo $moved ? "Medienspeicher nach $target verschoben.\n" : "Kein alter Medienspeicher vorhanden; keine Änderung.\n";
	} catch (Throwable $error) {
		fwrite(STDERR, $error->getMessage() . "\n");
		exit(1);
	}
}

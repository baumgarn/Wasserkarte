<?php

require_once __DIR__ . '/storage.php';

function mediaProcessImage(string $source, string $id, bool $local): array
{
	if (!function_exists('imagewebp') || !function_exists('imagecreatefromjpeg')) {
		throw new RuntimeException('Der Server benötigt PHP GD mit JPEG-, PNG- und WebP-Unterstützung.');
	}
	$size = filesize($source);
	if ($size === false || $size <= 0 || $size > MEDIA_MAX_BYTES) {
		throw new InvalidArgumentException('Ein Bild darf höchstens 10 MB groß sein.');
	}
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
	$dimensions = @getimagesize($source);
	if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !is_array($dimensions)
		|| ($dimensions['mime'] ?? null) !== $mime) {
		throw new InvalidArgumentException('Bitte ein JPEG-, PNG- oder WebP-Bild auswählen.');
	}
	$width = (int) $dimensions[0];
	$height = (int) $dimensions[1];
	if ($width <= 0 || $height <= 0 || $width * $height > MEDIA_MAX_PIXELS) {
		throw new InvalidArgumentException('Ein Bild darf höchstens 24 Megapixel enthalten.');
	}
	// Auch bei kleinen komprimierten Dateien die Speichergrenze vor dem Dekodieren prüfen.
	$limit = trim((string) ini_get('memory_limit'));
	$bytes = (int) $limit;
	$suffix = strtolower(substr($limit, -1));
	$bytes *= ['g' => 1073741824, 'm' => 1048576, 'k' => 1024][$suffix] ?? 1;
	if ($bytes > 0 && memory_get_usage(true) + $width * $height * 12 + 32 * 1024 * 1024 > $bytes) {
		throw new InvalidArgumentException('Das Bild ist für diesen Server zu groß. Bitte vorher verkleinern.');
	}
	$image = match ($mime) {
		'image/jpeg' => @imagecreatefromjpeg($source),
		'image/png' => @imagecreatefrompng($source),
		'image/webp' => @imagecreatefromwebp($source),
	};
	if ($image === false) throw new InvalidArgumentException('Das Bild konnte nicht gelesen werden.');
	$written = [];
	try {
		if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
			$exif = @exif_read_data($source);
			$orientation = (int) ($exif['Orientation'] ?? 1);
			if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
			$angle = [3 => 180, 4 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90][$orientation] ?? 0;
			if ($angle !== 0) {
				$rotated = imagerotate($image, $angle, 0);
				if ($rotated === false) throw new RuntimeException('Bild konnte nicht gedreht werden.');
				imagedestroy($image);
				$image = $rotated;
			}
		}
		$width = imagesx($image);
		$height = imagesy($image);
		$variants = [];
		foreach (['display' => 2000, 'thumbnail' => 300] as $variant => $max) {
			$scale = min(1, $max / max($width, $height));
			$w = max(1, (int) round($width * $scale));
			$h = max(1, (int) round($height * $scale));
			$target = imagecreatetruecolor($w, $h);
			if ($target === false) throw new RuntimeException('Bildformat konnte nicht erzeugt werden.');
			try {
				imagealphablending($target, false);
				imagesavealpha($target, true);
				imagecopyresampled($target, $image, 0, 0, 0, 0, $w, $h, $width, $height);
				$file = mediaFilePath(['id' => $id], $variant, $local);
				$written[] = $file;
				if (!imagewebp($target, $file, $variant === 'display' ? 85 : 80) || !is_file($file) || filesize($file) === 0) {
					throw new RuntimeException('Bild konnte nicht gespeichert werden.');
				}
				@chmod($file, 0640);
				$variants[$variant] = [
					'path' => ($variant === 'display' ? 'display/' : 'thumbnails/') . $id . '.webp',
					'width' => $w, 'height' => $h, 'bytes' => filesize($file), 'mimeType' => 'image/webp',
				];
			} finally {
				imagedestroy($target);
			}
		}
		return $variants;
	} catch (Throwable $error) {
		foreach ($written as $file) if (is_file($file)) unlink($file);
		throw $error;
	} finally {
		imagedestroy($image);
	}
}

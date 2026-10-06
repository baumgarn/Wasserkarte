<?php

require_once __DIR__ . '/images.php';

const MEDIA_VIDEO_MAX_BYTES = 100 * 1024 * 1024;

function mediaAacDescriptor(string $data): bool
{
	$offset = 4; // FullBox version/flags.
	$descriptor = static function (int $tag) use ($data, &$offset): bool {
		if (!isset($data[$offset]) || ord($data[$offset++]) !== $tag) return false;
		$length = 0;
		for ($i = 0; $i < 4; $i++) {
			if (!isset($data[$offset])) return false;
			$byte = ord($data[$offset++]);
			$length = ($length << 7) | ($byte & 127);
			if (($byte & 128) === 0) return $offset + $length <= strlen($data);
		}
		return false;
	};
	if (!$descriptor(3) || !isset($data[$offset + 2])) return false;
	$flags = ord($data[$offset + 2]);
	$offset += 3;
	if ($flags & 128) $offset += 2;
	if ($flags & 64) {
		if (!isset($data[$offset])) return false;
		$offset += 1 + ord($data[$offset]);
	}
	if ($flags & 32) $offset += 2;
	return $descriptor(4) && isset($data[$offset]) && in_array(ord($data[$offset]), [0x40, 0x66, 0x67, 0x68], true);
}

// MP4-Boxen lesen, ohne die Videodaten in den Arbeitsspeicher zu laden.
function mediaVideoInfo(string $source): array
{
	$handle = fopen($source, 'rb');
	if ($handle === false) throw new InvalidArgumentException('Video konnte nicht gelesen werden.');
	$codecs = [];
	$info = [];
	$boxes = 0;
	$duration = null;
	$read = function (int $start, int $end, int $depth = 0) use (&$read, $handle, &$codecs, &$info, &$boxes, &$duration): void {
		if ($depth > 8) throw new InvalidArgumentException('Ungültiges MP4.');
		for ($offset = $start; $offset + 8 <= $end;) {
			if (++$boxes > 10000) throw new InvalidArgumentException('Ungültiges MP4.');
			fseek($handle, $offset);
			$header = fread($handle, 8);
			if (strlen($header) !== 8) throw new InvalidArgumentException('Ungültiges MP4.');
			$size = unpack('N', substr($header, 0, 4))[1];
			$type = substr($header, 4, 4);
			$headerSize = 8;
			if ($size === 1) {
				$extended = fread($handle, 8);
				if (strlen($extended) !== 8) throw new InvalidArgumentException('Ungültiges MP4.');
				$parts = unpack('Nhigh/Nlow', $extended);
				$size = $parts['high'] * 4294967296 + $parts['low'];
				$headerSize = 16;
			}
			if ($size === 0) $size = $end - $offset;
			if ($size < $headerSize || $offset + $size > $end) throw new InvalidArgumentException('Ungültiges MP4.');
			$payload = $offset + $headerSize;
			if (in_array($type, ['moov', 'trak', 'mdia', 'minf', 'stbl'], true)) $read($payload, $offset + $size, $depth + 1);
			if ($type === 'mvhd') {
				fseek($handle, $payload);
				$data = $size > $headerSize ? fread($handle, min(32, $size - $headerSize)) : '';
				if (strlen($data) >= 20 && ord($data[0]) === 0) {
					$time = unpack('Nscale/Nticks', substr($data, 12, 8));
					if ($time['scale']) $duration = $time['ticks'] / $time['scale'];
				} elseif (strlen($data) >= 32 && ord($data[0]) === 1) {
					$time = unpack('Nscale/Nhigh/Nlow', substr($data, 20, 12));
					if ($time['scale']) $duration = ($time['high'] * 4294967296 + $time['low']) / $time['scale'];
				}
			}
			if ($type === 'stsd') {
				fseek($handle, $payload);
				if ($size - $headerSize < 8) throw new InvalidArgumentException('Ungültiges MP4.');
				$data = fread($handle, 8);
				if (strlen($data) !== 8) throw new InvalidArgumentException('Ungültiges MP4.');
				$count = unpack('N', substr($data, 4, 4))[1];
				if ($count > 32) throw new InvalidArgumentException('Ungültiges MP4.');
				$entry = $payload + 8;
				for ($i = 0; $i < $count; $i++) {
					if ($entry + 8 > $offset + $size) throw new InvalidArgumentException('Ungültiges MP4.');
					fseek($handle, $entry);
					$data = fread($handle, min(36, $offset + $size - $entry));
					if (strlen($data) < 8) throw new InvalidArgumentException('Ungültiges MP4.');
					$length = unpack('N', substr($data, 0, 4))[1];
					$codec = substr($data, 4, 4);
					if ($length < 8 || $entry + $length > $offset + $size) throw new InvalidArgumentException('Ungültiges MP4.');
					$codecs[] = $codec;
					if (in_array($codec, ['avc1', 'avc3'], true) && strlen($data) >= 36) {
						$info = unpack('nwidth/nheight', substr($data, 32, 4));
					}
					if ($codec === 'mp4a') {
						$aac = false;
						for ($child = $entry + 36; $child + 8 <= $entry + $length;) {
							fseek($handle, $child);
							$header = fread($handle, 8);
							$childSize = unpack('N', substr($header, 0, 4))[1];
							if ($childSize < 8 || $child + $childSize > $entry + $length) break;
							if (substr($header, 4, 4) === 'esds' && $childSize <= 4096) $aac = mediaAacDescriptor(fread($handle, $childSize - 8));
							$child += $childSize;
						}
						if (!$aac) throw new InvalidArgumentException('Bitte ein MP4-Video mit AAC-Ton auswählen.');
					}
					$entry += $length;
				}
			}
			$offset += $size;
		}
	};
	try { $read(0, filesize($source)); } finally { fclose($handle); }
	if (empty($info['width']) || empty($info['height']) || array_diff($codecs, ['avc1', 'avc3', 'mp4a']) !== []) {
		throw new InvalidArgumentException('Bitte ein MP4-Video mit H.264 und AAC-Ton auswählen.');
	}
	return $info + ['duration' => $duration];
}

function mediaProcessVideo(string $source, string $poster, string $id, bool $local): array
{
	$bytes = filesize($source);
	if (!$bytes || $bytes > MEDIA_VIDEO_MAX_BYTES) throw new InvalidArgumentException('Ein Video darf höchstens 100 MB groß sein.');
	if ((new finfo(FILEINFO_MIME_TYPE))->file($source) !== 'video/mp4') throw new InvalidArgumentException('Bitte ein MP4-Video auswählen.');
	$info = mediaVideoInfo($source);
	$variants = mediaProcessImage($poster, $id, $local);
	@unlink(mediaFilePath(['id' => $id], 'display', $local));
	$file = mediaFilePath(['id' => $id, 'type' => 'video'], 'display', $local);
	if (!copy($source, $file)) {
		@unlink($file);
		@unlink(mediaFilePath(['id' => $id], 'thumbnail', $local));
		throw new RuntimeException('Video konnte nicht gespeichert werden.');
	}
	@chmod($file, 0640);
	unset($info['duration']);
	$variants['display'] = $info + ['bytes' => $bytes, 'mimeType' => 'video/mp4'];
	return $variants;
}

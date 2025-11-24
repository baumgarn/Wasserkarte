<?php

// Updates all daily aggregated telemetry for all devices.
// Should run through a cronjob right after midnight. Add to crontab -e:
// 5 0 * * * /usr/bin/php /var/home/badbelzig/www/wasserkarte.badbelzig-klimadaten.de/api/dailyaverages.php >> $HOME/wasserkarte.log 2>&1

$nfk_labels = [
    [ "value" => 10,  "name" => "Sehr trocken" ], // 0–10
    [ "value" => 30,  "name" => "Trocken"      ], // 10–30
    [ "value" => 80,  "name" => "Optimal"      ], // 30–80
    [ "value" => 100, "name" => "Nass"         ], // 80–100
    [ "value" => 120, "name" => "Sehr nass"    ], // 100+
];

require_once 'config.php';

// only run cache wasn't updated since midnight or if cache doesn't exist 
if (is_file(CACHE_FILE_ALLTELEMETRY)) {
    $mtime = filemtime(CACHE_FILE_ALLTELEMETRY);
    if ($mtime !== false) {
        $midnight = strtotime('today midnight'); // timestamp for today's 00:00

        if ($mtime >= $midnight) {
            // Already updated once today
			echo date('Y-m-d H:i:s', (int) (microtime(true))) . " – Skipped: cache already updated after midnight\n";
            exit(0);
        }
    }
}

//

if (version_compare(phpversion(), '7.1', '>=')) {
	ini_set('precision', 17);
	ini_set('serialize_precision', -1);
}

require_once 'telemetry.php';
require_once 'devices.php';
require_once 'datamodel.php';
require_once 'cache.php';
require_once 'auth.php';


function dailyAverages() {

	$startTime = microtime(true);
	$now = time() * 1000;

	// first get all devices query

	$token = getAuthToken();
	$deviceData = getThingsBoardDevices();

	// then run daily average timeseries query for all devices

	$alltimeseries = [];
	$earliest = PHP_INT_MAX;
	$latest = 0;
	$previousLatest = getLatestTimestampFromCache();
	$new_nfk_values = [];
	
	foreach ($deviceData['devices'] as $device) {
		$deviceId = $device['id'];
		$data = getTimeseriesForDevice($token, $deviceId, null, 'all', '1d');
		
		if ($data) {
			$json = json_encode($data, JSON_PRETTY_PRINT);
			saveTelemetryCache($deviceId, 'all', '1d', $json);	
			$alltimeseries[$deviceId] = $data['telemetry'];

			// check earliest and latest timestamp
			$firstts = $data['telemetry']['data'][0][0];
			$lastts = end($data['telemetry']['data'])[0];
			if ($earliest > $firstts) {
				$earliest = $firstts;
			}
			if ($latest < $lastts) {
				$latest = $lastts;
			}
			
			// collect nfk_avg values from previous last day on, to calculate daily average of all devices
			// exclude locations with groundwater attribute
			// if (!isset($device['attributes']['Grundwasser'])) {
				$nfk_avg_index = array_search('nfk_avg', $data['telemetry']['schema']);
				if ($nfk_avg_index !== false) {
					$rows = $data['telemetry']['data'];
					for ($i = count($rows) - 1; $i >= 0; $i--) {
						$row = $rows[$i];
						$ts = $row[0];
						
						
						if ($ts > $previousLatest) { // until previous last day
							if (isRowValid($row, $data['telemetry']['schema'])) {
								$nfk_avg = $rows[$i][$nfk_avg_index];
								if (! isset($new_nfk_values[$ts])) {
									$new_nfk_values[$ts] = [];
								}
								$new_nfk_values[$ts][] = $nfk_avg;
							}
						} else {
							break;
						}
					}
				}
			// }
		}
	}

	// calculate nfk_averages
	// $nfk_daily_averages = $new_nfk_values;
	$cached_nfk_averages = getDailyAveragesPairsFromCache();
	$new_nfk_values = averageBucketsAsPairs($new_nfk_values, 2);
	$nfk_daily_averages = array_merge($cached_nfk_averages, $new_nfk_values);

	// $nfk_daily_averages = $cached_nfk_averages;

	// response

	$days = null;
	if ($earliest !== null && $latest !== null) {
		$days = round(($latest - $earliest) / (1000 * 60 * 60 * 24), 0) + 1;
	}

	$endTime = microtime(true);
	$queryTime =  $endTime - $startTime;

	$response = [
		'queryTime' => $queryTime,
		'earliestTimestamp' => $earliest,
		'latestTimestamp' => $latest,
		'previousLatestTimestamp' => $previousLatest,
		'earliestDate' => date('Y-m-d H:i:s', (int) ($earliest / 1000)),
		'latestDate' => date('Y-m-d H:i:s', (int) ($latest / 1000)),
		'days' => $days,
		'nfk_daily_averages' => $nfk_daily_averages,
		'devices' => $alltimeseries,
	];

	$json = json_encode($response, JSON_PRETTY_PRINT);
	atomicWrite(CACHE_FILE_ALLTELEMETRY, $json);
	
    $gzData = gzencode($json, 9);
	if ($gzData === false) {
    	throw new RuntimeException('gzencode failed');
	}
    $gzPath = CACHE_FILE_ALLTELEMETRY . '.gz';
    atomicWrite($gzPath, $gzData);

	$queryTime = microtime(true) - $startTime;
	echo date('Y-m-d H:i:s', (int) (microtime(true))) . " – Daily averages updated (" . number_format($queryTime,2) . "s)\n";

}

dailyAverages();


// GET LATEST TIMESTAMP FROM CACHE WITHOUT LOADING FULL CACHE FILE

function getLatestTimestampFromCache(): int {
    $filename = CACHE_FILE_ALLTELEMETRY;

    if (!file_exists($filename)) {
        return 0;
    }

    $handle = fopen($filename, "r");
    if (!$handle) {
        return 0;
    }

    $buffer = '';
    while (!feof($handle)) {
        $buffer .= fread($handle, 1024); // read in chunks
        if (strpos($buffer, '"latestTimestamp"') !== false) {
            if (preg_match('/"latestTimestamp"\s*:\s*(\d+)/', $buffer, $matches)) {
                fclose($handle);
                return (int)$matches[1];
            }
        }
    }

    fclose($handle);
    return 0;
}

// FUNCTION TO CALCULATE ALL AVERAGE VALUES

function averageBucketsAsPairs(array $buckets, ?int $precision = 2): array {
    $out = [];
    foreach ($buckets as $ts => $values) {
        if (!is_array($values) || $values === []) {
            $out[] = [(int)$ts, null];
            continue;
        }
		$count = count($values);

		// var_dump($ts);
		// var_dump($values);

        $sum = 0.0;
        $n = 0;
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $sum += (float)$v;
                $n++;
            }
        }

        if ($n === 0) {
            $avg = null;
        } else {
            $avg = $sum / $n;
            if (is_int($precision)) {
				$avg = round($avg, $precision);
            }
        }
		
		$value_split = bucketizeNfkValues($values);

        $out[] = [(int)$ts, $avg, count($values), $value_split];
    }

    // sort by timestamp
    usort($out, fn($a, $b) => $a[0] <=> $b[0]);
    return $out;
}

// GET DAILY AVERAGE PAIRS FROM CACHE

function getDailyAveragesPairsFromCache(): array {
    $filename = CACHE_FILE_ALLTELEMETRY;
    if (!file_exists($filename)) return [];
    $h = fopen($filename, 'r');
    if (!$h) return [];

    $chunkSize = 65536; // 64 KB
    $needle = '"nfk_daily_averages"';

    $buffer = '';
    $found  = false;

    $fragment = '';
    $startCaptured = false;

    while (!feof($h)) {
        $chunk = fread($h, $chunkSize);
        if ($chunk === false) break;

        if (!$found) {
            $buffer .= $chunk;

            $pos = strpos($buffer, $needle);
            if ($pos === false) {
                if (strlen($buffer) > 2 * $chunkSize) {
                    $buffer = substr($buffer, -$chunkSize);
                }
                continue;
            }

            // From just after the key, find the opening '['
            $tail = substr($buffer, $pos + strlen($needle));
            if (!preg_match('/\s*:\s*\[/', $tail, $m, PREG_OFFSET_CAPTURE)) {
                fclose($h);
                return [];
            }

            $after = $m[0][1] + strlen($m[0][0]); // position just after '[' in $tail
            $fragment = '[' . substr($tail, $after);
            $found = true;
            $startCaptured = true;
        } else {
            $fragment .= $chunk;
        }

        if ($startCaptured) {
			if (preg_match('/\]\s*\]\s*\]/', $fragment, $mm, PREG_OFFSET_CAPTURE)) {
            // if (preg_match('/\]\s*\]/', $fragment, $mm, PREG_OFFSET_CAPTURE)) {
                $endPos = $mm[0][1] + strlen($mm[0][0]); 
                $frag = substr($fragment, 0, $endPos);

                fclose($h);

                $decoded = json_decode($frag, true);
                if (!is_array($decoded)) return [];

                return $decoded; // return raw array of arrays, no normalization
            }

            if (strlen($fragment) > 8 * 1024 * 1024) {
                fclose($h);
                return [];
            }
        }
    }

    fclose($h);
    return [];
}

// COUNT HOW OUR DAILY LOCATION NFK AVERAGES FALL INTO EACH LABEL CATEGORY IE: "TROCKEN" "NASS" ETC

function bucketizeNfkValues(array $values): array {
	global $nfk_labels;

	
    $numBuckets = count($nfk_labels);

	// one counter per label
	$buckets = array_fill(0, $numBuckets, 0);

	foreach ($values as $v) {
		if (!is_numeric($v)) {
			continue;
		}
		$v = (float)$v;

		// default: last bucket (e.g. "Sehr nass")
		$bucketIndex = $numBuckets - 1;

		// find first label where v < label.value
		foreach ($nfk_labels as $i => $label) {
			if ($v < $label['value']) {
				$bucketIndex = $i;
				break;
			}
		}
		$buckets[$bucketIndex]++;
	}
    
    return $buckets;
}


// ROW IS VALID ONLY IF ALL REQUIRED FIELDS ARE WITHIN -10 AND 100
function isRowValid(array $row, array $schema): bool {
    $fields = [
        'Bodenfeuchte_10cm',
        'Bodenfeuchte_30cm',
        'Bodenfeuchte_60cm',
        'Bodenfeuchte_80cm',
    ];

    foreach ($fields as $field) {
        $idx = array_search($field, $schema, true);
        if ($idx === false) {
            continue; // field not present in this schema → ignore
        }
        $val = $row[$idx] ?? null;
        if ($val === false) {
            return false; // treat non-numeric as invalid
        }
        $f = (float)$val;
        if ($f < -10 || $f > 100) {
            return false; // out of range → row invalid
        }
    }
    return true;
}
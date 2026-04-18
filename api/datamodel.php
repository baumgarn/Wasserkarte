<?php

function loadSoilTable(): array
{
	static $soilTable = null;

	if ($soilTable !== null) {
		return $soilTable;
	}

	$path = dirname(__DIR__) . '/data/soil-table.json';
	$json = @file_get_contents($path);
	if ($json === false) {
		throw new RuntimeException("Failed to read soil table from {$path}");
	}

	$data = json_decode($json, true);
	if (!is_array($data)) {
		throw new RuntimeException("Invalid soil table JSON in {$path}");
	}

	$soilTable = $data;
	return $soilTable;
}

$soil_table = loadSoilTable();

function normalizeSoilTableKey($value): ?string
{
	if ($value === null) {
		return null;
	}

	$value = trim((string) $value);
	return $value === '' ? null : $value;
}

function normalizeHumusKey($value): ?string
{
	$value = normalizeSoilTableKey($value);
	if ($value === 'h0' || $value === 'h1') {
		return 'h0-1';
	}

	return $value;
}

function getSoilWaterCapacityValues(array $atts, int $depth, array $soilTable): ?array
{
	$soil = normalizeSoilTableKey($atts['Bodenart_' . $depth . 'cm'] ?? $atts['Bodenart'] ?? null);
	$humus = normalizeHumusKey($atts['Humusgehalt_' . $depth . 'cm'] ?? $atts['Humusgehalt'] ?? null);

	if ($soil === null || $humus === null) {
		return null;
	}

	if (
		$humus === 'h5'
		&& !isset($soilTable[$soil]['FK'][$humus], $soilTable[$soil]['TW'][$humus])
		&& isset($soilTable[$soil]['FK']['h4'], $soilTable[$soil]['TW']['h4'])
	) {
		$humus = 'h4';
	}

	if (!isset($soilTable[$soil]['FK'][$humus], $soilTable[$soil]['TW'][$humus])) {
		return null;
	}

	return [
		'FK' => $soilTable[$soil]['FK'][$humus],
		'TW' => $soilTable[$soil]['TW'][$humus],
	];
}


function expandSchema($schema) {
	
	$c = count($schema);

	if (in_array('Bodenfeuchte_10cm', $schema)) {
		$schema[] = 'nfk_10cm';
	}
	if (in_array('Bodenfeuchte_30cm', $schema)) {
		$schema[] = 'nfk_30cm';
	}
	if (in_array('Bodenfeuchte_60cm', $schema)) {
		$schema[] = 'nfk_60cm';
	}
	if (in_array('Bodenfeuchte_80cm', $schema)) {
		$schema[] = 'nfk_80cm';
	}
	
	if ($c > 1) {
		$schema[] = 'nfk_avg';
		$schema[] = 'vol_avg';
	}

	return $schema;
}


function expandSensorDataWithCalculations($data, $deviceInfo) {
	global $soil_table;

	$atts = $deviceInfo['attributes'];

	$schema = $data['schema'] ?? null;
	if ($schema == null) {
		return $data;
	}
	$fieldIndex = buildFieldIndex($data['schema']);

	$Bodenfeuchte_10cm_index = $fieldIndex['Bodenfeuchte_10cm'] ?? null;
	$Bodenfeuchte_30cm_index = $fieldIndex['Bodenfeuchte_30cm'] ?? null;
	$Bodenfeuchte_60cm_index = $fieldIndex['Bodenfeuchte_60cm'] ?? null;
	$Bodenfeuchte_80cm_index = $fieldIndex['Bodenfeuchte_80cm'] ?? null;
	$nfk_10cm_index = $fieldIndex['nfk_10cm'] ?? null;
	$nfk_30cm_index = $fieldIndex['nfk_30cm'] ?? null;
	$nfk_60cm_index = $fieldIndex['nfk_60cm'] ?? null;
	$nfk_80cm_index = $fieldIndex['nfk_80cm'] ?? null;
	$nfk_avg_index = $fieldIndex['nfk_avg'] ?? null;
	$vol_avg_index = $fieldIndex['vol_avg'] ?? null;

	// nfk_10 check
	if (in_array('Bodenfeuchte_10cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 10, $soil_table)) !== null) {
		$FK10 = $soilValues['FK'];
		$TW10 = $soilValues['TW'];
	}
	// nfk_30 check
	if (in_array('Bodenfeuchte_30cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 30, $soil_table)) !== null) {
		$FK30 = $soilValues['FK'];
		$TW30 = $soilValues['TW'];
	}
	// nfk_60 check
	if (in_array('Bodenfeuchte_60cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 60, $soil_table)) !== null) {
		$FK60 = $soilValues['FK'];
		$TW60 = $soilValues['TW'];
	}
	// nfk_80 check
	if (in_array('Bodenfeuchte_80cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 80, $soil_table)) !== null) {
		$FK80 = $soilValues['FK'];
		$TW80 = $soilValues['TW'];
	}

	foreach ($data['data'] as &$row) {
		// reset per-row temps to avoid stale carryover
		$vol10 = $vol30 = $vol60 = $vol80 = null;
		$nfk10 = $nfk30 = $nfk60 = $nfk80 = null;

		if (isset($FK10, $TW10) && $Bodenfeuchte_10cm_index !== null && $nfk_10cm_index !== null) { // nfk_10 apply
			$den = ($FK10 - $TW10);
			$vol10 = $row[$Bodenfeuchte_10cm_index] ?? null;
			if (!isValidBodenfeuchte($vol10)) {
				$row[$Bodenfeuchte_10cm_index] = $vol10 = null;
			}
			if ($den != 0 && $vol10 !== null) {
				$nfk10 = round((($vol10 - $TW10) / $den) * 100, 2);
				if (is_finite($nfk10)) { $row[$nfk_10cm_index] = $nfk10; }
			}
		}

		if (isset($FK30, $TW30) && $Bodenfeuchte_30cm_index !== null && $nfk_30cm_index !== null) { // nfk_30 apply
			$den = ($FK30 - $TW30);
			$vol30 = $row[$Bodenfeuchte_30cm_index] ?? null;
			if (!isValidBodenfeuchte($vol30)) {
				$row[$Bodenfeuchte_30cm_index] = $vol30 = null;
			}
			if ($den != 0 && $vol30 !== null) {
				$nfk30 = round((($vol30 - $TW30) / $den) * 100, 2);
				if (is_finite($nfk30)) { $row[$nfk_30cm_index] = $nfk30; }
			}
		}

		if (isset($FK60, $TW60) && $Bodenfeuchte_60cm_index !== null && $nfk_60cm_index !== null) { // nfk_60 apply
			$den = ($FK60 - $TW60);
			$vol60 = $row[$Bodenfeuchte_60cm_index] ?? null;
			if (!isValidBodenfeuchte($vol60)) {
				$row[$Bodenfeuchte_60cm_index] = $vol60 = null;
			}
			if ($den != 0 && $vol60 !== null) {
				$nfk60 = round((($vol60 - $TW60) / $den) * 100, 2);
				if (is_finite($nfk60)) { $row[$nfk_60cm_index] = $nfk60; }
			}
		}

		if (isset($FK80, $TW80) && $Bodenfeuchte_80cm_index !== null && $nfk_80cm_index !== null) { // nfk_80 apply
			$den = ($FK80 - $TW80);
			$vol80 = $row[$Bodenfeuchte_80cm_index] ?? null;
			if (!isValidBodenfeuchte($vol80)) {
				$row[$Bodenfeuchte_80cm_index] = $vol80 = null;
			}
			if ($den != 0 && $vol80 !== null) {
				$nfk80 = round((($vol80 - $TW80) / $den) * 100, 2);
				if (is_finite($nfk80)) { $row[$nfk_80cm_index] = $nfk80; }
			}
		}
		
		// average nfk
		$nfk_avg = avg_value($nfk10, $nfk30, $nfk60, $nfk80);
		if ($nfk_avg_index !== null && $nfk_avg !== null && is_finite($nfk_avg)) {
			$row[$nfk_avg_index] = round($nfk_avg,2);
		}
		
		// average vol
		$vol_avg = avg_value($vol10, $vol30, $vol60, $vol80);
		if ($vol_avg_index !== null && $vol_avg !== null && is_finite($vol_avg)) {
			$row[$vol_avg_index] = round($vol_avg,2);
		}
	}

	$colCount = count($data['schema']);
	foreach ($data['data'] as &$row) {
		// ensure every column index exists
		for ($i = 0; $i < $colCount; $i++) {
			if (!array_key_exists($i, $row)) {
				$row[$i] = null;
			}
		}
		ksort($row, SORT_NUMERIC); // order by numeric index
		$row = array_values($row); // reindex to 0..n-1
	}
	unset($row);
	
	return $data;
}


// set average Totwasser & Feldkapazität
function setAvgTWFK($deviceInfo) {
	
	global $soil_table;

	$atts = $deviceInfo['attributes'];

	$schema = $deviceInfo['telemetrySchema']['schema'] ?? [];
	
	$FK10 = $TW10 = $FK30 = $TW30 = $FK60 = $TW60 = $FK80 = $TW80 = null;

	// nfk_10 check
	if (in_array('Bodenfeuchte_10cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 10, $soil_table)) !== null) {
		$FK10 = $soilValues['FK'];
		$TW10 = $soilValues['TW'];
	}
	// nfk_30 check
	if (in_array('Bodenfeuchte_30cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 30, $soil_table)) !== null) {
		$FK30 = $soilValues['FK'];
		$TW30 = $soilValues['TW'];
	}
	// nfk_60 check
	if (in_array('Bodenfeuchte_60cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 60, $soil_table)) !== null) {
		$FK60 = $soilValues['FK'];
		$TW60 = $soilValues['TW'];
	}
	// nfk_80 check
	if (in_array('Bodenfeuchte_80cm', $schema) && ($soilValues = getSoilWaterCapacityValues($atts, 80, $soil_table)) !== null) {
		$FK80 = $soilValues['FK'];
		$TW80 = $soilValues['TW'];
	}

	$avg_FK = round(avg_value($FK10,$FK30,$FK60,$FK80), 2);
	$avg_TW = round(avg_value($TW10,$TW30,$TW60,$TW80), 2);


	$deviceInfo['attributes']['avg_FK'] = $avg_FK;
	$deviceInfo['attributes']['avg_TW'] = $avg_TW;

	return $deviceInfo;
}

function avg_value($v10,$v30,$v60,$v80)
{
  
    // Interpolate 60 from 30 & 80 if 60 missing but 10 & 30 & 80 available
    if (isset($v10) && isset($v30) && !isset($v60) && isset($v80)) {
        $v60 = ($v30 + $v80) / 2.0;
    }

    $result = null;

    // 10,30,60
    if (isset($v10) && isset($v30) && isset($v60)) {
        $result = $v10 + ($v10 + $v30) / 2.0 + $v30 * 2.0 + $v60 * 2.0;
    }
    // 10,30 (no 60)
    elseif (isset($v10) && isset($v30) && !isset($v60)) {
        $result = $v10 + ($v10 + $v30) / 2.0 + $v30 * 4.0;
    }
    // 10,60 (no 30)
    elseif (isset($v10) && isset($v60) && !isset($v30)) {
        $result = $v10 * 3.0 + $v60 * 3.0;
    }
    // 10,80 (no 30, 60)
    elseif (isset($v10) && isset($v80) && !isset($v30) && !isset($v60)) {
        $result = $v10 * 3.0 + $v80 * 3.0;
    }
    // 30,60 (no 10)
    elseif (!isset($v10) && isset($v30) && isset($v60)) {
        $result = $v30 * 4.0 + $v60 * 2.0;
    }
    // 10
    elseif (isset($v10) && !isset($v30) && !isset($v60) && !isset($v80)) {
        $result = $v10 * 6.0;
    }
    // 30
    elseif (isset($v30) && !isset($v10) && !isset($v60) && !isset($v80)) {
        $result = $v30 * 6.0;
    }
    // 60
    elseif (isset($v60) && !isset($v10) && !isset($v30) && !isset($v80)) {
        $result = $v60 * 6.0;
    }
    // 80
    elseif (isset($v80) && !isset($v10) && !isset($v30) && !isset($v60)) {
        $result = $v80 * 6.0;
    }

    return round($result / 6, 2);
}

function isValidBodenfeuchte($value) {
	return (is_numeric($value) && $value >= -10 && $value <= 100);
}


function buildFieldIndex(array $schema): array
{
	return array_flip($schema);
}

function setRowValue(array &$row, string $field, $value, array $fieldIndex): void
{
	if (isset($fieldIndex[$field])) {
		$row[$fieldIndex[$field]] = $value;
	}
}

function getRowValue(array $row, string $field, array $fieldIndex)
{
	return $fieldIndex[$field] ?? null
		? $row[$fieldIndex[$field]]
		: null;
}

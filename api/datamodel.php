<?php

$soil_table = [
	"Ss" => [
		"name"  => "sandiger Sand",
		"TW"    => ["h0-1" => 2, "h2" => 3, "h3" => 4, "h4" => 6],
		"FK"    => ["h0-1" => 13, "h2" => 16, "h3" => 18, "h4" => 23]
	],
	"Sl2" => [
		"name"  => "schwach lehmiger Sand",
		"TW"    => ["h0-1" => 6, "h2" => 7, "h3" => 8, "h4" => 9],
		"FK"    => ["h0-1" => 21, "h2" => 23, "h3" => 26, "h4" => 60]
	],
	"Sl3" => [
		"name"  => "mittel lehmiger Sand",
		"TW"    => ["h0-1" => 9, "h2" => 10, "h3" => 10, "h4" => 12],
		"FK"    => ["h0-1" => 25, "h2" => 27, "h3" => 29, "h4" => 34]
	],
	"Ls4" => [
		"name"  => "sandiger Lehm",
		"TW"    => ["h0-1" => 13, "h2" => 14, "h3" => 14, "h4" => 15],
		"FK"    => ["h0-1" => 28, "h2" => 60, "h3" => 32, "h4" => 36]
	]
];



function expandSchema($schema) {
	
	$c = count($schema);

	if (in_array('Bodenfeuchte_10cm', $schema)) {
		$schema[] = 'nfk_10cm';
	}
	if (in_array('Bodenfeuchte_60cm', $schema)) {
		$schema[] = 'nfk_60cm';
	}
	if (in_array('Bodenfeuchte_60cm', $schema)) {
		$schema[] = 'nfk_60cm';
	}
	if (in_array('Bodenfeuchte_80cm', $schema)) {
		$schema[] = 'nfk_80cm';
	}
	
	if ($c > 2) {
		$schema[] = 'vol_avg';
		$schema[] = 'nfk_avg';
	}

	return $schema;
}


function expandSensorDataWithCalculations($data, $deviceInfo) {
	global $soil_table;

	$atts = $deviceInfo['attributes'];
	$schema = $data['schema'];
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
	if (in_array('Bodenfeuchte_10cm', $schema) && (in_array('Bodenart_10cm', $atts) || in_array('Bodenart', $atts))) {
		$soil10 = $atts['Bodenart_10cm'] ?? $atts['Bodenart'];
		$humus10 = $atts['Humusgehalt_10cm'] ?? $atts['Humusgehalt'];
		if ($humus10 == 'h0' || $humus10 == 'h1') {$humus10 = 'h0-1';}
		$FK10 = $soil_table[$soil10]['FK'][$humus10];
		$TW10 = $soil_table[$soil10]['TW'][$humus10];
	}
	// nfk_30 check
	if (in_array('Bodenfeuchte_30cm', $schema) && (in_array('Bodenart_30cm', $atts) || in_array('Bodenart', $atts))) {
		$soil30 = $atts['Bodenart_30cm'] ?? $atts['Bodenart'];
		$humus30 = $atts['Humusgehalt_30cm'] ?? $atts['Humusgehalt'];
		if ($humus30 == 'h0' || $humus30 == 'h1') {$humus30 = 'h0-1';}
		$FK30 = $soil_table[$soil30]['FK'][$humus30];
		$TW30 = $soil_table[$soil30]['TW'][$humus30];
	}
	// nfk_60 check
	if (in_array('Bodenfeuchte_60cm', $schema) && (in_array('Bodenart_60cm', $atts) || in_array('Bodenart', $atts))) {
		$soil60 = $atts['Bodenart_60cm'] ?? $atts['Bodenart'];
		$humus60 = $atts['Humusgehalt_60cm'] ?? $atts['Humusgehalt'];
		if ($humus60 == 'h0' || $humus60 == 'h1') {$humus60 = 'h0-1';}
		$FK60 = $soil_table[$soil60]['FK'][$humus60];
		$TW60 = $soil_table[$soil60]['TW'][$humus60];
	}
	// nfk_80 check
	if (in_array('Bodenfeuchte_80cm', $schema) && (in_array('Bodenart_80cm', $atts) || in_array('Bodenart', $atts))) {
		$soil80 = $atts['Bodenart_80cm'] ?? $atts['Bodenart'];
		$humus80 = $atts['Humusgehalt_80cm'] ?? $atts['Humusgehalt'];
		if ($humus80 == 'h0' || $humus80 == 'h1') {$humus80 = 'h0-1';}
		$FK80 = $soil_table[$soil80]['FK'][$humus80];
		$TW80 = $soil_table[$soil80]['TW'][$humus80];
	}

	foreach ($data['data'] as &$row) {

		if (isset($FK10) && isset($TW10)) { // nfk_10 apply
			$vol10 = $row[$Bodenfeuchte_10cm_index] ?? null;
			$nfk10 = round(max(0, (($vol10 - $TW10) / ($FK10 - $TW10)) * 100), 2);
			$row[$nfk_10cm_index] = $nfk10;
		}

		if (isset($FK30) && isset($TW30)) { // nfk_30 apply
			$vol30 = $row[$Bodenfeuchte_30cm_index] ?? null;
			$nfk30 = round(max(0, (($vol30 - $TW30) / ($FK30 - $TW30)) * 100), 2);
			$row[$nfk_30cm_index] = $nfk30;
		}

		if (isset($FK60) && isset($TW60)) { // nfk_60 apply
			$vol60 = $row[$Bodenfeuchte_60cm_index] ?? null;
			$nfk60 = round(max(0, (($vol60 - $TW60) / ($FK60 - $TW60)) * 100), 2);
			$row[$nfk_60cm_index] = $nfk60;
		}

		if (isset($FK80) && isset($TW80)) { // nfk_80 apply
			$vol80 = $row[$Bodenfeuchte_80cm_index] ?? null;
			$nfk80 = round(max(0, (($vol80 - $TW80) / ($FK80 - $TW80)) * 100), 2);
			$row[$nfk_80cm_index] = $nfk80;
		}
		
		// average nfk
		$nfk_avg = avg_value($nfk10 ?? null, $nfk30 ?? null, $nfk60 ?? null, $nfk80 ?? null );
		if ($nfk_avg) {
			$row[$nfk_avg_index] = $nfk_avg;
		}
		
		// average vol
		$vol_avg = avg_value($vol10 ?? null, $vol30 ?? null, $vol60 ?? null, $vol80 ?? null );
		if ($vol_avg) {
			$row[$vol_avg_index] = $vol_avg;
		}
		
	}

	unset($row);

	return $data;
}


function avg_value($v10,$v30,$v60,$v80)
{

	// var_dump($v10);
	// var_dump($v30);
	// var_dump($v60);
	// var_dump($v80);
  
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
        // Matches original: use 30's slot as 10’s proxy? (v30*3 + v60*3) where v30 is missing.
        // We keep the logic identical for parity:
        $result = $v10 * 3.0 + $v60 * 3.0;
    }
    // 30,60 (no 10)
    elseif (!isset($v10) && isset($v30) && isset($v60)) {
        $result = $v30 * 4.0 + $v60 * 2.0;
    }

    return round($result / 6, 2);
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
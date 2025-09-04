<?php

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
	
	if ($c > 2) {
		$schema[] = 'vol_avg';
		$schema[] = 'nfk_avg';
	}

	return $schema;
}


function expandSensorDataWithCalculations($data, $deviceInfo) {
	$atts = $deviceInfo['attributes'];

	$fieldIndex = buildFieldIndex($data['schema']);

	$targetField = 'nfk_10cm';

	foreach ($data['data'] as &$row) {
		setRowValue($row, $targetField, 'golden', $fieldIndex);
	}
	unset($row); // break reference

	return $data;
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
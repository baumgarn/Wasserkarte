<?php

function managementEmailSettingsFromValue($value): ?array
{
	if (is_string($value)) {
		$value = json_decode($value, true);
	}
	if (!is_array($value) || !is_bool($value['enabled'] ?? null)) {
		return null;
	}
	// Migrate existing per-location rules to a common threshold.
	if (!array_key_exists('locations', $value) && isset($value['rules']) && is_array($value['rules'])) {
		$locations = [];
		$thresholds = [];
		foreach ($value['rules'] as $rule) {
			if (!is_array($rule) || !in_array($rule['threshold'] ?? null, [0, 30, 50], true)) return null;
			$locations[] = $rule['deviceId'] ?? null;
			$thresholds[] = $rule['threshold'];
		}
		$value['locations'] = $locations;
		$value['threshold'] = $thresholds ? max($thresholds) : 50;
	}
	if (!in_array($value['threshold'] ?? null, ['always', 0, 30, 50], true)
		|| !is_array($value['locations'] ?? null) || count($value['locations']) > 200) return null;
	$style = $value['style'] ?? 'detailed';
	if (!in_array($style, ['compact', 'detailed'], true)) return null;
	$locations = [];
	$seen = [];
	foreach ($value['locations'] as $id) {
		if (!is_string($id) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $id)
			|| isset($seen[strtolower($id)])) return null;
		$seen[strtolower($id)] = true;
		$locations[] = $id;
	}
	return ['enabled' => $value['enabled'], 'threshold' => $value['threshold'], 'style' => $style, 'locations' => $locations];
}

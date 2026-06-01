<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'] = array_merge(
	$cfg['suppress_issue_types'],
	[
		// Suppress issue types that currently exist in the codebase.
		'PhanPossiblyUndeclaredVariable',
		'PhanTypeMismatchArgumentNullable',
		'PhanUndeclaredClassMethod',
		'PhanUndeclaredExtendedClass',
		'PhanUndeclaredMethod',
		'PhanUndeclaredProperty',
		'PhanUndeclaredTypeParameter'
	]
);

return $cfg;

<?php

/**
 * Namespace internationalization for the MintyDocs extension.
 *
 * @author Yaron Koren
 */

$namespaceNames = [];

if ( !defined( 'MD_NS_DRAFT' ) ) {
	define( 'MD_NS_DRAFT', 620 );
	define( 'MD_NS_DRAFT_TALK', 621 );
}

$namespaceNames['en'] = [
	MD_NS_DRAFT      => 'Draft',
	MD_NS_DRAFT_TALK => 'Draft_talk',
];

/* Serbian Cyrillic (српски (ћирилица)) */
$namespaceNames['sr-ec'] = [
	MD_NS_DRAFT      => 'Нацрт',
	MD_NS_DRAFT_TALK => 'Разговор_о_нацрту',
];

/* Serbian Latin (srpski (latinica)) */
$namespaceNames['sr-el'] = [
	MD_NS_DRAFT      => 'Nacrt',
	MD_NS_DRAFT_TALK => 'Razgovor_o_nacrtu',
];

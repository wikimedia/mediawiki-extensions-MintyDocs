<?php

/**
 * Namespace internationalization for the MintyDocs extension.
 *
 * @author Yaron Koren
 */

$namespaceNames = array();

if ( !defined( 'MD_NS_DRAFT' ) ) {
	define( 'MD_NS_DRAFT', 620 );
	define( 'MD_NS_DRAFT_TALK', 621 );
}

$namespaceNames['en'] = array(
	MD_NS_DRAFT      => 'Draft',
	MD_NS_DRAFT_TALK => 'Draft_talk',
);
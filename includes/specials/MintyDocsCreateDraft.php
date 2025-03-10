<?php

use MediaWiki\Title\Title;

class MintyDocsCreateDraft extends MintyDocsPublish {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'MintyDocsCreateDraft' );

		self::$mNoActionNeededMessage = "No drafts need creating.";
		self::$mEditSummaryMsg = "mintydocs-createdraft-editsummary";
		self::$mSuccessMsg = "mintydocs-createdraft-success";
		self::$mSinglePageMsg = "mintydocs-createdraft-singlepage";
		self::$mButtonMsg = "mintydocs-createdraft-button";
	}

	function generateSourceTitle( $sourcePageName ) {
		return Title::newFromText( $sourcePageName, NS_MAIN );
	}

	function generateTargetTitle( $targetPageName ) {
		return Title::newFromText( $targetPageName, MD_NS_DRAFT );
	}

	function overwritingIsAllowed() {
		return false;
	}

	function validateTitle( $title ) {
		if ( $title->getNamespace() != NS_MAIN ) {
			throw new MWException( 'Page must be in the main namespace!' );
		}
	}

	function validateSinglePageAction( $fromTitle, $toTitle ) {
		if ( $toTitle->exists() ) {
			return 'A draft cannot be created for this page - it already exists.';
		}
	}

}

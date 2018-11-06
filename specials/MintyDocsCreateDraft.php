<?php

class MintyDocsCreateDraft extends MintyDocsPublish {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'MintyDocsCreateDraft' );

		self::$mFromNamespace = NS_MAIN;
		self::$mToNamespace = MD_NS_DRAFT;
		self::$mSinglePageMessage = "Create a draft page?";
		self::$mButtonText = "Create draft";
	}

}

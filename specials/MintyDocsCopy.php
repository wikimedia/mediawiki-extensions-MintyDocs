<?php

class MintyDocsCopy extends MintyDocsPublish {

	/**
	* Constructor
	*/
	function __construct() {
		parent::__construct( 'MintyDocsCopy' );

		self::$mNoActionNeededMessage = "None of the pages in this manual need to be copied.";
		self::$mEditSummary = 'Copied manual';
		self::$mSinglePageMessage = "Copy this page?";
		self::$mButtonText = "Copy";
	}

	function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$req = $this->getRequest();

		// Check permissions.
		if ( !$this->getUser()->isAllowed( 'mintydocs-administer' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$setVersion = $req->getCheck( 'mdSetVersion' );
		$publish = $req->getCheck( 'mdPublish' );
		if ( $setVersion || $publish ) {
			// Guard against cross-site request forgeries (CSRF).
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), $this->getName() );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling.";
				$out->addHTML( $text );
				return;
			}
		}

		$this->targetVersion = $req->getVal( 'target_version' );

		if ( $publish ) {
			$this->publishAll();
			return;
		}

		try {
			$title = $this->getTitleFromQuery( $query );
		} catch ( Exception $e ) {
			$out->addHTML( $e->getMessage() );
			return;
		}

		if ( $setVersion ) {
			$this->displayMainForm( $title );
			return;
		}

		$this->displayVersionSelector( $title );
	}

	function displayVersionSelector( $title ) {
		$out = $this->getOutput();

		$mdPage = MintyDocsUtils::pageFactory( $title );
		list( $productStr, $versionStr ) = $mdPage->getProductAndVersionStrings();
		$productPage = Title::newFromText( $productStr );
		$product = new MintyDocsProduct( $productPage );
		$versions = $product->getChildrenPages();
		$versionStrings = array();
		foreach ( $versions as $versionTitle ) {
			list ( $curProductStr, $curVersionStr ) = explode( '/', $versionTitle->getText() );
			if ( $curVersionStr !== $versionStr ) {
				$versionStrings[] = $curVersionStr;
			}
		}

		$optionsHtml = '';
		foreach ( $versionStrings as $curVersionStr ) {
			$optionsHtml .= Html::element(
				'option', [
					'value' => $curVersionStr
				], $curVersionStr
			) . "\n";
		}

		$text = Html::hidden( 'csrf', $this->getUser()->getEditToken( $this->getName() ) ) . "\n";
		$text .= "Select version to copy pages to: ";
		$text .= Html::rawElement( 'select', array( 'name' => 'target_version' ), $optionsHtml ) . "\n";
		$text .= '<p>' . Html::input( 'mdSetVersion', 'Continue', 'submit' ) . "</p>\n";
		$text = Html::rawElement( 'form', array( 'method' => 'post' ), $text );

		$out->addHtml( $text );
	}

	function displayPageParents( $mdPage ) {
		$targetTitle = $this->generateTargetTitle( $mdPage->getTitle()->getText() );
		$text = '<p>These will be copied to the location ' . Linker::link( $targetTitle ) . ".</p>\n";
		$text .= Html::hidden( 'target_version', $this->targetVersion );
		return $text;
	}

	function generateSourceTitle( $sourcePageName ) {
		return Title::newFromText( $sourcePageName, NS_MAIN );
	}

	function generateTargetTitle( $targetPageName ) {
		list( $product, $version, $manualAndTopic ) = explode( '/', $targetPageName, 3 );
		$targetPageName = "$product/" . $this->targetVersion . "/$manualAndTopic";
		return Title::newFromText( $targetPageName, NS_MAIN );
	}

	function overwritingIsAllowed() {
		return true;
	}

	function validateTitle( $title ) {
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( ! $mdPage instanceof MintyDocsManual ) {
			throw new MWException( 'Page must be a MintyDocs manual.' );
		}
	}

}

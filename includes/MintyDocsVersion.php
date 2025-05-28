<?php

class MintyDocsVersion extends MintyDocsPage {

	const RELEASED_STATUS = 'released';
	const UNRELEASED_STATUS = 'unreleased';
	const CLOSED_STATUS = 'closed';

	static function getPageTypeValue() {
		return 'Version';
	}

	function getDisplayName() {
		// No special display name for versions.
		return $this->getActualName();
	}

	function getStatus() {
		return strtolower( $this->getPageProp( 'MintyDocsStatus' ) );
	}

	/**
	 * For version pages, parameters can be inherited, but
	 * page contents cannot.
	 */
	function inheritsPageContents() {
		return false;
	}

	function getInheritedPage() {
		return null;
	}

	function getHeader() {
		global $wgMintyDocsShowBreadcrumbs;

		$text = '';

		$product = new MintyDocsProduct( $this->getParentPage() );
		if ( $wgMintyDocsShowBreadcrumbs ) {
			$versionDescText = wfMessage( 'mintydocs-version-desc', $this->getDisplayName() )
				->rawParams( $product->getLink() )
				->parse();
			$text .= Html::rawElement( 'div', [ 'class' => 'MintyDocsVersionDesc' ], $versionDescText );
		}

		$manualsListText = wfMessage( 'mintydocs-version-manuallist' ) . "\n";
		$manualsListText .= "<ul>\n";

		$manualsAndTheirRealNames = $this->getAllManuals();
		$manualsListStr = $this->getPossiblyInheritedParam( 'MintyDocsManualsList' );
		$manualsList = explode( ',', $manualsListStr );
		foreach ( $manualsList as $manualName ) {
			if ( array_key_exists( $manualName, $manualsAndTheirRealNames ) ) {
				$manual = $manualsAndTheirRealNames[$manualName];
				$manualsListText .= "<li>" . $manual->getLink() . "</li>\n";
				unset( $manualsAndTheirRealNames[$manualName] );
			} else {
				// Display anyway, so people know something's wrong.
				$manualsListText .= Html::element( 'li', [], $manualName ) . "\n";
			}
		}
		$manualsListText .= "</ul>\n";

		if ( count( $manualsAndTheirRealNames ) > 0 ) {
			// Display error
			// @TODO - this is hardcoded for now, but change this
			// to an i18n message if it works out as a feature.
			$errorMsg = "The following manuals are defined for this version but are not included in the list of manuals: " .
				implode( ', ', array_keys( $manualsAndTheirRealNames ) );
			// $errorMsg = wfMessage( 'mintydocs-version-extramanuals', implode( ', ', array_keys( $manualsAndTheirRealNames ) ) );
			$text .= Html::warningBox( $errorMsg );
		}

		$text .= Html::rawElement( 'div', [ 'class' => 'MintyDocsManualList' ], $manualsListText );

		return $text;
	}

	function getEquivalentPageForVersion( $version ) {
		return $version->getTitle();
	}

	function getAllManuals() {
		$manualPages = $this->getChildrenPages();
		$manualsAndTheirRealNames = [];
		foreach ( $manualPages as $manualPage ) {
			$mdManualPage = new MintyDocsManual( $manualPage );
			$actualName = $mdManualPage->getActualName();
			$manualsAndTheirRealNames[$actualName] = $mdManualPage;
		}

		return $manualsAndTheirRealNames;
	}

	public function getProductAndVersion() {
		$product = new MintyDocsProduct( $this->getParentPage() );
		return [ $product, $this ];
	}

}

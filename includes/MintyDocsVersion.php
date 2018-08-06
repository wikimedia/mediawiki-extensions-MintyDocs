<?php

class MintyDocsVersion extends MintyDocsPage {

	const RELEASED_STATUS = 'Released';
	const UNRELEASED_STATUS = 'Unreleased';
	const CLOSED_STATUS = 'Closed';
	
	static function getPageTypeValue() {
		return 'Version';
	}
	
	function getDisplayName() {
		// No special display name for versions.
		return $this->getActualName();
	}
	
	function getStatus() {
		return $this->getPageProp( 'MintyDocsStatus' );
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
		$product = new MintyDocsProduct( $this->getParentPage() );
		$versionDescText = wfMessage( 'mintydocs-version-desc', $this->getDisplayName(), $product->getLink() )->text();
		$text = Html::rawElement( 'div', array( 'class' => 'MintyDocsVersionDesc' ), $versionDescText );

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
				$manualsListText .= "<li>$manualName</li>\n";
			}
		}
		$manualsListText .= "</ul>\n";
		
		if ( count( $manualsAndTheirRealNames ) > 0 ) {
			// Display error
			// @TODO - this is hardcoded for now, but change this
			// to an i18n message if it works out as a feature.
			$errorMsg = "The following manuals are defined for this version but are not included in the list of manuals: " .
				implode( ', ', array_keys( $manualsAndTheirRealNames ) );
			//$errorMsg = wfMessage( 'mintydocs-version-extramanuals', implode( ', ', array_keys( $manualsAndTheirRealNames ) ) );
			$text .= Html::rawElement( 'div', array( 'class' => 'warningbox' ), $errorMsg );
		}
		
		$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsManualList' ), $manualsListText );

		return $text;
	}

	function getEquivalentPageForVersion( $version ) {
		return $version->getTitle();
	}

	function getAllManuals() {
		$manualPages = $this->getChildrenPages();
		$manualsAndTheirRealNames = array();
		foreach ( $manualPages as $manualPage ) {
			$mdManualPage = new MintyDocsManual( $manualPage );
			$actualName = $mdManualPage->getActualName();
			$manualsAndTheirRealNames[$actualName] = $mdManualPage;
		}

		return $manualsAndTheirRealNames;
	}

	public function getProductAndVersion() {
		$product = new MintyDocsProduct( $this->getParentPage() );
		return array( $product, $this );
	}

}
<?php

class MintyDocsUtils {

	static public $pageClassesInOrder = array( 'MintyDocsProduct', 'MintyDocsVersion', 'MintyDocsManual', 'MintyDocsTopic' );

	static public function getPagePropForTitle( $title, $propName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'page_props',
			array(
				'pp_value'
			),
			array(
				'pp_page' => $title->getArticleID(),
				'pp_propname' => $propName
			)
		);
		// First row of the result set.
		$row = $dbr->fetchRow( $res );
		if ( $row == null ) {
			return null;
		}

		return $row[0];
	}

	static public function titlePagePropIncludesValue( $title, $propName, $value ) {
		$fullValue = self::getPagePropForTitle( $title, $propName );
		$individualValues = explode( ',', $fullValue );
		foreach ( $individualValues as $individualValue ) {
			if ( trim( $individualValue ) == $value ) {
				return true;
			}
		}
		return false;
	}

	static public function getPageType( $title ) {
		return self::getPagePropForTitle( $title, 'MintyDocsPageType' );
	}

	static public function pageFactory( $title ) {
		$pageType = self::getPageType( $title );
		if ( $pageType == 'Product' ) {
			return new MintyDocsProduct( $title );
		} elseif ( $pageType == 'Version' ) {
			return new MintyDocsVersion( $title );
		} elseif ( $pageType == 'Manual' ) {
			return new MintyDocsManual( $title );
		} elseif ( $pageType == 'Topic' ) {
			return new MintyDocsTopic( $title );
		}

		return null;
	}

	static function getPageParts( $title ) {
		$pageName = $title->getText();
		$lastSlashPos = strrpos( $pageName, '/' );
		if ( $lastSlashPos === false ) {
			return array( null, $pageName );
		}
		$parentPageName = substr( $pageName, 0, $lastSlashPos );
		$thisPageName = substr( $pageName, $lastSlashPos + 1 );
		return array( $parentPageName, $thisPageName );
	}

	/**
	 * Helper function - returns whether the user is currently requesting
	 * a page via the simple URL for it - not specifying a version number,
	 * not editing the page, etc.
	 *
	 * Copied from the Approved Revs extension's
	 * ApprovedRevs::isDefaultPageRequest().
	 */
	public static function isDefaultPageRequest() {
		global $wgRequest;
		if ( $wgRequest->getCheck( 'oldid' ) ) {
			return false;
		}
		// check if it's an action other than viewing
		global $wgRequest;
		if ( $wgRequest->getCheck( 'action' ) &&
			$wgRequest->getVal( 'action' ) != 'view' &&
			$wgRequest->getVal( 'action' ) != 'purge' &&
			$wgRequest->getVal( 'action' ) != 'render' ) {
				return false;
		}
		return true;
	}

}
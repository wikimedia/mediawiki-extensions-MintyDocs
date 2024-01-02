<?php

use MediaWiki\MediaWikiServices;

class MintyDocsUtils {

	public static $pageClassesInOrder = [ 'MintyDocsProduct', 'MintyDocsVersion', 'MintyDocsManual', 'MintyDocsTopic' ];

	public static function getReadDB() {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		if ( method_exists( $lbFactory, 'getReplicaDatabase' ) ) {
			// MW 1.40+
			return $lbFactory->getReplicaDatabase();
		} else {
			return $lbFactory->getMainLB()->getMaintenanceConnectionRef( DB_REPLICA );
		}
	}

	public static function getPagePropForTitle( Title $title, $propName ) {
		$dbr = self::getReadDB();
		$res = $dbr->select( 'page_props',
			[
				'pp_value'
			],
			[
				'pp_page' => $title->getArticleID(),
				'pp_propname' => $propName
			]
		);
		// First row of the result set.
		$row = $res->fetchRow();
		if ( $row == null ) {
			return null;
		}

		return $row[0];
	}

	public static function titlePagePropIncludesValue( Title $title, $propName, $value ) {
		$fullValue = self::getPagePropForTitle( $title, $propName );
		$individualValues = explode( ',', $fullValue );
		foreach ( $individualValues as $individualValue ) {
			if ( trim( $individualValue ) == $value ) {
				return true;
			}
		}
		return false;
	}

	public static function getPageType( Title $title ) {
		return self::getPagePropForTitle( $title, 'MintyDocsPageType' );
	}

	public static function pageFactory( Title $title ) {
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

	static function getPageParts( Title $title ) {
		$pageName = $title->getPrefixedText();
		$lastSlashPos = strrpos( $pageName, '/' );
		if ( $lastSlashPos === false ) {
			return [ null, $pageName ];
		}
		$parentPageName = substr( $pageName, 0, $lastSlashPos );
		$thisPageName = substr( $pageName, $lastSlashPos + 1 );
		return [ $parentPageName, $thisPageName ];
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

	public static function createOrModifyPage( Title $title, $pageText, $editSummary, $user ) {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		if ( !$wikiPage ) {
			throw new MWException( 'Wiki page not found "' . $title->getPrefixedDBkey() . '"' );
		}

		// It's strange that doEditContent() doesn't
		// automatically attach the 'bot' flag when the user
		// is a bot...
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( $permissionManager->userHasRight( $user, 'bot' ) ) {
			$flags = EDIT_FORCE_BOT;
		} else {
			$flags = 0;
		}

		$newContent = new WikitextContent( $pageText );

		$updater = $wikiPage->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $newContent );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( $editSummary ), $flags );
	}

	/**
	 * Get a content language object.
	 *
	 * @return Language
	 */
	public static function getContLang() {
		return MediaWikiServices::getInstance()->getContentLanguage();
	}

	/**
	 * Creates the name of the page that appears in the URL;
	 * this method is necessary because Title::getPartialURL(), for
	 * some reason, doesn't include the namespace.
	 * Based on PFUtils::titleURLString() from Page Forms.
	 *
	 * @param Title $title
	 * @return string
	 */
	public static function titleURLString( Title $title ) {
		$namespace = $title->getNsText();
		if ( $namespace !== '' ) {
			$namespace .= ':';
		}
		if ( method_exists( "MediaWiki\\MediaWikiServices", "getNamespaceInfo" ) ) {
			$isCapitalized = MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->isCapitalized( $title->getNamespace() );
		} else {
			$isCapitalized = MWNamespace::isCapitalized( $title->getNamespace() );
		}
		if ( $isCapitalized ) {
			return $namespace . self::getContLang()->ucfirst( $title->getPartialURL() );
		} else {
			return $namespace . $title->getPartialURL();
		}
	}

}

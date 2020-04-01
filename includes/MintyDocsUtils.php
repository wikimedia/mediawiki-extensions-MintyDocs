<?php

use MediaWiki\MediaWikiServices;

class MintyDocsUtils {

	public static $pageClassesInOrder = [ 'MintyDocsProduct', 'MintyDocsVersion', 'MintyDocsManual', 'MintyDocsTopic' ];

	public static function getPagePropForTitle( $title, $propName ) {
		$dbr = wfGetDB( DB_REPLICA );
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
		$row = $dbr->fetchRow( $res );
		if ( $row == null ) {
			return null;
		}

		return $row[0];
	}

	public static function titlePagePropIncludesValue( $title, $propName, $value ) {
		$fullValue = self::getPagePropForTitle( $title, $propName );
		$individualValues = explode( ',', $fullValue );
		foreach ( $individualValues as $individualValue ) {
			if ( trim( $individualValue ) == $value ) {
				return true;
			}
		}
		return false;
	}

	public static function getPageType( $title ) {
		return self::getPagePropForTitle( $title, 'MintyDocsPageType' );
	}

	public static function pageFactory( $title ) {
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

	public static function createOrModifyPage( $title, $pageText, $editSummary, $userID = null ) {
		global $wgUser;

		if ( $title === null ) {
			throw new MWException( "Invalid title" );
		}

		$wikiPage = new WikiPage( $title );
		if ( !$wikiPage ) {
			throw new MWException( 'Wiki page not found "' . $title->getPrefixedDBkey() . '"' );
		}

		if ( $userID != null ) {
			// Change global $wgUser variable to the one
			// specified only for the extent of this edit.
			$actual_user = $wgUser;
			$wgUser = User::newFromId( $userID );
		}

		// It's strange that doEditContent() doesn't
		// automatically attach the 'bot' flag when the user
		// is a bot...
		if ( method_exists( 'MediaWiki\Permissions\PermissionManager', 'userHasRight' ) ) {
			// MW 1.34+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		} else {
			$permissionManager = null;
		}
		if ( self::userIsAllowed( $wgUser, 'bot', $permissionManager ) ) {
			$flags = EDIT_FORCE_BOT;
		} else {
			$flags = 0;
		}

		$newContent = new WikitextContent( $pageText );
		$wikiPage->doEditContent( $newContent, $editSummary, $flags );

		if ( $userID != null ) {
			$wgUser = $actual_user;
		}
	}

	/**
	 * @param User $user
	 * @param Action $action
	 * @param PermissionManager $permissionManager
	 * @return bool
	 */
	public static function userIsAllowed( $user, $action, $permissionManager ) {
		if ( $permissionManager != null ) {
			return $permissionManager->userHasRight( $user, $action );
		} else {
			return $user->isAllowed( $action );
		}
	}

	/**
	 * Get a content language (old $wgContLang) object. For MW < 1.32,
	 * return the global.  For all others, use MediaWikiServices.
	 *
	 * @return Language
	 */
	public static function getContLang() {
		if ( method_exists( "MediaWiki\\MediaWikiServices", "getContentLanguage" ) ) {
			return MediaWikiServices::getInstance()->getContentLanguage();
		} else {
			global $wgContLang;
			return $wgContLang;
		}
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
	public static function titleURLString( $title ) {
		$namespace = $title->getNsText();
		if ( $namespace !== '' ) {
			$namespace .= ':';
		}
		if ( MWNamespace::isCapitalized( $title->getNamespace() ) ) {
			return $namespace . self::getContLang()->ucfirst( $title->getPartialURL() );
		} else {
			return $namespace . $title->getPartialURL();
		}
	}

}

<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

abstract class MintyDocsPage {

	protected $mTitle = null;
	protected $mIsInvalid = false;

	/**
	 * See if the specified page can set to this type.
	 */
	public static function checkPageEligibility( $parentPageName, $thisPageName ) {
		// Check if it has a parent page.
		if ( $parentPageName == null ) {
			// @TODO - add i18n for page type.
			$pageType = static::getPageTypeValue();
			return wfMessage( "mintydocs-noparentpage", $pageType )->parse();
		}

		// Check if its parent page belongs to the right class.
		$parentPageTitle = Title::newFromText( $parentPageName );
		$pageLevel = array_search( get_called_class(), MintyDocsUtils::$pageClassesInOrder );
		$parentClass = MintyDocsUtils::$pageClassesInOrder[$pageLevel - 1];
		$parentPageType = $parentClass::getPageTypeValue();
		if ( MintyDocsUtils::getPageType( $parentPageTitle ) != $parentPageType ) {
			// @TODO - add i18n for page type.
			return wfMessage( "mintydocs-invalidparentpage", $parentPageName, $parentPageType )->parse();
		}

		return null;
	}

	public function __construct( Title $title ) {
		$this->mTitle = $title;
	}

	public function getTitle() {
		return $this->mTitle;
	}

	public function getPageProp( $propName ) {
		return MintyDocsUtils::getPagePropForTitle( $this->mTitle, $propName );
	}

	function getChildrenPages() {
		$childrenPages = [];

		$dbr = MintyDocsUtils::getReadDB();
		$res = $dbr->select( 'page_props',
			[
				'pp_page'
			],
			[
				'pp_value' => $this->mTitle->getPrefixedText(),
				'pp_propname' => 'MintyDocsParentPage'
			]
		);

		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
		while ( $row = $res->fetchRow() ) {
			$childrenPages[] = Title::newFromID( $row[0] );
		}

		return $childrenPages;
	}

	static function getPageTypeValue() {
	}

	public function inheritsPageContents() {
		// @TODO - change null to false
		return $this->getPageProp( 'MintyDocsInherit' );
	}

	public function inheritsParams() {
		// @TODO - change null to false
		return $this->getPageProp( 'MintyDocsInherit' );
	}

	/**
	 * Should not be called for Product pages.
	 */
	public function getProductAndVersionStrings() {
		$pageName = $this->mTitle->getPrefixedText();
		$pageNameParts = explode( '/', $pageName );
		// The product page name may have some slashes in it.
		$pageLevel = array_search( get_class( $this ), MintyDocsUtils::$pageClassesInOrder ) + 1;
		$numProductNameParts = count( $pageNameParts ) - $pageLevel + 1;
		$productNameParts = array_slice( $pageNameParts, 0, $numProductNameParts );
		$productName = implode( '/', $productNameParts );

		$versionString = $pageNameParts[$numProductNameParts];

		return [ $productName, $versionString ];
	}

	public function getProductAndVersion() {
		[ $productName, $versionString ] = $this->getProductAndVersionStrings();
		$productPage = Title::newFromText( $productName );
		$product = new MintyDocsProduct( $productPage );
		$versionPage = Title::newFromText( $productName . '/' . $versionString );
		$version = new MintyDocsVersion( $versionPage );
		return [ $product, $version ];
	}

	public function getEquivalentPagesForPreviousVersions() {
		[ $productName, $versionString ] = $this->getProductAndVersionStrings();
		$productPage = Title::newFromText( $productName );
		$product = new MintyDocsProduct( $productPage );

		$equivalentPages = [];
		$versionsAndTheirPages = $product->getVersionsBefore( $versionString );
		foreach ( $versionsAndTheirPages as $curVersionString => $curVersion ) {
			$curEquivalentPage = $this->getEquivalentPageForVersion( $curVersion );
			if ( $curEquivalentPage != null ) {
				$equivalentPages[] = $curEquivalentPage;
			}
		}

		return $equivalentPages;
	}

	public function getInheritedPage() {
		// Quick escape.
		if ( !$this->inheritsPageContents() ) {
			return null;
		}

		$equivalentPages = $this->getEquivalentPagesForPreviousVersions();
		$className = get_called_class();

		foreach ( $equivalentPages as $equivalentPage ) {
			$mdEquivalentPage = new $className( $equivalentPage );
			if ( !$mdEquivalentPage->inheritsPageContents() ) {
				return $mdEquivalentPage;
			}
		}
		throw new MWException( "There is no version from which to inherit!" );
	}

	/**
	 * Inheritance of parser function params (like "topics list=") works
	 * slightly differently from inheritance of page free text: in both
	 * cases it's triggered when the "inherit" parameter is used, but for
	 * free text the code automatically goes back to the last version that
	 * doesn't include "inherit", while for parameters the code stops with
	 * the last version that has that parameter defined (which could even
	 * be the current version).
	 */
	public function getPossiblyInheritedParam( $pagePropName ) {
		$paramValue = $this->getPageProp( $pagePropName );
		if ( $paramValue != null ) {
			return $paramValue;
		}
		if ( !$this->inheritsParams() ) {
			return null;
		}

		return $this->getInheritedParam( $pagePropName );
	}

	public function getInheritedParam( $pagePropName ) {
		$equivalentPages = $this->getEquivalentPagesForPreviousVersions();

		foreach ( $equivalentPages as $equivalentPage ) {
			$paramValue = MintyDocsUtils::getPagePropForTitle( $equivalentPage, $pagePropName );
			if ( $paramValue !== null ) {
				return $paramValue;
			}
			$inherits = MintyDocsUtils::getPagePropForTitle( $equivalentPage, 'MintyDocsInherit' );
			if ( !$inherits ) {
				return null;
			}
		}
		return null;
	}

	abstract function getHeader();

	function getFooter() {
		return null;
	}

	function getSidebarText() {
		return null;
	}

	function getActualName() {
		$pageName = $this->mTitle->getPrefixedText();
		$lastSlashPos = strrpos( $pageName, '/' );
		if ( $lastSlashPos === false ) {
			return $pageName;
		}
		return substr( $pageName, $lastSlashPos + 1 );
	}

	function getDisplayName() {
		return $this->getPageProp( 'displaytitle' );
	}

	public function getParentPage() {
		$pageName = $this->mTitle->getPrefixedText();
		$lastSlashPos = strrpos( $pageName, '/' );
		if ( $lastSlashPos === false ) {
			return null;
		}
		$parentPageName = substr( $pageName, 0, $lastSlashPos );
		return Title::newFromText( $parentPageName );
	}

	public function getLink() {
		return MediaWikiServices::getInstance()->getLinkRenderer()
			->makeLink( $this->mTitle, $this->getDisplayName() );
	}

	/**
	 * Used only for Manual and Topic.
	 */
	function getEquivalentsInOtherVersions( $product, $thisVersionString ) {
		$equivalents = [];
		$versionsAndTheirPages = $product->getVersions();
		foreach ( $versionsAndTheirPages as $versionString => $version ) {
			if ( $versionString == $thisVersionString ) {
				continue;
			}
			$equivPage = $this->getEquivalentPageForVersion( $version );
			if ( $equivPage != null ) {
				$equivalents[$versionString] = $equivPage;
			}
		}
		return $equivalents;
	}

	/**
	 * Used only for Manual and Topic.
	 */
	function getEquivalentPageForVersion( $version ) {
		$equivPageName = $this->getEquivalentPageNameForVersion( $version );
		$equivPage = Title::newFromText( $equivPageName );
		if ( $equivPage->exists() && MintyDocsUtils::getPageType( $equivPage ) == $this->getPageTypeValue() ) {
			return $equivPage;
		}
		return null;
	}

	function getEquivalentPageNameForVersion( $version ) {
		return null;
	}

	public function userCanView( $user ) {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		if ( $this->mIsInvalid ) {
			// If it's a standalone topic in the draft namespace,
			// and the user has no special permissions, then they
			// can't view it. Otherwise, they can.
			return (
				!( $this instanceof MintyDocsTopic ) ||
				$this->mTitle->getNamespace() !== MD_NS_DRAFT ||
				$permissionManager->userHasRight( $user, 'mintydocs-administer' ) ||
				$permissionManager->userHasRight( $user, 'mintydocs-edit' ) ||
				$permissionManager->userHasRight( $user, 'mintydocs-preview' )
			);
		}

		[ $product, $version ] = $this->getProductAndVersion();

		// If this is a draft page, only people with some kind of
		// MintyDocs permission can view it.
		if ( $this->mTitle->getNamespace() == MD_NS_DRAFT ) {
			if ( !$permissionManager->userHasRight( $user, 'mintydocs-administer' ) &&
			!$permissionManager->userHasRight( $user, 'mintydocs-edit' ) &&
			!$permissionManager->userHasRight( $user, 'mintydocs-preview' ) &&
			!$product->userIsAdmin( $user ) &&
			!$product->userIsEditor( $user ) &&
			!$product->userIsPreviewer( $user ) ) {
				return false;
			}
		}
		$versionStatus = $version->getStatus();

		if ( $versionStatus == MintyDocsVersion::RELEASED_STATUS ) {
			// Everyone can view this.
			return true;
		} elseif ( $versionStatus == MintyDocsVersion::CLOSED_STATUS ) {
			if ( $permissionManager->userHasRight( $user, 'mintydocs-administer' ) ) {
				return true;
			}
			if ( $product->userIsAdmin( $user ) ) {
				return true;
			}
			return false;
		} else { // UNRELEASED_STATUS - the default
			if ( $permissionManager->userHasRight( $user, 'mintydocs-administer' ) ||
				$permissionManager->userHasRight( $user, 'mintydocs-edit' ) ||
				$permissionManager->userHasRight( $user, 'mintydocs-preview' ) ) {
				return true;
			}
			if ( $product->userIsAdmin( $user ) ||
				$product->userIsEditor( $user ) ||
				$product->userIsPreviewer( $user ) ) {
				return true;
			}
			return false;
		}

		// If it's some other text, or blank, let everyone view it.
		return true;
	}

	public function hasDraftPage() {
		if ( $this->mTitle->getNamespace() == NS_MAIN ) {
			$possibleDraftPage = Title::newFromText( $this->mTitle->getText(), MD_NS_DRAFT );
			if ( $possibleDraftPage->exists() ) {
				return true;
			}
		}
		return false;
	}

	public function userCanEdit( $user ) {
		if ( $this->mIsInvalid ) {
			return true;
		}

		// If there's a corresponding draft page, it's non-editable,
		// unless the user has special permission.
		if ( $this->hasDraftPage() && !$user->isAllowed( 'mintydocs-editlive' ) ) {
			return false;
		}

		[ $product, $version ] = $this->getProductAndVersion();

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		$versionStatus = $version->getStatus();

		if ( $versionStatus == MintyDocsVersion::RELEASED_STATUS ) {
			// Everyone can edit this, as far as MintyDocs is concerned.
			return true;
		} elseif ( $versionStatus == MintyDocsVersion::CLOSED_STATUS ) {
			if ( $permissionManager->userHasRight( $user, 'mintydocs-administer' ) ) {
				return true;
			}
			if ( $product->userIsAdmin( $user ) ) {
				return true;
			}
			return false;
		} else { // UNRELEASED_STATUS - the default
			if ( $permissionManager->userHasRight( $user, 'mintydocs-administer' ) ||
				$permissionManager->userHasRight( $user, 'mintydocs-edit' ) ) {
				return true;
			}
			if ( $product->userIsAdmin( $user ) ||
				$product->userIsEditor( $user ) ) {
				return true;
			}
			return false;
		}

		// If the status is some other value, or blank, let everyone view it.
		return true;
	}

	public function userCanAdminister( $user ) {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		if ( ( $this instanceof MintyDocsTopic ) && $this->mIsInvalid ) {
			// If it's a standalone topic in the draft namespace,
			// it can only be published if the user is globally
			// an administrator.
			return $permissionManager->userHasRight( $user, 'mintydocs-administer' );
		}

		if ( $permissionManager->userHasRight( $user, 'mintydocs-administer' ) ) {
			return true;
		}

		[ $product, $version ] = $this->getProductAndVersion();
		if ( $product->userIsAdmin( $user ) ) {
			return true;
		}

		return false;
	}

}

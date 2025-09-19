<?php

use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class MintyDocsHooks {

	public static function registerExtension() {
		global $wgNamespaceRobotPolicies;

		if ( !defined( 'MD_NS_DRAFT' ) ) {
			define( 'MD_NS_DRAFT', 620 );
			define( 'MD_NS_DRAFT_TALK', 621 );
		}

		// Search engines should not index draft content.
		// Should MD_NS_DRAFT_TALK be included here also? It seems
		// strange to have discussions about draft pages show up in
		// search engines, but then again, if other talk pages get
		// indexed, there's no reason not to index these as well.
		$wgNamespaceRobotPolicies[MD_NS_DRAFT] = 'noindex';
	}

	/**
	 * @param Parser &$parser
	 */
	public static function registerParserFunctions( &$parser ) {
		$parser->setFunctionHook( 'mintydocs_product', [ 'MintyDocsParserFunctions', 'renderProduct' ] );
		$parser->setFunctionHook( 'mintydocs_version', [ 'MintyDocsParserFunctions', 'renderVersion' ] );
		$parser->setFunctionHook( 'mintydocs_manual', [ 'MintyDocsParserFunctions', 'renderManual' ] );
		$parser->setFunctionHook( 'mintydocs_topic', [ 'MintyDocsParserFunctions', 'renderTopic' ] );
		$parser->setFunctionHook( 'mintydocs_link', [ 'MintyDocsParserFunctions', 'renderLink' ] );
	}

	/**
	 * Register the "draft" namespaces for MintyDocs.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 *
	 * @param array &$list
	 */
	public static function registerNamespaces( array &$list ) {
		global $wgNamespacesWithSubpages;

		$list[MD_NS_DRAFT] = 'Draft';
		$list[MD_NS_DRAFT_TALK] = 'Draft_talk';

		// Support subpages only for talk pages by default
		$wgNamespacesWithSubpages[MD_NS_DRAFT_TALK] = true;
	}

	/**
	 * @param Title &$title
	 * @param User &$user
	 * @param string $action
	 * @param string &$result
	 * @return bool|null
	 */
	public static function checkPermissions( &$title, &$user, $action, &$result ) {
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return;
		}

		// Unlike most hooks, getUserPermissionsErrors requires returning false
		// if there's an error.
		if ( ( $action == 'edit' || $action == 'formedit' ) && !$mdPage->userCanEdit( $user ) ) {
			$result = false;
			return false;
		} elseif ( $action == 'read' && !$mdPage->userCanView( $user ) ) {
			$result = false;
			return false;
		}
	}

	/**
	 * @param OutputPage &$out
	 * @param string &$text
	 * @return bool
	 */
	public static function addTextToPage( &$out, &$text ) {
		global $wgMintyDocsDisplayFooterElementsInSidebar;

		$action = Action::getActionName( $out->getContext() );
		if ( $action != 'view' ) {
			return;
		}
		$title = $out->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return;
		}
		$inheritedPage = $mdPage->getInheritedPage();
		if ( $inheritedPage !== null ) {
			$services = MediaWikiServices::getInstance();
			$wikiPage = $services->getWikiPageFactory()->newFromTitle( $inheritedPage->getTitle() );
			$rawAccess = MediaWiki\Revision\RevisionRecord::RAW;
			$inheritedPageText = $wikiPage->getContent( $rawAccess )->getText();
			$text .= $services->getParser()->parse( $inheritedPageText, $title, ParserOptions::newFromAnon() )->getText();
		}
		$text = $mdPage->getHeader() . $text;

		if ( !$wgMintyDocsDisplayFooterElementsInSidebar ) {
			$text .= $mdPage->getFooter();
		}
	}

	/**
	 * @param OutputPage &$out
	 * @param string &$text
	 * @return bool
	 */
	public static function showNoticeForDraftPage( &$out, &$text ) {
		$action = Action::getActionName( $out->getContext() );
		if ( $action != 'view' ) {
			return;
		}
		$title = $out->getTitle();
		if ( $title->getNamespace() !== MD_NS_DRAFT ) {
			return;
		}

		$liveTitle = Title::newFromText( $title->getText(), NS_MAIN );
		if ( $liveTitle->exists() ) {
			$req = $out->getContext()->getRequest();
			$query = [];
			// Pass on the "context" stored in the query string.
			$queryStringParams = [ 'contextProduct', 'contextVersion', 'contextManual' ];
			foreach ( $queryStringParams as $param ) {
				if ( $req->getCheck( $param ) ) {
					$query[$param] = $req->getVal( $param );
				}
			}
			$linkToPublished = MediaWikiServices::getInstance()->getLinkRenderer()
				->makeKnownLink( $liveTitle, $html = null, $attribs = [], $query );
			$msg = "This is a draft page; the published version of this page can be found at $linkToPublished.";
		} else {
			$msg = 'This is a draft page; it has not yet been published.';
		}
		$warningText = Html::warningBox( $msg );
		$text = $warningText . $text;
	}

	/**
	 * Only users with special permission can edit "live" pages that have
	 * an equivalent page in the draft namespace. If this is what's
	 * happening, display a warning at the top to remind the user that
	 * they are doing something thiat is not ideal.
	 */
	public static function addLivePageEditWarning( EditPage $editPage, OutputPage $output ) {
		$title = $editPage->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null || !$mdPage->hasDraftPage() ) {
			return;
		}
		$draftTitle = Title::newFromText( $title->getText(), MD_NS_DRAFT );
		$draftLink = MediaWikiServices::getInstance()->getLinkRenderer()
			->makeKnownLink( $draftTitle, 'draft page' );
		$msg = "Warning: this page has a corresponding $draftLink. It is generally better to edit the draft page, and then publish it, rather than to edit this page directly.";
		$editPage->editFormPageTop .= Html::warningBox( $msg );
	}

	/**
	 * @param Skin $skin
	 * @param array[] &$sidebar
	 * @return bool
	 */
	public static function addTextToSidebar( Skin $skin, &$sidebar ) {
		global $wgRequest;
		global $wgMintyDocsDisplayFooterElementsInSidebar;

		if ( !$wgMintyDocsDisplayFooterElementsInSidebar ) {
			return;
		}

		$contextProduct = $wgRequest->getVal( 'contextProduct' );
		$contextVersion = $wgRequest->getVal( 'contextVersion' );
		$contextManual = $wgRequest->getVal( 'contextManual' );

		// If an entire context has been set via the URL query string,
		// generate a manual page based on that, so we can get a
		// sidebar in place.
		if ( $contextProduct && $contextVersion && $contextManual ) {
			$manualName = "$contextProduct/$contextVersion/$contextManual";
			$title = Title::newFromText( $manualName );
		} else {
			$title = $skin->getTitle();
		}

		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return;
		}

		$sidebarContents = $mdPage->getSidebarText();
		if ( $sidebarContents == null ) {
			return;
		}

		[ $header, $contents ] = $sidebarContents;
		$sidebar[$header] = $contents;
	}

	/**
	 * Called by the PageSaveComplete hook.
	 */
	public static function setSearchText( WikiPage $wikiPage, User $user,
		string $summary, int $flags,
		MediaWiki\Revision\RevisionStoreRecord $revisionRecord,
		MediaWiki\Storage\EditResult $editResult ) {
		if ( $revisionRecord === null ) {
			return;
		}

		$title = $wikiPage->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );

		if ( $mdPage == null ) {
			return;
		}

		if ( !$mdPage->inheritsPageContents() ) {
			return;
		}

		// @TODO - does the template call need to be added/removed/etc.?
		//$newSearchText = $mdPage->getPageContents();

		//DeferredUpdates::addUpdate( new SearchUpdate( $title->getArticleID(), $title->getText(), $newSearchText ) );
	}

	/**
	 * @param array &$vars
	 * @return bool
	 */
	static function setGlobalJSVariables( &$vars ) {
		global $wgScriptPath;

		$vars['wgMintyDocsScriptPath'] = $wgScriptPath . '/extensions/MintyDocs';
	}

	/**
	 * Register wiki markup words associated with MAG_NIFTYVAR as a variable
	 *
	 * @param array &$customVariableIDs
	 * @return bool
	 */
	public static function declareVarIDs( &$customVariableIDs ) {
		$customVariableIDs[] = 'MAG_MINTYDOCSPRODUCT';
		$customVariableIDs[] = 'MAG_MINTYDOCSVERSION';
		$customVariableIDs[] = 'MAG_MINTYDOCSMANUAL';
		$customVariableIDs[] = 'MAG_MINTYDOCSDISPLAYNAME';
	}

	/**
	 * Assign a value to our variable
	 *
	 * @param Parser $parser
	 * @param array &$cache
	 * @param string $magicWordId
	 * @param string &$ret
	 * @return bool
	 */
	public static function assignAValue( $parser, &$cache, $magicWordId, &$ret ) {
		$handledIDs = [
			'MAG_MINTYDOCSPRODUCT',
			'MAG_MINTYDOCSVERSION',
			'MAG_MINTYDOCSMANUAL',
			'MAG_MINTYDOCSDISPLAYNAME'
		];
		if ( !in_array( $magicWordId, $handledIDs ) ) {
			return;
		}
		$title = $parser->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return;
		}
		$className = get_class( $mdPage );
		switch ( $magicWordId ) {
			case 'MAG_MINTYDOCSPRODUCT':
				if ( $className == 'MintyDocsProduct' ) {
					return;
				}
				[ $product, $version ] = $mdPage->getProductAndVersion();
				$ret = $cache[$magicWordId] = $product->getDisplayName();
				break;
			case 'MAG_MINTYDOCSVERSION':
				if ( $className == 'MintyDocsProduct' || $className == 'MintyDocsVersion' ) {
					return;
				}
				[ $productName, $versionString ] = $mdPage->getProductAndVersionStrings();
				$ret = $cache[$magicWordId] = $versionString;
				break;
			case 'MAG_MINTYDOCSMANUAL':
				if ( $className == 'MintyDocsProduct' || $className == 'MintyDocsVersion' || $className == 'MintyDocsManual' ) {
					return;
				}
				$manual = $mdPage->getManual();
				$ret = $cache[$magicWordId] = $manual->getDisplayName();
				break;
			case 'MAG_MINTYDOCSDISPLAYNAME':
				$ret = $cache[$magicWordId] = $mdPage->getDisplayName();
				break;
			default:
				break;
		}
	}

	public static function makeDraftsNonSearchable( &$searchableNamespaces ) {
		$user = RequestContext::getMain()->getUser();

		// Allow for searching of the Draft and Draft_talk
		// namespaces only by MD administrators and editors.
		if (
			$user->isAllowed( 'mintydocs-administer' ) ||
			$user->isAllowed( 'mintydocs-edit' )
		) {
			return;
		}

		unset( $searchableNamespaces[MD_NS_DRAFT] );
		unset( $searchableNamespaces[MD_NS_DRAFT_TALK] );
	}

	/**
	 * @param PFFormPrinter &$formPrinter
	 */
	public static function registerPageFormsInputs( &$formPrinter ) {
		$formPrinter->registerInputType( 'MintyDocsTOCInput' );
	}
}

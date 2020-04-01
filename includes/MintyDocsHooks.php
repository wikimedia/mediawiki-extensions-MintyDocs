<?php

use MediaWiki\MediaWikiServices;

class MintyDocsHooks {

	public static function registerExtension() {
		if ( !defined( 'MD_NS_DRAFT' ) ) {
			define( 'MD_NS_DRAFT', 620 );
			define( 'MD_NS_DRAFT_TALK', 621 );
		}
	}

	public static function registerParserFunctions( &$parser ) {
		$parser->setFunctionHook( 'mintydocs_product', [ 'MintyDocsParserFunctions', 'renderProduct' ] );
		$parser->setFunctionHook( 'mintydocs_version', [ 'MintyDocsParserFunctions', 'renderVersion' ] );
		$parser->setFunctionHook( 'mintydocs_manual', [ 'MintyDocsParserFunctions', 'renderManual' ] );
		$parser->setFunctionHook( 'mintydocs_topic', [ 'MintyDocsParserFunctions', 'renderTopic' ] );
		$parser->setFunctionHook( 'mintydocs_link', [ 'MintyDocsParserFunctions', 'renderLink' ] );
		return true;
	}

	/**
	 * Register the "draft" namespaces for MintyDocs.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 *
	 * @param array &$list
	 *
	 * @return true
	 */
	public static function registerNamespaces( array &$list ) {
		global $wgNamespacesWithSubpages;

		$list[MD_NS_DRAFT] = 'Draft';
		$list[MD_NS_DRAFT_TALK] = 'Draft_talk';

		// Support subpages only for talk pages by default
		$wgNamespacesWithSubpages[MD_NS_DRAFT_TALK] = true;

		return true;
	}

	public static function checkPermissions( &$title, &$user, $action, &$result ) {
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return true;
		}
		// If we are setting the edit or view permission, return false to
		// avoid our permission value getting overriden by something else.
		if ( $action == 'edit' || $action == 'formedit' ) {
			$result = $mdPage->userCanEdit( $user );
			return false;
		} elseif ( $action == 'read' ) {
			$result = $mdPage->userCanView( $user );
			return false;
		}
		return true;
	}

	public static function addTextToPage( &$out, &$text ) {
		global $wgMintyDocsDisplayFooterElementsInSidebar;

		$action = Action::getActionName( $out->getContext() );
		if ( $action != 'view' ) {
			return true;
		}
		$title = $out->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return true;
		}
		$inheritedPage = $mdPage->getInheritedPage();
		if ( $inheritedPage !== null ) {
			$revision = Revision::newFromTitle( $inheritedPage->getTitle() );
			$inheritedPageText = $revision->getContent()->getNativeData();
			$text .= MediaWikiServices::getInstance()->getParser()->parse( $inheritedPageText, $title, new ParserOptions() )->getText();
		}
		$text = $mdPage->getHeader() . $text;

		if ( !$wgMintyDocsDisplayFooterElementsInSidebar ) {
			$text .= $mdPage->getFooter();
		}
		return true;
	}

	public static function showNoticeForDraftPage( &$out, &$text ) {
		$action = Action::getActionName( $out->getContext() );
		if ( $action != 'view' ) {
			return true;
		}
		$title = $out->getTitle();
		if ( $title->getNamespace() !== MD_NS_DRAFT ) {
			return true;
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
			$linkToPublished = Linker::linkKnown( $liveTitle, $html = null, $attribs = [], $query );
			$msg = "This is a draft page; the published version of this page can be found at $linkToPublished.";
		} else {
			$msg = 'This is a draft page; it has not yet been published.';
		}
		$warningText = Html::rawElement( 'div', [ 'class' => 'warningbox' ], $msg );
		$text = $warningText . $text;
		return true;
	}

	public static function addTextToSidebar( Skin $skin, &$sidebar ) {
		global $wgMintyDocsDisplayFooterElementsInSidebar;

		if ( !$wgMintyDocsDisplayFooterElementsInSidebar ) {
			return true;
		}

		$title = $skin->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return true;
		}

		$sidebarContents = $mdPage->getSidebarText();
		if ( $sidebarContents == null ) {
			return true;
		}

		list( $header, $contents ) = $sidebarContents;
		$sidebar[$header] = $contents;
	}

	/**
	 * Based on function of the same name in ApprovedRevs.hook.php, from
	 * the Approved Revs extension.
	 */
	public static function setSearchText( $article, $user, $content,
		$summary, $isMinor, $isWatch, $section, $flags, $revision,
		$status, $baseRevId, $undidRevId = 0 ) {
		if ( $revision === null ) {
			return true;
		}

		$title = $article->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );

		if ( $mdPage == null ) {
			return true;
		}

		if ( !$mdPage->inheritsPageContents() ) {
			return true;
		}

		// @TODO - does the template call need to be added/removed/etc.?
		//$newSearchText = $mdPage->getPageContents();

		//DeferredUpdates::addUpdate( new SearchUpdate( $title->getArticleID(), $title->getText(), $newSearchText ) );

		return true;
	}

	static function setGlobalJSVariables( &$vars ) {
		global $wgScriptPath;

		$vars['wgMintyDocsScriptPath'] = $wgScriptPath . '/extensions/MintyDocs';

		return true;
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

		return true;
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
		$handledIDs = [ 'MAG_MINTYDOCSPRODUCT', 'MAG_MINTYDOCSVERSION', 'MAG_MINTYDOCSMANUAL' ];
		if ( !in_array( $magicWordId, $handledIDs ) ) {
			return true;
		}
		$title = $parser->getTitle();
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return true;
		}
		$className = get_class( $mdPage );
		switch ( $magicWordId ) {
			case 'MAG_MINTYDOCSPRODUCT':
				if ( $className == 'MintyDocsProduct' ) {
					return true;
				}
				list( $product, $version ) = $mdPage->getProductAndVersion();
				$ret = $cache[$magicWordId] = $product->getDisplayName();
				break;
			case 'MAG_MINTYDOCSVERSION':
				if ( $className == 'MintyDocsProduct' || $className == 'MintyDocsVersion' ) {
					return true;
				}
				list( $productName, $versionString ) = $mdPage->getProductAndVersionStrings();
				$ret = $cache[$magicWordId] = $versionString;
				break;
			case 'MAG_MINTYDOCSMANUAL':
				if ( $className == 'MintyDocsProduct' || $className == 'MintyDocsVersion' || $className == 'MintyDocsManual' ) {
					return true;
				}
				$manual = $mdPage->getManual();
				$ret = $cache[$magicWordId] = $manual->getDisplayName();
				break;
			default:
				break;
		}
		return true;
	}

	public static function registerPageFormsInputs( &$formPrinter ) {
		$formPrinter->registerInputType( 'MintyDocsTOCInput' );
	}

}

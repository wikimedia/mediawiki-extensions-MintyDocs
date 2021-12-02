<?php

use MediaWiki\MediaWikiServices;

class MintyDocsHooks {

	public static function registerExtension() {
		global $wgNamespaceRobotPolicies, $wgHooks;

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

		if ( class_exists( 'MediaWiki\HookContainer\HookContainer' ) ) {
			// MW 1.35+
			$wgHooks['PageSaveComplete'][] = 'MintyDocsHooks::setSearchText';
		} else {
			$wgHooks['PageContentSaveComplete'][] = 'MintyDocsHooks::setSearchTextOld';
		}
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

	/**
	 * @param Title &$title
	 * @param User &$user
	 * @param string $action
	 * @param string &$result
	 * @return string|null
	 */
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

	/**
	 * @param OutputPage &$out
	 * @param string &$text
	 * @return bool
	 */
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
			$wikiPage = WikiPage::factory( $inheritedPage->getTitle() );
			$rawAccess = MediaWiki\Revision\RevisionRecord::RAW;
			$inheritedPageText = $wikiPage->getContent( $rawAccess )->getNativeData();
			$text .= MediaWikiServices::getInstance()->getParser()->parse( $inheritedPageText, $title, ParserOptions::newFromAnon() )->getText();
		}
		$text = $mdPage->getHeader() . $text;

		if ( !$wgMintyDocsDisplayFooterElementsInSidebar ) {
			$text .= $mdPage->getFooter();
		}
		return true;
	}

	/**
	 * @param OutputPage &$out
	 * @param string &$text
	 * @return bool
	 */
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
			return true;
		}
		$draftTitle = Title::newFromText( $title->getText(), MD_NS_DRAFT );
		$draftLink = Linker::linkKnown( $draftTitle, 'draft page' );
		$msg = "Warning: this page has a corresponding $draftLink. It is generally better to edit the draft page, and then publish it, rather than to edit this page directly.";
		$editPage->editFormPageTop .= Html::rawElement( 'div', [ 'class' => 'warningbox' ], $msg );

		return true;
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
			return true;
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
	 * Called by the PageContentSaveComplete hook; used for MW < 1.35.
	 * Based on function of the same name in ApprovedRevs.hook.php, from
	 * the Approved Revs extension.
	 *
	 * @param WikiPage $article
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $isMinor
	 * @param bool $isWatch
	 * @param string $section
	 * @param int $flags
	 * @param Revision $revision
	 * @param Status $status
	 * @param int|false $baseRevId
	 * @param int $undidRevId
	 * @return true
	 */
	public static function setSearchTextOld( $article, $user, $content,
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

	/**
	 * Called by the PageSaveComplete hook; used for MW >= 1.35.
	 *
	 * @return true
	 */
	public static function setSearchText( WikiPage $wikiPage, User $user,
		string $summary, int $flags,
		MediaWiki\Revision\RevisionStoreRecord $revisionRecord,
		MediaWiki\Storage\EditResult $editResult ) {
		if ( $revisionRecord === null ) {
			return true;
		}

		$title = $wikiPage->getTitle();
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

	/**
	 * @param array &$vars
	 * @return bool
	 */
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
		$customVariableIDs[] = 'MAG_MINTYDOCSDISPLAYNAME';

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
		$handledIDs = [
			'MAG_MINTYDOCSPRODUCT',
			'MAG_MINTYDOCSVERSION',
			'MAG_MINTYDOCSMANUAL',
			'MAG_MINTYDOCSDISPLAYNAME'
		];
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
			case 'MAG_MINTYDOCSDISPLAYNAME':
				$ret = $cache[$magicWordId] = $mdPage->getDisplayName();
				break;
			default:
				break;
		}
		return true;
	}

	public static function makeDraftsNonSearchable( &$searchableNamespaces ) {
		$user = RequestContext::getMain()->getUser();

		// Allow for searching of the Draft and Draft_talk
		// namespaces only by MD administrators and editors.
		if (
			$user->isAllowed( 'mintydocs-administer' ) ||
			$user->isAllowed( 'mintydocs-edit' )
		) {
			return true;
		}

		unset( $searchableNamespaces[MD_NS_DRAFT] );
		unset( $searchableNamespaces[MD_NS_DRAFT_TALK] );

		return true;
	}

	/**
	 * @param PFFormPrinter &$formPrinter
	 */
	public static function registerPageFormsInputs( &$formPrinter ) {
		$formPrinter->registerInputType( 'MintyDocsTOCInput' );
	}
}

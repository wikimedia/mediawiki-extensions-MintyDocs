<?php

class MintyDocsHooks {

	public static function registerParserFunctions( &$parser ) {
		$parser->setFunctionHook( 'mintydocs_product', array( 'MintyDocsParserFunctions', 'renderProduct' ) );
		$parser->setFunctionHook( 'mintydocs_version', array( 'MintyDocsParserFunctions', 'renderVersion' ) );
		$parser->setFunctionHook( 'mintydocs_manual', array( 'MintyDocsParserFunctions', 'renderManual' ) );
		$parser->setFunctionHook( 'mintydocs_topic', array( 'MintyDocsParserFunctions', 'renderTopic' ) );
		$parser->setFunctionHook( 'mintydocs_link', array( 'MintyDocsParserFunctions', 'renderLink' ) );
		return true;
	}

	static public function checkPermissions( &$title, &$user, $action, &$result ) {
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( $mdPage == null ) {
			return true;
		}
		if ( $action == 'edit' || $action == 'formedit' ) {
			if ( !$mdPage->userCanEdit( $user ) ) {
				// For some reason this also needs to return
				// false... $result will get overridden
				// otherwise?
				$result = false;
				return false;
			}
		} elseif ( $action == 'read' ) {
			if ( !$mdPage->userCanView( $user ) ) {
				// For some reason this also needs to return
				// false... $result will get overridden
				// otherwise?
				$result = false;
				return false;
			}
		}
		return true;
	}

	static public function addTextToPage( &$out, &$text ) {
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
			global $wgParser;
			$text .= $wgParser->parse( $inheritedPageText, $title, new ParserOptions() )->getText();
		}
		$text = $mdPage->getHeader() . $text;

		if ( ! $wgMintyDocsDisplayFooterElementsInSidebar ) {
			$text .= $mdPage->getFooter();
		}
		return true;
	}

	static public function addTextToSidebar( Skin $skin, &$sidebar ) {
		global $wgMintyDocsDisplayFooterElementsInSidebar;

		if ( ! $wgMintyDocsDisplayFooterElementsInSidebar ) {
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
	static public function setSearchText( $article, $user, $content,
		$summary, $isMinor, $isWatch, $section, $flags, $revision,
		$status, $baseRevId, $undidRevId = 0 ) {

		if ( is_null( $revision ) ) {
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
	 * Register wiki markup words associated with MAG_NIFTYVAR as a variable
	 *
	 * @param array $customVariableIDs
	 * @return boolean
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
	 * @param array $cache
	 * @param string $magicWordId
	 * @param string $ret
	 * @return boolean
	 */
	public static function assignAValue( &$parser, &$cache, &$magicWordId, &$ret ) {
		$handledIDs = array( 'MAG_MINTYDOCSPRODUCT', 'MAG_MINTYDOCSVERSION', 'MAG_MINTYDOCSMANUAL' );
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
				$ret = $product->getDisplayName();
				break;
			case 'MAG_MINTYDOCSVERSION':
				if ( $className == 'MintyDocsProduct' || $className == 'MintyDocsVersion' ) {
					return true;
				}
				list( $productName, $versionString ) = $mdPage->getProductAndVersionStrings();
				$ret = $versionString;
				break;
			case 'MAG_MINTYDOCSMANUAL':
				if ( $className == 'MintyDocsProduct' || $className == 'MintyDocsVersion' || $className == 'MintyDocsManual' ) {
					return true;
				}
				$manual = $mdPage->getManual();
				$ret = $manual->getDisplayName();
				break;
			default:
				break;
		}
		return true;
	}

}
<?php

use MediaWiki\MediaWikiServices;

/**
 * Parser functions for MintyDocs.
 *
 * @file
 * @ingroup MintyDocs
 *
 * The following parser functions are defined: #mintydocs_product,
 * #mintydocs_version, #mintydocs_manual, #mintydocs_topic and #mintydocs_link.
 *
 * '#mintydocs_product' is called as:
 * {{#mintydocs_product:display name=|admins=|editors=|previewers=}}
 *
 * This function defines a product page.
 *
 * '#mintydocs_version' is called as:
 * {{#mintydocs_version:status=|manuals list=}}
 *
 * This function defines a version page.
 *
 * '#mintydocs_manual' is called as:
 * {{#mintydocs_manual:display name=|topics list=|topics list page=
 * |pagination|inherit|topic default form=|topic alternate forms=}}
 *
 * "topics list=" holds a bulleted hierarchy of topic names. Names that
 * begin with a "!" are considered "standalone topics" - these are topic
 * pages that are not defined as being part of this manual, and their full
 * page name must be specified. Names that begin with a "-" are displayed
 * as simple strings, not links.
 *
 * "topics list page=" holds the name of a page that in turns holds a
 * bulleted hierarchy. Only one of this parameter and "topics list=" should
 * be specified.
 *
 * This function defines a manual page.
 *
 * '#mintydocs_topic' is called as:
 * {{#mintydocs_topic:display name=|toc name=|inherit}}
 *
 * This function defines a topic page.
 *
 * '#mintydocs_link' is called as:
 * {{#mintydocs_link:product=|version=|manual=|topic=|standalone|link text=}}
 *
 * This function displays a link to another page in the MintyDocs system.
 */

class MintyDocsParserFunctions {

	/**
	 * #mintydocs_product
	 */
	static function renderProduct( &$parser ) {
		list( $parentPageName, $thisPageName ) = MintyDocsUtils::getPageParts( $parser->getTitle() );
		$returnMsg = MintyDocsProduct::checkPageEligibility( $parentPageName, $thisPageName );
		if ( $returnMsg != null ) {
			return $returnMsg;
		}

		$displayTitle = null;

		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$processedParams = self::processParams( $parser, $params );

		$parserOutput = $parser->getOutput();
		$parserOutput->addModules( 'ext.mintydocs.main' );

		$parserOutput->setProperty( 'MintyDocsPageType', 'Product' );

		foreach ( $processedParams as $paramName => $value ) {
			if ( $paramName == 'display name' ) {
				$displayTitle = $value;
			} elseif ( $paramName == 'admins' ) {
				$value = str_replace( '_', ' ', $value );
				$parserOutput->setProperty( 'MintyDocsProductAdmins', $value );
			} elseif ( $paramName == 'editors' ) {
				$value = str_replace( '_', ' ', $value );
				$parserOutput->setProperty( 'MintyDocsProductEditors', $value );
			} elseif ( $paramName == 'previewers' ) {
				$value = str_replace( '_', ' ', $value );
				$parserOutput->setProperty( 'MintyDocsProductPreviewers', $value );
			}
		}

		if ( $displayTitle == null ) {
			$displayTitle = $thisPageName;
		}
		$parserOutput->setDisplayTitle( $displayTitle );
	}

	static function renderVersion( &$parser ) {
		list( $parentPageName, $thisPageName ) = MintyDocsUtils::getPageParts( $parser->getTitle() );
		$returnMsg = MintyDocsVersion::checkPageEligibility( $parentPageName, $thisPageName );
		if ( $returnMsg != null ) {
			return $returnMsg;
		}

		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$processedParams = self::processParams( $parser, $params );

		$parserOutput = $parser->getOutput();
		$parserOutput->addModules( 'ext.mintydocs.main' );

		$parserOutput->setProperty( 'MintyDocsPageType', 'Version' );
		$parserOutput->setProperty( 'MintyDocsParentPage', $parentPageName );

		foreach( $processedParams as $paramName => $value ) {
			if ( $paramName == 'inherit' && $value == null ) {
				$parserOutput->setProperty( 'MintyDocsInherit', true );
			} elseif ( $paramName == 'status' ) {
				// @TODO - put in check here for values.
				$parserOutput->setProperty( 'MintyDocsStatus', $value );
			} elseif ( $paramName == 'manuals list' ) {
				$parserOutput->setProperty( 'MintyDocsManualsList', $value );
			}
		}
	}

	static function renderManual( &$parser ) {
		list( $parentPageName, $thisPageName ) = MintyDocsUtils::getPageParts( $parser->getTitle() );
		$returnMsg = MintyDocsManual::checkPageEligibility( $parentPageName, $thisPageName );
		if ( $returnMsg != null ) {
			return $returnMsg;
		}

		$displayTitle = null;
		$inherits = false;

		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$processedParams = self::processParams( $parser, $params );

		$parserOutput = $parser->getOutput();
		$parserOutput->addModules( 'ext.mintydocs.main' );

		$parserOutput->setProperty( 'MintyDocsPageType', 'Manual' );
		$parserOutput->setProperty( 'MintyDocsParentPage', $parentPageName );

		foreach( $processedParams as $paramName => $value ) {
			if ( $paramName == 'display name' ) {
				$parserOutput->setProperty( 'MintyDocsDisplayName', $value );
				$displayTitle = $value;
			} elseif ( $paramName == 'inherit' && $value == null ) {
				$parserOutput->setProperty( 'MintyDocsInherit', true );
				$inherits = true;
			} elseif ( $paramName == 'topics list' ) {
				$parserOutput->setProperty( 'MintyDocsTopicsList', $value );
			} elseif ( $paramName == 'topics list page' ) {
				// We use the same property name as 'topics list',
				// 'MintyDocsTopicsList', instead of something like
				// 'MintyDocsTopicsListPage', so that there won't
				// be any ambiguity about which one to use if
				// it's an inherited property.
				// We differentiate between the two based on
				// whether the value starts with a '*' or not.
				// @TODO - add validation before storage.
				$parserOutput->setProperty( 'MintyDocsTopicsList', $value );
			} elseif ( $paramName == 'pagination' && $value == null ) {
				$parserOutput->setProperty( 'MintyDocsPagination', true );
			} elseif ( $paramName == 'topic default form' ) {
				$parserOutput->setProperty( 'MintyDocsTopicDefaultForm', $value );
			} elseif ( $paramName == 'topic alternate forms' ) {
				$parserOutput->setProperty( 'MintyDocsTopicAlternateForms', $value );
			}
		}

		if ( $displayTitle == null && $inherits ) {
			$manual = new MintyDocsManual( $parser->getTitle() );
			$inheritedDisplayName = $manual->getInheritedParam( 'MintyDocsDisplayName' );
			if ( $inheritedDisplayName != null ) {
				$displayTitle = $inheritedDisplayName;
			}
		}
		if ( $displayTitle == null ) {
			$displayTitle = $thisPageName;
		}

		$parserOutput->setDisplayTitle( $displayTitle );
	}

	static function renderTopic( &$parser ) {
		//if ($parser->getTitle() == null ) return;
		list( $parentPageName, $thisPageName ) = MintyDocsUtils::getPageParts( $parser->getTitle() );
		$returnMsg = MintyDocsTopic::checkPageEligibility( $parentPageName, $thisPageName );
		if ( $returnMsg != null ) {
			// It's an "invalid" topic.
			$parentPageName = null;
			$thisPageName = $parser->getTitle()->getFullText();
		}

		$displayTitle = null;
		$tocDisplayTitle = null;
		$inherits = false;

		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$processedParams = self::processParams( $parser, $params );

		$parserOutput = $parser->getOutput();
		$parserOutput->addModules( 'ext.mintydocs.main' );

		$parserOutput->setProperty( 'MintyDocsPageType', 'Topic' );
		$parserOutput->setProperty( 'MintyDocsParentPage', $parentPageName );

		foreach( $processedParams as $paramName => $value ) {
			if ( $paramName == 'display name' ) {
				$parserOutput->setProperty( 'MintyDocsDisplayName', $value );
				$displayTitle = $value;
			} elseif ( $paramName == 'toc name' ) {
				$tocDisplayTitle = $value;
			} elseif ( $paramName == 'inherit' && $value == null ) {
				$parserOutput->setProperty( 'MintyDocsInherit', true );
				$inherits = true;
			}
		}

		if ( $displayTitle == null && $inherits ) {
			$topic = new MintyDocsTopic( $parser->getTitle() );
			$inheritedDisplayName = $topic->getInheritedParam( 'MintyDocsDisplayName' );
			if ( $inheritedDisplayName != null ) {
				$displayTitle = $inheritedDisplayName;
			}
		}
		if ( $displayTitle == null ) {
			$displayTitle = $thisPageName;
		}
		$parserOutput->setDisplayTitle( $displayTitle );

		if ( $tocDisplayTitle == null && $inherits ) {
			$topic = new MintyDocsTopic( $parser->getTitle() );
			$inheritedTOCName = $topic->getInheritedParam( 'MintyDocsTOCName' );
			if ( $inheritedTOCName != null ) {
				$tocDisplayTitle = $inheritedTOCName;
			}
		}

		if ( $tocDisplayTitle == null ) {
			$tocDisplayTitle = $displayTitle;
		}
		$parserOutput->setProperty( 'MintyDocsTOCName', $tocDisplayTitle );
	}

	static function renderLink( &$parser ) {
		global $wgRequest;

		// We need to see the query string for a few things, and
		// unfortunately we need to disable the cache in order
		// to see it.
		$parser->disableCache();
		$curTitle = $parser->getTitle();
		if ( $curTitle->isSpecial( 'FormEdit' ) ) {
			// If we're in Special:FormEit, then this is probably
			// being called by a WYSIWYG editor like VisualEditor.
			// If so, get out the actual page name from the URL,
			// and pretend we're on that page, so this will
			// display correctly.
			$pageNameParts = explode( '/', $curTitle->getText(), 3 );
			$curTitle = Title::newFromText( $pageNameParts[2] );
		}
		$mdPage = MintyDocsUtils::pageFactory( $curTitle );
		if ( $mdPage == null ) {
			return "<div class=\"error\">#mintydocs_link must be called from a MintyDocs-enabled page.</div>";
		}

		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$processedParams = self::processParams( $parser, $params );

		$product = $version = $manual = $topic = $linkText = null;
		$contextProduct = $contextVersion = $contextManual = null;
		$standalone = false;
		foreach( $processedParams as $paramName => $value ) {
			if ( $paramName == 'product' ) {
				$product = $value;
			} elseif ( $paramName == 'version' ) {
				$version = $value;
			} elseif ( $paramName == 'manual' ) {
				$manual = $value;
			} elseif ( $paramName == 'topic' ) {
				$topic = $value;
			} elseif ( $paramName == 'standalone' ) {
				$standalone = true;
			} elseif ( $paramName == 'link text' ) {
				$linkText = $value;
			} elseif ( $paramName == 'context product' ) {
				$contextProduct = $value;
			} elseif ( $paramName == 'context version' ) {
				$contextVersion = $value;
			} elseif ( $paramName == 'context manual' ) {
				$contextManual = $value;
			}
		}

		// Handle links to standalone topics right away.
		if ( $topic != null && $standalone ) {
			$linkedPageName = self::possibleNamespacePrefix( $curTitle ) . $topic;
			$query = array();
			if ( $contextProduct != null ) {
				$query['contextProduct'] = $contextProduct;
			} elseif ( $wgRequest->getCheck( 'contextProduct' ) ) {
				$query['contextProduct'] = $wgRequest->getVal( 'contextProduct' );
			}
			if ( $contextVersion != null ) {
				$query['contextVersion'] = $contextVersion;
			} elseif ( $wgRequest->getCheck( 'contextVersion' ) ) {
				$query['contextVersion'] = $wgRequest->getVal( 'contextVersion ');
			}
			if ( $contextManual != null ) {
				$query['contextManual'] = $contextManual;
			} elseif ( $wgRequest->getCheck( 'contextManual' ) ) {
				$query['contextManual'] = $wgRequest->getVal( 'contextManual ');
			}
			return self::getLinkHTML( $linkedPageName, $linkText, $query );
		}

		if ( $topic != null ) {
			$linkedPageType = 'topic';
		} elseif ( $manual != null ) {
			$linkedPageType = 'manual';
		} elseif ( $version != null ) {
			$linkedPageType = 'version';
		} elseif ( $product != null ) {
			$linkedPageType = 'product';
		} else {
			return "<div class=\"error\">At least one of product, version, manual and topic must be specified.</div>";
		}

		if ( $linkedPageType == 'product' || $linkedPageType == 'version' ) {
			if ( $topic != null && $manual == null ) {
				return "<div class=\"error\">A 'manual' value must be specified in this case.</div>";
			}
		}

		if ( $linkedPageType == 'product' ) {
			if ( $manual != null && $version == null ) {
				return "<div class=\"error\">A 'version' value must be specified in this case.</div>";
			}
		}

		if ( get_class( $mdPage ) == 'MintyDocsTopic' ) {
			$curProduct = $wgRequest->getVal( 'product' );
			$curVersion = $wgRequest->getVal( 'version' );
			$curManual = $wgRequest->getVal( 'manual' );
		} else {
			$curProduct = $curVersion = $curManual = null;
		}

		// Get this page's own product, and possibly version and manual.
		if ( get_class( $mdPage ) == 'MintyDocsProduct' ) {
			$curProduct = $mdPage->getActualName();
		} elseif ( get_class( $mdPage ) == 'MintyDocsVersion' ) {
			list( $curProduct, $curVersion ) = $mdPage->getProductAndVersionStrings();
		} elseif ( $curProduct != null && $curVersion != null && $curManual != null ) {
			// No need to do anything; the values have already been
			// set.
		} elseif ( get_class( $mdPage ) == 'MintyDocsManual' ) {
			list( $curProduct, $curVersion ) = $mdPage->getProductAndVersionStrings();
			$curManual = $mdPage->getActualName();
		} else { // MintyDocsTopic
			list( $curProduct, $curVersion ) = $mdPage->getProductAndVersionStrings();
			$curManual = $mdPage->getManual()->getActualName();
		}

		if ( $product != null ) {
			$linkedProduct = self::possibleNamespacePrefix( $curTitle ) . $product;
		} else {
			$linkedProduct = $curProduct;
		}
		$linkedPageName = $linkedProduct;
		if ( $linkedPageType == 'version' || $linkedPageType == 'manual' || $linkedPageType == 'topic' ) {
			$linkedVersion = ( $version != null ) ? $version : $curVersion;
			$linkedPageName .= '/' . $linkedVersion;
		} else {
			$linkedVersion = null;
		}
		if ( $linkedPageType == 'manual' || $linkedPageType == 'topic' ) {
			$linkedManual = ( $manual != null ) ? $manual : $curManual;
			$linkedPageName .= '/' . $linkedManual;
		} else {
			$linkedManual = null;
		}
		if ( $linkedPageType == 'topic' ) {
			$linkedPageName .= '/' . $topic;
		}

		$query = array();
		if ( $contextProduct != null ) {
			// Do nothing.
		} elseif ( $wgRequest->getCheck( 'contextProduct' ) ) {
			$contextProduct = $wgRequest->getVal( 'contextProduct' );
		} else {
			$contextProduct = $curProduct;
		}
		if ( $linkedProduct != null && $linkedProduct != $contextProduct ) {
			$query['contextProduct'] = $contextProduct;
		}
		if ( $contextVersion != null ) {
			// Do nothing.
		} elseif ( $wgRequest->getCheck( 'contextVersion' ) ) {
			$contextVersion = $wgRequest->getVal( 'contextVersion' );
		} else {
			$contextVersion = $curVersion;
		}
		if ( $linkedVersion != null && $linkedVersion != $contextVersion ) {
			$query['contextVersion'] = $contextVersion;
		}
		if ( $contextManual != null ) {
			// Do nothing.
		} elseif ( $wgRequest->getCheck( 'contextManual' ) ) {
			$contextManual = $wgRequest->getVal( 'contextManual' );
		} else {
			$contextManual = $curManual;
		}
		if ( $linkedManual != null && $linkedManual != $contextManual ) {
			$query['contextManual'] = $contextManual;
		}

		return self::getLinkHTML( $linkedPageName, $linkText, $query );
	}

	static function processParams( $parser, $params ) {
		$processedParams = array();

		// Assign params.
		foreach ( $params as $i => $param ) {
			$elements = explode( '=', $param, 2 );

			// Set param name and value.
			if ( count( $elements ) > 1 ) {
				$paramName = trim( $elements[0] );
				// Parse (and sanitize) parameter values.
				// We call recursivePreprocess() and not
				// recursiveTagParse() so that URL values will
				// not be turned into links.
				//$value = trim( $parser->recursivePreprocess( $elements[1] ) );
				$value = $elements[1];
			} else {
				$paramName = trim( $param );
				$value = null;
			}
			$processedParams[$paramName] = $value;
		}

		return $processedParams;
	}

	public static function possibleNamespacePrefix( $title ) {
		// If we're in the Draft namespace, add on the Draft:
		// prefix to whatever page name was specified.
		if ( $title->getNamespace() != MD_NS_DRAFT ) {
			return '';
		}
		global $wgContLang;
		return $wgContLang->getNsText( MD_NS_DRAFT ) . ':';
	}

	static function getLinkHTML( $pageName, $linkText, $query ) {
		$title = Title::newFromText( $pageName );
		if ( $linkText == null ) {
			$mdPage = MintyDocsUtils::pageFactory( $title );
			if ( $mdPage != null ) {
				$linkText = $mdPage->getDisplayName();
			}
		}
		$str = Linker::link( $title, $linkText, $customAttribs = array(), $query );
		return array( $str, 'noparse' => true, 'isHTML' => true );
	}

}

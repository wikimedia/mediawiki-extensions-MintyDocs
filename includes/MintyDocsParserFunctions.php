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
 * {{#mintydocs_manual:display name=|topics list=|topics list page=|
 * pagination|inherit}}
 *
 * "topics list=" holds a bulleted hierarchy of topic names. Names that
 * begin with a "!" are considered "standalone topics" - these are topic
 * pages that are not defined as being part of this manual, and their full
 * page name must be specified.
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
 * {{#mintydocs_link:product=|version=|manual=|topic=}}
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
				$usernames = explode( ',', $value );
				foreach ( $usernames as $username ) {
					$username = trim( str_replace( '_', ' ', $username ) );
					$parserOutput->setProperty( 'MintyDocsProductAdmin', $username );
				}
			} elseif ( $paramName == 'editors' ) {
				$usernames = explode( ',', $value );
				foreach ( $usernames as $username ) {
					$username = trim( str_replace( '_', ' ', $username ) );
					$parserOutput->setProperty( 'MintyDocsProductEditor', $username );
				}
			} elseif ( $paramName == 'previewers' ) {
				$usernames = explode( ',', $value );
				foreach ( $usernames as $username ) {
					$username = trim( str_replace( '_', ' ', $username ) );
					$parserOutput->setProperty( 'MintyDocsProductPreviewer', $username );
				}
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
		$mdPage = MintyDocsUtils::pageFactory( $parser->getTitle() );
		if ( $mdPage == null ) {
			return true;
		}

		$params = func_get_args();
		array_shift( $params ); // We don't need the parser.
		$processedParams = self::processParams( $parser, $params );

		$product = $version = $manual = $topic = null;
		foreach( $processedParams as $paramName => $value ) {
			if ( $paramName == 'product' ) {
				$product = $value;
			} elseif ( $paramName == 'version' ) {
				$version = $value;
			} elseif ( $paramName == 'manual' ) {
				$manual = $value;
			} elseif ( $paramName == 'topic' ) {
				$topic = $value;
			}
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
		// If it's a topic, there's a chance that it's "standalone",
		// meaning that it gets its data from the query string.
		// Unfortunately, we need to disable the cache in order to see
		// the query string, so we have to do that regardless of
		// whether it's standalone or not.
		if ( get_class( $mdPage ) == 'MintyDocsTopic' ) {
			$parser->disableCache();
			global $wgRequest;
			$curProduct = $wgRequest->getVal( 'product' );
			$curVersion = $wgRequest->getVal( 'version' );
			$curManual = $wgRequest->getVal( 'manual' );
		}

		// Get this page's own product, and possibly version and manual.
		if ( get_class( $mdPage ) == 'MintyDocsProduct' ) {
			$curProduct = $mdPage->getActualName();
		} elseif ( $curProduct != null && $curVersion != null && $curManual != null ) {
			// NO need to do anything; the values have laready been
			// set.
		} else {
			list( $curProduct, $curVersion ) = $mdPage->getProductAndVersionStrings();
			$curManual = $mdPage->getManual()->getActualName();
		}

		if ( $product != null ) {
			$linkedPageName = $product;
		} else {
			$linkedPageName = $curProduct;
		}
		if ( $linkedPageType == 'version' || $linkedPageType == 'manual' || $linkedPageType == 'topic' ) {
			if ( $version != null ) {
				$linkedPageName .= '/' . $version;
			} else {
				$linkedPageName .= '/' . $curVersion;
			}
		}
		if ( $linkedPageType == 'manual' || $linkedPageType == 'topic' ) {
			if ( $manual != null ) {
				$linkedPageName .= '/' . $manual;
			} else {
				$linkedPageName .= '/' . $curManual;
			}
		}
		if ( $linkedPageType == 'topic' ) {
			$linkedPageName .= '/' . $topic;
		}

		$linkedTitle = Title::newFromText( $linkedPageName );

		if ( method_exists( 'MediaWikiServices', 'getLinkRenderer' ) ) {
			// MW 1.28+
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$linkStr = $linkRenderer->makeLink( $linkedTitle, $displayName );
		} else {
			$linkStr = Linker::link( $linkedTitle, $displayName );
		}

		return array( $linkStr, 'noparse' => true, 'isHTML' => true );
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
}

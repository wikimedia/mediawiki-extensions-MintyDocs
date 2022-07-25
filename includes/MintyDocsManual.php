<?php

use MediaWiki\MediaWikiServices;

class MintyDocsManual extends MintyDocsPage {

	private $mTOCHTML = null;
	private $mTOCArray = [];

	static function getPageTypeValue() {
		return 'Manual';
	}

	public function hasPagination() {
		// @TODO - change null to false
		return $this->getPossiblyInheritedParam( 'MintyDocsPagination' );
	}

	function getHeader() {
		global $wgMintyDocsShowBreadcrumbs;

		$text = '';

		list( $product, $version ) = $this->getProductAndVersion();
		if ( $wgMintyDocsShowBreadcrumbs ) {
			$manualDescText = wfMessage( 'mintydocs-manual-desc', $version->getLink(), $product->getLink() )->text();
			$text .= Html::rawElement( 'div', [ 'class' => 'MintyDocsManualDesc' ], $manualDescText );
		}

		$equivsInOtherVersions = $this->getEquivalentsInOtherVersions( $product, $version->getActualName() );
		if ( count( $equivsInOtherVersions ) > 0 ) {
			$otherVersionsText = wfMessage( 'mintydocs-manual-otherversions' )->text() . "\n";
			$otherVersionsText .= "<ul>\n";
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

			foreach ( $equivsInOtherVersions as $versionName => $manualPage ) {
				$otherVersionsText .= "<li>" . $linkRenderer->makeLink( $manualPage, $versionName ) . "</li>\n";
			}
			$otherVersionsText .= "</ul>\n";
			$text .= Html::rawElement( 'div', [ 'class' => 'MintyDocsOtherManualVersions' ], $otherVersionsText );
		}

		// We have to call this before it's actually needed (for the
		// sidebar), so that the error messages, if any, will be
		// generated early enough to be displayed.
		$this->generateTableOfContents( true );

		return $text;
	}

	function getSidebarText() {
		if ( $this->mIsInvalid ) {
			return null;
		}

		$toc = $this->getTableOfContents( true );
		return [ $this->getDisplayName(), $toc ];
	}

	function getAllTopics() {
		$topicPages = $this->getChildrenPages();
		$topics = [];
		foreach ( $topicPages as $topicPage ) {
			$topics[] = new MintyDocsTopic( $topicPage );
		}

		return $topics;
	}

	// phpcs:ignore MediaWiki.Commenting.FunctionComment.MissingDocumentationPrivate
	private function generateTableOfContents( $showErrors ) {
		$services = MediaWikiServices::getInstance();
		$parser = $services->getParser();
		$linkRenderer = $services->getLinkRenderer();

		$tocOrPageName = trim( $this->getPossiblyInheritedParam( 'MintyDocsTopicsList' ) );
		// Decide whether this is a table of contents or a page name
		// based on whether or not the string starts with a '*' -
		// hopefully that's a good enough check.
		if ( $tocOrPageName == null ) {
			$this->mTOCHTML = null;
			return;
		} elseif ( substr( $tocOrPageName, 0, 1 ) == '*' ) {
			$rawTOC = $tocOrPageName;
		} else {
			$title = Title::newFromText( $tocOrPageName );
			if ( $title == null ) {
				$this->mTOCHTML = null;
				return;
			}
			if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
				// MW 1.36+
				$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );
			} else {
				$wikiPage = new WikiPage( $title );
			}
			$content = $wikiPage->getContent();
			if ( $content == null ) {
				$this->mTOCHTML = null;
				return;
			}
			$pageText = $content->getText();
			// "Initialize" the parser, to avoid occasional errors
			// when the parser's $mOptions field is not set.
			$parser->startExternalParse(
				$title,
				ParserOptions::newFromAnon(),
				Parser::OT_HTML
			);
			$rawTOC = $parser->recursiveTagParse( $pageText );
		}

		// Get rid of any HTML tags that may have gotten into
		// the topics list.
		$rawTOC = strip_tags( $rawTOC );

		// If the topics list comes from a page, there's a
		// chance that it's from a dynamic query, which means
		// that there might be extra newlines, etc. Get rid
		// of these, to make the output cleaner.
		$rawTOCLines = explode( "\n", $rawTOC );
		$tocLines = [];
		foreach ( $rawTOCLines as $line ) {
			$line = trim( $line );
			if ( str_replace( '*', '', $line ) != '' ) {
				$tocLines[] = $line;
			}
		}

		$useFormForRedLinkedTopics = false;
		if ( class_exists( 'PFFormEdit' ) ) {
			$topicDefaultForm = trim( $this->getPossiblyInheritedParam( 'MintyDocsTopicDefaultForm' ) );
			$topicAlternateFormsStr = trim( $this->getPossiblyInheritedParam( 'MintyDocsTopicAlternateForms' ) );
			if ( $topicDefaultForm != null || $topicAlternateFormsStr != null ) {
				$useFormForRedLinkedTopics = true;
			}
		}
		if ( $useFormForRedLinkedTopics ) {
			$formEditQuery = [];
			if ( $topicDefaultForm != null ) {
				$formEditQuery['form'] = $topicDefaultForm;
			}
			if ( $topicAlternateFormsStr != '' ) {
				$topicAlternateForms = array_map( 'trim', explode( ',', $topicAlternateFormsStr ) );
				$formEditQuery['alt_form'] = [];
				foreach ( $topicAlternateForms as $i => $altForm ) {
					$formEditQuery['alt_form'][] = $altForm;
				}
			}
			$formSpecialPage = $services
				->getSpecialPageFactory()
				->getPage( 'FormEdit' );

			$formSpecialPageTitle = $formSpecialPage->getPageTitle();
		}

		$manualHasDraftPage = $this->hasDraftPage();

		$topics = $this->getAllTopics();
		$this->mTOCArray = [];
		$unlinkedLineTracker = [];
		foreach ( $tocLines as $lineNum => &$line ) {
			$line = str_replace( '_', ' ', $line );
			$matches = [];
			preg_match( "/(\*+)\s*(.*)\s*$/", $line, $matches );
			$numAsterisks = strlen( $matches[1] );
			$lineValue = $matches[2];
			// Handle unlinked lines.
			if ( strpos( $lineValue, '-' ) === 0 ) {
				$displayText = trim( substr( $lineValue, 1 ) );
				$line = str_replace( $lineValue, $displayText, $line );
				$this->mTOCArray[] = [ $displayText, $numAsterisks ];
				$unlinkedLineTracker[$lineNum] = [ $numAsterisks, true ];
				continue;
			}
			$unlinkedLineTracker[] = [ $numAsterisks, false ];
			$isStandalone = ( strpos( $lineValue, '!' ) === 0 );
			$isBorrowed = ( strpos( $lineValue, '+' ) === 0 );
			if ( $isStandalone || $isBorrowed ) {
				$title = Title::newFromText( trim( substr( $lineValue, 1 ) ), $this->getTitle()->getNamespace() );
				if ( $isStandalone ) {
					$topic = MintyDocsTopic::newStandalone( $title, $this );
				} else { // $isBorrowed
					$topic = MintyDocsTopic::newBorrowed( $title, $this );
				}
				if ( $topic != null ) {
					$this->mTOCArray[] = [ $topic, $numAsterisks ];
				} else {
					$this->mTOCArray[] = [ $title, $numAsterisks ];
				}
				continue;
			}

			$foundMatchingTopic = false;
			foreach ( $topics as $i => $topic ) {
				$topicActualName = $topic->getActualName();
				if ( $lineValue == $topicActualName ) {
					$foundMatchingTopic = true;
					$line = str_replace( $lineValue, $topic->getTOCLink(), $line );
					$this->mTOCArray[] = [ $topic, $numAsterisks ];
					// Unset this so that $topics will hold the list of unmatched topics.
					unset( $topics[$i] );
					break;
				}
			}
			if ( !$foundMatchingTopic ) {
				// Make a link to this page, which is either
				// nonexistent or at least lacks a #minty_docs_topic
				// call.
				$topicPageName = $this->getTitle()->getPrefixedText() . '/' . trim( $lineValue );
				$title = Title::newFromText( $topicPageName );
				if ( $manualHasDraftPage ) {
					// If it's a non-draft page, and it has
					// a draft page, then just don't
					// display red-linked topics at all -
					// they presumably exist as drafts but
					// haven't been published yet.
					unset( $tocLines[$lineNum] );
					unset( $unlinkedLineTracker[$lineNum] );
					continue;
				}

				if ( $useFormForRedLinkedTopics ) {
					$formEditQuery['target'] = $topicPageName;
					$url = $formSpecialPageTitle->getLocalURL( $formEditQuery );
					$linkAttrs = [ 'href' => $url, 'class' => 'new', 'data-mdtype' => 'topic' ];
					$link = Html::rawElement( 'a', $linkAttrs, $lineValue );
				} else {
					$link = $linkRenderer->makeLink(
						$title,
						$lineValue,
						[ 'data-mdtype' => 'topic' ]
					);
				}

				$line = str_replace( $lineValue, $link, $line );
				$this->mTOCArray[] = [ $title, $numAsterisks ];
			}
		}

		// Now, use $unlinkedLineTracker to "hide" (remove from the
		// display) any unlinked lines that have no children, or at
		// least no displayed children.
		// We do this mostly so that a TOC "header" above topics that
		// have not yet been published to the main namespace also does
		// not get displayed in the main namespace.
		// First we call array_values() on both arrays to get rid of
		// any empty lines from the previous run-through.
		$tocLines = array_values( $tocLines );
		$unlinkedLineTracker = array_values( $unlinkedLineTracker );
		$unlinkedLinesToHide = [];
		for ( $lineNum = count( $unlinkedLineTracker ) - 1; $lineNum >= 0; $lineNum-- ) {
			list( $numAsterisks, $isUnlinked ) = $unlinkedLineTracker[$lineNum];
			if ( !$isUnlinked ) {
				continue;
			}
			$nextLineNum = $lineNum + 1;
			while ( in_array( $nextLineNum, $unlinkedLinesToHide ) ) {
				$nextLineNum++;
			}
			if ( $nextLineNum >= count( $unlinkedLineTracker ) ) {
				$unlinkedLinesToHide[] = $lineNum;
				continue;
			}
			list( $nextLineNumAsterisks, $nextLineIsUnlinked ) = $unlinkedLineTracker[$nextLineNum];
			if ( $nextLineNumAsterisks <= $numAsterisks ) {
				$unlinkedLinesToHide[] = $lineNum;
			}
		}

		foreach ( $unlinkedLinesToHide as $lineNum ) {
			unset( $tocLines[$lineNum] );
		}

		$toc = implode( "\n", $tocLines );

		// Handle standalone topics - prepended with a "!".
		$toc = preg_replace_callback(
			"/(\*+)\s*!\s*(.*)\s*$/m",
			function ( $matches ) {
				$standaloneTopicTitle = Title::newFromText( $matches[2], $this->getTitle()->getNamespace() );
				$standaloneTopic = MintyDocsTopic::newStandalone( $standaloneTopicTitle, $this );
				if ( $standaloneTopic == null ) {
					return $matches[1] . $matches[2];
				}
				return $matches[1] . $standaloneTopic->getTOCLink();
			},
			$toc
		);

		// Same with "borrowed" topics, with "+".
		$toc = preg_replace_callback(
			"/(\*+)\s*\+\s*(.*)\s*$/m",
			function ( $matches ) {
				$borrowedTopicTitle = Title::newFromText( $matches[2], $this->getTitle()->getNamespace() );
				$borrowedTopic = MintyDocsTopic::newBorrowed( $borrowedTopicTitle, $this );
				if ( $borrowedTopic == null ) {
					return $matches[1] . $matches[2];
				}
				return $matches[1] . $borrowedTopic->getTOCLink();
			},
			$toc
		);

		// Add a link to this manual as the first item in the TOC.
		// @TODO - the display name should be a #mintydocs_manual
		// param or otherwise configurable.
		$linkToManual = $linkRenderer->makeLink( $this->mTitle, 'About' );
		$toc = "*$linkToManual\n" . $toc;

		// doBlockLevels() takes care of just parsing '*' into
		// bulleted lists, which is all we need.
		$this->mTOCHTML = BlockLevelPass::doBlockLevels( $toc, true );

		if ( $showErrors && count( $topics ) > 0 ) {
			// Display error
			global $wgOut;
			$topicLinks = [];
			foreach ( $topics as $topic ) {
				$topicLinks[] = $topic->getTOCLink();
			}
			// @TODO - this is hardcoded for now, but change this
			// to an i18n message if it works out as a feature.
			//$errorMsg = wfMessage( 'mintydocs-manual-extratopics', implode( ', ', $topicLinks ) )->text();
			$errorMsg = "The following topics are defined for this manual but are not included in the list of topics: " .
				 implode( ', ', $topicLinks );
			$wgOut->addHTML( Html::warningBox( $errorMsg ) );
		}
	}

	function getTableOfContents( $showErrors ) {
		if ( $this->mTOCHTML == null ) {
			$this->generateTableOfContents( $showErrors );
		}
		return $this->mTOCHTML;
	}

	function getTableOfContentsArray( $showErrors ) {
		if ( $this->mTOCArray == null ) {
			$this->generateTableOfContents( $showErrors );
		}
		return $this->mTOCArray;
	}

	function getPreviousAndNextTopics( $topic, $showErrors ) {
		if ( $this->mTOCArray == null ) {
			$this->generateTableOfContents( $showErrors );
		}
		$topicActualName = $topic->getActualName();
		$prevTopic = null;
		$nextTopic = null;

		foreach ( $this->mTOCArray as $i => $curTopic ) {
			if ( !( $curTopic[0] instanceof MintyDocsTopic ) ) {
				continue;
			}
			$curTopicActualName = $curTopic[0]->getActualName();
			if ( $topicActualName == $curTopicActualName ) {
				$j = $i - 1;
				while ( $prevTopic == null && $j >= 0 ) {
					if ( $this->mTOCArray[$j][0] instanceof MintyDocsTopic ) {
						$prevTopic = $this->mTOCArray[$j][0];
					}
					$j--;
				}
				$j = $i + 1;
				while ( $nextTopic == null && $j < count( $this->mTOCArray ) ) {
					if ( $this->mTOCArray[$j][0] instanceof MintyDocsTopic ) {
						$nextTopic = $this->mTOCArray[$j][0];
					}
					$j++;
				}
			}
		}
		return [ $prevTopic, $nextTopic ];
	}

	function getEquivalentPageNameForVersion( $version ) {
		$versionPageName = $version->getTitle()->getPrefixedText();
		return $versionPageName . '/' . $this->getActualName();
	}

}

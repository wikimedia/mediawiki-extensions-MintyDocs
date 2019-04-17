<?php

class MintyDocsManual extends MintyDocsPage {

	private $mTOCHTML = null;
	private $mTOCArray = array();

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
			$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsManualDesc' ), $manualDescText );
		}

		$equivsInOtherVersions = $this->getEquivalentsInOtherVersions( $product, $version->getActualName() );
		if ( count( $equivsInOtherVersions ) > 0 ) {
			$otherVersionsText = wfMessage( 'mintydocs-manual-otherversions' )->text() . "\n";
			$otherVersionsText .= "<ul>\n";
			foreach ( $equivsInOtherVersions as $versionName => $manualPage ) {
				$otherVersionsText .= "<li>" . Linker::link( $manualPage, $versionName ) . "</li>\n";
			}
			$otherVersionsText .= "</ul>\n";
			$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsOtherManualVersions' ), $otherVersionsText );
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
		return array( $this->getDisplayName(), $toc );
	}

	function getAllTopics() {
		$topicPages = $this->getChildrenPages();
		$topics = array();
		foreach ( $topicPages as $topicPage ) {
			$topics[] = new MintyDocsTopic( $topicPage );
		}

		return $topics;
	}

	private function generateTableOfContents( $showErrors ) {
		global $wgParser;

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
			$wikiPage = new WikiPage( $title );
			$content = $wikiPage->getContent();
			if ( $content == null ) {
				$this->mTOCHTML = null;
				return;
			}
			$pageText = $content->getNativeData();
			// "Initialize" the parser, to avoid occasional errors
			// when the parser's $mOptions field is not set.
			$wgParser->startExternalParse( $title, new ParserOptions, Parser::OT_HTML );
			$rawTOC = $wgParser->recursiveTagParse( $pageText );
		}

		// Get rid of any HTML tags that may have gotten into
		// the topics list.
		$rawTOC = strip_tags( $rawTOC );

		// If the topics list comes from a page, there's a
		// chance that it's from a dynamic query, which means
		// that there might be extra newlines, etc. Get rid
		// of these, to make the output cleaner.
		$rawTOCLines = explode( "\n", $rawTOC );
		$tocLines = array();
		foreach( $rawTOCLines as $line ) {
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
			$formEditQuery = array();
			if ( $topicDefaultForm != null ) {
				$formEditQuery['form'] = $topicDefaultForm;
			}
			if ( $topicAlternateFormsStr != '' ) {
				$topicAlternateForms = array_map( 'trim', explode( ',', $topicAlternateFormsStr ) );
				$formEditQuery['alt_form'] = array();
				foreach ( $topicAlternateForms as $i => $altForm ) {
					$formEditQuery['alt_form'][] = $altForm;
				}
			}
			$formSpecialPage = SpecialPageFactory::getPage( 'FormEdit' );
			$formSpecialPageTitle = $formSpecialPage->getPageTitle();
		}

		$manualHasDraftPage = $this->hasDraftPage();

		$topics = $this->getAllTopics();
		$this->mTOCArray = array();
		foreach( $tocLines as $lineNum => &$line ) {
			$matches = array();
			preg_match( "/(\*+)\s*(.*)\s*$/", $line, $matches );
			$numAsterisks = strlen( $matches[1] );
			$lineValue = $matches[2];
			if ( strpos( $lineValue, '-' ) === 0 ) {
				$displayText = trim( substr( $lineValue, 1 ) );
				$line = str_replace( $lineValue, $displayText, $line );
				$this->mTOCArray[] = array( $displayText, $numAsterisks );
				continue;
			}
			if ( strpos( $lineValue, '!' ) === 0 ) {
				$title = Title::newFromText( trim( substr( $lineValue, 1 ) ) );
				$topic = MintyDocsTopic::newStandalone( $title, $this );
				if ( $topic != null ) {
					$this->mTOCArray[] = array( $topic, $numAsterisks );
				} else {
					$this->mTOCArray[] = array( $title, $numAsterisks );
				}
				continue;
			}

			$foundMatchingTopic = false;
			foreach ( $topics as $i => $topic ) {
				$topicActualName = $topic->getActualName();
				if ( $lineValue == $topicActualName ) {
					$foundMatchingTopic = true;
					$line = str_replace( $lineValue, $topic->getTOCLink(), $line );
					$this->mTOCArray[] = array( $topic, $numAsterisks );
					// Unset this so that $topics will hold the list of unmatched topics.
					unset( $topics[$i] );
					break;
				}
			}
			if ( !$foundMatchingTopic ) {
				// Make a link to this page, which is either
				// nonexistent or at least lacks a #minty_docs
				// topic call.
				$topicPageName = $this->getTitle()->getPrefixedText() . '/' . trim( $lineValue );
				$title = Title::newFromText( $topicPageName );
				if ( $manualHasDraftPage ) {
					// If it's a non-draft page, and it has
					// a draft page, then just don't
					// display red-linked topics at all -
					// they presumably exist as drafts but
					// haven't been published yet.
					unset( $tocLines[$lineNum] );
					continue;
				}

				if ( $useFormForRedLinkedTopics ) {
					$formEditQuery['target'] = $topicPageName;
					$url = $formSpecialPageTitle->getLocalURL( $formEditQuery );
					$linkAttrs = array( 'href' => $url, 'class' => 'new', 'data-mdtype' => 'topic' );
					$link = Html::rawElement( 'a', $linkAttrs, $lineValue );
				} else {
					$link = Linker::link( $title, $lineValue, array( 'data-mdtype' => 'topic' ) );
				}

				$line = str_replace( $lineValue, $link, $line );
				$this->mTOCArray[] = array( $title, $numAsterisks );
			}
		}

		$toc = implode( "\n", $tocLines );

		// Handle standalone topics - prepended with a "!".
		$toc = preg_replace_callback(
			"/(\*+)\s*!\s*(.*)\s*$/m",
			function( $matches ) {
				$standaloneTopicTitle = Title::newFromText( $matches[2] );
				$standaloneTopic = MintyDocsTopic::newStandalone( $standaloneTopicTitle, $this );
				if ( $standaloneTopic == null ) {
					return $matches[1] . $matches[2];
				}
				return $matches[1] . $standaloneTopic->getTocLink();
			},
			$toc
		);

		// Add a link to this manual as the first item in the TOC.
		// @TODO - the display name should be a #mintydocs_manual
		// param or otherwise configurable.
		$linkToManual = Linker::link( $this->mTitle, 'About' );
		$toc = "*$linkToManual\n" . $toc;

		// doBlockLevels() takes care of just parsing '*' into
		// bulleted lists, which is all we need.
		$this->mTOCHTML = $wgParser->doBlockLevels( $toc, true );

		if ( $showErrors && count( $topics ) > 0 ) {
			// Display error
			global $wgOut;
			$topicLinks = array();
			foreach ( $topics as $topic ) {
				$topicLinks[] = $topic->getTOCLink();
			}
			// @TODO - this is hardcoded for now, but change this
			// to an i18n message if it works out as a feature.
			//$errorMsg = wfMessage( 'mintydocs-manual-extratopics', implode( ', ', $topicLinks ) )->text();
			$errorMsg = "The following topics are defined for this manual but are not included in the list of topics: " .
				 implode( ', ', $topicLinks );
			$wgOut->addHTML( Html::rawElement( 'div', array( 'class' => 'warningbox' ), $errorMsg ) );
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

		foreach( $this->mTOCArray as $i => $curTopic ) {
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
		return array( $prevTopic, $nextTopic );
	}

	function getEquivalentPageNameForVersion( $version ) {
		$versionPageName = $version->getTitle()->getText();
		return $versionPageName . '/' . $this->getActualName();
	}

}
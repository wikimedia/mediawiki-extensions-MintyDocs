<?php

class MintyDocsManual extends MintyDocsPage {
	
	private $mTOC = null;
	private $mOrderedTopics = null;
	
	static function getPageTypeValue() {
		return 'Manual';
	}

	public function hasPagination() {
		// @TODO - change null to false
		return $this->getPossiblyInheritedParam( 'MintyDocsPagination' );
	}

	function getHeader() {
		list( $product, $version ) = $this->getProductAndVersion();
		$manualDescText = wfMessage( 'mintydocs-manual-desc', $version->getLink(), $product->getLink() )->text();
		$text = Html::rawElement( 'div', array( 'class' => 'MintyDocsManualDesc' ), $manualDescText );

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

		$contentsText = wfMessage( 'mintydocs-manual-contents' )->text() . "\n" . $this->getTableOfContents( true );
		$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsManualTOC' ), $contentsText );

		return $text;
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

		$tocOrPageName = $this->getPossiblyInheritedParam( 'MintyDocsTopicsList' );
		// Decide whether this is a table of contents or a page name
		// based on whether or not the string starts with a '*' -
		// hopefully that's a good enough check.
		if ( $tocOrPageName == null ) {
			$this->mTOC = null;
			return;
		} elseif ( substr( $tocOrPageName, 0, 1 ) == '*' ) {
			$toc = $tocOrPageName;
		} else {
			$title = Title::newFromText( $tocOrPageName );
			if ( $title == null ) {
				$this->mTOC = null;
				return;
			}
			$wikiPage = new WikiPage( $title );
			$content = $wikiPage->getContent();
			if ( $content == null ) {
				$this->mTOC = null;
				return;
			}
			$pageText = $content->getNativeData();
			// "Initialize" the parser, to avoid occasional errors
			// when the parser's $mOptions field is not set.
			$wgParser->startExternalParse( $title, new ParserOptions, Parser::OT_HTML );
			$rawTOC = $wgParser->recursiveTagParse( $pageText );
			// If the topics list comes from a page, there's a
			// chance that it's from a dynamic query, which means
			// that there might be extra newlines, etc. Get rid
			// of these, to make the output cleaner.
			$tocLines = explode( "\n", $rawTOC );
			$toc = '';
			foreach( $tocLines as $line ) {
				$line = trim( $line );
				if ( str_replace( '*', '', $line ) != '' ) {
					$toc .= "$line\n";
				}
			}
		}
		$topics = $this->getAllTopics();
		$this->mOrderedTopics = array();
		foreach ( $topics as $i => $topic ) {
			$topicActualName = $topic->getActualName();
			// Get level of each topic in the hierarchy - this is
			// not currently used by anything, but it may become
			// useful in the future.
			$matches = array();
			preg_match( "/(\*+)\s*$topicActualName\s*$/m", $toc, $matches );
			$numAsterisks = strlen( $matches[1] );
			$tocBeforeReplace = $toc;
			$toc = preg_replace( "/(\*+)\s*$topicActualName\s*$/m",
				'$1' . $topic->getTOCLink(), $toc );
			if ( $toc != $tocBeforeReplace ) {
				// Replacement was succesful.
				$this->mOrderedTopics[] = array( $topicActualName, $numAsterisks );
				unset( $topics[$i] );
			}
		}

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

		// doBlockLevels() takes care of just parsing '*' into
		// bulleted lists, which is all we need.
		$this->mTOC = $wgParser->doBlockLevels( $toc, true );

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
		if ( $this->mTOC == null ) {
			$this->generateTableOfContents( $showErrors );
		}
		return $this->mTOC;
	}

	function getOrderedTopics( $showErrors ) {
		if ( $this->mOrderedTopics == null ) {
			$this->generateTableOfContents( $showErrors );
		}
		return $this->mOrderedTopics;
	}

	function getPreviousAndNextTopics( $topic, $showErrors ) {
		if ( $this->mOrderedTopics == null ) {
			$this->generateTableOfContents( $showErrors );
		}
		$topicActualName = $topic->getActualName();
		$prevTopic = null;
		$nextTopic = null;

		foreach( $this->mOrderedTopics as $i => $curTopic ) {
			$curTopicActualName = $curTopic[0];
			if ( $topicActualName == $curTopicActualName ) {
				// It's wasteful to have to create the MintyDocsTopic objects
				// again, but there are only two of them, so it seems
				// easier to do it this way than to store lots of unneeded
				// objects.
				$manualPageName = $this->mTitle->getText();
				if ( $i == 0 ) {
					$prevTopic = null;
				} else {
					$prevTopicActualName = $this->mOrderedTopics[$i - 1][0];
					$prevTopicPageName = $manualPageName . '/' . $prevTopicActualName;
					$prevTopicPage = Title::newFromText( $prevTopicPageName );
					$prevTopic = new MintyDocsTopic( $prevTopicPage );
				}
				if ( $i == count( $this->mOrderedTopics ) - 1 ) {
					$nextTopic = null;
				} else {
					$nextTopicActualName = $this->mOrderedTopics[$i + 1][0];
					$nextTopicPageName = $manualPageName . '/' . $nextTopicActualName;
					$nextTopicPage = Title::newFromText( $nextTopicPageName );
					$nextTopic = new MintyDocsTopic( $nextTopicPage );
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
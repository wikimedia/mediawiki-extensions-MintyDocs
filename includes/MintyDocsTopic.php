<?php

class MintyDocsTopic extends MintyDocsPage {
	private $mManual = null;
	private $mIsStandalone = false;
	private $mIsBorrowed = false;

	public function __construct( $title ) {
		global $wgRequest;

		$this->mTitle = $title;

		// Determine whether this is an invalid and/or standalone
		// topic, and also get the corresponding manual.
		// This is a little confusing: "invalid" is a permanent aspect
		// of a topic, indicating that its URL does not conform to the
		// "product/version/manual/topic" syntax, while "standalone" is
		// a temporary aspect, indicating that it is currently being
		// viewed with product, version and manual specified in the
		// query string.
		// "invalid" and "standalone" are independent of onne another;
		// a topic can be valid but still used as a standalone topic
		// within another manual.
		$manualFromQueryString = null;
		if ( $wgRequest->getCheck( 'product' ) &&
			$wgRequest->getCheck( 'version' ) &&
			$wgRequest->getCheck( 'manual' ) ) {
			$manualPageName = $wgRequest->getVal( 'product' ) . '/' .
				$wgRequest->getVal( 'version' ) . '/' .
				$wgRequest->getVal( 'manual' );
			$manualTitle = Title::newFromText( $manualPageName );
			$manualFromQueryString = new MintyDocsManual( $manualTitle );
			if ( $manualFromQueryString->getPageProp( 'MintyDocsPageType' ) == 'Manual' ) {
				$this->mIsStandalone = true;
			}
		}

		$parentPage = $this->getParentPage();
		if ( $parentPage == null ) {
			$this->mIsInvalid = true;
			return null;
		}
		$manualFromPageName = new MintyDocsManual( $parentPage );
		if ( $manualFromPageName->getPageProp( 'MintyDocsPageType' ) != 'Manual' ) {
			$this->mIsInvalid = true;
		}

		if ( $manualFromQueryString != null ) {
			$this->mManual = $manualFromQueryString;
		} elseif ( $manualFromPageName != null ) {
			$this->mManual = $manualFromPageName;
		}
	}

	static function getPageTypeValue() {
		return 'Topic';
	}

	static function newStandalone( $title, $manual ) {
		$topic = new MintyDocsTopic( $title );
		// Make sure this page calls #mintydocs_topic.
		$pageType = $topic->getPageProp( 'MintyDocsPageType' );
		if ( $pageType != 'Topic' ) {
			return null;
		}

		$topic->mManual = $manual;
		$topic->mIsStandalone = true;
		return $topic;
	}

	static function newBorrowed( $title, $manual ) {
		$topic = self::newStandalone( $title, $manual );
		$topic->mIsStandalone = false;
		$topic->mIsBorrowed = true;
		return $topic;
	}

	function getHeader() {
		global $wgMintyDocsShowBreadcrumbs;

		if ( $this->mIsInvalid ) {
			return;
		}

		$text = '';

		$manual = $this->getManual();
		list( $product, $version ) = $manual->getProductAndVersion();

		if ( $wgMintyDocsShowBreadcrumbs ) {
			$topicDescText = wfMessage( 'mintydocs-topic-desc', $manual->getLink(), $version->getLink(), $product->getLink() )->text();
			$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsTopicDesc' ), $topicDescText );
		}

		$equivsInOtherVersions = $this->getEquivalentsInOtherVersions( $product, $version->getActualName() );
		if ( count( $equivsInOtherVersions ) > 0 ) {
			$otherVersionsText = wfMessage( 'mintydocs-topic-otherversions' )->text() . "\n";
			$otherVersionsText .= "<ul>\n";
			foreach ( $equivsInOtherVersions as $versionName => $topicPage ) {
				$otherVersionsText .= "<li>" . Linker::link( $topicPage, $versionName ) . "</li>\n";
			}
			$otherVersionsText .= "</ul>\n";
			$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsOtherManualVersions' ), $otherVersionsText );
		}

		if ( $manual->hasPagination() ) {
			list( $prevTopic, $nextTopic ) = $manual->getPreviousAndNextTopics( $this, false );
			if ( $prevTopic ) {
				$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsPrevTopicLink' ), '&larr;<br />' . $prevTopic->getLink() );
			}
			if ( $nextTopic ) {
				$text .= Html::rawElement( 'div', array( 'class' => 'MintyDocsNextTopicLink' ), '&rarr;<br />' . $nextTopic->getLink() );
			}
		}

		return $text;
	}

	function getTOCLink() {
		$displayName = $this->getPageProp( 'MintyDocsTOCName' );
		// Is this necessary?
		if ( $displayName == null ) {
			$displayName = $this->getDisplayName();
		}
		$query = array();
		if ( $this->mIsStandalone ) {
			$manual = $this->getManual();
			list( $product, $version ) = $manual->getProductAndVersion();
			$query['product'] = $product->getActualName();
			$query['version'] = $version->getActualName();
			$query['manual'] = $manual->getActualName();
		} elseif ( $this->mIsBorrowed ) {
			$manual = $this->getManual();
			list( $product, $version ) = $manual->getProductAndVersion();
			$query['contextProduct'] = $product->getActualName();
			$query['contextVersion'] = $version->getActualName();
			$query['contextManual'] = $manual->getActualName();
		}
		return Linker::link( $this->mTitle, $displayName, array(), $query );
	}

	function getFooter() {
		if ( $this->mIsInvalid ) {
			return null;
		}

		$manual = $this->getManual();

		$header = '<p>' . $manual->getDisplayName() . '</p>';
		$toc = $manual->getTableOfContents( false );
		return Html::rawElement( 'div', array( 'class' => 'MintyDocsTopicTOC' ), $header . $toc );
	}

	function getSidebarText() {
		if ( $this->mIsInvalid ) {
			return null;
		}

		$manual = $this->getManual();
		$toc = $manual->getTableOfContents( false );
		return array( $manual->getDisplayName(), $toc );
	}

	function getChildrenPages() {
		return array();
	}

	function getManual() {
		return $this->mManual;
	}

	function getEquivalentPageNameForVersion( $version ) {
		$versionPageName = $version->getTitle()->getPrefixedText();
		return $versionPageName . '/' . $this->getManual()->getActualName() . '/' . $this->getActualName();
	}

	function isStandalone() {
		return $this->mIsStandalone;
	}
}

<?php

use MediaWiki\MediaWikiServices;

/**
 * @author Yaron Koren
 */

class MintyDocsPublish extends UnlistedSpecialPage {

	protected static $mNoActionNeededMessage = "Nothing to publish.";
	protected static $mEditSummaryMsg = "mintydocs-publish-editsummary";
	protected static $mSuccessMsg = "mintydocs-publish-success";
	protected static $mSinglePageMsg = "mintydocs-publish-singlepage";
	protected static $mButtonMsg = "mintydocs-publish-button";
	private static $mCheckboxNumber = 1;

	/**
	 * Constructor
	 */
	function __construct( $pageName = null ) {
		if ( $pageName == null ) {
			$pageName = 'MintyDocsPublish';
		}
		parent::__construct( $pageName );
	}

	function generateSourceTitle( $sourcePageName ) {
		return Title::newFromText( $sourcePageName, MD_NS_DRAFT );
	}

	function generateTargetTitle( $targetPageName ) {
		return Title::newFromText( $targetPageName, NS_MAIN );
	}

	function generateParentTargetTitle( $fromParentTitle ) {
		$fromParentPageName = $fromParentTitle->getText();
		return $this->generateTargetTitle( $fromParentPageName );
	}

	function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$req = $this->getRequest();

		if ( $query == '' ) {
			$pageName = $req->getVal( 'page_name_1' );
			$query = $this->generateSourceTitle( $pageName );
		}

		try {
			$title = $this->getTitleFromQuery( $query );
		} catch ( Exception $e ) {
			$out->addHTML( $e->getMessage() );
			return;
		}

		// Check permissions.
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( !$mdPage->userCanAdminister( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}

		$publish = $req->getCheck( 'mdPublish' );
		if ( $publish ) {
			// Guard against cross-site request forgeries (CSRF).
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), $this->getName() );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling.";
				$out->addHTML( $text );
				return;
			}

			$this->publishAll();
			return;
		}

		$this->displayMainForm( $title );
	}

	function getTitleFromQuery( $query ) {
		if ( $query == null ) {
			throw new MWException( 'Page name must be set.' );
		}

		// Generate Title
		if ( $query instanceof Title ) {
			$title = $query;
		} else {
			$title = Title::newFromText( $query );
		}

		// Class-specific validation.
		$this->validateTitle( $title );

		// Error if page does not exist.
		if ( !$title->exists() ) {
			throw new MWException( $this->msg( "pagelang-nonexistent-page", '[[' . $title->getFullText() . ']]' ) );
		}

		return $title;
	}

	function validateTitle( $title ) {
		if ( $title->getNamespace() != MD_NS_DRAFT ) {
			throw new MWException( 'Must be a Draft page!' );
		}
	}

	function displayMainForm( $title ) {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$out->enableOOUI();

		// Display checkboxes.
		$text = '<form id="mdPublishForm" action="" method="post">';
		if ( $req->getCheck( 'single' ) ) {
			$isSinglePage = true;
		} else {
			$mdPage = MintyDocsUtils::pageFactory( $title );
			$isSinglePage = ( $mdPage == null || $mdPage instanceof MintyDocsTopic );
		}
		if ( $isSinglePage ) {
			$toTitle = $this->generateTargetTitle( $title->getText() );
			$error = $this->validateSinglePageAction( $title, $toTitle );
			if ( $error != null ) {
				$out->addHTML( $error );
				return;
			}
			$text .= Html::element( 'p', null, $this->msg( self::$mSinglePageMsg )->text() );
			$text .= Html::hidden( 'page_name_1', $title->getText() );
		} else {
			$text .= '<h3>Pages for ' . $mdPage->getLink() . ':</h3>';
			$text .= ( new ListToggle( $this->getOutput() ) )->getHTML();
			$text .= Html::rawElement(
				'ul',
				[ 'style' => 'margin: 15px 0; list-style: none;' ],
				$this->displayPageParents( $mdPage )
			);
			$pagesTree = $this->makePagesTree( $mdPage );
			$text .= Html::rawElement(
				'ul',
				null,
				$this->displayCheckboxesForTree( $pagesTree['node'], $pagesTree['tree'] )
			);
		}

		if ( !$isSinglePage && self::$mCheckboxNumber == 1 ) {
			$text = '<p>(' . self::$mNoActionNeededMessage . ")</p>\n";
			$out->addHTML( $text );
			return;
		}

		$titleString = MintyDocsUtils::titleURLString( $this->getPageTitle() );
		$text .= Html::hidden( 'title', $titleString ) . "\n";
		$text .= Html::hidden( 'csrf', $this->getUser()->getEditToken( $this->getName() ) ) . "\n";
		$text .= "<br />\n" . new OOUI\ButtonInputWidget(
			[
				'name' => 'mdPublish',
				'type' => 'submit',
				'flags' => [ 'progressive', 'primary' ],
				'label' => $this->msg( self::$mButtonMsg )->parse()
			]
		);

		$text .= '</form>';
		$out->addHTML( $text );
	}

	function validateSinglePageAction( $fromTitle, $toTitle ) {
		if ( !$toTitle->exists() ) {
			return null;
		}
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
			$fromPage = $wikiPageFactory->newFromTitle( $fromTitle );
			$toPage = $wikiPageFactory->newFromTitle( $toTitle );
		} else {
			$fromPage = WikiPage::factory( $fromTitle );
			$toPage = WikiPage::factory( $toTitle );
		}
		$fromPageText = $fromPage->getContent()->getNativeData();
		$toPageText = $toPage->getContent()->getNativeData();
		if ( $fromPageText == $toPageText ) {
			return 'There is no need to publish this page - the published version matches the draft version.';
		}
		return null;
	}

	function displayPageParents( $mdPage ) {
		$parentPage = $mdPage->getParentPage();
		if ( $parentPage == null ) {
			return '';
		}
		$parentMDPage = MintyDocsUtils::pageFactory( $parentPage );
		if ( $parentMDPage == null ) {
			return '';
		}

		// We use the <li> tag so that MediaWiki's toggle JS will
		// work on these checkboxes.
		return $this->displayPageParents( $parentMDPage ) .
			'<li class="parentPage"><em>' . $parentMDPage->getPageTypeValue() . '</em>: ' .
			$this->displayLine( $parentMDPage ) . '</li>';
	}

	static function makePagesTree( $mdPage, $numTopicIndents = 0 ) {
		$pagesTree = [ 'node' => $mdPage, 'tree' => [] ];
		if ( $mdPage instanceof MintyDocsProduct ) {
			$versions = $mdPage->getVersions();
			foreach ( $versions as $versionNum => $version ) {
				$pagesTree['tree'][] = self::makePagesTree( $version );
			}
			return $pagesTree;
		} elseif ( $mdPage instanceof MintyDocsVersion ) {
			$manuals = $mdPage->getAllManuals();
			foreach ( $manuals as $manualName => $manual ) {
				$pagesTree['tree'][] = self::makePagesTree( $manual );
			}
			return $pagesTree;
		} elseif ( $mdPage instanceof MintyDocsManual ) {
			$toc = $mdPage->getTableOfContentsArray( false );
			foreach ( $toc as $i => $element ) {
				list( $topic, $curLevel ) = $element;
				if ( $topic instanceof MintyDocsTopic || is_string( $topic ) ) {
					$pagesTree['tree'][] = self::makePagesTree( $topic, $curLevel - 1 );
				}
			}
			return $pagesTree;
		} elseif ( $mdPage instanceof MintyDocsTopic || is_string( $mdPage ) ) {
			if ( $numTopicIndents > 0 ) {
				$pagesTree['node'] = null;
				$pagesTree['tree'][] = self::makePagesTree( $mdPage, $numTopicIndents - 1 );
			}
			return $pagesTree;
		}
	}

	function displayCheckboxesForTree( $node, $tree ) {
		$text = '';
		if ( $node == null ) {
			// Do nothing.
		} elseif ( is_string( $node ) ) {
			$text .= "\n<li><em>" . $node . '</em></li>';
		} elseif ( $node instanceof MintyDocsTopic && $node->isBorrowed() ) {
			$text .= "\n<li>" . $node->getLink() . ' (this is a borrowed page)</li>';
		} else {
			$text .= "\n<li>" . $this->displayLine( $node ) . '</li>';
		}
		if ( count( $tree ) > 0 ) {
			$text .= '<ul>';
			foreach ( $tree as $node ) {
				$innerNode = $node['node'];
				$innerTree = $node['tree'];
				$text .= $this->displayCheckboxesForTree( $innerNode, $innerTree );
			}
			$text .= '</ul>';
		}
		return $text;
	}

	function displayLine( $mdPage ) {
		// See if the parent page of this one (if there is such a thing)
		// exists in the new location - if not, we can't publish this
		// page.
		$cannotBePublished = false;
		$parentTitle = $mdPage->getParentPage();
		if ( $parentTitle !== null ) {
			$toParentTitle = $this->generateTargetTitle( $parentTitle->getText() );
			if ( !$toParentTitle->exists() ) {
				$cannotBePublished = true;
			}
		}

		$fromTitle = $mdPage->getTitle();
		$fromPageName = $fromTitle->getText();
		$toTitle = $this->generateTargetTitle( $fromPageName );
		if ( !$toTitle->exists() ) {
			return $this->displayLineWithCheckbox( $mdPage, $fromPageName, false, $cannotBePublished );
		}
		if ( !$this->overwritingIsAllowed() && $toTitle->exists() ) {
			return $mdPage->getLink() . ' (already exists)';
		}

		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
			$fromPage = $wikiPageFactory->newFromTitle( $fromTitle );
			$toPage = $wikiPageFactory->newFromTitle( $toTitle );
		} else {
			$fromPage = WikiPage::factory( $fromTitle );
			$toPage = WikiPage::factory( $toTitle );
		}
		$fromPageText = $fromPage->getContent()->getNativeData();
		$toPageText = $toPage->getContent()->getNativeData();
		// If the text of the two pages is the same, no point
		// dislaying a checkbox.
		if ( $fromPageText == $toPageText ) {
			return $mdPage->getLink() . ' (no change)';
		}
		return $this->displayLineWithCheckbox( $mdPage, $fromPageName, true, $cannotBePublished );
	}

	function displayLineWithCheckbox( $mdPage, $fromPageName, $toPageExists, $cannotBePublished ) {
		$checkboxAttrs = [
			'name' => 'page_name_' . self::$mCheckboxNumber++,
			'value' => $fromPageName,
			'class' => [ 'mdCheckbox' ],
			'selected' => true
		];
		if ( $cannotBePublished ) {
			$checkboxAttrs['disabled'] = true;
		}
		$str = new OOUI\CheckboxInputWidget( $checkboxAttrs );
		if ( $toPageExists ) {
			$str .= $mdPage->getLink();
		} else {
			$str .= '<strong>' . $mdPage->getLink() . '</strong>';
		}
		if ( $cannotBePublished ) {
			$str .= ' (cannot be published because its parent page has not been published)';
		}
		return $str;
	}

	function overwritingIsAllowed() {
		return true;
	}

	function publishAll() {
		$req = $this->getRequest();
		$user = $this->getUser();
		$out = $this->getOutput();

		$jobs = [];

		$submittedValues = $req->getValues();
		$toTitles = [];

		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		} else {
			$wikiPageFactory = null;
		}
		foreach ( $submittedValues as $key => $val ) {
			if ( substr( $key, 0, 10 ) != 'page_name_' ) {
				continue;
			}

			$fromPageName = $val;
			$fromTitle = $this->generateSourceTitle( $fromPageName );
			if ( $wikiPageFactory !== null ) {
				// MW 1.36+
				$fromPage = $wikiPageFactory->newFromTitle( $fromTitle );
			} else {
				$fromPage = WikiPage::factory( $fromTitle );
			}
			$fromPageText = $fromPage->getContent()->getNativeData();
			$toTitle = $this->generateTargetTitle( $fromPageName );
			$toTitles[] = $toTitle;
			$params = [];
			$params['user_id'] = $user->getId();
			$params['page_text'] = $fromPageText;
			$params['edit_summary'] = $this->msg( self::$mEditSummaryMsg )->inContentLanguage()->text();

			// If this is a MintyDocs page with a parent page, send
			// the name of the parent page in the other namespace
			// to the job, so the job can check whether that page
			// exists, and cancel the save if not - we don't want to
			// create an invalid MD page.
			// The most likely scenario for that to happen (though
			// not the only one) is that a whole set of pages are
			// being created, and for some reason the saving of
			// child pages occurs right before the saving of the
			// parent.
			$fromMDPage = MintyDocsUtils::pageFactory( $fromTitle );
			if ( $fromMDPage !== null ) {
				$fromParentTitle = $fromMDPage->getParentPage();
				if ( $fromParentTitle !== null ) {
					$toParentTitle = $this->generateParentTargetTitle( $fromParentTitle );
					$toParentPageName = $toParentTitle->getFullText();
					$params['parent_page'] = $toParentPageName;
				}
			}

			$jobs[] = new MintyDocsCreatePageJob( $toTitle, $params );
		}

		if ( method_exists( MediaWikiServices::class, 'getJobQueueGroup' ) ) {
			// MW 1.37+
			MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobs );
		} else {
			JobQueueGroup::singleton()->push( $jobs );
		}

		$linkRenderer = $this->getLinkRenderer();

		if ( count( $jobs ) == 0 ) {
			$text = 'No pages were specified.';
		} elseif ( count( $jobs ) == 1 ) {
			if ( $toTitle->exists() ) {
				$text = 'The page ' . $linkRenderer->makeLink( $toTitle ) . ' will be modified.';
			} else {
				$text = 'The page ' . $linkRenderer->makeLink( $toTitle ) . ' will be created.';
			}
		} else {
			$titlesStr = '';
			foreach ( $toTitles as $i => $title ) {
				if ( $i > 0 ) {
					$titlesStr .= ', ';
				}
				$titlesStr .= $linkRenderer->makeLink( $title );
			}
			$text = $this->msg( self::$mSuccessMsg, $titlesStr )->text();
		}

		$out->addHTML( $text );
	}

}

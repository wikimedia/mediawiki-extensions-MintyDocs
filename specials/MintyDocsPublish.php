<?php

/**
 * @author Yaron Koren
 */

class MintyDocsPublish extends SpecialPage {

	protected static $mNoActionNeededMessage = "Nothing to publish.";
	protected static $mEditSummary = 'Published';
	protected static $mSuccessMessage = 'The following pages will be created or modified: ';
	protected static $mSinglePageMessage = "Publish this draft page?";
	protected static $mButtonText = "Publish";
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

	function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$req = $this->getRequest();

		// Check permissions.
		if ( !$this->getUser()->isAllowed( 'mintydocs-administer' ) ) {
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

		try {
			$title = $this->getTitleFromQuery( $query );
		} catch ( Exception $e ) {
			$out->addHTML( $e->getMessage() );
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
			throw new MWException( 'Page does not exist!' );
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

		$out->addModules( 'ext.mintydocs.publish' );

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
			$text .= '<p>' . self::$mSinglePageMessage . '</p>';
			$text .= Html::hidden( 'page_name_1', $title->getText() );
		} else {
			$text .= '<p>Pages for ' . $mdPage->getLink() . ':</p>';
			$text .= $this->displayPageParents( $mdPage );
			$pagesTree = $this->makePagesTree( $mdPage );
			$text .= '<ul>';
			$text .= $this->displayCheckboxesForTree( $pagesTree['node'], $pagesTree['tree'] );
			$text .= '</ul>';
			$text .= $this->displayToggleLinks();
		}

		if ( !$isSinglePage && self::$mCheckboxNumber == 1 ) {
			$text = '<p>(' . self::$mNoActionNeededMessage . ")</p>\n";
			$out->addHTML( $text );
			return;
		}

		$mdp = $this->getTitle();
		$text .= Html::hidden( 'title', PFUtils::titleURLString( $mdp ) ) . "\n";

		$text .= Html::hidden( 'csrf', $this->getUser()->getEditToken( $this->getName() ) ) . "\n";

		$text .= Html::element( 'input',
			array(
				'type' => 'submit',
				'name' => 'mdPublish',
				'value' => self::$mButtonText
			)
		);

		$text .= '</form>';
		$out->addHTML( $text );
	}

	function validateSinglePageAction( $fromTitle, $toTitle ) {
		if ( ! $toTitle->exists() ) {
			return null;
		}
		$fromPage = WikiPage::factory( $fromTitle );
		$fromPageText = $fromPage->getContent()->getNativeData();
		$toPage = WikiPage::factory( $toTitle );
		$toPageText = $toPage->getContent()->getNativeData();
		if ( $fromPageText == $toPageText ) {
			return 'There is no need to publish this page - the published version matches the draft version.';
		}
		return null;
	}

	function displayPageParents( $mdPage ) {
		if ( $mdPage instanceof MintyDocsProduct ) {
			return '';
		}

		$parentPage = $mdPage->getParentPage();
		if ( $parentPage == null ) {
			return '';
		}
		$parentMDPage = MintyDocsUtils::pageFactory( $parentPage );
		if ( $parentMDPage == null ) {
			return '';
		}

		return $this->displayPageParents( $parentMDPage ) .
			'<p class="parentPage"><em>' . $parentMDPage->getPageTypeValue() . '</em>: ' .
			$this->displayPageName( $parentMDPage ) . '</p>';
	}

	static function makePagesTree( $mdPage ) {
		$pagesTree = array( 'node' => $mdPage, 'tree' => array() );
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
			$topics = $mdPage->getAllTopics();
			foreach ( $topics as $topic ) {
				$pagesTree['tree'][] = self::makePagesTree( $topic );
			}
			return $pagesTree;
		} elseif ( $mdPage instanceof MintyDocsTopic ) {
			return $pagesTree;
		}
	}

	function displayCheckboxesForTree( $node, $tree ) {
		$text = '';
		if ( $node != null ) {
			$text .= "\n<li>" . $this->displayPageName( $node ) . '</li>';
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

	function displayToggleLinks() {
		$text =<<<END
<p class="selectAndDeselect">
<a id="selectall">Select all</a>
&middot;
<a id="deselectall">Deselect all</a>
</p>

END;
		return $text;
	}

	function displayPageName( $mdPage ) {
		$fromTitle = $mdPage->getTitle();
		$fromPageName = $fromTitle->getText();
		$toTitle = $this->generateTargetTitle( $fromPageName );
		if ( !$toTitle->exists() ) {
			return Html::check( 'page_name_' . self::$mCheckboxNumber++, true, array( 'value' => $fromPageName, 'class' => 'mdCheckbox' ) ) .
			'<strong>' . $mdPage->getLink() . '</strong>';
		}
		if ( !$this->overwritingIsAllowed() && $toTitle->exists() ) {
			return $mdPage->getLink() . ' (already exists)';
		}

		$fromPage = WikiPage::factory( $fromTitle );
		$fromPageText = $fromPage->getContent()->getNativeData();
		$toPage = WikiPage::factory( $toTitle );
		$toPageText = $toPage->getContent()->getNativeData();
		// If the text of the two pages is the same, no point
		// dislaying a checkbox.
		if ( $fromPageText == $toPageText ) {
			return $mdPage->getLink() . ' (no change)';
		}
		return Html::check( 'page_name_' . self::$mCheckboxNumber++, true, array( 'value' => $fromPageName ) ) . $mdPage->getLink();
	}

	function overwritingIsAllowed() {
		return true;
	}

	function publishAll() {
		$req = $this->getRequest();
		$user = $this->getUser();
		$out = $this->getOutput();

		$jobs = array();

		$submittedValues = $req->getValues();
		$toTitles = array();

		foreach( $submittedValues as $key => $val ) {
			if ( substr( $key, 0, 10 ) != 'page_name_' ) {
				continue;
			}

			$fromPageName = $val;
			$fromTitle = $this->generateSourceTitle( $fromPageName );
			$fromPage = WikiPage::factory( $fromTitle );
			$fromPageText = $fromPage->getContent()->getNativeData();
			$toTitle = $this->generateTargetTitle( $fromPageName );
			$toTitles[] = $toTitle;
			$params = array();
			$params['user_id'] = $user->getId();
			$params['page_text'] = $fromPageText;
			$params['edit_summary'] = self::$mEditSummary;

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
			if ( $fromMDPage && ( ! $fromMDPage instanceof MintyDocsProduct ) ) {
				$fromParentTitle = $fromMDPage->getParentPage();
				$fromParentPageName = $fromParentTitle->getText();
				$toParentTitle = self::generateTargetTitle( $fromParentPageName );
				$toParentPageName = $toParentTitle->getText();
				$params['parent_page'] = $toParentPageName;
			}

			$jobs[] = new MintyDocsCreatePageJob( $toTitle, $params );
		}

		JobQueueGroup::singleton()->push( $jobs );

		if ( count( $jobs ) == 0 ) {
			$text = 'No pages were specified.';
		} elseif ( count( $jobs ) == 1 ) {
			if ( $toTitle->exists() ) {
				$text = 'The page ' . Linker::link( $toTitle ) . ' will be modified.';
			} else {
				$text = 'The page ' . Linker::link( $toTitle ) . ' will be created.';
			}
		} else {
			$titlesStr = '';
			foreach ( $toTitles as $i => $title ) {
				if ( $i > 0 ) {
					$titlesStr .= ', ';
				}
				$titlesStr .= Linker::link( $title );
			}
			$text = self::$mSuccessMessage . $titlesStr . '.';
		}

		$out->addHTML( $text );
	}

}

<?php

/**
 * @author Yaron Koren
 */

class MintyDocsPublish extends SpecialPage {

	protected static $mFromNamespace = MD_NS_DRAFT;
	protected static $mToNamespace = NS_MAIN;
	protected static $mSinglePageMessage = "Publish this draft page?";
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


		if ( $query == null ) {
			$out->addHTML('Page name must be set.');
			return;
		}

		// Generate Title
		if ( $query instanceof Title ) {
			$title = $query;
		} else {
			$title = Title::newFromText( $query );
		}

		// Error if this is not in the Draft namespace
		if ( $title->getNamespace() != self::$mFromNamespace ) {
			$out->addHTML('Must be a Draft page!');
			return;
		}

		// Error if page does not exist
		if ( !$title->exists() ) {
			$out->addHTML('Page does not exist!');
			return;
		}

		// display checkbox
		$text = '<form id="mdPublishForm" action="" method="post">';
		$mdPage = MintyDocsUtils::pageFactory( $title );
		$isSinglePage = ( $mdPage == null || $mdPage instanceof MintyDocsTopic );
		if ( $isSinglePage ) {
			$toTitle = Title::newFromText( $title->getText(), self::$mToNamespace );
			if ( self::$mToNamespace == MD_NS_DRAFT ) {
				if ( $toTitle->exists() ) {
					$out->addHTML( 'A draft cannot be created for this page - it already exists.' );
					return;
				}
			} elseif ( $toTitle->exists() ) {
				$fromPage = WikiPage::factory( $title );
				$fromPageText = $fromPage->getContent()->getNativeData();
				$toPage = WikiPage::factory( $toTitle );
				$toPageText = $toPage->getContent()->getNativeData();
				if ( $fromPageText == $toPageText ) {
					$out->addHTML( 'There is no need to publish this page - the live version matches the draft version.' );
					return;
				}
			}
			$text .= '<p>' . self::$mSinglePageMessage . '</p>';
			$text .= Html::hidden( 'page_name_1', $title->getText() );
		} else {
			$out->addHTML('<p>Pages for ' . $mdPage->getLink() . ':</p>');
			$pagesTree = self::makePagesTree( $mdPage );
			$text .= '<ul>';
			$text .= self::displayCheckboxesForTree( $pagesTree['node'], $pagesTree['tree'] );
			$text .= '</ul>';
		}

		if ( !$isSinglePage && self::$mCheckboxNumber == 1 ) {
			if ( self::$mFromNamespace == MD_NS_DRAFT ) {
				$text .= "<p>(Nothing to publish.)</p>\n";
			} else {
				$text .= "<p>(No drafts need creating.)</p>\n";
			}
			$out->addHTML( $text );
			return;
		}

		$mdp = $this->getTitle();
		$text .= Html::hidden( 'title', PFUtils::titleURLString( $mdp ) );

		$text .= "\t" . Html::hidden( 'csrf', $this->getUser()->getEditToken( $this->getName() ) ) . "\n";

		$text .= Html::element( 'input',
			array(
				'type' => 'submit',
				'name' => 'mdPublish',
				'value' => 'Publish' //wfMessage( 'Pf_createclass_create' )->text()
			)
		);

		$text .= '</form>';
		$out->addHTML( $text );
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

	static function displayCheckboxesForTree( $node, $tree ) {
		$text = '';
		if ( $node != null ) {
			$text .= "\n<li>" . self::displayPageName( $node ) . '</li>';
		}
		if ( count( $tree ) > 0 ) {
			$text .= '<ul>';
			foreach ( $tree as $node ) {
				$innerNode = $node['node'];
				$innerTree = $node['tree'];
				$text .= self::displayCheckboxesForTree( $innerNode, $innerTree );
			}
			$text .= '</ul>';
		}
		return $text;
	}

	static function displayPageName( $mdPage ) {
		$fromTitle = $mdPage->getTitle();
		$fromPageName = $fromTitle->getText();
		$fromPage = WikiPage::factory( $fromTitle );
		$fromPageText = $fromPage->getContent()->getNativeData();
		$toTitle = Title::newFromText( $fromTitle->getText(), self::$mToNamespace );
		if ( !$toTitle->exists() ) {
			return Html::check( 'page_name_' . self::$mCheckboxNumber++, true, array( 'value' => $fromPageName ) ) . '<strong>' . $mdPage->getLink() . '</strong>';
		}
		// Different rules for creating draft vs. publishing.
		if ( $toTitle->exists() && self::$mToNamespace == MD_NS_DRAFT ) {
			return $mdPage->getLink() . ' (already exists)';
		}
		$toPage = WikiPage::factory( $toTitle );
		$toPageText = $toPage->getContent()->getNativeData();
		// If the text of the two pages is the same, no point
		// dislaying a checkbox.
		if ( $fromPageText == $toPageText ) {
			return $mdPage->getLink() . ' (no change)';
		}
		return Html::check( 'page_name_' . self::$mCheckboxNumber++, true, array( 'value' => $fromPageName ) ) . $mdPage->getLink();
	}

	function publishAll() {
		$req = $this->getRequest();
		$user = $this->getUser();
		$out = $this->getOutput();

		if ( self::$mFromNamespace == MD_NS_DRAFT ) {
			$editSummary = 'Published';
		} else {
			$editSummary = 'Created draft';
		}

		$jobs = array();

		$submittedValues = $req->getValues();

		foreach( $submittedValues as $key => $val ) {
			if ( substr( $key, 0, 10 ) != 'page_name_' ) {
				continue;
			}

			$fromPageName = $val;
			$fromTitle = Title::makeTitleSafe( self::$mFromNamespace, $fromPageName );
			$fromPage = WikiPage::factory( $fromTitle );
			$fromPageText = $fromPage->getContent()->getNativeData();
			$toTitle = Title::makeTitleSafe( self::$mToNamespace, $fromPageName );
			$params = array();
			$params['user_id'] = $user->getId();
			$params['page_text'] = $fromPageText;
			$params['edit_summary'] = $editSummary;
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
			if ( self::$mFromNamespace == MD_NS_DRAFT ) {
				$text = 'The specified pages will be created or modified.';
			} else {
				$text = 'The specified draft pages will be created.';
			}
		}

		$out->addHTML( $text );
	}

}

<?php

use MediaWiki\MediaWikiServices;

/**
 * @author Yaron Koren
 */

class MintyDocsDelete extends UnlistedSpecialPage {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'MintyDocsDelete' );
	}

	function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$req = $this->getRequest();

		if ( $query == null ) {
			$out->addHTML( 'Page name must be set.' );
			return;
		}

		// Generate Title
		if ( $query instanceof Title ) {
			$title = $query;
		} else {
			$title = Title::newFromText( $query );
		}

		// Error if page does not exist
		if ( !$title->exists() ) {
			$out->addHTML( 'Page does not exist!' );
			return;
		}

		$mdPage = MintyDocsUtils::pageFactory( $title );
		// For now, this only supports deleting manuals.
		if ( $mdPage == null || $mdPage->getPageTypeValue() !== 'Manual' ) {
			$out->addHTML( 'Page must be a MintyDocs manual.' );
			return;
		}

		// Check permissions.
		if ( !$mdPage->userCanAdminister( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}

		$delete = $req->getCheck( 'mdDelete' );
		if ( $delete ) {
			// Guard against cross-site request forgeries (CSRF).
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), $this->getName() );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling.";
				$out->addHTML( $text );
				return;
			}

			$this->deleteAll( $title );
			return;
		}

		$text = '<form id="mdDeleteForm" action="" method="post">';
		$out->addHTML( '<p>The following pages will be deleted:</p>' );
		$pagesTree = MintyDocsPublish::makePagesTree( $mdPage );
		$text .= Html::rawElement( 'ul', null, self::displayTree( $pagesTree['node'], $pagesTree['tree'] ) );

		$titleString = MintyDocsUtils::titleURLString( $this->getPageTitle() );
		$text .= Html::hidden( 'title', $titleString ) . "\n";
		$text .= Html::hidden( 'csrf', $this->getUser()->getEditToken( $this->getName() ) ) . "\n";
		$text .= Html::input( 'mdDelete', $this->msg( 'delete' )->parse(), 'submit' );

		$text .= '</form>';
		$out->addHTML( $text );
	}

	static function displayTree( $node, $tree ) {
		$text = '';
		// Skip blank nodes, or nodes that are just text.
		if ( $node instanceof MintyDocsPage ) {
			$text .= "\n<li>" . $node->getLink() . '</li>';
		}
		if ( count( $tree ) > 0 ) {
			$text .= '<ul>';
			foreach ( $tree as $node ) {
				$innerNode = $node['node'];
				$innerTree = $node['tree'];
				$text .= self::displayTree( $innerNode, $innerTree );
			}
			$text .= '</ul>';
		}
		return $text;
	}

	function deleteAll( $title ) {
		$user = $this->getUser();
		$out = $this->getOutput();

		$mdPage = MintyDocsUtils::pageFactory( $title );

		$jobs = [];

		$params = [
			'user_id' => $user->getId(),
			'deletion_reason' => $this->msg( 'mintydocs-delete-deletionreason' )->inContentLanguage()->text()
		];

		$jobs[] = new MintyDocsDeletePageJob( $title, $params );
		// If this special page ever supports deleting anything other
		// than manuals (i.e. versions or products), this code will
		// have to become slightly more complex.
		foreach ( $mdPage->getChildrenPages() as $child ) {
			$jobs[] = new MintyDocsDeletePageJob( $child, $params );
		}

		if ( method_exists( MediaWikiServices::class, 'getJobQueueGroup' ) ) {
			// MW 1.37+
			MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobs );
		} else {
			JobQueueGroup::singleton()->push( $jobs );
		}

		$text = $this->msg( 'mintydocs-delete-success' )->parse();

		$out->addHTML( $text );
	}

}

<?php

use MediaWiki\MediaWikiServices;

/**
 * Job to delete a page, for use by Special:MintyDocsDelete.
 *
 * @author Yaron Koren
 */
class MintyDocsDeletePageJob extends Job {

	/**
	 * @param Title $title
	 * @param array $params
	 * @param int $id
	 */
	function __construct( $title, $params = '', $id = 0 ) {
		parent::__construct( 'MDDeletePage', $title, $params, $id );
	}

	/**
	 * Run an MDDeletePage job.
	 *
	 * @return bool success
	 */
	function run() {
		if ( $this->title === null ) {
			$this->error = "MDDeletePage: Invalid title";
			return false;
		}

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->title );
		if ( !$wikiPage ) {
			$this->error = 'MDDeletePage: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		$user = User::newFromId( $this->params['user_id'] );
		$deletionReason = '';
		if ( array_key_exists( 'deletion_reason', $this->params ) ) {
			$deletionReason = $this->params['deletion_reason'];
		}

		$error = '';
		$wikiPage->doDeleteArticleReal( $deletionReason, $user, false, null, $error );
		if ( $error != '' ) {
			$this->error = 'MDDeletePage: ' . $error;
			return false;
		}

		return true;
	}
}

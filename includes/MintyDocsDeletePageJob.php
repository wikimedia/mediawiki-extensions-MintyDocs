<?php

/**
 * Job to delete a page, for use by Special:MintyDocsDelete.
 *
 * @author Yaron Koren
 */
class MintyDocsDeletePageJob extends Job {

	function __construct( $title, $params = '', $id = 0 ) {
		parent::__construct( 'MDDeletePage', $title, $params, $id );
	}

	/**
	 * Run an MDDeletePage job.
	 *
	 * @return bool success
	 */
	function run() {
		if ( is_null( $this->title ) ) {
			$this->error = "MDDeletePage: Invalid title";
			return false;
		}

		$wikiPage = new WikiPage( $this->title );
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
		if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
			$wikiPage->doDeleteArticle( $deletionReason, false, null, null, $error, $user );
		} else {
			$wikiPage->doDeleteArticleReal( $deletionReason, $user, false, null, $error );
		}
		if ( $error != '' ) {
			$this->error = 'MDDeletePage: ' . $error;
			return false;
		}

		return true;
	}
}
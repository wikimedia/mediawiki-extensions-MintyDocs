<?php

/**
 * Job to create or modify a page, for use by Special:MintyDocsPublish.
 *
 * @author Yaron Koren
 */
class MintyDocsCreatePageJob extends Job {

	/**
	 * @param Title $title
	 * @param array $params
	 * @param int $id
	 */
	function __construct( $title, $params = '', $id = 0 ) {
		parent::__construct( 'MDCreatePage', $title, $params, $id );
	}

	/**
	 * Run a createPage job
	 * @return bool success
	 */
	function run() {
		// If a page is supposed to have a parent but doesn't, we
		// don't want to save it, because that would lead to an
		// invalid page.
		if ( array_key_exists( 'parent_page', $this->params ) ) {
			$parent_page = $this->params['parent_page'];
			$parent_title = Title::newFromText( $parent_page );
			if ( !$parent_title->exists() ) {
				$this->error = "MDCreatePage: Parent page is missing; canceling save.";
				return false;
			}
		}

		$pageText = $this->params['page_text'];
		$editSummary = '';
		if ( array_key_exists( 'edit_summary', $this->params ) ) {
			$editSummary = $this->params['edit_summary'];
		}
		$userID = $this->params['user_id'];
		try {
			MintyDocsUtils::createOrModifyPage( $this->title, $pageText, $editSummary, $userID );
		} catch ( MWException $e ) {
			$this->error = 'MDCreatePage: ' . $e->getMessage();
			return false;
		}

		return true;
	}
}

<?php

/**
 * Job to create or modify a page, for use by Special:MintyDocsPublish.
 *
 * @author Yaron Koren
 */
class MintyDocsCreatePageJob extends Job {

	function __construct( $title, $params = '', $id = 0 ) {
		parent::__construct( 'MDCreatePage', $title, $params, $id );
	}

	/**
	 * Run a createPage job
	 * @return bool success
	 */
	function run() {
		if ( is_null( $this->title ) ) {
			$this->error = "MDCreatePage: Invalid title";
			return false;
		}

		$wikiPage = new WikiPage( $this->title );
		if ( !$wikiPage ) {
			$this->error = 'MDCreatePage: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		// If a page is supposed to have a parent but doesn't, we
		// don't want to save it, because that would lead to an
		// invalid page.
		if ( array_key_exists( 'parent_page', $this->params ) ) {
			$parent_page = $this->params['parent_page'];
			$parent_title = Title::newFromText( $parent_page );
			if ( ! $parent_title->exists() ) {
				$this->error = "MDCreatePage: Parent page is missing; canceling save.";
				return false;
			}
		}

		$page_text = $this->params['page_text'];
		// Change global $wgUser variable to the one
		// specified by the job only for the extent of this
		// replacement.
		global $wgUser;
		$actual_user = $wgUser;
		$wgUser = User::newFromId( $this->params['user_id'] );
		$edit_summary = '';
		if ( array_key_exists( 'edit_summary', $this->params ) ) {
			$edit_summary = $this->params['edit_summary'];
		}

		// It's strange that doEditContent() doesn't
		// automatically attach the 'bot' flag when the user
		// is a bot...
		if ( $wgUser->isAllowed( 'bot' ) ) {
			$flags = EDIT_FORCE_BOT;
		} else {
			$flags = 0;
		}

		$new_content = new WikitextContent( $page_text );
		$wikiPage->doEditContent( $new_content, $edit_summary, $flags );

		$wgUser = $actual_user;
		return true;
	}
}

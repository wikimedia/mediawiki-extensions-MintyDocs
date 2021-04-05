<?php

/**
 * Handles the 'recreatedata' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class MintyDocsPublishAction extends Action {
	/**
	 * Return the name of the action this object responds to
	 * @return string lowercase
	 */
	public function getName() {
		return 'mdpublish';
	}

	/**
	 * The main action entry point. Do all output for display and send it
	 * to the context output.
	 * $this->getOutput(), etc.
	 */
	public function show() {
		$title = $this->page->getTitle();

		$mdPublishPage = new MintyDocsPublish();
		$mdPublishPage->execute( $title );
	}

	/**
	 * Adds an "action" (i.e., a tab) to publish a page.
	 *
	 * @param Title $obj
	 * @param array &$links
	 * @return bool
	 */
	static function displayTab( $obj, &$links ) {
		$title = $obj->getTitle();
		// Draft pages only.
		if ( !$title || !$title->exists() || $title->getNamespace() !== MD_NS_DRAFT ) {
			return true;
		}

		$mdPage = MintyDocsUtils::pageFactory( $title );
		$user = $obj->getUser();
		if ( !$mdPage->userCanAdminister( $user ) ) {
			return true;
		}

		$request = $obj->getRequest();

		$mdPublishTab = [
			'class' => ( $request->getVal( 'action' ) == 'mdpublish' ) ? 'selected' : '',
			'text' => wfMessage( 'mintydocs-publish-button' ),
			'href' => $title->getLocalURL( 'action=mdpublish' )
		];

		$links['views']['mdpublish'] = $mdPublishTab;

		return true;
	}

}

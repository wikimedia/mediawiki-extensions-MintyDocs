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
		$title = $this->getTitle();

		$mdPublishPage = new MintyDocsPublish();
		$mdPublishPage->execute( $title );
	}

	/**
	 * Adds an "action" (i.e., a tab) to publish a page.
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 * @return bool
	 */
	static function displayTab( SkinTemplate $skinTemplate, array &$links ) {
		$title = $skinTemplate->getTitle();
		// Draft pages only.
		if ( !$title || !$title->exists() || $title->getNamespace() !== MD_NS_DRAFT ) {
			return true;
		}

		// MintyDocs pages only.
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( !$mdPage ) {
			return true;
		}

		$user = $skinTemplate->getUser();
		if ( !$user || !$mdPage->userCanAdminister( $user ) ) {
			return true;
		}

		$request = $skinTemplate->getRequest();

		$mdPublishTab = [
			'class' => ( $request->getVal( 'action' ) == 'mdpublish' ) ? 'selected' : '',
			'text' => wfMessage( 'mintydocs-publish-button' )->escaped(),
			'href' => $title->getLocalURL( 'action=mdpublish' )
		];

		$links['views']['mdpublish'] = $mdPublishTab;

		return true;
	}

}

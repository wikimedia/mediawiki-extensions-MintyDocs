<?php

use MediaWiki\MediaWikiServices;

/**
 * Handles the 'recreatedata' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class MintyDocsPublishAction extends Action {
	/**
	 * Return the name of the action this object responds to
	 * @return String lowercase
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

		$user = $obj->getUser();
		if ( method_exists( 'MediaWiki\Permissions\PermissionManager', 'userCan' ) ) {
			// MW 1.33+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			if ( !$permissionManager->userCan( 'mintydocs-administer', $user, $title ) ) {
				return true;
			}
		} else {
			if ( !$title->userCan( 'mintydocs-administer', $user ) ) {
				return true;
			}
		}

		$request = $obj->getRequest();

		$mdPublishTab = array(
			'class' => ( $request->getVal( 'action' ) == 'mdpublish' ) ? 'selected' : '',
			'text' => wfMessage( 'mintydocs-publish-button' ),
			'href' => $title->getLocalURL( 'action=mdpublish' )
		);

		$links['views']['mdpublish'] = $mdPublishTab;

		return true;
	}

}

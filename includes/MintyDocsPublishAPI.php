<?php

use MediaWiki\MediaWikiServices;

/**
 * @ingroup MintyDocs
 */
class MintyDocsPublishAPI extends ApiBase {

	/**
	 * Evaluates the parameters, performs the requested API query, and sets up
	 * the result.
	 *
	 * The execute() method will be invoked when an API call is processed.
	 *
	 * The result data is stored in the ApiResult object available through
	 * getResult().
	 */
	function execute() {
		$params = $this->extractRequestParams();

		$pageName = $params['title'];

		// We need to do this to get MD_NS_DRAFT defined.
		$dummyArray = [];
		MintyDocsHooks::registerNamespaces( $dummyArray );

		$fromTitle = Title::newFromText( $pageName, MD_NS_DRAFT );
		if ( !$fromTitle || $fromTitle->isExternal() ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $pageName ) ] );
		}
		if ( !$fromTitle->canExist() ) {
			$this->dieWithError( 'apierror-pagecannotexist' );
		}
		if ( !$fromTitle->exists() ) {
			$this->dieWithError( 'apierror-missingtitle' );
		}

		$fromMDPage = MintyDocsUtils::pageFactory( $fromTitle );
		$user = $this->getUser();
		if ( $fromMDPage->userCanPublish( $user ) ) {
			$this->dieWithError( [ 'apierror-permissiondenied', $this->msg( "action-mintydocs-administer" ) ] );
		}

		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$fromPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $fromTitle );
		} else {
			$fromPage = WikiPage::factory( $fromTitle );
		}
		$fromPageText = $fromPage->getContent()->getText();

		$toTitle = Title::newFromText( $pageName );

		$editSummary = 'Published';

		try {
			MintyDocsUtils::createOrModifyPage( $toTitle, $fromPageText, $editSummary, $user );
		} catch ( MWException $e ) {
			$this->dieWithError( $e->getMessage() );
		}

		$result = $this->getResult();
		$result->addValue( [ 'mintydocspublish' ], 'status', 'success' );
	}

	/**
	 * Indicates whether this module requires write mode
	 * @return bool
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * Returns the array of allowed parameters (parameter name) => (default
	 * value) or (parameter name) => (array with PARAM_* constants as keys)
	 * Don't call this function directly: use getFinalParams() to allow
	 * hooks to modify parameters as needed.
	 *
	 * @return array or false
	 */
	function getAllowedParams() {
		return [
			'title' => null
		];
	}

	/**
	 * Returns usage examples for this module.
	 *
	 * @return string[]
	 */
	protected function getExamplesMessages() {
		return [
			'action=mintydocspublish&title=ABC123' => 'apihelp-mintydocspublish-example-title'
		];
	}

}

<?php

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
		if ( !$this->getUser()->isAllowed( 'mintydocs-administer' ) ) {
			$this->mintyDocsDie( [ 'apierror-permissiondenied', $this->msg( "action-mintydocs-administer" ) ] );
		}

		$params = $this->extractRequestParams();

		$pageName = $params['title'];

		// We need to do this to get MD_NS_DRAFT defined.
		$dummyArray = [];
		MintyDocsHooks::registerNamespaces( $dummyArray );

		$fromTitle = Title::newFromText( $pageName, MD_NS_DRAFT );
		if ( !$fromTitle || $fromTitle->isExternal() ) {
			$this->mintyDocsDie( [ 'apierror-invalidtitle', wfEscapeWikiText( $pageName ) ] );
		}
		if ( !$fromTitle->canExist() ) {
			$this->mintyDocsDie( 'apierror-pagecannotexist' );
		}
		if ( !$fromTitle->exists() ) {
			$this->mintyDocsDie( 'apierror-missingtitle' );
		}

		$fromPage = WikiPage::factory( $fromTitle );
		$fromPageText = $fromPage->getContent()->getNativeData();

		$toTitle = Title::newFromText( $pageName );

		$editSummary = 'Published';

		try {
			MintyDocsUtils::createOrModifyPage( $toTitle, $fromPageText, $editSummary );
		} catch ( MWException $e ) {
			$this->mintyDocsDie( $e->getMessage() );
		}

		$result = $this->getResult();
		$result->addValue( [ 'mintydocspublish' ], 'status', 'success' );
	}

	/**
	 * @param string $text
	 */
	function mintyDocsDie( $text ) {
		if ( method_exists( $this, 'dieWithError' ) ) {
			// MW 1.29+
			$this->dieWithError( $text );
		} else {
			$this->dieUsage( $text );
		}
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

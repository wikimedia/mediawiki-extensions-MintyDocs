<?php

use MediaWiki\Html\Html;
use MediaWiki\Title\Title;

class MintyDocsCopy extends MintyDocsPublish {

	private $mParentTitle;
	private $mTargetProduct;
	private $mTargetVersion;
	private $mTargetManual;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'MintyDocsCopy' );

		self::$mNoActionNeededMessage = "None of the pages in this manual need to be copied.";
		self::$mEditSummaryMsg = "mintydocs-copy-editsummary";
		self::$mSuccessMsg = "mintydocs-copy-success";
		self::$mSinglePageMsg = "mintydocs-copy-singlepage";
		self::$mButtonMsg = "mintydocs-copy-button";
	}

	function execute( $query ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$req = $this->getRequest();

		// Check permissions.
		if ( !$this->getUser()->isAllowed( 'mintydocs-administer' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$setVersion = $req->getCheck( 'mdSetVersion' );
		$publish = $req->getCheck( 'mdPublish' );
		if ( $setVersion || $publish ) {
			// Guard against cross-site request forgeries (CSRF).
			$validToken = $this->getUser()->matchEditToken( $req->getVal( 'csrf' ), $this->getName() );
			if ( !$validToken ) {
				$text = "This appears to be a cross-site request forgery; canceling.";
				$out->addHTML( $text );
				return;
			}
		}

		try {
			$title = $this->getTitleFromQuery( $query );
		} catch ( Exception $e ) {
			$out->addHTML( $e->getMessage() );
			return;
		}
		$this->mParentTitle = $title;

		$this->mTargetProduct = $req->getVal( 'target_product' );
		$this->mTargetVersion = $req->getVal( 'target_version' );
		$this->mTargetManual = $req->getVal( 'target_manual' );

		if ( $publish ) {
			$this->publishAll();
			return;
		}

		if ( $setVersion || ( $this->mTargetVersion && $this->mTargetProduct ) ) {
			$this->displayMainForm( $title );
			return;
		}

		$this->displayVersionSelector( $title );
	}

	function displayVersionSelector( $title ) {
		$out = $this->getOutput();
		$out->enableOOUI();

		$mdPage = MintyDocsUtils::pageFactory( $title );
		[ $productStr, $versionStr ] = $mdPage->getProductAndVersionStrings();
		$productPage = Title::newFromText( $productStr );
		$product = new MintyDocsProduct( $productPage );
		$versions = $product->getChildrenPages();
		$versionStrings = [];
		foreach ( $versions as $versionTitle ) {
			[ $curProductStr, $curVersionStr ] = explode( '/', $versionTitle->getText() );
			if ( $curVersionStr !== $versionStr ) {
				$versionStrings[] = $curVersionStr;
			}
		}

		$options = [];
		foreach ( $versionStrings as $curVersionStr ) {
			$options[] = [ 'data' => $curVersionStr ];
		}

		$text = Html::hidden( 'csrf', $this->getUser()->getEditToken( $this->getName() ) ) . "\n";
		$dropdown = new OOUI\DropdownInputWidget(
			[
				'options' => $options,
				'name' => 'target_version'
			]
		);
		$text .= new OOUI\FieldLayout(
			$dropdown,
			[
				'align' => 'inline',
				'label' => 'Select version to copy pages to:'
			]
		);
		$text .= "<br /><br />\n" . new OOUI\ButtonInputWidget(
			[
				'name' => 'mdSetVersion',
				'type' => 'submit',
				'flags' => 'progressive',
				'label' => $this->msg( 'apisandbox-continue' )->parse()
			]
		);
		$text = Html::rawElement( 'form', [ 'method' => 'post' ], $text );

		$out->addHtml( $text );
	}

	function displayPageParents( $mdPage ) {
		$targetTitle = $this->generateTargetTitle( $mdPage->getTitle()->getText() );
		$targetLink = $this->getLinkRenderer()->makeLink( $targetTitle );
		$text = Html::rawElement(
			'p',
			[],
			"These will be copied to the location $targetLink."
		);
		$text .= "\n" . Html::hidden( 'target_version', $this->mTargetVersion );
		return $text;
	}

	function generateSourceTitle( $sourcePageName ) {
		return Title::newFromText( $sourcePageName, $this->mParentTitle->getNamespace() );
	}

	function generateTargetTitle( $targetPageName ) {
		$pageElements = explode( '/', $targetPageName, 4 );
		if ( count( $pageElements ) == 4 ) {
			[ $product, $version, $manual, $topic ] = $pageElements;
			// These two checks are probably not necessary - setting
			// a target product and manual name may only ever be
			// applicable to copying manuals, not topics. Doesn't
			// hurt to check, though.
			if ( $this->mTargetProduct ) {
				$product = $this->mTargetProduct;
			}
			if ( $this->mTargetManual ) {
				$manual = $this->mTargetManual;
			}
			$targetPageName = "$product/" . $this->mTargetVersion . "/$manual/$topic";
		} elseif ( count( $pageElements ) == 3 ) {
			[ $product, $version, $manual ] = $pageElements;
			if ( $this->mTargetProduct ) {
				$product = $this->mTargetProduct;
			}
			if ( $this->mTargetManual ) {
				$manual = $this->mTargetManual;
			}
			$targetPageName = "$product/" . $this->mTargetVersion . "/$manual";
		} else {
			// Probably 2 - product and version. We just need the product.
			$product = $pageElements[0];
			if ( $this->mTargetProduct ) {
				$product = $this->mTargetProduct;
			}
			$targetPageName = "$product/" . $this->mTargetVersion;
		}
		return Title::newFromText( $targetPageName, $this->mParentTitle->getNamespace() );
	}

	function generateParentTargetTitle( $fromParentTitle ) {
		$fromParentPageName = $fromParentTitle->getFullText();
		return $this->generateTargetTitle( $fromParentPageName );
	}

	function overwritingIsAllowed() {
		return true;
	}

	function validateTitle( $title ) {
		$mdPage = MintyDocsUtils::pageFactory( $title );
		if ( !$mdPage instanceof MintyDocsManual ) {
			throw new MWException( 'Page must be a MintyDocs manual.' );
		}
	}

}

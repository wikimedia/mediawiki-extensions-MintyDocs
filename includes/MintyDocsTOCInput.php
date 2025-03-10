<?php

use MediaWiki\Html\Html;

/**
 * @file
 * @ingroup PF
 */

class MintyDocsTOCInput extends PFFormInput {

	/**
	 * @return string
	 */
	public static function getName() {
		return 'mintydocs toc';
	}

	/**
	 * @return null|string|array
	 */
	public function getResourceModuleNames() {
		return [ 'ext.mintydocs.jstree' ];
	}

	/**
	 * @return string[]
	 */
	public static function getOtherPropTypesHandled() {
		return [ '_txt' ];
	}

	/**
	 * @return array
	 */
	public static function getOtherCargoTypesHandled() {
		return [ 'Text' ];
	}

	/**
	 * @param string $cur_value
	 * @param string $input_name
	 * @param bool $is_mandatory
	 * @param bool $is_disabled
	 * @param array $other_args
	 * @return string
	 */
	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		$nodeNum = 1;
		$treeData = [];
		$lines = explode( "\n", $cur_value );
		foreach ( $lines as $line ) {
			preg_match( "/^(\*+)/", $line, $matches );
			if ( count( $matches ) < 2 ) {
				continue;
			}
			$asterisks = $matches[1];
			$levelNum = strlen( $asterisks );
			$line = trim( substr( $line, $levelNum ) );
			$firstChar = $line[0];
			$nodeType = 'page';
			if ( $firstChar == '!' ) {
				$nodeType = 'standalone';
				$line = trim( substr( $line, 1 ) );
			} elseif ( $firstChar == '+' ) {
				$nodeType = 'borrowed';
				$line = trim( substr( $line, 1 ) );
			} elseif ( $firstChar == '-' ) {
				$nodeType = 'text';
				$line = trim( substr( $line, 1 ) );
			}
			if ( $levelNum == 1 ) {
				$parentID = '#';
			} else {
				$parentID = $lastIDForLevel[$levelNum - 1];
			}
			$curID = 'node' . $nodeNum++;
			$treeData[] = [ 'id' => $curID, 'parent' => $parentID, 'text' => $line, 'type' => $nodeType ];
			$lastIDForLevel[$levelNum] = $curID;
		}

		$mainDiv = Html::element( 'div', [
			'class' => 'MintyDocsTOC',
			'data-input-name' => $input_name,
			'data-tree-layout' => json_encode( $treeData ),
			'style' => 'margin: 1em 0; min-height: 200px;'
		] );
		$buttonAttrs = [
			'label' => wfMessage( 'mintydocs-tocinput-addentry' )->parse(),
			'icon' => 'add'
		];
		$button = new OOUI\ButtonWidget( $buttonAttrs );
		return Html::rawElement( 'div', null, $mainDiv . "\n" . $button );
	}

	/**
	 * @return array[]
	 */
	public static function getParameters() {
		$params = [];
		$params[] = [
			'name' => 'mandatory',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_mandatory' )->text()
		];
		$params[] = [
			'name' => 'restricted',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_restricted' )->text()
		];
		$params[] = [
			'name' => 'class',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_class' )->text()
		];
		$params[] = [
			'name' => 'default',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_default' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText() {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}

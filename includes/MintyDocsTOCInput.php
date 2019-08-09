<?php
/**
 * @file
 * @ingroup PF
 */

class MintyDocsTOCInput extends PFFormInput {
	public static function getName() {
		return 'mintydocs toc';
	}

	public function getResourceModuleNames() {
		return array( 'ext.mintydocs.jstree' );
	}

	public static function getOtherPropTypesHandled() {
		return array( '_txt' );
	}

	public static function getOtherCargoTypesHandled() {
		return array( 'Text' );
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		$nodeNum = 1;
		$treeData = array();
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
			$treeData[] = array( 'id' => $curID, 'parent' => $parentID, 'text' => $line, 'type' => $nodeType );
			$lastIDForLevel[$levelNum] = $curID;
		}

		$mainDiv = Html::element( 'div', array(
			'class' => 'MintyDocsTOC',
			'data-input-name' => $input_name,
			'data-tree-layout' => json_encode( $treeData ),
			'style' => 'margin-top:1em; min-height:200px;'
		) );
		$button = Html::element( 'button', array( 'type' => 'button' ), 'Add entry' );
		return Html::rawElement( 'div', null, $mainDiv . "\n" . $button );
	}

	public static function getParameters() {
		$params = array();
		$params[] = array(
			'name' => 'mandatory',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_mandatory' )->text()
		);
		$params[] = array(
			'name' => 'restricted',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_restricted' )->text()
		);
		$params[] = array(
			'name' => 'class',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_class' )->text()
		);
		$params[] = array(
			'name' => 'default',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_default' )->text()
		);
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

( function ( $, mw ) {

	var mdImagesPath = mw.config.get( 'wgMintyDocsScriptPath' ) + '/images/';

	$('.MintyDocsTOC').each(function() {
		var tree = $(this);
		var treeData = JSON.parse( tree.attr( 'data-tree-layout' ) );
		tree.jstree({
			"core" : {
				"animation" : 0,
				"check_callback" : true,
				"dblclick_toggle" : false,
				'force_text' : true,
				"themes" : { "stripes" : true },
				"strings": {
					'New node' : 'New entry'
				},
				'data' : treeData
			},
			"types" : {
				"default" : { "icon" : mdImagesPath + "MD-page-icon.png" },
				"standalone" : { "icon": mdImagesPath + "MD-standalone-icon.png" },
				"borrowed" : { "icon": mdImagesPath + "MD-borrowed-icon.png" },
				"text" : { "icon": mdImagesPath + "MD-text-icon.png" }
			},
			"plugins" : [ "contextmenu", "dnd", "types", "wholerow" ]
		}).bind("loaded.jstree", function (event, data) {
			// Open the entire tree - this makes for a more useful
			// display, and it's necessary when submitting the
			// form, because otherwise the unopened nodes don't
			// get loaded in time.
			tree.jstree("open_all");
		}).bind("dblclick.jstree", function (event) {
			// Change double-clicking to edit a node's name,
			// rather than opening/closing it.
			var actualTree = tree.jstree();
			var node = actualTree.get_node(event.target);
			actualTree.edit(node);
		});
		tree.siblings('button').click(function () {
			tree.jstree("create_node", null, null, "last", function (node) {
				this.edit(node);
			});
		});
	});

	// Recursive function.
	function getWikitextForNodeAndChildren( tree, nodeID, levelNum ) {
		var wikitext = '';
		var node = tree.get_node(nodeID);
		if ( nodeID != '#' ) {
			wikitext += '*'.repeat(levelNum) + ' ';
			if ( node.type == 'standalone' ) {
				wikitext += '!';
			} else if ( node.type == 'borrowed' ) {
				wikitext += '+';
			} else if ( node.type == 'text' ) {
				wikitext += '-';
			}
			wikitext += node.text + "\n";
		}
		var children = tree.get_node(nodeID).children;
		var numChildren = children.length;
		for ( var i = 0; i < numChildren; i++ ) {
			wikitext += getWikitextForNodeAndChildren( tree, children[i], levelNum + 1 );
		}
		return wikitext;
	}

	// When form is submitted, add a hidden input for each tree, so that
	// Page Forms can get the data.
	$( "#pfForm" ).submit(function( event ) {
		$('.MintyDocsTOC').each(function() {
			var tree = $(this).jstree(true);
			var wikitext = getWikitextForNodeAndChildren( tree, '#', 0 );
			var inputName = $(this).attr( 'data-input-name' );
			$('<input>').attr( 'type', 'hidden' ).attr( 'name', inputName ).attr( 'value', wikitext ).appendTo( '#pfForm' );
		});
	});

}( jQuery, mediaWiki ) );

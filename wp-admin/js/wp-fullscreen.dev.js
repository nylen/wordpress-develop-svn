/**
 * PubSub
 *
 * A lightweight publish/subscribe implementation.
 * Private use only!
 */
var PubSub, fullscreen, wptitlehint;

PubSub = function() {
	this.topics = {};
};

PubSub.prototype.subscribe = function( topic, callback ) {
	if ( ! this.topics[ topic ] )
		this.topics[ topic ] = [];

	this.topics[ topic ].push( callback );
	return callback;
};

PubSub.prototype.unsubscribe = function( topic, callback ) {
	var i, l,
		topics = this.topics[ topic ];

	if ( ! topics )
		return callback || [];

	// Clear matching callbacks
	if ( callback ) {
		for ( i = 0, l = topics.length; i < l; i++ ) {
			if ( callback == topics[i] )
				topics.splice( i, 1 );
		}
		return callback;

	// Clear all callbacks
	} else {
		this.topics[ topic ] = [];
		return topics;
	}
};

PubSub.prototype.publish = function( topic, args ) {
	var i, l, broken,
		topics = this.topics[ topic ];

	if ( ! topics )
		return;

	args = args || [];

	for ( i = 0, l = topics.length; i < l; i++ ) {
		broken = ( topics[i].apply( null, args ) === false || broken );
	}
	return ! broken;
};

/**
 * Distraction Free Writing
 * (wp-fullscreen)
 *
 * Access the API globally using the fullscreen variable.
 */

(function($){
	var api, ps, bounder, s;

	// Initialize the fullscreen/api object
	fullscreen = api = {};

	// Create the PubSub (publish/subscribe) interface.
	ps = api.pubsub = new PubSub();
	timer = 0;
	block = false;

	s = api.settings = { // Settings
		visible : false,
		mode : 'tinymce',
		editor_id : 'content',
		title_id : 'title',
		timer : 0
	}

	/**
	 * Bounder
	 *
	 * Creates a function that publishes start/stop topics.
	 * Used to throttle events.
	 */
	bounder = function( start, stop, delay ) {
		delay = delay || 1250;

		if ( block )
			return;

		block = true;

		setTimeout( function() {
			block = false;
		}, 400 );

		if ( s.timer )
			clearTimeout( s.timer );
		else
			ps.publish( start );

		function timed() {
			ps.publish( stop );
			s.timer = 0;
		}

		s.timer = setTimeout( timed, delay );
	};

	/**
	 * on()
	 *
	 * Turns fullscreen on.
	 *
	 * @param string mode Optional. Switch to the given mode before opening.
	 */
	api.on = function() {
		if ( s.visible )
			return;

		s.mode = $('#' + s.editor_id).is(':hidden') ? 'tinymce' : 'html';

		if ( ! s.element )
			api.ui.init();

		s.is_mce_on = s.has_tinymce && typeof( tinyMCE.get(s.editor_id) ) != 'undefined';

		api.ui.fade( 'show', 'showing', 'shown' );
	};

	/**
	 * off()
	 *
	 * Turns fullscreen off.
	 */
	api.off = function() {
		if ( ! s.visible )
			return;

		api.ui.fade( 'hide', 'hiding', 'hidden' );
	};

	/**
	 * switchmode()
	 *
	 * @return string - The current mode.
	 *
	 * @param string to - The fullscreen mode to switch to.
	 * @event switchMode
	 * @eventparam string to   - The new mode.
	 * @eventparam string from - The old mode.
	 */
	api.switchmode = function( to ) {
		var from = s.mode;

		if ( ! to || ! s.visible || ! s.has_tinymce )
			return from;

		// Don't switch if the mode is the same.
		if ( from == to )
			return from;

		ps.publish( 'switchMode', [ from, to ] );
		s.mode = to;
		ps.publish( 'switchedMode', [ from, to ] );

		return to;
	};

	/**
	 * General
	 */

	api.save = function() {
		var hidden = $('#hiddenaction'), old = hidden.val(), spinner = $('#wp-fullscreen-save img'),
			message = $('#wp-fullscreen-save span');

		spinner.show();
		api.savecontent();

		hidden.val('wp-fullscreen-save-post');

		$.post( ajaxurl, $('form#post').serialize(), function(r){
			spinner.hide();
			message.show();

			setTimeout( function(){
				message.fadeOut(800);
			}, 3000 );

			if ( r.last_edited )
				$('#wp-fullscreen-save input').attr( 'title',  r.last_edited );

		}, 'json');

		hidden.val(old);
	}

	api.savecontent = function() {
		var ed, content;

		$('#' + s.title_id).val( $('#wp-fullscreen-title').val() );

		if ( s.mode === 'tinymce' && (ed = tinyMCE.get('wp_mce_fullscreen')) ) {
			content = ed.save();
		} else {
			content = $('#wp_mce_fullscreen').val();
		}

		$('#' + s.editor_id).val( content );
	}

	set_title_hint = function( title ) {
		if ( ! title.val().length )
			title.siblings('label').css( 'visibility', '' );
		else
			title.siblings('label').css( 'visibility', 'hidden' );
	}

	ps.subscribe( 'showToolbar', function() {
		api.fade.In( s.topbar, 600, function(){ ps.publish('toolbarShown'); } );
		$('#wp-fullscreen-body').addClass('wp-fullscreen-focus');
	});

	ps.subscribe( 'hideToolbar', function() {
		api.fade.Out( s.topbar, 600, function(){ ps.publish('toolbarHidden'); } );
		$('#wp-fullscreen-body').removeClass('wp-fullscreen-focus');
	});

	ps.subscribe( 'show', function() { // This event occurs before the overlay blocks the UI.
		var title = $('#wp-fullscreen-title').val( $('#' + s.title_id).val() );

		set_title_hint( title );
		$('#wp-fullscreen-save input').attr( 'title',  $('#last-edit').text() );

		s.textarea_obj.value = edCanvas.value;

		if ( s.has_tinymce && s.mode === 'tinymce' )
			tinyMCE.execCommand('wpFullScreenInit');

		s._edCanvas = edCanvas;
		edCanvas = s.textarea_obj;

		s.orig_y = $(window).scrollTop();
	});

	ps.subscribe( 'showing', function() { // This event occurs while the DFW overlay blocks the UI.
		var scrollY = s.mode === 'html' ? 220 + s._edCanvas.scrollTop : 140 + tinyMCE.get(s.editor_id).getBody().scrollTop;
		
		$( document.body ).addClass( 'fullscreen-active' );
		api.refresh_buttons();

		$( document ).bind( 'mousemove.fullscreen', function(e) { bounder( 'showToolbar', 'hideToolbar', 2500 ); } );
		bounder( 'showToolbar', 'hideToolbar', 2500 );

		api.bind_resize();
		setTimeout( api.resize_textarea, 200 );

		if ( scrollY < 171 )
			scrollY = 0;

		scrollTo(0, scrollY);
	});

	ps.subscribe( 'shown', function() { // This event occurs after the DFW overlay is shown
		s.visible = true;

		// init the standard TinyMCE instance if missing
		if ( s.has_tinymce && ! s.is_mce_on ) {
			htmled = document.getElementById(s.editor_id), old_val = htmled.value;

			htmled.value = switchEditors.wpautop( old_val );

			tinyMCE.settings.setup = function(ed) {
				ed.onInit.add(function(ed) {
					ed.hide();
					delete tinyMCE.settings.setup;
					ed.getElement().value = old_val;
				});
			}

			tinyMCE.execCommand("mceAddControl", false, s.editor_id);
			s.is_mce_on = true;
		}
	});

	ps.subscribe( 'hide', function() { // This event occurs before the overlay blocks DFW.

		api.savecontent();
		$( document ).unbind( '.fullscreen' );
		$(s.textarea_obj).unbind('.grow');

		if ( s.has_tinymce && s.mode === 'tinymce' )
			tinyMCE.execCommand('wpFullScreenSave');

		set_title_hint( $('#' + s.title_id) );

		// Restore and update edCanvas.
		edCanvas = s._edCanvas;
		edCanvas.value = s.textarea_obj.value;
	});

	ps.subscribe( 'hiding', function() { // This event occurs while the overlay blocks the DFW UI.

		// Make sure the correct editor is displaying.
		if ( s.has_tinymce && s.mode === 'tinymce' && $('#' + s.editor_id).is(':visible') ) {
			switchEditors.go( s.editor_id, 'tinymce' );
		} else if ( s.mode == 'html' && $('#' + s.editor_id).is(':hidden') ) {
			switchEditors.go( s.editor_id, 'html' );
		}

		$( document.body ).removeClass( 'fullscreen-active' );
		scrollTo(0, s.orig_y);
	});

	ps.subscribe( 'hidden', function() { // This event occurs after DFW is removed.
		s.visible = false;
		$('#wp_mce_fullscreen').removeAttr('style');

		if ( s.has_tinymce && s.is_mce_on )
			tinyMCE.execCommand('wpFullScreenClose');

		s.textarea_obj.value = '';
		api.oldheight = 0;
	});

	ps.subscribe( 'switchMode', function( from, to ) {
		var ed;

		if ( !s.has_tinymce || !s.is_mce_on )
			return;

		ed = tinyMCE.get('wp_mce_fullscreen');

		if ( from === 'html' && to === 'tinymce' ) {
			s.textarea_obj.value = switchEditors.wpautop( s.textarea_obj.value );

			if ( 'undefined' == typeof(ed) )
				tinyMCE.execCommand('wpFullScreenInit');
			else
				ed.show();

		} else if ( from === 'tinymce' && to === 'html' ) {
			if ( ed )
				ed.hide();
		}
	});

	ps.subscribe( 'switchedMode', function( from, to ) {
		api.refresh_buttons();

		if ( to === 'html' )
			setTimeout( api.resize_textarea, 200 );
	});

	/**
	 * Buttons
	 */
	api.b = function() {
		if ( s.has_tinymce && 'tinymce' === s.mode )
			tinyMCE.execCommand('Bold');
	}

	api.i = function() {
		if ( s.has_tinymce && 'tinymce' === s.mode )
			tinyMCE.execCommand('Italic');
	}

	api.ul = function() {
		if ( s.has_tinymce && 'tinymce' === s.mode )
			tinyMCE.execCommand('InsertUnorderedList');
	}

	api.ol = function() {
		if ( s.has_tinymce && 'tinymce' === s.mode )
			tinyMCE.execCommand('InsertOrderedList');
	}

	api.link = function() {
		if ( s.has_tinymce && 'tinymce' === s.mode )
			tinyMCE.execCommand('WP_Link');
		else
			wpLink.open();
	}

	api.unlink = function() {
		if ( s.has_tinymce && 'tinymce' === s.mode )
			tinyMCE.execCommand('unlink');
	}

	api.refresh_buttons = function() {

		if ( s.mode === 'html' ) {
			$('#wp-fullscreen-mode-bar').removeClass('wp-tmce-mode').addClass('wp-html-mode');
			$('#wp-fullscreen-button-bar').fadeOut( 200, function(){
				$(this).addClass('wp-html-mode').fadeIn( 250 );
			});
		} else if ( s.mode === 'tinymce' ) {
			$('#wp-fullscreen-mode-bar').removeClass('wp-html-mode').addClass('wp-tmce-mode');
			$('#wp-fullscreen-button-bar').fadeOut( 200, function(){
				$(this).removeClass('wp-html-mode').fadeIn( 250 );
			});
		}
	}

	/**
	 * UI Elements
	 *
	 * Used for transitioning between states.
	 */
	api.ui = {
		init: function() {
			var topbar = s.topbar = $('#fullscreen-topbar');
			s.element = $('#fullscreen-fader');
			s.textarea_obj = document.getElementById('wp_mce_fullscreen');
			s.has_tinymce = typeof(tinyMCE) != 'undefined';

			if ( !s.has_tinymce )
				$('#wp-fullscreen-mode-bar').hide();

			if ( wptitlehint )
				wptitlehint('wp-fullscreen-title');

			topbar.mouseenter(function(e){
				$('#fullscreen-topbar').addClass('fullscreen-make-sticky');
				$( document ).unbind( '.fullscreen' );
				clearTimeout( s.timer );
				s.timer = 0;
			}).mouseleave(function(e){
				$('#fullscreen-topbar').removeClass('fullscreen-make-sticky');
				$( document ).bind( 'mousemove.fullscreen', function(e) { bounder( 'showToolbar', 'hideToolbar', 2500 ); } );
			});
		},

		fade: function( before, during, after ) {
			if ( ! s.element )
				api.ui.init();

			// If any callback bound to before returns false, bail.
			if ( before && ! ps.publish( before ) )
				return;

			api.fade.In( s.element, 600, function() {
				if ( during )
					ps.publish( during );

				api.fade.Out( s.element, 600, function() {
					if ( after )
						ps.publish( after );
				})
			});
		}
	};

	api.fade = {
		transitionend: 'transitionend webkitTransitionEnd oTransitionEnd',

		// Sensitivity to allow browsers to render the blank element before animating.
		sensitivity: 100,

		In: function( element, speed, callback ) {

			callback = callback || $.noop;
			speed = speed || 400;

			if ( api.fade.transitions ) {
				if ( element.is(':visible') ) {
					element.addClass( 'fade-trigger' );
					return element;
				}

				element.show();
				element.one( this.transitionend, function() {
					callback();
				});
				setTimeout( function() { element.addClass( 'fade-trigger' ); }, this.sensitivity );
			} else {
				element.css( 'opacity', 1 ).fadeIn( speed, callback );
			}

			return element;
		},

		Out: function( element, speed, callback ) {

			callback = callback || $.noop;
			speed = speed || 400;

			if ( ! element.is(':visible') )
				return element;

			if ( api.fade.transitions ) {
				element.one( api.fade.transitionend, function() {
					if ( element.hasClass('fade-trigger') )
						return;

					element.hide();
					callback();
				});
				setTimeout( function() { element.removeClass( 'fade-trigger' ); }, this.sensitivity );
			} else {
				element.fadeOut( speed, callback );
			}

			return element;
		},

		transitions: (function() { // Check if the browser supports CSS 3.0 transitions
			var s = document.documentElement.style;

			return ( typeof ( s.WebkitTransition ) == 'string' ||
				typeof ( s.MozTransition ) == 'string' ||
				typeof ( s.OTransition ) == 'string' ||
				typeof ( s.transition ) == 'string' );
		})()
	};


	/**
	 * Resize API
	 *
	 * Automatically updates textarea height.
	 */

	api.bind_resize = function() {
		$(s.textarea_obj).bind('keypress.grow click.grow paste.grow', function(){
			setTimeout( api.resize_textarea, 200 );
		});
	}

	api.oldheight = 0;
	api.resize_textarea = function() {
		var txt = s.textarea_obj, newheight, scroll = document.body.scrollTop || document.documentElement.scrollTop;

		newheight = txt.scrollHeight > 300 ? txt.scrollHeight : 300;

		if ( newheight != api.oldheight ) {
			txt.style.height = newheight + 'px';
	//		window.scrollTo(0, scroll);
			api.oldheight = newheight;
		}
	};

})(jQuery);

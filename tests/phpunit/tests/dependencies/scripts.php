<?php
/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_Scripts extends WP_UnitTestCase {
	protected $old_wp_scripts;

	protected $wp_scripts_print_translations_output;

	function setUp() {
		parent::setUp();
		$this->old_wp_scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );
		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );

		$this->wp_scripts_print_translations_output  = <<<JS
<script type='text/javascript'>
( function( domain, translations ) {
	var localeData = translations.locale_data[ domain ] || translations.locale_data.messages;
	localeData[""].domain = domain;
	wp.i18n.setLocaleData( localeData, domain );
} )( "__DOMAIN__", __JSON_TRANSLATIONS__ );
</script>
JS;
		$this->wp_scripts_print_translations_output .= "\n";
	}

	function tearDown() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );
		parent::tearDown();
	}

	/**
	 * Test versioning
	 *
	 * @ticket 11315
	 */
	function test_wp_enqueue_script() {
		wp_enqueue_script( 'no-deps-no-version', 'example.com', array() );
		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );
		wp_enqueue_script( 'empty-deps-version', 'example.com', array(), 1.2 );
		wp_enqueue_script( 'empty-deps-null-version', 'example.com', array(), null );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='http://example.com?ver=$ver'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=$ver'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=1.2'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEquals( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 42804
	 */
	function test_wp_enqueue_script_with_html5_support_does_not_contain_type_attribute() {
		add_theme_support( 'html5', array( 'script' ) );

		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );

		wp_enqueue_script( 'empty-deps-no-version', 'example.com' );

		$ver      = get_bloginfo( 'version' );
		$expected = "<script src='http://example.com?ver=$ver'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Test the different protocol references in wp_enqueue_script
	 *
	 * @global WP_Scripts $wp_scripts
	 * @ticket 16560
	 */
	public function test_protocols() {
		// Init.
		global $wp_scripts;
		$base_url_backup      = $wp_scripts->base_url;
		$wp_scripts->base_url = 'http://example.com/wordpress';
		$expected             = '';
		$ver                  = get_bloginfo( 'version' );

		// Try with an HTTP reference.
		wp_enqueue_script( 'jquery-http', 'http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Try with an HTTPS reference.
		wp_enqueue_script( 'jquery-https', 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Try with an automatic protocol reference (//).
		wp_enqueue_script( 'jquery-doubleslash', '//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Try with a local resource and an automatic protocol reference (//).
		$url = '//my_plugin/script.js';
		wp_enqueue_script( 'plugin-script', $url );
		$expected .= "<script type='text/javascript' src='$url?ver=$ver'></script>\n";

		// Try with a bad protocol.
		wp_enqueue_script( 'jquery-ftp', 'ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js' );
		$expected .= "<script type='text/javascript' src='{$wp_scripts->base_url}ftp://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js?ver=$ver'></script>\n";

		// Go!
		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEquals( '', get_echo( 'wp_print_scripts' ) );

		// Cleanup.
		$wp_scripts->base_url = $base_url_backup;
	}

	/**
	 * Test script concatenation.
	 */
	public function test_script_concatenation() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/directory/' );

		wp_enqueue_script( 'one', '/directory/script.js' );
		wp_enqueue_script( 'two', '/directory/script.js' );
		wp_enqueue_script( 'three', '/directory/script.js' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$ver      = get_bloginfo( 'version' );
		$expected = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one,two,three&amp;ver={$ver}'></script>\n";

		$this->assertEquals( $expected, $print_scripts );
	}

	/**
	 * Testing `wp_script_add_data` with the data key.
	 *
	 * @ticket 16024
	 */
	function test_wp_script_add_data_with_data_key() {
		// Enqueue and add data.
		wp_enqueue_script( 'test-only-data', 'example.com', array(), null );
		wp_script_add_data( 'test-only-data', 'data', 'testing' );
		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\ntesting\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";

		// Go!
		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEquals( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with the conditional key.
	 *
	 * @ticket 16024
	 */
	function test_wp_script_add_data_with_conditional_key() {
		// Enqueue and add conditional comments.
		wp_enqueue_script( 'test-only-conditional', 'example.com', array(), null );
		wp_script_add_data( 'test-only-conditional', 'conditional', 'gt IE 7' );
		$expected = "<!--[if gt IE 7]>\n<script type='text/javascript' src='http://example.com'></script>\n<![endif]-->\n";

		// Go!
		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEquals( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with both the data & conditional keys.
	 *
	 * @ticket 16024
	 */
	function test_wp_script_add_data_with_data_and_conditional_keys() {
		// Enqueue and add data plus conditional comments for both.
		wp_enqueue_script( 'test-conditional-with-data', 'example.com', array(), null );
		wp_script_add_data( 'test-conditional-with-data', 'data', 'testing' );
		wp_script_add_data( 'test-conditional-with-data', 'conditional', 'lt IE 9' );
		$expected  = "<!--[if lt IE 9]>\n<script type='text/javascript'>\n/* <![CDATA[ */\ntesting\n/* ]]> */\n</script>\n<![endif]-->\n";
		$expected .= "<!--[if lt IE 9]>\n<script type='text/javascript' src='http://example.com'></script>\n<![endif]-->\n";

		// Go!
		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEquals( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_script_add_data` with an anvalid key.
	 *
	 * @ticket 16024
	 */
	function test_wp_script_add_data_with_invalid_key() {
		// Enqueue and add an invalid key.
		wp_enqueue_script( 'test-invalid', 'example.com', array(), null );
		wp_script_add_data( 'test-invalid', 'invalid', 'testing' );
		$expected = "<script type='text/javascript' src='http://example.com'></script>\n";

		// Go!
		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );

		// No scripts left to print.
		$this->assertEquals( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing 'wp_register_script' return boolean success/failure value.
	 *
	 * @ticket 31126
	 */
	function test_wp_register_script() {
		$this->assertTrue( wp_register_script( 'duplicate-handler', 'http://example.com' ) );
		$this->assertFalse( wp_register_script( 'duplicate-handler', 'http://example.com' ) );
	}

	/**
	 * @ticket 35229
	 */
	function test_wp_register_script_with_handle_without_source() {
		$expected  = "<script type='text/javascript' src='http://example.com?ver=1'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com?ver=2'></script>\n";

		wp_register_script( 'handle-one', 'http://example.com', array(), 1 );
		wp_register_script( 'handle-two', 'http://example.com', array(), 2 );
		wp_register_script( 'handle-three', false, array( 'handle-one', 'handle-two' ) );

		wp_enqueue_script( 'handle-three' );

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 35643
	 */
	function test_wp_enqueue_script_footer_alias() {
		wp_register_script( 'foo', false, array( 'bar', 'baz' ), '1.0', true );
		wp_register_script( 'bar', home_url( 'bar.js' ), array(), '1.0', true );
		wp_register_script( 'baz', home_url( 'baz.js' ), array(), '1.0', true );

		wp_enqueue_script( 'foo' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$this->assertEmpty( $header );
		$this->assertContains( home_url( 'bar.js' ), $footer );
		$this->assertContains( home_url( 'baz.js' ), $footer );
	}

	/**
	 * Test mismatch of groups in dependencies outputs all scripts in right order.
	 *
	 * @ticket 35873
	 */
	public function test_group_mismatch_in_deps() {
		$scripts = new WP_Scripts;
		$scripts->add( 'one', 'one', array(), 'v1', 1 );
		$scripts->add( 'two', 'two', array( 'one' ) );
		$scripts->add( 'three', 'three', array( 'two' ), 'v1', 1 );

		$scripts->enqueue( array( 'three' ) );

		$this->expectOutputRegex( '/^(?:<script[^>]+><\/script>\\n){7}$/' );

		$scripts->do_items( false, 0 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertNotContains( 'three', $scripts->done );

		$scripts->do_items( false, 1 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );

		$scripts = new WP_Scripts;
		$scripts->add( 'one', 'one', array(), 'v1', 1 );
		$scripts->add( 'two', 'two', array( 'one' ), 'v1', 1 );
		$scripts->add( 'three', 'three', array( 'one' ) );
		$scripts->add( 'four', 'four', array( 'two', 'three' ), 'v1', 1 );

		$scripts->enqueue( array( 'four' ) );

		$scripts->do_items( false, 0 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertNotContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );
		$this->assertNotContains( 'four', $scripts->done );

		$scripts->do_items( false, 1 );
		$this->assertContains( 'one', $scripts->done );
		$this->assertContains( 'two', $scripts->done );
		$this->assertContains( 'three', $scripts->done );
		$this->assertContains( 'four', $scripts->done );
	}

	/**
	 * @ticket 35873
	 */
	function test_wp_register_script_with_dependencies_in_head_and_footer() {
		wp_register_script( 'parent', '/parent.js', array( 'child-head' ), null, true );            // In footer.
		wp_register_script( 'child-head', '/child-head.js', array( 'child-footer' ), null, false ); // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array(), null, true );              // In footer.

		wp_enqueue_script( 'parent' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-footer.js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/child-head.js'></script>\n";
		$expected_footer  = "<script type='text/javascript' src='/parent.js'></script>\n";

		$this->assertEquals( $expected_header, $header );
		$this->assertEquals( $expected_footer, $footer );
	}

	/**
	 * @ticket 35956
	 */
	function test_wp_register_script_with_dependencies_in_head_and_footer_in_reversed_order() {
		wp_register_script( 'child-head', '/child-head.js', array(), null, false );                      // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array(), null, true );                   // In footer.
		wp_register_script( 'parent', '/parent.js', array( 'child-head', 'child-footer' ), null, true ); // In footer.

		wp_enqueue_script( 'parent' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-head.js'></script>\n";
		$expected_footer  = "<script type='text/javascript' src='/child-footer.js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/parent.js'></script>\n";

		$this->assertEquals( $expected_header, $header );
		$this->assertEquals( $expected_footer, $footer );
	}

	/**
	 * @ticket 35956
	 */
	function test_wp_register_script_with_dependencies_in_head_and_footer_in_reversed_order_and_two_parent_scripts() {
		wp_register_script( 'grandchild-head', '/grandchild-head.js', array(), null, false );             // In head.
		wp_register_script( 'child-head', '/child-head.js', array(), null, false );                       // In head.
		wp_register_script( 'child-footer', '/child-footer.js', array( 'grandchild-head' ), null, true ); // In footer.
		wp_register_script( 'child2-head', '/child2-head.js', array(), null, false );                     // In head.
		wp_register_script( 'child2-footer', '/child2-footer.js', array(), null, true );                  // In footer.
		wp_register_script( 'parent-footer', '/parent-footer.js', array( 'child-head', 'child-footer', 'child2-head', 'child2-footer' ), null, true ); // In footer.
		wp_register_script( 'parent-header', '/parent-header.js', array( 'child-head' ), null, false );   // In head.

		wp_enqueue_script( 'parent-footer' );
		wp_enqueue_script( 'parent-header' );

		$header = get_echo( 'wp_print_head_scripts' );
		$footer = get_echo( 'wp_print_footer_scripts' );

		$expected_header  = "<script type='text/javascript' src='/child-head.js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/grandchild-head.js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/child2-head.js'></script>\n";
		$expected_header .= "<script type='text/javascript' src='/parent-header.js'></script>\n";

		$expected_footer  = "<script type='text/javascript' src='/child-footer.js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/child2-footer.js'></script>\n";
		$expected_footer .= "<script type='text/javascript' src='/parent-footer.js'></script>\n";

		$this->assertEquals( $expected_header, $header );
		$this->assertEquals( $expected_footer, $footer );
	}

	/**
	 * @ticket 14853
	 */
	function test_wp_add_inline_script_returns_bool() {
		$this->assertFalse( wp_add_inline_script( 'test-example', 'console.log("before");', 'before' ) );
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		$this->assertTrue( wp_add_inline_script( 'test-example', 'console.log("before");', 'before' ) );
	}

	/**
	 * @ticket 14853
	 */
	function test_wp_add_inline_script_unknown_handle() {
		$this->assertFalse( wp_add_inline_script( 'test-invalid', 'console.log("before");', 'before' ) );
		$this->assertEquals( '', get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	function test_wp_add_inline_script_before() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		$expected  = "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	function test_wp_add_inline_script_after() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	function test_wp_add_inline_script_before_and_after() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	function test_wp_add_inline_script_before_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		$expected = "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	function test_wp_add_inline_script_after_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected = "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 44551
	 */
	function test_wp_add_inline_script_before_and_after_for_handle_without_source() {
		wp_register_script( 'test-example', '' );
		wp_enqueue_script( 'test-example' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	function test_wp_add_inline_script_multiple() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\nconsole.log(\"before\");\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\nconsole.log(\"after\");\n</script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	function test_wp_add_inline_script_localized_data_is_added_first() {
		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', array( 'foo' => 'bar' ) );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		$expected  = "<script type='text/javascript'>\n/* <![CDATA[ */\nvar testExample = {\"foo\":\"bar\"};\n/* ]]> */\n</script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_with_concat() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/directory/' );

		wp_enqueue_script( 'one', '/directory/one.js' );
		wp_enqueue_script( 'two', '/directory/two.js' );
		wp_enqueue_script( 'three', '/directory/three.js' );

		wp_add_inline_script( 'one', 'console.log("before one");', 'before' );
		wp_add_inline_script( 'two', 'console.log("before two");', 'before' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript'>\nconsole.log(\"before one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/directory/one.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"before two\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/directory/two.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/directory/three.js?ver={$ver}'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_before_with_concat2() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/directory/' );

		wp_enqueue_script( 'one', '/directory/one.js' );
		wp_enqueue_script( 'two', '/directory/two.js' );
		wp_enqueue_script( 'three', '/directory/three.js' );

		wp_add_inline_script( 'one', 'console.log("before one");', 'before' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript'>\nconsole.log(\"before one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/directory/one.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/directory/two.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/directory/three.js?ver={$ver}'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after_with_concat() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/directory/' );

		wp_enqueue_script( 'one', '/directory/one.js' );
		wp_enqueue_script( 'two', '/directory/two.js' );
		wp_enqueue_script( 'three', '/directory/three.js' );
		wp_enqueue_script( 'four', '/directory/four.js' );

		wp_add_inline_script( 'two', 'console.log("after two");' );
		wp_add_inline_script( 'three', 'console.log("after three");' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/directory/two.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after two\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/directory/three.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after three\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/directory/four.js?ver={$ver}'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 14853
	 */
	public function test_wp_add_inline_script_after_and_before_with_concat_and_conditional() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		$expected_localized  = "<!--[if gte IE 9]>\n";
		$expected_localized .= "<script type='text/javascript'>\n/* <![CDATA[ */\nvar testExample = {\"foo\":\"bar\"};\n/* ]]> */\n</script>\n";
		$expected_localized .= "<![endif]-->\n";

		$expected  = "<!--[if gte IE 9]>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";
		$expected .= "<![endif]-->\n";

		wp_enqueue_script( 'test-example', 'example.com', array(), null );
		wp_localize_script( 'test-example', 'testExample', array( 'foo' => 'bar' ) );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_script_add_data( 'test-example', 'conditional', 'gte IE 9' );

		$this->assertEquals( $expected_localized, get_echo( 'wp_print_scripts' ) );
		$this->assertEquals( $expected, $wp_scripts->print_html );
		$this->assertTrue( $wp_scripts->do_concat );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_with_concat_and_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertEquals( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_with_concat_and_conditional_and_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$ver}'></script>\n";
		$expected .= "<!--[if gte IE 9]>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";
		$expected .= "<![endif]-->\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("after");' );
		wp_script_add_data( 'test-example', 'conditional', 'gte IE 9' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertEquals( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_with_concat_and_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );

		wp_print_scripts();
		$print_scripts = get_echo( '_print_scripts' );

		$this->assertEquals( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_after_concat_with_core_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=jquery-core,jquery-migrate&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"before\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='http://example.com'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/dist/vendor/wp-polyfill.min.js'></script>\n";
		$expected .= "<script type='text/javascript'>\n";
		$expected .= "( 'fetch' in window ) || document.write( '<script src=\"http://example.org/wp-includes/js/dist/vendor/wp-polyfill-fetch.min.js\"></scr' + 'ipt>' );( document.contains ) || document.write( '<script src=\"http://example.org/wp-includes/js/dist/vendor/wp-polyfill-node-contains.min.js\"></scr' + 'ipt>' );( window.FormData && window.FormData.prototype.keys ) || document.write( '<script src=\"http://example.org/wp-includes/js/dist/vendor/wp-polyfill-formdata.min.js\"></scr' + 'ipt>' );( Element.prototype.matches && Element.prototype.closest ) || document.write( '<script src=\"http://example.org/wp-includes/js/dist/vendor/wp-polyfill-element-closest.min.js\"></scr' + 'ipt>' );\n";
		$expected .= "</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/dist/dom-ready.min.js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/dist/a11y.min.js'></script>\n";
		$expected .= "<script type='text/javascript' src='http://example2.com'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after\");\n</script>\n";

		wp_enqueue_script( 'test-example', 'http://example.com', array( 'jquery' ), null );
		wp_add_inline_script( 'test-example', 'console.log("before");', 'before' );
		wp_enqueue_script( 'test-example2', 'http://example2.com', array( 'wp-a11y' ), null );
		wp_add_inline_script( 'test-example2', 'console.log("after");', 'after' );

		$print_scripts  = get_echo( 'wp_print_scripts' );
		$print_scripts .= get_echo( '_print_scripts' );

		/*
		 * We've replaced wp-a11y.js with @wordpress/a11y package (see #45066),
		 * and `wp-polyfill` is now a dependency of the packaged wp-a11y.
		 * The packaged scripts contain various version numbers, which are not exposed,
		 * so we will remove all version args from the output.
		 */
		$print_scripts = preg_replace(
			'~js\?ver=([^"\']*)~', // Matches `js?ver=X.X.X` and everything to single or double quote.
			'js',                  // The replacement, `js` without the version arg.
			$print_scripts         // Printed scripts.
		);

		$this->assertEquals( $expected, $print_scripts );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_customize_dependency() {
		global $wp_scripts;

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$wp_scripts->base_url  = '';
		$wp_scripts->do_concat = true;

		$expected_tail  = "<script type='text/javascript' src='/customize-dependency.js'></script>\n";
		$expected_tail .= "<script type='text/javascript'>\n";
		$expected_tail .= "tryCustomizeDependency()\n";
		$expected_tail .= "</script>\n";

		$handle = 'customize-dependency';
		wp_enqueue_script( $handle, '/customize-dependency.js', array( 'customize-controls' ), null );
		wp_add_inline_script( $handle, 'tryCustomizeDependency()' );

		$print_scripts  = get_echo( 'wp_print_scripts' );
		$print_scripts .= get_echo( '_print_scripts' );

		$tail = substr( $print_scripts, strrpos( $print_scripts, "<script type='text/javascript' src='/customize-dependency.js'>" ) );
		$this->assertEquals( $expected_tail, $tail );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_after_for_core_scripts_with_concat_is_limited_and_falls_back_to_no_concat() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		wp_enqueue_script( 'one', '/wp-includes/js/script.js' );
		wp_enqueue_script( 'two', '/wp-includes/js/script2.js', array( 'one' ) );
		wp_add_inline_script( 'one', 'console.log("after one");', 'after' );
		wp_enqueue_script( 'three', '/wp-includes/js/script3.js' );
		wp_enqueue_script( 'four', '/wp-includes/js/script4.js' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-includes/js/script.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"after one\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script2.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script3.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script4.js?ver={$ver}'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 36392
	 */
	public function test_wp_add_inline_script_before_third_core_script_prints_two_concat_scripts() {
		global $wp_scripts;

		$wp_scripts->do_concat    = true;
		$wp_scripts->default_dirs = array( '/wp-admin/js/', '/wp-includes/js/' ); // Default dirs as in wp-includes/script-loader.php.

		wp_enqueue_script( 'one', '/wp-includes/js/script.js' );
		wp_enqueue_script( 'two', '/wp-includes/js/script2.js', array( 'one' ) );
		wp_enqueue_script( 'three', '/wp-includes/js/script3.js' );
		wp_add_inline_script( 'three', 'console.log("before three");', 'before' );
		wp_enqueue_script( 'four', '/wp-includes/js/script4.js' );

		$ver       = get_bloginfo( 'version' );
		$expected  = "<script type='text/javascript' src='/wp-admin/load-scripts.php?c=0&amp;load%5Bchunk_0%5D=one,two&amp;ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript'>\nconsole.log(\"before three\");\n</script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script3.js?ver={$ver}'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script4.js?ver={$ver}'></script>\n";

		$this->assertEquals( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js'></script>\n";

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_for_plugin() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'plugin-example', '/wp-content/plugins/my-plugin/js/script.js', array(), null );
		wp_set_script_translations( 'plugin-example', 'internationalized-plugin', DIR_TESTDATA . '/languages/plugins' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'internationalized-plugin',
				file_get_contents( DIR_TESTDATA . '/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-content/plugins/my-plugin/js/script.js'></script>\n";

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_for_theme() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'theme-example', '/wp-content/themes/my-theme/js/script.js', array(), null );
		wp_set_script_translations( 'theme-example', 'internationalized-theme', DIR_TESTDATA . '/languages/themes' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'internationalized-theme',
				file_get_contents( DIR_TESTDATA . '/languages/themes/internationalized-theme-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-content/themes/my-theme/js/script.js'></script>\n";

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_with_handle_file() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'script-handle', '/wp-admin/js/script.js', array(), null );
		wp_set_script_translations( 'script-handle', 'admin', DIR_TESTDATA . '/languages/' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'admin',
				file_get_contents( DIR_TESTDATA . '/languages/admin-en_US-script-handle.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-admin/js/script.js'></script>\n";

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_i18n_dependency() {
		global $wp_scripts;

		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages/' );

		$script = $wp_scripts->registered['test-example'];

		$this->assertContains( 'wp-i18n', $script->deps );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_when_translation_file_does_not_exist() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_enqueue_script( 'test-example', '/wp-admin/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'admin', DIR_TESTDATA . '/languages/' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'admin',
				'{ "locale_data": { "messages": { "": {} } } }',
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-admin/js/script.js'></script>\n";

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_after_register() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_register_script( 'test-example', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-example', 'default', DIR_TESTDATA . '/languages' );

		wp_enqueue_script( 'test-example' );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js'></script>\n";

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * @ticket 45103
	 */
	public function test_wp_set_script_translations_dependency() {
		wp_register_script( 'wp-i18n', '/wp-includes/js/dist/wp-i18n.js', array(), null );
		wp_register_script( 'test-dependency', '/wp-includes/js/script.js', array(), null );
		wp_set_script_translations( 'test-dependency', 'default', DIR_TESTDATA . '/languages' );

		wp_enqueue_script( 'test-example', '/wp-includes/js/script2.js', array( 'test-dependency' ), null );

		$expected  = "<script type='text/javascript' src='/wp-includes/js/dist/wp-i18n.js'></script>\n";
		$expected .= str_replace(
			array(
				'__DOMAIN__',
				'__JSON_TRANSLATIONS__',
			),
			array(
				'default',
				file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' ),
			),
			$this->wp_scripts_print_translations_output
		);
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script.js'></script>\n";
		$expected .= "<script type='text/javascript' src='/wp-includes/js/script2.js'></script>\n";

		$this->assertEqualsIgnoreEOL( $expected, get_echo( 'wp_print_scripts' ) );
	}

	/**
	 * Testing `wp_enqueue_code_editor` with file path.
	 *
	 * @ticket 41871
	 * @covers ::wp_enqueue_code_editor()
	 */
	public function test_wp_enqueue_code_editor_when_php_file_will_be_passed() {
		$real_file              = WP_PLUGIN_DIR . '/hello.php';
		$wp_enqueue_code_editor = wp_enqueue_code_editor( array( 'file' => $real_file ) );
		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertEqualSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertEqualSets(
			array(
				'autoCloseBrackets',
				'autoCloseTags',
				'continueComments',
				'direction',
				'extraKeys',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'matchBrackets',
				'matchTags',
				'mode',
				'styleActiveLine',
				'gutters',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);
		$this->assertEmpty( $wp_enqueue_code_editor['codemirror']['gutters'] );

		$this->assertEqualSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertEqualSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertEqualSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `compact`.
	 *
	 * @ticket 41871
	 * @covers ::wp_enqueue_code_editor()
	 */
	public function test_wp_enqueue_code_editor_when_generated_array_by_compact_will_be_passed() {
		$file                   = '';
		$wp_enqueue_code_editor = wp_enqueue_code_editor( compact( 'file' ) );
		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertEqualSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertEqualSets(
			array(
				'continueComments',
				'direction',
				'extraKeys',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'mode',
				'styleActiveLine',
				'gutters',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);
		$this->assertEmpty( $wp_enqueue_code_editor['codemirror']['gutters'] );

		$this->assertEqualSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertEqualSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertEqualSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `array_merge`.
	 *
	 * @ticket 41871
	 * @covers ::wp_enqueue_code_editor()
	 */
	public function test_wp_enqueue_code_editor_when_generated_array_by_array_merge_will_be_passed() {
		$wp_enqueue_code_editor = wp_enqueue_code_editor(
			array_merge(
				array(
					'type'       => 'text/css',
					'codemirror' => array(
						'indentUnit' => 2,
						'tabSize'    => 2,
					),
				),
				array()
			)
		);

		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertEqualSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertEqualSets(
			array(
				'autoCloseBrackets',
				'continueComments',
				'direction',
				'extraKeys',
				'gutters',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'lint',
				'matchBrackets',
				'mode',
				'styleActiveLine',
				'tabSize',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);

		$this->assertEqualSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertEqualSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertEqualSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	/**
	 * Testing `wp_enqueue_code_editor` with `array`.
	 *
	 * @ticket 41871
	 * @covers ::wp_enqueue_code_editor()
	 */
	public function test_wp_enqueue_code_editor_when_simple_array_will_be_passed() {
		$wp_enqueue_code_editor = wp_enqueue_code_editor(
			array(
				'type'       => 'text/css',
				'codemirror' => array(
					'indentUnit' => 2,
					'tabSize'    => 2,
				),
			)
		);

		$this->assertNonEmptyMultidimensionalArray( $wp_enqueue_code_editor );

		$this->assertEqualSets( array( 'codemirror', 'csslint', 'jshint', 'htmlhint' ), array_keys( $wp_enqueue_code_editor ) );
		$this->assertEqualSets(
			array(
				'autoCloseBrackets',
				'continueComments',
				'direction',
				'extraKeys',
				'gutters',
				'indentUnit',
				'indentWithTabs',
				'inputStyle',
				'lineNumbers',
				'lineWrapping',
				'lint',
				'matchBrackets',
				'mode',
				'styleActiveLine',
				'tabSize',
			),
			array_keys( $wp_enqueue_code_editor['codemirror'] )
		);

		$this->assertEqualSets(
			array(
				'errors',
				'box-model',
				'display-property-grouping',
				'duplicate-properties',
				'known-properties',
				'outline-none',
			),
			array_keys( $wp_enqueue_code_editor['csslint'] )
		);

		$this->assertEqualSets(
			array(
				'boss',
				'curly',
				'eqeqeq',
				'eqnull',
				'es3',
				'expr',
				'immed',
				'noarg',
				'nonbsp',
				'onevar',
				'quotmark',
				'trailing',
				'undef',
				'unused',
				'browser',
				'globals',
			),
			array_keys( $wp_enqueue_code_editor['jshint'] )
		);

		$this->assertEqualSets(
			array(
				'tagname-lowercase',
				'attr-lowercase',
				'attr-value-double-quotes',
				'doctype-first',
				'tag-pair',
				'spec-char-escape',
				'id-unique',
				'src-not-empty',
				'attr-no-duplication',
				'alt-require',
				'space-tab-mixed-disabled',
				'attr-unsafe-chars',
			),
			array_keys( $wp_enqueue_code_editor['htmlhint'] )
		);
	}

	function test_no_source_mapping() {
		$all_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( ABSPATH ) . '/build/' ) );
		$js_files  = new RegexIterator( $all_files, '/\.js$/' );
		foreach ( $js_files as $js_file ) {
			$contents = trim( file_get_contents( $js_file ) );

			// We allow data: URLs.
			$found = preg_match( '/sourceMappingURL=((?!data:).)/', $contents );
			$this->assertSame( $found, 0, "sourceMappingURL found in $js_file" );
		}
	}
}

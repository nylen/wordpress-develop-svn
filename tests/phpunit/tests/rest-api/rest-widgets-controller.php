<?php
/**
 * Unit tests covering WP_REST_Widgets_Controller_Test functionality.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.8.0
 */

/**
 * Tests for REST API for Widgets.
 *
 * @since 5.8.0
 *
 * @see WP_Test_REST_Controller_Testcase
 * @group restapi
 * @covers WP_REST_Widgets_Controller
 */
class WP_Test_REST_Widgets_Controller extends WP_Test_REST_Controller_Testcase {
	/**
	 * @var int
	 */
	public $menu_id;

	/**
	 * @var int
	 */
	protected static $superadmin_id;

	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $admin_id_without_unfiltered_html;

	/**
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * @var int
	 */
	protected static $author_id;

	/**
	 * @var int
	 */
	protected static $per_page = 50;

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);
		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor_id     = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$author_id     = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	public function setUp() {
		global $wp_registered_widgets, $wp_registered_sidebars, $_wp_sidebars_widgets, $wp_widget_factory;

		parent::setUp();

		wp_set_current_user( self::$admin_id );

		// Unregister all widgets and sidebars.
		$wp_registered_widgets  = array();
		$wp_registered_sidebars = array();
		$_wp_sidebars_widgets   = array();
		update_option( 'sidebars_widgets', array() );

		// Re-register core widgets.
		$wp_widget_factory->_register_widgets();

		// Register a non-multi widget for testing.
		wp_register_widget_control(
			'testwidget',
			'WP test widget',
			function () {
				$settings = get_option( 'widget_testwidget' );

				// check if anything's been sent.
				if ( isset( $_POST['update_testwidget'] ) ) {
					$settings['id']    = $_POST['test_id'];
					$settings['title'] = $_POST['test_title'];

					update_option( 'widget_testwidget', $settings );
				}

				echo 'WP test widget form';
			},
			100,
			200
		);
		wp_register_sidebar_widget(
			'testwidget',
			'WP test widget',
			function () {
				$settings = wp_parse_args(
					get_option( 'widget_testwidget' ),
					array(
						'id'    => 'Default id',
						'title' => 'Default text',
					)
				);
				echo '<h1>' . $settings['id'] . '</h1><span>' . $settings['title'] . '</span>';
			},
			array(
				'description' => 'A non-multi widget for testing.',
			)
		);
	}

	private function setup_widget( $id_base, $number, $settings ) {
		global $wp_widget_factory;

		$option_name = "widget_$id_base";
		update_option(
			$option_name,
			array(
				$number => $settings,
			)
		);

		$widget_object = $wp_widget_factory->get_widget_object( $id_base );
		$widget_object->_set( $number );
		$widget_object->_register_one( $number );
	}

	private function setup_sidebar( $id, $attrs = array(), $widgets = array() ) {
		global $wp_registered_sidebars;
		update_option(
			'sidebars_widgets',
			array_merge(
				(array) get_option( 'sidebars_widgets', array() ),
				array(
					$id => $widgets,
				)
			)
		);
		$wp_registered_sidebars[ $id ] = array_merge(
			array(
				'id'            => $id,
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			$attrs
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/widgets', $routes );
		$this->assertArrayHasKey( '/wp/v2/widgets/(?P<id>[\w\-]+)', $routes );
	}

	/**
	 * @ticket 41683
	 */
	public function test_context_param() {
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_no_widgets() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widgets' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( array(), $data );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widgets' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items_wrong_permission_author() {
		wp_set_current_user( self::$author_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/widgets' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_items() {
		add_filter( 'pre_http_request', array( $this, 'mocked_rss_response' ) );
		global $wp_widget_factory;

		$wp_widget_factory->widgets['WP_Widget_RSS']->widget_options['show_instance_in_rest'] = false;

		$block_content = '<!-- wp:paragraph --><p>Block test</p><!-- /wp:paragraph -->';

		$this->setup_widget(
			'rss',
			1,
			array(
				'title' => 'RSS test',
				'url'   => 'https://wordpress.org/news/feed',
			)
		);
		$this->setup_widget(
			'block',
			1,
			array(
				'content' => $block_content,
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'block-1', 'rss-1', 'testwidget' )
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/widgets' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertEqualSets(
			array(
				array(
					'id'       => 'block-1',
					'id_base'  => 'block',
					'sidebar'  => 'sidebar-1',
					'instance' => array(
						'encoded' => base64_encode(
							serialize(
								array(
									'content' => $block_content,
								)
							)
						),
						'hash'    => wp_hash(
							serialize(
								array(
									'content' => $block_content,
								)
							)
						),
						'raw'     => array(
							'content' => $block_content,
						),
					),
					'rendered' => '<p>Block test</p>',
				),
				array(
					'id'       => 'rss-1',
					'id_base'  => 'rss',
					'sidebar'  => 'sidebar-1',
					'instance' => array(
						'encoded' => base64_encode(
							serialize(
								array(
									'title' => 'RSS test',
									'url'   => 'https://wordpress.org/news/feed',
								)
							)
						),
						'hash'    => wp_hash(
							serialize(
								array(
									'title' => 'RSS test',
									'url'   => 'https://wordpress.org/news/feed',
								)
							)
						),
					),
					'rendered' => '<a class="rsswidget" href="https://wordpress.org/news/feed"><img class="rss-widget-icon" style="border:0" width="14" height="14" src="http://example.org/wp-includes/images/rss.png" alt="RSS" /></a> <a class="rsswidget" href="https://wordpress.org/news">RSS test</a><ul><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/12/introducing-learn-wordpress/\'>Introducing Learn WordPress</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/12/simone/\'>WordPress 5.6 “Simone”</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/12/state-of-the-word-2020/\'>State of the Word 2020</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/12/the-month-in-wordpress-november-2020/\'>The Month in WordPress: November 2020</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/12/wordpress-5-6-release-candidate-2/\'>WordPress 5.6 Release Candidate 2</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/11/wordpress-5-6-release-candidate/\'>WordPress 5.6 Release Candidate</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/11/wordpress-5-6-beta-4/\'>WordPress 5.6 Beta 4</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/11/wordpress-5-6-beta-3/\'>WordPress 5.6 Beta 3</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/11/the-month-in-wordpress-october-2020/\'>The Month in WordPress: October 2020</a></li><li><a class=\'rsswidget\' href=\'https://wordpress.org/news/2020/10/wordpress-5-5-3-maintenance-release/\'>WordPress 5.5.3 Maintenance Release</a></li></ul>',
				),
				array(
					'id'       => 'testwidget',
					'id_base'  => 'testwidget',
					'sidebar'  => 'sidebar-1',
					'instance' => null,
					'rendered' => '<h1>Default id</h1><span>Default text</span>',
				),
			),
			$data
		);

		$wp_widget_factory->widgets['WP_Widget_RSS']->widget_options['show_instance_in_rest'] = true;
	}

	public function mocked_rss_response() {
		$single_value_headers = array(
			'content-type' => 'application/rss+xml; charset=UTF-8',
			'link'         => '<https://wordpress.org/news/wp-json/>; rel="https://api.w.org/"',
		);

		return array(
			'headers'  => new Requests_Utility_CaseInsensitiveDictionary( $single_value_headers ),
			'body'     => file_get_contents( DIR_TESTDATA . '/feed/wordpress-org-news.xml' ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Test a GET request in edit context. In particular, we expect rendered_form to be served correctly.
	 *
	 * @ticket 41683
	 */
	public function test_get_items_edit_context() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'testwidget' )
		);

		$request            = new WP_REST_Request( 'GET', '/wp/v2/widgets' );
		$request['context'] = 'edit';
		$response           = rest_get_server()->dispatch( $request );
		$data               = $response->get_data();
		$data               = $this->remove_links( $data );
		$this->assertEqualSets(
			array(
				array(
					'id'            => 'text-1',
					'id_base'       => 'text',
					'sidebar'       => 'sidebar-1',
					'instance'      => array(
						'encoded' => base64_encode(
							serialize(
								array(
									'text' => 'Custom text test',
								)
							)
						),
						'hash'    => wp_hash(
							serialize(
								array(
									'text' => 'Custom text test',
								)
							)
						),
						'raw'     => array(
							'text' => 'Custom text test',
						),
					),
					'rendered'      => '<div class="textwidget">Custom text test</div>',
					'rendered_form' => '<input id="widget-text-1-title" name="widget-text[1][title]" class="title sync-input" type="hidden" value="">' . "\n" .
									'			<textarea id="widget-text-1-text" name="widget-text[1][text]" class="text sync-input" hidden>Custom text test</textarea>' . "\n" .
									'			<input id="widget-text-1-filter" name="widget-text[1][filter]" class="filter sync-input" type="hidden" value="on">' . "\n" .
									'			<input id="widget-text-1-visual" name="widget-text[1][visual]" class="visual sync-input" type="hidden" value="on">',
				),
				array(
					'id'            => 'testwidget',
					'id_base'       => 'testwidget',
					'sidebar'       => 'sidebar-1',
					'rendered'      => '<h1>Default id</h1><span>Default text</span>',
					'rendered_form' => 'WP test widget form',
					'instance'      => null,
				),
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/widgets/text-1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEqualSets(
			array(
				'id'       => 'text-1',
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'encoded' => base64_encode(
						serialize(
							array(
								'text' => 'Custom text test',
							)
						)
					),
					'hash'    => wp_hash(
						serialize(
							array(
								'text' => 'Custom text test',
							)
						)
					),
					'raw'     => array(
						'text' => 'Custom text test',
					),
				),
				'rendered' => '<div class="textwidget">Custom text test</div>',
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );

		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/widgets/text-1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_wrong_permission_author() {
		wp_set_current_user( self::$author_id );
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/widgets/text-1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'encoded' => base64_encode(
						serialize(
							array(
								'text' => 'Updated text test',
							)
						)
					),
					'hash'    => wp_hash(
						serialize(
							array(
								'text' => 'Updated text test',
							)
						)
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'text-2', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'text'   => 'Updated text test',
				'title'  => '',
				'filter' => false,
			),
			get_option( 'widget_text' )[2]
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_malformed_instance() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'encoded' => base64_encode(
						serialize(
							array(
								'text' => 'Updated text test',
							)
						)
					),
					'hash'    => 'badhash',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_widget', $response, 400 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_bad_instance() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_widget', $response, 400 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_using_raw_instance() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'block',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'raw' => array(
						'content' => '<!-- wp:paragraph --><p>Block test</p><!-- /wp:paragraph -->',
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'block-7', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'content' => '<!-- wp:paragraph --><p>Block test</p><!-- /wp:paragraph -->',
			),
			get_option( 'widget_block' )[7]
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_raw_instance_not_supported() {
		global $wp_widget_factory;

		$wp_widget_factory->widgets['WP_Widget_Text']->widget_options['show_instance_in_rest'] = false;

		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'raw' => array(
						'title' => 'Updated text test',
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_widget', $response, 400 );

		$wp_widget_factory->widgets['WP_Widget_Text']->widget_options['show_instance_in_rest'] = true;
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_using_form_data() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'   => 'text',
				'sidebar'   => 'sidebar-1',
				'form_data' => 'widget-text[2][text]=Updated+text+test',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'text-2', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'text'   => 'Updated text test',
				'title'  => '',
				'filter' => false,
			),
			$data['instance']['raw']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_multiple_in_a_row() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'raw' => array( 'text' => 'Text 1' ),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'text-2', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'text'   => 'Text 1',
				'title'  => '',
				'filter' => false,
			),
			$data['instance']['raw']
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'raw' => array( 'text' => 'Text 2' ),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'text-3', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'text'   => 'Text 2',
				'title'  => '',
				'filter' => false,
			),
			$data['instance']['raw']
		);

		$sidebar = rest_do_request( '/wp/v2/sidebars/sidebar-1' );
		$this->assertContains( 'text-2', $sidebar->get_data()['widgets'] );
		$this->assertContains( 'text-3', $sidebar->get_data()['widgets'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_second_instance() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/widgets' );
		$request->set_body_params(
			array(
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'raw' => array(
						'text' => 'Updated text test',
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'text-2', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'text'   => 'Updated text test',
				'title'  => '',
				'filter' => false,
			),
			$data['instance']['raw']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/widgets/text-1' );
		$request->set_body_params(
			array(
				'id'       => 'text-1',
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'raw' => array(
						'text' => 'Updated text test',
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'text-1', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'text'   => 'Updated text test',
				'title'  => '',
				'filter' => false,
			),
			$data['instance']['raw']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_reassign_sidebar() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);
		$this->setup_sidebar(
			'sidebar-2',
			array(
				'name' => 'Test sidebar',
			),
			array()
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/widgets/text-1' );
		$request->set_body_params(
			array(
				'sidebar' => 'sidebar-2',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$this->assertNotWPError( $error, $error ? $error->get_error_message() : '' );
		$this->assertSame( 'sidebar-2', $response->get_data()['sidebar'] );

		$sidebar1 = rest_do_request( '/wp/v2/sidebars/sidebar-1' );
		$this->assertNotContains( 'text-1', $sidebar1->get_data()['widgets'] );

		$sidebar2 = rest_do_request( '/wp/v2/sidebars/sidebar-2' );
		$this->assertContains( 'text-1', $sidebar2->get_data()['widgets'] );
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_shouldnt_require_id_base() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/widgets/text-1' );
		$request->set_body_params(
			array(
				'id'       => 'text-1',
				'instance' => array(
					'raw' => array(
						'text' => 'Updated text test',
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'text-1', $data['id'] );
		$this->assertSame( 'sidebar-1', $data['sidebar'] );
		$this->assertEqualSets(
			array(
				'text'   => 'Updated text test',
				'title'  => '',
				'filter' => false,
			),
			$data['instance']['raw']
		);
	}

	/**
	 * @group multisite
	 */
	public function test_store_html_as_admin() {
		if ( is_multisite() ) {
			$this->assertSame(
				'<div class="textwidget">alert(1)</div>',
				$this->update_text_widget_with_raw_html( '<script>alert(1)</script>' )
			);
		} else {
			$this->assertSame(
				'<div class="textwidget"><script>alert(1)</script></div>',
				$this->update_text_widget_with_raw_html( '<script>alert(1)</script>' )
			);
		}
	}

	/**
	 * @group multisite
	 */
	public function test_store_html_as_superadmin() {
		wp_set_current_user( self::$superadmin_id );
		if ( is_multisite() ) {
			$this->assertSame(
				'<div class="textwidget"><script>alert(1)</script></div>',
				$this->update_text_widget_with_raw_html( '<script>alert(1)</script>' )
			);
		} else {
			$this->assertSame(
				'<div class="textwidget"><script>alert(1)</script></div>',
				$this->update_text_widget_with_raw_html( '<script>alert(1)</script>' )
			);
		}
	}

	protected function update_text_widget_with_raw_html( $html ) {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/widgets/text-1' );
		$request->set_body_params(
			array(
				'id'       => 'text-1',
				'id_base'  => 'text',
				'instance' => array(
					'raw' => array(
						'text' => $html,
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		return $data['rendered'];
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_legacy_widget() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'testwidget' )
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/widgets/testwidget' );
		$request->set_body_params(
			array(
				'id'        => 'testwidget',
				'name'      => 'WP test widget',
				'form_data' => 'test_id=My+test+id&test_title=My+test+title&update_testwidget=true',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				'id'            => 'testwidget',
				'id_base'       => 'testwidget',
				'sidebar'       => 'sidebar-1',
				'rendered'      => '<h1>My test id</h1><span>My test title</span>',
				'rendered_form' => 'WP test widget form',
				'instance'      => null,
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_create_item_legacy_widget() {
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array()
		);

		$request = new WP_REST_Request( 'PUT', '/wp/v2/widgets/testwidget' );
		$request->set_body_params(
			array(
				'id'        => 'testwidget',
				'sidebar'   => 'sidebar-1',
				'name'      => 'WP test widget',
				'form_data' => 'test_id=My+test+id&test_title=My+test+title&update_testwidget=true',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$data     = $this->remove_links( $data );
		$this->assertSame(
			array(
				'id'            => 'testwidget',
				'id_base'       => 'testwidget',
				'sidebar'       => 'sidebar-1',
				'rendered'      => '<h1>My test id</h1><span>My test title</span>',
				'rendered_form' => 'WP test widget form',
				'instance'      => null,
			),
			$data
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_no_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_update_item_wrong_permission_author() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sidebars/sidebar-1' );
		$request->set_body_params(
			array(
				'widgets' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * Tests if the endpoint correctly handles "slashable" characters such as " or '.
	 */
	public function test_update_item_slashing() {
		$this->setup_widget( 'text', 1, array( 'text' => 'Custom text test' ) );
		$this->setup_sidebar( 'sidebar-1', array( 'name' => 'Test sidebar' ), array( 'text-1', 'rss-1' ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/widgets/text-1' );
		$request->set_body_params(
			array(
				'id'       => 'text-1',
				'id_base'  => 'text',
				'sidebar'  => 'sidebar-1',
				'instance' => array(
					'raw' => array(
						'text' => 'Updated \\" \\\' text test',
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEqualSets(
			array(
				'text'   => 'Updated \\" \\\' text test',
				'title'  => '',
				'filter' => false,
			),
			$data['instance']['raw']
		);

		$this->assertSame(
			'<div class="textwidget">Updated \\" \\\' text test</div>',
			$data['rendered']
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_delete_item() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/widgets/text-1' );
		$response = rest_do_request( $request );

		$this->assertEqualSets(
			array(
				'id'            => 'text-1',
				'id_base'       => 'text',
				'sidebar'       => 'wp_inactive_widgets',
				'instance'      => array(
					'encoded' => base64_encode(
						serialize(
							array(
								'text' => 'Custom text test',
							)
						)
					),
					'hash'    => wp_hash(
						serialize(
							array(
								'text' => 'Custom text test',
							)
						)
					),
					'raw'     => array(
						'text' => 'Custom text test',
					),
				),
				'rendered'      => '',
				'rendered_form' => '<input id="widget-text-1-title" name="widget-text[1][title]" class="title sync-input" type="hidden" value="">' . "\n" .
								'			<textarea id="widget-text-1-text" name="widget-text[1][text]" class="text sync-input" hidden>Custom text test</textarea>' . "\n" .
								'			<input id="widget-text-1-filter" name="widget-text[1][filter]" class="filter sync-input" type="hidden" value="on">' . "\n" .
								'			<input id="widget-text-1-visual" name="widget-text[1][visual]" class="visual sync-input" type="hidden" value="on">',
			),
			$response->get_data()
		);
	}

	/**
	 * @ticket 41683
	 */
	public function test_delete_item_force() {
		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/widgets/text-1' );
		$request->set_query_params( array( 'force' => true ) );
		$response = rest_do_request( $request );

		$this->assertEqualSets(
			array(
				'deleted'  => true,
				'previous' => array(

					'id'            => 'text-1',
					'id_base'       => 'text',
					'sidebar'       => 'sidebar-1',
					'instance'      => array(
						'encoded' => base64_encode(
							serialize(
								array(
									'text' => 'Custom text test',
								)
							)
						),
						'hash'    => wp_hash(
							serialize(
								array(
									'text' => 'Custom text test',
								)
							)
						),
						'raw'     => array(
							'text' => 'Custom text test',
						),
					),
					'rendered'      => '<div class="textwidget">Custom text test</div>',
					'rendered_form' => '<input id="widget-text-1-title" name="widget-text[1][title]" class="title sync-input" type="hidden" value="">' . "\n" .
									'			<textarea id="widget-text-1-text" name="widget-text[1][text]" class="text sync-input" hidden>Custom text test</textarea>' . "\n" .
									'			<input id="widget-text-1-filter" name="widget-text[1][filter]" class="filter sync-input" type="hidden" value="on">' . "\n" .
									'			<input id="widget-text-1-visual" name="widget-text[1][visual]" class="visual sync-input" type="hidden" value="on">',

				),
			),
			$response->get_data()
		);

		$response = rest_do_request( '/wp/v2/widgets/text-1' );
		$this->assertSame( 404, $response->get_status() );

		$this->assertArrayNotHasKey( 'text-1', get_option( 'sidebars_widgets' )['sidebar-1'] );
		$this->assertArrayNotHasKey( 1, get_option( 'widget_text' ) );
	}

	/**
	 * @ticket 41683
	 */
	public function test_delete_item_logged_out() {
		wp_set_current_user( 0 );

		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/widgets/text-1' );
		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 401 );
	}

	/**
	 * @ticket 41683
	 */
	public function test_delete_item_author() {
		wp_set_current_user( self::$author_id );

		$this->setup_widget(
			'text',
			1,
			array(
				'text' => 'Custom text test',
			)
		);
		$this->setup_sidebar(
			'sidebar-1',
			array(
				'name' => 'Test sidebar',
			),
			array( 'text-1', 'rss-1' )
		);

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/widgets/text-1' );
		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_cannot_manage_widgets', $response, 403 );
	}

	/**
	 * The test_prepare_item() method does not exist for sidebar.
	 */
	public function test_prepare_item() {
	}

	/**
	 * @ticket 41683
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/widgets' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertSame( 7, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'id_base', $properties );
		$this->assertArrayHasKey( 'sidebar', $properties );
		$this->assertArrayHasKey( 'rendered', $properties );
		$this->assertArrayHasKey( 'rendered_form', $properties );
		$this->assertArrayHasKey( 'instance', $properties );
		$this->assertArrayHasKey( 'form_data', $properties );
	}

	/**
	 * Helper to remove links key.
	 *
	 * @param array $data Array of data.
	 *
	 * @return array
	 */
	protected function remove_links( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$count = 0;
		foreach ( $data as $item ) {
			if ( is_array( $item ) && isset( $item['_links'] ) ) {
				unset( $data[ $count ]['_links'] );
			}
			$count ++;
		}

		return $data;
	}
}

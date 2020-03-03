<?php
/**
 * Plugin Name:       Add Custom Header Images
 * Plugin URI:        https://github.com/afragen/add-custom-header-images
 * Description:       Remove default header images and add custom header images. Images must be added to new page titled <strong>The Headers</strong>.  Based upon a post from <a href="http://juliobiason.net/2011/10/25/twentyeleven-with-easy-rotating-header-images/">Julio Biason</a>.
 * Version:           1.9.0.2
 * Author:            Andy Fragen
 * Author URI:        https://thefragens.com
 * License:           GNU General Public License v2
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       add-custom-header-images
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/afragen/add-custom-header-images
 * Requires at least: 4.8
 * Requires PHP:      5.6
 */

/**
 * Class Add_Custom_Header_Images
 */
class Add_Custom_Header_Images {
	/**
	 * Variable to hold the data for `get_page_by_title()`.
	 *
	 * @var array|null|\WP_Post
	 */
	private $the_headers_page;

	/**
	 * Variable to hold header image URL.
	 *
	 * @var string
	 */
	public $header_image;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$the_headers_title      = __( 'The Headers', 'add-custom-header-images' );
		$this->the_headers_page = get_page_by_title( esc_attr( $the_headers_title ) );
		$this->run();
	}

	/**
	 * Let's get started.
	 *
	 * @return bool
	 */
	public function run() {
		add_action(
			'init',
			function () {
				load_plugin_textdomain( 'add-custom-header-images', false, basename( __DIR__ ) );
			}
		);

		if ( ( is_admin() && null === $this->the_headers_page )
		) {
			add_action( 'admin_notices', [ $this, 'headers_page_not_present' ] );

			return false;
		}
		add_action( 'after_setup_theme', [ $this, 'new_default_header_images' ], 99 );
		add_action( 'after_setup_theme', [ $this, 'setup_default_header_image' ], 100 );
	}

	/**
	 * Disable plugin if 'The Headers' page does not exist.
	 */
	public function headers_page_not_present() {
		echo '<div class="error notice is-dismissible"><p>';
		echo wp_kses_post( __( 'Add Custom Header Images requires a page titled <strong>The Headers</strong>.', 'add-custom-header-images' ) );
		echo '</p></div>';
	}

	/**
	 * Remove default header images.
	 */
	public function remove_default_header_images() {
		global $_wp_default_headers;
		if ( empty( $_wp_default_headers ) ) {
			return false;
		}

		$header_ids = [];
		foreach ( (array) array_keys( $_wp_default_headers ) as $key ) {
			if ( ! is_int( $key ) ) {
				$header_ids[] = $key;
			}
		}

		unregister_default_headers( $header_ids );
	}

	/**
	 * Add new default header images.
	 *
	 * @link http://juliobiason.net/2011/10/25/twentyeleven-with-easy-rotating-header-images/
	 */
	public function new_default_header_images() {
		if ( ! $this->the_headers_page instanceof \WP_Post ) {
			return false;
		}

		$this->remove_default_header_images();
		$headers      = [];
		$images_query = new \WP_Query(
			[
				'post_parent'    => $this->the_headers_page->ID,
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'order'          => 'ASC',
				'orderby'        => 'menu_order ID',
			]
		);

		// Get images from blocks.
		preg_match_all( '|id":(\d+)|', $this->the_headers_page->post_content, $matches );
		foreach ( $matches[1] as $id ) {
			$blocks[] = (object) [
				'ID'         => (int) $id,
				'post_title' => get_the_title( $id ),
			];
		}

		$images = array_merge( $images_query->posts, $blocks );

		if ( empty( $images ) ) {
			return false;
		}

		foreach ( $images as $image ) {
			$thumb = wp_get_attachment_image_src( $image->ID, 'medium' );

			$headers[] = [
				'url'           => wp_get_attachment_url( $image->ID ),
				'thumbnail_url' => $thumb[0],
				'description'   => $image->post_title,
				'attachment_id' => $image->ID,
			];

			$image_ids[] = $image->ID;
		}

		$headers = $this->filter_headers( $headers, $image_ids );

		register_default_headers( $headers );
	}

	/**
	 * Remove duplicate $headers.
	 *
	 * @param array $headers   Array of image data.
	 * @param array $image_ids Array of image IDs.
	 *
	 * @return void
	 */
	private function filter_headers( $headers, $image_ids ) {
		$image_ids = array_flip( array_unique( $image_ids ) );
		$headers   = array_filter(
			$headers,
			function ( $id ) use ( &$image_ids ) {
				if ( array_key_exists( $id['attachment_id'], $image_ids ) ) {
					unset( $image_ids[ $id['attachment_id'] ] );

					return $id;
				}
			}
		);

		return $headers;
	}

	/**
	 * Add default header image if theme doesn't support it.
	 *
	 * @return void
	 */
	public function setup_default_header_image() {
		if ( ! current_theme_supports( 'custom-header' ) ) {
			add_theme_support( 'custom-header' );
			$this->header_image = get_header_image();

			if ( ! function_exists( 'wp_body_open' ) ) {
				/**
				 * Shim for wp_body_open, ensuring backward compatibility with versions of WordPress older than 5.2.
				 */
				function wp_body_open() {
					do_action( 'wp_body_open' );
				}
			}

			add_action(
				'wp_body_open',
				function () {
					echo '<header><img src=' . $this->header_image . '></header>';
				}
			);
			add_action( 'wp_head', [ $this, 'header_image_style' ] );
		}
	}

	/**
	 * Header image CSS styling.
	 *
	 * @return void
	 */
	public function header_image_style() {
		?>
			<style>
			header > img {
				display: block;
				margin-left: auto;
				margin-right: auto;
				width: 90%;
			}
			</style>
		<?php
	}
}

new Add_Custom_Header_Images();

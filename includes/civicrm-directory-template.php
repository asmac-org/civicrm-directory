<?php
/**
 * Template Class.
 *
 * Handles templating functionality.
 *
 * @package CiviCRM_Directory
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Template Class.
 *
 * A class that encapsulates templating functionality for CiviCRM Directory.
 *
 * @since 0.1
 */
class CiviCRM_Directory_Template {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Viewed contact.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var array $contact The requested contact data.
	 */
	public $contact = false;
	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store plugin reference.
		$this->plugin = $parent;

	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Override some page elements.
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 5 );

		// Filter the content.
		add_filter( 'the_content', [ $this, 'directory_render' ] );

	}

	/**
	 * Actions to perform on plugin activation.
	 *
	 * @since 0.1
	 */
	public function activate() {

	}

	/**
	 * Actions to perform on plugin deactivation (NOT deletion).
	 *
	 * @since 0.1
	 */
	public function deactivate() {

	}

	// #########################################################################

	/**
	 * Check query for directory entry view request.
	 *
	 * @since 0.2.1
	 *
	 * @param object $query The current Posts query object.
	 */
	public function pre_get_posts( $query ) {

		// Front end and main query?
		if ( ! is_admin() && $query->is_main_query() ) {

			// Are we viewing a contact?
			$contact_id = $query->get( 'entry' );
			if ( ! empty( $contact_id ) ) {

				// Sanity check.
				$contact_id = absint( $contact_id );

				// Reject failed conversions.
				if ( $contact_id == 0 ) {
					return $query;
				}

				// Set up contact once query is complete.
				add_action( 'wp', [ $this, 'entry_setup' ] );

				// Store contact ID for retrieval in entry_setup().
				$this->contact_id = $contact_id;

			}

		}

	}

	/**
	 * Register hooks and retrieve data for directory contact view.
	 *
	 * @since 0.2.4
	 */
	public function entry_setup() {

		// Bail if we don't have a valid countact ID.
		if ( ! isset( $this->contact_id ) ) {
			return;
		}
		if ( ! is_int( $this->contact_id ) ) {
			return;
		}

		// Loop through the results and get group ID from post meta.
		if ( have_posts() ) {
			while ( have_posts() ) :
the_post();
				global $post;
				$group_id = $this->plugin->cpt_meta->group_id_get( $post->ID );
			endwhile;
		}

		// Reset loop.
		rewind_posts();

		// Sanity check.
		if ( empty( $group_id ) ) {
			return $results;
		}

		// Restrict to group.
		$args = [
			'group_id' => $group_id,
		];

		// Get contact.
		$this->contact = $this->plugin->civi->contact_get_by_id( $this->contact_id, $args );

		// Filter the document title for themes that still use wp_title().
		add_filter( 'wp_title', [ $this, 'document_title' ], 20, 3 );

		// Filter the document title for themes that support 'title-tag'.
		add_filter( 'document_title_parts', [ $this, 'document_title_parts' ], 20 );

		// Filter the title.
		add_filter( 'the_title', [ $this, 'the_title' ], 10 );

		// Override the initial map query.
		add_filter( 'civicrm_directory_map_contacts', [ $this, 'map_query_filter' ] );

	}

	/**
	 * Override document title when viewing a contact in the directory.
	 *
	 * This filter only kicks in with themes that do not add theme support for
	 * 'title-tag' and still use wp_title(). This includes default themes up to
	 * Twenty Fourteen, so it's worth supporting.
	 *
	 * @since 0.2.4
	 *
	 * @param string $title The page title.
	 * @param string $sep Title separator.
	 * @param string $sep_location Location of the separator (left or right).
	 * @return string $title The modified page title.
	 */
	public function document_title( $title, $sep, $sep_location ) {

		// Safety first.
		if ( ! isset( $this->contact['display_name'] ) ) {
			return $title;
		}

		// Sep on right, so reverse the order.
		if ( 'right' == $sep_location ) {
			$title = $this->contact['display_name'] . " $sep " . $title;
		} else {
			$title = $title . " $sep " . $this->contact['display_name'];
		}

		// --<
		return $title;

	}

	/**
	 * Add the root network name when the sub-blog is a group blog.
	 *
	 * @since 3.8
	 *
	 * @param array $parts The existing title parts.
	 * @return array $parts The modified title parts.
	 */
	public function document_title_parts( $parts ) {

		// Safety first.
		if ( ! isset( $this->contact['display_name'] ) ) {
			return $parts;
		}

		// Prepend the contact display name.
		$parts = [ 'name' => $this->contact['display_name'] ] + $parts;

		// --<
		return $parts;

	}

	/**
	 * Override title of the directory page when viewing a contact.
	 *
	 * @since 0.2.1
	 *
	 * @param string $title The existing title.
	 * @return string $title The modified title.
	 */
	public function the_title( $title ) {

		global $wp_query;

		// Are we viewing a contact?
		if ( ! empty( $wp_query->query_vars['entry'] ) && is_singular( 'directory' ) && in_the_loop() ) {

			// Override title if we're successful.
			if ( $this->contact !== false ) {
				$title = $this->contact['display_name'];
			}

		}

		// --<
		return $title;

	}

	/**
	 * Get the title of the directory page.
	 *
	 * @since 0.2.2
	 *
	 * @param int $post_id The numeric ID of the directory.
	 * @return str $title The directory title.
	 */
	public function get_the_title( $post_id = null ) {

		// Use current post if none passed.
		if ( is_null( $post_id ) ) {
			$post_id = get_the_ID();
		}

		// Remove filter.
		remove_filter( 'the_title', [ $this, 'the_title' ], 10 );

		// Get title.
		$title = get_the_title( $post_id );

		// Re-add filter.
		add_filter( 'the_title', [ $this, 'the_title' ], 10 );

		// --<
		return $title;

	}

	/**
	 * Construct the markup for a contact.
	 *
	 * @since 0.2.2
	 *
	 * @return string $markup The constructed markup for a contact.
	 */
	public function the_contact() {

		// Init return.
		$markup = '';

		// Bail if we don't have a contact.
		if ( $this->contact === false ) {
			return $markup;
		}

		// Grab contact type.
		$contact_type = $this->contact['contact_type'];

		// Init contact types.
		$types = [ 'Contact', $contact_type ];

		// Get all public contact fields.
		$all_contact_fields = $this->plugin->civi->contact_fields_get( $types, 'public' );

		// Build reference array.
		$core_refs = [];
		foreach ( $all_contact_fields as $contact_field ) {
			$core_refs[ $contact_field['name'] ] = $contact_field['title'];
		}

		// Get contact custom fields.
		$all_contact_custom_fields = $this->plugin->civi->contact_custom_fields_get( $types );

		// Extract just the reference data.
		$custom_field_refs = [];
		foreach ( $all_contact_custom_fields as $key => $value ) {
			$custom_field_refs[ $value['id'] ] = $value['label'];
		}

		// Fields that have associated option groups.
		$fields_with_optgroups = [ 'Select', 'Radio', 'CheckBox', 'Multi-Select', 'AdvMulti-Select' ];

		// Fill out the content of the custom fields.
		$custom_option_refs = [];
		foreach ( $all_contact_custom_fields as $key => $field ) {

			// If this field type doesn't have an option group.
			if ( ! in_array( $field['html_type'], $fields_with_optgroups ) ) {

				// Grab data format.
				$custom_option_refs[ $field['id'] ] = $field['data_type'];

			} else {

				// Grab data from option group.
				if ( isset( $field['option_group_id'] ) && ! empty( $field['option_group_id'] ) ) {
					$custom_option_refs[ $field['id'] ] = CRM_Core_OptionGroup::valuesByID( absint( $field['option_group_id'] ) );
				}

			}

		}

		// Build reference array.
		$other_refs = [];

		// Get all email types.
		$email_types = $this->plugin->civi->email_types_get();

		// Build reference array.
		foreach ( $email_types as $email_type ) {
			$other_refs['email'][ $email_type['key'] ] = $email_type['value'];
		}

		// Get all website types.
		$website_types = $this->plugin->civi->website_types_get();

		// Build reference array.
		foreach ( $website_types as $website_type ) {
			$other_refs['website'][ $website_type['key'] ] = $website_type['value'];
		}

		// Get all phone types.
		$phone_types = $this->plugin->civi->phone_types_get();

		// Build reference array.
		foreach ( $phone_types as $phone_type ) {
			$other_refs['phone'][ $phone_type['key'] ] = $phone_type['value'];
		}

		// Get all address types.
		$address_types = $this->plugin->civi->address_types_get();

		// Build reference array.
		foreach ( $address_types as $address_type ) {
			$other_refs['address']['locations'][ $address_type['key'] ] = $address_type['value'];
		}

		// Get all address fields.
		$address_fields = $this->plugin->civi->address_fields_get();

		// Build reference array.
		foreach ( $address_fields as $address_field ) {
			$other_refs['address']['fields'][ $address_field['name'] ] = $address_field['title'];
		}

		// Get contact fields data.
		$contact_fields = $this->plugin->cpt_meta->contact_fields_get();

		// Init args.
		$args = [
			'returns' => [],
			'api.Email.get' => [],
			'api.Website.get' => [],
			'api.Phone.get' => [],
			'api.Address.get' => [],
		];

		// Build fields-to-return.
		$fields_core = isset( $contact_fields[ $contact_type ]['core'] ) ? $contact_fields[ $contact_type ]['core'] : [];
		foreach ( $fields_core as $key => $field ) {
			$args['returns'][] = $field;
		}
		$fields_custom = isset( $contact_fields[ $contact_type ]['custom'] ) ? $contact_fields[ $contact_type ]['custom'] : [];
		foreach ( $fields_custom as $field ) {
			$args['returns'][] = 'custom_' . $field;
		}

		// Build chained API calls.
		$fields_other = isset( $contact_fields[ $contact_type ]['other'] ) ? $contact_fields[ $contact_type ]['other'] : [];
		foreach ( $fields_other as $field ) {

			if ( $field == 'email' ) {
				foreach ( $contact_fields[ $contact_type ]['email']['enabled'] as $email ) {
					$args['api.Email.get'][] = $email;
				}
			}

			if ( $field == 'website' ) {
				foreach ( $contact_fields[ $contact_type ]['website']['enabled'] as $website ) {
					$args['api.Website.get'][] = $website;
				}
			}

			if ( $field == 'phone' ) {
				foreach ( $contact_fields[ $contact_type ]['phone']['enabled'] as $loc_type ) {
					$args['api.Phone.get'][ $loc_type ] = $contact_fields[ $contact_type ]['phone'][ $loc_type ];
				}
			}

			if ( $field == 'address' ) {
				foreach ( $contact_fields[ $contact_type ]['address']['enabled'] as $loc_type ) {
					$args['api.Address.get'][ $loc_type ] = $contact_fields[ $contact_type ]['address'][ $loc_type ];
				}
			}

		}

		// Restrict to group.
		$args['group_id'] = $this->plugin->cpt_meta->group_id_get( get_the_ID() );

		// Get contact again, this time with custom fields etc.
		$contact_data = $this->plugin->civi->contact_get_by_id( $this->contact['contact_id'], $args );

		// Init template var.
		$contact = [];

		// Build core data array.
		foreach ( $fields_core as $field ) {
			if ( ! empty( $contact_data[ $field ] ) ) {
				$contact['core'][] = [
					'label' => $core_refs[ $field ],
					'value' => $contact_data[ $field ],
				];
			}
		}

		// Build custom data array.
		foreach ( $fields_custom as $field_id ) {
			if ( ! empty( $contact_data[ 'custom_' . $field_id ] ) ) {
				if ( is_array( $custom_option_refs[ $field_id ] ) ) {
					$value = $custom_option_refs[ $field_id ][ $contact_data[ 'custom_' . $field_id ] ];
				} else {
					$value = $contact_data[ 'custom_' . $field_id ];
				}
				$contact['custom'][] = [
					'label' => $custom_field_refs[ $field_id ],
					'value' => $value,
				];
			}
		}

		// Build other data arrays.
		foreach ( $fields_other as $field ) {

			if ( $field == 'email' ) {
				foreach ( $contact_data['api.Email.get']['values'] as $item ) {
					if ( ! empty( $item['email'] ) ) {
						$contact[ $field ][ $item['location_type_id'] ] = [
							'label' => $other_refs[ $field ][ $item['location_type_id'] ],
							'value' => $item['email'],
						];
					}
				}
			}

			if ( $field == 'website' ) {
				foreach ( $contact_data['api.Website.get']['values'] as $item ) {
					if ( ! empty( $item['url'] ) ) {
						$contact[ $field ][ $item['website_type_id'] ] = [
							'label' => $other_refs[ $field ][ $item['website_type_id'] ],
							'value' => $item['url'],
						];
					}
				}
			}

			if ( $field == 'phone' ) {
				foreach ( $contact_data['api.Phone.get']['values'] as $item ) {
					if ( ! empty( $item['phone'] ) ) {
						$contact[ $field ][ $item['location_type_id'] ] = [
							'label' => $other_refs['email'][ $item['location_type_id'] ],
							'value' => $other_refs[ $field ][ $item['phone_type_id'] ] . ': ' . $item['phone'],
						];
					}
				}
			}

			if ( $field == 'address' ) {

				// Init data for this address.
				foreach ( $contact_fields[ $contact_type ][ $field ]['enabled'] as $location_type_id ) {

					$fields = $contact_fields[ $contact_type ][ $field ][ $location_type_id ];

					$contact[ $field ][ $location_type_id ] = [
						'label' => $other_refs[ $field ]['locations'][ $location_type_id ],
						'address' => [],
					];

					foreach ( $contact_data['api.Address.get']['values'] as $item ) {

						foreach ( $item as $key => $value ) {

							// Skip nested queries.
							if ( $key == 'state_province_id.name' ) {
								continue;
							}
							if ( $key == 'country_id.name' ) {
								continue;
							}

							// Skip if not asked for.
							if ( ! in_array( $key, $contact_fields[ $contact_type ][ $field ][ $location_type_id ] ) ) {
								continue;
							}
							if ( $location_type_id != $item['location_type_id'] ) {
								continue;
							}

							// Skip if empty.
							if ( empty( $value ) ) {
								continue;
							}

							// Init label.
							$label = $other_refs[ $field ]['fields'][ $key ];

							// Handle some fields differently.
							if ( $key == 'state_province_id' ) {
								$value = $item['state_province_id.name'];
								$label = __( 'State/Province', 'civicrm-directory' );
							}
							if ( $key == 'country_id' ) {
								$value = $item['country_id.name'];
								$label = __( 'Country', 'civicrm-directory' );
							}

							// Add to data array.
							$contact[ $field ][ $location_type_id ]['address'][] = [
								'label' => $label,
								'value' => $value,
							];

						}

					}

					// Unset if empty.
					if ( empty( $contact[ $field ][ $location_type_id ]['address'] ) ) {
						unset( $contact[ $field ][ $location_type_id ] );
					}

				}

			}

		}

		// Use template.
		$file = 'civicrm-directory/directory-details.php';

		// Get template.
		$template = $this->find_file( $file );

		// Buffer the template part.
		ob_start();
		include $template;
		$markup = ob_get_contents();
		ob_end_clean();

		// --<
		return $markup;

	}

	/**
	 * Override the initial map query.
	 *
	 * @since 0.2.1
	 *
	 * @param array $contacts The contacts retrieved from CiviCRM.
	 * @return array $contacts The modified contacts retrieved from CiviCRM.
	 */
	public function map_query_filter( $contacts ) {

		// Override if viewing a contact.
		if ( $this->contact !== false ) {
			$contacts = [ $this->contact ];
		}

		// --<
		return $contacts;

	}

	/**
	 * Callback filter to display a Directory.
	 *
	 * @param str $content The existing content.
	 * @return str $content The modified content.
	 */
	public function directory_render( $content ) {

		global $wp_query;

		// Only on canonical Directory pages.
		if ( ! is_singular( $this->plugin->cpt->post_type_name ) ) {
			return $content;
		}

		// Only for our post type.
		if ( get_post_type( get_the_ID() ) !== $this->plugin->cpt->post_type_name ) {
			return $content;
		}

		// Are we viewing a contact?
		if ( ! empty( $this->contact ) ) {
			$file = 'civicrm-directory/directory-contact.php';
		} else {
			$file = 'civicrm-directory/directory-index.php';
		}

		// Get template.
		$template = $this->find_file( $file );

		// Buffer the template part.
		ob_start();
		include $template;
		$content = ob_get_contents();
		ob_end_clean();

		// --<
		return $content;

	}

	/**
	 * Insert the listing markup.
	 *
	 * @since 0.1
	 *
	 * @param array $data The configuration data.
	 */
	public function insert_markup( $data = [] ) {

		/**
		 * Data can be amended (or created) by callbacks for this filter.
		 *
		 * @since 0.1.3
		 *
		 * @param array $data The existing template data.
		 * @return array $data The modified template data.
		 */
		$data = apply_filters( 'civicrm_directory_listing_markup', $data );

		// Init template vars.
		$listing = isset( $data['listing'] ) ? $data['listing'] : '';
		$feedback = isset( $data['feedback'] ) ? $data['feedback'] : '';

		// Get template.
		$template = $this->find_file( 'civicrm-directory/directory-listing.php' );

		// Include the template part.
		include $template;

	}

	/**
	 * Find a template given a relative path.
	 *
	 * Example: 'civicrm-directory/directory-search.php'
	 *
	 * @since 0.1
	 *
	 * @param str $template_path The relative path to the template.
	 * @return str|bool $full_path The absolute path to the template, or false on failure.
	 */
	public function find_file( $template_path ) {

		// Get stack.
		$stack = $this->template_stack();

		// Constuct templates array.
		$templates = [];
		foreach ( $stack as $location ) {
			$templates[] = trailingslashit( $location ) . $template_path;
		}

		// Let's look for it.
		$full_path = false;
		foreach ( $templates as $template ) {
			if ( file_exists( $template ) ) {
				$full_path = $template;
				break;
			}
		}

		// --<
		return $full_path;

	}

	/**
	 * Construct template stack.
	 *
	 * @since 0.1
	 *
	 * @return array $stack The stack of locations to look for a template in.
	 */
	public function template_stack() {

		// Define paths.
		$template_dir = get_stylesheet_directory();
		$parent_template_dir = get_template_directory();
		$plugin_template_directory = CIVICRM_DIRECTORY_PATH . 'assets/templates/theme';

		// Construct stack.
		$stack = [ $template_dir, $parent_template_dir, $plugin_template_directory ];

		/**
		 * Allow stack to be filtered.
		 *
		 * @since 0.1
		 *
		 * @param array $stack The default template stack.
		 * @return array $stack The filtered template stack.
		 */
		$stack = apply_filters( 'civicrm_directory_template_stack', $stack );

		// Sanity check.
		$stack = array_unique( $stack );

		// --<
		return $stack;

	}

}

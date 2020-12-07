<?php

add_action( 'init', array( 'GFDirectory_Admin', 'initialize' ) );

class GFDirectory_Admin {

	public static function initialize() {
		new GFDirectory_Admin();
	}

	public function __construct() {

		if ( ! is_admin() ) {
			return;
		}

		$settings = GFDirectory::get_settings();

		add_action( 'admin_notices', array( &$this, 'gf_warning' ) );
		add_filter( 'gform_pre_render', array( 'GFDirectory_Admin', 'show_field_ids' ) );

		//creates a new Settings page on Gravity Forms' settings screen
		if ( GFDirectory::has_access( 'gravityforms_directory' ) ) {
			RGForms::add_settings_page( 'Directory', array( &$this, 'settings_page' ), '' );
		}
		add_filter( 'gform_addon_navigation', array( &$this, 'create_menu' ) ); //creates the subnav left menu

		//Adding "embed form" button
		add_action( 'media_buttons', array( &$this, 'add_form_button' ), 30 );

		if ( in_array( RG_CURRENT_PAGE, array( 'post.php', 'page.php', 'page-new.php', 'post-new.php' ) ) ) {
			add_action( 'admin_footer', array( &$this, 'add_mce_popup' ) );
			wp_enqueue_script( 'jquery-ui-datepicker' );
		}

		if ( ! empty( $settings['modify_admin'] ) ) {
			add_action( 'admin_head', array( &$this, 'admin_head' ), 1 );
		}

		add_action( 'gform_entries_first_column_actions', array( $this, 'add_edit_entry_link' ), 10, 5 );

		add_action( 'gform_entry_list_bulk_actions', array( $this, 'add_bulk_actions' ), 10, 2 );

		add_action( 'gform_entry_list_action', array( $this, 'process_bulk_update' ), 10, 3 );

	}

	/**
	 * Add Approve and Disapprove bulk actions to the entries dropdown
	 * @param array $actions
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function add_bulk_actions( $actions = array(), $form_id = 0 ) {

		$actions[ 'approve-' . $form_id ] = esc_html__( 'Approve', 'gravity-forms-addons' );
		$actions[ 'unapprove-' . $form_id ] = esc_html__( 'Disapprove', 'gravity-forms-addons' );

		return $actions;
	}

	/**
	 * Fires after the default entry list actions have been processed.
	 *
	 * Requires Gravity Forms 2.2.4
	 *
	 * @param string $action  Action being performed.
	 * @param array  $entries The entry IDs the action is being applied to.
	 * @param int    $form_id The current form ID.
	 *
	 * @return void
	 */
	public static function process_bulk_update( $bulk_action = '', $entries = array(), $form_id = 0 ) {

		$bulk_action = explode( '-', $bulk_action );

		if ( ! in_array( $bulk_action[0], array( 'approve', 'unapprove' ) ) || ! isset( $bulk_action[1] ) || intval( $bulk_action[1] ) !== intval( $form_id ) ) {
			return;
		}

		$message = '';

		$entries = array_map( 'intval', $entries );

		$entry_count = count( $entries ) > 1 ? sprintf( __( '%d entries', 'gravityforms' ), count( $entries ) ) : __( '1 entry', 'gravityforms' );

		switch ( $bulk_action[0] ) {
			case 'approve':
				self::directory_update_bulk( $entries, 1, $bulk_action[1] );
				$message = sprintf( __( '%s approved.', 'gravity-forms-addons' ), $entry_count );
				break;

			case 'unapprove':
				self::directory_update_bulk( $entries, 0, $bulk_action[1] );
				$message = sprintf( __( '%s disapproved.', 'gravity-forms-addons' ), $entry_count );
				break;
		}

		if ( $message ) {
			echo '<div id="message" class="updated notice is-dismissible"><p>' . $message . '</p></div>';
		}
	}


	private static function directory_update_bulk( $leads, $approved, $form_id ) {

		if ( empty( $leads ) || ! is_array( $leads ) ) {
			return false;
		}

		$approvedcolumn = GFDirectory::globals_get_approved_column( $form_id );

		$approved = empty( $approved ) ? 0 : 'Approved';
		foreach ( $leads as $lead_id ) {
			GFDirectory::directory_update_approved( $lead_id, $approved, $form_id, $approvedcolumn );
		}
	}

	// If the classes don't exist, the plugin won't do anything useful.
	public function gf_warning() {
		global $pagenow;
		$message = '';

		if ( 'plugins.php' != $pagenow ) {
			return;
		}

		if ( ! GFDirectory::is_gravityforms_installed() ) {
			if ( file_exists( WP_PLUGIN_DIR . '/gravityforms/gravityforms.php' ) ) {
				$message .= sprintf( esc_html__( '%1$sGravity Forms is installed but not active. %2$sActivate Gravity Forms%3$s to use the Gravity Forms Directory plugin.%4$s', 'gravity-forms-addons' ), '<p>', '<a href="' . esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=gravityforms/gravityforms.php' ), 'activate-plugin_gravityforms/gravityforms.php' ) ) . '" style="font-weight:strong;">', '</a>', '</p>' );
			} else {
				$message = sprintf(
					esc_html__(
						'%sGravity Forms cannot be found%s

				The %sGravity Forms plugin%s must be installed and activated for the Gravity Forms Directory plugin to work.

				If you haven\'t installed the plugin, you can %3$spurchase the plugin here%4$s. If you have, and you believe this notice is in error, %5$sstart a topic on the plugin support forum%4$s.

				%6$s%7$sBuy Gravity Forms%4$s%8$s
				',
						'gravity-forms-addons'
					),
					'<strong>',
					'</strong>',
					"<a href='https://katz.si/gravityforms'>",
					'</a>',
					'<a href="https://wordpress.org/tags/gravity-forms-addons?forum_id=10#postform">',
					'<p class="submit">',
					"<a href='https://katz.si/gravityforms' style='color:white!important' class='button button-primary'>",
					'</p>'
				);
			}
		}
		if ( ! empty( $message ) ) {
			echo '<div id="message" class="error">' . wpautop( $message ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else if ( $message = get_transient( 'kws_gf_activation_notice' ) ) {
			echo '<div id="message" class="updated">' . wpautop( $message ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			delete_transient( 'kws_gf_activation_notice' );
		}
	}

	public function admin_head( $settings = array() ) {
		if ( empty( $settings ) ) {
			$settings = GFDirectory::get_settings();
		}

		if ( ! empty( $settings['modify_admin']['expand'] ) ) {
			if ( isset( $_REQUEST['page'] ) && 'gf_edit_forms' == $_REQUEST['page'] && isset( $_REQUEST['id'] ) && is_numeric( $_REQUEST['id'] ) ) {
				$style = '<style>
					.gforms_edit_form_expanded ul.menu li.add_field_button_container ul,
					.gforms_edit_form_expanded ul.menu li.add_field_button_container ul ol {
						display:block!important;
					}
					#floatMenu {padding-top:1.4em!important;}
				</style>';
				$style = apply_filters( 'kws_gf_display_all_fields', $style );
				echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		if ( GFDirectory::is_gravity_page( 'gf_entries' ) || GFDirectory::is_gravity_page( 'gf_edit_forms' ) ) {
			self::add_edit_js( isset( $_REQUEST['id'] ), $settings );
		}
	}

	private static function add_edit_js( $edit_forms = false, $settings = array() ) {
		?>
		<script>
			// Edit link for Gravity Forms entries
			jQuery( document ).ready( function ( $ ) {

				$('select[id^=bulk-action-selector-]').each(function() {
					var $optgroup = $('<optgroup label="<?php esc_attr_e( 'Directory', 'gravity-forms-addons' ); ?>"></optgroup>');

					$('option[value^="approve-"]', $( this ) ).remove().appendTo( $optgroup );
					$('option[value^="unapprove-"]', $( this ) ).remove().appendTo( $optgroup );

					$( this ).append( $optgroup );
				});

				<?php if ( ! empty( $settings['modify_admin']['expand'] ) && $edit_forms ) { ?>
				var onScrollScript = window.onscroll;
				$( 'div.gforms_edit_form #add_fields #floatMenu' ).prepend( '<div class="gforms_expend_all_menus_form"><label for="expandAllMenus"><input type="checkbox" id="expandAllMenus" value="1" /> Expand All Menus</label></div>' );

				$( 'input#expandAllMenus' ).on( 'click', function ( e ) {
					if ( $( this ).is( ':checked' ) ) {
						window.onscroll = '';
						$( 'div.gforms_edit_form' ).addClass( 'gforms_edit_form_expanded' );
						//$('ul.menu li .button-title-link').unbind().die(); // .unbind() is for the initial .click()... .die() is for the live() below
					} else {
						window.onscroll = onScrollScript;
						$( 'div.gforms_edit_form' ).removeClass( 'gforms_edit_form_expanded' );
					}
				} );

					<?php
				}
				if ( ! empty( $settings['modify_admin']['toggle'] ) && $edit_forms ) {
					?>

				$( 'ul.menu' ).addClass( 'noaccordion' );
					<?php
				}
				?>
			} );
		</script>
		<?php
	}

	public function add_edit_entry_link( $form_id, $field_id, $value, $entry, $query_string ) {

		$settings = GFDirectory::get_settings();

		if ( ! empty( $settings['modify_admin']['ids'] ) ) {

			$field_id_url = GF_DIRECTORY_URL . 'includes/views/html-field-ids.php';
			$field_id_url = add_query_arg(
				array(
					'id' => $form_id,
					'show_field_ids' => 'true',
					'TB_iframe' => 'true',
					'height' => 295,
					'width' => 370,
				),
				$field_id_url
			);
			?>
			<span class="edit"> | <a title="<?php esc_attr( printf( __( 'Fields for Form ID %s', 'gravity-forms-addons' ), $form_id ) ); ?>" href="<?php echo esc_url( $field_id_url ); ?>" class="thickbox form_ids"><?php esc_attr_e( 'IDs', 'gravity-forms-addons' ); ?></a></span>
			<?php
		}

		if ( ! empty( $settings['modify_admin']['edit'] ) ) {

			$edit_entry_link = admin_url( 'admin.php' ) . '?screen_mode=edit&' . $query_string;
			?>
			<span class="edit"> | <a title="<?php esc_attr_e( 'Edit this entry', 'gravity-forms-addons' ); ?>" href="<?php echo esc_url( $edit_entry_link ); ?>"><?php esc_attr_e( 'Edit', 'gravity-forms-addons' ); ?></a></span>
			<?php
		}
	}

	/**
	 * @param array $form
	 *
	 * @return array|mixed|null
	 */
	public static function show_field_ids( $form = array() ) {
		if ( isset( $_REQUEST['show_field_ids'] ) && isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) {
			$form = GFAPI::get_form( $_GET['id'] );

			echo <<<EOD
		<style>

			#input_ids th, #input_ids td { border-bottom:1px solid #999; padding:.25em 15px; }
			#input_ids th { border-bottom-color: #333; font-size:.9em; background-color: #464646; color:white; padding:.5em 15px; font-weight:bold;  }
			#input_ids { background:#ccc; margin:0 auto; font-size:1.2em; line-height:1.4; width:100%; border-collapse:collapse;  }
			#input_ids strong { font-weight:bold; }
			#input_ids caption,
			#preview_hdr { display:none;}
			#input_ids caption { color:white!important;}
		</style>
EOD;

			if ( ! empty( $form ) ) {
				echo '<table id="input_ids"><caption id="input_id_caption">Fields for <strong>Form ID ' . $form['id'] . '</strong></caption><thead><tr><th>Field Name</th><th>Field ID</th></thead><tbody>';
			}
			foreach ( $form['fields'] as $field ) {
				// If there are multiple inputs for a field; ie: address has street, city, zip, country, etc.
				if ( is_array( $field['inputs'] ) ) {
					foreach ( $field['inputs'] as $input ) {
						echo "<tr><td width='50%'><strong>{$input['label']}</strong></td><td>{$input['id']}</td></tr>";
					}
				} // Otherwise, it's just the one input.
				else {
					echo "<tr><td width='50%'><strong>{$field['label']}</strong></td><td>{$field['id']}</td></tr>";
				}
			}
			if ( ! empty( $form ) ) {
				echo '</tbody></table><div style="clear:both;"></div></body></html>';
				exit();
			}
		} else {
			return $form;
		}
	}

	public function add_mce_popup() {

		//Action target that displays the popup to insert a form to a post/page
		include_once( GF_DIRECTORY_PATH . 'includes/views/html-mce-popup-admin.php' );
	}

	public static function make_popup_options( $js = false ) {
		$i = 0;

		$defaults = GFDirectory::directory_defaults();

		$standard = array(
			array(
				'text',
				'page_size',
				20,
				sprintf( esc_html__( 'Number of entries to show at once. Use %1$s0%2$s to show all entries.', 'gravity-forms-addons' ), '<code>', '</code>' ),
			),
			array(
				'select',
				'directoryview',
				array(
					array(
						'value' => 'table',
						'label' => esc_html__( 'Table', 'gravity-forms-addons' ),
					),
					array(
						'value' => 'ul',
						'label' => esc_html__( 'Unordered List', 'gravity-forms-addons' ),
					),
					array(
						'value' => 'dl',
						'label' => esc_html__( 'Definition List', 'gravity-forms-addons' ),
					),
				),
				esc_html__( 'Format for directory listings (directory view)', 'gravity-forms-addons' ),
			),
			array(
				'select',
				'entryview',
				array(
					array(
						'value' => 'table',
						'label' => esc_html__( 'Table', 'gravity-forms-addons' ),
					),
					array(
						'value' => 'ul',
						'label' => esc_html__( 'Unordered List', 'gravity-forms-addons' ),
					),
					array(
						'value' => 'dl',
						'label' => esc_html__( 'Definition List', 'gravity-forms-addons' ),
					),
				),
				esc_html__( 'Format for single entries (single entry view)', 'gravity-forms-addons' ),
			),
			array( 'checkbox', 'search', true, esc_html__( 'Show the search field', 'gravity-forms-addons' ) ),
			array(
				'checkbox',
				'smartapproval',
				true,
				esc_html__( 'Automatically convert directory into Approved-only when an Approved field is detected.', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'approved',
				false,
				sprintf( esc_html__( "(If Smart Approval above is not enabled) Show only entries that have been Approved (have a field in the form that is an Admin-only checkbox with a value of 'Approved'). %1\$sNote:%2\$s This will hide entries that have not been explicitly approved.%3\$s", 'gravity-forms-addons' ), "<span class='description'><strong>", '</strong>', '</span>' ),
			),
		);
		if ( ! $js ) {
			echo '<ul>';
			foreach ( $standard as $o ) {
				self::make_field( $o[0], $o[1], maybe_serialize( $o[2] ), $o[3], $defaults );
			}
			echo '</ul>';
		} else {
			foreach ( $standard as $o ) {
				$out[ $i ] = self::make_popup_js( $o[0], $o[1], $defaults );
				$i ++;
			}
		}

		$content = array(
			array(
				'checkbox',
				'entry',
				true,
				esc_html__( "If there's a displayed Entry ID column, add link to each full entry", 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'getimagesize',
				false,
				esc_html__( 'Calculate image sizes (Warning: this may slow down the directory loading speed!)', 'gravity-forms-addons' ),
			),
			array(
				'radio',
				'postimage',
				array(
					array(
						'label'   => '<img src="' . GFCommon::get_base_url() . '/images/doctypes/icon_image.gif" /> ' . esc_html__( 'Show image icon', 'gravity-forms-addons' ),
						'value'   => 'icon',
						'default' => '1',
					),
					array(
						'label' => esc_html__( 'Show full image', 'gravity-forms-addons' ),
						'value' => 'image',
					),
				),
				esc_html__( 'How do you want images to appear in the directory?', 'gravity-forms-addons' ),
			),
			#array('checkbox', 'fulltext' , true, esc_html__("Show full content of a textarea or post content field, rather than an excerpt", 'gravity-forms-addons')),

			array(
				'date',
				'start_date',
				false,
				sprintf( esc_html__( 'Start date (in %1$sYYYY-MM-DD%2$s format)', 'gravity-forms-addons' ), '<code>', '</code>' ),
			),
			array(
				'date',
				'end_date',
				false,
				sprintf( esc_html__( 'End date (in %1$sYYYY-MM-DD%2$s format)', 'gravity-forms-addons' ), '<code>', '</code>' ),
			),
		);

		$administration = array(
			array(
				'checkbox',
				'showadminonly',
				false,
				sprintf( esc_html__( 'Show Admin-Only columns %1$s(in Gravity Forms, Admin-Only fields are defined by clicking the Advanced tab on a field in the Edit Form view, then editing Visibility > Admin Only)%2$s', 'gravity-forms-addons' ), "<span class='description'>", '</span>' ),
			),
			array(
				'checkbox',
				'useredit',
				false,
				esc_html__( "Allow logged-in users to edit entries they created. Will add an 'Edit Your Entry' field to the Single Entry View.", 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'limituser',
				false,
				esc_html__( 'Only display entries created by the currently logged-in user (users will not see entries created by other people).', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'adminedit',
				false,
				sprintf( esc_html__( 'Allow %1$sadministrators%2$s to edit all entries. Will add an \'Edit Your Entry\' field to the Single Entry View.', 'gravity-forms-addons' ), '<strong>', '</strong>' ),
			),
		);

		$style_label = esc_html_x( 'Style %d', 'Lightbox style', 'gravity-forms-addons' );

		$lightbox = array(

			array(
				'radio',
				'lightboxstyle',
				array(
					array(
						'label' => sprintf( $style_label, 1 ) . ' <a href="http://www.jacklmoore.com/colorbox/example1/" target="_blank">See example</a>',
						'value' => '1',
					),
					array(
						'label' => sprintf( $style_label, 2 ) . ' <a href="http://www.jacklmoore.com/colorbox/example2/" target="_blank">See example</a>',
						'value' => '2',
					),
					array(
						'label'   => sprintf( $style_label, 3 ) . ' <a href="http://www.jacklmoore.com/colorbox/example3/" target="_blank">See example</a>',
						'value'   => '3',
						'default' => '1',
					),
					array(
						'label' => sprintf( $style_label, 4 ) . ' <a href="http://www.jacklmoore.com/colorbox/example4/" target="_blank">See example</a>',
						'value' => '4',
					),
					array(
						'label' => sprintf( $style_label, 5 ) . ' <a href="http://www.jacklmoore.com/colorbox/example5/" target="_blank">See example</a>',
						'value' => '5',
					),
				),
				'What style should the lightbox use?',
			),
			array(
				'checkboxes',
				'lightboxsettings',
				array(
					array(
						'label'   => esc_html__( 'Images', 'gravity-forms-addons' ),
						'value'   => 'images',
						'default' => '1',
					),
					array(
						'label' => esc_html__( 'Entry Links (Open entry details in lightbox)' ),
						'value' => 'entry',
					),
					array(
						'label' => esc_html__( 'Website Links (non-entry)', 'gravity-forms-addons' ),
						'value' => 'urls',
					),
				),
				esc_html__( 'Set what type of links should be loaded in the lightbox', 'gravity-forms-addons' ),
			),
		);

		$formatting = array(
			array(
				'checkbox',
				'jstable',
				false,
				esc_html__( 'Use the TableSorter jQuery plugin to sort the table?', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'titleshow',
				true,
				'<strong>' . esc_html__( 'Show a form title?', 'gravity-forms-addons' ) . '</strong> ' . esc_html__( 'By default, the title will be the form title.', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'showcount',
				true,
				esc_html__( "Do you want to show 'Displaying 1-19 of 19'?", 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'thead',
				true,
				sprintf( esc_html__( 'Show the top heading row (%1$s&lt;thead&gt;%2$s)', 'gravity-forms-addons' ), '<code>', '</code>' ),
			),
			array(
				'checkbox',
				'tfoot',
				true,
				sprintf( esc_html__( 'Show the bottom heading row (%1$s&lt;tfoot&gt;%2$s)', 'gravity-forms-addons' ), '<code>', '</code>' ),
			),
			array(
				'checkbox',
				'pagelinksshowall',
				true,
				esc_html__( 'Show each page number (eg: 1 2 3 4 5 6 7 8) instead of summary (eg: 1 2 3 ... 8 &raquo;)', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'jssearch',
				true,
				sprintf( esc_html__( 'Use JavaScript for sorting (otherwise, %1$slinks%2$s will be used for sorting by column)', 'gravity-forms-addons' ), '<em>', '</em>' ),
			),
			array(
				'checkbox',
				'dateformat',
				false,
				esc_html__( 'Override the options from Gravity Forms, and use standard PHP date formats', 'gravity-forms-addons' ),
			),
		);

		$links = array(
			array(
				'checkbox',
				'linkemail',
				true,
				esc_html__( 'Convert email fields to email links', 'gravity-forms-addons' ),
			),
			array( 'checkbox', 'linkwebsite', true, esc_html__( 'Convert URLs to links', 'gravity-forms-addons' ) ),
			array(
				'checkbox',
				'truncatelink',
				false,
				sprintf( esc_html__( 'Show more simple links for URLs (strip %1$shttp://%2$s, %3$swww.%4$s, etc.)', 'gravity-forms-addons' ), '<code>', '</code>', '<code>', '</code>' ),
			),    #'truncatelink' => false,
			array(
				'checkbox',
				'linknewwindow',
				false,
				sprintf( esc_html__( '%1$sOpen links in new window?%2$s (uses %3$s)', 'gravity-forms-addons' ), '<strong>', '</strong>', "<code>target='_blank'</code>" ),
			),
			array(
				'checkbox',
				'nofollowlinks',
				false,
				sprintf( esc_html__( '%1$sAdd %2$snofollow%3$s to all links%4$s, including emails', 'gravity-forms-addons' ), '<strong>', '<code>', '</code>', '</strong>' ),
			),
		);

		$address = array(
			array(
				'checkbox',
				'appendaddress',
				false,
				esc_html__( 'Add the formatted address as a column at the end of the table', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'hideaddresspieces',
				false,
				esc_html__( 'Hide the pieces that make up an address (Street, City, State, ZIP, Country, etc.)', 'gravity-forms-addons' ),
			),
		);

		$entry = array(
			array(
				'text',
				'entrytitle',
				esc_html__( 'Entry Detail', 'gravity-forms-addons' ),
				esc_html__( 'Title of entry lightbox window', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'entrydetailtitle',
				sprintf( esc_html__( 'Entry Detail Table Caption', 'gravity-forms-addons' ), esc_html__( 'The text displayed at the top of the entry details. Use %1$s%%%%formtitle%%%%%2$s and %s%%%%leadid%%%%%s as variables that will be replaced.', 'gravity-forms-addons' ), '<code>', '</code>', '<code>', '</code>' ),
			),
			array(
				'text',
				'entrylink',
				esc_html__( 'View entry details', 'gravity-forms-addons' ),
				esc_html__( 'Link text to show full entry', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'entryth',
				esc_html__( 'More Info', 'gravity-forms-addons' ),
				esc_html__( 'Entry ID column title', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'entryback',
				esc_html__( '&larr; Back to directory', 'gravity-forms-addons' ),
				esc_html__( 'The text of the link to return to the directory view from the single entry view.', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'entryonly',
				true,
				esc_html__( 'When viewing full entry, show entry only? Otherwise, show entry with directory below', 'gravity-forms-addons' ),
			),
			array(
				'checkbox',
				'entryanchor',
				true,
				esc_html__( 'When returning to directory view from single entry view, link to specific anchor row?', 'gravity-forms-addons' ),
			),
		);

		$fieldsets = array(
			esc_html__( 'Content Settings', 'gravity-forms-addons' )          => $content,
			esc_html__( 'Administration of Entries', 'gravity-forms-addons' ) => $administration,
			esc_html__( 'Lightbox Options', 'gravity-forms-addons' )          => $lightbox,
			esc_html__( 'Formatting Options', 'gravity-forms-addons' )        => $formatting,
			esc_html__( 'Link Settings', 'gravity-forms-addons' )             => $links,
			esc_html__( 'Address Options', 'gravity-forms-addons' )           => $address,
		);

		if ( ! $js ) {
			echo '<a href="#kws_gf_advanced_settings" class="kws_gf_advanced_settings">' . esc_html__( 'Show advanced settings', 'gravity-forms-addons' ) . '</a>';
			echo '<div style="display:none;" id="kws_gf_advanced_settings">';
			echo "<h2 style='margin:0; padding:0; font-weight:bold; font-size:1.5em; margin-top:1em;'>Single-Entry View</h2>";
			echo '<span class="howto">These settings control whether users can view each entry as a separate page or lightbox. Single entries will show all data associated with that entry.</span>';
			echo '<ul style="padding:0 15px 0 15px; width:100%;">';
			foreach ( $entry as $o ) {
				if ( isset( $o[3] ) ) {
					$o3 = esc_html( $o[3] );
				} else {
					$o3 = '';
				}
				self::make_field( $o[0], $o[1], maybe_serialize( $o[2] ), $o3, $defaults );
			}
			echo '</ul>';

			echo '<div class="hr-divider label-divider"></div>';

			echo "<h2 style='margin:0; padding:0; font-weight:bold; font-size:1.5em; margin-top:1em;'>" . esc_html__( 'Directory View', 'gravity-forms-addons' ) . '</h2>';
			echo '<span class="howto">' . esc_html__( 'These settings affect how multiple entries are shown at once.', 'gravity-forms-addons' ) . '</span>';

			foreach ( $fieldsets as $title => $fieldset ) {
				echo "<fieldset><legend><h3 style='padding-top:1em; padding-bottom:.5em; margin:0;'>{$title}</h3></legend>";
				echo '<ul style="padding: 0 15px 0 15px; width:100%;">';
				foreach ( $fieldset as $o ) {
					self::make_field( $o[0], $o[1], maybe_serialize( $o[2] ), $o[3], $defaults );
				}
				echo '</ul></fieldset>';
				echo '<div class="hr-divider label-divider"></div>';
			}
			echo "<h2 style='margin:0; padding:0; font-weight:bold; font-size:1.5em; margin-top:1em;'>" . esc_html__( 'Additional Settings', 'gravity-forms-addons' ) . '</h2>';
			echo '<span class="howto">' . esc_html__( 'These settings affect both the directory view and single entry view.', 'gravity-forms-addons' ) . '</span>';
			echo '<ul style="padding: 0 15px 0 15px; width:100%;">';
		} else {
			foreach ( $entry as $o ) {
				$out[ $i ] = self::make_popup_js( $o[0], $o[1], $defaults );
				$i ++;
			}
			foreach ( $fieldsets as $title => $fieldset ) {
				foreach ( $fieldset as $o ) {
					$out[ $i ] = self::make_popup_js( $o[0], $o[1], $defaults );
					$i ++;
				}
			}
		}
		$advanced = array(
			array(
				'text',
				'tableclass',
				'gf_directory widefat',
				esc_html__( 'Class for the <table>, <ul>, or <dl>', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'tablestyle',
				'',
				esc_html__( 'inline CSS for the <table>, <ul>, or <dl>', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'rowclass',
				'',
				esc_html__( 'Class for the <table>, <ul>, or <dl>', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'rowstyle',
				'',
				esc_html__( "Inline CSS for all <tbody><tr>'s, <ul><li>'s, or <dl><dt>'s", 'gravity-forms-addons' ),
			),
			array(
				'text',
				'valign',
				'baseline',
				esc_html__( 'Vertical align for table cells', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'sort',
				'date_created',
				esc_html__( 'Use the input ID ( example: 1.3 or 7 or ip)', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'dir',
				'DESC',
				sprintf( esc_html__( 'Sort in ascending order (%1$sASC%2$s or descending (%3$sDESC%4$s)', 'gravity-forms-addons' ), '<code>', '</code>', '<code>', '</code>' ),
			),
			array(
				'text',
				'startpage',
				1,
				esc_html__( 'If you want to show page 8 instead of 1', 'gravity-forms-addons' ),
			),
			array(
				'text',
				'pagelinkstype',
				'plain',
				sprintf( esc_html__( 'Type of pagination links. %1$splain%2$s is just a string with the links separated by a newline character. The other possible values are either %3$sarray%4$s or %5$slist%6$s.', 'gravity-forms-addons' ), '<code>', '</code>', '<code>', '</code>', '<code>', '</code>' ),
			),
			array(
				'text',
				'titleprefix',
				'Entries for ',
				esc_html__( "Default GF behavior is 'Entries : '", 'gravity-forms-addons' ),
			),
			array(
				'text',
				'tablewidth',
				'100%',
				esc_html__( "Set the 'width' attribute for the <table>, <ul>, or <dl>", 'gravity-forms-addons' ),
			),
			array(
				'text',
				'datecreatedformat',
				get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ),
				sprintf( esc_html__( 'Use %1$sstandard PHP date formats%2$s', 'gravity-forms-addons' ), "<a href='http://php.net/manual/en/function.date.php' target='_blank'>", '</a>' ),
			),
			array(
				'checkbox',
				'credit',
				true,
				esc_html__( 'Give credit to the plugin creator (who has spent over 300 hours on this free plugin!) with a link at the bottom of the directory', 'gravity-forms-addons' ),
			),
		);
		if ( ! $js ) {
			foreach ( $advanced as $o ) {
				self::make_field( $o[0], $o[1], maybe_serialize( $o[2] ), $o[3], $defaults );
			}
			echo '</ul></fieldset></div>';
		} else {
			foreach ( $advanced as $o ) {
				$out[ $i ] = self::make_popup_js( $o[0], $o[1], $defaults );
				$i ++;
			}

			return $out;
		}
	}

	public static function make_field( $type, $id, $default, $label, $defaults = array() ) {
		$rawid   = $id;
		$idLabel = '';
		if ( GFDirectory::is_gravity_page( 'gf_settings' ) ) {
			$id      = 'gf_addons_directory_defaults[' . $id . ']';
			$idLabel = " <span style='color:#868686'>(<pre style='display:inline'>{$rawid}</pre>)</span>";
		}
		$checked = '';
		$label   = str_replace( '&lt;code&gt;', '<code>', str_replace( '&lt;/code&gt;', '</code>', $label ) );
		$output  = '<li class="setting-container" style="width:90%; clear:left; border-bottom: 1px solid #cfcfcf; padding:.25em .25em .4em; margin-bottom:.25em;">';
		$default = maybe_unserialize( $default );

		$class = '';
		if ( 'date' == $type ) {
			$type  = 'text';
			$class = ' class="gf_addons_datepicker datepicker"';
		}

		if ( 'checkbox' == $type ) {
			if ( ! empty( $defaults[ "{$rawid}" ] ) || ( $defaults[ "{$rawid}" ] === '1' || $defaults[ "{$rawid}" ] === 1 ) ) {
				$checked = ' checked="checked"';
			}
			$output .= '<label for="gf_settings_' . $rawid . '"><input type="hidden" value="" name="' . $id . '" /><input type="checkbox" id="gf_settings_' . $rawid . '"' . $checked . ' name="' . $id . '" /> ' . $label . $idLabel . '</label>' . "\n";
		} elseif ( 'text' == $type ) {
			$default = $defaults[ "{$rawid}" ];
			$output .= '<label for="gf_settings_' . $rawid . '"><input type="text" id="gf_settings_' . $rawid . '" value="' . htmlspecialchars( stripslashes( $default ) ) . '" style="width:40%;" name="' . $id . '"' . $class . ' /> <span class="howto">' . $label . $idLabel . '</span></label>' . "\n";
		} elseif ( $type == 'radio' || $type == 'checkboxes' ) {
			if ( is_array( $default ) ) {
				$output .= $label . $idLabel . '<ul class="ul-disc">';
				foreach ( $default as $opt ) {
					if ( $type == 'radio' ) {
						$id_opt = $id . '_' . sanitize_title( $opt['value'] );
						if ( ! empty( $defaults[ "{$rawid}" ] ) && $defaults[ "{$rawid}" ] == $opt['value'] ) {
							$checked = ' checked="checked"';
						} else {
							$checked = '';
						}
						$inputtype = 'radio';
						$name      = $id;
						$value     = $opt['value'];
						$output .= '
						<li><label for="gf_settings_' . $id_opt . '">';
					} else {
						$id_opt = $rawid . '_' . sanitize_title( $opt['value'] );
						if ( ! empty( $defaults[ "{$rawid}" ][ sanitize_title( $opt['value'] ) ] ) ) {
							$checked = ' checked="checked"';
						} else {
							$checked = '';
						}
						$inputtype = 'checkbox';
						$name      = $id . '[' . sanitize_title( $opt['value'] ) . ']';
						$value     = 1;
						$output .= '
							<li><label for="gf_settings_' . $id_opt . '">
								<input type="hidden" value="0" name="' . $name . '" />';
					}
					$output .= '
							<input type="' . $inputtype . '"' . $checked . ' value="' . $value . '" id="gf_settings_' . $id_opt . '" name="' . $name . '" /> ' . $opt['label'] . " <span style='color:#868686'>(<pre style='display:inline'>" . sanitize_title( $opt['value'] ) . '</pre>)</span>' . '
						</label>
					</li>' . "\n";
				}
				$output .= '</ul>';
			}
		} elseif ( $type == 'select' ) {
			if ( is_array( $default ) ) {
				$output .= '
				<label for="gf_settings_' . $rawid . '">' . $label . '
				<select name="' . $id . '" id="gf_settings_' . $rawid . '">';
				foreach ( $default as $opt ) {

					if ( ! empty( $defaults[ "{$rawid}" ] ) && $defaults[ "{$rawid}" ] == $opt['value'] ) {
						$checked = ' selected="selected"';
					} else {
						$checked = '';
					}
					$id_opt = $id . '_' . sanitize_title( $opt['value'] );
					$output .= '<option' . $checked . ' value="' . $opt['value'] . '"> ' . $opt['label'] . '</option>' . "\n";
				}
				$output .= '</select>' . $idLabel . '
				</label>
				';
			} else {
				$output = '';
			}
		}
		if ( ! empty( $output ) ) {
			$output .= '</li>' . "\n";
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public static function make_popup_js( $type, $id, $defaults ) {

		foreach ( $defaults as $key => $default ) {
			if ( true === $default || 'on' === $default ) {
				$defaults[ $key ] = 'true';
			} elseif ( false === $default || ( 'checkbox' == $type && empty( $default ) ) ) {
				$defaults[ $key ] = 'false';
			}
		}
		$defaultsArray = array();
		if ( $type == 'checkbox' ) {
			$js = 'var ' . $id . ' = jQuery("#gf_settings_' . $id . '").is(":checked") ? "true" : "false";';
		} elseif ( $type == 'checkboxes' && is_array( $defaults[ "{$id}" ] ) ) {
			$js = '';
			$i  = 0;
			$js .= "\n\t\t\tvar " . $id . ' = new Array();';
			foreach ( $defaults[ "{$id}" ] as $key => $value ) {
				$defaultsArray[] = $key;
				$js .= "\n\t\t\t" . $id . '[' . $i . '] = jQuery("input#gf_settings_' . $id . '_' . $key . '").is(":checked") ? "' . $key . '" : null;';
				$i ++;
			}
		} elseif ( $type == 'text' || $type == 'date' ) {
			$js = 'var ' . $id . ' = jQuery("#gf_settings_' . $id . '").val();';
		} elseif ( $type == 'radio' ) {
			$js = '
			if(jQuery("input[name=\'' . $id . '\']:checked").length > 0) {
				var ' . $id . ' = jQuery("input[name=\'' . $id . '\']:checked").val();
			} else {
				var ' . $id . ' = jQuery("input[name=\'' . $id . '\']").eq(0).val();
			}';
		} elseif ( $type == 'select' ) {
			$js = '
			if(jQuery("select[name=\'' . $id . '\']:selected").length > 0) {
				var ' . $id . ' = jQuery("select[name=\'' . $id . '\']:selected").val();
			} else {
				var ' . $id . ' = jQuery("select[name=\'' . $id . '\']").eq(0).val();
			}';
		}
		$set = '';
		if ( ! is_array( $defaults[ "{$id}" ] ) ) {
			$idCode = $id . '=\""+' . $id . '+"\"';
			$set    = 'var ' . $id . 'Output = (jQuery.trim(' . $id . ') == "' . trim( addslashes( stripslashes( $defaults[ "{$id}" ] ) ) ) . '") ? "" : " ' . $idCode . '";';
		} else {

			$idCode2 = $id . '.join()';
			$idCode  = '"' . $idCode2 . '"';
			$set     = '
			' . $id . ' =  jQuery.grep(' . $id . ',function(n){ return(n); });
			var ' . $id . 'Output = (jQuery.trim(' . $idCode2 . ') === "' . implode( ',', $defaultsArray ) . '") ? "" : " ' . $id . '=\""+ ' . $idCode2 . '+"\"";';
		}
		// Debug

		$return = array(
			'js'       => $js,
			'id'       => $id,
			'idcode'   => $idCode,
			'setvalue' => $set,
		);

		return $return;
	}

	public function add_form_button() {

		$output = '<a href="#TB_inline?width=640&amp;inlineId=select_gf_directory" class="thickbox button select_gf_directory gform_media_link" id="add_gform" title="' . esc_attr__( 'Add a Gravity Forms Directory', 'gravity-forms-addons' ) . '"><span class="dashicons dashicons-welcome-widgets-menus" style="line-height: 26px;"></span> ' . esc_html__( 'Add Directory', 'gravityforms' ) . '</a>';

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	//Creates directory left nav menu under Forms
	public function create_menu( $menus ) {
		// Adding submenu if user has access
		$permission = GFDirectory::has_access( 'gravityforms_directory' );
		if ( ! empty( $permission ) ) {
			$menus[] = array(
				'name'       => 'gf_settings&subview=Directory',
				'label'      => esc_html__( 'Directory', 'gravity-forms-addons' ),
				'callback'   => array( &$this, 'settings_page' ),
				'permission' => $permission,
			);
		}

		return $menus;
	}

	public function settings_page() {
		$message = $validimage = false;
		global $plugin_page;

		if ( isset( $_POST['gf_addons_submit'] ) ) {
			check_admin_referer( 'update', 'gf_directory_update' );

			$directory_defaults = $_POST['gf_addons_directory_defaults'];
			$directory_defaults = map_deep( $directory_defaults, array( $this, 'sanitize_setting_text' ) );

			$settings = array(
				'directory'          => isset( $_POST['gf_addons_directory'] ),
				'referrer'           => isset( $_POST['gf_addons_referrer'] ),
				'directory_defaults' => GFDirectory::directory_defaults( $directory_defaults, true ),
				'modify_admin'       => isset( $_POST['gf_addons_modify_admin'] ) ? $_POST['gf_addons_modify_admin'] : array(),
				'version'            => GF_DIRECTORY_VERSION,
				'saved'              => true,
			);
			$message  = esc_html__( 'Settings saved.', 'gravity-forms-addons' );
			update_option( 'gf_addons_settings', $settings );
		} else {
			$settings = GFDirectory::get_settings();
		}

		include_once( GF_DIRECTORY_PATH . 'includes/views/html-gf-directory-settings-admin.php' );
	}

	/**
     * Allow HTML, but not unsafe HTML, in settings
     *
     * @uses wp_kses()
     *
	 * @since 4.2
     *
     * @param string $setting_text
     *
     * @return string, run through wp_kses()
	 */
	public function sanitize_setting_text( $setting_text ) {
	    $allowed_protocols   = wp_allowed_protocols();
        $allowed_protocols[] = 'data';

        return wp_kses( $setting_text, array(
            'a'       => array(
                'class'       => array(),
                'id'          => array(),
                'href'        => array(),
                'title'       => array(),
                'rel'         => array(),
                'target'      => array(),
                'data-toggle' => array(),
                'data-access' => array(),
            ),
            'img'     => array(
                'class' => array(),
                'id'    => array(),
                'src'   => array(),
                'href'  => array(),
                'alt'   => array(),
                'title' => array(),
            ),
            'span'    => array( 'class' => array(), 'id' => array(), 'title' => array(), 'data-toggle' => array() ),
            'label'   => array( 'class' => array(), 'id' => array(), 'for' => array() ),
            'code'    => array( 'class' => array(), 'id' => array() ),
            'tt'      => array( 'class' => array(), 'id' => array() ),
            'pre'     => array( 'class' => array(), 'id' => array() ),
            'table'   => array( 'class' => array(), 'id' => array() ),
            'thead'   => array(),
            'tfoot'   => array(),
            'td'      => array( 'class' => array(), 'id' => array(), 'colspan' => array() ),
            'th'      => array( 'class' => array(), 'id' => array(), 'colspan' => array(), 'scope' => array() ),
            'ul'      => array( 'class' => array(), 'id' => array() ),
            'li'      => array( 'class' => array(), 'id' => array() ),
            'p'       => array( 'class' => array(), 'id' => array() ),
            'h1'      => array( 'class' => array(), 'id' => array() ),
            'h2'      => array( 'class' => array(), 'id' => array() ),
            'h3'      => array( 'class' => array(), 'id' => array() ),
            'h4'      => array( 'class' => array(), 'id' => array() ),
            'h5'      => array( 'class' => array(), 'id' => array() ),
            'div'     => array( 'class' => array(), 'id' => array(), 'aria-live' => array() ),
            'small'   => array( 'class' => array(), 'id' => array(), 'data-toggle' => array() ),
            'header'  => array( 'class' => array(), 'id' => array() ),
            'footer'  => array( 'class' => array(), 'id' => array() ),
            'section' => array( 'class' => array(), 'id' => array() ),
            'br'      => array(),
            'strong'  => array(),
            'em'      => array(),
            'input'   => array(
                'class'     => array(),
                'id'        => array(),
                'type'      => array( 'text' ),
                'value'     => array(),
                'size'      => array(),
                'aria-live' => array(),
            ),
            'button'  => array( 'class' => array(), 'id' => array(), 'aria-live' => array() ),
        ),
        $allowed_protocols
        );
	}
}

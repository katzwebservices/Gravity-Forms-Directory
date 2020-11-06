<?php
/**
 * Loads the content of [directory] shortcode.
 *
 * @link       https://gravityview.co
 * @since      TODO
 *
 * @package    gravity-forms-addons
 * @subpackage gravity-forms-addons/includes
 */

class GFDirectory_Shortcode extends GFDirectory {

	/**
	 * Instance of this class.
	 *
	 * @since    TODO
	 *
	 * @var      object
	 */
	protected static $instance = null;

	public function __construct() {
		add_shortcode( 'directory', array( $this, 'make_directory' ) );
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function include_gf_files() {
		if ( ! class_exists( 'GFEntryDetail' ) ) {
			@require_once( GFCommon::get_base_path() . "/entry_detail.php" );
		}
		if ( ! class_exists( 'GFCommon' ) ) {
			@require_once( GFCommon::get_base_path() . "/common.php" );
		}
		if ( ! class_exists( 'RGFormsModel' ) ) {
			@require_once( GFCommon::get_base_path() . "/forms_model.php" );
		}
		if ( ! class_exists( 'GFEntryList' ) ) {
			require_once( GFCommon::get_base_path() . "/entry_list.php" );
		}
	}

	public static function make_directory( $atts ) {
		global $wpdb, $wp_rewrite, $post, $wpdb, $directory_shown, $kws_gf_scripts, $kws_gf_styles;

		self::include_gf_files();

		//quit if version of wp is not supported
		if ( ! class_exists( 'GFCommon' ) || ! GFCommon::ensure_wp_version() ) {
			return;
		}

		// Already showed edit directory form and there are more than one forms on the page.
		if ( did_action( 'kws_gf_directory_post_after_edit_lead_form' ) ) {
			return;
		}

		ob_start(); // Using ob_start() allows us to use echo instead of $output .=

		foreach ( $atts as $key => $att ) {
			if ( strtolower( $att ) == 'false' ) {
				$atts[ $key ] = false;
			}
			if ( strtolower( $att ) == 'true' ) {
				$atts[ $key ] = true;
			}
		}

		$atts['approved'] = isset( $atts['approved'] ) ? $atts['approved'] : - 1;

		if ( ! empty( $atts['lightboxsettings'] ) && is_string( $atts['lightboxsettings'] ) ) {
			$atts['lightboxsettings'] = explode( ',', $atts['lightboxsettings'] );
		}

		$options = GFDirectory::directory_defaults( $atts );

		// Make sure everything is on the same page.
		if ( is_array( $options['lightboxsettings'] ) ) {
			foreach ( $options['lightboxsettings'] as $key => $value ) {
				if ( is_numeric( $key ) ) {
					$options['lightboxsettings']["{$value}"] = $value;
					unset( $options['lightboxsettings']["{$key}"] );
				}
			}
		}


		extract( $options );

		$form_id = $form;

		$form = GFAPI::get_form( $form_id );

		if ( ! $form ) {
			return;
		}

		$sort_field     = empty( $_GET["sort"] ) ? $sort : $_GET["sort"];
		$sort_direction = empty( $_GET["dir"] ) ? $dir : $_GET["dir"];
		$search_query   = isset( $_GET["gf_search"] ) ? $_GET["gf_search"] : NULL;


		$start_date = ! empty( $_GET["start_date"] ) ? $_GET["start_date"] : $start_date;
		$end_date   = ! empty( $_GET["end_date"] ) ? $_GET["end_date"] : $end_date;

		$page_index       = empty( $_GET["pagenum"] ) ? $startpage - 1 : intval( $_GET["pagenum"] ) - 1;
		$star             = ( isset( $_GET["star"] ) && is_numeric( $_GET["star"] ) ) ? intval( $_GET["star"] ) : NULL;
		$read             = ( isset( $_GET["read"] ) && is_numeric( $_GET["read"] ) ) ? intval( $_GET["read"] ) : NULL;
		$first_item_index = $page_index * $page_size;
		$link_params      = array();
		if ( ! empty( $page_index ) ) {
			$link_params['pagenum'] = $page_index;
		}
		$formaction = esc_url_raw( remove_query_arg( array(
			'gf_search',
			'sort',
			'dir',
			'pagenum',
			'edit',
		), add_query_arg( $link_params ) ) );
		$tableclass .= ! empty( $jstable ) ? sprintf( ' tablesorter tablesorter-%s', apply_filters( 'kws_gf_tablesorter_theme', 'blue', $form ) ) : '';
		$title           = $form["title"];
		$sort_field_meta = RGFormsModel::get_field( $form, $sort_field );
		$is_numeric      = ( $sort_field_meta && $sort_field_meta->type === "number" );

		$columns = self::get_grid_columns( $form_id, true );

		$approvedcolumn = NULL;

		$smartapproval = ! empty( $smartapproval );
		$enable_smart_approval = false;

		// Approved is not enabled, and smart approval is enabled
		if ( - 1 === $approved && $smartapproval ) {
		    $enable_smart_approval = true;
            $approved = true;
		}

		if ( true === $approved ) {
			$approvedcolumn = self::get_approved_column( $form );
		}


		if ( $approved || ( ! empty( $smartapproval ) && $approved === - 1 ) && ! empty( $approvedcolumn ) ) {
			$approved = true; // If there is an approved column, turn on approval
		} else {
			$approved = false; // Otherwise, show entries as normal.
		}

		$entrylinkcolumns = self::get_entrylink_column( $form, $entry );
		$adminonlycolumns = self::get_admin_only( $form );

		//
		// Show only a single entry
		//
		$detail = self::process_lead_detail( true, $entryback, $showadminonly, $adminonlycolumns, $approvedcolumn, $options, $entryonly );

		if ( ! empty( $entry ) && ! empty( $detail ) ) {

			// Once again, checking to make sure this hasn't been shown already with multiple shortcodes on one page.
			if ( ! did_action( 'kws_gf_after_directory' ) ) {
				echo $detail;
			}

			if ( ! empty( $entryonly ) ) {
				do_action( 'kws_gf_after_directory', do_action( 'kws_gf_after_directory_form_' . $form_id, $form, compact( "approved", "sort_field", "sort_direction", "search_query", "first_item_index", "page_size", "star", "read", "is_numeric", "start_date", "end_date" ) ) );

				$content = ob_get_clean(); // Get the output and clear the buffer

				// If the form is form #2, two filters are applied: `kws_gf_directory_output_2` and `kws_gf_directory_output`
				$content = apply_filters( 'kws_gf_directory_output', apply_filters( 'kws_gf_directory_output_' . $form_id, self::html_display_type_filter( $content, $directoryview ) ) );

				return $content;
			}
		}


		// since 3.5 - remove columns of the fields not allowed to be shown
		$columns = self::remove_hidden_fields( $columns, $adminonlycolumns, $approvedcolumn, false, false, $showadminonly, $form );

		// hook for external selection of columns
		$columns = apply_filters( 'kws_gf_directory_filter_columns', $columns );


		//since 3.5 search criteria
		$show_search_filters = self::get_search_filters( $form );
		$show_search_filters = apply_filters( 'kws_gf_directory_search_filters', $show_search_filters, $form );
		$search_criteria     = array();
		foreach ( $show_search_filters as $key ) {
			if ( '' !== rgget('filter_' . $key ) ) {
				$search_criteria['field_filters'][] = array(
                  'key' => $key,
                  'value' => rgget('filter_' . $key ),
                );
			}
		}

        // 2.3 supports $smartapproval out of the box
		if( $smartapproval && $enable_smart_approval && self::use_gf_23_db() ) {

			$search_criteria['field_filters'][] = array(
				'key' => 'is_approved',
				'operator' => 'isnot',
				'value' => ''
			);

			$search_criteria['field_filters'][] = array(
				'key' => 'is_approved',
				'operator' => 'isnot',
				'value' => '0'
			);

			$search_criteria['field_filters']['mode'] = 'all';

        }

        $total_count = 0;

		//
		// Or start to generate the directory
		//
		$leads = GFDirectory::get_leads( $form_id, $sort_field, $sort_direction, $search_query, $first_item_index, $page_size, $star, $read, $is_numeric, $start_date, $end_date, 'active', $approvedcolumn, $limituser, $search_criteria, $total_count );

		// Allow lightbox to determine whether showadminonly is valid without passing a query string in URL
		if ( $entry === true && ! empty( $lightboxsettings['entry'] ) ) {
			if ( get_site_transient( 'gf_form_' . $form_id . '_post_' . $post->ID . '_showadminonly' ) != $showadminonly ) {
				set_site_transient( 'gf_form_' . $form_id . '_post_' . $post->ID . '_showadminonly', $showadminonly, HOUR_IN_SECONDS );
			}
		} else {
			delete_site_transient( 'gf_form_' . $form_id . '_post_' . $post->ID . '_showadminonly' );
		}


		// Get a list of query args for the pagination links
		if ( ! empty( $search_query ) ) {
			$args["gf_search"] = urlencode( $search_query );
		}
		if ( ! empty( $sort_field ) ) {
			$args["sort"] = $sort_field;
		}
		if ( ! empty( $sort_direction ) ) {
			$args["dir"] = $sort_direction;
		}
		if ( ! empty( $star ) ) {
			$args["star"] = $star;
		}

		if ( $page_size > 0 ) {

			// $leads contains all the entries according to request, since 3.5, to allow multisort.
			if ( apply_filters( 'kws_gf_directory_want_multisort', false ) ) {
				$lead_count = count( $leads );
				$leads      = array_slice( $leads, $first_item_index, $page_size );
			} else {
				$lead_count = $total_count;
			}


			$page_links = array(
				'base'      => esc_url_raw( @add_query_arg( 'pagenum', '%#%' ) ),// get_permalink().'%_%',
				'format'    => '&pagenum=%#%',
				'add_args'  => $args,
				'prev_text' => $prev_text,
				'next_text' => $next_text,
				'total'     => ceil( $lead_count / $page_size ),
				'current'   => $page_index + 1,
				'show_all'  => $pagelinksshowall,
			);

			$page_links = apply_filters( 'kws_gf_results_pagination', $page_links );

			$page_links = paginate_links( $page_links );
		} else {
			// Showing all results
			$page_links = false;
			$lead_count = sizeof( $leads );
		}


		if ( ! isset( $directory_shown ) ) {
			$directory_shown = true;


			?>

			<script>
				<?php if(! empty( $lightboxsettings['images'] ) || ! empty( $lightboxsettings['entry'] )) { ?>

				var tb_pathToImage = "<?php echo esc_js( site_url( '/wp-includes/js/thickbox/loadingAnimation.gif' ) ); ?>";
				var tb_closeImage = "<?php echo esc_js( site_url( '/wp-includes/js/thickbox/tb-close.png' ) ); ?>";
				var tb_height = 600;
				<?php } ?>
				function not_empty( variable ) {
					if ( variable == '' || variable == null || variable == 'undefined' || typeof(variable) == 'undefined' ) {
						return false;
					} else {
						return true;
					}
				}

				<?php if(! empty( $jstable )) { ?>
				jQuery( document ).ready( function ( $ ) {
					$( '.tablesorter' ).each( function () {
						$( this ).tablesorter(<?php echo apply_filters( 'kws_gf_directory_tablesorter_options', '' ) ?>);
					} );
				} );
				<?php } else if(isset( $jssearch ) && $jssearch) { ?>
				function Search( search, sort_field_id, sort_direction, search_criteria ) {
					if ( not_empty( search ) ) {
						var search = "&gf_search=" + encodeURIComponent( search );
					} else {
						var search = '';
					}

					var search_filters = '';
					if ( not_empty( search_criteria ) ) {
						$.each( search_criteria, function ( index, value ) {
							search_filters += "&filter_" + index + "=" + encodeURIComponent( value );
						} );
					}

					if ( not_empty( sort_field_id ) ) {
						var sort = "&sort=" + sort_field_id;
					} else {
						var sort = '';
					}
					if ( not_empty( sort_direction ) ) {
						var dir = "&dir=" + sort_direction;
					} else {
						var dir = '';
					}
					var page = '<?php if ( $wp_rewrite->using_permalinks() ) {
							echo '?';
						} else {
							echo '&';
						} ?>page=' +<?php echo isset( $_GET['pagenum'] ) ? intval( $_GET['pagenum'] ) : '"1"'; ?>;
					var location = "<?php echo esc_js( get_permalink( $post->ID ) ); ?>" + page + search + sort + dir + search_filters;
					document.location = location;
				}
				<?php } ?>
			</script>
		<?php } ?>

		<div class="wrap">
			<?php if ( $titleshow ) : ?>
				<h2><?php echo $titleprefix . $title; ?></h2>
			<?php endif; ?>

			<?php // --- Render Search Box ---

			if ( $search || ! empty( $show_search_filters ) ) : ?>

				<form id="lead_form" method="get" action="<?php echo $formaction; ?>">
					<?php
					//New logic for search criterias (since 3.5)

					if ( ! empty( $show_search_filters ) ) {

						foreach ( $show_search_filters as $key ) {
							$properties = self::get_field_properties( $form, $key );
							if ( in_array( $properties['type'], array(
								'select',
								'checkbox',
								'radio',
								'post_category',
							) ) ) {
								echo self::render_search_dropdown( $properties['label'], 'filter_' . $properties['id'], $properties['choices'] ); //Label, name attr, choices
							} else {
								echo self::render_search_input( $properties['label'], 'filter_' . $properties['id'] ); //label, attr name
							}

						}

					}

					?>
					<p class="search-box">
						<?php if ( $search ) : ?>
							<label class="hidden"
							       for="lead_search"><?php esc_html_e( "Search Entries:", "gravity-forms-addons" ); ?></label>
							<input type="text" name="gf_search" id="lead_search"
							       value="<?php echo $search_query ?>"<?php if ( $searchtabindex ) {
								echo ' tabindex="' . intval( $searchtabindex ) . '"';
							} ?> />
						<?php endif; ?>
						<?php
						// If not using permalinks, let's make the form work!
						echo ! empty( $_GET['p'] ) ? '<input name="p" type="hidden" value="' . esc_html( $_GET['p'] ) . '" />' : '';
						echo ! empty( $_GET['page_id'] ) ? '<input name="page_id" type="hidden" value="' . esc_html( $_GET['page_id'] ) . '" />' : '';
						?>
						<input type="submit" class="button" id="lead_search_button"
						       value="<?php esc_attr_e( "Search", "gravity-forms-addons" ) ?>"<?php if ( $searchtabindex ) {
							echo ' tabindex="' . intval( $searchtabindex ++ ) . '"';
						} ?> />
					</p>
				</form>

			<?php endif;


			//Displaying paging links if appropriate

			if ( $lead_count > 0 && $showcount || $page_links ) {
				if ( $lead_count == 0 ) {
					$first_item_index --;
				}
				?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php if ( $showcount ) {
							if ( ( $first_item_index + $page_size ) > $lead_count || $page_size <= 0 ) {
								$second_part = $lead_count;
							} else {
								$second_part = $first_item_index + $page_size;
							}
							?>
							<span
								class="displaying-num"><?php printf( __( "Displaying %d - %d of %d", "gravity-forms-addons" ), $first_item_index + 1, $second_part, $lead_count ) ?></span>
						<?php }
						if ( $page_links ) {
							echo $page_links;
						} ?>
					</div>
					<div class="clear"></div>
				</div>
				<?php
			}

			do_action( 'kws_gf_before_directory_after_nav', do_action( 'kws_gf_before_directory_after_nav_form_' . $form_id, $form, $leads, compact( "approved", "sort_field", "sort_direction", "search_query", "first_item_index", "page_size", "star", "read", "is_numeric", "start_date", "end_date" ) ) );
			?>

			<table class="<?php echo $tableclass; ?>" cellspacing="0"<?php if ( ! empty( $tablewidth ) ) {
				echo ' width="' . $tablewidth . '"';
			}
			echo $tablestyle ? ' style="' . $tablestyle . '"' : ''; ?>>
				<?php if ( $thead ) { ?>
					<thead>
					<tr>
						<?php

						$addressesExist = false;
						foreach ( $columns as $field_id => $field_info ) {
							$dir = $field_id == 0 ? "DESC" : "ASC"; //default every field so ascending sorting except date_created (id=0)
							if ( $field_id == $sort_field ) { //reverting direction if clicking on the currently sorted field
								$dir = $sort_direction == "ASC" ? "DESC" : "ASC";
							}
							if ( is_array( $adminonlycolumns ) && ! in_array( $field_id, $adminonlycolumns ) || ( is_array( $adminonlycolumns ) && in_array( $field_id, $adminonlycolumns ) && $showadminonly ) || ! $showadminonly ) {
								if ( $field_info['type'] == 'address' && $appendaddress && $hideaddresspieces ) {
									$addressesExist = true;
									continue;
								}
								?>
								<?php
								$_showlink = false;
								if ( isset( $jssearch ) && $jssearch && ! isset( $jstable ) ) { ?>
									<th scope="col" id="gf-col-<?php echo $form_id . '-' . $field_id ?>" class="manage-column" onclick="Search('<?php echo $search_query ?>', '<?php echo $field_id ?>', '<?php echo $dir ?>', '' );" style="cursor:pointer;"><?php
								} elseif ( isset( $jstable ) && $jstable || $field_info['type'] === 'id' ) { ?>
									<th scope="col" id="gf-col-<?php echo $form_id . '-' . $field_id ?>" class="manage-column">
								<?php } else {
									$_showlink = true;
									?>
									<th scope="col" id="gf-col-<?php echo $form_id . '-' . $field_id ?>" class="manage-column">
									<a href="<?php
									$searchpage     = isset( $_GET['pagenum'] ) ? intval( $_GET['pagenum'] ) : 1;
									$new_query_args = array(
										'gf_search' => $search_query,
										'sort'      => $field_id,
										'dir'       => $dir,
										'pagenum'   => $searchpage,
									);
									foreach ( $search_criteria as $key => $value ) {
										$new_query_args[ 'filter_' . $key ] = $value;
									}
									echo esc_url_raw( add_query_arg( $new_query_args, get_permalink( $post->ID ) ) );
									?>"><?php
								}
								if ( $field_info['type'] == 'id' && $entry ) {
									$label = $entryth;
								} else {
									$label = $field_info["label"];
								}

								$label = apply_filters( 'kws_gf_directory_th', apply_filters( 'kws_gf_directory_th_' . $field_id, apply_filters( 'kws_gf_directory_th_' . sanitize_title( $label ), $label ) ) );
								echo esc_html( $label );

							if ( $_showlink ) { ?></a><?php } ?>
								</th>
								<?php
							}
						}

						if ( $appendaddress && $addressesExist ) {
							?>
							<th scope="col" id="gf-col-<?php echo $form_id . '-' . $field_id ?>" class="manage-column"
							    onclick="Search('<?php echo $search_query ?>', '<?php echo $field_id ?>', '<?php echo $dir ?>');"
							    style="cursor:pointer;"><?php
								$label = apply_filters( 'kws_gf_directory_th', apply_filters( 'kws_gf_directory_th_address', 'Address' ) );
								echo esc_html( $label )

								?></th>
							<?php
						}
						?>
					</tr>
					</thead>
				<?php } ?>
				<tbody class="list:user user-list">
				<?php
				include_once( GF_DIRECTORY_PATH. "includes/template-row.php" );
				?>
				</tbody>
				<?php if ( $tfoot ) {
					if ( isset( $jssearch ) && $jssearch && ! isset( $jstable ) ) {
						$th = '<th scope="col" id="gf-col-' . $form_id . '-' . $field_id . '" class="manage-column" onclick="Search(\'' . $search_query . '\', \'' . $field_id . '\', \'' . $dir . '\');" style="cursor:pointer;">';
					} else {
						$th = '<th scope="col" id="gf-col-' . $form_id . '-' . $field_id . '" class="manage-column">';
					}
					?>
					<tfoot>
					<tr>
						<?php
						$addressesExist = false;
						foreach ( $columns as $field_id => $field_info ){
						$dir = $field_id == 0 ? "DESC" : "ASC"; //default every field so ascending sorting except date_created (id=0)
						if ( $field_id == $sort_field ) { //reverting direction if clicking on the currently sorted field
							$dir = $sort_direction == "ASC" ? "DESC" : "ASC";
						}
						if ( is_array( $adminonlycolumns ) && ! in_array( $field_id, $adminonlycolumns ) || ( is_array( $adminonlycolumns ) && in_array( $field_id, $adminonlycolumns ) && $showadminonly ) || ! $showadminonly ) {
						if ( $field_info['type'] == 'address' && $appendaddress && $hideaddresspieces ) {
							$addressesExist = true;
							continue;
						}

						echo $th;

						if ( $field_info['type'] == 'id' && $entry ) {
							$label = $entryth;
						} else {
							$label = $field_info["label"];
						}

						$label = apply_filters( 'kws_gf_directory_th', apply_filters( 'kws_gf_directory_th_' . $field_id, apply_filters( 'kws_gf_directory_th_' . sanitize_title( $label ), $label ) ) );
						echo esc_html( $label )

						?></th>
						<?php
						}
						}
						if ( $appendaddress && $addressesExist ) {
							?>
							<th scope="col" id="gf-col-<?php echo $form_id . '-' . $field_id ?>" class="manage-column"
							    onclick="Search('<?php echo $search_query ?>', '<?php echo $field_id ?>', '<?php echo $dir ?>');"
							    style="cursor:pointer;"><?php
								$label = apply_filters( 'kws_gf_directory_th', apply_filters( 'kws_gf_directory_th_address', 'Address' ) );
								echo esc_html( $label )

								?></th>
							<?php
						}
						?>
					</tr>
					<?php if ( ! empty( $credit ) ) {
						self::get_credit_link( sizeof( $columns ), $options );
					} ?>
					</tfoot>
				<?php } ?>
			</table>
			<?php

			do_action( 'kws_gf_after_directory_before_nav', do_action( 'kws_gf_after_directory_before_nav_form_' . $form_id, $form, $leads, compact( "approved", "sort_field", "sort_direction", "search_query", "first_item_index", "page_size", "star", "read", "is_numeric", "start_date", "end_date" ) ) );


			//Displaying paging links if appropriate

			if ( $lead_count > 0 && $showcount || $page_links ) {
				if ( $lead_count == 0 ) {
					$first_item_index --;
				}
				?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php if ( $showcount ) {
							if ( ( $first_item_index + $page_size ) > $lead_count || $page_size <= 0 ) {
								$second_part = $lead_count;
							} else {
								$second_part = $first_item_index + $page_size;
							}
							?>
							<span
								class="displaying-num"><?php printf( __( "Displaying %d - %d of %d", "gravity-forms-addons" ), $first_item_index + 1, $second_part, $lead_count ) ?></span>
						<?php }
						if ( $page_links ) {
							echo $page_links;
						} ?>
					</div>
					<div class="clear"></div>
				</div>
				<?php
			}

			?>
		</div>
		<?php
		if ( empty( $credit ) ) {
			echo "\n" . '<!-- Directory generated by Gravity Forms Directory & Addons : http://wordpress.org/extend/plugins/gravity-forms-addons/ -->' . "\n";
		}

		do_action( 'kws_gf_after_directory', do_action( 'kws_gf_after_directory_form_' . $form_id, $form, $leads, compact( "approved", "sort_field", "sort_direction", "search_query", "first_item_index", "page_size", "star", "read", "is_numeric", "start_date", "end_date" ) ) );

		$content = ob_get_contents(); // Get the output
		ob_end_clean(); // Clear the cache

		// If the form is form #2, two filters are applied: `kws_gf_directory_output_2` and `kws_gf_directory_output`
		$content = apply_filters( 'kws_gf_directory_output', apply_filters( 'kws_gf_directory_output_' . $form_id, self::html_display_type_filter( $content, $directoryview ) ) );

		return $content; // Return it!
	}

	public static function get_grid_columns( $form_id, $input_label_only = false ) {
		$form      = GFFormsModel::get_form_meta( $form_id );
		$field_ids = self::get_grid_column_meta( $form_id );

		if ( ! is_array( $field_ids ) ) {
			$field_ids = array();
			for ( $i = 0, $count = sizeof( $form["fields"] ); $i < $count && $i < 5; $i ++ ) {
				$field = $form["fields"][ $i ];

				if ( $field->displayOnly ) {
					continue;
				}


				if ( isset( $field->inputs ) && is_array( $field->inputs ) ) {
					$field_ids[] = $field->id;
					if ( 'name' === $field->type ) {
						$field_ids[] = $field->id . '.3'; //adding first name
						$field_ids[] = $field->id . '.6'; //adding last name
					} else if ( isset( $field->inputs[0] ) ) {
						$field_ids[] = $field->inputs[0]["id"]; //getting first input
					}
				} else {
					$field_ids[] = $field->id;
				}
			}
			//adding default entry meta columns
			$entry_metas = GFFormsModel::get_entry_meta( $form_id );
			foreach ( $entry_metas as $key => $entry_meta ) {
				if ( rgar( $entry_meta, "is_default_column" ) ) {
					$field_ids[] = $key;
				}
			}
		}

		$columns    = array();
		$entry_meta = GFFormsModel::get_entry_meta( $form_id );
		foreach ( $field_ids as $field_id ) {

			switch ( $field_id ) {
				case "id" :
					$columns[ $field_id ] = array( "label" => "Entry Id", "type" => "id" );
					break;
				case "ip" :
					$columns[ $field_id ] = array( "label" => "User IP", "type" => "ip" );
					break;
				case "date_created" :
					$columns[ $field_id ] = array( "label" => "Entry Date", "type" => "date_created" );
					break;
				case "source_url" :
					$columns[ $field_id ] = array( "label" => "Source Url", "type" => "source_url" );
					break;
				case "payment_status" :
					$columns[ $field_id ] = array( "label" => "Payment Status", "type" => "payment_status" );
					break;
				case "transaction_id" :
					$columns[ $field_id ] = array( "label" => "Transaction Id", "type" => "transaction_id" );
					break;
				case "payment_date" :
					$columns[ $field_id ] = array( "label" => "Payment Date", "type" => "payment_date" );
					break;
				case "payment_amount" :
					$columns[ $field_id ] = array( "label" => "Payment Amount", "type" => "payment_amount" );
					break;
				case "created_by" :
					$columns[ $field_id ] = array( "label" => "User", "type" => "created_by" );
					break;
				case ( ( is_string( $field_id ) || is_int( $field_id ) ) && array_key_exists( $field_id, $entry_meta ) ) :
					$columns[ $field_id ] = array( "label" => $entry_meta[ $field_id ]["label"], "type" => $field_id );
					break;
				default :
					$field = GFFormsModel::get_field( $form, $field_id );
					if ( $field ) {
						$columns[ strval( $field_id ) ] = array(
							"label"     => self::get_label( $field, $field_id, $input_label_only ),
							"type"      => rgobj( $field, 'type' ),
							"inputType" => rgobj( $field, 'inputType' ),
						);
					}
			}
		}

		return $columns;
	}

	static private function get_entrylink_column( $form, $entry = false ) {
		if ( ! is_array( $form ) ) {
			return false;
		}

		$columns = empty( $entry ) ? array() : array( 'id' => 'id' );
		foreach ( @$form['fields'] as $key => $col ) {
			if ( ! empty( $col['useAsEntryLink'] ) ) {
				$columns[ $col['id'] ] = $col['useAsEntryLink'];
			}
		}

		return empty( $columns ) ? false : $columns;
	}

}
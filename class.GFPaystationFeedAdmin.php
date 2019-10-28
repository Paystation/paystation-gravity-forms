<?php

// ============================================================================================================================================================
/**
 * Feed admin class.
 * Allows fields on the forms to be mapped to the parameters included in the payment requests
 * and also allows setting of some other options specific to the forms.
 */
// ============================================================================================================================================================
class GFPaystationFeedAdmin {

	protected $plugin;

	// ====================================================================================================================================
	/**
	 * @param GFPaystationPlugin $plugin handle to the plugin object
	 */
	// ====================================================================================================================================
	public function __construct($plugin) {

		$this->plugin = $plugin;

		// Add filters and actions to hook in to admin things
		add_filter('wp_print_scripts', array($this, 'removeScripts'));
		add_filter('parent_file', array($this, 'filterParentFile'));
		add_action('add_meta_boxes_' . GFPAYSTATION_TYPE_FEED, array($this, 'actionAddMetaBoxes'));
		add_action('save_post', array($this, 'saveCustomFields'), 10, 2);
		add_filter('manage_' . GFPAYSTATION_TYPE_FEED . '_posts_columns', array($this, 'filterManageColumns'));
		add_filter('post_row_actions', array($this, 'filterPostRowActions'));
		add_filter('wp_insert_post_data', array($this, 'filterInsertPostData'), 10, 2);
		add_filter('post_updated_messages', array($this, 'filterPostUpdatedMessages'));

		// Add the feed admin javscript file to the list of files to be included in the page source.
		wp_enqueue_script('gfpaystation-feed-admin', $this->plugin->urlBase . 'js/feed-admin.js', array('jquery'), GFPAYSTATION_PLUGIN_VERSION, true);
	}

	// ====================================================================================================================================
	/**
	 * remove some scripts we don't want loaded
	 */
	// ====================================================================================================================================
	public function removeScripts() {
		// stop WordPress SEO breaking our tooltips!
		wp_dequeue_script('wp-seo-metabox');
		wp_dequeue_script('jquery-qtip');
	}

	// ====================================================================================================================================
	/**
	 * Tell WordPress admin that Gravity Forms menu is parent page.
	 * @param string $parent_file
	 * @return string
	 */
	// ====================================================================================================================================
	public function filterParentFile($parent_file) {
		global $submenu_file;

		// Set parent menu for filter return.
		$parent_file = 'gf_edit_forms';

		// Set submenu by side effect.
		$submenu_file = 'gfpaystation-options';

		return $parent_file;
	}

	// ====================================================================================================================================
	/**
	 * Add meta boxes (these are the boxes seen on the mapping screen) for custom fields.
	 * @param WP_Post $post
	 */
	// ====================================================================================================================================
	public function actionAddMetaBoxes($post) {

		try {
			$feed = new GFPaystationFeed();
			if ($post && $post->ID) {
				$feed->loadFromPost($post);
			}
		} catch (GFPaystationException $e) {
			// NOP -- we'll have an empty feed
		}

		// The 'metaboxForm', 'metaboxFields', etc are function names below which output the HTML for these boxes.
		add_meta_box('meta_' . GFPAYSTATION_TYPE_FEED . '_form', 'Gravity Form', array($this, 'metaboxForm'),
			GFPAYSTATION_TYPE_FEED, 'normal', 'high', array('feed' => $feed));

		add_meta_box('meta_' . GFPAYSTATION_TYPE_FEED . '_fields', 'Form to Transaction Parameter Mapping', array($this, 'metaboxFields'),
			GFPAYSTATION_TYPE_FEED, 'normal', 'high', array('feed' => $feed));

		add_meta_box('meta_' . GFPAYSTATION_TYPE_FEED . '_urls', 'Redirect URLs', array($this, 'metaboxURLs'),
			GFPAYSTATION_TYPE_FEED, 'normal', 'high', array('feed' => $feed));

		add_meta_box('meta_' . GFPAYSTATION_TYPE_FEED . '_opts', 'Notification Settings', array($this, 'metaboxOpts'),
			GFPAYSTATION_TYPE_FEED, 'normal', 'high', array('feed' => $feed));

		// Replace standard Publish box with a custom one
		remove_meta_box('submitdiv', GFPAYSTATION_TYPE_FEED, 'side');

		add_meta_box('meta_' . GFPAYSTATION_TYPE_FEED . '_submit', 'Options', array($this, 'metaboxSave'),
			GFPAYSTATION_TYPE_FEED, 'side', 'high', array('feed' => $feed));
	}

	// ====================================================================================================================================
	/**
	 * metabox for custom save/publish
	 * @param WP_Post $post
	 * @param array $metabox has metabox id, title, callback, and args elements.
	 */
	// ====================================================================================================================================
	public function metaboxSave($post, $metabox) {
		global $action;
		?>
		<div style="display:none;">
			<?php submit_button(__('Save'), 'button', 'save'); ?>
		</div>
		<div id="major-publishing-actions">
			<?php do_action('post_submitbox_start'); ?>
			<div id="delete-action">
				<?php
				if (current_user_can("delete_post", $post->ID)) {
					if (!EMPTY_TRASH_DAYS) {
						$delete_text = __('Delete Permanently');
					}
					else {
						$delete_text = __('Move to Trash');
					}
					?>
					<a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
				} ?>
			</div>
			<div id="publishing-action">
				<span class="spinner"></span>
				<input name="original_publish" type="hidden" id="original_publish" value="Save"/>
				<?php submit_button('Save', 'primary button-large', 'publish', false, array()); ?>
			</div>
			<div class="clear"></div>
			<?php
			$feedsURL = 'edit.php?post_type=' . GFPAYSTATION_TYPE_FEED;
			echo "<a href=\"$feedsURL\">Return to feed list</a>.\n";
			?>
		</div>
		<?php
	}

	// ====================================================================================================================================
	/**
	 * Metabox for Gravity Form field, only listing forms that don't have a feed or are current feed's form (i.e. the select form feld).
	 * @param WP_Post $post
	 * @param array $metabox has metabox id, title, callback, and args elements.
	 */
	// ====================================================================================================================================
	public function metaboxForm($post, $metabox) {

		$feed = $metabox['args']['feed'];
		$forms = GFFormsModel::get_forms();

		$feeds = GFPaystationFeed::getList();
		$feedMap = array();
		foreach ($feeds as $f) {
			$feedMap[$f->FormID] = 1;
		}

		?>
		<select size="1" name="_gfpaystation_form">
			<option value="">(Select form)</option>
			<?php
			foreach ($forms as $form) {
				// only if form for this feed, or without a feed
				if ($form->id == $feed->FormID || !isset($feedMap[$form->id])) {
					$selected = selected($feed->FormID, $form->id);
					echo "<option value='{$form->id}' $selected>", htmlspecialchars($form->title), "</option>\n";
				}
			}
			?>
		</select>
		<?php
	}

	// ====================================================================================================================================
	/**
	 * Metabox for Redirect URL on failure - only really should be set when the Gravity form is set up to redirect to another page on
	 * success rather than re-display and output the confirmation message (or error message if there was an error).
	 * @param WP_Post $post
	 * @param array $metabox has metabox id, title, callback, and args elements.
	 */
	// ====================================================================================================================================
	public function metaboxURLs($post, $metabox) {
		$feed = $metabox['args']['feed'];
		$UrlFail = htmlspecialchars($feed->UrlFail);

		?>
		<strong>Notes:</strong>
		<p>
			If you have set the Gravity Form up to display the confirmation message after submission (this is the default) then you probably
			don't need to specify a failure url here. This is because the error message from Paystation will be displayed instead of the confirmation
			message if the payment failed. This also means the form confirmation message you type should say what you want the end-user to see when the payment was successful.
		</p>
		<p>
			However, if you have specified on the Gravity Form that it is to redirect somewhere after submission (for example a "thank you" page) then it is
			a good idea that you specify a failure url here (for example a "sorry payment failed" page) because this plugin cannot change the text displayed
			on whatever page you redirect to in order to inform the user of success or failure.
		</p>
		<p><label>URL to redirect to on transaction failure (optional):</label><br/>
			<input type="url" class='large-text' name="_gfpaystation_url_fail" value="<?php echo $UrlFail; ?>"/>
		</p>
		<?php
	}

	// ====================================================================================================================================
	/**
	 * Metabox for options related to notification settings.
	 * @param WP_Post $post
	 * @param array $metabox has metabox id, title, callback, and args elements.
	 */
	// ====================================================================================================================================
	public function metaboxOpts($post, $metabox) {
		$feed = $metabox['args']['feed'];
		?>
		<strong>Notes:</strong>
		<p>
			Tick these to only get an email notification when the payment was successful. If you leave these un-ticked you will get a notification
			as soon as the end-user has submitted the form, which means before the payment step. You may want a notification before the payment has
			been made, but remember to check in the form Entries, or in your Paystation online admin, that the payment was successful before shipping any goods.
		</p>
		<p><label><input type="checkbox" name="_gfpaystation_delay_notify" value="1" <?php checked($feed->DelayNotify); ?> />
				Send admin notification only when payment is received.</label></p>
		<p><label><input type="checkbox" name="_gfpaystation_delay_autorespond" value="1" <?php checked($feed->DelayAutorespond); ?> />
				Send user notification only when payment is received.</label></p>
		<p><label><input type="checkbox" name="_gfpaystation_delay_post" value="1" <?php checked($feed->DelayPost); ?> />
				Create post only when payment is received.</label></p>
		<?php
	}

	// ====================================================================================================================================
	/**
	 * Metabox for Fields to Map to the parameters sent in the payment request.
	 * @param WP_Post $post
	 * @param array $metabox has metabox id, title, callback, and args elements.
	 */
	// ====================================================================================================================================
	public function metaboxFields($post, $metabox) {
		wp_nonce_field(GFPAYSTATION_TYPE_FEED . '_save', GFPAYSTATION_TYPE_FEED . '_wpnonce', false, true);

		$feed = $metabox['args']['feed'];
		$MerchantReference = htmlspecialchars($feed->MerchantReference);
		$CustomerDetails = htmlspecialchars($feed->CustomerDetails);
		$OrderDetails = htmlspecialchars($feed->OrderDetails);
		$PaystationOverrideId = htmlspecialchars($feed->PaystationOverrideId);
		$fields = $feed->FormID ? self::getFormFields($feed->FormID) : false;

		?>
		<strong>Notes:</strong>
		<p>
			All these fields are optional, but we highly recommend that you at least set the Merchant Reference to something help you identify
			the customer, such as their email address. Customer details could be their name and Order details could be the name of the form.
		</p>
		<p>
			The first three fields you map here are displayed in your Paystation Online Admin when looking at transactions. You can also search on the Merchant Reference.
		</p>
		<table class='gfpaystation-feed-fields gfpaystation-details'>
			<tr>
				<th>Merchant Reference:</th>
				<td>
					<select size="1" name="_gfpaystation_merchant_ref">
						<?php if ($fields) {
							echo self::selectFields($MerchantReference, $fields);
						} ?>
					</select>
				</td>
			</tr>
			<tr>
				<th>Customer Details:</th>
				<td>
					<select size="1" name="_gfpaystation_customer_details">
						<?php if ($fields) {
							echo self::selectFields($CustomerDetails, $fields);
						} ?>
					</select>
				</td>
			</tr>
			<tr>
				<th>Order Details:</th>
				<td>
					<select size="1" name="_gfpaystation_order_details">
						<?php if ($fields) {
							echo self::selectFields($OrderDetails, $fields);
						} ?>
					</select>
				</td>
			</tr>
		</table>
		<br/>
		<p>
			<strong>Leave this field empty</strong> unless instructed otherwise. It is only used for a very specific feature to do with making the payment go in different
			Paystation accounts based on user selection on the form such as region, branch, country, etc. Please contact us for more details if you are interested in being able to do this.
		</p>
		<table class='gfpaystation-feed-fields gfpaystation-details'>
			<tr>
				<th>Paystation Id Override:</th>
				<td>
					<select size="1" name="_gfpaystation_override_id">
						<?php if ($fields) {
							echo self::selectFields($PaystationOverrideId, $fields);
						} ?>
					</select>
				</td>
			</tr>

		</table>
		<?php
	}

	// ====================================================================================================================================
	/**
	 * Filter insert fields, to set post title from form name.
	 * @param array $data the post insert data
	 * @param array $postarr data from the form post
	 * @return array
	 */
	// ====================================================================================================================================
	public function filterInsertPostData($data, $postarr) {

		$formID = isset($postarr['_gfpaystation_form']) ? intval($postarr['_gfpaystation_form']) : 0;

		if ($formID) {
			$form = GFFormsModel::get_form($formID);
			$data['post_title'] = $form->title;
			$data['post_name'] = sanitize_title($form->title);
		}

		return $data;
	}

	// ====================================================================================================================================
	/**
	 * Save custom fields about the form.
	 */
	// ====================================================================================================================================
	public function saveCustomFields($postID) {

		// Check whether this is an auto save routine. If it is, our form has not been submitted, so we don't want to do anything
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return $postID;
		}

		global $typenow;

		// Handle post type
		if ($typenow == GFPAYSTATION_TYPE_FEED) {
			// verify permission to edit post / page
			if (!current_user_can('edit_post', $postID)) {
				return $postID;
			}

			$fields = array(
				'_gfpaystation_form',
				'_gfpaystation_url_fail',
				'_gfpaystation_url_success',
				'_gfpaystation_merchant_ref',
				'_gfpaystation_customer_details',
				'_gfpaystation_order_details',
				'_gfpaystation_override_id',
				'_gfpaystation_opt',
				'_gfpaystation_delay_post',
				'_gfpaystation_delay_notify',
				'_gfpaystation_delay_autorespond',
			);

			if (isset($_POST['_gfpaystation_form'])) {
				if (!wp_verify_nonce($_POST[GFPAYSTATION_TYPE_FEED . '_wpnonce'], GFPAYSTATION_TYPE_FEED . '_save')) {
					die('Security exception');
				}
			}

			foreach ($fields as $fieldName) {
				if (isset($_POST[$fieldName])) {

					$value = $_POST[$fieldName];

					if (empty($value)) {
						delete_post_meta($postID, $fieldName);
					}
					else {
						update_post_meta($postID, $fieldName, $value);
					}
				}
				else {
					// checkboxes aren't set, so delete them
					delete_post_meta($postID, $fieldName);
				}
			}
		}

		return $postID;
	}

	// ====================================================================================================================================
	/**
	 * Remove unwanted actions from list of feeds
	 * @param array $actions
	 * @return array
	 */
	// ====================================================================================================================================
	public function filterPostRowActions($actions) {
		unset($actions['inline hide-if-no-js']);

		return $actions;
	}

	// ====================================================================================================================================
	/**
	 * Change the post updated messages
	 * @param array $messages
	 * @return array
	 */
	// ====================================================================================================================================
	public function filterPostUpdatedMessages($messages) {
		$messages[GFPAYSTATION_TYPE_FEED] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => 'Feed updated.',
			2 => 'Custom field updated.',
			3 => 'Custom field deleted.',
			4 => 'Feed updated.',
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf('Feed restored to revision from %s', wp_post_revision_title((int) $_GET['revision'], false)) : false,
			6 => 'Feed published.',
			7 => 'Feed saved.',
			8 => 'Feed submitted.',
			9 => 'Feed scheduled for: ',
			10 => 'Feed draft updated.',
		);

		return $messages;
	}

	// ====================================================================================================================================
	/**
	 * Filter to add columns to post list.
	 * @param array $posts_columns
	 * @return array
	 */
	// ====================================================================================================================================
	public function filterManageColumns($posts_columns) {

		// Date isn't useful for this post type
		unset($posts_columns['date']);

		// stop File Gallery adding No. of Attachments
		unset($posts_columns['attachment_count']);

		return $posts_columns;
	}

	// ====================================================================================================================================
	/**
	 * get a map of GF form field IDs to field names, for populating drop-down lists on the scren where the fields are
	 * mapped to the gateway parameters
	 * @param int $formID
	 * @return array
	 */
	// ====================================================================================================================================
	public static function getFormFields($formID) {

		$form = GFFormsModel::get_form_meta($formID);

		$fields = array(
			'form' => $formID . ' (form ID)',
			'title' => $form['title'] . ' (form title)',
		);

		if (is_array($form['fields'])) {
			foreach ($form['fields'] as $field) {
				if (!rgar($field, 'displayOnly')) {
					// pick up simple fields and selected compound fields
					if (empty($field['inputs']) || in_array(GFFormsModel::get_input_type($field), array('name', 'address'))) {
						$fields[(string) $field['id']] = GFCommon::get_label($field);
					}

					// pick up subfields
					if (isset($field['inputs']) && is_array($field['inputs'])) {
						foreach ($field['inputs'] as $input) {
							$fields[(string) $input['id']] = GFCommon::get_label($field, $input['id']);
						}
					}
				}
			}
		}

		return $fields;
	}

	// ====================================================================================================================================
	/**
	 * Return a list of drop-down list items for field mappings
	 * @param string $current the currently selected option
	 * @param array $fields
	 * @return string
	 */
	// ====================================================================================================================================
	public static function selectFields($current, $fields) {
		$opts = "<option value=''> (not selected) </option>\n";

		foreach ($fields as $name => $title) {
			$selected = selected($current, $name);
			$title = htmlspecialchars($title);
			$opts .= "<option value='$name' $selected>$title</option>\n";
		}

		return $opts;
	}
}

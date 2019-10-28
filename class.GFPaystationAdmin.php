<?php

// ============================================================================================================================================================
/**
 * Class for admin screens.
 */
// ============================================================================================================================================================
class GFPaystationAdmin {

	const MENU_PAGE = 'gfpaystation';   // This is the slug used for in the URL for the paystation module.

	protected $plugin;
	protected $adminPage = false;

	// ====================================================================================================================================
	/**
	 * Constructor.
	 * @param GFPaystationAdmin $plugin
	 */
	// ====================================================================================================================================
	public function __construct($plugin) {

		$this->plugin = $plugin;

		// handle admin init action
		add_action('admin_init', array($this, 'actionAdminInit'));

		// need this for custom handling of metaboxes when edit (for some reason, $typenow is unset at admin_init when edit!)
		if (!empty($_REQUEST['action'])) {
			add_action('admin_action_' . $_REQUEST['action'], array($this, 'actionAdminAction'));
		}

		// add GravityForms hooks
		add_filter("gform_addon_navigation", array($this, 'gformAddonNavigation'));

		// hook for showing admin messages (warning if gravity forms is not activated).
		add_action('admin_notices', array($this, 'actionAdminNotices'));

		// add action hook for adding plugin action links (settings link in plugins list).
		add_action('plugin_action_links_' . GFPAYSTATION_PLUGIN_NAME, array($this, 'addPluginActionLinks'));

		// Ensure the Payment details go in to the new entry box as the info box is no longer around.
		add_filter('gform_enable_entry_info_payment_details', '__return_false');

		// hook for enqueuing admin styles
		add_filter('admin_enqueue_scripts', array($this, 'enqueueScripts'));

		// AJAX actions
		add_action('wp_ajax_gfpaystation_form_fields', array($this, 'ajaxGfFormFields'));
		add_action('wp_ajax_gfpaystation_form_has_feed', array($this, 'ajaxGfFormHasFeed'));
	}

	// ====================================================================================================================================
	/**
	 * test whether GravityForms plugin is installed and active
	 * @return boolean
	 */
	// ====================================================================================================================================
	public static function isGfActive() {
		return class_exists('RGForms');
	}

	// ====================================================================================================================================
	/**
	 * handle admin init action
	 */
	// ====================================================================================================================================
	public function actionAdminInit() {
		global $typenow;

		if ($typenow) {
			$this->loadAdminPage($typenow);
		}
	}

	// ====================================================================================================================================
	/**
	 * for cases when typenow isn't set on admin init, catch it later
	 */
	// ====================================================================================================================================
	public function actionAdminAction() {
		global $typenow;

		if ($typenow) {
			$this->loadAdminPage($typenow);
		}
	}

	// ====================================================================================================================================
	/**
	 * load admin page for custom post type
	 * @param string $typenow post type
	 */
	// ====================================================================================================================================
	protected function loadAdminPage($typenow) {
		if (!$this->adminPage) {
			if ($typenow == GFPAYSTATION_TYPE_FEED) {
				$this->adminPage = new GFPaystationFeedAdmin($this->plugin);
			}
		}
	}

	// ====================================================================================================================================
	/**
	 * enqueue our admin stylesheet
	 */
	// ====================================================================================================================================
	public function enqueueScripts() {
		wp_enqueue_style('gfpaystation-admin', "{$this->plugin->urlBase}style-admin.css", false, GFPAYSTATION_PLUGIN_VERSION);
	}

	// ====================================================================================================================================
	/**
	 * show admin messages - this will show a message at the top of the screen in red if Gravity forms is not installed and activated.
	 * the message appears on any page of the admin screens.
	 */
	// ====================================================================================================================================
	public function actionAdminNotices() {
		if (!self::isGfActive()) {
			$this->plugin->showError('GravityForms Paystation (3 party hosted) plugin requires <a href="http://www.gravityforms.com/">GravityForms</a> plugin to be installed and activated.');
		}
	}

	// ====================================================================================================================================
	/**
	 * Action hook for adding plugin action links, adds the settings link in the plugins list.
	 */
	// ====================================================================================================================================
	public function addPluginActionLinks($links) {
		// Add settings link, but only if GravityForms plugin is active.
		if (self::isGfActive()) {
			$settings_link = '<a href="admin.php?page=' . self::MENU_PAGE . '-options">' . __('Settings') . '</a>';
			array_unshift($links, $settings_link);
		}

		return $links;
	}

	// ====================================================================================================================================
	/**
	 * filter hook for building GravityForms navigation
	 * @param array $menus
	 * @return array
	 */
	// ====================================================================================================================================
	public function gformAddonNavigation($menus) {
		// Add menu item for options - this is the Paystation (3 party) item in the forms menu.
		$menus[] = array('name' => self::MENU_PAGE . '-options', 'label' => 'Paystation (3 party)', 'callback' => array($this, 'optionsAdmin'), 'permission' => 'manage_options');
		return $menus;
	}

	// ====================================================================================================================================
	/**
	 * action hook for processing admin menu item
	 */
	// ====================================================================================================================================
	public function optionsAdmin() {
		$admin = new GFPaystationOptionsAdmin($this->plugin, self::MENU_PAGE . '-options');
		$admin->process();
	}

	// ====================================================================================================================================
	/**
	 * AJAX action to check for GF form already has feed, returning feed ID
	 * Becase these are called via ajax, they echo out the results.
	 */
	// ====================================================================================================================================
	public function ajaxGfFormHasFeed() {
		$formID = isset($_GET['id']) ? $_GET['id'] : 0;

		if (!$formID) {
			die("Bad form ID: $formID");
		}

		$feed = GFPaystationFeed::getFormFeed($formID);
		echo $feed ? $feed->ID : 0;
		exit;
	}

	// ====================================================================================================================================
	/**
	 * AJAX action for getting a list of form fields for a form
	 * Becase these are called via ajax, they echo out the results.
	 */
	// ====================================================================================================================================
	public function ajaxGfFormFields() {
		$formID = isset($_GET['id']) ? $_GET['id'] : 0;

		if (!$formID) {
			die("Bad form ID: $formID");
		}

		$fields = GFPaystationFeedAdmin::getFormFields($formID);
		$html = GFPaystationFeedAdmin::selectFields('', $fields);

		echo $html;
		exit;
	}
}

<?php
// ============================================================================================================================================================
/**
 * Feed model class
 */
// ============================================================================================================================================================
class GFPaystationFeed {

	public $ID;                                // unique ID for feed, same as post ID
	public $FeedName;                        // name of feed, same as post_title
	public $FormID;                            // ID of form in Gravity Forms
	public $DelayPost;                        // boolean: create post only when payment is received
	public $DelayNotify;                    // boolean: send admin notification only when payment is received
	public $DelayAutorespond;                // boolean: send user notification only when payment is received
	public $IsEnabled;                        // boolean: is this feed enabled?

	// fields set in admin
	public $UrlFail;                        // URL to redirect to on transaction failure
	public $Opt;                            // optional timeout data, TO=yymmddHHmm

	// field mappings to GF form
	public $MerchantReference;                // merchant reference
	public $CustomerDetails;                // optional string of up to 255 chars.
	public $OrderDetails;                    // optional string of up to 255 chars.
	public $PaystationOverrideId;

	protected static $fieldMap = array(
		'FormID' => '_gfpaystation_form',
		'UrlFail' => '_gfpaystation_url_fail',
		'MerchantReference' => '_gfpaystation_merchant_ref',
		'CustomerDetails' => '_gfpaystation_customer_details',
		'OrderDetails' => '_gfpaystation_order_details',
		'PaystationOverrideId' => '_gfpaystation_override_id',
		'Opt' => '_gfpaystation_opt',
		'DelayPost' => '_gfpaystation_delay_post',
		'DelayNotify' => '_gfpaystation_delay_notify',
		'DelayAutorespond' => '_gfpaystation_delay_autorespond',
	);

	// ====================================================================================================================================
	/**
	 * Constructor.
	 * @param integer $ID unique ID of feed, or NULL to create an empty object initialised to sensible defaults
	 */
	// ====================================================================================================================================
	public function __construct($ID = null) {
		if (is_null($ID)) {
			$this->ID = 0;
			$this->IsEnabled = true;
			return;
		}

		$post = get_post($ID);
		if ($post) {
			$this->loadFromPost($post);
		}
		else {
			throw new GFPaystationException(__CLASS__ . ": can't load feed: $ID");
		}
	}

	// ====================================================================================================================================
	/**
	 * load feed from WordPress post object
	 * @param WP_Post $post
	 */
	// ====================================================================================================================================
	public function loadFromPost($post) {
		if ($post->post_type != GFPAYSTATION_TYPE_FEED) {
			throw new GFPaystationException(__CLASS__ . ": post is not a Paystation feed: {$post->ID}");
		}

		$this->ID = $post->ID;
		$this->FeedName = $post->post_title;
		$this->IsEnabled = ($post->post_status == 'publish');

		$meta = get_post_meta($post->ID);

		foreach (self::$fieldMap as $name => $metaname) {
			$this->{$name} = self::metaValue($meta, $metaname);
		}
	}

	// ====================================================================================================================================
	/**
	 * get single value from meta array
	 * @param array $meta
	 * @param string $key
	 * @return mixed
	 */
	// ====================================================================================================================================
	protected static function metaValue($meta, $key) {
		return (isset($meta[$key][0])) ? $meta[$key][0] : false;
	}

	// ====================================================================================================================================
	/**
	 * get inverse map of GF fields to feed fields
	 * @return array
	 */
	// ====================================================================================================================================
	public function getGfFieldMap() {
		$map = array();

		foreach (['MerchantReference', 'CustomerDetails', 'OrderDetails', 'PaystationOverrideId'] as $feedName) {
			if (!empty($this->{$feedName})) {
				$map[$this->{$feedName}] = $feedName;
			}
		}

		return $map;
	}

	// ====================================================================================================================================
	/**
	 * list all feeds
	 * @return array(WpWinePagesProduct)
	 */
	// ====================================================================================================================================
	public static function getList() {
		$feeds = array();

		$args = array(
			'post_type' => GFPAYSTATION_TYPE_FEED,
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'posts_per_page' => -1,
		);

		$posts = get_posts($args);

		if ($posts) {
			try {
				foreach ($posts as $post) {
					$feed = new self();
					$feed->loadFromPost($post);
					$feeds[] = $feed;
				}
			} catch (GFPaystationException $e) {
				$feeds = false;
			}
		}

		return $feeds;
	}

	// ====================================================================================================================================
	/**
	 * get feed for GF form, by form ID
	 * @param int $formID
	 * @return self
	 */
	// ====================================================================================================================================
	public static function getFormFeed($formID) {
		if (!$formID) {
			throw new GFPaystationException(__METHOD__ . ": must give form ID");
		}

		$posts = get_posts(array(
			'post_type' => GFPAYSTATION_TYPE_FEED,
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'posts_per_page' => 1,
			'meta_key' => '_gfpaystation_form',
			'meta_value' => $formID,
		));

		if ($posts && count($posts) > 0) {
			try {
				$feed = new self();
				$feed->loadFromPost($posts[0]);
			} catch (GFPaystationException $e) {
				$feed = false;
			}
		}
		else {
			$feed = false;
		}

		return $feed;
	}
}

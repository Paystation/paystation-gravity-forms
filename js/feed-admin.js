/* script for supporting the Gravity Forms Paystation feed admin page */

jQuery(function ($) {

	var formID;			// selected form ID

	// watch for form selection changes
	$("select[name='_gfpaystation_form']").change(function() {
		formID = $(this).val();

		// remove any field mappings, will add new ones if new form successfully selected
		$(".gfpaystation-feed-fields select option").remove();

		if (formID) {
			// check that if form already has a feed, it's this one
			$.ajax({
				type: "GET",
				url: ajaxurl,
				cache: false,
				dataType: "text",
				data: { action: "gfpaystation_form_has_feed", id: formID },
				success: checkFeedMapsForm
			});
		}
	});

	/*
	* check for feed mapping to selected form, ensure it's this feed
	* @param {String} feedID the feed ID, as a string; "0" for no feed found
	* @param {String} status AJAX status
	* @param {Object} xhr the AJAX request object
	*/
	function checkFeedMapsForm(feedID, status, xhr) {
		var post_id = parseInt($("input[name='post_ID']").val(), 10);

		// make sure we just have the feed ID and no errant whitespace
		feedID = parseInt(feedID, 10);

		// check for feed mapping that isn't the one being edited
		if (feedID && feedID != post_id) {
			alert("That form already has a feed.");
		}

		else {
			// get fields for selected form, populate drop-downs
			$.ajax({
				type: "GET",
				url: ajaxurl,
				cache: false,
				dataType: "html",
				data: { action: "gfpaystation_form_fields", id: formID },
				success: loadFieldMappingOptions
			});
		}
	}

	/*
	* load field mapping options for selected form
	* @param {String} options the HTML for the drop-down options
	* @param {String} status AJAX status
	* @param {Object} xhr the AJAX request object
	*/
	function loadFieldMappingOptions(options, status, xhr) {
		$(".gfpaystation-feed-fields select").each(function() {
			$(this).html(options);
		});
	}

});

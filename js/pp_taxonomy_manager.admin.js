/**
 * @file
 * Provides some configurations for the tablesorter.
 */

(function ($) {
  Drupal.behaviors.pp_taxonomy_manager  = {
    attach: function () {
      // Make the project tables sortable if tablesorter is available.
      if ($.isFunction($.fn.tablesorter)) {
        $("table#pp-taxonomy-manager-configurations-table").tablesorter({
          widgets: ["zebra"],
          widgetOptions: {
            zebra: ["odd", "even"]
          },
          sortList: [[0, 0]],
          headers: {
            3: { sorter: false },
            4: { sorter: false }
          }
        });

        $("table#pp-taxonomy-manager-interconnection-table").tablesorter({
          widgets: ["zebra"],
          widgetOptions: {
            zebra: ["odd", "even"]
          },
          sortList: [[1, 1], [0, 0]]
        });

        $("table#pp-taxonomy-manager-powertagging-table").tablesorter({
          widgets: ["zebra"],
          widgetOptions: {
            zebra: ["odd", "even"]
          },
          sortList: [[0, 0]]
        });
      }

      if ($("form#pp-taxonomy-manager-add-form").length > 0) {
        $('#edit-load-connection').change(function() {
          var connection_value = (jQuery(this).val());
          if (connection_value.length > 0) {
            var connection_details = connection_value.split('|');
            jQuery('#edit-server-title').val(connection_details[0]);
            jQuery('#edit-url').val(connection_details[1]);
            jQuery('#edit-username').val(connection_details[2]);
            jQuery('#edit-password').val(connection_details[3]);
          }
          return false;
        });
      }
    }
  };

})(jQuery);

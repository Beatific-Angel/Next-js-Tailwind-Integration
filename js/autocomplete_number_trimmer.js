(function ($, Drupal) {

  Drupal.behaviors.autocomplete_number_trimmer = {
    attach: function (context, settings) {
      // Get the entity reference input
      const $eref = $('#edit-organisation-id', context);
      if($eref.val()){
        // If field has value on page load, change it.
        const val = $eref.val();
        const match = val.match(/\s\((.*?)\)$/);
        $eref.data('real-value', val);
        $eref.val(val.replace(match[0], ''));
      }
      // Listen for the autocompleteSelect event
      $eref.once().on('autocompleteSelect', function (e, node) {
        const val = $(node).data('autocompleteValue');
        const match = val.match(/\s\((.*?)\)$/);
        // Put the value with id into data storage
        $eref.data('real-value', val);
        // Set the value without the id
        $eref.val(val.replace(match[0], ''));
      }).closest('form').submit(function (e) {
        // On form submit, set the value back to the stored value with id
        $eref.val($eref.data('real-value'));
      });
    }
  };

})(jQuery, Drupal);

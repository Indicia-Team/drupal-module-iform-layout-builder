jQuery(document).ready(function($) {

  // Delete button confirmation.
  $('.block-data-entry-submit-buttons-block button[value="DELETE"]').click(function(e) {
    if (!confirm('Are you sure you want to delete all the data on this form?')) {
      e.preventDefault();
      return false;
    }
  });

});

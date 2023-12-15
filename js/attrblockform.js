
(function ($) {
  var lastLabelSet = '';

  function applyLabelIfNotChanged(newLabel) {
    if ($('[name="settings[option_label]"]').val() === '' || $('[name="settings[option_label]"]').val() === lastLabelSet) {
      $('[name="settings[option_label]"]').val(newLabel);
      lastLabelSet = newLabel;
    }
  }

  $(document).on('change', '[name="settings[option_admin_name]"]', function() {
    // If the label has not been changed by the user, default it to the same as the admin name.
    applyLabelIfNotChanged($('[name="settings[option_admin_name]"]').val());
  });

  $(document).on('change', '[name="settings[option_existing_attribute_id]"]', function() {
    // If the label has not been changed by the user, default it to the same as the chosen attr name.
    applyLabelIfNotChanged($('[name="settings[option_existing_attribute_id]"]  option:selected').text());
    // Admin name = attribute name (if control visible).
    $('[name="settings[option_admin_name]"]').val($('[name="settings[option_existing_attribute_id]"]  option:selected').text());
  });
})(jQuery);
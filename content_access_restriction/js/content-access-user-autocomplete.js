(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.contentAccessUserAutocomplete = {
    attach: function (context) {
      once('content-access-user-autocomplete', '#edit-field-restrict-usernames-custom', context).forEach(function (element) {
        var $element = jQuery(element);

        // Drupal's core autocomplete replaces the complete input value after
        // a suggestion is selected. Preserve the comma-separated values that
        // were entered before the current autocomplete token.
        $element.on('autocompletesearch', function () {
          var value = element.value || '';
          var commaIndex = value.lastIndexOf(',');
          element.dataset.autocompletePrefix = commaIndex === -1
            ? ''
            : value.substring(0, commaIndex + 1).replace(/\s*$/, ' ') ;
        });

        $element.on('autocompleteselect', function (event, ui) {
          var prefix = element.dataset.autocompletePrefix || '';
          var username = ui.item && ui.item.value ? ui.item.value : '';

          if (!username) {
            return;
          }

          // Run after Drupal's autocomplete select handler so our tokenized
          // value is the final value in the field.
          window.setTimeout(function () {
            element.value = prefix + username + ', ';
            element.dispatchEvent(new Event('input', { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
          }, 0);
        });
      });
    }
  };
})(Drupal, once);

/*
 * Initializes WordPress's standard color picker on all relevant inputs
 * within the Dual Chatbot settings page.
 */
(function($){
  function initColorPicker($input){
    var initial = $input.val();
    var dataDefault = $input.attr('data-default-color');
    $input.wpColorPicker({
      // If available, use data-default-color for the "Standard" button
      defaultColor: dataDefault || initial || false,
      change: function(event, ui){
        // Ensure the input value reflects the selected color
        if (ui && ui.color) {
          var color = ui.color.toString();
          $(event.target).val(color).trigger('change');
        }
      },
      clear: function(event){
        $(event.target).val('').trigger('change');
      }
    });
  }

  $(function(){
    // Target all color-like option fields
    var selector = 'input[type="text"][name*="color"], input[type="text"][name*="_color"]';
    $(selector).each(function(){
      var $el = $(this);
      // Add a live swatch next to the input
      if (!$el.next().hasClass('dual-chatbot-color-swatch')) {
        var swatch = $('<span class="dual-chatbot-color-swatch" />');
        var v = $el.val();
        if (v) swatch.css('background-color', v);
        $el.after(swatch);
      }
      initColorPicker($el);
    });

    // Safety: initialize on focus if not yet initialized
    $(document).on('focus', selector, function(){
      var $el = $(this);
      if (!$el.hasClass('wp-color-picker')) {
        initColorPicker($el);
      }
      // ensure swatch exists on focus
      if (!$el.next().hasClass('dual-chatbot-color-swatch')) {
        var sw = $('<span class="dual-chatbot-color-swatch" />');
        var vv = $el.val();
        if (vv) sw.css('background-color', vv);
        $el.after(sw);
      }
    });

    // If tabs toggle visibility, refresh pickers to ensure proper layout
    $('.nav-tab-wrapper a').on('click', function(){
      setTimeout(function(){
        $('.wp-color-picker').each(function(){
          var $input = $(this);
          try {
            // Force Iris to sync with current value
            if ($input.val()) {
              $input.wpColorPicker('color', $input.val());
            }
            // Update swatch
            var $s = $input.next('.dual-chatbot-color-swatch');
            if ($s.length) { $s.css('background-color', $input.val() || 'transparent'); }
          } catch(e) {}
        });
      }, 0);
    });
  });
})(jQuery);

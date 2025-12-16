/**
 * Ensures off-canvas dialog behavior is attached to help AI edit links.
 */
(function ($, Drupal, once) {
  Drupal.behaviors.bmHelpAiAdmin = {
    attach(context) {
      once('bm-help-ai-dialog', '.bm-help-ai-edit.use-ajax', context).forEach((link) => {
        // Nothing needed; presence of use-ajax + dialog attrs allows core to bind.
        // This behavior exists to ensure Drupal behaviors run on these links.
      });
    },
  };
})(jQuery, Drupal, once);

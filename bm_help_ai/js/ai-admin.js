/**
 * Help AI admin behaviors.
 *
 * - Ensures Drupal behaviors bind to dialog links.
 * - Persists off-canvas width for the browser session (sessionStorage).
 */
(function ($, Drupal, once) {
  const STORAGE_KEY = 'bm_help_ai.off_canvas_width_px';

  function getStoredWidth() {
    try {
      const value = sessionStorage.getItem(STORAGE_KEY);
      if (!value) return null;
      const parsed = parseInt(value, 10);
      return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function setStoredWidth(widthPx) {
    try {
      sessionStorage.setItem(STORAGE_KEY, String(widthPx));
    } catch (e) {
      // Ignore storage failures (private browsing, etc.).
    }
  }

  Drupal.behaviors.bmHelpAiAdmin = {
    attach(context) {
      once('bm-help-ai-dialog', '.bm-help-ai-edit.use-ajax, .bm-help-ai-display.use-ajax', context).forEach(() => {
        // No-op: core binds AJAX + dialog based on attributes.
      });

      // Persist off-canvas width for this browser session.
      once('bm-help-ai-offcanvas-width', 'html', context).forEach(() => {
        // Apply stored width before the off-canvas dialog is created.
        window.addEventListener('dialog:beforecreate', (event) => {
          const $element = $(event.target);
          if (!Drupal.offCanvas || !Drupal.offCanvas.isOffCanvas($element)) {
            return;
          }
          const storedWidth = getStoredWidth();
          if (storedWidth) {
            event.settings.width = storedWidth;
          }
        });

        // Capture user-driven resize and store new width.
        window.addEventListener('dialog:aftercreate', (event) => {
          const $element = $(event.target);
          if (!Drupal.offCanvas || !Drupal.offCanvas.isOffCanvas($element)) {
            return;
          }

          const saveWidthDebounced = Drupal.debounce(() => {
            const $widget = $element.dialog('widget');
            const width = $widget.outerWidth();
            if (width) {
              setStoredWidth(width);
            }
          }, 150);

          $element.on('dialogresizestop.bmHelpAi', saveWidthDebounced);
        });
      });
    },
  };
})(jQuery, Drupal, once);

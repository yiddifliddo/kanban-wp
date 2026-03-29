/* CWDS Kanban - Admin JS v1.0.0 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Auto-dismiss notices after 5 seconds
        setTimeout(function() {
            $('.cwds-kanban-admin .notice.is-dismissible').fadeOut();
        }, 5000);
    });
})(jQuery);

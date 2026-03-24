/**
 * RBAC Matrix management
 *
 * @author Antigravity AI
 */
$(function() {
    'use strict';

    /**
     * Real-time search for permissions
     */
    $('#rbac_search').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        
        // Filter permission rows
        $("#matrix_table tbody .perm-row").each(function() {
            var permName = $(this).attr('data-perm-name').toLowerCase();
            var isVisible = permName.indexOf(value) > -1;
            $(this).toggle(isVisible);
        });
        
        // Hide domain headers if no permissions are visible in that domain
        $('.domain-row').each(function() {
            var nextRows = $(this).nextUntil('.domain-row', '.perm-row:visible');
            $(this).toggle(nextRows.length > 0);
        });
    });

    /**
     * "Check All" behavior for role columns
     */
    $('.check-all').on('click', function() {
        var roleId = $(this).data('role-id');
        // Find visible checkboxes for this role
        var checkboxes = $('input[name^="perm[' + roleId + ']"]:visible');
        
        // If all are checked, uncheck all. Otherwise, check all.
        var allChecked = (checkboxes.length > 0 && checkboxes.length === checkboxes.filter(':checked').length);
        checkboxes.prop('checked', !allChecked);
        
        // Trigger change event for potential Semantic-UI checkbox integration
        checkboxes.trigger('change');
    });

    // Semantic-UI tooltips are usually automatic via data-tooltip,
    // but we can ensure they are initialized if needed.
    if ($.fn.popup) {
        $('[data-tooltip]').popup();
    }
});

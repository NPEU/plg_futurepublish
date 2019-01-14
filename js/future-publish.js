var FuturePublish = {
    'future_publish_group_id': false,

    'init': function () {
        FuturePublish.addCloneButton();
        FuturePublish.addFutureDateListener();

        // Added queued content warning: (timeout ensures everything has been set up)
        // Note magic number (1000), may fail if load is slow. Monitor/increase/improve.
        window.setTimeout(function(){
            if (FuturePublish.hasQueuedContent()) {
                FuturePublish.addQueueNotice();
            }
        }, 1000);

        return true;
    },

    'joomlaFieldCalendarUpdateAction': function () {
        // The joomla calendar doesn't fire the change event when the input is updated from the
        // date-picker by default, so I need to provide a separate handler to fudge it:
        // (Note the handler is actually added to the input server-side, this is just something for
        // it to call)
        FuturePublish.removeQueueNotice();
        if (FuturePublish.hasQueuedContent()) {
            FuturePublish.addQueueNotice();
        }
    },

    'addCloneButton': function () {
        $ = jQuery;

        // Insert the button markup:
        $('#jform_com_fields_future_content-lbl').after('<button id="clone_future_content" class="btn btn-primary">Clone current content</button>');

        // Record the custom field id:
        FuturePublish.future_publish_group_id = $('#clone_future_content').parents('.tab-pane').attr('id');

        // Add click handler
        $('#clone_future_content').click(function(e){
            e.preventDefault();

            // Get current content:
            $current_editor_body = FuturePublish.getCurrentEditorBody().clone();

            // Set Future content:
            $future_editor_body = FuturePublish.getFutureEditorBody();

            $future_editor_body.empty().append($current_editor_body.contents());

            return false;
        });
    },

    'addFutureDateListener': function () {
        $('#jform_com_fields_future_publish_date').change(function(e){
            FuturePublish.removeQueueNotice();
            if (FuturePublish.hasQueuedContent()) {
                FuturePublish.addQueueNotice();
            }
        });

    },

    'addQueueNotice': function () {
        $ = jQuery;

        $('#general').prepend([
            '<div class="row-fluid future-publish-queue-notice">',
            '    <div class="alert alert-danger">',
            '       <h4 class="alert-heading">Warning</h4>',
            '       <div class="alert-message">',
            '           <p>Future content is queued.<br/>',
            '           Any changes made to the content here will be replaced by the future content at the future publish date/time.</p>',
            '           <p>See the \'Future Publishing\' tab for details.</p>',
            '       </div>',
            '   </div>',
            '</div>'
        ].join("\n"));

        $('a[href="#' + FuturePublish.future_publish_group_id + '"]').append('<b class="future-publish-queue-notice"> (!)</b>');
    },

    'removeQueueNotice': function () {
        $ = jQuery;

        $('.future-publish-queue-notice').remove();
    },

    'hasQueuedContent': function () {
        $ = jQuery;

        // Check for future date: (e.g. 2019-01-30 11:54:48)
        future_date = $('#jform_com_fields_future_publish_date').val();
        if (future_date != '' && /\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}/.test(future_date)) {
            future_timestamp = Math.floor((new Date(future_date)).getTime() / 1000);
            now_timestamp = Math.floor((new Date()).getTime() / 1000);
            if (future_timestamp <= now_timestamp) {
                return false;
            }
        } else {
            return false;
        }

        return true;

        // Hmmm. Future date is all that's required. Empty future content just means that the
        // article will be updated with nothing (empty).
        // I think that's probably just the way it'll have to be, but keep this for reference:
        //future_editor_body = FuturePublish.getFutureEditorBody()[0];
        //return !(future_editor_body.textContent == '');
    },

    'getCurrentEditorBody': function () {
        $ = jQuery;

        $current_editor_frame = FuturePublish.getCurrentEditorFrame();
        return $($current_editor_frame.contents().find('body')[0]);
    },

    'getCurrentEditorFrame': function () {
        $ = jQuery;

        return $($('#general').find('iframe')[0]);
    },

    'getFutureEditorBody': function () {
        $ = jQuery;

        $future_editor_frame = FuturePublish.getFutureEditorFrame();
        return $($future_editor_frame.contents().find('body')[0]);
    },

    'getFutureEditorFrame': function (button) {
        $ = jQuery;

        $clone_button = $('#clone_future_content');

        return $($clone_button.parents('.control-label').next().find('iframe')[0]);
    }

};

jQuery(function(){
    FuturePublish.init();
});
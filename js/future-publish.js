var FuturePublish = {
    'future_publish_group_id': false,
    'future_date_in_future': null,

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

            console.log(window.Joomla.editors.instances);

            // This is CKEditor specific.
            // Ideally I'd fix CKEditor to use the proper Joomla.editors object, then change this
            // to make it universal.
            var current_editor = CKEDITOR.instances.jform_articletext;
            var future_editor  = CKEDITOR.instances.jform_com_fields_future_content;

            future_editor.setData(current_editor.getData());

            // I have no idea why the above code removes the ck_wym class, but it does, so adding
            // it back in.
            window.setTimeout(function(){
                $future_editor_body = FuturePublish.getFutureEditorBody();
                $future_editor_body.addClass('ck_wym');
            }, 1500);
            e.preventDefault();
            return false;




            // Get current content:
            //$current_editor_body = FuturePublish.getCurrentEditorBody().clone();

            // Set Future content:
            //$future_editor_body = FuturePublish.getFutureEditorBody();

            //$future_editor_body.empty().append($current_editor_body.contents());

            //return false;
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

        title = FuturePublish.future_date_in_future
              ? 'Future content is queued.'
              : 'Future content is queued with a date that is <strong>in the past</strong>.';

        message = FuturePublish.future_date_in_future
                ? 'Any changes made to the content here will be replaced by the future content at the future publish date/time.</p>'
                : 'Any changes made to the content here will be replaced by the future content <strong>the next time the page is loaded</strong>.</p>';

        notice = [
            '<div class="row-fluid future-publish-queue-notice">',
            '    <div class="alert alert-danger">',
            '       <h4 class="alert-heading">Warning</h4>',
            '       <div class="alert-message">',
            '           <p>' + title + '</br>',
            '           ' + message + '</p>',
            '           <p>See the \'Future Publishing\' tab for details.</p>',
            '       </div>',
            '   </div>',
            '</div>'
        ]

        $('#item-form > .form-inline-header').after(notice.join("\n"));

        $('a[href="#' + FuturePublish.future_publish_group_id + '"]').append('<b class="future-publish-queue-notice"> (!)</b>');
    },

    'removeQueueNotice': function () {
        $ = jQuery;

        $('.future-publish-queue-notice').remove();
    },

    'hasQueuedContent': function () {
        $ = jQuery;


        // (note I used to check if the date was a valid future date, but that's wrong,
        // a date in the past can still be saved and the main content will be overwritten on the
        // next page load without warning the user, so don't do that.)

        future_date = $('#jform_com_fields_future_publish_date').val();

        // Check for future date in correct format: (e.g. 2019-01-30 11:54:48)
        if (future_date != '' && /\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}/.test(future_date)) {

            // Valid format, now compare with 'now':
            future_timestamp = Math.floor((new Date(future_date)).getTime() / 1000);
            now_timestamp = Math.floor((new Date()).getTime() / 1000);
            if (future_timestamp >= now_timestamp) {
                FuturePublish.future_date_in_future = true;
                return true;
            } else {
                FuturePublish.future_date_in_future = false;
                return true;
            }
        }

        FuturePublish.future_date_in_future = null;
        return false;

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

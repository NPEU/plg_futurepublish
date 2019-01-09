var FuturePublish = {
    'init': function () {
        this.addCloneButton();
        
        return true;
    },
    'addCloneButton': function () {
        $ = jQuery;
        $('#jform_com_fields_future_content-lbl').after('<button id="future_content_clone" class="btn btn-primary">Clone current content</button>');
        $('#future_content_clone').click(function(e){
            e.preventDefault();
            
            // Get current content:
            $current_editor_frame = $($('#general')
                .find('iframe')[0]);
                
            $current_editor_body = $($current_editor_frame.contents().find('body')[0]).clone();
                
            // Set Future content:
            $future_editor_frame = $($(this)
                .parents('.control-label')
                .next()
                .find('iframe')[0]);
            $future_editor_body = $($future_editor_frame.contents().find('body')[0]);
            
            $future_editor_body.empty().append($current_editor_body.contents());
            
            return false;
        });
    }
    
};

jQuery(function(){
    FuturePublish.init();
});
jQuery(document).ready(function($){
    // Init the color picker
    $('.colpicker').wpColorPicker();

    // Init sortables element
    $('.sortable').sortable({
        update: function(event, ui) {
            var idArray = new Array();
            var input = $(this).parent().children("input");
            $(this).children("li").each( function() {
                idArray.push($(this).data("term_id"));
            });

            input.val(JSON.stringify(idArray));
        }
    });
});

/**
 * Image uploader
 *
 **/
jQuery(document).ready(function() {
    var $ = jQuery;
    String.prototype.replaceAll = function(target, replacement) {
      return this.split(target).join(replacement);
    };

    // Multiple set remove
    $(document).on('click', '.rdm-option-remove-button', function(e) {
        e.preventDefault();

        var $parent = $(this).closest('.multiple-set-parent');
        var $set = $(this).closest('.m-fieldset');
        var elements = $parent.find('.m-fieldset').length;
        if (elements === 1) {
            $set.find('input').attr('value', '');
            $set.hide();
        } else {
            $set.remove();
        }
        
    });
    // Multiple set add
    $(document).on('click', '.rdm-option-add-button', function(e) {
        e.preventDefault();

        var index = 0;

        var $parent = $(this).closest('.multiple-set-parent');
        var $subparent = $parent.find('.multiple-set');
        var elements = $parent.find('.m-fieldset').length;
        if(elements > 0){
            var $set = $parent.find('.m-fieldset').eq( elements-1 );
            //getting the index
            index = parseInt ( $set.data('index') );
        }
        else{
            //new element lets set the index to zero
            index = 0;
        }

        //copy the template.
        var $newSet = $parent.find('.m-fieldset-template').clone();
        //change the class
        $newSet.addClass('m-fieldset').removeClass('m-fieldset-template');
        $newSet.find('input').attr('value', '');
        $newSet.data('index', index +1);
        var newSet = $newSet[0].outerHTML;
        var newSetHTML = newSet.replaceAll('index-placeholder', (parseInt(index) +1 ) );
        $subparent.append(newSetHTML);

    });

    jQuery(document).on('click', '.rdm_image_upload', function(e) {
        uploadInputID = jQuery(this).prev('input');
        formfield = jQuery('.rdm_image_upload').attr('name');
        tb_show('', 'media-upload.php?type=image&amp;TB_iframe=true&width=650&height=800');
        return false;
    });
    window.old_send_to_editor = window.send_to_editor;
    window.send_to_editor = function(html) {
        if (typeof uploadInputID === 'undefined') {
            window.old_send_to_editor(html);
            return;
        } else {
            imgurl = jQuery('img',html).attr('src');
            href = jQuery(html).attr('href');
            url = imgurl === '' || (typeof imgurl === 'undefined') ? href : imgurl;
            uploadInputID.val(url);
            tb_remove();
        }
    }

    $('#importer-settings-form').submit(function() {
        $('.m-fieldset-template').remove();
        return true;
    });
});

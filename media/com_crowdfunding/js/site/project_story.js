jQuery(document).ready(function() {
	"use strict";

	// Style file input
	jQuery('#jform_pitch_image').fileinput({
        showPreview: false,
        showUpload: false,
        browseClass: "btn btn-success",
        browseLabel: Joomla.JText._('COM_CROWDFUNDING_PICK_IMAGE'),
        browseIcon: '<span class="fa fa-picture-o"></span> ',
        removeClass: "btn btn-danger",
        removeLabel: Joomla.JText._('COM_CROWDFUNDING_REMOVE'),
        removeIcon: '<span class="fa fa-trash"></span> ',
        layoutTemplates: {
            main1:
            "<div class=\'input-group {class}\'>\n" +
            "   <div class=\'input-group-btn\'>\n" +
            "       {browse}\n" +
            "       {remove}\n" +
            "   </div>\n" +
            "   {caption}\n" +
            "</div>"
        }
    });

});
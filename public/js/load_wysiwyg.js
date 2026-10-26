/*
 * load_wysiwyg.js - turns #summernote into a rich text editor with Summernote-lite.
 * Summernote still requires jQuery: load scripts('jquery'), scripts('wysiwyg_js') and scripts('wysiwyg_css')
 * on the page before this file. The rest of Caligrafy does not need jQuery.
 */
$(function() {
        if (document.documentElement.clientWidth > 600) {
            configuration = {
                minHeight: 200,
                //maxHeight: 400,
				dialogsInBody: true,
                toolbar:[
                         ['style', ['style', 'fontsize', 'bold', 'underline']],
                         ['para', ['ul', 'ol', 'paragraph', 'height']],
                         ['insert', ['link', 'table', 'picture', 'video', 'codeview']]
                ]
            };
        } else if(document.documentElement.clientWidth > 320) {
            configuration = {
                minHeight: 200,
                //maxHeight: 400,
				dialogsInBody: true,
                toolbar:[
                         ['style', ['style', 'fontsize', 'bold', 'underline']],
                         ['para', ['ul', 'ol', 'paragraph']]
                ]
            };
        } else {
            configuration = {
                minHeight: 200,
                //maxHeight: 400,
				dialogsInBody: true,
                toolbar:[
                         ['style', ['style', 'fontsize', 'bold', 'underline']]
                ]
            };            
            
        }
	$('#summernote').summernote(configuration);
});
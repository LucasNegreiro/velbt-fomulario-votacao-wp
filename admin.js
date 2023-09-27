jQuery(document).ready(function($) {
    var mediaUploader;
    $('#upload-button').click(function(e) {
        e.preventDefault();
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Escolha uma imagem',
            button: {
                text: 'Escolha uma imagem'
            },
            multiple: false
        });
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#logo_url').val(attachment.url);
        });
        mediaUploader.open();
    });
});

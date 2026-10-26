/*!
 * picture_uploader.js - click an <img data-type="editable" id="..."> to pick a new picture (max 2 MB) and preview it.
 * A hidden file input named after the image id is added to the form #kp_new_form (or to the image's form).
 * No jQuery needed.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('img[data-type=editable]').forEach(function (img) {
        var input = document.createElement('input');
        input.type = 'file';
        input.hidden = true;
        input.className = 'd-none';
        input.accept = 'image/*';
        input.name = img.id;
        input.setAttribute('data-image-placeholder', img.id);

        var form = document.getElementById('kp_new_form') || img.closest('form') || img.parentElement;
        form.appendChild(input);

        img.addEventListener('click', function () {
            var globalError = document.getElementById('global_error_message');
            if (globalError) globalError.className = 'd-none';
            input.click();
        });

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            var pictureError = document.getElementById('picture_error');
            if (!file) return;
            if (file.size / 1024 / 1024 <= 2) {
                var reader = new FileReader();
                reader.onload = function (e) { img.src = e.target.result; };
                reader.readAsDataURL(file);
                if (pictureError) pictureError.className = 'error d-none';
            } else if (pictureError) {
                pictureError.className = 'error show';
            }
        });
    });
});

(function(Drupal, once) {

    Drupal.behaviors.siteFeedbackImageUpload = {

        attach: function(context) {

            once(
                'site-feedback-image-upload',
                '.site-feedback-image-upload',
                context
            ).forEach(function(fileInput) {

                /*
                 * Find the feedback form.
                 */
                const form = fileInput.closest('form');

                if (!form) {
                    return;
                }

                /*
                 * Hidden field containing uploaded FIDs.
                 */
                const fidInput = form.querySelector('.site-feedback-image-fids');

                const previewContainer = document.createElement('div');

                previewContainer.className = 'site-feedback-image-preview';

                const uploadWrapper = fileInput.closest('.site-feedback-image-upload-wrapper');

                if (uploadWrapper && uploadWrapper.parentNode) {
                    uploadWrapper.parentNode.insertBefore(previewContainer, uploadWrapper.nextSibling);
                } else {
                    fileInput.parentNode.appendChild(previewContainer);
                }

                let uploadedFiles = [];

                form.addEventListener('siteFeedbackImageUploaded',
                    function(event) {
                        if (!event.detail || !event.detail.fid) {
                            return;
                        }

                        uploadedFiles.push({
                            fid: event.detail.fid,
                            filename: event.detail.filename,
                            filesize: event.detail.filesize,
                            url: event.detail.url,
                            uri: event.detail.uri
                        });

                        updateFidInput();
                        renderPreviews();

                    }
                );

                function formatFileSize(bytes) {

                    if (bytes === 0) {
                        return '0 Bytes';
                    }

                    const units = ['Bytes', 'KB', 'MB', 'GB'];
                    const index = Math.floor(Math.log(bytes) / Math.log(1024));

                    return (
                        parseFloat((bytes / Math.pow(1024, index)).toFixed(2)) + ' ' + units[index]
                    );
                }

                function validateFile(file) {
                    const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];

                    const maxSize = 5 * 1024 * 1024;

                    if (!allowedTypes.includes(file.type)) {
                        alert(Drupal.t('The file "@filename" is not a supported image format. Please select PNG, JPG, JPEG or WebP.', {
                            '@filename': file.name
                        }));
                        return false;
                    }

                    if (file.size > maxSize) {
                        alert(Drupal.t('The file "@filename" exceeds the maximum size of 5 MB.', {
                            '@filename': file.name
                        }));
                        return false;
                    }
                    return true;
                }

                /**
                 * Check duplicate file.
                 */
                function isDuplicate(file) {
                    return uploadedFiles.some(
                        function(existingFile) {
                            return (
                                existingFile.filename === file.name &&
                                existingFile.filesize === file.size
                            );
                        }
                    );
                }

                /**
                 * Update hidden FID field.
                 */
                function updateFidInput() {
                    if (!fidInput) {
                        return;
                    }

                    const fids = uploadedFiles.map(
                        function(file) {
                            return file.fid;
                        }
                    );

                    fidInput.value =
                        fids.join(',');
                }

                /**
                 * Upload file.
                 */
                async function uploadFile(file) {

                    const formData =
                        new FormData();

                    formData.append(
                        'file',
                        file
                    );

                    try {
                        const tokenResponse =
                            await fetch(
                                Drupal.url('session/token'),
                                { credentials: 'same-origin' }
                            );

                        if (!tokenResponse.ok) {
                            throw new Error(
                                Drupal.t(
                                    'Unable to obtain security token.'
                                )
                            );
                        }

                        const csrfToken =
                            await tokenResponse.text();

                        /*
                         * Upload image.
                         */
                        const response =
                            await fetch(
                                Drupal.url('site-feedback/upload-image'), {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'X-CSRF-Token': csrfToken
                                    }
                                }
                            );

                        const responseText = await response.text();
                        let result;
                        try {
                            result = JSON.parse(responseText);
                        }
                        catch (error) {
                            throw new Error(
                                Drupal.t('Image upload failed.')
                            );
                        }

                        if (!response.ok || !result.success) {
                            throw new Error(result.message || Drupal.t('Image upload failed.'));
                        }

                        /*
                         * Add uploaded file.
                         */
                        uploadedFiles.push({
                            fid: result.fid,
                            filename: result.filename,
                            filesize: result.filesize,
                            url: result.url,
                            uri: result.uri
                        });

                        updateFidInput();
                        renderPreviews();

                    } catch (error) {
                        alert(error.message || Drupal.t('Unable to upload the image.'));
                    }

                }

                /**
                 * Delete uploaded file.
                 */
                async function deleteFile(
                    file,
                    index
                ) {

                    try {

                        /*
                         * Get CSRF token.
                         */
                        const tokenResponse =
                            await fetch(
                                Drupal.url('session/token'),
                                { credentials: 'same-origin' }
                            );

                        if (!tokenResponse.ok) {
                            throw new Error(
                                Drupal.t(
                                    'Unable to obtain security token.'
                                )
                            );
                        }

                        const csrfToken =
                            await tokenResponse.text();

                        /*
                         * Delete image.
                         */
                        const response =
                            await fetch(
                                Drupal.url(
                                    'site-feedback/delete-image/' +
                                    file.fid
                                ), {
                                    method: 'DELETE',
                                    credentials: 'same-origin',
                                    headers: {
                                        'X-CSRF-Token': csrfToken
                                    }
                                }
                            );

                        const responseText = await response.text();
                        let result;
                        try {
                            result = JSON.parse(responseText);
                        }
                        catch (error) {
                            throw new Error(
                                Drupal.t('Unable to delete the image.')
                            );
                        }

                        if (
                            !response.ok ||
                            !result.success
                        ) {

                            throw new Error(
                                result.message ||
                                Drupal.t(
                                    'Unable to delete the image.'
                                )
                            );
                        }

                        /*
                         * Remove from array.
                         */
                        uploadedFiles.splice(
                            index,
                            1
                        );

                        /*
                         * Update hidden field.
                         */
                        updateFidInput();

                        /*
                         * Refresh previews.
                         */
                        renderPreviews();

                    } catch (error) {

                        alert(
                            error.message ||
                            Drupal.t(
                                'Unable to delete the image.'
                            )
                        );

                    }

                }

                /**
                 * Render previews.
                 */
                function renderPreviews() {

                    previewContainer.innerHTML = '';

                    uploadedFiles.forEach(
                        function(file, index) {

                            /*
                             * Preview item.
                             */
                            const previewItem =
                                document.createElement('div');

                            previewItem.className =
                                'site-feedback-image-preview-item';

                            /*
                             * Thumbnail.
                             */
                            const thumbnail =
                                document.createElement('div');

                            thumbnail.className =
                                'site-feedback-image-thumbnail';

                            /*
                             * Image link.
                             */
                            const imageLink =
                                document.createElement('a');

                            imageLink.href =
                                file.url;

                            imageLink.target =
                                '_blank';

                            imageLink.rel =
                                'noopener noreferrer';

                            imageLink.title =
                                Drupal.t(
                                    'Open image in new tab'
                                );

                            /*
                             * Image.
                             */
                            const image =
                                document.createElement('img');

                            image.src =
                                file.url;

                            image.alt =
                                file.filename;

                            imageLink.appendChild(
                                image
                            );

                            thumbnail.appendChild(
                                imageLink
                            );

                            /*
                             * Remove button.
                             */
                            const removeButton =
                                document.createElement('button');

                            removeButton.type =
                                'button';

                            removeButton.className =
                                'site-feedback-image-remove';

                            removeButton.innerHTML =
                                '&times;';

                            removeButton.setAttribute(
                                'aria-label',
                                Drupal.t(
                                    'Remove @filename', {
                                        '@filename': file.filename
                                    }
                                )
                            );

                            removeButton.title =
                                Drupal.t(
                                    'Remove image'
                                );

                            removeButton.addEventListener(
                                'click',
                                function() {

                                    deleteFile(
                                        file,
                                        index
                                    );

                                }
                            );

                            thumbnail.appendChild(
                                removeButton
                            );

                            /*
                             * File information.
                             */
                            const fileInfo =
                                document.createElement('div');

                            fileInfo.className =
                                'site-feedback-image-info';

                            /*
                             * Filename link.
                             */
                            const imageTitle =
                                document.createElement('a');

                            imageTitle.className =
                                'site-feedback-image-name';

                            imageTitle.href =
                                file.url;

                            imageTitle.target =
                                '_blank';

                            imageTitle.rel =
                                'noopener noreferrer';

                            imageTitle.textContent =
                                file.filename;

                            imageTitle.title =
                                Drupal.t(
                                    'Open image in new tab'
                                );

                            /*
                             * File size.
                             */
                            const fileSize =
                                document.createElement('div');

                            fileSize.className =
                                'site-feedback-image-size';

                            fileSize.textContent =
                                formatFileSize(
                                    file.filesize
                                );

                            /*
                             * Add information.
                             */
                            fileInfo.appendChild(
                                imageTitle
                            );

                            fileInfo.appendChild(
                                fileSize
                            );

                            /*
                             * Add preview.
                             */
                            previewItem.appendChild(
                                thumbnail
                            );

                            previewItem.appendChild(
                                fileInfo
                            );

                            previewContainer.appendChild(
                                previewItem
                            );

                        }
                    );

                }

                /**
                 * Handle image selection.
                 */
                fileInput.addEventListener(
                    'change',
                    async function() {

                        const files =
                            Array.from(
                                fileInput.files
                            );

                        /*
                         * Upload each image.
                         */
                        for (const file of files) {

                            /*
                             * Validate.
                             */
                            if (!validateFile(file)) {
                                continue;
                            }

                            /*
                             * Check duplicate.
                             */
                            if (isDuplicate(file)) {
                                continue;
                            }

                            /*
                             * Upload immediately.
                             */
                            await uploadFile(file);

                        }

                        /*
                         * Clear the native input.
                         *
                         * This allows the user to select
                         * the same file again later.
                         */
                        fileInput.value = '';

                    }
                );

            });

        }

    };

})(Drupal, once);
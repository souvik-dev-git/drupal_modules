(function(Drupal, once) {
    Drupal.behaviors.siteFeedback = {
        attach: function(context) {
            once('site-feedback', 'body', context).forEach(function() {
                // Create floating feedback button.
                const feedbackButton = document.createElement('button');
                feedbackButton.type = 'button';
                feedbackButton.className = 'site-feedback-button';
                feedbackButton.setAttribute('aria-label', 'Give Feedback');
                feedbackButton.setAttribute('title', 'Give Feedback');
                feedbackButton.innerHTML = `
          <span class="site-feedback-icon">
            <i class="fa-solid fa-thumbs-up"></i>
          </span>
          <span class="site-feedback-label">Give Feedback</span>
        `;
                // Create popup.
                const feedbackModal = document.createElement('div');
                feedbackModal.className = 'site-feedback-modal';
                feedbackModal.setAttribute('role', 'dialog');
                feedbackModal.setAttribute('aria-modal', 'true');
                feedbackModal.setAttribute('aria-labelledby', 'site-feedback-modal-title');
                feedbackModal.hidden = true;
                feedbackModal.innerHTML = `
          <div class="site-feedback-overlay"></div>

          <div class="site-feedback-dialog">

            <div class="site-feedback-header">

              <h2 id="site-feedback-modal-title">
                Website Experience Feedback
              </h2>

              <button
                type="button"
                class="site-feedback-close"
                aria-label="Close"
              >
                &times;
              </button>

            </div>

            <div class="site-feedback-body">
              <div class="site-feedback-loading">
                Loading feedback form...
              </div>
            </div>

          </div>
        `;
                document.body.appendChild(feedbackButton);
                document.body.appendChild(feedbackModal);
                const closeButton = feedbackModal.querySelector('.site-feedback-close');
                const feedbackBody = feedbackModal.querySelector('.site-feedback-body');
                // Open popup.
                feedbackButton.addEventListener('click', function() {
                    feedbackModal.hidden = false;
                    document.body.classList.add('site-feedback-modal-open');
                    loadFeedbackForm();
                    closeButton.focus();
                });
                // Close popup.
                closeButton.addEventListener('click', function() {
                    closeFeedbackModal();
                });
                // Close popup using Escape key.
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !feedbackModal.hidden) {
                        closeFeedbackModal();
                    }
                });
                /**
                 * Load feedback form.
                 */
                function loadFeedbackForm() {
                    if (feedbackBody.querySelector('#site-feedback-form-wrapper')) {
                        return;
                    }
                    const formUrl = Drupal.url('site-feedback/form');
                    fetch(formUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Site-Feedback-Request': 'modal'
                        }
                    }).then(function(response) {
                        if (!response.ok) {
                            throw new Error('Unable to load feedback form.');
                        }
                        return response.text();
                    }).then(function(html) {
                        feedbackBody.innerHTML = html;
                        Drupal.attachBehaviors(feedbackBody);
                        attachFeedbackForm();
                    }).catch(function(error) {
                        console.error('Developer Feedback:', error);
                        feedbackBody.innerHTML = `
                <div class="site-feedback-error">
                  <p>
                    Unable to load the feedback form.
                    Please try again later.
                  </p>
                </div>
              `;
                    });
                }
                /**
                 * Attach custom feedback form submission.
                 */
                function attachFeedbackForm() {
                    const feedbackForm = feedbackBody.querySelector('#site-feedback-form-wrapper form');
                    if (!feedbackForm) {
                        return;
                    }
                    if (feedbackForm.dataset.siteFeedbackAttached === 'true') {
                        return;
                    }
                    feedbackForm.dataset.siteFeedbackAttached = 'true';
                    feedbackForm.addEventListener('submit', function(event) {
                        event.preventDefault();
                        /*
                         * Use browser validation for required fields.
                         */
                        if (!feedbackForm.checkValidity()) {
                            feedbackForm.reportValidity();
                            return;
                        }
                        const submitButton = feedbackForm.querySelector('.site-feedback-submit');
                        /*
                         * Prevent double submission.
                         */
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        const formData = new FormData(feedbackForm);
                        /*
                         * Obtain Drupal's CSRF token for the state-changing
                         * submission request.
                         */
                        fetch(Drupal.url('session/token'), {
                            credentials: 'same-origin'
                        }).then(function(tokenResponse) {
                            if (!tokenResponse.ok) {
                                throw new Error('Unable to obtain security token.');
                            }
                            return tokenResponse.text();
                        }).then(function(csrfToken) {
                            /*
                             * Submit to dedicated endpoint.
                             */
                            const submitUrl = Drupal.url('site-feedback/submit');
                            return fetch(submitUrl, {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-Token': csrfToken
                                }
                            });
                        }).then(function(response) {
                            return response.text().then(function(text) {
                                let data;
                                try {
                                    data = JSON.parse(text);
                                }
                                catch (error) {
                                    throw new Error('Feedback submission failed.');
                                }
                                if (!response.ok) {
                                    const error = new Error(
                                        data.message || 'Feedback submission failed.'
                                    );
                                    error.data = data;
                                    throw error;
                                }
                                return data;
                            });
                        }).then(function(data) {
                            /*
                             * Successful submission.
                             */
                            feedbackBody.innerHTML = `
                                <div class="site-feedback-success">

                                  <div class="site-feedback-success-icon" aria-hidden="true">
                                    <i class="fa-solid fa-check"></i>
                                  </div>

                                  <h3 class="site-feedback-success-title">
                                    Thank you for your feedback!
                                  </h3>

                                  <p class="site-feedback-success-message"></p>
                                  <div class="site-feedback-success-reference">

                                    <span class="site-feedback-success-reference-label">
                                      Feedback Reference:
                                    </span>

                                    <span
                                      class="site-feedback-reference-value"
                                      id="site-feedback-reference"
                                    ></span>

                                    <button type="button" class="site-feedback-copy-reference" title="Copy feedback reference" aria-label="Copy feedback reference">
                                      <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    </button>

                                  </div>

                                </div>
                              `;
                            const successMessage = feedbackBody.querySelector('.site-feedback-success-message');
                            const successReferenceElement = feedbackBody.querySelector('#site-feedback-reference');
                            const copyReference = feedbackBody.querySelector('.site-feedback-copy-reference');
                            if (successMessage) {
                                successMessage.textContent = data.message || '';
                            }
                            if (successReferenceElement) {
                                successReferenceElement.textContent = data.feedback_reference || '';
                            }
                            if (copyReference) {
                                copyReference.setAttribute('data-copy-value', data.feedback_reference || '');
                            }
                            const copyButtons = feedbackBody.querySelectorAll('.site-feedback-copy-reference');
                            copyButtons.forEach(function(copyButton) {
                                copyButton.addEventListener('click', async function() {
                                    const value = copyButton.getAttribute('data-copy-value');
                                    if (!value) {
                                        return;
                                    }
                                    try {
                                        await navigator.clipboard.writeText(value);
                                        copyButton.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
                                        copyButton.setAttribute('title', 'Copied');
                                        setTimeout(function() {
                                            copyButton.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i>';
                                            copyButton.setAttribute('title', 'Copy');
                                        }, 2000);
                                    } catch (error) {
                                        console.error('Unable to copy value:', error);
                                    }
                                });
                            });
                            const copyReferenceButton = feedbackBody.querySelector('.site-feedback-copy-reference');
                            const referenceElement = feedbackBody.querySelector('#site-feedback-reference');
                            if (copyReferenceButton && referenceElement) {
                                copyReferenceButton.addEventListener('click', async () => {
                                    const reference = referenceElement.textContent.trim();
                                    try {
                                        await navigator.clipboard.writeText(reference);
                                        copyReferenceButton.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
                                        copyReferenceButton.setAttribute('title', 'Copied');
                                        setTimeout(() => {
                                            copyReferenceButton.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i>';
                                            copyReferenceButton.setAttribute('title', 'Copy feedback reference');
                                        }, 2000);
                                    } catch (error) {
                                        console.error('Unable to copy feedback reference:', error);
                                    }
                                });
                            }
                        }).catch(function(error) {
                            console.error('Developer Feedback:', error);
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                            if (error.data && error.data.errors) {
                                Object.keys(error.data.errors).forEach(function(fieldName) {
                                    const field = feedbackForm.querySelector(`[name="${fieldName}"]`);
                                    if (field) {
                                        field.setAttribute('aria-invalid', 'true');
                                    }
                                });
                            }
                            let errorMessage = 'Unable to submit the feedback. ' + 'Please try again later.';
                            if (error.data && error.data.errors) {
                                errorMessage = Object.values(error.data.errors).join('<br>');
                            }
                            const existingError = feedbackBody.querySelector('.site-feedback-submit-error');
                            if (existingError) {
                                existingError.remove();
                            }
                            feedbackForm.insertAdjacentHTML('afterbegin', `
                                  <div class="
                                    site-feedback-error
                                    site-feedback-submit-error
                                  ">
                                    <p>
                                      ${errorMessage}
                                    </p>
                                  </div>
                                `);
                        });
                    });
                }
                /**
                 * Close feedback modal.
                 */
                function closeFeedbackModal() {
                    feedbackModal.hidden = true;
                    document.body.classList.remove('site-feedback-modal-open');
                    feedbackButton.focus();
                }
            });
        }
    };
    /**
     * ================================================================
     * Star Rating
     * ================================================================
     */
    Drupal.behaviors.customStarRating = {
        attach: function(context) {
            const elements = once('starRating', '.star-rating-container, div[data-drupal-selector="edit-rating"]', context);
            elements.forEach(function(container) {
                const items = Array.from(container.querySelectorAll('.form-type-radio, .form-item'));
                const radios = Array.from(container.querySelectorAll('input[type="radio"]'));
                items.forEach(function(item) {
                    const label = item.querySelector('label');
                    if (!label) {
                        return;
                    }
                    if (label.querySelector('.fa-star')) {
                        return;
                    }
                    const star = document.createElement('i');
                    star.classList.add('fa-regular', 'fa-star');
                    label.appendChild(star);
                });

                function getSelectedRating() {
                    const selectedRadio = radios.find(function(radio) {
                        return radio.checked;
                    });
                    return selectedRadio ? parseInt(selectedRadio.value, 10) : 0;
                }

                function setStarIcon(item, filled) {
                    const star = item.querySelector('.fa-star');
                    if (!star) {
                        return;
                    }
                    if (filled) {
                        star.classList.remove('fa-regular');
                        star.classList.add('fa-solid');
                    } else {
                        star.classList.remove('fa-solid');
                        star.classList.add('fa-regular');
                    }
                }

                function applySelectedRating() {
                    const selectedRating = getSelectedRating();
                    items.forEach(function(item, index) {
                        const filled = index < selectedRating;
                        setStarIcon(item, filled);
                        if (filled) {
                            item.classList.add('is-active');
                        } else {
                            item.classList.remove('is-active');
                        }
                    });
                }

                function applyHoverRating(rating) {
                    items.forEach(function(item, index) {
                        const filled = index < rating;
                        setStarIcon(item, filled);
                        if (filled) {
                            item.classList.add('is-hover');
                        } else {
                            item.classList.remove('is-hover');
                        }
                    });
                }
                items.forEach(function(item, index) {
                    item.addEventListener('mouseenter', function() {
                        const hoverRating = index + 1;
                        applyHoverRating(hoverRating);
                    });
                });
                container.addEventListener('mouseleave', function() {
                    items.forEach(function(item) {
                        item.classList.remove('is-hover');
                    });
                    applySelectedRating();
                });
                radios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        items.forEach(function(item) {
                            item.classList.remove('is-hover');
                        });
                        applySelectedRating();
                    });
                });
                applySelectedRating();
            });
        }
    };
    /**
     * ================================================================
     * Title Character Counter
     * ================================================================
     */
    Drupal.behaviors.feedbackTitleCharacterCounter = {
        attach: function(context) {
            const titleFields = once('feedback-title-character-counter', '[name="title"]', context);
            titleFields.forEach(function(titleField) {
                const maxLength = 150;
                const maxMessage = document.createElement('div');
                maxMessage.className = 'site-feedback-title-max';
                maxMessage.textContent = 'Maximum 150 characters.';
                const remainingMessage = document.createElement('div');
                remainingMessage.className = 'site-feedback-title-remaining';
                remainingMessage.textContent = '150 characters remaining.';
                titleField.insertAdjacentElement('afterend', maxMessage);
                maxMessage.insertAdjacentElement('afterend', remainingMessage);

                function updateCharacterCount() {
                    const currentLength = titleField.value.length;
                    const remaining = maxLength - currentLength;
                    remainingMessage.textContent = remaining + (remaining === 1 ? ' character remaining' : ' characters remaining');
                }
                titleField.addEventListener('input', updateCharacterCount);
                updateCharacterCount();
            });
        }
    };
    /**
     * ================================================================
     * Feedback Character Counter
     * ================================================================
     */
    Drupal.behaviors.feedbackCharacterCounter = {
        attach: function(context) {
            const feedbackFields = once('feedback-character-counter', '[name="feedback"]', context);
            feedbackFields.forEach(function(feedbackField) {
                const maxLength = 5000;
                const maxMessage = document.createElement('div');
                maxMessage.className = 'site-feedback-feedback-max';
                maxMessage.textContent = 'Maximum 5000 characters.';
                const remainingMessage = document.createElement('div');
                remainingMessage.className = 'site-feedback-feedback-remaining';
                remainingMessage.textContent = '5000 characters remaining.';
                feedbackField.insertAdjacentElement('afterend', maxMessage);
                maxMessage.insertAdjacentElement('afterend', remainingMessage);

                function updateCharacterCount() {
                    const currentLength = feedbackField.value.length;
                    const remaining = maxLength - currentLength;
                    remainingMessage.textContent = remaining + (remaining === 1 ? ' character remaining' : ' characters remaining');
                }
                feedbackField.addEventListener('input', updateCharacterCount);
                updateCharacterCount();
            });
        }
    };
    /**
     * ================================================================
     * Custom Upload Button
     * ================================================================
     */
    Drupal.behaviors.customImageUploadButton = {
        attach: function(context) {
            once('site-feedback-image-upload', '.site-feedback-image-upload', context).forEach(function(fileInput) {
                // Create wrapper.
                const wrapper = document.createElement('div');
                wrapper.className = 'site-feedback-image-upload-wrapper';
                // Create custom upload button.
                const uploadButton = document.createElement('button');
                uploadButton.type = 'button';
                uploadButton.className = 'site-feedback-upload-button';
                uploadButton.innerHTML = `
          <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
          <span>Upload Image</span>
        `;
                // Insert wrapper before the file input.
                fileInput.parentNode.insertBefore(wrapper, fileInput);
                // Move file input into wrapper.
                wrapper.appendChild(fileInput);
                // Add custom button.
                wrapper.appendChild(uploadButton);
                // Open native file picker.
                uploadButton.addEventListener('click', function() {
                    fileInput.click();
                });
            });
        }
    };
})(Drupal, once);
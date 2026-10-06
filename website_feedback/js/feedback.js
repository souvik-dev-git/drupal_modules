(function(Drupal, once) {
    Drupal.behaviors.websiteFeedback = {
        attach: function(context) {
            once('website-feedback', 'body', context).forEach(function() {
                // Create floating feedback button.
                const feedbackButton = document.createElement('button');
                feedbackButton.type = 'button';
                feedbackButton.className = 'website-feedback-button';
                feedbackButton.setAttribute('aria-label', 'Give Feedback');
                feedbackButton.setAttribute('title', 'Give Feedback');
                feedbackButton.innerHTML = `
          <span class="website-feedback-icon">
            <i class="fa-solid fa-thumbs-up"></i>
          </span>
          <span class="website-feedback-label">Share Feedback</span>
        `;
                // Create popup.
                const feedbackModal = document.createElement('div');
                feedbackModal.className = 'website-feedback-modal';
                feedbackModal.setAttribute('role', 'dialog');
                feedbackModal.setAttribute('aria-modal', 'true');
                feedbackModal.setAttribute('aria-labelledby', 'website-feedback-modal-title');
                feedbackModal.hidden = true;
                feedbackModal.innerHTML = `
          <div class="website-feedback-overlay"></div>

          <div class="website-feedback-dialog">

            <div class="website-feedback-header">

              <h2 id="website-feedback-modal-title">
                Website Experience Feedback
              </h2>

              <button
                type="button"
                class="website-feedback-close"
                aria-label="Close"
              >
                &times;
              </button>

            </div>

            <div class="website-feedback-body">
              <div class="website-feedback-loading">
                Loading feedback form...
              </div>
            </div>

          </div>
        `;
                document.body.appendChild(feedbackButton);
                document.body.appendChild(feedbackModal);
                const closeButton = feedbackModal.querySelector('.website-feedback-close');
                const feedbackBody = feedbackModal.querySelector('.website-feedback-body');
                // Open popup.
                feedbackButton.addEventListener('click', function() {
                    feedbackModal.hidden = false;
                    document.body.classList.add('website-feedback-modal-open');
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
                    if (feedbackBody.querySelector('#website-feedback-form-wrapper')) {
                        return;
                    }
                    const formUrl = Drupal.url('website-feedback/form');
                    fetch(formUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'X-Website-Feedback-Request': 'modal'
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
                        console.error('Website Feedback:', error);
                        feedbackBody.innerHTML = `
                <div class="website-feedback-error">
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
                    const feedbackForm = feedbackBody.querySelector('#website-feedback-form-wrapper form');
                    if (!feedbackForm) {
                        return;
                    }
                    if (feedbackForm.dataset.websiteFeedbackAttached === 'true') {
                        return;
                    }
                    feedbackForm.dataset.websiteFeedbackAttached = 'true';
                    feedbackForm.addEventListener('submit', function(event) {
                        event.preventDefault();
                        /*
                         * Use browser validation for required fields.
                         */
                        if (!feedbackForm.checkValidity()) {
                            feedbackForm.reportValidity();
                            return;
                        }
                        const submitButton = feedbackForm.querySelector('.website-feedback-submit');
                        /*
                         * Prevent double submission.
                         */
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        const formData = new FormData(feedbackForm);
                        /*
                         * Submit to dedicated endpoint.
                         */
                        const submitUrl = Drupal.url('website-feedback/submit');
                        fetch(submitUrl, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function(response) {
                            return response.json().then(function(data) {
                                if (!response.ok) {
                                    const error = new Error('Feedback submission failed.');
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
                                <div class="website-feedback-success">

                                  <div class="website-feedback-success-icon" aria-hidden="true">
                                    <i class="fa-solid fa-check"></i>
                                  </div>

                                  <h3 class="website-feedback-success-title">
                                    Thank you for your feedback!
                                  </h3>

                                  <p class="website-feedback-success-message">
                                    ${data.message}
                                  </p>
                                  <div class="website-feedback-success-reference">

                                    <span class="website-feedback-success-reference-label">
                                      Feedback Reference:
                                    </span>

                                    <span
                                      class="website-feedback-reference-value"
                                      id="website-feedback-reference"
                                    >
                                      ${data.feedback_reference}
                                    </span>

                                    <button type="button" class="website-feedback-copy-reference" data-copy-value="${data.feedback_reference}" title="Copy feedback reference" aria-label="Copy feedback reference">
                                      <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                    </button>

                                  </div>

                                  ${data.servicenow_ticket_number ? `
                                    <div class="website-feedback-success-reference">

                                      <span class="website-feedback-success-reference-label">
                                        ServiceNow Ticket:
                                      </span>

                                      <span
                                        class="website-feedback-reference-value"
                                        id="website-servicenow-ticket"
                                      >
                                        ${data.servicenow_ticket_number}
                                      </span>

                                      <button
                                        type="button"
                                        class="website-feedback-copy-reference"
                                        data-copy-value="${data.servicenow_ticket_number}"
                                        title="Copy ServiceNow ticket"
                                        aria-label="Copy ServiceNow ticket"
                                      >
                                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                      </button>

                                    </div>
                                    ` : ''
                                  }
                                </div>
                              `;
                            const copyButtons = feedbackBody.querySelectorAll('.website-feedback-copy-reference');
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
                            const copyReferenceButton = feedbackBody.querySelector('.website-feedback-copy-reference');
                            const referenceElement = feedbackBody.querySelector('#website-feedback-reference');
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
                            console.error('Website Feedback:', error);
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
                            const existingError = feedbackBody.querySelector('.website-feedback-submit-error');
                            if (existingError) {
                                existingError.remove();
                            }
                            feedbackForm.insertAdjacentHTML('afterbegin', `
                                  <div class="
                                    website-feedback-error
                                    website-feedback-submit-error
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
                    document.body.classList.remove('website-feedback-modal-open');
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
                maxMessage.className = 'website-feedback-title-max';
                maxMessage.textContent = 'Maximum 150 characters.';
                const remainingMessage = document.createElement('div');
                remainingMessage.className = 'website-feedback-title-remaining';
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
                maxMessage.className = 'website-feedback-feedback-max';
                maxMessage.textContent = 'Maximum 5000 characters.';
                const remainingMessage = document.createElement('div');
                remainingMessage.className = 'website-feedback-feedback-remaining';
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
            once(
                'website-feedback-custom-upload-button',
                '.website-feedback-image-upload',
                context
            ).forEach(function(fileInput) {

                // Create wrapper.
                const wrapper = document.createElement('div');
                wrapper.className = 'website-feedback-image-upload-wrapper';

                // Create custom upload button.
                const uploadButton = document.createElement('button');
                uploadButton.type = 'button';
                uploadButton.className = 'website-feedback-upload-button';
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
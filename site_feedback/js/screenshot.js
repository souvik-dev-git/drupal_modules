(function(Drupal, once) {

    Drupal.behaviors.siteFeedbackScreenshot = {

        attach: function(context) {

            once(
                'site-site-feedback-screenshot',
                '.site-feedback-screenshot-button',
                context
            ).forEach(function(screenshotButton) {

                const form = screenshotButton.closest('form');

                if (!form) {
                    return;
                }

                /*
                 * Create screenshot editor.
                 */
                const editor = document.createElement('div');

                editor.className = 'site-feedback-screenshot-editor';
                editor.hidden = true;

                editor.innerHTML = `
          <div class="site-feedback-screenshot-overlay"></div>

          <div class="site-feedback-screenshot-dialog">

            <div class="site-feedback-screenshot-header">

              <h3>
                Edit Screenshot
              </h3>

              <button
                type="button"
                class="site-feedback-screenshot-close"
                aria-label="Close"
              >
                &times;
              </button>

            </div>

            <div class="site-feedback-screenshot-toolbar">

              <button
                type="button"
                class="site-feedback-tool-button is-active"
                data-tool="highlight"
              >
                <i class="fa-solid fa-highlighter"></i>
                Highlight
              </button>

              <button
                type="button"
                class="site-feedback-tool-button"
                data-tool="blackout"
              >
                <i class="fa-solid fa-square"></i>
                Blackout
              </button>

              <button
                type="button"
                class="site-feedback-tool-button"
                data-tool="notes"
              >
                <i class="fa-regular fa-note-sticky"></i>
                Notes
              </button>

              <button
                type="button"
                class="site-feedback-tool-button"
                data-tool="undo"
              >
                <i class="fa-solid fa-rotate-left"></i>
                Undo
              </button>

              <button
                type="button"
                class="site-feedback-tool-button"
                data-tool="clear"
              >
                <i class="fa-solid fa-trash"></i>
                Clear
              </button>

            </div>

            <div class="site-feedback-screenshot-instruction">
              Select Highlight or Blackout, then drag over the area
              you want to mark.
            </div>

            <div class="site-feedback-screenshot-canvas-wrapper">

              <canvas
                class="site-feedback-screenshot-canvas"
              ></canvas>

            </div>

            <div class="site-feedback-screenshot-footer">

              <button
                type="button"
                class="site-feedback-screenshot-cancel"
              >
                Cancel
              </button>

              <button
                type="button"
                class="site-feedback-screenshot-upload"
              >
                <i class="fa-solid fa-upload"></i>
                Upload Screenshot
              </button>

            </div>

          </div>
        `;

                document.body.appendChild(editor);

                const canvas = editor.querySelector(
                    '.site-feedback-screenshot-canvas'
                );

                const canvasWrapper = editor.querySelector(
                    '.site-feedback-screenshot-canvas-wrapper'
                );

                const closeButton = editor.querySelector(
                    '.site-feedback-screenshot-close'
                );

                const cancelButton = editor.querySelector(
                    '.site-feedback-screenshot-cancel'
                );

                const uploadButton = editor.querySelector(
                    '.site-feedback-screenshot-upload'
                );

                const toolButtons = editor.querySelectorAll(
                    '.site-feedback-tool-button'
                );

                const instruction = editor.querySelector(
                    '.site-feedback-screenshot-instruction'
                );

                const ctx = canvas.getContext('2d');

                let originalCanvas = null;

                let currentTool = 'highlight';

                let isDrawing = false;

                let startX = 0;
                let startY = 0;

                /*
                 * Used when dragging an existing note.
                 */
                let isDraggingNote = false;

                let draggedNote = null;

                let noteDragOffsetX = 0;
                let noteDragOffsetY = 0;

                let noteDragMoved = false;

                /*
                 * Stores all annotations.
                 *
                 * Existing highlight/blackout annotations use:
                 *
                 * {
                 *   type,
                 *   x,
                 *   y,
                 *   width,
                 *   height
                 * }
                 *
                 * Notes use:
                 *
                 * {
                 *   type: 'notes',
                 *   x,
                 *   y,
                 *   text
                 * }
                 */
                let annotations = [];


                /*
                 * ------------------------------------------------------------
                 * Screenshot capture
                 * ------------------------------------------------------------
                 */

                screenshotButton.addEventListener(
                    'click',
                    async function() {

                        if (
                            typeof html2canvas === 'undefined'
                        ) {

                            alert(
                                Drupal.t(
                                    'The screenshot functionality is currently unavailable.'
                                )
                            );

                            return;
                        }

                        screenshotButton.disabled = true;

                        const originalButtonText =
                            screenshotButton.innerHTML;

                        screenshotButton.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin"></i> Capturing...';

                        /*
                         * Hide feedback UI while taking screenshot.
                         */
                        const feedbackButton =
                            document.querySelector(
                                '.site-feedback-button'
                            );

                        const feedbackModal =
                            document.querySelector(
                                '.site-feedback-modal'
                            );

                        const previousButtonVisibility =
                            feedbackButton ?
                            feedbackButton.style.visibility :
                            '';

                        const previousModalVisibility =
                            feedbackModal ?
                            feedbackModal.style.visibility :
                            '';

                        if (feedbackButton) {

                            feedbackButton.style.visibility =
                                'hidden';

                        }

                        if (feedbackModal) {

                            feedbackModal.style.visibility =
                                'hidden';

                        }

                        /*
                         * Allow browser to repaint before capture.
                         */
                        await new Promise(function(resolve) {

                            requestAnimationFrame(function() {

                                requestAnimationFrame(resolve);

                            });

                        });

                        try {

                            const fullWidth =
                                Math.max(
                                    document.body.scrollWidth,
                                    document.documentElement.scrollWidth,
                                    document.body.offsetWidth,
                                    document.documentElement.offsetWidth,
                                    document.documentElement.clientWidth
                                );

                            const fullHeight =
                                Math.max(
                                    document.body.scrollHeight,
                                    document.documentElement.scrollHeight,
                                    document.body.offsetHeight,
                                    document.documentElement.offsetHeight,
                                    document.documentElement.clientHeight
                                );

                            originalCanvas =
                                await html2canvas(
                                    document.documentElement, {
                                        backgroundColor: '#ffffff',

                                        useCORS: true,

                                        allowTaint: false,

                                        logging: false,

                                        scale: Math.min(
                                            window.devicePixelRatio || 1,
                                            2
                                        ),

                                        width: fullWidth,

                                        height: fullHeight,

                                        windowWidth: fullWidth,

                                        windowHeight: fullHeight,

                                        scrollX: 0,

                                        scrollY: 0,

                                        x: 0,

                                        y: 0,

                                        ignoreElements: function(element) {

                                            return (
                                                element.classList &&
                                                (
                                                    element.classList.contains(
                                                        'site-feedback-button'
                                                    ) ||
                                                    element.classList.contains(
                                                        'site-feedback-modal'
                                                    ) ||
                                                    element.classList.contains(
                                                        'site-feedback-screenshot-editor'
                                                    )
                                                )
                                            );

                                        }

                                    }
                                );

                            /*
                             * Open editor.
                             */
                            openEditor(originalCanvas);

                        } catch (error) {

                            console.error(
                                'Developer Feedback Screenshot:',
                                error
                            );

                            alert(
                                Drupal.t(
                                    'Unable to capture the full page screenshot.'
                                )
                            );

                        } finally {

                            if (feedbackButton) {

                                feedbackButton.style.visibility =
                                    previousButtonVisibility;

                            }

                            if (feedbackModal) {

                                feedbackModal.style.visibility =
                                    previousModalVisibility;

                            }

                            screenshotButton.disabled = false;

                            screenshotButton.innerHTML =
                                originalButtonText;

                        }

                    }
                );


                /*
                 * ------------------------------------------------------------
                 * Open editor
                 * ------------------------------------------------------------
                 */

                function openEditor(sourceCanvas) {

                    annotations = [];

                    isDrawing = false;

                    isDraggingNote = false;

                    draggedNote = null;

                    noteDragMoved = false;

                    canvas.style.cursor = '';

                    /*
                     * Copy screenshot into canvas.
                     */
                    canvas.width = sourceCanvas.width;

                    canvas.height = sourceCanvas.height;

                    ctx.clearRect(
                        0,
                        0,
                        canvas.width,
                        canvas.height
                    );

                    ctx.drawImage(
                        sourceCanvas,
                        0,
                        0
                    );

                    editor.hidden = false;

                    document.body.classList.add(
                        'site-feedback-screenshot-editor-open'
                    );

                    setActiveTool('highlight');

                    updateInstruction();

                }


                /*
                 * ------------------------------------------------------------
                 * Close editor
                 * ------------------------------------------------------------
                 */

                function closeEditor() {

                    editor.hidden = true;

                    document.body.classList.remove(
                        'site-feedback-screenshot-editor-open'
                    );

                    annotations = [];

                    originalCanvas = null;

                    isDrawing = false;

                    isDraggingNote = false;

                    draggedNote = null;

                    noteDragMoved = false;

                    canvas.style.cursor = '';

                }


                closeButton.addEventListener(
                    'click',
                    closeEditor
                );

                cancelButton.addEventListener(
                    'click',
                    closeEditor
                );


                /*
                 * ------------------------------------------------------------
                 * Tools
                 * ------------------------------------------------------------
                 */

                toolButtons.forEach(function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            const tool =
                                button.dataset.tool;

                            if (tool === 'undo') {

                                undoLastAnnotation();

                                return;

                            }

                            if (tool === 'clear') {

                                clearAnnotations();

                                return;

                            }

                            setActiveTool(tool);

                        }
                    );

                });


                function setActiveTool(tool) {

                    currentTool = tool;

                    /*
                     * Reset note dragging state when changing tools.
                     */
                    isDraggingNote = false;

                    draggedNote = null;

                    noteDragMoved = false;

                    canvas.style.cursor = '';

                    toolButtons.forEach(function(button) {

                        if (
                            button.dataset.tool === tool
                        ) {

                            button.classList.add(
                                'is-active'
                            );

                        } else {

                            button.classList.remove(
                                'is-active'
                            );

                        }

                    });

                    updateInstruction();

                }



                function updateInstruction() {

                    if (currentTool === 'blackout') {

                        instruction.textContent =
                            Drupal.t(
                                'Drag over the area you want to hide.'
                            );

                    } else if (currentTool === 'notes') {

                        instruction.textContent =
                            Drupal.t(
                                'Click to add a note. Drag an existing note to move it.'
                            );

                    } else {

                        instruction.textContent =
                            Drupal.t(
                                'Drag over the area you want to highlight.'
                            );

                    }

                }


                /*
                 * ------------------------------------------------------------
                 * Canvas coordinates
                 * ------------------------------------------------------------
                 */

                function getCanvasCoordinates(event) {

                    const rect =
                        canvas.getBoundingClientRect();

                    const scaleX =
                        canvas.width / rect.width;

                    const scaleY =
                        canvas.height / rect.height;

                    return {

                        x: (event.clientX - rect.left) *
                            scaleX,

                        y: (event.clientY - rect.top) *
                            scaleY

                    };

                }


                /*
                 * ------------------------------------------------------------
                 * Find note at position
                 * ------------------------------------------------------------
                 */

                // function findNoteAtPosition(
                //   position
                // ) {

                //   const markerRadius = 14;

                //   /*
                //    * Search backwards so that the most recently
                //    * created note is selected if notes overlap.
                //    */
                //   for (
                //     let index = annotations.length - 1;
                //     index >= 0;
                //     index--
                //   ) {

                //     const annotation =
                //       annotations[index];

                //     if (
                //       annotation.type !== 'notes'
                //     ) {

                //       continue;

                //     }

                //     const distanceX =
                //       position.x - annotation.x;

                //     const distanceY =
                //       position.y - annotation.y;

                //     const distance =
                //       Math.sqrt(
                //         (distanceX * distanceX) +
                //         (distanceY * distanceY)
                //       );

                //     if (
                //       distance <= markerRadius + 6
                //     ) {

                //       return annotation;

                //     }

                //   }

                //   return null;

                // }

                function findNoteAtPosition(position) {

                    const markerRadius = 14;
                    const markerHitPadding = 8;

                    /*
                     * Search backwards so the most recently created
                     * note is selected first when notes overlap.
                     */
                    for (
                        let index = annotations.length - 1; index >= 0; index--
                    ) {

                        const annotation =
                            annotations[index];

                        if (
                            annotation.type !== 'notes'
                        ) {
                            continue;
                        }

                        const x = annotation.x;
                        const y = annotation.y;
                        const text = annotation.text || '';

                        /*
                         * ------------------------------------------------------------
                         * Check note marker.
                         * ------------------------------------------------------------
                         */
                        const distanceX =
                            position.x - x;

                        const distanceY =
                            position.y - y;

                        const distance =
                            Math.sqrt(
                                (distanceX * distanceX) +
                                (distanceY * distanceY)
                            );

                        if (
                            distance <= markerRadius + markerHitPadding
                        ) {
                            return annotation;
                        }

                        /*
                         * ------------------------------------------------------------
                         * Calculate note text box.
                         *
                         * This must match the dimensions used in drawNote().
                         * ------------------------------------------------------------
                         */
                        const fontSize = 14;
                        const padding = 8;
                        const lineHeight = 20;

                        const maxTextWidth =
                            Math.min(
                                320,
                                canvas.width * 0.35
                            );

                        const words =
                            text.split(/\s+/);

                        const lines = [];

                        let currentLine = '';

                        ctx.save();

                        ctx.font =
                            `${fontSize}px Arial`;

                        words.forEach(
                            function(word) {

                                const testLine =
                                    currentLine ?
                                    currentLine + ' ' + word :
                                    word;

                                const testWidth =
                                    ctx.measureText(
                                        testLine
                                    ).width;

                                if (
                                    testWidth > maxTextWidth &&
                                    currentLine
                                ) {

                                    lines.push(
                                        currentLine
                                    );

                                    currentLine =
                                        word;

                                } else {

                                    currentLine =
                                        testLine;

                                }

                            }
                        );

                        if (currentLine) {
                            lines.push(currentLine);
                        }

                        let longestLineWidth = 0;

                        lines.forEach(
                            function(line) {

                                const width =
                                    ctx.measureText(line).width;

                                if (
                                    width > longestLineWidth
                                ) {

                                    longestLineWidth =
                                        width;

                                }

                            }
                        );

                        ctx.restore();

                        const boxWidth =
                            Math.min(
                                longestLineWidth + (padding * 2),
                                maxTextWidth + (padding * 2)
                            );

                        const boxHeight =
                            (lines.length * lineHeight) +
                            (padding * 2);

                        /*
                         * Same positioning logic as drawNote().
                         */
                        let boxX =
                            x + markerRadius + 8;

                        let boxY =
                            y - markerRadius - 8 - boxHeight;

                        if (
                            boxX + boxWidth > canvas.width
                        ) {

                            boxX =
                                x - markerRadius - 8 - boxWidth;

                        }

                        if (boxX < 0) {
                            boxX = 5;
                        }

                        if (boxY < 0) {

                            boxY =
                                y + markerRadius + 8;

                        }

                        if (
                            boxY + boxHeight > canvas.height
                        ) {

                            boxY =
                                canvas.height -
                                boxHeight -
                                5;

                        }

                        /*
                         * Add a small hit area around the text box.
                         */
                        const hitPadding = 4;

                        if (
                            position.x >= boxX - hitPadding &&
                            position.x <= boxX + boxWidth + hitPadding &&
                            position.y >= boxY - hitPadding &&
                            position.y <= boxY + boxHeight + hitPadding
                        ) {

                            return annotation;

                        }

                    }

                    return null;
                }


                /*
                 * ------------------------------------------------------------
                 * Update note cursor
                 * ------------------------------------------------------------
                 */

                function updateNoteCursor(position) {

                    if (currentTool !== 'notes') {

                        canvas.style.cursor = '';

                        return;

                    }

                    const note =
                        findNoteAtPosition(position);

                    if (note) {

                        canvas.style.cursor =
                            'grab';

                    } else {

                        canvas.style.cursor =
                            'default';

                    }

                }


                /*
                 * ------------------------------------------------------------
                 * Mouse drawing / note dragging
                 * ------------------------------------------------------------
                 */

                canvas.addEventListener(
                    'mousedown',
                    function(event) {

                        const position =
                            getCanvasCoordinates(event);


                        /*
                         * Notes mode.
                         */
                        if (currentTool === 'notes') {

                            const existingNote =
                                findNoteAtPosition(position);


                            /*
                             * Existing note found.
                             *
                             * Start dragging it.
                             */
                            if (existingNote) {

                                isDraggingNote = true;

                                draggedNote = existingNote;

                                noteDragMoved = false;

                                noteDragOffsetX =
                                    position.x -
                                    existingNote.x;

                                noteDragOffsetY =
                                    position.y -
                                    existingNote.y;

                                canvas.style.cursor =
                                    'grabbing';

                                return;

                            }


                            /*
                             * Empty area.
                             *
                             * Wait until mouseup before creating
                             * a new note.
                             */
                            isDrawing = true;

                            startX = position.x;

                            startY = position.y;

                            return;

                        }


                        /*
                         * Existing Highlight and Blackout behavior.
                         */
                        if (
                            currentTool !== 'highlight' &&
                            currentTool !== 'blackout'
                        ) {

                            return;

                        }

                        isDrawing = true;

                        startX = position.x;

                        startY = position.y;

                    }
                );


                canvas.addEventListener(
                    'mousemove',
                    function(event) {

                        const position =
                            getCanvasCoordinates(event);


                        /*
                         * Move existing note.
                         */
                        if (
                            isDraggingNote &&
                            draggedNote
                        ) {

                            const newX =
                                position.x -
                                noteDragOffsetX;

                            const newY =
                                position.y -
                                noteDragOffsetY;

                            /*
                             * Detect actual movement.
                             */
                            if (
                                Math.abs(newX - draggedNote.x) > 1 ||
                                Math.abs(newY - draggedNote.y) > 1
                            ) {

                                noteDragMoved = true;

                            }


                            /*
                             * Keep the note marker completely
                             * inside the screenshot.
                             */
                            const markerRadius = 14;

                            draggedNote.x =
                                Math.max(
                                    markerRadius,
                                    Math.min(
                                        canvas.width - markerRadius,
                                        newX
                                    )
                                );

                            draggedNote.y =
                                Math.max(
                                    markerRadius,
                                    Math.min(
                                        canvas.height - markerRadius,
                                        newY
                                    )
                                );

                            canvas.style.cursor =
                                'grabbing';

                            redrawCanvas();

                            return;

                        }


                        /*
                         * Notes mode.
                         *
                         * Change cursor when hovering over
                         * an existing note.
                         */
                        if (
                            currentTool === 'notes'
                        ) {

                            updateNoteCursor(position);

                        }


                        /*
                         * No drawing while using Notes.
                         */
                        if (
                            currentTool === 'notes'
                        ) {

                            return;

                        }


                        if (!isDrawing) {

                            return;

                        }


                        const width =
                            position.x - startX;

                        const height =
                            position.y - startY;

                        redrawCanvas();

                        drawPreviewAnnotation(
                            startX,
                            startY,
                            width,
                            height
                        );

                    }
                );


                canvas.addEventListener(
                    'mouseup',
                    function(event) {

                        /*
                         * Finish note dragging.
                         */
                        if (isDraggingNote) {

                            isDraggingNote = false;

                            draggedNote = null;

                            canvas.style.cursor =
                                currentTool === 'notes' ?
                                'default' :
                                '';

                            redrawCanvas();

                            return;

                        }


                        if (!isDrawing) {

                            return;

                        }

                        isDrawing = false;


                        /*
                         * Notes mode.
                         *
                         * If the user clicked without dragging,
                         * create a new note.
                         */
                        if (currentTool === 'notes') {

                            const position =
                                getCanvasCoordinates(event);

                            const distanceX =
                                position.x - startX;

                            const distanceY =
                                position.y - startY;

                            const distance =
                                Math.sqrt(
                                    (distanceX * distanceX) +
                                    (distanceY * distanceY)
                                );


                            /*
                             * Only create a note if this was
                             * effectively a click.
                             */
                            if (distance < 5) {

                                addNote(position);

                            }

                            updateNoteCursor(position);

                            return;

                        }


                        const position =
                            getCanvasCoordinates(event);

                        const width =
                            position.x - startX;

                        const height =
                            position.y - startY;


                        /*
                         * Ignore very small selections.
                         */
                        if (
                            Math.abs(width) < 5 ||
                            Math.abs(height) < 5
                        ) {

                            redrawCanvas();

                            return;

                        }

                        annotations.push({

                            type: currentTool,

                            x: startX,

                            y: startY,

                            width: width,

                            height: height

                        });

                        redrawCanvas();

                    }
                );


                canvas.addEventListener(
                    'mouseleave',
                    function() {

                        /*
                         * Stop dragging a note if the mouse leaves
                         * the canvas.
                         */
                        if (isDraggingNote) {

                            isDraggingNote = false;

                            draggedNote = null;

                            canvas.style.cursor =
                                currentTool === 'notes' ?
                                'default' :
                                '';

                            redrawCanvas();

                            return;

                        }


                        /*
                         * Notes do not create an annotation
                         * when leaving the canvas.
                         */
                        if (isDrawing) {

                            isDrawing = false;

                            if (
                                currentTool === 'notes'
                            ) {

                                redrawCanvas();

                                return;

                            }

                            redrawCanvas();

                        }

                    }
                );


                /*
                 * ------------------------------------------------------------
                 * Touch support
                 * ------------------------------------------------------------
                 */

                canvas.addEventListener(
                    'touchstart',
                    function(event) {

                        event.preventDefault();

                        const touch =
                            event.touches[0];

                        const position =
                            getTouchCoordinates(touch);


                        /*
                         * Notes mode.
                         */
                        if (currentTool === 'notes') {

                            const existingNote =
                                findNoteAtPosition(position);


                            /*
                             * Existing note.
                             *
                             * Start dragging.
                             */
                            if (existingNote) {

                                isDraggingNote = true;

                                draggedNote = existingNote;

                                noteDragMoved = false;

                                noteDragOffsetX =
                                    position.x -
                                    existingNote.x;

                                noteDragOffsetY =
                                    position.y -
                                    existingNote.y;

                                return;

                            }


                            /*
                             * Empty area.
                             *
                             * Wait until touchend before creating
                             * the note.
                             */
                            isDrawing = true;

                            startX = position.x;

                            startY = position.y;

                            return;

                        }


                        if (
                            currentTool !== 'highlight' &&
                            currentTool !== 'blackout'
                        ) {

                            return;

                        }

                        isDrawing = true;

                        startX = position.x;

                        startY = position.y;

                    }, {
                        passive: false
                    }
                );


                canvas.addEventListener(
                    'touchmove',
                    function(event) {

                        if (
                            !isDrawing &&
                            !isDraggingNote
                        ) {

                            return;

                        }

                        event.preventDefault();

                        const touch =
                            event.touches[0];

                        const position =
                            getTouchCoordinates(touch);


                        /*
                         * Move existing note.
                         */
                        if (
                            isDraggingNote &&
                            draggedNote
                        ) {

                            const newX =
                                position.x -
                                noteDragOffsetX;

                            const newY =
                                position.y -
                                noteDragOffsetY;


                            if (
                                Math.abs(newX - draggedNote.x) > 1 ||
                                Math.abs(newY - draggedNote.y) > 1
                            ) {

                                noteDragMoved = true;

                            }


                            /*
                             * Keep the note marker completely
                             * inside the screenshot.
                             */
                            const markerRadius = 14;

                            draggedNote.x =
                                Math.max(
                                    markerRadius,
                                    Math.min(
                                        canvas.width - markerRadius,
                                        newX
                                    )
                                );

                            draggedNote.y =
                                Math.max(
                                    markerRadius,
                                    Math.min(
                                        canvas.height - markerRadius,
                                        newY
                                    )
                                );

                            redrawCanvas();

                            return;

                        }


                        /*
                         * Notes mode.
                         *
                         * No preview while creating a new note.
                         */
                        if (
                            currentTool === 'notes'
                        ) {

                            return;

                        }


                        const width =
                            position.x - startX;

                        const height =
                            position.y - startY;

                        redrawCanvas();

                        drawPreviewAnnotation(
                            startX,
                            startY,
                            width,
                            height
                        );

                    }, {
                        passive: false
                    }
                );


                canvas.addEventListener(
                    'touchend',
                    function(event) {

                        event.preventDefault();


                        /*
                         * Finish note dragging.
                         */
                        if (isDraggingNote) {

                            isDraggingNote = false;

                            draggedNote = null;

                            redrawCanvas();

                            return;

                        }


                        if (!isDrawing) {

                            return;

                        }

                        isDrawing = false;


                        /*
                         * Notes mode.
                         */
                        if (currentTool === 'notes') {

                            const touch =
                                event.changedTouches[0];

                            const position =
                                getTouchCoordinates(touch);

                            const distanceX =
                                position.x - startX;

                            const distanceY =
                                position.y - startY;

                            const distance =
                                Math.sqrt(
                                    (distanceX * distanceX) +
                                    (distanceY * distanceY)
                                );


                            /*
                             * Only create a note for a tap,
                             * not a drag.
                             */
                            if (distance < 5) {

                                addNote(position);

                            }

                            return;

                        }


                        const touch =
                            event.changedTouches[0];

                        const position =
                            getTouchCoordinates(touch);

                        const width =
                            position.x - startX;

                        const height =
                            position.y - startY;


                        if (
                            Math.abs(width) < 5 ||
                            Math.abs(height) < 5
                        ) {

                            redrawCanvas();

                            return;

                        }

                        annotations.push({

                            type: currentTool,

                            x: startX,

                            y: startY,

                            width: width,

                            height: height

                        });

                        redrawCanvas();

                    }, {
                        passive: false
                    }
                );


                function getTouchCoordinates(touch) {

                    const rect =
                        canvas.getBoundingClientRect();

                    const scaleX =
                        canvas.width / rect.width;

                    const scaleY =
                        canvas.height / rect.height;

                    return {

                        x: (touch.clientX - rect.left) *
                            scaleX,

                        y: (touch.clientY - rect.top) *
                            scaleY

                    };

                }


                /*
                 * ------------------------------------------------------------
                 * Notes
                 * ------------------------------------------------------------
                 */

                function addNote(position) {

                    /*
                     * Ask the user for the note text.
                     */
                    const noteText =
                        window.prompt(
                            Drupal.t('Enter your note:')
                        );


                    /*
                     * Cancelled.
                     */
                    if (noteText === null) {

                        return;

                    }

                    const trimmedText =
                        noteText.trim();


                    /*
                     * Do not create empty notes.
                     */
                    if (trimmedText === '') {

                        return;

                    }


                    /*
                     * Add note to annotation collection.
                     */
                    annotations.push({

                        type: 'notes',

                        x: position.x,

                        y: position.y,

                        text: trimmedText

                    });


                    /*
                     * Redraw screenshot with note.
                     */
                    redrawCanvas();

                }


                /*
                 * ------------------------------------------------------------
                 * Drawing
                 * ------------------------------------------------------------
                 */

                function redrawCanvas() {

                    if (!originalCanvas) {

                        return;

                    }

                    ctx.clearRect(
                        0,
                        0,
                        canvas.width,
                        canvas.height
                    );

                    ctx.drawImage(
                        originalCanvas,
                        0,
                        0
                    );

                    annotations.forEach(
                        function(annotation) {

                            drawAnnotation(
                                annotation
                            );

                        }
                    );

                }


                function drawAnnotation(
                    annotation
                ) {

                    /*
                     * ----------------------------------------------------------
                     * Notes
                     * ----------------------------------------------------------
                     */

                    if (
                        annotation.type === 'notes'
                    ) {

                        drawNote(annotation);

                        return;

                    }


                    let x = annotation.x;

                    let y = annotation.y;

                    let width = annotation.width;

                    let height = annotation.height;


                    /*
                     * Normalize negative drag directions.
                     */
                    if (width < 0) {

                        x += width;

                        width = Math.abs(width);

                    }


                    if (height < 0) {

                        y += height;

                        height = Math.abs(height);

                    }


                    /*
                     * ----------------------------------------------------------
                     * Blackout
                     * ----------------------------------------------------------
                     */

                    if (
                        annotation.type === 'blackout'
                    ) {

                        ctx.save();

                        ctx.fillStyle =
                            'rgba(0, 0, 0, 0.95)';

                        ctx.fillRect(
                            x,
                            y,
                            width,
                            height
                        );

                        ctx.restore();

                    }

                    /*
                     * ----------------------------------------------------------
                     * Highlight
                     * ----------------------------------------------------------
                     */
                    else {

                        ctx.save();

                        ctx.fillStyle =
                            'rgba(255, 230, 0, 0.45)';

                        ctx.fillRect(
                            x,
                            y,
                            width,
                            height
                        );

                        ctx.strokeStyle =
                            'rgba(255, 193, 7, 0.9)';

                        ctx.lineWidth =
                            Math.max(
                                2,
                                canvas.width / 1500
                            );

                        ctx.strokeRect(
                            x,
                            y,
                            width,
                            height
                        );

                        ctx.restore();

                    }

                }


                /*
                 * ------------------------------------------------------------
                 * Draw Note
                 * ------------------------------------------------------------
                 */

                function drawNote(annotation) {

                    const x = annotation.x;

                    const y = annotation.y;

                    const text =
                        annotation.text || '';


                    /*
                     * Note marker.
                     */
                    const markerRadius = 14;


                    /*
                     * Note background.
                     */
                    ctx.save();

                    ctx.beginPath();

                    ctx.arc(
                        x,
                        y,
                        markerRadius,
                        0,
                        Math.PI * 2
                    );

                    ctx.fillStyle =
                        '#007396';

                    ctx.fill();


                    /*
                     * Note number.
                     *
                     * Calculate the number based on the note's
                     * current position among all note annotations.
                     */
                    const noteNumber =
                        annotations
                        .filter(function(item) {

                            return item.type === 'notes';

                        })
                        .indexOf(annotation) + 1;


                    /*
                     * Draw note number.
                     */
                    ctx.fillStyle =
                        '#ffffff';

                    ctx.font =
                        'bold 12px Arial';

                    ctx.textAlign =
                        'center';

                    ctx.textBaseline =
                        'middle';

                    ctx.fillText(
                        String(noteNumber),
                        x,
                        y
                    );

                    ctx.restore();


                    /*
                     * ----------------------------------------------------------
                     * Draw note text box
                     * ----------------------------------------------------------
                     */

                    const fontSize = 14;

                    const padding = 8;

                    const lineHeight = 20;

                    const maxTextWidth =
                        Math.min(
                            320,
                            canvas.width * 0.35
                        );


                    /*
                     * Split note into words so long text wraps.
                     */
                    const words =
                        text.split(/\s+/);

                    const lines = [];

                    let currentLine = '';

                    ctx.save();

                    ctx.font =
                        `${fontSize}px Arial`;

                    words.forEach(
                        function(word) {

                            const testLine =
                                currentLine ?
                                currentLine + ' ' + word :
                                word;

                            const testWidth =
                                ctx.measureText(
                                    testLine
                                ).width;

                            if (
                                testWidth > maxTextWidth &&
                                currentLine
                            ) {

                                lines.push(
                                    currentLine
                                );

                                currentLine =
                                    word;

                            } else {

                                currentLine =
                                    testLine;

                            }

                        }
                    );

                    if (currentLine) {

                        lines.push(
                            currentLine
                        );

                    }


                    /*
                     * Calculate box dimensions.
                     */
                    let longestLineWidth = 0;

                    lines.forEach(
                        function(line) {

                            const width =
                                ctx.measureText(
                                    line
                                ).width;

                            if (
                                width > longestLineWidth
                            ) {

                                longestLineWidth =
                                    width;

                            }

                        }
                    );


                    const boxWidth =
                        Math.min(
                            longestLineWidth + (padding * 2),
                            maxTextWidth + (padding * 2)
                        );

                    const boxHeight =
                        (lines.length * lineHeight) +
                        (padding * 2);


                    /*
                     * Position text box slightly away
                     * from the marker.
                     */
                    let boxX =
                        x + markerRadius + 8;

                    let boxY =
                        y - markerRadius - 8 - boxHeight;


                    /*
                     * Keep the box inside canvas.
                     */
                    if (
                        boxX + boxWidth > canvas.width
                    ) {

                        boxX =
                            x - markerRadius - 8 - boxWidth;

                    }


                    if (boxX < 0) {

                        boxX = 5;

                    }


                    if (boxY < 0) {

                        boxY =
                            y + markerRadius + 8;

                    }


                    if (
                        boxY + boxHeight > canvas.height
                    ) {

                        boxY =
                            canvas.height -
                            boxHeight -
                            5;

                    }


                    /*
                     * Text box shadow.
                     */
                    ctx.shadowColor =
                        'rgba(0, 0, 0, 0.25)';

                    ctx.shadowBlur = 6;

                    ctx.shadowOffsetX = 1;

                    ctx.shadowOffsetY = 2;


                    /*
                     * Text box.
                     */
                    ctx.fillStyle =
                        '#ffffff';

                    ctx.fillRect(
                        boxX,
                        boxY,
                        boxWidth,
                        boxHeight
                    );


                    /*
                     * Border.
                     */
                    ctx.shadowColor =
                        'transparent';

                    ctx.strokeStyle =
                        '#007396';

                    ctx.lineWidth = 1;

                    ctx.strokeRect(
                        boxX,
                        boxY,
                        boxWidth,
                        boxHeight
                    );


                    /*
                     * Text.
                     */
                    ctx.fillStyle =
                        '#222222';

                    ctx.textAlign =
                        'left';

                    ctx.textBaseline =
                        'top';

                    lines.forEach(
                        function(line, index) {

                            ctx.fillText(
                                line,
                                boxX + padding,
                                boxY +
                                padding +
                                (index * lineHeight)
                            );

                        }
                    );

                    ctx.restore();

                }


                function drawPreviewAnnotation(
                    x,
                    y,
                    width,
                    height
                ) {

                    drawAnnotation({

                        type: currentTool,

                        x: x,

                        y: y,

                        width: width,

                        height: height

                    });

                }


                /*
                 * ------------------------------------------------------------
                 * Undo
                 * ------------------------------------------------------------
                 */

                function undoLastAnnotation() {

                    if (!annotations.length) {

                        return;

                    }

                    annotations.pop();

                    redrawCanvas();

                }


                /*
                 * ------------------------------------------------------------
                 * Clear
                 * ------------------------------------------------------------
                 */

                function clearAnnotations() {

                    annotations = [];

                    redrawCanvas();

                }


                /*
                 * ------------------------------------------------------------
                 * Upload screenshot
                 * ------------------------------------------------------------
                 */

                uploadButton.addEventListener(
                    'click',
                    async function() {

                        if (
                            !canvas.width ||
                            !canvas.height
                        ) {

                            alert(
                                Drupal.t(
                                    'No screenshot is available.'
                                )
                            );

                            return;

                        }

                        uploadButton.disabled = true;

                        const originalText =
                            uploadButton.innerHTML;

                        uploadButton.innerHTML =
                            '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';

                        try {

                            const blob =
                                await canvasToBlob(
                                    canvas
                                );

                            if (!blob) {

                                throw new Error(
                                    Drupal.t(
                                        'Unable to create the screenshot image.'
                                    )
                                );

                            }


                            /*
                             * Get CSRF token.
                             */
                            const tokenResponse =
                                await fetch(
                                    Drupal.url('session/token'), {
                                        credentials: 'same-origin'
                                    }
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
                             * Prepare upload.
                             */
                            const formData =
                                new FormData();

                            const now = new Date();

                            const timestamp =
                                now.getFullYear() +
                                String(now.getMonth() + 1).padStart(2, '0') +
                                String(now.getDate()).padStart(2, '0') +
                                '-' +
                                String(now.getHours()).padStart(2, '0') +
                                String(now.getMinutes()).padStart(2, '0') +
                                String(now.getSeconds()).padStart(2, '0');

                            const screenshotFilename =
                                `site-feedback-screenshot-${timestamp}.png`;

                            formData.append(
                                'file',
                                blob,
                                screenshotFilename
                            );


                            /*
                             * Upload using existing endpoint.
                             */
                            const response =
                                await fetch(
                                    Drupal.url(
                                        'site-feedback/upload-image'
                                    ), {
                                        method: 'POST',

                                        body: formData,

                                        credentials: 'same-origin',

                                        headers: {
                                            'X-CSRF-Token': csrfToken
                                        }

                                    }
                                );


                            const result =
                                await response.json();


                            if (
                                !response.ok ||
                                !result.success
                            ) {

                                throw new Error(
                                    result.message ||
                                    Drupal.t(
                                        'Unable to upload screenshot.'
                                    )
                                );

                            }


                            /*
                             * Notify existing image-upload.js.
                             *
                             * This ensures the screenshot becomes part
                             * of the same uploaded image collection.
                             */
                            form.dispatchEvent(
                                new CustomEvent(
                                    'siteFeedbackImageUploaded', {
                                        bubbles: false,

                                        detail: {

                                            fid: result.fid,

                                            filename: result.filename,

                                            filesize: result.filesize,

                                            url: result.url,

                                            uri: result.uri

                                        }

                                    }
                                )
                            );


                            /*
                             * Close screenshot editor.
                             */
                            closeEditor();

                        } catch (error) {

                            console.error(
                                'Developer Feedback Screenshot Upload:',
                                error
                            );

                            alert(
                                error.message ||
                                Drupal.t(
                                    'Unable to upload screenshot.'
                                )
                            );

                        } finally {

                            uploadButton.disabled = false;

                            uploadButton.innerHTML =
                                originalText;

                        }

                    }
                );


                /*
                 * ------------------------------------------------------------
                 * Canvas to Blob
                 * ------------------------------------------------------------
                 */

                function canvasToBlob(
                    sourceCanvas
                ) {

                    return new Promise(
                        function(resolve) {

                            sourceCanvas.toBlob(
                                function(blob) {

                                    resolve(blob);

                                },
                                'image/png',
                                1
                            );

                        }
                    );

                }

            });

        }

    };

})(Drupal, once);
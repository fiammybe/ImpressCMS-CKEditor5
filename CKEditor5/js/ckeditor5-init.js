/**
 * CKEditor 5 initialization script for ImpressCMS
 * 
 * This file handles the initialization of CKEditor 5 instances
 * with robust error handling and debugging capabilities.
 */

// Initialize CKEditor 5 on the specified textarea
function initCKEditor5(options) {
    // Default options
    const defaults = {
        textareaId: '',
        toolbar: '["heading", "bold", "italic", "link", "bulletedList", "numberedList", "undo", "redo"]',
        language: 'en',
        debug: false
    };

    // Merge options with defaults
    const config = Object.assign({}, defaults, options);

    // Debug logging function
    const debug = (message, data) => {
        if (config.debug) {
            console.log(`CKEditor 5 Debug: ${message}`, data || '');
        }
    };

    debug(`Looking for textarea with ID: ${config.textareaId}`);

    // Find the textarea element - try different selectors
    let textareaElement = document.getElementById(config.textareaId);
    if (textareaElement) {
        debug('Found textarea by ID');
    }

    // If not found, try with name attribute
    if (!textareaElement) {
        textareaElement = document.querySelector(`textarea[name="${config.textareaId}"]`);
        if (textareaElement) {
            debug('Found textarea by name attribute');
        }
    }

    // If still not found, try with a more flexible selector
    if (!textareaElement) {
        debug(`Trying to find textarea with ID containing: ${config.textareaId}`);
        const textareas = document.querySelectorAll('textarea');
        debug(`Found ${textareas.length} textareas on the page`);

        for (let i = 0; i < textareas.length; i++) {
            debug(`Checking textarea #${i} with ID: ${textareas[i].id}`);
            if (textareas[i].id && textareas[i].id.indexOf(config.textareaId) !== -1) {
                textareaElement = textareas[i];
                debug(`Found matching textarea with ID: ${textareaElement.id}`);
                break;
            }
        }
    }

    // Last resort - try to find any textarea
    if (!textareaElement) {
        debug('Last resort - trying to find any textarea');
        const anyTextarea = document.querySelector('textarea');
        if (anyTextarea) {
            textareaElement = anyTextarea;
            debug(`Found a textarea as last resort with ID: ${textareaElement.id}`);
        }
    }

    // If we found the textarea, initialize CKEditor
    if (textareaElement) {
        // Parse toolbar configuration if it's a string
        let toolbarConfig = config.toolbar;
        if (typeof toolbarConfig === 'string') {
            try {
                toolbarConfig = JSON.parse(toolbarConfig);
            } catch (e) {
                debug(`Error parsing toolbar config: ${e.message}`);
            }
        }

        // CKEditor configuration
        const editorConfig = {
            toolbar: toolbarConfig,
            language: config.language,
            image: {
                toolbar: [
                    'imageTextAlternative',
                    'imageStyle:inline',
                    'imageStyle:block',
                    'imageStyle:side'
                ]
            },
            table: {
                contentToolbar: [
                    'tableColumn',
                    'tableRow',
                    'mergeTableCells'
                ]
            }
        };

        debug('Initializing CKEditor with config', editorConfig);

        // Create the editor
        ClassicEditor
            .create(textareaElement, editorConfig)
            .then(editor => {
                debug('CKEditor 5 initialized successfully');

                // Save data back to textarea on form submit
                const form = textareaElement.closest('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        textareaElement.value = editor.getData();
                    });
                }

                // Custom image upload adapter
                editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
                    return {
                        upload: () => {
                            return new Promise((resolve, reject) => {
                                // Open the image browser in a popup
                                const popup = window.open(config.imageBrowserUrl, 'imageBrowser', 'width=800,height=600');

                                // Handle the image selection
                                window.addEventListener('message', function(event) {
                                    if (event.data && event.data.imageUrl) {
                                        resolve({
                                            default: event.data.imageUrl
                                        });
                                    } else {
                                        reject('Image upload failed');
                                    }
                                }, { once: true });
                            });
                        }
                    };
                };
            })
            .catch(error => {
                console.error('CKEditor 5 initialization failed:', error);

                // Display a user-friendly error message
                if (config.debug) {
                    // In debug mode, show detailed error
                    const errorDiv = document.createElement('div');
                    errorDiv.style.color = 'red';
                    errorDiv.style.padding = '10px';
                    errorDiv.style.border = '1px solid red';
                    errorDiv.style.margin = '10px 0';
                    errorDiv.innerHTML = '<strong>CKEditor 5 Error:</strong> ' + error.message + '<br><br>' +
                        '<small>Please check the browser console for more details.</small>';
                    textareaElement.parentNode.insertBefore(errorDiv, textareaElement);

                    // Log additional debug information
                    debug('Error details', {
                        textareaId: config.textareaId,
                        textareaElement: textareaElement,
                        textareaHTML: textareaElement.outerHTML,
                        config: editorConfig
                    });
                }

                // Make sure the original textarea is still usable
                textareaElement.style.display = 'block';
            });
    } else {
        console.error(`CKEditor 5 initialization failed: Could not find textarea element with ID or name ${config.textareaId}`);

        // Display a user-friendly error message
        if (config.debug) {
            // In debug mode, show detailed error
            const errorDiv = document.createElement('div');
            errorDiv.style.color = 'red';
            errorDiv.style.padding = '10px';
            errorDiv.style.border = '1px solid red';
            errorDiv.style.margin = '10px 0';
            errorDiv.innerHTML = '<strong>CKEditor 5 Error:</strong> Could not find textarea element with ID or name "' +
                config.textareaId + '"<br><br>' +
                '<small>Please check the browser console for more details.</small>';

            // Try to append to the form or body
            const form = document.querySelector('form');
            if (form) {
                form.appendChild(errorDiv);
            } else {
                document.body.appendChild(errorDiv);
            }

            // Log additional debug information
            debug('No textarea found', {
                textareaId: config.textareaId,
                allTextareas: document.querySelectorAll('textarea'),
                allForms: document.querySelectorAll('form')
            });
        }
    }
}

// Initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // This will be called by the PHP code that includes this script
    // The actual initialization happens when initCKEditor5() is called with options
});

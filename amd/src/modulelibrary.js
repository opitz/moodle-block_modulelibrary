/**
 * AMD module for block_modulelibrary
 * @module block_modulelibrary/modulelibrary
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification'],
    function($, ajax, templates, notification) {

    /**
     * Selected template module
     * @type {{cmid: number, name: string}|null}
     */
    let selectedModule = null;

    /** @type {number} Current course id */
    let currentCourseId = 0;

    /** @type {boolean} Whether a copy request is in progress */
    let copyInProgress = false;

    /** @type {string} localStorage key for selected template course */
    const selectedCourseStorageKey = 'block_modulelibrary_selected_course';

    /**
     * Initializes the Module Library JS.
     * @param {Object} opts
     */
    const init = function(opts) {
        opts = opts || {};
        currentCourseId = opts.currentCourseId || 0;
        bindEvents();
        restoreSelectedTemplateCourse();
    };

    /**
     * Bind all events for the module library UI
     */
    function bindEvents() {
        // When selecting a template course.
        $(document).on('change', '#modulelibrary-course-select', function() {
            const courseId = $(this).val();
            if (courseId) {
                storeSelectedTemplateCourse(courseId);
                loadTemplateCourseSections(courseId);
            } else {
                clearSelectedTemplateCourse();
                $('#modulelibrary-modules').empty();
                $('#modulelibrary-copy-form').empty();
            }
        });

        // When clicking "Copy this module".
        $(document).on('click', '.select-template-module-btn', function() {
            selectedModule = {cmid: $(this).data('cmid'), name: $(this).data('name')};
            showCopyForm();
        });

        // Handle copy confirmation.
        $(document).on('submit', '#copy-assessment-form', function(e) {
            e.preventDefault();
            doCopy();
        });

        // Cancel copy.
        $(document).on('click', '#cancel-copy-btn', function() {
            if (copyInProgress) {
                return;
            }
            selectedModule = null;
            $('#modulelibrary-copy-form').empty();
        });
    }

    /**
     * Store the selected template course.
     *
     * @param {string|number} courseId
     */
    function storeSelectedTemplateCourse(courseId) {
        window.localStorage.setItem(selectedCourseStorageKey, String(courseId));
    }

    /**
     * Clear the selected template course.
     */
    function clearSelectedTemplateCourse() {
        window.localStorage.removeItem(selectedCourseStorageKey);
    }

    /**
     * Restore the selected template course after page reloads.
     */
    function restoreSelectedTemplateCourse() {
        const courseId = window.localStorage.getItem(selectedCourseStorageKey);
        if (!courseId) {
            return;
        }

        const courseSelect = $('#modulelibrary-course-select');
        if (!courseSelect.find(`option[value="${courseId}"]`).length) {
            clearSelectedTemplateCourse();
            return;
        }

        courseSelect.val(courseId);
        loadTemplateCourseSections(courseId);
    }

    /**
     * Load sections and modules from the template course.
     * @param {number|string} courseId
     */
    function loadTemplateCourseSections(courseId) {
        const loading = document.querySelector('#modulelibrary-loading');
        const container = document.querySelector('#modulelibrary-modules');
        const formContainer = document.querySelector('#modulelibrary-copy-form');

        loading.style.display = 'block'; // Show a spinner.
        container.innerHTML = '';
        formContainer.innerHTML = '';

        ajax.call([{
            methodname: 'block_modulelibrary_get_template_course_modules',
            args: {courseid: parseInt(courseId, 10)}
        }])[0].done(function(response) {
            loading.style.display = 'none'; // Hide the spinner.
            templates.render('block_modulelibrary/modules', response)
                .then((html, js) => {
                    templates.appendNodeContents(container, html, js);
                    return null;
                })
                .catch(notification.exception);
        }).fail(function(err) {
            loading.style.display = 'none'; // Hide the spinner.
            notification.exception(err);
        });
    }

    /**
     * Show copy form for selected template module
     */
    function showCopyForm() {
        if (!selectedModule) {
            return;
        }

        $('#modulelibrary-copy-form').html('<p>Loading target sections...</p>');

        ajax.call([{
            methodname: 'block_modulelibrary_get_target_course_sections',
            args: {courseid: currentCourseId}
        }])[0].done(function(sections) {
            // Data to pass to the Mustache template.
            const data = {
                modulename: selectedModule.name,
                sections: sections
            };

            templates.render('block_modulelibrary/copy_form', data)
                .then(function(html, js) {
                    $('#modulelibrary-copy-form').html(html);
                    templates.runTemplateJS(js);
                    return null;
                })
                .fail(function(err) {
                    $('#modulelibrary-copy-form').html('<p>Failed to render form</p>');
                    notification.exception(err);
                });

        }).fail(function(err) {
            $('#modulelibrary-copy-form').html('<p>Failed to load target sections</p>');
            notification.exception(err);
        });
    }

    /**
     * Perform copy of selected template module
     */
    function doCopy() {
        if (!selectedModule) {
            notification.exception(new Error('No template module selected'));
            return;
        }

        if (copyInProgress) {
            return;
        }

        const targetsection = parseInt($('#target-section').val() || 0, 10);
        const confirmButton = $('#confirm-copy-btn');
        const cancelButton = $('#cancel-copy-btn');
        const targetSectionSelect = $('#target-section');
        const progressMessage = $('#copy-progress-message');
        const originalButtonText = confirmButton.text();

        copyInProgress = true;
        confirmButton.prop('disabled', true).text('Copying...');
        cancelButton.prop('disabled', true);
        targetSectionSelect.prop('disabled', true);
        progressMessage.removeClass('d-none');

        ajax.call([{
            methodname: 'block_modulelibrary_copy_activity',
            args: {
                cmid: parseInt(selectedModule.cmid, 10),
                targetcourseid: currentCourseId,
                targetsection: targetsection
            }
        }])[0].done(function(response) {
            if (response.status) {
                notification.addNotification({message: 'Module copied successfully', type: 'success'});
                setTimeout(function() {
                    window.location.reload();
                }, 1200);
            } else {
                copyInProgress = false;
                confirmButton.prop('disabled', false).text(originalButtonText);
                cancelButton.prop('disabled', false);
                targetSectionSelect.prop('disabled', false);
                progressMessage.addClass('d-none');
                notification.exception(new Error(response.message || 'Copy failed'));
            }
        }).fail(function(err) {
            copyInProgress = false;
            confirmButton.prop('disabled', false).text(originalButtonText);
            cancelButton.prop('disabled', false);
            targetSectionSelect.prop('disabled', false);
            progressMessage.addClass('d-none');
            notification.exception(err);
        });
    }

    return {init: init};
});

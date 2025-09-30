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

    /**
     * Initializes the Module Library JS.
     * @param {Object} opts
     */
    const init = function(opts) {
        opts = opts || {};
        currentCourseId = opts.currentCourseId || 0;
        bindEvents();
    };

    /**
     * Bind all events for the module library UI
     */
    function bindEvents() {
        // When selecting a template course.
        $(document).on('change', '#modulelibrary-course-select', function() {
            const courseId = $(this).val();
            if (courseId) {
                loadTemplateCourseSections(courseId);
            } else {
                $('#modulelibrary-modules').empty();
                $('#modulelibrary-copy-form').empty();
            }
        });

        // When clicking "Copy this module".
        $(document).on('click', '.select-template-module-btn', function() {
            selectedModule = { cmid: $(this).data('cmid'), name: $(this).data('name') };
            showCopyForm();
        });

        // Handle copy confirmation.
        $(document).on('submit', '#copy-assessment-form', function(e) {
            e.preventDefault();
            doCopy();
        });

        // Cancel copy.
        $(document).on('click', '#cancel-copy-btn', function() {
            selectedModule = null;
            $('#modulelibrary-copy-form').empty();
        });
    }

    /**
     * Load sections and modules from the template course.
     * @param {number} courseId
     */
    function loadTemplateCourseSections(courseId) {
        $('#modulelibrary-loading').show();
        $('#modulelibrary-modules').empty();
        $('#modulelibrary-copy-form').empty();

        ajax.call([{
            methodname: 'block_modulelibrary_get_template_course_modules',
            args: { courseid: parseInt(courseId, 10) }
        }])[0].done(function(response) {
            $('#modulelibrary-loading').hide();
            const data = response;
            if (!data || !data.sections?.length) {
                $('#modulelibrary-modules').html('<p>No modules found in this course.</p>');
                return;
            }
            let html = '<h4>' + data.title + '</h4>';
            data.sections.forEach(function(section) {
                html += '<h5>Section ' + section.section + ' - ' + (section.name || '') + '</h5><ul>';
                section.modules.forEach(function(m) {
                    html += '<li>' + m.modname + ': ' + m.name +
                        ' <button type="button" class="select-template-module-btn" data-cmid="' + m.cmid +
                        '" data-name="' + m.name + '">Copy this module</button>' + '</li>';
                });
                html += '</ul>';
            });
            $('#modulelibrary-modules').html(html);
        }).fail(function(err) {
            $('#modulelibrary-loading').hide();
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
            args: { courseid: currentCourseId }
        }])[0].done(function(sections) {
            // Data to pass to the Mustache template
            const data = {
                modulename: selectedModule.name,
                sections: sections
            };

            templates.render('block_modulelibrary/copy_form', data)
                .then(function(html, js) {
                    $('#modulelibrary-copy-form').html(html);
                    templates.runTemplateJS(js);
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
        const targetsection = parseInt($('#target-section').val() || 0, 10);

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
                setTimeout(function() { window.location.reload(); }, 1200);
            } else {
                notification.exception(new Error('Copy failed'));
            }
        }).fail(function(err) {
            notification.exception(err);
        });
    }

    return { init: init };
});

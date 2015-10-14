/**
 * Resource and activity toolbox class.
 *
 * This class is responsible for managing AJAX interactions with activities and resources
 * when viewing a course in editing mode.
 *
 * @module moodle-course-toolboxes
 * @namespace M.course.toolboxes
 */

/**
 * Section toolbox class.
 *
 * This class is responsible for managing AJAX interactions with sections
 * when viewing a course in editing mode.
 *
 * @class section
 * @constructor
 * @extends M.course.toolboxes.toolbox
 */
var SECTIONTOOLBOX = function() {
    SECTIONTOOLBOX.superclass.constructor.apply(this, arguments);
};

Y.extend(SECTIONTOOLBOX, TOOLBOX, {
    /**
     * An Array of events added when editing a title.
     * These should all be detached when editing is complete.
     *
     * @property edittitleevents
     * @protected
     * @type Array
     * @protected
     */
    edittitleevents: [],

    /**
     * Initialize the section toolboxes module.
     *
     * Updates all span.commands with relevant handlers and other required changes.
     *
     * @method initializer
     * @protected
     */
    initializer : function() {
        M.course.coursebase.register_module(this);

        // Section Highlighting.
        Y.delegate('click', this.toggle_highlight, SELECTOR.PAGECONTENT, SELECTOR.SECTIONLI + ' ' + SELECTOR.HIGHLIGHT, this);

        // Section Visibility.
        Y.delegate('click', this.toggle_hide_section, SELECTOR.PAGECONTENT, SELECTOR.SECTIONLI + ' ' + SELECTOR.SHOWHIDE, this);

        if (this.get('renaming')) {
            BODY.delegate('key', this.handle_data_action, 'down:enter', SELECTOR.SECTIONACTION, this);
            Y.delegate('click', this.handle_data_action, BODY, SELECTOR.SECTIONACTION, this);
        }
    },

    toggle_hide_section : function(e) {
        // Prevent the default button action.
        e.preventDefault();

        // Get the section we're working on.
        var section = e.target.ancestor(M.course.format.get_section_selector(Y)),
            button = e.target.ancestor('a', true),
            hideicon = button.one('img'),
            buttontext = button.one('span'),

        // The value to submit
            value,

        // The text for strings and images. Also determines the icon to display.
            action,
            nextaction;

        if (!section.hasClass(CSS.SECTIONHIDDENCLASS)) {
            section.addClass(CSS.SECTIONHIDDENCLASS);
            value = 0;
            action = 'hide';
            nextaction = 'show';
        } else {
            section.removeClass(CSS.SECTIONHIDDENCLASS);
            value = 1;
            action = 'show';
            nextaction = 'hide';
        }

        var newstring = M.util.get_string(nextaction + 'fromothers', 'format_' + this.get('format'));
        hideicon.setAttrs({
            'alt' : newstring,
            'src'   : M.util.image_url('i/' + nextaction)
        });
        button.set('title', newstring);
        if (buttontext) {
            buttontext.set('text', newstring);
        }

        // Change the show/hide status
        var data = {
            'class' : 'section',
            'field' : 'visible',
            'id'    : Y.Moodle.core_course.util.section.getId(section.ancestor(M.course.format.get_section_wrapper(Y), true)),
            'value' : value
        };

        var lightbox = M.util.add_lightbox(Y, section);
        lightbox.show();

        this.send_request(data, lightbox, function(response) {
            var activities = section.all(SELECTOR.ACTIVITYLI);
            activities.each(function(node) {
                var button;
                if (node.one(SELECTOR.SHOW)) {
                    button = node.one(SELECTOR.SHOW);
                } else {
                    button = node.one(SELECTOR.HIDE);
                }
                var activityid = Y.Moodle.core_course.util.cm.getId(node);

                // NOTE: resourcestotoggle is returned as a string instead
                // of a Number so we must cast our activityid to a String.
                if (Y.Array.indexOf(response.resourcestotoggle, "" + activityid) !== -1) {
                    M.course.resource_toolbox.handle_resource_dim(button, node, action);
                }
            }, this);
        });
    },

    /**
     * Toggle highlighting the current section.
     *
     * @method toggle_highlight
     * @param {EventFacade} e
     */
    toggle_highlight : function(e) {
        // Prevent the default button action.
        e.preventDefault();

        // Get the section we're working on.
        var section = e.target.ancestor(M.course.format.get_section_selector(Y));
        var button = e.target.ancestor('a', true);
        var buttonicon = button.one('img');
        var buttontext = button.one('span');

        // Determine whether the marker is currently set.
        var togglestatus = section.hasClass('current');
        var value = 0;

        // Set the current highlighted item text.
        var old_string = M.util.get_string('markthistopic', 'moodle');

        var selectedpage = Y.one(SELECTOR.PAGECONTENT);
        selectedpage
            .all(M.course.format.get_section_selector(Y) + '.current ' + SELECTOR.HIGHLIGHT)
            .set('title', old_string);
        selectedpage
            .all(M.course.format.get_section_selector(Y) + '.current ' + SELECTOR.HIGHLIGHT + ' span')
            .set('text', M.util.get_string('highlight', 'moodle'));
        selectedpage
            .all(M.course.format.get_section_selector(Y) + '.current ' + SELECTOR.HIGHLIGHT + ' img')
            .set('alt', old_string)
            .set('src', M.util.image_url('i/marker'));

        // Remove the highlighting from all sections.
        selectedpage.all(M.course.format.get_section_selector(Y))
            .removeClass('current');

        // Then add it if required to the selected section.
        if (!togglestatus) {
            section.addClass('current');
            value = Y.Moodle.core_course.util.section.getId(section.ancestor(M.course.format.get_section_wrapper(Y), true));
            var new_string = M.util.get_string('markedthistopic', 'moodle');
            button
                .set('title', new_string);
            buttonicon
                .set('alt', new_string)
                .set('src', M.util.image_url('i/marked'));
            if (buttontext) {
                buttontext
                    .set('text', M.util.get_string('highlightoff', 'moodle'));
            }
        }

        // Change the highlight status.
        var data = {
            'class' : 'course',
            'field' : 'marker',
            'value' : value
        };
        var lightbox = M.util.add_lightbox(Y, section);
        lightbox.show();
        this.send_request(data, lightbox);
    },

    /**
     * Handles the delegation event. When this is fired someone has triggered an action.
     *
     * Note not all actions will result in an AJAX enhancement.
     *
     * @protected
     * @method handle_data_action
     * @param {EventFacade} ev The event that was triggered.
     * @return {boolean}
     */
    handle_data_action: function(ev) {
        // We need to get the anchor element that triggered this event.
        var node = ev.target;
        if (!node.test('a')) {
            node = node.ancestor(SELECTOR.SECTIONACTION);
        }

        // From the anchor we can get both the activity (added during initialisation) and the action being
        // performed (added by the UI as a data attribute).
        var action = node.getData('action'),
            section = node.ancestor(SELECTOR.SECTIONBOX);

        if (!node.test('a') || !action || !section) {
            // It wasn't a valid action node.
            return;
        }

        // Switch based upon the action and do the desired thing.
        if (action == 'editsectiontitle') {
            // The user wishes to edit the title of the event.
            this.edit_section_title(ev, node, section);
        }
    },

    /**
     * Edit the title for the section
     *
     * @method edit_section_title
     * @protected
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} section The section node that this action will be performed on.
     * @chainable
     */
    edit_section_title: function(ev, button, section) {
        // Get the element we're working on
        var sectionid = Y.Moodle.core_course.util.section.getId(section),
            instancename  = section.one(SELECTOR.SECTIONNAME),
            instance = section.one(SELECTOR.SECTIONINSTANCE),
            currenttitle = instancename.get('firstChild'),
            oldtitle = currenttitle.get('data'),
            titletext = oldtitle,
            thisevent,
            anchor = instance,
            data = {
                'class': 'section',
                'field': 'gettitle',
                'id': sectionid
            };

        // Prevent the default actions.
        ev.preventDefault();

        this.send_request(data, null, function(response) {
            // Try to retrieve the existing string from the server
            if (response.instancename) {
                titletext = response.instancename;
            }

            // Create the editor and submit button
            var editform = Y.Node.create('<form action="#" />');
            var editinstructions = Y.Node.create('<span class="'+CSS.EDITINSTRUCTIONS+'" id="id_editinstructions" />')
                .set('innerHTML', M.util.get_string('edittitleinstructions', 'moodle'));
            var editor = Y.Node.create('<input name="title" type="text" class="'+CSS.TITLEEDITOR+'" />').setAttrs({
                'value': titletext,
                'autocomplete': 'off',
                'aria-describedby': 'id_editinstructions',
                'maxLength': '255'
            });

            // Clear the existing content and put the editor in
            editform.appendChild(editor);
            editform.setData('anchor', anchor);
            instance.insert(editinstructions, 'before');
            anchor.replace(editform);

            // We hide various components whilst editing:
            section.addClass(CSS.EDITINGTITLE);

            // Focus and select the editor text
            editor.focus().select();

            // Cancel the edit if we lose focus or the escape key is pressed.
            /*thisevent = editor.on('blur', this.edit_section_title_cancel, this, section, false);
            this.edittitleevents.push(thisevent);
            thisevent = editor.on('key', this.edit_section_title_cancel, 'esc', this, section, true);
            this.edittitleevents.push(thisevent);

            // Handle form submission.
            thisevent = editform.on('submit', this.edit_section_title_submit, this, section, oldtitle);
            this.edittitleevents.push(thisevent);*/
        });
        return this;
    },

    /**
     * Add a loading icon to the specified activity.
     *
     * The icon is added within the action area.
     *
     * @method add_spinner
     * @param {Node} section The section to add a loading icon to
     * @return {Node|null} The newly created icon, or null if the action area was not found.
     */
    add_spinner: function(section) {
        var actionarea = section.one(SELECTOR.SECTIONINSTANCE);
        if (actionarea) {
            return M.util.add_spinner(Y, actionarea);
        }
        return null;
    },

    /**
     * Handles the submit event when editing the section title.
     *
     * @method edit_section_title_submit
     * @protected
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} section The section whose title we are altering.
     * @param {String} originaltitle The original title the section had.
     */
    edit_section_title_submit: function(ev, section, originaltitle) {
        // We don't actually want to submit anything
        ev.preventDefault();

        var newtitle = Y.Lang.trim(section.one(SELECTOR.SECTIONFORM + ' ' + SELECTOR.ACTIVITYTITLE).get('value'));
        this.edit_section_title_clear(section);
        var spinner = this.add_spinner(section);
        if (newtitle !== null && newtitle !== originaltitle) {
            var data = {
                'class': 'section',
                'field': 'updatetitle',
                'title': newtitle,
                'id': Y.Moodle.core_course.util.section.getId(section)
            };
            this.send_request(data, spinner, function(response) {
                if (response.instancename) {
                    section.one(SELECTOR.SECTIONNAME).setContent(response.instancename);
                }
            });
        }
    },

    /**
     * Handles the cancel event when editing the section title.
     *
     * @method edit_section_title_cancel
     * @protected
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} section The section whose title we are altering.
     * @param {Boolean} preventdefault If true we should prevent the default action from occuring.
     */
    edit_section_title_cancel: function(ev, section, preventdefault) {
        if (preventdefault) {
            ev.preventDefault();
        }
        this.edit_section_title_clear(section);
    },

    /**
     * Handles clearing the editing UI and returning things to the original state they were in.
     *
     * @method edit_section_title_clear
     * @protected
     * @param {Node} section  The section whose title we were altering.
     */
    edit_section_title_clear: function(section) {
        // Detach all listen events to prevent duplicate triggers
        new Y.EventHandle(this.edittitleevents).detach();

        var editform = section.one(SELECTOR.SECTIONFORM),
            instructions = section.one('#id_editinstructions');
        if (editform) {
            editform.replace(editform.getData('anchor'));
        }
        if (instructions) {
            instructions.remove();
        }

        // Remove the editing class again to revert the display.
        section.removeClass(CSS.EDITINGTITLE);

        // Refocus the link which was clicked originally so the user can continue using keyboard nav.
        Y.later(100, this, function() {
            section.one(SELECTOR.EDITSECTIONTITLE).focus();
        });

        // TODO MDL-50768 This hack is to keep Behat happy until they release a version of
        // MinkSelenium2Driver that fixes
        // https://github.com/Behat/MinkSelenium2Driver/issues/80.
        if (!Y.one('input[name=title]')) {
            Y.one('body').append('<input type="text" name="title" style="display: none">');
        }
    }
}, {
    NAME : 'course-section-toolbox',
    ATTRS : {
        /**
         * Indicates if we should use AJAX section renaming.
         *
         * @attribute renaming
         * @default 0
         * @type boolean
         */
        renaming : {
            'value': false
        }
    }
});

M.course.init_section_toolbox = function(config) {
    return new SECTIONTOOLBOX(config);
};

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @module moodle-editor_atto-editor
 * @submodule clean
 */

/**
 * Functions for the Atto editor to clean the generated content.
 *
 * See {{#crossLink "M.editor_atto.Editor"}}{{/crossLink}} for details.
 *
 * @namespace M.editor_atto
 * @class EditorClean
 */

function EditorClean() {}

EditorClean.ATTRS= {
};

EditorClean.prototype = {
    /**
     * Clean the generated HTML content without modifying the editor content.
     *
     * This includes removes all YUI ids from the generated content.
     *
     * @return {string} The cleaned HTML content.
     */
    getCleanHTML: function() {
        // Clone the editor so that we don't actually modify the real content.
        var editorClone = this.editor.cloneNode(true),
            html;

        // Remove all YUI IDs.
        Y.each(editorClone.all('[id^="yui"]'), function(node) {
            node.removeAttribute('id');
        });

        editorClone.all('.atto_control').remove(true);
        html = editorClone.get('innerHTML');

        // Revert untouched editor contents to an empty string.
        if (html === '<p></p>' || html === '<p><br></p>') {
            return '';
        }

        // Remove any and all nasties from source.
       return this._cleanHTML(html);
    },

    /**
     * Clean the HTML content of the editor.
     *
     * @method cleanEditorHTML
     * @chainable
     */
    cleanEditorHTML: function() {
        var startValue = this.editor.get('innerHTML');
        this.editor.set('innerHTML', this._cleanHTML(startValue));

        return this;
    },

    /**
     * Clean the specified HTML content and remove any content which could cause issues.
     *
     * @method _cleanHTML
     * @private
     * @param {String} content The content to clean
     * @return {String} The cleaned HTML
     */
    _cleanHTML: function(content) {
        // Removing limited things that can break the page or a disallowed, like unclosed comments, style blocks, etc.

        var rules = [
            // Remove any style blocks. Some browsers do not work well with them in a contenteditable.
            // Plus style blocks are not allowed in body html, except with "scoped", which most browsers don't support as of 2015.
            // Reference: "http://stackoverflow.com/questions/1068280/javascript-regex-multiline-flag-doesnt-work"
            {regex: /<style[^>]*>[\s\S]*?<\/style>/gi, replace: ""},

            // Source: "http://www.codinghorror.com/blog/2006/01/cleaning-words-nasty-html.html"
            // Remove any obviously disallowed tags: title, meta, style, st0-9, head, font, html, body, link.
            {regex: /<\/?(title|meta|style|st\d|head|html|body|link|!\[)[^>]*?>/gi, replace: ""},
            // Remove any open HTML comment opens that are not followed by a close. This can completely break page layout.
            {regex: /<!--(?!.*?-->)/gi, replace: ""}
        ];

        content = this._filterContentWithRules(content, rules);

        return content;
    },

    /**
     * Take the supplied content and run on the supplied regex rules.
     *
     * @method _filterContentWithRules
     * @private
     * @param {String} content The content to clean
     * @param {Array} rules An array of structures: [ {regex: /something/, replace: "something"}, {...}, ...]
     * @return {String} The cleaned content
     */
    _filterContentWithRules: function(content, rules) {
        var i = 0;
        for (i = 0; i < rules.length; i++) {
            content = content.replace(rules[i].regex, rules[i].replace);
        }

        return content;
    },

    /**
     * Intercept and clean html paste events.
     *
     * @method pasteCleanup
     * @param {Object} source The YUI EventFacade  object
     * @return {Boolean} True if the passed event should continue, false if not.
     */
    pasteCleanup: function(source) {
        // We only expect paste events, but we will check anyways.
        if (source.type === 'paste') {
            // The YUI event wrapper doesn't provide paste event info, so we need the underlying event.
            var event = source._event;
            // Check if we have a valid clipboardData object in the event. IE and older browsers may not.
            if (event && event.clipboardData && event.clipboardData.getData) {
                // Check if there is HTML type to be pasted, this is all we care about.
                var types = event.clipboardData.types;
                var isHTML = false;
                // Different browsers use different things to hold the types, so test various functions.
                if (typeof types.contains === 'function') {
                    isHTML = types.contains('text/html');
                } else if (typeof types.indexOf === 'function') {
                    isHTML = (types.indexOf('text/html') > -1);
                } else {
                    // We don't know how to handle the clipboard info, so wait for the clipboard event to finish then fallback.
                    this.fallbackPasteCleanup();
                    return true;
                }

                if (isHTML) {
                    // Get the clipboard content.
                    var content;
                    try {
                        content = event.clipboardData.getData('text/html');
                    } catch (error) {
                        // Something went wrong. Fallback.
                        this.fallbackPasteCleanup();
                        return true;
                    }

                    // Stop the original paste.
                    source.preventDefault();

                    // Scrub the paste content.
                    content = this._cleanPasteHTML(content);

                    // Save the current selection.
                    // Using saveSelection as it produces a more consistent experience.
                    var selection = window.rangy.saveSelection();

                    // Insert the content.
                    this.insertContentAtFocusPoint(content);

                    // Restore the selection, and collapse to end.
                    window.rangy.restoreSelection(selection);
                    window.rangy.getSelection().collapseToEnd();

                    // Update the text area.
                    this.updateOriginal();
                    return false;
                } else {
                    // This is a non-html paste event, we can just let this continue on and call updateOriginalDelayed.
                    this.updateOriginalDelayed();
                    return true;
                }
            } else {
                // If we reached a here, this probably means the browser has limited (or no) clipboard support.
                // Wait for the clipboard event to finish then fallback.
                this.fallbackPasteCleanup();
                return true;
            }
        }

        // We should never get here - we must have received a non-paste event for some reason.
        // Um, just call updateOriginalDelayed() - it's safe.
        this.updateOriginalDelayed();
        return true;
    },

    /**
     * Setup to capture the paste event in the fallback method. Clipboard capture not supported.
     *
     * @method fallbackPasteCleanup
     * @chainable
     */
    fallbackPasteCleanup: function() {
        Y.log("Clipboard not supported, using fallback paste.", "debug", LOGNAME);
        // Insert a node to "capture" the paste. It must have content or some browsers drop it from the DOM.
        var node = this.insertContentAtFocusPoint('<span>|</span>');

        // Check that the we got the inserted node back.
        if (!node) {
            Y.soon(Y.bind(this._cleanEntireEditorPaste, this));
            return this;
        }

        // Give the node a ID we can lookup later.
        var nodeID = node.generateID();

        // Select the new node contents so the cursor is in the right position for the paste.
        var selection = window.rangy.getSelection();
        var range = selection.getRangeAt(0);
        range.selectNodeContents(node.getDOMNode());
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);

        // Now schedule the remainder of the fallback code to happen after the paste happens.
        Y.soon(Y.bind(this._continueFallbackPasteCleanup, this, this.editor.getHTML().length, node.getHTML().length, nodeID));

        return this;
    },

    /**
     * Continue the paste event fallback after the paste completed.
     *
     * @method _continueFallbackPasteCleanup
     * @param {int} preEditorLength The length of the editor innerHTML before the paste
     * @param {int} preNodeLength The length of the holder node innerHTML before the paste
     * @param {String} nodeID YUI id of the holder node.
     */
    _continueFallbackPasteCleanup: function(preEditorLength, preNodeLength, nodeID) {
        // We re-fetch the node (rather than passing it in) in case it was removed during the paste somehow.
        var node = this.editor.one('#' + nodeID);
        if (!node) {
            // We can't seem to find our node anymore.
            this._cleanPasteEntireEditor();
            return;
        }

        var content = node.getHTML();
        if ((content.length === preNodeLength) && (this.editor.getHTML().length > preEditorLength)) {
            // If our editor got more content during the paste but the node didn't.
            // This happens if the paste didn't occur in our node as intended - as some browsers won't let the cursor move.
            node.remove(true);
            this._cleanPasteEntireEditor();
            return;
        }

        // Save the cursor into the HTML so it can be restored after cleaning.
        var selection = window.rangy.saveSelection();
        content = node.getHTML();

        // Remove the pipe we inserted into our span. Should be at the end of the content area.
        if (content.charAt(content.length - 1) === '|') {
            content = content.slice(0, -1);
        }
        // Clean it.
        content = this._cleanPasteHTML(content);
        node.setHTML(content);

        // Use raw DOM elements because YUI nodes don't consider plain text as a node and we can lose them.
        var domnode = node.getDOMNode();
        // Move the children to it's parent position so we can remove the holder node.
        while (domnode.firstChild) {
            domnode.parentNode.insertBefore(domnode.firstChild, domnode);
        }

        // Remove the now empty span.
        node.remove(true);

        // Restore the cursor position.
        window.rangy.restoreSelection(selection);

        // Update the text area.
        this.updateOriginal();
    },

    /**
     * Do _cleanPasteHTML on the entire editor because we couldn't capture the paste content.
     *
     * @method _cleanPasteEntireEditor
     * @chainable
     */
    _cleanPasteEntireEditor: function() {
        Y.log("Could not capture paste, cleaning entire editor.", "debug", LOGNAME);
        // Save the cursor into the HTML so it can be restored after cleaning.
        var selection = window.rangy.saveSelection();

        var content = this.editor.getHTML();
        this.editor.setHTML(this._cleanPasteHTML(content));

        // Restore the cursor position.
        window.rangy.restoreSelection(selection);

        return this;
    },

    /**
     * Cleanup html that comes from WYSIWYG paste events. These are more likely to contain messy code that we need to strip.
     *
     * @method _cleanPasteHTML
     * @private
     * @param {String} content The html content to clean
     * @return {String} The cleaned HTML
     */
    _cleanPasteHTML: function(content) {
        // Rules that get rid of the real-nasties and don't care about normalize code (correct quotes, line breaks, etc).
        var rules = [
            // Stuff that is specifically from MS Word and similar office packages.
            // Remove if comment blocks.
            {regex: /<!--\[if[\s\S]*?endif\]-->/gi, replace: ""},
            // Remove start and end fragment comment blocks.
            {regex: /<!--(Start|End)Fragment-->/gi, replace: ""},
            // Remove any xml blocks.
            {regex: /<xml[^>]*?>[\s\S]*?<\/xml>/gi, replace: ""},
            // Remove any <?xml><\?xml> blocks.
            {regex: /<\?xml[^>]*?>[\s\S]*?<\\\?xml>/gi, replace: ""},
            // Remove <o:blah>, <\o:blah>.
            {regex: /<\/?\w+:[^>]*?>/gi, replace: ""}

        ];

        // Apply the first set of harsher rules.
        content = this._filterContentWithRules(content, rules);

        // Apply the standard rules, which mainly cleans things like headers, links, and style blocks.
        content = this._cleanHTML(content);

        // Now we let the browser normalize the code by loading it into the DOM and then get the html back.
        // This gives us well quoted, well formatted code to continue our work on. Word may provide very poorly formatted code.
        var holder = document.createElement('div');
        holder.innerHTML = content;
        content = holder.innerHTML;
        // Free up the dom memory.
        holder.innerHTML = "";

        // Run some more rules that care about quotes and whitespace.
        rules = [
            // Remove MSO-blah, MSO:blah (e.g. in style attributes).
            {regex: /\s*MSO[-:][^;"']*;?/gi, replace: ""},
            // Remove class="Msoblah" or class='Msoblah'.
            {regex: /class\s*?=\s*?"Mso[^"]*"/gi, replace: ""},
            // Remove OLE_LINK# anchors that may litter the code.
            {regex: /<a [^>]*?name\s*?=\s*?"OLE_LINK\d*?"[^>]*?>\s*?<\/a>/gi, replace: ""},
            // Remove unused class, style, or id attributes. This will make empty detection easier later.
            {regex: /(class|style|id)\s*?=\s*?"\s*?"/gi, replace: ""}
        ];

        // Apply the rules.
        content = this._filterContentWithRules(content, rules);

        // Reapply the standard cleaner to the content.
        content = this._cleanHTML(content);

        // Clean unused spans out of the content.
        content = this._cleanSpans(content);

        return content;
    },

    /**
     * Clean empty or un-unused spans from passed HTML.
     *
     * This code intentionally doesn't use YUI Nodes. YUI was quite a bit slower at this, so using raw DOM objects instead.
     *
     * @method _cleanSpans
     * @private
     * @param {String} content The content to clean
     * @return {String} The cleaned HTML
     */
    _cleanSpans: function(content) {
        // Reference: "http://stackoverflow.com/questions/8131396/remove-nested-span-without-id"

        // This is better to run detached from the DOM, so the browser doesn't try to update on each change.
        var holder = document.createElement('div');
        holder.innerHTML = content;
        var spans = holder.getElementsByTagName('span');

        // Since we will be removing elements from the list, we should copy it to an array, making it static.
        var spansarr = Array.prototype.slice.call(spans, 0);

        spansarr.forEach(function(span) {
            if (span.innerHTML.length === 0) {
                // If no content, delete node.
                span.parentNode.removeChild(span);
            } else if (!span.hasAttributes()) {
                // If no attributes (id, class, style, etc), move the contents to it's parent position.
                while (span.firstChild) {
                    span.parentNode.insertBefore(span.firstChild, span);
                }

                // Remove the now empty span.
                span.parentNode.removeChild(span);
            }
        });

        return holder.innerHTML;
    }
};

Y.Base.mix(Y.M.editor_atto.Editor, [EditorClean]);

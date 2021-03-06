/*
 * This file is part of the Husky Validation.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

define([
    'type/default'
], function(Default) {

    'use strict';

    var dataChangedHandler = function(data, $el) {
        App.emit('sulu.preview.update', $el, data);
        App.emit('sulu.content.changed');
    };

    return function($el, options) {
        var defaults = {},

            subType = {
                initializeSub: function() {
                    var dataChangedEvent = 'sulu.media-selection.' + options.instanceName + '.data-changed';

                    App.off(dataChangedEvent, dataChangedHandler);
                    App.on(dataChangedEvent, dataChangedHandler);
                },

                setValue: function(value) {
                    // array of objects
                    if(App.util.typeOf(value) === 'array' && App.util.typeOf(value[0]) === 'object') {
                        var ids = [];
                        App.util.foreach(value, function(el) {
                            ids.push(el.id);
                        }.bind(this));

                        value.ids = ids;
                    }

                    App.dom.data($el, 'media-selection', value);

                },

                getValue: function() {
                    return App.dom.data($el, 'media-selection');
                },

                needsValidation: function() {
                    return false;
                },

                validate: function() {
                    return true;
                }
            };

        return new Default($el, defaults, options, 'smartContent', subType);
    };
});

window._formBuilderRules = window._formBuilderRules || [];

/* 	Stores additional action callbacks not declared in original FormBuilder object
	to add callbacks, copy the below line into your javascript file
	then extend the object
	$.extend(window._formBuilderActions, {
		myAction: function({the action data} actionData, {the result of the condition check} result) {
			// do something here
		}
	});
*/
window._formBuilderActions = window._formBuilderActions || {};

/* 	Stores additional state callbacks not declared in original FormBuilder object
	to add callbacks, copy the below line into your javascript file
	then extend the object
	$.extend(window._formBuilderStates, {
		myStateCheck: function({the condition data} condition) {
			// do something here, must return either true or false
		}
	});
*/
window._formBuilderStates = window._formBuilderStates || {};

window._formBuilders = [];

(function($){
	"use strict";

	$.validator.addMethod("minSelections", function(values, element, params) {
		return values.length >= params;
	}, $.validator.format("Please select at least {0} options"));

	$.validator.addMethod("maxSelections", function(values, element, params) {
		return values.length <= params;
	}, $.validator.format("Please select no more than {0} options"));

	window.FormBuilder = function(rulesData) {
		$.extend(this, {
			rulesData: rulesData,
			fieldActions: [],
			form: null,
			formId: null,
			formFields: [],
			validator: null,
			validatorConfig: rulesData.validatorConfig || {},
			_validatorBaseConfig: {
				useNospam: true,
				rules: {},
				messages: {}
			},
			hiddenSelectionValues: {},
			selectFieldOptions: {},
			conditions: {
				onFormLoad: []
			},
			actions: {
				onFormLoad: []
			},
			_formLoaded: true,
			init: function() {
				var me=this;
				me.fieldActions = rulesData.fieldActions;
				me.formId = me.rulesData.formId;
				me.form = $("#" + me.formId);
				me.formFields = me.form.find('input, textarea, select');
				me.selectFieldOptions = me.rulesData.selectFieldOptions;
				me.loadFieldActions(me.fieldActions);
				me.loadValidatorConfig(me.validatorConfig);
				setTimeout(function(){
					me._formLoaded = true;
					me.triggerActions('onFormLoad');
					var keys = Object.keys(me.actions);
					for(var k=0; k < keys.length; k++) {
						if (keys[k] !== 'onFormLoad') {
							me.triggerActions(keys[k]);
						}
					}
				}, 0);
				return me;
			},
			handleError: function(message) {
				console.error(message);
			},
			loadValidatorConfig: function(config) {
				this.validatorConfig = this._validatorBaseConfig;
				$.extend(true, this.validatorConfig, config);
				if (typeof $.validator === 'function') {
					this.validator = this.form.validate(this.validatorConfig);
				}
				return this;
			},
			loadFieldActions: function(fieldActions) {
				this.fieldActions = fieldActions;
				var key;
				for(var i = 0; i < fieldActions.length; i++) {
					key = fieldActions[i].conditionsHash;
					this.addCondition(key, fieldActions[i].conditions);
					this.addAction(key, fieldActions[i].action);
				}
				return this;
			},
			addCondition: function(key, conditions) {
				if (this.conditions[key] === undefined) {
					this.conditions[key] = conditions;
					// bind the key to the field
					var formBuilder = this;
					for(var c=0; c < conditions.length; c++) {
						$(conditions[c].selector).each(function(){
							formBuilder.bindField(this, key);
						});
					}
				}
				return this;
			},
			addAction: function(key, action) {
				this.actions[key] = this.actions[key] || [];
				this.actions[key].push(action);
				return this;
			},
			checkCondition: function(key) {
				// get the conditions to check
				var conditions = this.conditions[key];
				if (conditions === undefined) {
					this.handleError('Conditions missing for key '+key);
					return;
				}
				var stateCallback;
				for(var c = 0; c < conditions.length; c++) {
					stateCallback = conditions[c].stateCallback;
					// find the function to call
					if (this[stateCallback] !== undefined) {
						if (!this[stateCallback](conditions[c])) {
							return false;
						}
					} else if (window._formBuilderStates[stateCallback] !== undefined) {
						if (window._formBuilderStates[stateCallback](conditions[c])) {
							return false;
						}
					} else if (window[stateCallback] !== undefined) {
						if (window[stateCallback](conditions[c])) {
							return false;
						}
					} else {
						this.handleError('Conditional callback ' + stateCallback + ' does not exist');
						continue;
					}
				}
				return true;
			},
			triggerActions: function(key, conditionResult) {
				conditionResult = conditionResult || this.checkCondition(key);
				var actionData ,actionCallbackName;
				for(var a=0; a < this.actions[key].length; a++) {
					actionData = this.actions[key][a];
					actionCallbackName = actionData.callback;
					if (typeof this[actionCallbackName] === 'function') {
						this[actionCallbackName](actionData, conditionResult);
					} else if (typeof window._formBuilderActions[actionCallbackName] === 'function') {
						window._formBuilderActions[actionCallbackName](actionData, conditionResult);
					} else if (typeof window[actionCallbackName] === 'function') {
						window[actionCallbackName](actionData, conditionResult);
					} else {
						this.handleError('Conditional callback ' + actionData.callback + ' does not exist');
					}
				}
			},
			bindField: function(field, key) {
				if (field[0] !== undefined) {
					field = field[0];
				}
				if ($(field).is('option')) {
					return this.bindField($(field).parent('select'), key);
				}
				if ($(field).data('FormBuilderKeys') === undefined) {
					$(field).data('FormBuilderKeys', []);
				}
				$(field).data('FormBuilderKeys').push(key);
				if ($(field).data('FormBuilder') === undefined) {
					$(field).data('FormBuilder', this);
					$(field).on('change', function(){
						var _key;
						var conditionResult;
						for(var k=0; k < $(this).data('FormBuilderKeys').length; k++) {
							$(this).data('FormBuilder').triggerActions($(this).data('FormBuilderKeys')[k]);
						}
					});
				}
			},
			// gets the form main div element for the entire field or field list
			_getFieldContainer: function(selector) {
				// get this target field
				var $target = $(selector);
				// see if there is a wrapper
				if ($target.parents('.fieldgroup-field').length) {
					$target = $target.parents('.fieldgroup-field');
				} else if ($target.parents('.field').length) {
					$target = $target.parents('.field');
				}
				return $target;
			},
			// gets the container where an element is listed with siblings
			// an element removed can be added back into the orignal container
			_getOptionsContainer: function(selector) {
				// get this target field
				var $target = $(selector);
				// see if there is a wrapper
				if ($target.is('option')) {
					$target = $target.parents('select').first();
				} else if ($target.is('[type="radio"],[type="checkbox"]')) {
					$target = $target.parents('ul').first();
				}
				return $target;
			},
			// gets the input parent element that will be removed from it's siblings
			_getSelectionContainer: function(selector) {
				// get this target field
				var $target = $(selector);
				// see if there is a wrapper
				if ($target.is('[type="radio"],[type="checkbox"]')) {
					$target = $target.parents('li, div, label').first();
				}
				return $target;
			},
			// action functions
			// shows a field if the result is true
			actionShowField: function(actionData, result) {
				if (!result) {
					return this.actionHideField(actionData, !result);
				}
				var $target = this._getFieldContainer(actionData.selector);
				$target.show();
			},
			// hides a field if the result is true
			actionHideField: function(actionData, result) {
				if (!result) {
					return this.actionShowField(actionData, !result);
				}
				if ($(actionData.selector).is('input,textarea,select')) {
					if ($(actionData.selector).is('[type="checkbox"],[type="radio"]')) {
						// unchech checkboxes and radios
						$(actionData.selector).prop('checked', false);
					} else {
						// clear out text inputs and dropdowns
						$(actionData.selector).val('');
					}
				} else {
					// unchech checkboxes and radios
					$(actionData.selector).find('[type="checkbox"],[type="radio"]').prop('checked', false);
					// clear out text inputs and dropdowns
					$(actionData.selector).find('input,select,textarea').not('[type="checkbox"],[type="radio"]').val('');
				}
				var $target = this._getFieldContainer(actionData.selector);
				$target.hide();
				return this;
			},
			// adds a selection option if the result is true
			actionShowFieldOption: function(actionData, result) {
				// get this target field
				if (!result) {
					return this.actionHideFieldOption(actionData, !result);
				}
				var $target = this._getSelectionContainer(actionData.selector);
				if (!$target.length) {
					console.warn('Form Builder action to show field option, but field container is not found', actionData);
					return;
				}
				$target.show();
			},
			// removes a selection option if the result is true
			actionHideFieldOption: function(actionData, result) {
				if (!result) {
					return this.actionShowFieldOption(actionData, !result);
				}
				// get this target field
				var $target = this._getSelectionContainer(actionData.selector);
				if (!$target.length) {
					console.warn('Form Builder action to hide field option, but field container is not found', actionData);
					return;
				}
				if ($target.is('option')) {
					$target.prop('selected', false);
				} else {
					$target.prop('checked',false);
				}
				$target.hide();
			},
			// form state functions
			stateOnFormLoad: function(condition) {
				// if we're calling this script, then the form is loaded

				return this._formLoaded;
			},
			// field state functions
			stateMatchAny: function(condition) {
				console.log(condition);
				if ((condition.config.matchValue !== undefined) && (condition.config.matchValue !== undefined) && (condition.config.matchValue.length)) {
					var values = Object.values(condition.config.matchValue);
					var fieldValue = $(condition.selector).first().val().toString();
					console.log('field Value', fieldValue);
					for(var i=0; i<values.length; i++) {
						if (fieldValue === values[i].toString()) {
							console.log('true');
							return true;
						}
					}
				}
				return false;
			},
			stateMatch: function(condition) {
				var $conditionField = $(condition.selector);
				var $conditionFieldValue = $conditionField.first().val();
				if (($conditionField.first().is('input[type="radio"]')) && (condition.selections !== undefined)) {
					$conditionFieldValue = $conditionField.filter(':checked').val();
					for(var c = 0; c < condition.selections.length; c++) {
						if (parseInt($conditionFieldValue,10) === parseInt(condition.selections[c].value,10)) {
							return true;
						}
					}
				} else if (($conditionField.first().is('input[type="checkbox"]')) && (condition.selections !== undefined)) {
					for(var c = 0; c < condition.selections.length; c++) {
						if ($(condition.selections[c].selector).prop('checked')) {
							return true;
						}
					}
				} else if ( ($conditionField.is('select')) ) { //|| ($conditionField.is('input[type="radio"]')) ) {
					if (condition.selections !== undefined) {
						for(var c = 0; c < condition.selections.length; c++) {
							if ($conditionFieldValue === condition.selections[c].value.toString()) {
								return true;
							}
						}
					}
				}
				return false;
			},
			stateIsEmpty: function(condition) {
				return !this.stateHasValue(condition);
			},
			stateHasValue: function(condition) {
				var $conditionField = $(condition.selector);
				if (($conditionField.is('input[type="checkbox"]')) || ($conditionField.is('input[type="radio"]'))) {
					return ($conditionField.filter(':checked').length > 0);
				} else if ($conditionField.is('input.currency')) {
					var floatVal = Math.ceil(parseFloat($conditionField.val().replace(/[^0-9\.]/,'')));
					return (floatVal > 0);
				}
				return ($conditionField.val() !== '');
			}
		});

		this.init(rulesData);

		return this;
	};

	window.FormBuilderCounter = function(element) {
		if (element[0] !== undefined) {
			element = element[0];
		}
		if (element._formBuilderCounter !== undefined) {
			return element._formBuilderCounter;
		}
		element._formBuilderCounter = {
			element: element,
			currentCount: 0,
			countType: '',
			countDisplay: null,
			monitorTimer: 0,
			init: function() {
				this.countType = this.element.dataset.count;
				this.countDisplay = $("<span/>");
				$(this.element).after($("<span/>")
					.addClass('form-builder-counter-display')
					.text(this.countType.charAt(0).toUpperCase() + this.countType.slice(1) + ' Count: ')
					.append(this.countDisplay));
				$(this.element).on('keyup blur', function(){
					this._formBuilderCounter.count();
				});
				$(this.element).on('focus', function(){
					this._formBuilderCounter.monitor();
				});
				this.count();
				return this;
			},
			monitor: function() {
				var me = this;
				me.monitorTimer = setInterval(function(){
					me.count();
				}, 500);
				$(this.element).on('blur', function(){
					clearInterval(me.monitorTimer);
				})
			},
			count: function() {
				switch(this.countType) {
					case 'word':
					case 'words':
						return this.countWords();
					case 'character':
					case 'characters':
						return this.countCharacters();
				}
				return this;
			},
			countWords: function() {
				if (this.element.value === "") {
					return this._displayCount(0);
				}
				return this._displayCount(this.element
					.value	// get the field valule
					.trim()	// trim off any white space
					.replace(/\s{2,}/,' ')	// correct any double spaces
					.split(' ')	// split by the space character
					.length);	// count the words
			},
			countCharacters: function() {
				if (this.element.value === "") {
					return this._displayCount(0);
				}
				return this._displayCount(this.element
					.value	// get the field value
					.split('')	// split every character
					.length);	// count the characters
			},
			_displayCount: function(num) {
				if (parseInt($(this.element).attr('data-current-count')) !== parseInt(num)) {
					this.countDisplay.text(num);
					$(this.element).attr('data-current-count', num).trigger('count-changed').trigger('change');
				}
				return this;
			}
		};
		return element._formBuilderCounter.init();
	};

	$(document).ready(function(){
		for(var r = 0; r < window._formBuilderRules.length; r++) {
			window._formBuilders.push(new window.FormBuilder(window._formBuilderRules[r]));
		}
		$("form[data-form-builder]").find('textarea.show-counter[data-count]').each(function(){
			window.FormBuilderCounter(this);
		});
	});
}(jQuery));
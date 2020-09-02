(function($){
	"use strict";
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
			validatorConfig: {},
			_validatorBaseConfig: {
				useNospam: true,
				rules: {},
				messages: {}
			},
			hiddenDropdownValues: {},
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
						formBuilder.handleError('Conditional callback ' + actionData.callback + ' does not exist');
					}
				}
			},
			bindField: function(field, key) {
				if (field[0] !== undefined) {
					field = field[0];
				}
				field.FormBuilderKeys = field.FormBuilderKeys || [];
				field.FormBuilderKeys.push(key);
				if (field.FormBuilder === undefined) {
					field.FormBuilder = this;
					$(field).change(function(){
						var _key;
						var conditionResult;
						for(var k=0; k < this.FormBuilderKeys.length; k++) {
							this.FormBuilder.triggerActions(this.FormBuilderKeys[k]);
						}
					});
				}
			},
			_getFieldContainer: function(selector) {
				// get this target field
				var $target = $(selector);
				// see if there is a wrapper
				if ($target.is('option')) {
					$target = $target.parents('select').first();
				} else if ($target.parents('.fieldgroup-field').length) {
					$target = $target.parents('.fieldgroup-field');
				} else if ($target.parents('.field').length) {
					$target = $target.parents('.field');
				}
				return $target;
			},
			// action functions
			// shows a field if the result is true
			actionShowField: function(actionData, result) {
				if (!result) {
					return this.actionHideField(actionData, !result);
				}
				return this._getFieldContainer(actionData.selector).show();
			},
			// hides a field if the result is true
			actionHideField: function(actionData, result) {
				if (!result) {
					return this.actionShowField(actionData, !result);
				}
				$(actionData.selector).prop('checked', false);
				return this._getFieldContainer(actionData.selector).hide();
			},
			// adds a selection option if the result is true
			actionShowFieldOption: function(actionData, result) {
				// get this target field
				if (!result) {
					return this.actionHideFieldOption(actionData, !result);
				}
				var $target = $(actionData.fieldSelector);
				if ($target.length) {
					console.warn('Action "actionShowFieldOption" called when option is already shown');
					return;
				}
				var $field = this._getFieldContainer(actionData.fieldSelector);
				if (!$field.length) {
					console.warn('Form Builder action to show field option, but field container is not found', actionData);
					return;
				}
				if (this.hiddenDropdownValues[actionData.selector] === undefined) {
					console.error('Dropdown element not cached for injection', actionData);
					return;
				}
				if (this.selectFieldOptions[actionData.fieldSelector] === undefined) {
					console.error('Original options not provided to inject removed value', actionData);
					return;
				}
				var insertBeforeIndex = 0;
				for(var o=0; o < actionData.allOptions.length; o++) {
					// did we get to the affected option in the list
					if (actionData.allOptions[o].selector === actionData.selector) {
						switch (actionData.fieldType) {
							case 'Dropdown':
								// if the first value is an empty value, increment one more
								if ($field.find('option').first().attr('value') === '') {
									insertBeforeIndex++;
								}
								$field.find('> *').eq(insertBeforeIndex).before(this.hiddenDropdownValues[actionData.selector]);
								break;
							case 'Radio Buttons':
							case 'Checkbox':
								$field.find('> *').eq(insertBeforeIndex).before(this.hiddenDropdownValues[actionData.selector]);
								break;
							default:
								console.warn('Injection not setup for '+actionData.fieldType+' type field');
						}
						return;
					// if the current parsed option exists, update the injection index
					} else if ($(actionData.allOptions[o].selector).length) {
						insertBeforeIndex++;
					}
				}
			},
			// removes a selection option if the result is true
			actionHideFieldOption: function(actionData, result) {
				// get this target field
				if (!result) {
					return this.actionShowFieldOption(actionData, !result);
				}
				var $target = this._getFieldContainer(actionData.fieldSelector);
				if (!$target.length) {
					console.warn('Form Builder action to hide field option, but field container is not found', actionData);
					return;
				}
				switch (actionData.fieldType) {
					case 'Dropdown':
						break;
					case 'Radio Buttons':
					case 'Checkbox':
						$target = this._getFieldContainer(actionData.selector);
						break;
					default:
						console.warn('Injection not setup for '+actionData.fieldType+' type field');
				}
				this.hiddenDropdownValues[actionData.selector] = $target.clone();
				$target.remove();
			},
			stateOnFormLoad: function(condition) {
				// if we're calling this script, then the form is loaded
				return this._formLoaded;
			},
			// field state functions
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
							if ($conditionFieldValue === condition.selections[c].value) {
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
				}
				return ($conditionField.val() !== '');
			}
		});
		
		this.init(rulesData);
		
		return this;
	};
	window._formBuilders = [];
	$(document).ready(function(){
		for(var r = 0; r < window._formBuilderRules.length; r++) {
			window._formBuilders.push(new window.FormBuilder(window._formBuilderRules[r]));
		}
	});
}(jQuery));
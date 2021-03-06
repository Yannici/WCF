/**
 * Bootstraps WCF's JavaScript.
 * It defines globals needed for backwards compatibility
 * and runs modules that are needed on page load.
 * 
 * @author	Tim Duesterhus
 * @copyright	2001-2017 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @module	WoltLabSuite/Core/Bootstrap
 */
define(
	[
		'favico',                  'enquire',                'perfect-scrollbar',      'WoltLabSuite/Core/Date/Time/Relative',
		'Ui/SimpleDropdown',       'WoltLabSuite/Core/Ui/Mobile',  'WoltLabSuite/Core/Ui/TabMenu', 'WoltLabSuite/Core/Ui/FlexibleMenu',
		'Ui/Dialog',               'WoltLabSuite/Core/Ui/Tooltip', 'WoltLabSuite/Core/Language',   'WoltLabSuite/Core/Environment',
		'WoltLabSuite/Core/Date/Picker', 'EventHandler',           'Core',                   'WoltLabSuite/Core/Ui/Page/JumpToTop'
	], 
	function(
		 favico,                   enquire,                  perfectScrollbar,         DateTimeRelative,
		 UiSimpleDropdown,         UiMobile,                 UiTabMenu,                UiFlexibleMenu,
		 UiDialog,                 UiTooltip,                Language,                 Environment,
		 DatePicker,               EventHandler,             Core,                     UiPageJumpToTop
	)
{
	"use strict";
	
	// perfectScrollbar does not need to be bound anywhere, it just has to be loaded for WCF.js
	window.Favico = favico;
	window.enquire = enquire;
	// non strict equals by intent
	if (window.WCF == null) window.WCF = { };
	if (window.WCF.Language == null) window.WCF.Language = { };
	window.WCF.Language.get = Language.get;
	window.WCF.Language.add = Language.add;
	window.WCF.Language.addObject = Language.addObject;
	
	// WCF.System.Event compatibility
	window.__wcf_bc_eventHandler = EventHandler;
	
	/**
	 * @exports	WoltLabSuite/Core/Bootstrap
	 */
	return {
		/**
		 * Initializes the core UI modifications and unblocks jQuery's ready event.
		 * 
		 * @param       {Object=}       options         initialization options
		 */
		setup: function(options) {
			options = Core.extend({
				enableMobileMenu: true
			}, options);
			
			Environment.setup();
			
			DateTimeRelative.setup();
			DatePicker.init();
			
			UiSimpleDropdown.setup();
			UiMobile.setup({
				enableMobileMenu: options.enableMobileMenu
			});
			UiTabMenu.setup();
			//UiFlexibleMenu.setup();
			UiDialog.setup();
			UiTooltip.setup();
			
			new UiPageJumpToTop();
			
			// convert method=get into method=post
			var forms = elBySelAll('form[method=get]');
			for (var i = 0, length = forms.length; i < length; i++) {
				forms[i].setAttribute('method', 'post');
			}
			
			if (Environment.browser() === 'microsoft') {
				window.onbeforeunload = function() {
					/* Prevent "Back navigation caching" (http://msdn.microsoft.com/en-us/library/ie/dn265017%28v=vs.85%29.aspx) */
				};
			}
			
			// DEBUG ONLY
			var interval = 0;
			interval = window.setInterval(function() {
				if (typeof window.jQuery === 'function') {
					window.clearInterval(interval);
					
					window.jQuery.holdReady(false);
				}
			}, 20);
		}
	};
});

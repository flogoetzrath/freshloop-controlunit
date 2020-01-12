/**
 * @name: main.js
 * @description: Global JavaScript file
 * @author: Florian Götzrath <info@floriangoetzrath.de>
 */

$(document).ready(function()
{
	
	const navbar = $(".component-navbar");
	const navbar_clone = navbar.clone();
	
	/**
	 * The scroll event
	 */
	$(window).on('scroll', function()
	{
		/* *Discontinued*
		
		let y_scroll_pos = window.pageYOffset;
		let scroll_pos_navtrigger = navbar[0].offsetHeight;
		
		if(y_scroll_pos > scroll_pos_navtrigger)
		{
			navbar_clone.appendTo("body");
			navbar_clone.addClass("component-navbar-active");
			navbar_clone.slideDown(150);
		}
		else navbar_clone.slideUp();
		
		*/
	});
	// scroll event
	
	/**
	 * Initiate toogle functionality
	 */
	$('[data-toggle="tooltip"]').tooltip();
	// Toggle functionality
	
	/**
	 * Adds to every element in the DOM with the same class as a given element
	 * a spcific class with an ID to eventually returns the searched element as a jQuery object
	 *
	 * @param jsEl
	 * @param specificClass
	 * @returns {jQuery|HTMLElement}
	 */
	function jsElTojQueryObj(jsEl, specificClass)
	{
		
		function jsElTojQueryObj(jsEl, specificClass)
		{
			
			let id = jsEl.id ? `#${jsEl.id}` : "";
			let classes = "";
			let counter = 0;
			
			if(typeof jsEl !== "object" || jsEl.length <= 1 && jsEl[0] === "#document") return;
			jsEl.classList.add(`${specificClass}-${counter++}`);
			
			if(jsEl.classList && jsEl.classList.length > 0)
			{
				
				jsEl.classList.forEach(className =>
				{
					if (!classes.includes(`.${className}`)) classes += `.${className}`;
				});
				
			}
			
			return $(`${jsEl.tagName.toLowerCase()}${id}${classes}`);
			
		} // function jsElTojQueryObj(jsEl, specificClass)
		
	} // function jsElTojQueryObj(jsEl, specificClass)
	
	/**
	 * Simulates a mouse click
	 *
	 * @param {HTMLElement} el
	 */
	function simulateClick(el)
	{
		
		let evt;
		
		if (document.createEvent)
		{
			
			evt = document.createEvent("MouseEvents");
			evt.initMouseEvent("click", true, true, window, 0, 0, 0, 0, 0, false, false, false, false, 0, null);
		
		}
		
		(evt) ? el.dispatchEvent(evt) : (el.click && el.click());
		
	} // function simulateClick()
	
});
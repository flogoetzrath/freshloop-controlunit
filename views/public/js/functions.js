/**
 * Array difference addition
 *
 * @param a
 * @returns {*[]}
 */
Array.prototype.diff = function(a) {
	return this.filter(function(i) {return a.indexOf(i) < 0;});
};

/**
 * jQuery outerHTML integration
 *
 * @returns {*|jQuery}
 */
jQuery.fn.outerHTML = function() {
	return jQuery('<div />').append(this.eq(0).clone()).html();
}; // jQuery.fn.outerHTML()

/**
 * Moves an element in the DOM
 *
 * @param {String} initialSelector
 * @param {String} newParentSelector
 * @param {boolean} appendToEnd
 */
function moveElinDOM(initialSelector, newParentSelector, appendToEnd = false)
{
	
	let initialEl = $(initialSelector);
	let newParentEl = $(newParentSelector);
	
	if(appendToEnd) newParentEl.append(initialEl.outerHTML());
	else newParentEl.prepend(initialEl.outerHTML());
	
	initialEl.detach();
	
} // function moveElinDOM()
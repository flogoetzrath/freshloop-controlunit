/**
 * @name: admin.js
 * @description: Global JavaScript file concerning the admin area
 * @author: Florian Götzrath <info@floriangoetzrath.de>
 */

let burgerMenuHandler;
let sidebarHandler;
let navbarsearchHandler;

window.onload = () => {
	
	/* Preloader */
	let preloader = document.querySelector('#preloader');
	$(preloader).delay(400).animate({ left: "-300%" });
	
	/* Media Queries */
	let mql980 = window.matchMedia("(max-width: 980px)");
	
	/* Event Binding */
	if(mql980) {
		// Init Burger Menu
		burgerMenuHandler = new BurgerMenuHandler();
		// Init SVG Burger Trigger Animation and Behavior
		initSVGBurgerTrigger();
	}
	
	/* Init SidebarHandler */
	sidebarHandler = new SidebarHandler();
	
	/* Init NavbarSearchHandler */
	navbarsearchHandler = new NavbarSearchHandler();
	
	/* Manage Widget carousel(s) */
	$('.unit_carousel_widget').slick({
		dots: false,
		variableWidth: false,
		mobileFirst: true,
		prevArrow: '<i class="fas fa-chevron-left unit_carousel_widget_prev"></i>',
		nextArrow: '<i class="fas fa-chevron-right unit_carousel_widget_next"></i>'
	});
	
	/* UI Calculation */
	$(".admin-container-left").css({
		"height": (parseInt($(".admin-container-right").height()) + parseInt($("#navbar").height()) + 1).toString() + "px"
	});
	
};

window.onresize = () => {
	
	if(burgerMenuHandler) burgerMenuHandler.resizeHook();
	
};

/**
 * Initializes the burger trigger svg's look and onclick animation
 */
function initSVGBurgerTrigger()
{
	
	let circlehider = document.getElementById("circle");
	circlehider.style.opacity = "0";
	
	Moveit.put(circle,{
		start:"99.5%",
		end:"99.5%"
	});
	Moveit.put(topline,{
		start:"0%",
		end:"40%"
	});
	Moveit.put(middle,{
		start:"0%",
		end:"13%"
	});
	Moveit.put(bottom,{
		start:"0%",
		end:"40%"
	});
	var clicker = false;
	$(".burger-trigger").click(function(){
		if(!clicker){
			document.getElementById("circle").style.transitionDelay = "5s";
			circlehider.style.opacity = "1";
			Moveit.animate(topline,{
				start:"44.45%",
				end:"95.5%",
				duration:0.4,
				delay:0,
				timing:"ease-in",
			});
			Moveit.animate(bottom,{
				start:"44.45%",
				end:"95.5%",
				duration:0.4,
				delay:0,
				timing:"ease-in",
			});
			Moveit.animate(middle,{
				start:"18.3%",
				end:"18.3%",
				duration:0.2,
				delay:0,
				timing:"ease-in",
			});
			Moveit.animate(circle,{
				start:"0%",
				end:"100%",
				duration:0.2,
				delay:0.2,
				timing:"ease-in",
			})
		}
		else{
			Moveit.animate(topline,{
				start:"0%",
				end:"40%",
				duration:0.4,
				delay:0,
				timing:"ease-in",
			});
			Moveit.animate(bottom,{
				start:"0%",
				end:"40%",
				duration:0.4,
				delay:0,
				timing:"ease-in",
			});
			Moveit.animate(middle,{
				start:"0%",
				end:"13%",
				duration:0.2,
				delay:0,
				timing:"ease-in",
			});
			Moveit.animate(circle,{
				start:"99.5%",
				end:"99.5%",
				duration:0.2,
				delay:0.2,
				timing:"ease-in",
			});
			
			circlehider.style.opacity = "0";
		}
		clicker = !clicker;
	});

} // function initSVGBurgerTrigger()

/**
 * Adds a toast notification
 *
 * @param {String} msg
 * @param {String} type
 * @param {Number} duration
 * @param {Object} options
 */
function addToastNotification(msg, type, duration = 5000, options = null)
{
	
	let types = [
		"success",
		"info",
		"warning",
		"error"
	];
	
	if(!types.includes(type)) type = "info";
	
	if(typeof options !== null) toastr.options = options;
	else toastr.options = {
		"closeButton": true,
		"debug": false,
		"newestOnTop": true,
		"progressBar": true,
		"positionClass": "toast-bottom-right",
		"preventDuplicates": false,
		"onclick": null,
		"showDuration": "300",
		"hideDuration": "5000",
		"timeOut": duration,
		"extendedTimeOut": "1000",
		"showEasing": "swing",
		"hideEasing": "linear",
		"showMethod": "fadeIn",
		"hideMethod": "fadeOut"
	};
	
	// Render Toast
	toastr[type](msg);
	
} // function addToastNotification()

/**
 * Handles and Administers Burger Menu Actions starting at a width of 1080px
 */
class BurgerMenuHandler {
	
	/**
	 * BurgerMenuHandler constructor
	 */
	constructor()
	{
	
		this.burgerMenu = document.querySelector("#navbarBurgerTarget");
		this.burgerTrigger = document.querySelector(".burger-trigger");
		
		this.navigationBar = document.querySelector("#navbar");
		this.adminContainerRight = document.querySelector(".admin-container-right");
		
		this.width = $(window).width();
		this.onclickCallback = null;
		
		this.initJSMediaQueries();
	
	} // constructor()
	
	/**
	 * Called from outside on window resize
	 */
	resizeHook()
	{
		
		this.width = $(window).width();
		this.initJSMediaQueries();
		
	} // resizeHook()
	
	/**
	 * Acts as an intermediary between script and functionality
	 * in order to administer changes regarding the viewport width
	 */
	initJSMediaQueries()
	{
		
		// Main breakpoint to enable the burgermenu
		if(this.width < 1080 && this.width >= 320)
		{
			
			this.moveBurgerMenuInDOM();
			this.moveBurgerMenuDropDownShows();
			this.initBehavior();
			
		}
		else
		{
			
			this.createInitialPlacing();
			
		}
		
	} // initJSMediaQueries()
	
	/**
	 *  (Re)establishes the inital element placing in the DOM
	 */
	createInitialPlacing()
	{
	
		if(this.burgerMenu.previousElementSibling === this.burgerTrigger) return false;
		else
		{
			
			this.burgerTrigger.insertAdjacentElement("afterend", this.burgerMenu);
			this.moveBurgerMenuDropDownShows();
			
			this.burgerTrigger.removeEventListener("click", this.onclickCallback);
			
		}
	
	} // createInitialPlacing()
	
	/**
	 * Moves the burger menu to its expected position in the DOM
	 */
	moveBurgerMenuInDOM()
	{
		
		// If item has already been moved
		if(!this.navigationBar.querySelector("#navbarBurgerTarget")) return false;
		
		// Move element
		this.adminContainerRight.insertAdjacentElement('afterend', this.burgerMenu);
		
	} // moveBurgerMenuInDOM()
	
	/**
	 * Toggles inital position and being layered after its parent for mobile view
	 */
	moveBurgerMenuDropDownShows()
	{
	
		let dropDownShows = this.burgerMenu.querySelectorAll(".dropdown-menu");
		
		let moveToParent = dropdown => {
			dropdown.parentElement.insertAdjacentElement("afterend", dropdown);
		};
		
		let moveToDropDownTrigger = dropdown => {
			if(this.width < 1080) return false;
			dropdown.previousElementSibling.insertAdjacentElement("beforeend", dropdown);
		};
		
		dropDownShows.forEach((dropdown, i) => {
			if(i + 1 === dropDownShows.length) return;
			
			if(!dropdown.parentElement.classList.contains("control") && this.width < 1080)
				moveToParent(dropdown);
			else moveToDropDownTrigger(dropdown);
		});
	
	} // moveBurgerMenuDropDownShows()
	
	/**
	 * Initiates the behavior of the burger menus onclick
	 */
	initBehavior()
	{
		
		if(this.onclickCallback == null)
		{
			
			this.onclickCallback = () => {
				this.adminContainerRight.classList.toggle("burgerAnimationTranslateLeft");
				this.burgerMenu.classList.toggle("burgerAnimationTranslateLeft");
			};
			
		}
		
		this.burgerTrigger.addEventListener('click', this.onclickCallback);
		
	} // initBehavior()

} // class BurgerMenuHandler

/**
 * Handles and Administers Sidebar Actions
 */
class SidebarHandler {
	
	/**
	 * SidebarHandler constructor
	 */
	constructor()
	{
		
		this.firstCastOnMobile = false;
		
		this.toggleSidebarBtns = document.querySelectorAll(".toggleSidebar");
		this.sidebar = document.querySelector(".sidebar");
		this.width = $(window).width();
		
		this.sidebarIsToggled = JSON.parse(localStorage.getItem('sidebarIsToggled')) || false;
		this.sidebarDropdownIsToggled = JSON.parse(localStorage.getItem('sidebarDropdownIsToggled')) || false;
		
		this.initJSMediaQueries();
		this.bindEvents();
		
	} // constructor()
	
	/**
	 * Initiates js media queries
	 */
	initJSMediaQueries()
	{
		
		if(this.width < 1400)
		{
			
			this.toggleSidebar();
			this.firstCastOnMobile = true;
			
		}
		
	} // initJSMediaQueries()
	
	/**
	 * Registers Event binding
	 */
	bindEvents()
	{
		
		// Dropdown toggle
		if(this.sidebarDropdownIsToggled) this.toggleSidebarDropdown($(".admin-dropdown-toggle a"), false);
		$(".admin-dropdown-toggle a").on('click', e => this.toggleSidebarDropdown(e.target));
		$(".admin-dropdown-chevron").on('click', e => this.toggleSidebarDropdown($(e.target).closest(".admin-dropdown-main")[0]));
		
		// Dropdown hover
		let initDropdownHover = () =>
		{
			
			if(this.width < 1400 && this.width >= 330) this.activateAbsoluteSidebar();
			else this.deactivateAbsoluteSidebar();
			
		};
		
		initDropdownHover();
		$(window).resize(() => initDropdownHover());
		
		// Init sidebar behavior
		this.toggleSidebarBtns.forEach(btn => { btn.addEventListener('click', () => this.toggleSidebar()); });
		
	} // bindEvents()
	
	/**
	 * Toggles sidebar
	 */
	toggleSidebar(e = null)
	{
	
		// Reinit slick carousel on main dashboard page if existing
		// $('.unit_carousel_widget').slick('resize');
		// $('.unit_carousel_widget').slick('reinit');
		
		// Actually toggle the sidebar
		if(!this.sidebar) return false;
		
		if(this.sidebar.parentElement.style.display === "none")
			this.sidebar.parentElement.style.display = "block";
		
		this.sidebar.style.display = "block";
		
		/**
		 * Toggles the class "navToggle"
		 */
		let switchNavToggle = () => {
			this.sidebar.parentElement.parentElement.classList.toggle('navToggle');
		}; // inline function switchNavToggle()
		
		if(this.firstCastOnMobile)
		{
			
			// Delay animation and give JS time to add the display block property
			setTimeout(() => switchNavToggle(), 1);
			
		} else switchNavToggle();
		
	} // toggleSidebar()
	
	/**
	 * Sidebar subpages toggle
	 *
	 * @param el
	 * @param watchStorage
	 */
	toggleSidebarDropdown(el, watchStorage = true)
	{
		
		if(watchStorage)
		{
			if(typeof localStorage.getItem('sidebarDropdownIsToggled') === "undefined")
				localStorage.setItem('sidebarDropdownIsToggled', "true");
			else
			{
				
				let storageDropdownVal = !JSON.parse(localStorage.getItem('sidebarDropdownIsToggled'));
				localStorage.setItem('sidebarDropdownIsToggled', storageDropdownVal.toString());
				
			}
		}
		
		let dropdown = $(el).next();
		let chevron = $(el).find(".admin-dropdown-chevron");
		
		dropdown.slideToggle();
		chevron.toggleClass("fa-chevron-down fa-minus", "slow");
		
	} // toggleSidebarDropdown()
	
	/**
	 * Activates the hovering sidebar
	 */
	activateAbsoluteSidebar()
	{
		
		$(".admin-container-left").addClass("sidebar-hover-container");
		
		this.sidebar.style.display = "none";
		this.sidebar.parentElement.style.display = "none";
		
	} // activateAbsoluteSidebar()
	
	/**
	 * Deactivates the hovering sidebar
	 */
	deactivateAbsoluteSidebar()
	{
		
		this.sidebar.style.display = "block";
		$(".admin-container-left").removeClass("sidebar-hover-container");
		
	} // deactivateAbsoluteSidebar()
	
} // class SidebarHandler

/**
 * Handles AJAX search requests and fills the result area box
 */
class NavbarSearchHandler {

	constructor() {
	
		// Get crucial elements
		this.navbarsearch_trigger = document.querySelector(".navbarsearch-trigger");
		this.navbarsearch_modal = document.getElementById('searchInput_modal');
		this.search_box = document.querySelector(".search_box");
		this.results_area = document.querySelector(".result_area");
		
		this.noResultMarkup = this.results_area.innerHTML;
		
		this.closeModal = () => {
			this.navbarsearch_modal.classList.remove("is-active");
			this.search_box.value = "";
			this.results_area.innerHTML = this.noResultMarkup;
		};
		
		// Open Modal Event
		this.navbarsearch_trigger.addEventListener("click", e => {
			e.preventDefault();
			this.navbarsearch_modal.classList.toggle('is-active');
			this.search_box.focus();
		});
		
		this.navbarsearch_trigger.addEventListener("keydown", e => e.preventDefault());
		
		// Close Modal Events
		document.querySelector(".modal-background").addEventListener("click", this.closeModal);
		document.querySelector(".modal-close").addEventListener("click", this.closeModal);
		
		// Init AJAX calls
		this.search_box.addEventListener("keyup", e => this.processAJAXResults(e.target.value));
		
	} // constructor()
	
	/**
	 * Sends the AJAX request and fills the results area with the response
	 *
	 * @param searchStr
	 */
	processAJAXResults(searchStr) {
	
		// If the string is not sized
		if(searchStr.length === 0) return this.results_area.innerHTML = this.noResultMarkup;
		
		// Init the request object
		let xmlhttp;
		let noResultMarkup = this.noResultMarkup;
		let results_area = this.results_area;
		
		if(window.XMLHttpRequest) xmlhttp = new XMLHttpRequest();
		else xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		
		// Set up action for after getting a response
		xmlhttp.onreadystatechange = function() {
			if(this.readyState === 4 && this.status === 200) {
				let res = this.responseText;
				res = res.substring(res.lastIndexOf("</style>") + 8, res.length);
				res = JSON.parse(res);
				
				if(Object.keys(res).length === 0) results_area.innerHTML = noResultMarkup;
				else results_area.innerHTML = "";
				
				Object.keys(res).forEach(key => {
					let markup = `
						<a href='${res[key]}' class="row">
							<div class="col-md-6">${key}</div>
							<div class="col-md-6 text-right">${res[key]}</div>
						</a>
					`;
					
					if(!results_area.innerHTML.includes(markup))
						results_area.innerHTML += markup;
				});
			}
		};
		
		// Issue Request
		xmlhttp.open("GET", "?page=Admin&action=navbarSearchRequest&q="+searchStr, true);
		xmlhttp.send();
	
	} // processAJAXResults()
	
} // class NavbarSearchHandler